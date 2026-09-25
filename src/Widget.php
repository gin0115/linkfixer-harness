<?php
/**
 * Loads the watcher and the floating panel on harness posts.
 *
 * @package LinkFixer_Harness
 */

namespace LinkFixer_Harness;

defined( 'ABSPATH' ) || exit;

/**
 * Front end panel.
 */
class Widget {

	/**
	 * Registers the hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue' ), 1 );
	}

	/**
	 * Enqueues the watcher (head) and the panel (footer) with the page data.
	 *
	 * @return void
	 */
	public static function enqueue(): void {
		if ( ! is_singular() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$post_id  = (int) get_queried_object_id();
		$scenario = Scenarios::for_post( $post_id );
		$page     = null === $scenario ? null : $scenario->page_data( $post_id );
		if ( null === $page ) {
			return;
		}

		$load = (int) get_option( $scenario->load_count_key(), 0 ) + 1;
		update_option( $scenario->load_count_key(), $load, false );

		// In the head, so fetch is wrapped before the Link Fixer's footer script runs.
		wp_enqueue_script( 'lfh-watch', LFH_URL . 'assets/watch.js', array(), LFH_VERSION, false );
		wp_enqueue_script( 'lfh-panel', LFH_URL . 'assets/panel.js', array( 'lfh-watch' ), LFH_VERSION, true );
		wp_enqueue_style( 'lfh-panel', LFH_URL . 'assets/panel.css', array(), LFH_VERSION );

		$data = array_merge(
			$page,
			array(
				'settings'    => Rest::settings(),
				'rest_root'   => esc_url_raw( rest_url() ),
				'nonce'       => wp_create_nonce( 'wp_rest' ),
				'load'        => $load,
				'calls_since' => Call_Log::last_id(),
			)
		);

		wp_add_inline_script( 'lfh-panel', 'window.LFH_PAGE = ' . wp_json_encode( $data ) . ';', 'before' );
	}
}
