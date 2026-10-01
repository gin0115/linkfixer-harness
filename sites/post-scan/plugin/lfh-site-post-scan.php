<?php
/**
 * Plugin Name: Link Fixer Harness site: Scanning existing posts
 * Description: Content written before the Link Fixer was installed: five posts, a page, an excluded post and a draft. The checklist panel covers the scan working through them in batches and onboarding ending.
 * Version: 0.1.0
 * Requires PHP: 7.4
 * Requires Plugins: linkfixer-harness
 * Author: Glynn Quelch
 * License: GPL-2.0-or-later
 *
 * @package LinkFixer_Harness
 */

namespace LinkFixer_Harness;

use Internet_Archive\Wayback_Machine_Link_Fixer\Settings\Settings;

defined( 'ABSPATH' ) || exit;

add_action(
	'lfh_loaded',
	static function () {
		require_once __DIR__ . '/Scenario_Post_Scan.php';
		add_filter( 'lfh_scenarios', static fn( $scenarios ) => array_merge( $scenarios, array( new Scenario_Post_Scan() ) ) );

		// Jobs only run when the panel's buttons say so.
		Queue::manual();
	}
);

// Two posts a batch, so the batches can be seen.
add_filter( 'iawmlf_posts_per_batch', static fn() => 2 );

// The Dashboard's numbers are cached for 2 minutes; 1 second here so each batch shows straight away (0 would never expire).
add_filter( 'iawmlf_dashboard_onboarding_stats_cache_expiry', static fn() => 1 );
add_filter( 'iawmlf_dashboard_link_stats_cache_expiry', static fn() => 1 );

add_filter(
	'lfh_checklist',
	static function () {
		$scenario = Scenarios::get( 'post-scan' );
		$registry = $scenario ? (array) $scenario->registry() : array();
		$posts    = array_map( 'intval', (array) ( $registry['posts'] ?? array() ) );
		$ids      = static fn( $roles ) => array_values( array_map( static fn( $role ) => $posts[ $role ] ?? 0, $roles ) );

		// Every published post and page counts towards "Posts Checked".
		$total = (int) wp_count_posts( 'post' )->publish + (int) wp_count_posts( 'page' )->publish;
		$ours  = count( Scenario_Post_Scan::SCANNABLE ) + 1;

		$scanned = static fn( $roles, $is, $say ) => array(
			'type'  => 'meta_count',
			'posts' => $ids( $roles ),
			'key'   => Settings::LINK_META_KEY,
			'is'    => $is,
			'say'   => $say,
		);
		$scan    = static fn( $status, $extra = array() ) => array_merge(
			array(
				'type'   => 'action',
				'hook'   => 'iawmlf_scan_existing_posts',
				'status' => $status,
			),
			$extra
		);
		$link    = static fn( $role, $say ) => array(
			'type'  => 'link_row',
			'url'   => 'https://harness.test/scan-' . $role . '/check/200',
			'field' => 'excluded',
			'is'    => false,
			'say'   => $say,
		);
		$page    = array(
			'type' => 'param',
			'name' => 'page',
			'is'   => 'iawmlf-dashboard',
		);
		$all     = $scanned( Scenario_Post_Scan::SCANNABLE, 6, '' );

		return array(
			'title' => 'Scanning existing posts',
			'intro' => 'Five posts, a page, an excluded post and a draft, all written before the Link Fixer was installed, so none has been scanned. The scan takes 2 posts a batch here. Jobs only run when you press Run next job, and the next scan is only queued when an admin page loads.',
			'queue' => true,
			'items' => array(
				array(
					'id'     => 'start',
					'do'     => 'Open Link Fixer (its Dashboard page).',
					'expect' => sprintf( 'Onboarding is in progress: "Posts Checked" shows %1$d / %2$d (none of the %3$d Harness posts yet), and a scan is waiting.', $total - $ours, $total, $ours ),
					'link'   => 'admin:admin.php?page=iawmlf-dashboard',
					'when'   => array( $page, $scanned( Scenario_Post_Scan::SCANNABLE, 0, '' ) ),
					'checks' => array(
						array(
							'type'     => 'text',
							'selector' => '.iawmlf_dashboard-stats-box--info .iawmlf_dashboard-stats-number',
							'is'       => ( $total - $ours ) . ' / ' . $total,
							'say'      => sprintf( 'Posts Checked shows %1$d / %2$d', $total - $ours, $total ),
						),
						$scan( 'pending', array( 'say' => 'A scan is waiting' ) ),
					),
				),
				array(
					'id'     => 'batch',
					'do'     => 'Press Run next job once.',
					'expect' => 'The scan takes one batch: 2 of the 6 posts that can be scanned.',
					'when'   => array( $scan( 'complete', array( 'count' => 1 ) ) ),
					'checks' => array( $scanned( Scenario_Post_Scan::SCANNABLE, 2, '2 posts have been scanned' ) ),
				),
				array(
					'id'     => 'next-batch',
					'do'     => 'Reload the page.',
					'expect' => 'The next scan is queued for about 10 minutes from now.',
					'when'   => array( $scan( 'complete', array( 'count' => 1 ) ), $scan( 'pending' ) ),
					'checks' => array(
						$scan(
							'pending',
							array(
								'due_min' => 9 * MINUTE_IN_SECONDS,
								'due_max' => 11 * MINUTE_IN_SECONDS,
								'say'     => 'The next scan is due in about 10 minutes',
							)
						),
					),
				),
				array(
					'id'     => 'all-scanned',
					'do'     => 'Keep pressing Run next job, reloading the page after each scan, until all 6 are scanned.',
					'expect' => 'The 5 posts and the page are scanned and their links are in the Links table. The draft and the excluded post are not scanned.',
					'when'   => array( $all ),
					'checks' => array(
						$link( 's1', 'Existing post 1\'s link is in the Links table' ),
						$link( 'page', 'The page\'s link is in the Links table' ),
						$scanned( array( 'draft' ), 0, 'The draft is not scanned' ),
						$scanned( array( 'excluded' ), 0, 'The excluded post is not scanned' ),
					),
				),
				array(
					'id'     => 'onboarding-ends',
					'do'     => 'Open Link Fixer (its Dashboard page).',
					'expect' => 'Every post that can be scanned has been, so onboarding ends and the "Link Statistics Overview" shows.',
					'link'   => 'admin:admin.php?page=iawmlf-dashboard',
					'when'   => array( $page, $all ),
					'checks' => array(
						array(
							'type'     => 'missing',
							'selector' => '.iawmlf_dashboard-onboarding-text',
							'say'      => 'The onboarding progress no longer shows',
						),
						array(
							'type'     => 'exists',
							'selector' => '.iawmlf_dashboard-stats-sidebar',
							'say'      => 'The overview shows',
						),
					),
				),
				array(
					'id'     => 'draft-published',
					'do'     => 'Open "Harness: existing draft" and publish it.',
					'expect' => 'Its link is found as it is published, without waiting for the scan.',
					'when'   => array(
						array(
							'type' => 'option',
							'name' => 'lfh_saved_posts',
							'has'  => $posts['draft'] ?? 0,
						),
					),
					'checks' => array(
						$scanned( array( 'draft' ), 1, 'The draft is scanned' ),
						$link( 'draft', 'Its link is in the Links table' ),
					),
				),
			),
		);
	}
);
