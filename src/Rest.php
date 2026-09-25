<?php
/**
 * Harness REST routes, used by the panel and by anything driving the tests (Puppeteer, an AI).
 *
 * @package LinkFixer_Harness
 */

namespace LinkFixer_Harness;

use Internet_Archive\Wayback_Machine_Link_Fixer\Settings\Settings;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Routes under lfh/v1.
 */
class Rest {

	public const NAMESPACE = 'lfh/v1';

	private const RESULTS_OPTION = 'lfh_results';
	private const RESULTS_LIMIT  = 200;

	/**
	 * Registers the hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'rest_api_init', array( self::class, 'register_routes' ) );
	}

	/**
	 * Registers the routes.
	 *
	 * @return void
	 */
	public static function register_routes(): void {
		$routes = array(
			'state'     => array( 'GET', 'get_state' ),
			'calls'     => array( 'GET', 'get_calls' ),
			'seed'      => array( 'POST', 'seed' ),
			'age'       => array( 'POST', 'age' ),
			'settings'  => array( 'POST', 'update_settings' ),
			'results'   => array( 'GET, POST', 'results' ),
			'step'      => array( 'POST', 'step' ),
			'verify'    => array( 'POST', 'verify' ),
			'run/save'  => array( 'POST', 'run_save' ),
			'runs'      => array( 'GET', 'runs' ),
			'run/start' => array( 'POST', 'run_start' ),
		);

		foreach ( $routes as $route => $config ) {
			register_rest_route(
				self::NAMESPACE,
				'/' . $route,
				array(
					'methods'             => $config[0],
					'callback'            => array( self::class, $config[1] ),
					// Starting a run needs an administrator; everything else also accepts the run token.
					'permission_callback' => 'run/start' === $route
						? static fn() => current_user_can( 'manage_options' )
						: array( self::class, 'allowed' ),
				)
			);
		}
	}

	/**
	 * An administrator, or a request carrying the current run token (X-LFH-Token).
	 *
	 * The runner sends the token without a nonce, so it keeps working after it logs in as another user.
	 *
	 * @param WP_REST_Request $request The request.
	 *
	 * @return boolean
	 */
	public static function allowed( WP_REST_Request $request ): bool {
		return current_user_can( 'manage_options' ) || Runner::token_valid( (string) $request->get_header( 'x_lfh_token' ) );
	}

	/**
	 * Starts a run.
	 *
	 * @return WP_REST_Response
	 */
	public static function run_start(): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'token'  => Runner::start(),
				'suites' => Suites::describe(),
			)
		);
	}

	/**
	 * Runs a step's setup and returns what the browser needs.
	 *
	 * @param WP_REST_Request $request The request, with suite and index.
	 *
	 * @return WP_REST_Response|\WP_Error
	 */
	public static function step( WP_REST_Request $request ) {
		$suite = Suites::get( (string) $request->get_param( 'suite' ) );
		if ( null === $suite ) {
			return new \WP_Error( 'lfh_unknown_suite', 'Unknown suite.', array( 'status' => 404 ) );
		}
		return new WP_REST_Response( Runner::run_step( $suite, absint( $request->get_param( 'index' ) ) ) );
	}

	/**
	 * Runs a step's server side checks.
	 *
	 * @param WP_REST_Request $request The request, with suite and index.
	 *
	 * @return WP_REST_Response|\WP_Error
	 */
	public static function verify( WP_REST_Request $request ) {
		$suite = Suites::get( (string) $request->get_param( 'suite' ) );
		if ( null === $suite ) {
			return new \WP_Error( 'lfh_unknown_suite', 'Unknown suite.', array( 'status' => 404 ) );
		}
		return new WP_REST_Response( Runner::verify( $suite, absint( $request->get_param( 'index' ) ) ) );
	}

	/**
	 * Stores the run so far.
	 *
	 * @param WP_REST_Request $request The request, the run as JSON.
	 *
	 * @return WP_REST_Response
	 */
	public static function run_save( WP_REST_Request $request ): WP_REST_Response {
		$run = $request->get_json_params();
		if ( is_array( $run ) ) {
			Runner::save_run( $run );
		}
		return new WP_REST_Response( array( 'saved' => is_array( $run ) ) );
	}

	/**
	 * Stored runs.
	 *
	 * @return WP_REST_Response
	 */
	public static function runs(): WP_REST_Response {
		return new WP_REST_Response( Runner::runs() );
	}

	/**
	 * The Link Fixer and harness settings that decide what the front end does.
	 *
	 * @return array<string, mixed>
	 */
	public static function settings(): array {
		return array(
			'fixer_option'       => Settings::get_fixer_option(),
			'link_icon'          => Settings::get_link_icon(),
			'duration'           => Settings::get_link_check_duration(),
			'failed_count'       => Settings::get_failed_count(),
			'valid_codes'        => array_map( 'intval', Settings::get_valid_http_status_codes() ),
			'process_links'      => Settings::is_link_processing_enabled(),
			'client_mode'        => get_client_mode(),
			'archive_online'     => 'no' === get_option( 'lfh_archive_online', 'yes' ) ? 'no' : 'yes',
			'environment'        => wp_get_environment_type(),
			'link_fixer_version' => defined( 'IAWMLF_VERSION' ) ? IAWMLF_VERSION : '',
			'harness_version'    => LFH_VERSION,
		);
	}

	/**
	 * Everything in one call.
	 *
	 * @return WP_REST_Response
	 */
	public static function get_state(): WP_REST_Response {
		$scenarios = array();
		foreach ( Scenarios::all() as $slug => $scenario ) {
			$scenarios[ $slug ] = array(
				'title'    => $scenario->title(),
				'summary'  => $scenario->summary(),
				'registry' => $scenario->registry(),
			);
		}

		return new WP_REST_Response(
			array(
				'settings'  => self::settings(),
				'scenarios' => $scenarios,
				'calls'     => Call_Log::since( max( 0, Call_Log::last_id() - 200 ) ),
				'results'  => (array) get_option( self::RESULTS_OPTION, array() ),
			)
		);
	}

	/**
	 * Calls since an id.
	 *
	 * @param WP_REST_Request $request The request.
	 *
	 * @return WP_REST_Response
	 */
	public static function get_calls( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response( Call_Log::since( absint( $request->get_param( 'since' ) ) ) );
	}

	/**
	 * Reseeds a scenario.
	 *
	 * @param WP_REST_Request $request The request, with scenario (slug, default mixed-links).
	 *
	 * @return WP_REST_Response|\WP_Error
	 */
	public static function seed( WP_REST_Request $request ) {
		$scenario = Scenarios::get( (string) ( $request->get_param( 'scenario' ) ?? 'mixed-links' ) );
		if ( null === $scenario ) {
			return new \WP_Error( 'lfh_unknown_scenario', 'Unknown scenario.', array( 'status' => 404 ) );
		}

		$registry = $scenario->seed();
		return new WP_REST_Response(
			array(
				'registry' => $registry,
				'main_url' => $scenario->first_url(),
			)
		);
	}

	/**
	 * Ages the seeded checks of one scenario, or of all of them.
	 *
	 * @param WP_REST_Request $request The request, with days and optionally scenario.
	 *
	 * @return WP_REST_Response
	 */
	public static function age( WP_REST_Request $request ): WP_REST_Response {
		$days     = (float) ( $request->get_param( 'days' ) ?? 4 );
		$slug     = $request->get_param( 'scenario' );
		$selected = null === $slug ? Scenarios::all() : array_filter( array( Scenarios::get( (string) $slug ) ) );

		$changed = 0;
		foreach ( $selected as $scenario ) {
			$changed += $scenario->age( $days );
		}

		return new WP_REST_Response( array( 'changed' => $changed ) );
	}

	/**
	 * Changes the fixer mode and the fake archive.org online status.
	 *
	 * @param WP_REST_Request $request The request.
	 *
	 * @return WP_REST_Response
	 */
	public static function update_settings( WP_REST_Request $request ): WP_REST_Response {
		$fixer = $request->get_param( 'fixer_option' );
		if ( in_array( $fixer, array( Settings::FIXER_OPTION_REPLACE_LINK, Settings::FIXER_OPTION_CHECK_ONLY, Settings::FIXER_OPTION_DO_NOTHING ), true ) ) {
			update_option( Settings::FIXER_OPTION, $fixer );
		}

		$online = $request->get_param( 'archive_online' );
		if ( in_array( $online, array( 'yes', 'no' ), true ) ) {
			update_option( 'lfh_archive_online', $online );
			// The plugin caches the online status for an hour.
			delete_transient( 'iawmlf_archive_api_online' );
		}

		return new WP_REST_Response( self::settings() );
	}

	/**
	 * Stores (POST) or returns (GET) the per-load results.
	 *
	 * @param WP_REST_Request $request The request.
	 *
	 * @return WP_REST_Response
	 */
	public static function results( WP_REST_Request $request ): WP_REST_Response {
		$results = (array) get_option( self::RESULTS_OPTION, array() );

		if ( 'POST' === $request->get_method() ) {
			$entry = $request->get_json_params();
			if ( is_array( $entry ) ) {
				$entry['saved'] = gmdate( 'c' );
				$results[]      = $entry;
				$results        = array_slice( $results, -self::RESULTS_LIMIT );
				update_option( self::RESULTS_OPTION, $results, false );
			}
		}

		return new WP_REST_Response( $results );
	}
}
