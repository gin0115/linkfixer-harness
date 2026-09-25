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
			'state'    => array( 'GET', 'get_state' ),
			'calls'    => array( 'GET', 'get_calls' ),
			'seed'     => array( 'POST', 'seed' ),
			'age'      => array( 'POST', 'age' ),
			'settings' => array( 'POST', 'update_settings' ),
			'results'  => array( 'GET, POST', 'results' ),
		);

		foreach ( $routes as $route => $config ) {
			register_rest_route(
				self::NAMESPACE,
				'/' . $route,
				array(
					'methods'             => $config[0],
					'callback'            => array( self::class, $config[1] ),
					'permission_callback' => static fn() => current_user_can( 'manage_options' ),
				)
			);
		}
	}

	/**
	 * The Link Fixer and harness settings that decide what the front end does.
	 *
	 * @return array<string, mixed>
	 */
	public static function settings(): array {
		return array(
			'fixer_option'       => Settings::get_fixer_option(),
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
		return new WP_REST_Response(
			array(
				'settings' => self::settings(),
				'registry' => get_option( 'lfh_scenario_mixed_links', null ),
				'calls'    => Call_Log::since( max( 0, Call_Log::last_id() - 200 ) ),
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
	 * Reseeds the scenario.
	 *
	 * @return WP_REST_Response
	 */
	public static function seed(): WP_REST_Response {
		$registry = Scenario_Mixed_Links::seed();
		return new WP_REST_Response(
			array(
				'registry' => $registry,
				'main_url' => get_permalink( $registry['posts']['main'] ),
			)
		);
	}

	/**
	 * Ages every seeded check.
	 *
	 * @param WP_REST_Request $request The request, with days.
	 *
	 * @return WP_REST_Response
	 */
	public static function age( WP_REST_Request $request ): WP_REST_Response {
		$days = (float) ( $request->get_param( 'days' ) ?? 4 );
		return new WP_REST_Response( array( 'changed' => Scenario_Mixed_Links::age( $days ) ) );
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
