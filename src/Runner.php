<?php
/**
 * Runs suite steps on the server: setup actions, resolving pages and server side checks.
 *
 * @package LinkFixer_Harness
 */

namespace LinkFixer_Harness;

use Internet_Archive\Wayback_Machine_Link_Fixer\Link\Link;
use Internet_Archive\Wayback_Machine_Link_Fixer\Link\Link_Repository;
use Internet_Archive\Wayback_Machine_Link_Fixer\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Server half of the test runner.
 */
class Runner {

	public const TOKEN_OPTION = 'lfh_run_token';
	public const ENV_OPTION   = 'lfh_environment';
	public const RUNS_OPTION  = 'lfh_runs';

	private const RUNS_LIMIT = 20;

	/**
	 * Check types answered on the server; everything else is checked in the browser.
	 */
	private const SERVER_CHECKS = array( 'option', 'action', 'link_row', 'calls' );

	/**
	 * Registers the hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_filter( 'iawmlf_is_production_environment', array( self::class, 'environment' ), 999 );
	}

	/**
	 * Forces production or staging when the harness option says so.
	 *
	 * @param boolean $is_production Current value.
	 *
	 * @return boolean
	 */
	public static function environment( $is_production ) {
		$environment = get_option( self::ENV_OPTION, '' );
		if ( 'production' === $environment ) {
			return true;
		}
		if ( 'staging' === $environment ) {
			return false;
		}
		return $is_production;
	}

	/**
	 * Starts a run: a fresh token the runner sends with every harness REST call.
	 *
	 * @return string
	 */
	public static function start(): string {
		$token = wp_generate_password( 32, false );
		update_option( self::TOKEN_OPTION, $token, false );
		return $token;
	}

	/**
	 * Whether a token matches the current run.
	 *
	 * @param string $token The token.
	 *
	 * @return boolean
	 */
	public static function token_valid( string $token ): bool {
		$saved = (string) get_option( self::TOKEN_OPTION, '' );
		return '' !== $saved && '' !== $token && hash_equals( $saved, $token );
	}

	/**
	 * Runs a step's setup and returns what the browser needs.
	 *
	 * @param Suite   $suite The suite.
	 * @param integer $index The step.
	 *
	 * @return array<string, mixed>
	 */
	public static function run_step( Suite $suite, int $index ): array {
		$step = $suite->step( $index );
		if ( null === $step ) {
			return array( 'done' => true );
		}

		$error = '';
		try {
			self::setup( $step['setup'] );
		} catch ( \Throwable $e ) {
			$error = get_class( $e ) . ': ' . $e->getMessage();
		}

		return array(
			'done'   => false,
			'index'  => $index,
			'total'  => count( $suite->steps() ),
			'title'  => $step['title'],
			'url'    => self::resolve_open( $step['open'] ),
			'checks' => array_values( array_filter( $step['checks'], static fn( $check ) => ! in_array( $check['type'], self::SERVER_CHECKS, true ) ) ),
			'then'   => $step['then'],
			'error'  => $error,
		);
	}

	/**
	 * Runs a step's server side checks.
	 *
	 * @param Suite   $suite The suite.
	 * @param integer $index The step.
	 *
	 * @return array<int, array{say:string, pass:bool, detail:string}>
	 */
	public static function verify( Suite $suite, int $index ): array {
		$step = $suite->step( $index );
		if ( null === $step ) {
			return array();
		}

		$results = array();
		foreach ( $step['checks'] as $check ) {
			if ( ! in_array( $check['type'], self::SERVER_CHECKS, true ) ) {
				continue;
			}
			try {
				$result = self::check( $check );
			} catch ( \Throwable $e ) {
				$result = array(
					'say'    => $check['say'] ?? $check['type'],
					'pass'   => false,
					'detail' => get_class( $e ) . ': ' . $e->getMessage(),
				);
			}
			$results[] = $result;
		}
		return $results;
	}

	/**
	 * Runs setup actions.
	 *
	 * @param array<int, array<int, mixed>> $actions The actions.
	 *
	 * @return void
	 *
	 * @throws \InvalidArgumentException On an unknown action or scenario.
	 */
	public static function setup( array $actions ): void {
		foreach ( $actions as $action ) {
			$name = (string) $action[0];

			switch ( $name ) {
				case 'seed':
				case 'age':
					$scenario = Scenarios::get( (string) $action[1] );
					if ( null === $scenario ) {
						throw new \InvalidArgumentException( 'Unknown scenario ' . esc_html( (string) $action[1] ) );
					}
					if ( 'seed' === $name ) {
						$scenario->seed();
					} else {
						$scenario->age( (float) $action[2] );
					}
					break;

				case 'options':
					foreach ( (array) $action[1] as $option => $value ) {
						if ( null === $value ) {
							delete_option( $option );
						} else {
							update_option( $option, $value );
						}
					}
					break;

				case 'environment':
					update_option( self::ENV_OPTION, (string) $action[1] );
					break;

				case 'archive_online':
					update_option( 'lfh_archive_online', 'no' === $action[1] ? 'no' : 'yes' );
					delete_transient( 'iawmlf_archive_api_online' );
					break;

				case 'reset_wizard':
					self::reset_wizard();
					break;

				default:
					throw new \InvalidArgumentException( 'Unknown setup action ' . esc_html( $name ) );
			}
		}
	}

	/**
	 * Puts the plugin back to how it is straight after activation: onboarding pending, nothing chosen.
	 *
	 * @return void
	 */
	private static function reset_wizard(): void {
		update_option( Settings::POST_ACTIVATION_ONBOARDING_KEY, Settings::ONBOARDING_PENDING_OPTION );
		update_option( Settings::SETUP_WIZARD_STEP_KEY, 'step-1' );

		foreach ( array(
			Settings::SETUP_WIZARD_COMPLETED_KEY,
			Settings::ONBOARDING_DATE_KEY,
			Settings::PROCESS_LINKS,
			Settings::ALLOWED_POST_TYPES,
			Settings::SCAN_EXISTING_POSTS,
			Settings::CAST_ARCHIVED_TO_HTTPS,
			Settings::FIXER_OPTION,
			Settings::ALLOW_OWN_CONTENT_SUBMISSIONS,
			Settings::ALLOWED_OWN_CONTENT_POST_TYPES,
			Settings::ROUTINELY_UPDATE_WAYBACK_MACHINE,
		) as $option ) {
			delete_option( $option );
		}

		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( 'iawmlf_scan_existing_posts' );
		}
	}

	/**
	 * Turns a step's "open" into a URL.
	 *
	 * @param string|null $open admin:<path>, front:<path>, post:<scenario>:<role>, or null.
	 *
	 * @return string|null
	 */
	public static function resolve_open( ?string $open ): ?string {
		if ( null === $open || '' === $open ) {
			return null;
		}

		$parts = explode( ':', $open, 2 );
		$rest  = $parts[1] ?? '';

		switch ( $parts[0] ) {
			case 'admin':
				return admin_url( $rest );
			case 'front':
				return home_url( $rest );
			case 'post':
				$target   = explode( ':', $rest, 2 );
				$scenario = Scenarios::get( $target[0] );
				$registry = null === $scenario ? null : $scenario->registry();
				return null !== $registry && isset( $target[1], $registry['posts'][ $target[1] ] )
					? (string) get_permalink( $registry['posts'][ $target[1] ] )
					: null;
		}

		return null;
	}

	/**
	 * Runs one server side check.
	 *
	 * @param array<string, mixed> $check The check.
	 *
	 * @return array{say:string, pass:bool, detail:string}
	 */
	private static function check( array $check ): array {
		switch ( $check['type'] ) {
			case 'option':
				$actual = get_option( $check['name'], null );
				return array(
					'say'    => $check['say'] ?? 'Setting ' . $check['name'] . ' is ' . wp_json_encode( $check['is'] ),
					'pass'   => self::same( $actual, $check['is'] ),
					'detail' => 'found ' . wp_json_encode( $actual ),
				);

			case 'action':
				$ids   = self::action_ids( $check );
				$count = count( $ids );
				$pass  = isset( $check['count'] ) ? $count === (int) $check['count'] : $count >= (int) ( $check['min'] ?? 1 );
				$want  = isset( $check['count'] ) ? (string) $check['count'] : 'at least ' . (int) ( $check['min'] ?? 1 );
				return array(
					'say'    => $check['say'] ?? sprintf( '%1$s %2$s background job(s) "%3$s"%4$s', $want, $check['status'], $check['hook'], isset( $check['due_within'] ) ? ' due within ' . (int) $check['due_within'] . 's' : '' ),
					'pass'   => $pass,
					'detail' => 'found ' . $count,
				);

			case 'link_row':
				$link   = self::find_link( $check );
				$actual = null === $link ? null : self::link_field( $link, (string) $check['field'] );
				return array(
					'say'    => $check['say'] ?? sprintf( 'Link %1$s: %2$s is %3$s', $check['id'] ?? $check['url'], $check['field'], wp_json_encode( $check['is'] ) ),
					'pass'   => null !== $link && self::same( $actual, $check['is'] ),
					'detail' => null === $link ? 'link row not found' : 'found ' . wp_json_encode( $actual ),
				);

			case 'calls':
				global $wpdb;
				$table = Call_Log::table();
				$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE method = %s AND url LIKE %s", $check['method'], '%' . $wpdb->esc_like( (string) $check['url_has'] ) . '%' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
				return array(
					'say'    => $check['say'] ?? sprintf( 'Archive.org was asked %1$s at least %2$d time(s) about %3$s', $check['method'], (int) ( $check['min'] ?? 1 ), $check['url_has'] ),
					'pass'   => $count >= (int) ( $check['min'] ?? 1 ),
					'detail' => 'found ' . $count,
				);
		}

		return array(
			'say'    => 'Unknown check ' . $check['type'],
			'pass'   => false,
			'detail' => '',
		);
	}

	/**
	 * Action Scheduler action ids matching a check.
	 *
	 * @param array<string, mixed> $check hook, status, optional args and due_within (seconds from now).
	 *
	 * @return int[]
	 */
	private static function action_ids( array $check ): array {
		$query = array(
			'hook'     => $check['hook'],
			'status'   => $check['status'],
			'per_page' => -1,
		);
		if ( isset( $check['args'] ) ) {
			$query['args'] = $check['args'];
		}

		$ids = array_map( 'intval', (array) as_get_scheduled_actions( $query, 'ids' ) );

		if ( isset( $check['due_within'] ) ) {
			$limit = time() + (int) $check['due_within'];
			$ids   = array_values(
				array_filter(
					$ids,
					static fn( $id ) => \ActionScheduler::store()->get_date( $id )->getTimestamp() <= $limit
				)
			);
		}

		return $ids;
	}

	/**
	 * Finds the link row a check is about, by scenario and id, or by URL.
	 *
	 * @param array<string, mixed> $check The check.
	 *
	 * @return Link|null
	 */
	private static function find_link( array $check ): ?Link {
		$repository = new Link_Repository();

		if ( isset( $check['url'] ) ) {
			return $repository->find_by_url( (string) $check['url'] );
		}

		$scenario = Scenarios::get( (string) $check['scenario'] );
		$registry = null === $scenario ? null : $scenario->registry();
		return null !== $registry && isset( $registry['links'][ $check['id'] ] )
			? $repository->find_by_id( (int) $registry['links'][ $check['id'] ] )
			: null;
	}

	/**
	 * Reads one field of a link.
	 *
	 * @param Link   $link  The link.
	 * @param string $field broken, excluded, archived, process, checks, last_code, redirect or message.
	 *
	 * @return mixed
	 */
	private static function link_field( Link $link, string $field ) {
		switch ( $field ) {
			case 'broken':
				return $link->is_broken();
			case 'excluded':
				return $link->is_excluded();
			case 'archived':
				return '' !== (string) $link->get_archived_href();
			case 'process':
				return $link->get_archive_process();
			case 'checks':
				return count( $link->get_checks() );
			case 'last_code':
				$last = $link->get_last_check();
				return null === $last ? null : (int) $last['http_code'];
			case 'redirect':
				return (string) $link->get_redirect_href();
			case 'message':
				return $link->get_message();
		}
		return null;
	}

	/**
	 * Loose comparison that treats "1"/true and ordered or unordered lists sensibly.
	 *
	 * @param mixed $actual   Found value.
	 * @param mixed $expected Expected value.
	 *
	 * @return boolean
	 */
	private static function same( $actual, $expected ): bool {
		if ( null === $expected ) {
			return null === $actual;
		}
		if ( is_bool( $expected ) ) {
			return (bool) $actual === $expected;
		}
		if ( is_array( $expected ) ) {
			if ( ! is_array( $actual ) ) {
				return false;
			}
			$a = array_map( 'strval', array_values( $actual ) );
			$b = array_map( 'strval', array_values( $expected ) );
			sort( $a );
			sort( $b );
			return $a === $b;
		}
		return (string) $actual === (string) $expected;
	}

	/**
	 * Stores a finished (or partial) run, keeping the most recent ones.
	 *
	 * @param array<string, mixed> $run The run.
	 *
	 * @return void
	 */
	public static function save_run( array $run ): void {
		$runs = array_values(
			array_filter(
				(array) get_option( self::RUNS_OPTION, array() ),
				static fn( $saved ) => ( $saved['id'] ?? '' ) !== ( $run['id'] ?? '' )
			)
		);

		$run['saved'] = gmdate( 'c' );
		$runs[]       = $run;
		update_option( self::RUNS_OPTION, array_slice( $runs, -self::RUNS_LIMIT ), false );
	}

	/**
	 * Stored runs, oldest first.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function runs(): array {
		return (array) get_option( self::RUNS_OPTION, array() );
	}
}
