<?php
/**
 * Plugin Name: Link Fixer Harness site: Dashboard
 * Description: Seven links for the Link Fixer Dashboard once onboarding is over. The checklist panel covers the six numbers, the tables they link to, Recent Link Checks, Latest Links and the WordPress Dashboard widget.
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
		require_once __DIR__ . '/Scenario_Dashboard.php';
		add_filter( 'lfh_scenarios', static fn( $scenarios ) => array_merge( $scenarios, array( new Scenario_Dashboard() ) ) );
	}
);

add_filter(
	'lfh_checklist',
	static function () {
		$box    = array(
			'total'      => '.iawmlf_dashboard-stats-sidebar .iawmlf_dashboard-stats-box:first-child',
			'saved'      => '.iawmlf_dashboard-stats-box--black',
			'archived'   => '.iawmlf_dashboard-stats-box--success',
			'ineligible' => '.iawmlf_dashboard-stats-box--warning',
			'progress'   => '.iawmlf_dashboard-stats-box--info',
			'broken'     => '.iawmlf_dashboard-stats-box--danger',
		);
		$number = static fn( $key, $is, $say ) => array(
			'type'     => 'text',
			'selector' => $box[ $key ] . ' .iawmlf_dashboard-stats-number',
			'is'       => $is,
			'say'      => $say,
		);
		$table  = static fn( $key, $rows, $say ) => array(
			'type' => 'page',
			'from' => $box[ $key ] . ' a.iawmlf_dashboard-stats-number',
			'rows' => $rows,
			'say'  => $say,
		);
		$text   = static fn( $selector, $has, $say ) => array(
			'type'     => 'text',
			'selector' => $selector,
			'has'      => $has,
			'say'      => $say,
		);
		$count  = static fn( $selector, $is, $say ) => array(
			'type'     => 'count',
			'selector' => $selector,
			'is'       => $is,
			'say'      => $say,
		);
		$exists = static fn( $selector, $say ) => array(
			'type'     => 'exists',
			'selector' => $selector,
			'say'      => $say,
		);
		$page   = array(
			'type' => 'param',
			'name' => 'page',
			'is'   => 'iawmlf-dashboard',
		);
		$widget = '#iawmlf_dashboard_widget';

		return array(
			'title' => 'Dashboard',
			'intro' => 'Seven links, D1 to D7: D1 and D2 broken with an archive, D3 broken without one, D4 working with an archive, D5 working without one, D6 new and never checked, D7 broken with an archive but excluded by hand. Onboarding finished 8 days ago.',
			'items' => array(
				array(
					'id'     => 'overview',
					'do'     => 'Open Link Fixer (its Dashboard page).',
					'expect' => 'The "Link Statistics Overview" shows, not the onboarding progress: Total Links 7, Links Saved 2, Archived Successfully 4, Ineligible for redirect 3, Checks in progress 1, Total Broken Links 3.',
					'link'   => 'admin:admin.php?page=iawmlf-dashboard',
					'when'   => array( $page ),
					'checks' => array(
						$exists( '.iawmlf_dashboard-stats-sidebar', 'The overview shows' ),
						array(
							'type'     => 'missing',
							'selector' => '.iawmlf_dashboard-onboarding-text',
							'say'      => 'The onboarding progress does not show',
						),
						$number( 'total', '7', 'Total Links is 7' ),
						$number( 'saved', '2', 'Links Saved is 2 (D1, D2)' ),
						$number( 'archived', '4', 'Archived Successfully is 4 (D1, D2, D4, D7)' ),
						$number( 'ineligible', '3', 'Ineligible for redirect is 3 (D3, D5, D6)' ),
						$number( 'progress', '1', 'Checks in progress is 1 (D6)' ),
						$number( 'broken', '3', 'Total Broken Links is 3 (D1, D2, D3; D7 is excluded)' ),
					),
				),
				array(
					'id'     => 'overview-tables',
					'do'     => 'Click each number in turn (the panel opens each one itself to check).',
					'expect' => 'Each opens the Links table filtered to the same links the number counted.',
					'when'   => array( $page, array( 'type' => 'exists', 'selector' => '.iawmlf_dashboard-stats-sidebar' ) ),
					'checks' => array(
						$table( 'total', 7, 'Total Links opens 7 links' ),
						$table( 'saved', 2, 'Links Saved opens 2 links' ),
						$table( 'archived', 4, 'Archived Successfully opens 4 links' ),
						$table( 'ineligible', 3, 'Ineligible for redirect opens 3 links' ),
						$table( 'broken', 3, 'Total Broken Links opens 3 links' ),
					),
				),
				array(
					'id'     => 'recent-checks',
					'do'     => 'Look at "Recent Link Checks".',
					'expect' => 'All seven links, newest check first: D1 (checked yesterday) at the top, D6 (never checked) at the bottom.',
					'when'   => array( $page ),
					'checks' => array(
						$count( '#recent-checks .iawmlf_dashboard-link-check-item', 7, 'It lists 7 links' ),
						$text( '#recent-checks .iawmlf_dashboard-link-check-item:first-child', 'd1-broken-archived', 'D1 is first' ),
						$text( '#recent-checks .iawmlf_dashboard-link-check-item:last-child', 'd6-new', 'D6 is last' ),
					),
				),
				array(
					'id'     => 'latest-links',
					'do'     => 'Open the "Latest Links" tab.',
					'expect' => 'The six links that are not excluded, newest first: D6 at the top. D7, excluded by hand, is left out.',
					'when'   => array( $page ),
					'checks' => array(
						$count( '#latest-links .iawmlf_dashboard-link-check-item', 6, 'It lists 6 links' ),
						$text( '#latest-links .iawmlf_dashboard-link-check-item:first-child', 'd6-new', 'D6 is first' ),
						array(
							'type'     => 'text',
							'selector' => '#latest-links',
							'lacks'    => 'd7-excluded',
							'say'      => 'D7 is not listed',
						),
					),
				),
				array(
					'id'     => 'widget',
					'do'     => 'Open the WordPress Dashboard and look at the Wayback Link Fixer widget.',
					'expect' => 'Today\'s Snapshots 12/100000 and Pending Snapshots 0 (from the Archive.org account), Link Processing and Auto Archiver on, Scan Existing Posts off, links checked every 3 days and broken after 3 failures, and "View Links (7)".',
					'link'   => 'admin:index.php',
					'when'   => array( $exists( 'body.index-php', '' ) ),
					'checks' => array(
						array(
							'type'     => 'text',
							'selector' => $widget . ' .iawmlf_dashboard-stats-ratio',
							'is'       => '12/100000',
							'say'      => 'Today\'s Snapshots is 12/100000',
						),
						array(
							'type'     => 'text',
							'selector' => $widget . ' .iawmlf_dashboard-stats-box:nth-child(2) .iawmlf_dashboard-stats-number',
							'is'       => '0',
							'say'      => 'Pending Snapshots is 0',
						),
						$exists( $widget . ' .iawmlf_dashboard-features-item:nth-child(1) .iawmlf_dashboard-features-status.enabled', 'Link Processing is on' ),
						$exists( $widget . ' .iawmlf_dashboard-features-item:nth-child(2) .iawmlf_dashboard-features-status.enabled', 'Auto Archiver is on' ),
						$exists( $widget . ' .iawmlf_dashboard-features-item:nth-child(3) .iawmlf_dashboard-features-status.disabled', 'Scan Existing Posts is off' ),
						$text( $widget . ' .iawmlf_dashboard-features', 'checked every 3 days and marked as broken after 3 consecutive failures', 'It says every 3 days, broken after 3 failures' ),
						$text( $widget . ' .iawmlf_dashboard-navigation', 'View Links (7)', 'It says "View Links (7)"' ),
					),
				),
			),
		);
	}
);
