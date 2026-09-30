<?php
/**
 * Plugin Name: Link Fixer Harness site: Editor permissions
 * Description: Opens the Link Fixer's reporting screens to editors with the iawmlf_reporting_page_capability filter. The site is used as an editor; the checklist panel covers what an editor can and cannot reach.
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
		require_once __DIR__ . '/Scenario_Permissions.php';
		add_filter( 'lfh_scenarios', static fn( $scenarios ) => array_merge( $scenarios, array( new Scenario_Permissions() ) ) );
	}
);

// Added on init, as a theme or plugin would, after the Link Fixer has booted.
add_action(
	'init',
	static function () {
		add_filter( 'iawmlf_reporting_page_capability', static fn() => 'edit_posts' );
	}
);

// The panel shows for the editor too.
add_filter( 'lfh_checklist_capability', static fn() => 'edit_posts' );

add_filter(
	'lfh_checklist',
	static function () {
		$settings_link = 'a[href*="page=iawmlf_settings"]';

		$exists  = static fn( $selector, $say = '' ) => array_filter(
			array(
				'type'     => 'exists',
				'selector' => $selector,
				'say'      => $say,
			)
		);
		$missing = static fn( $selector, $say ) => array(
			'type'     => 'missing',
			'selector' => $selector,
			'say'      => $say,
		);
		$text    = static fn( $selector, $has, $say = '' ) => array_filter(
			array(
				'type'     => 'text',
				'selector' => $selector,
				'has'      => $has,
				'say'      => $say,
			)
		);
		$param   = static fn( $name, $is ) => array(
			'type' => 'param',
			'name' => $name,
			'is'   => $is,
		);
		$notice  = static fn( $has, $say = '' ) => array_filter(
			array(
				'type' => 'notice',
				'has'  => $has,
				'say'  => $say,
			)
		);
		$closed  = static fn( $path, $say ) => array(
			'type'   => 'page',
			'url'    => 'admin:' . $path,
			'status' => 403,
			'has'    => 'Sorry, you are not allowed to access this page.',
			'say'    => $say,
		);
		$e3      = 'https://harness.test/e3-exclude/check/200';

		return array(
			'title' => 'Editor permissions',
			'intro' => 'You are logged in as an editor. This site sets iawmlf_reporting_page_capability to edit_posts, so editors get the Link Fixer\'s reporting screens (Dashboard, Links, the posts list column) but not Advanced Settings or the setup wizard.',
			'items' => array(
				array(
					'id'     => 'dashboard-widget',
					'do'     => 'Open the WordPress Dashboard.',
					'expect' => 'You are logged in as "editor", and the Wayback Link Fixer widget is there.',
					'link'   => 'admin:index.php',
					'when'   => array( $exists( 'body.index-php' ) ),
					'checks' => array(
						$text( '#wp-admin-bar-my-account', 'editor', 'You are logged in as the editor' ),
						$exists( '#iawmlf_dashboard_widget', 'The Wayback Link Fixer widget is on the Dashboard' ),
					),
				),
				array(
					'id'     => 'widget-buttons',
					'do'     => 'Look at the buttons along the bottom of the widget.',
					'expect' => 'Dashboard and View Links, but no Advanced Settings button, because an editor cannot open Advanced Settings.',
					'when'   => array( $exists( 'body.index-php' ), $exists( '#iawmlf_dashboard_widget' ) ),
					'checks' => array(
						$exists( '#iawmlf_dashboard_widget a[href*="page=iawmlf-links"]', 'There is a View Links button' ),
						$missing( '#iawmlf_dashboard_widget ' . $settings_link, 'There is no Advanced Settings button' ),
					),
				),
				array(
					'id'     => 'menu',
					'do'     => 'Look at Link Fixer in the WordPress admin left-hand menu.',
					'expect' => 'It has Dashboard and Links, and no Advanced Settings.',
					'when'   => array( $exists( 'body.index-php' ) ),
					'checks' => array(
						$exists( '#toplevel_page_iawmlf-dashboard', 'Link Fixer is in the menu' ),
						$exists( '#toplevel_page_iawmlf-dashboard a[href*="page=iawmlf-links"]', 'It has Links' ),
						$missing( '#adminmenu ' . $settings_link, 'It has no Advanced Settings' ),
					),
				),
				array(
					'id'     => 'settings-closed',
					'do'     => 'Open Advanced Settings by its address (the link here), then come back.',
					'expect' => 'WordPress says "Sorry, you are not allowed to access this page." The panel cannot show on that page, so it opens the address itself to check.',
					'link'   => 'admin:admin.php?page=iawmlf_settings',
					'checks' => array( $closed( 'admin.php?page=iawmlf_settings', 'Advanced Settings is closed to the editor' ) ),
				),
				array(
					'id'     => 'wizard-closed',
					'do'     => 'Open the setup wizard by its address (the link here), then come back.',
					'expect' => 'WordPress says "Sorry, you are not allowed to access this page."',
					'link'   => 'admin:admin.php?page=iawmlf-setup-wizard',
					'checks' => array( $closed( 'admin.php?page=iawmlf-setup-wizard', 'The setup wizard is closed to the editor' ) ),
				),
				array(
					'id'     => 'lf-dashboard',
					'do'     => 'Open Link Fixer (its Dashboard page).',
					'expect' => 'The link numbers show, and there is no Advanced Settings button.',
					'link'   => 'admin:admin.php?page=iawmlf-dashboard',
					'when'   => array( $param( 'page', 'iawmlf-dashboard' ) ),
					'checks' => array(
						$exists( '.iawmlf_dashboard-stats-number', 'The link numbers show' ),
						$missing( '.wrap ' . $settings_link, 'There is no Advanced Settings button' ),
					),
				),
				array(
					'id'     => 'links',
					'do'     => 'Open Link Fixer, Links.',
					'expect' => 'The table lists the three links, E1 to E3, and Bulk actions offers all four actions.',
					'link'   => 'admin:admin.php?page=iawmlf-links',
					'when'   => array( $param( 'page', 'iawmlf-links' ), $param( 'iawmlf_link_id', '' ), $param( 'iawmlf_status', '' ), $param( 's', '' ) ),
					'checks' => array(
						array(
							'type'     => 'count',
							'selector' => '#the-list tr',
							'is'       => 3,
							'say'      => 'The table has 3 rows',
						),
						array(
							'type'     => 'count',
							'selector' => '#bulk-action-selector-top option',
							'is'       => 5,
							'say'      => 'Bulk actions has the four actions (plus "Bulk actions")',
						),
					),
				),
				array(
					'id'     => 'bulk-check',
					'do'     => 'Tick E1, choose "Check link status" from Bulk actions and press Apply.',
					'expect' => 'A notice says E1 was checked successfully with 404 status.',
					'when'   => array( $notice( 'e1-broken' ) ),
					'checks' => array(
						$notice( 'checked successfully', 'The notice says "checked successfully"' ),
						$notice( 'with 404 status', 'It says "with 404 status"' ),
						array(
							'type'    => 'calls',
							'method'  => 'check_single',
							'url_has' => 'e1-broken',
							'min'     => 1,
							'say'     => 'E1 was checked',
						),
					),
				),
				array(
					'id'     => 'exclude-e3',
					'do'     => 'Open E3 (click its address in the table), tick "Exclude this link" and confirm.',
					'expect' => '"Link updated successfully." E3 is saved as excluded, and the Link Fixer records that "editor" asked for it.',
					'when'   => array( $text( '.wrap h1.wp-heading-inline', 'e3-exclude' ), $notice( 'Link updated successfully' ) ),
					'checks' => array(
						array(
							'type'  => 'link_row',
							'url'   => $e3,
							'field' => 'excluded',
							'is'    => true,
							'say'   => 'E3 is saved as excluded',
						),
						array(
							'type'  => 'link_row',
							'url'   => $e3,
							'field' => 'message',
							'has'   => 'User Requested To Exclude (editor on',
							'say'   => 'It records that "editor" asked for it',
						),
					),
				),
				array(
					'id'     => 'posts-column',
					'do'     => 'Open Posts.',
					'expect' => 'The Links column shows "1 broken out of 3" for "Harness: an editor\'s links", and "Excluded post" for "Harness: an excluded post".',
					'link'   => 'admin:edit.php',
					'when'   => array( $exists( 'body.edit-php.post-type-post' ) ),
					'checks' => array(
						$text( '#the-list', '1 broken out of 3', 'A post shows "1 broken out of 3"' ),
						$text( '#the-list', 'Excluded post', 'A post shows "Excluded post"' ),
					),
				),
				array(
					'id'     => 'posts-excluded-text',
					'do'     => 'Look at "Excluded post" in the Links column.',
					'expect' => 'It is plain text, not a link to Advanced Settings, which an editor cannot open.',
					'when'   => array( $exists( 'body.edit-php.post-type-post' ) ),
					'checks' => array( $missing( '#the-list ' . $settings_link, '"Excluded post" does not link to Advanced Settings' ) ),
				),
			),
		);
	}
);
