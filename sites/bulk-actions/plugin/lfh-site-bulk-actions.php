<?php
/**
 * Plugin Name: Link Fixer Harness site: Bulk actions at scale
 * Description: Sixty links for running the Links report bulk actions on all of them at once, with a slow Archive.org and a limit of 10 snapshots a minute.
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
		require_once __DIR__ . '/Scenario_Bulk_Actions.php';
		add_filter( 'lfh_scenarios', static fn( $scenarios ) => array_merge( $scenarios, array( new Scenario_Bulk_Actions() ) ) );

		// Queued jobs stay queued, so they can be counted.
		Queue::manual();
	}
);

add_filter(
	'lfh_checklist',
	static function () {
		$count = Scenario_Bulk_Actions::COUNT;
		$limit = Scenario_Bulk_Actions::LIMIT;
		$first = 'https://harness.test/b01/check/200/wait/0.25';
		$last  = sprintf( 'https://harness.test/b%02d/check/200/wait/0.25', $count );

		$notice = static fn( $has, $say, $how_many = null ) => array_filter(
			array(
				'type'  => 'notice',
				'has'   => $has,
				'count' => $how_many,
				'say'   => $say,
			),
			static fn( $value ) => null !== $value
		);
		$timing = array(
			'type' => 'timing',
			'max'  => 60,
			'say'  => 'The page came back within a minute',
		);
		$calls  = static fn( $method, $min, $max, $say ) => array(
			'type'    => 'calls',
			'method'  => $method,
			'url_has' => 'harness.test/b',
			'min'     => $min,
			'max'     => $max,
			'say'     => $say,
		);
		$jobs   = static fn( $hook, $say ) => array(
			'type'   => 'action',
			'hook'   => $hook,
			'status' => 'pending',
			'count'  => $limit,
			'say'    => $say,
		);

		return array(
			'title' => 'Bulk actions at scale',
			'intro' => 'Sixty working links, B01 to B60, all archived in 2020. The Archive.org stand-in takes a quarter of a second to answer about each link, and accepts 10 new snapshots a minute. Run each bulk action on all 60 at once.',
			'queue' => true,
			'items' => array(
				array(
					'id'     => 'per-page',
					'do'     => 'Open Link Fixer, Links. In Screen Options set "Number of items per page" to 100 and press Apply.',
					'expect' => 'All 60 links are listed on one page.',
					'link'   => 'admin:admin.php?page=iawmlf-links',
					'when'   => array(
						array(
							'type' => 'param',
							'name' => 'page',
							'is'   => 'iawmlf-links',
						),
						array(
							'type'     => 'value',
							'selector' => '#links_per_page',
							'is'       => '100',
						),
					),
					'checks' => array(
						array(
							'type'     => 'count',
							'selector' => '#the-list tr',
							'is'       => $count,
							'say'      => 'The table has 60 rows',
						),
					),
				),
				array(
					'id'     => 'check-all',
					'do'     => 'Tick the box at the top of the table to select all 60, choose "Check link status" and press Apply.',
					'expect' => 'Each link is checked once and gets its own "checked successfully" notice. At a quarter of a second a link the page takes about 15 seconds.',
					'when'   => array( $notice( 'checked successfully', '' ) ),
					'checks' => array(
						$notice( 'checked successfully', '60 notices say "checked successfully"', $count ),
						$calls( 'check_single', $count, $count, 'Each link was checked once' ),
						$timing,
					),
				),
				array(
					'id'     => 'update-all',
					'do'     => 'Select all again, choose "Update to latest snapshot" and press Apply.',
					'expect' => 'Every archived URL moves from the 2020 snapshot to the 2024 one, each with an "updated successfully" notice.',
					'when'   => array( $notice( 'Archived URL for', '' ) ),
					'checks' => array(
						$notice( 'updated successfully', '60 notices say "updated successfully"', $count ),
						array(
							'type'  => 'link_row',
							'url'   => $first,
							'field' => 'archived_href',
							'has'   => '20240101000000',
							'say'   => 'B01 points at the 2024 snapshot',
						),
						array(
							'type'  => 'link_row',
							'url'   => $last,
							'field' => 'archived_href',
							'has'   => '20240101000000',
							'say'   => 'B60 points at the 2024 snapshot',
						),
						$timing,
					),
				),
				array(
					'id'     => 'new-all',
					'do'     => 'Select all again, choose "Create new snapshot" and press Apply.',
					'expect' => 'The first 10 links get a new snapshot. Once Archive.org says the limit is reached, the Link Fixer stops asking for the rest and queues them to try again later.',
					'when'   => array( $notice( 'new snapshot', '' ) ),
					'checks' => array(
						$notice( 'a new snapshot will be created', '10 notices say a new snapshot will be created', $limit ),
						$jobs( 'iawmlf_check_snapshot_status', '10 snapshot checks are queued' ),
						$calls( 'create_snapshot', $limit, $limit + 1, 'Archive.org is not asked again once it says the limit is reached' ),
						array(
							'type'   => 'action',
							'hook'   => 'iawmlf_create_new_snapshot',
							'status' => 'pending',
							'url'    => $last,
							'min'    => 1,
							'say'    => 'B60, over the limit, is queued to try again later',
						),
						$timing,
					),
				),
				array(
					'id'     => 'validate-all',
					'do'     => 'Wait one minute (the limit is per minute). Select all, choose "Verify link allows checking" and press Apply.',
					'expect' => 'The first 10 get a verification request and say "Validating ...". Archive.org refuses the other 50, so their notices say so instead of "Validating".',
					'when'   => array( $notice( 'Validating', '' ) ),
					'checks' => array(
						$jobs( 'iawmlf_check_validator_status', '10 verification checks are queued' ),
						$notice( 'Validating', 'Only the 10 accepted links say "Validating"', $limit ),
						$timing,
					),
				),
			),
		);
	}
);
