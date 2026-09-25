<?php
/**
 * A site's checklist: plain steps shown in a floating panel that ticks them off as it sees them happen.
 *
 * @package LinkFixer_Harness
 */

namespace LinkFixer_Harness;

use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * A site plugin supplies its checklist through the lfh_checklist filter:
 *
 * title - the site's name.
 * intro - one or two sentences on what the site is for.
 * items - in order, each:
 *   id     - unique within the site.
 *   do     - what the tester does, in plain English.
 *   expect - what they should then see, in plain English.
 *   link   - optional page for the "do": admin:<path> or front:<path>.
 *   when   - checks that say this is the page and moment the item is about; all must pass.
 *   checks - what must be true then; all must pass.
 *
 * Checks in "when" and "checks" run in the browser (url, text, exists, missing, checked, count, script)
 * or on the server (option, action, link_row, calls, see Checks). An item is only judged on a page
 * where every "when" check passes; once passed it stays passed.
 */
class Checklist {

	private const STATE_OPTION = 'lfh_checklist_state';

	/**
	 * Registers the hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'rest_api_init', array( self::class, 'register_routes' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue' ) );
	}

	/**
	 * The site's checklist, with defaults filled in.
	 *
	 * @return array{title:string, intro:string, items:array<int, array<string, mixed>>}
	 */
	public static function get(): array {
		$list = array_merge(
			array(
				'title' => '',
				'intro' => '',
				'items' => array(),
			),
			(array) apply_filters( 'lfh_checklist', array() )
		);

		$items = array();
		foreach ( (array) $list['items'] as $index => $item ) {
			$items[] = array_merge(
				array(
					'id'     => 'item-' . $index,
					'do'     => '',
					'expect' => '',
					'link'   => '',
					'when'   => array(),
					'checks' => array(),
				),
				$item
			);
		}
		$list['items'] = $items;

		return $list;
	}

	/**
	 * One item by id.
	 *
	 * @param string $id The item id.
	 *
	 * @return array<string, mixed>|null
	 */
	private static function item( string $id ): ?array {
		foreach ( self::get()['items'] as $item ) {
			if ( $item['id'] === $id ) {
				return $item;
			}
		}
		return null;
	}

	/**
	 * Saved results, by item id.
	 *
	 * @return array<string, array{state:string, detail:string, at:string}>
	 */
	public static function state(): array {
		return (array) get_option( self::STATE_OPTION, array() );
	}

	/**
	 * What the browser needs: only the browser side of each check, and whether there is a server side.
	 *
	 * @return array<string, mixed>
	 */
	private static function for_browser(): array {
		$list  = self::get();
		$items = array();

		foreach ( $list['items'] as $item ) {
			$browser = static fn( $checks ) => array_values( array_filter( (array) $checks, static fn( $check ) => ! Checks::is_server( $check ) ) );
			$server  = static fn( $checks ) => count( array_filter( (array) $checks, array( Checks::class, 'is_server' ) ) );

			$items[] = array(
				'id'     => $item['id'],
				'do'     => $item['do'],
				'expect' => $item['expect'],
				'link'   => self::resolve_link( (string) $item['link'] ),
				'when'   => $browser( $item['when'] ),
				'checks' => $browser( $item['checks'] ),
				'server' => $server( $item['when'] ) + $server( $item['checks'] ) > 0,
			);
		}

		return array(
			'title' => $list['title'],
			'intro' => $list['intro'],
			'items' => $items,
		);
	}

	/**
	 * Turns admin:<path> or front:<path> into a URL.
	 *
	 * @param string $link The link.
	 *
	 * @return string
	 */
	private static function resolve_link( string $link ): string {
		if ( 0 === strpos( $link, 'admin:' ) ) {
			return admin_url( substr( $link, 6 ) );
		}
		if ( 0 === strpos( $link, 'front:' ) ) {
			return home_url( substr( $link, 6 ) );
		}
		return $link;
	}

	/**
	 * Registers the routes, administrators only.
	 *
	 * @return void
	 */
	public static function register_routes(): void {
		$routes = array(
			'checklist'          => array( 'GET', 'route_get' ),
			'checklist/evaluate' => array( 'POST', 'route_evaluate' ),
			'checklist/state'    => array( 'POST', 'route_state' ),
			'checklist/reset'    => array( 'POST', 'route_reset' ),
		);

		foreach ( $routes as $route => $config ) {
			register_rest_route(
				Rest::NAMESPACE,
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
	 * The checklist and its saved results.
	 *
	 * @return WP_REST_Response
	 */
	public static function route_get(): WP_REST_Response {
		return new WP_REST_Response( array_merge( self::for_browser(), array( 'state' => self::state() ) ) );
	}

	/**
	 * The server side of one item: whether its server "when" checks pass, and its server checks.
	 *
	 * @param WP_REST_Request $request The request, with id.
	 *
	 * @return WP_REST_Response|\WP_Error
	 */
	public static function route_evaluate( WP_REST_Request $request ) {
		$item = self::item( (string) $request->get_param( 'id' ) );
		if ( null === $item ) {
			return new \WP_Error( 'lfh_unknown_item', 'Unknown checklist item.', array( 'status' => 404 ) );
		}

		foreach ( (array) $item['when'] as $check ) {
			if ( Checks::is_server( $check ) && ! Checks::run( $check )['pass'] ) {
				return new WP_REST_Response( array( 'applies' => false ) );
			}
		}

		$results = array();
		foreach ( (array) $item['checks'] as $check ) {
			if ( Checks::is_server( $check ) ) {
				$results[] = Checks::run( $check );
			}
		}

		return new WP_REST_Response(
			array(
				'applies' => true,
				'results' => $results,
			)
		);
	}

	/**
	 * Saves one item's result.
	 *
	 * @param WP_REST_Request $request The request, with id, state (pass or fail) and detail.
	 *
	 * @return WP_REST_Response
	 */
	public static function route_state( WP_REST_Request $request ): WP_REST_Response {
		$state = self::state();
		$id    = sanitize_key( (string) $request->get_param( 'id' ) );

		$state[ $id ] = array(
			'state'  => 'pass' === $request->get_param( 'state' ) ? 'pass' : 'fail',
			'detail' => sanitize_text_field( (string) $request->get_param( 'detail' ) ),
			'at'     => gmdate( 'c' ),
		);
		update_option( self::STATE_OPTION, $state, false );

		return new WP_REST_Response( $state );
	}

	/**
	 * Clears every result.
	 *
	 * @return WP_REST_Response
	 */
	public static function route_reset(): WP_REST_Response {
		delete_option( self::STATE_OPTION );
		return new WP_REST_Response( array() );
	}

	/**
	 * Loads the panel for administrators when the site has a checklist.
	 *
	 * @return void
	 */
	public static function enqueue(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$list = self::for_browser();
		if ( empty( $list['items'] ) ) {
			return;
		}

		wp_enqueue_script( 'lfh-checklist', LFH_URL . 'assets/checklist.js', array(), LFH_VERSION, true );
		wp_enqueue_style( 'lfh-checklist', LFH_URL . 'assets/checklist.css', array(), LFH_VERSION );

		$config = array_merge(
			$list,
			array(
				'state'     => self::state(),
				'rest_root' => esc_url_raw( rest_url() ),
				'nonce'     => wp_create_nonce( 'wp_rest' ),
			)
		);

		wp_add_inline_script( 'lfh-checklist', 'window.LFH_CHECKLIST = ' . wp_json_encode( $config ) . ';', 'before' );
	}
}
