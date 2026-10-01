<?php
/**
 * Plugin Name: Link Fixer Harness site: Auto Archiver on staging
 * Description: A staging copy of a site with the Auto Archiver turned on. The checklist panel covers it staying off: the settings notice, the widget, publishing and the routine rescan.
 * Version: 0.1.0
 * Requires PHP: 7.4
 * Requires Plugins: linkfixer-harness
 * Author: Glynn Quelch
 * License: GPL-2.0-or-later
 *
 * @package LinkFixer_Harness
 */

namespace LinkFixer_Harness;

defined( 'ABSPATH' ) || exit;

add_action(
	'lfh_loaded',
	static function () {
		require_once __DIR__ . '/Scenario_Auto_Archiver_Staging.php';
		add_filter( 'lfh_scenarios', static fn( $scenarios ) => array_merge( $scenarios, array( new Scenario_Auto_Archiver_Staging() ) ) );

		// Anything queued stays queued, so it can be seen.
		Queue::manual();
	}
);

add_filter(
	'lfh_checklist',
	static function () {
		$scenario = Scenarios::get( 'auto-archiver-staging' );
		$registry = $scenario ? (array) $scenario->registry() : array();
		$seeded   = array_map( 'intval', array_values( (array) ( $registry['posts'] ?? array() ) ) );

		// The tester's new post: the newest published post that was not seeded.
		$found  = get_posts(
			array(
				'post_type'    => 'post',
				'post_status'  => 'publish',
				'numberposts'  => 1,
				'orderby'      => 'ID',
				'order'        => 'DESC',
				'fields'       => 'ids',
				'post__not_in' => $seeded ? $seeded : array( 0 ),
			)
		);
		$newest = ! empty( $found ) && (int) $found[0] > max( $seeded ? $seeded : array( 0 ) ) ? (int) $found[0] : 0;

		$none      = static fn( $hook, $say ) => array(
			'type'   => 'action',
			'hook'   => $hook,
			'status' => array( 'pending', 'complete', 'failed' ),
			'count'  => 0,
			'say'    => $say,
		);
		$dashboard = array(
			'type'     => 'exists',
			'selector' => 'body.index-php',
		);

		return array(
			'title' => 'Auto Archiver on staging',
			'intro' => 'This site is staging (WP_ENVIRONMENT_TYPE is staging), copied from a production site with the Auto Archiver and its routine rescan turned on. "Harness: archived 30 days ago (staging)" would be archived again on production. Jobs only run when you press Run next job.',
			'queue' => true,
			'items' => array(
				array(
					'id'     => 'settings',
					'do'     => 'Open Link Fixer, Advanced Settings.',
					'expect' => 'The Auto Archiver section says a non-production environment was detected and auto archiving is disabled.',
					'link'   => 'admin:admin.php?page=iawmlf_settings',
					'when'   => array(
						array(
							'type' => 'param',
							'name' => 'page',
							'is'   => 'iawmlf_settings',
						),
					),
					'checks' => array(
						array(
							'type'     => 'text',
							'selector' => '#iawmlf_settings_auto_archiver_section',
							'has'      => 'Non-production environment detected',
							'say'      => 'It says a non-production environment was detected',
						),
					),
				),
				array(
					'id'     => 'widget',
					'do'     => 'Open the WordPress Dashboard.',
					'expect' => 'The Wayback Link Fixer widget shows the Auto Archiver as off, although its setting is on.',
					'link'   => 'admin:index.php',
					'when'   => array( $dashboard ),
					'checks' => array(
						array(
							'type'     => 'exists',
							'selector' => '#iawmlf_dashboard_widget .iawmlf_dashboard-features-item:nth-child(2) .iawmlf_dashboard-features-status.disabled',
							'say'      => 'The Auto Archiver shows as off',
						),
					),
				),
				array(
					'id'     => 'no-rescan',
					'do'     => 'Stay on the Dashboard (any admin page load would schedule the routine rescan on production).',
					'expect' => 'No routine rescan ("Queue your posts for archiving") is scheduled.',
					'when'   => array( $dashboard ),
					'checks' => array( $none( 'iawmlf_add_own_posts', 'No routine rescan is scheduled' ) ),
				),
				array(
					'id'     => 'publish',
					'do'     => 'Write and publish a new post, then open Posts.',
					'expect' => 'No job to archive it is queued (on production one would be, for an hour later).',
					'link'   => 'admin:post-new.php',
					'when'   => array(
						array(
							'type'     => 'exists',
							'selector' => 'body.edit-php',
						),
						array(
							'type' => 'option',
							'name' => 'lfh_saved_posts',
							'has'  => $newest,
						),
					),
					'checks' => array( $none( 'iawmlf_process_local_post', 'No archive job was queued' ) ),
				),
			),
		);
	}
);
