<?php
/**
 * The Link Fixer's background jobs: list them and run them from the panel.
 *
 * @package LinkFixer_Harness
 */

namespace LinkFixer_Harness;

use Internet_Archive\Wayback_Machine_Link_Fixer\Link\Link_Repository;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Lists the Link Fixer's Action Scheduler jobs and runs them on demand.
 */
class Queue {

	/**
	 * The Link Fixer's job hooks, with a plain English name.
	 */
	private const HOOKS = array(
		'iawmlf_find_or_create_snapshot' => 'Find or create a snapshot',
		'iawmlf_create_new_snapshot'     => 'Create a new snapshot',
		'iawmlf_check_snapshot_status'   => 'Check the new snapshot',
		'iawmlf_update_archive_url'      => 'Update the archive URL',
		'iawmlf_link_access_validator'   => 'Verify the link allows checking',
		'iawmlf_check_validator_status'  => 'Check the verification',
		'iawmlf_scan_existing_posts'     => 'Scan existing posts',
		'iawmlf_process_local_post'      => 'Archive one of your posts',
		'iawmlf_add_own_posts'           => 'Queue your posts for archiving',
	);

	/**
	 * Registers the hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'rest_api_init', array( self::class, 'register_routes' ) );
	}

	/**
	 * Stops Action Scheduler running jobs by itself (async runner and WP-Cron runner), so only the panel runs them.
	 *
	 * With no concurrent batches allowed, has_maximum_concurrent_batches() is always true and neither runner starts.
	 * process_action() for a single job, which the panel uses, does not check it.
	 *
	 * @return void
	 */
	public static function manual(): void {
		add_filter( 'action_scheduler_allow_async_request_runner', '__return_false', 999 );
		add_filter( 'action_scheduler_queue_runner_concurrent_batches', '__return_zero', 999 );
	}

	/**
	 * Registers the routes, administrators only.
	 *
	 * @return void
	 */
	public static function register_routes(): void {
		register_rest_route(
			Rest::NAMESPACE,
			'/queue',
			array(
				'methods'             => 'GET',
				'callback'            => static fn() => new WP_REST_Response( self::snapshot() ),
				'permission_callback' => static fn() => current_user_can( 'manage_options' ),
			)
		);
		register_rest_route(
			Rest::NAMESPACE,
			'/queue/run',
			array(
				'methods'             => 'POST',
				'callback'            => static fn( WP_REST_Request $request ) => new WP_REST_Response( self::run( 'all' === $request->get_param( 'mode' ) ? 'all' : 'next' ) ),
				'permission_callback' => static fn() => current_user_can( 'manage_options' ),
			)
		);
	}

	/**
	 * Pending jobs, earliest first, and the most recent finished or failed ones.
	 *
	 * @return array{pending:array, recent:array}
	 */
	public static function snapshot(): array {
		$recent = array_merge( self::actions( 'complete' ), self::actions( 'failed' ) );
		usort( $recent, static fn( $a, $b ) => $b['time'] <=> $a['time'] ?: $b['id'] <=> $a['id'] );

		return array(
			'pending' => self::actions( 'pending' ),
			'recent'  => array_slice( $recent, 0, 15 ),
		);
	}

	/**
	 * Runs the next pending job whatever its due time, or all of them.
	 *
	 * @param string $mode next or all.
	 *
	 * @return array{ran:array, queue:array}
	 */
	public static function run( string $mode ): array {
		$ran = array();
		$max = 'all' === $mode ? 60 : 1;

		for ( $i = 0; $i < $max; $i++ ) {
			$pending = self::actions( 'pending' );
			if ( empty( $pending ) ) {
				break;
			}
			\ActionScheduler::runner()->process_action( $pending[0]['id'], 'LinkFixer Harness' );
			$ran[] = self::describe( $pending[0]['id'] );
		}

		return array(
			'ran'   => $ran,
			'queue' => self::snapshot(),
		);
	}

	/**
	 * Jobs with a status, pending ones earliest first.
	 *
	 * @param string $status pending, complete or failed.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function actions( string $status ): array {
		$ids = array();
		foreach ( array_keys( self::HOOKS ) as $hook ) {
			$ids = array_merge(
				$ids,
				array_map(
					'intval',
					(array) as_get_scheduled_actions(
						array(
							'hook'     => $hook,
							'status'   => $status,
							'per_page' => 100,
						),
						'ids'
					)
				)
			);
		}

		$actions = array_map( array( self::class, 'describe' ), $ids );
		usort( $actions, static fn( $a, $b ) => $a['time'] <=> $b['time'] ?: $a['id'] <=> $b['id'] );

		return $actions;
	}

	/**
	 * One job, for the panel.
	 *
	 * @param integer $id The action id.
	 *
	 * @return array<string, mixed>
	 */
	private static function describe( int $id ): array {
		$store  = \ActionScheduler::store();
		$action = $store->fetch_action( (string) $id );
		$args   = (array) $action->get_args();
		$time   = $store->get_date( $id )->getTimestamp();

		$link = null;
		if ( isset( $args['link_id'] ) ) {
			$link = ( new Link_Repository() )->find_by_id( (int) $args['link_id'] );
		}

		$logs = \ActionScheduler::logger()->get_logs( $id );
		$last = empty( $logs ) ? null : end( $logs );

		return array(
			'id'      => $id,
			'hook'    => $action->get_hook(),
			'label'   => self::HOOKS[ $action->get_hook() ] ?? $action->get_hook(),
			'status'  => $store->get_status( (string) $id ),
			'link'    => null === $link ? '' : $link->get_href(),
			'attempt' => $args['attempt'] ?? null,
			'time'    => $time,
			'in'      => $time - time(),
			'message' => null === $last ? '' : $last->get_message(),
		);
	}
}
