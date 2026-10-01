<?php
/**
 * Scenario: failed retry jobs, old and recent, for the daily clean-up.
 *
 * @package LinkFixer_Harness
 */

namespace LinkFixer_Harness;

defined( 'ABSPATH' ) || exit;

/**
 * Failed job clean-up.
 */
class Scenario_Housekeeping extends Scenario {

	/**
	 * The clean-up job.
	 */
	public const HOOK = 'iawmlf_failed_event_garbage_collection';

	/**
	 * Slug.
	 *
	 * @return string
	 */
	public function slug(): string {
		return 'housekeeping';
	}

	/**
	 * Plain English name.
	 *
	 * @return string
	 */
	public function title(): string {
		return 'Failed job clean-up';
	}

	/**
	 * What it proves.
	 *
	 * @return string
	 */
	public function summary(): string {
		return 'The daily clean-up removes failed retry jobs older than 7 days, keeping the last attempt of each, and leaves newer ones alone.';
	}

	/**
	 * One post.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function posts(): array {
		return array(
			'jobs' => array(
				'title' => 'Harness: links with failed jobs',
				'intro' => 'H1 has failed retry jobs from 10 days ago, H2 from 2 days ago.',
			),
		);
	}

	/**
	 * H1 and H2.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function links(): array {
		return array(
			self::link(
				array(
					'id'      => 'H1',
					'label'   => 'Old failed jobs',
					'url'     => self::url( 'h1-old-failures', 'check/200' ),
					'history' => array( array( 200, 10 ) ),
					'posts'   => array( 'jobs' ),
				)
			),
			self::link(
				array(
					'id'      => 'H2',
					'label'   => 'Recent failed jobs',
					'url'     => self::url( 'h2-recent-failures', 'check/200' ),
					'history' => array( array( 200, 2 ) ),
					'posts'   => array( 'jobs' ),
				)
			),
		);
	}

	/**
	 * The failed jobs to seed, by hook: each is [args, days ago]. The args match what each Link Fixer event schedules.
	 *
	 * @param integer $h1 H1's link id.
	 * @param integer $h2 H2's link id.
	 *
	 * @return array<string, array<int, array{0: array<string, mixed>, 1: int}>>
	 */
	public static function jobs( int $h1, int $h2 ): array {
		$status = static fn( $link, $job, $attempt ) => array(
			'link_id' => $link,
			'job_id'  => $job,
			'attempt' => $attempt,
		);
		$retry  = static fn( $link, $attempt ) => array(
			'link_id' => $link,
			'attempt' => $attempt,
		);

		return array(
			'iawmlf_check_snapshot_status'  => array(
				array( $status( $h1, 'lfh-job-h1', 0 ), 10 ),
				array( $status( $h1, 'lfh-job-h1', 1 ), 10 ),
				array( $status( $h1, 'lfh-job-h1', 2 ), 10 ),
				array( $status( $h2, 'lfh-job-h2', 0 ), 2 ),
				array( $status( $h2, 'lfh-job-h2', 1 ), 2 ),
			),
			'iawmlf_create_new_snapshot'    => array(
				array( $retry( $h1, 0 ), 10 ),
				array( $retry( $h1, 1 ), 10 ),
				array( $retry( $h2, 0 ), 2 ),
			),
			'iawmlf_update_archive_url'     => array(
				array( $retry( $h1, 0 ), 10 ),
				array( $retry( $h1, 1 ), 10 ),
			),
			'iawmlf_check_validator_status' => array(
				array( $status( $h1, 'lfh-job-h1', 0 ), 10 ),
				array( $status( $h1, 'lfh-job-h1', 1 ), 10 ),
			),
		);
	}

	/**
	 * Seeds the links, the failed jobs and a clean-up job due now.
	 *
	 * @return array<string, mixed> The registry.
	 */
	public function seed(): array {
		// Every job of these hooks, in any status, so a reseed starts clean.
		$store = \ActionScheduler::store();
		$hooks = array_merge( array_keys( self::jobs( 0, 0 ) ), array( self::HOOK ) );
		foreach ( $hooks as $hook ) {
			$ids = as_get_scheduled_actions(
				array(
					'hook'     => $hook,
					'status'   => '',
					'per_page' => -1,
				),
				'ids'
			);
			foreach ( $ids as $id ) {
				$store->delete_action( (int) $id );
			}
		}

		$registry = parent::seed();

		foreach ( self::jobs( (int) $registry['links']['H1'], (int) $registry['links']['H2'] ) as $hook => $jobs ) {
			foreach ( $jobs as $job ) {
				$id = as_schedule_single_action( time() - $job[1] * DAY_IN_SECONDS, $hook, $job[0], 'iawmlf_event' );
				$store->mark_failure( $id );
			}
		}

		as_schedule_single_action( time(), self::HOOK );

		return $registry;
	}
}
