<?php
/**
 * Plugin Name: Link Fixer Harness site: links report
 * Description: Seeds twelve links in every state the Links report can show. The checklist panel walks through the table, its filters and search, the four bulk actions and the link details screen.
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
		require_once __DIR__ . '/Scenario_Links_Report.php';
		add_filter( 'lfh_scenarios', static fn( $scenarios ) => array_merge( $scenarios, array( new Scenario_Links_Report() ) ) );
	}
);

add_filter(
	'lfh_checklist',
	static function () {
		$heading   = '.wrap h1.wp-heading-inline';
		$exclusion = '#iawmlf_link_exclusion .inside';
		$toggle    = '#iawmlf_toggle_exclusion';
		$rows      = '#the-list tr';

		// The list form is a GET form that always sends s, iawmlf_status and friends, even empty.
		$param   = static fn( $name, $is ) => array(
			'type' => 'param',
			'name' => $name,
			'is'   => $is,
		);
		$page    = $param( 'page', 'iawmlf-links' );
		$text    = static fn( $selector, $has, $say = '' ) => array_filter(
			array(
				'type'     => 'text',
				'selector' => $selector,
				'has'      => $has,
				'say'      => $say,
			)
		);
		$notice  = static fn( $has, $say = '' ) => array_filter(
			array(
				'type' => 'notice',
				'has'  => $has,
				'say'  => $say,
			)
		);
		$count   = static fn( $is, $say ) => array(
			'type'     => 'count',
			'selector' => $rows,
			'is'       => $is,
			'say'      => $say,
		);
		$details = static fn( $slug ) => array( $text( $heading, $slug ) );

		return array(
			'title' => 'Links report',
			'intro' => 'Twelve seeded links, A1 to A12, one for each state the Links report can show. Do each step; each one ticks itself off when the panel sees it happen.',
			'items' => array(
				array(
					'id'     => 'list',
					'do'     => 'Open Link Fixer, Links.',
					'expect' => 'A table of the twelve seeded links, A1 to A12.',
					'link'   => 'admin:admin.php?page=iawmlf-links',
					'when'   => array( $page, $param( 'iawmlf_link_id', '' ), $param( 'iawmlf_status', '' ), $param( 's', '' ) ),
					'checks' => array( $count( 12, 'The table has 12 rows' ) ),
				),
				array(
					'id'     => 'filter-broken',
					'do'     => 'Choose "Show broken links" and press Filter.',
					'expect' => 'Only the five broken links: A1, A3, A6, A7 and A8.',
					'when'   => array( $page, $param( 'iawmlf_status', '1' ), $param( 's', '' ) ),
					'checks' => array(
						$count( 5, 'The table has 5 rows' ),
						$text( '#the-list', 'a1-broken-archive', 'A1 is listed' ),
						array(
							'type'     => 'text',
							'selector' => '#the-list',
							'lacks'    => 'a2-working',
							'say'      => 'A2, which works, is not listed',
						),
					),
				),
				array(
					'id'     => 'search',
					'do'     => 'Set the filter back to "All" and search for a3.',
					'expect' => 'One row: A3.',
					'when'   => array( $page, $param( 's', 'a3' ), $param( 'iawmlf_status', '' ) ),
					'checks' => array(
						$count( 1, 'The table has 1 row' ),
						$text( '#the-list', 'a3-no-archive', 'It is A3' ),
					),
				),
				array(
					'id'     => 'search-nothing',
					'do'     => 'Search for nothing-matches.',
					'expect' => 'The table says "No links to display."',
					'when'   => array( $page, $param( 's', 'nothing-matches' ) ),
					'checks' => array( $text( '#the-list', 'No links to display', 'The table says "No links to display."' ) ),
				),
				array(
					'id'     => 'bulk-check',
					'do'     => 'Clear the search. Tick A9, choose "Check link status" from Bulk actions and press Apply.',
					'expect' => 'A notice says A9 was checked successfully with 404 status.',
					'when'   => array( $notice( 'a9-check' ) ),
					'checks' => array(
						$notice( 'checked successfully', 'The notice says "checked successfully"' ),
						$notice( 'with 404 status', 'It says "with 404 status"' ),
						array(
							'type'  => 'link_row',
							'url'   => 'https://harness.test/a9-check/check/404',
							'field' => 'checks',
							'is'    => 2,
							'say'   => 'A9 now has a second check recorded',
						),
						array(
							'type'  => 'link_row',
							'url'   => 'https://harness.test/a9-check/check/404',
							'field' => 'last_code',
							'is'    => 404,
							'say'   => 'Its newest check is a 404',
						),
					),
				),
				array(
					'id'     => 'bulk-update',
					'do'     => 'Tick A10, choose "Update to latest snapshot" and press Apply.',
					'expect' => 'A notice says the archived URL for A10 was updated. It now points at the 2024 snapshot instead of the 2020 one.',
					'when'   => array( $notice( 'a10-update' ) ),
					'checks' => array(
						$notice( 'updated successfully', 'The notice says "updated successfully"' ),
						array(
							'type'  => 'link_row',
							'url'   => 'https://harness.test/a10-update/check/200',
							'field' => 'archived_href',
							'has'   => '20240101000000',
							'say'   => 'A10 now points at the 2024 snapshot',
						),
					),
				),
				array(
					'id'     => 'bulk-new',
					'do'     => 'Tick A11, choose "Create new snapshot" and press Apply.',
					'expect' => 'A notice says A11 was added to the queue and a new snapshot will be created in the coming minutes.',
					'when'   => array( $notice( 'a11-new-snapshot' ) ),
					'checks' => array(
						$notice( 'added to the queue', 'The notice says it was added to the queue' ),
						array(
							'type'    => 'calls',
							'method'  => 'create_snapshot',
							'url_has' => 'a11-new-snapshot',
							'say'     => 'Archive.org was asked to save A11',
						),
						array(
							'type'   => 'action',
							'hook'   => 'iawmlf_check_snapshot_status',
							'status' => 'pending',
							'min'    => 1,
							'say'    => 'A background job to check the new snapshot is queued',
						),
					),
				),
				array(
					'id'     => 'bulk-validate',
					'do'     => 'Tick A12, choose "Verify link allows checking" and press Apply.',
					'expect' => 'A notice says A12 is being validated.',
					'when'   => array( $notice( 'a12-validate' ) ),
					'checks' => array(
						$notice( 'Validating', 'The notice says "Validating"' ),
						array(
							'type'    => 'calls',
							'method'  => 'create_snapshot',
							'url_has' => 'a12-validate',
							'say'     => 'Archive.org was asked to save A12',
						),
						array(
							'type'   => 'action',
							'hook'   => 'iawmlf_check_validator_status',
							'status' => 'pending',
							'min'    => 1,
							'say'    => 'A background job to check the result is queued',
						),
					),
				),
				array(
					'id'     => 'details-a1',
					'do'     => 'Click A1\'s address in the table to open its details.',
					'expect' => 'Archive status "HAS ARCHIVE", its checks listed with 404s, and "Found In" lists the post "Harness: report links".',
					'when'   => $details( 'a1-broken-archive' ),
					'checks' => array(
						$text( '#iawmlf_link_details', 'HAS ARCHIVE', 'Archive status says "HAS ARCHIVE"' ),
						$text( '#iawmlf_link_checks', '404', 'The checks show 404' ),
						$text( '#iawmlf_link_posts', 'Harness: report links', '"Found In" lists the post' ),
					),
				),
				array(
					'id'     => 'details-a3',
					'do'     => 'Back to all links, then open A3.',
					'expect' => 'Archive status "NO ARCHIVE".',
					'when'   => $details( 'a3-no-archive' ),
					'checks' => array( $text( '#iawmlf_link_details', 'NO ARCHIVE', 'Archive status says "NO ARCHIVE"' ) ),
				),
				array(
					'id'     => 'details-a4',
					'do'     => 'Open A4.',
					'expect' => 'Archive status "NEW", queued to be processed.',
					'when'   => $details( 'a4-new' ),
					'checks' => array( $text( '#iawmlf_link_details', 'NEW - This link has been queued', 'Archive status says "NEW"' ) ),
				),
				array(
					'id'     => 'details-a5',
					'do'     => 'Open A5.',
					'expect' => 'Archive status "PENDING".',
					'when'   => $details( 'a5-pending' ),
					'checks' => array( $text( '#iawmlf_link_details', 'PENDING - Queued for submission', 'Archive status says "PENDING"' ) ),
				),
				array(
					'id'     => 'details-a6',
					'do'     => 'Open A6.',
					'expect' => 'Link Exclusion says the link is currently excluded, and "Exclude this link" is ticked and can be changed.',
					'when'   => $details( 'a6-manual' ),
					'checks' => array(
						$text( $exclusion, 'currently excluded', 'It says the link is currently excluded' ),
						array(
							'type'     => 'checked',
							'selector' => $toggle,
							'is'       => true,
							'say'      => '"Exclude this link" is ticked',
						),
						array(
							'type'     => 'missing',
							'selector' => $toggle . '[disabled]',
							'say'      => 'It can be changed',
						),
					),
				),
				array(
					'id'     => 'details-a7',
					'do'     => 'Open A7.',
					'expect' => 'Link Exclusion says the link is excluded by your exclusion settings list, and the checkbox cannot be changed.',
					'when'   => $details( 'a7-rule' ),
					'checks' => array(
						$text( $exclusion, 'excluded by your exclusion settings list', 'It names the exclusion settings list' ),
						array(
							'type'     => 'exists',
							'selector' => $toggle . '[disabled]',
							'say'      => 'The checkbox cannot be changed',
						),
					),
				),
				array(
					'id'     => 'details-a8',
					'do'     => 'Open A8 (the LinkedIn link).',
					'expect' => 'Link Exclusion says the link is excluded by the built-in exclusion list, and the checkbox cannot be changed.',
					'when'   => $details( 'linkedin.com/in/lfh-harness-a8' ),
					'checks' => array(
						$text( $exclusion, 'excluded by the built-in exclusion list', 'It names the built-in exclusion list' ),
						array(
							'type'     => 'exists',
							'selector' => $toggle . '[disabled]',
							'say'      => 'The checkbox cannot be changed',
						),
					),
				),
				array(
					'id'     => 'exclude-a2',
					'do'     => 'Open A2, tick "Exclude this link" and confirm.',
					'expect' => '"Link updated successfully." and Link Exclusion now says the link is currently excluded.',
					'when'   => array_merge( $details( 'a2-working' ), array( $notice( 'Link updated successfully' ) ) ),
					'checks' => array(
						$text( $exclusion, 'currently excluded', 'It says the link is currently excluded' ),
						array(
							'type'  => 'link_row',
							'url'   => 'https://harness.test/a2-working/check/200',
							'field' => 'excluded',
							'is'    => true,
							'say'   => 'A2 is saved as excluded',
						),
						array(
							'type'  => 'link_row',
							'url'   => 'https://harness.test/a2-working/check/200',
							'field' => 'message',
							'has'   => 'User Requested To Exclude',
							'say'   => 'It is marked as excluded by a user',
						),
					),
				),
			),
		);
	}
);
