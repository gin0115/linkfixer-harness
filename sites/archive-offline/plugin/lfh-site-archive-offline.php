<?php
/**
 * Plugin Name: Link Fixer Harness site: Archive.org offline
 * Description: Archive.org (the stand-in) is offline, with two new links waiting. The checklist panel covers what the Link Fixer shows while offline, its jobs waiting an hour, and everything carrying on once it is back online.
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
		require_once __DIR__ . '/Scenario_Archive_Offline.php';
		add_filter( 'lfh_scenarios', static fn( $scenarios ) => array_merge( $scenarios, array( new Scenario_Archive_Offline() ) ) );

		// Jobs only run when the panel's buttons say so.
		Queue::manual();
	}
);

add_filter(
	'lfh_checklist',
	static function () {
		$o1 = 'https://harness.test/o1-archived/check/200/archive/yes';
		$o2 = 'https://harness.test/o2-new-snapshot/check/200/archive/none,yes/save/ok/status/success';

		$online    = static fn( $is ) => array(
			'type' => 'option',
			'name' => 'lfh_archive_online',
			'is'   => $is,
		);
		$dashboard = array(
			'type'     => 'exists',
			'selector' => 'body.index-php',
		);
		$links     = array(
			'type' => 'param',
			'name' => 'page',
			'is'   => 'iawmlf-links',
		);
		$bulk      = static fn( $count, $say ) => array(
			'type'     => 'count',
			'selector' => '#bulk-action-selector-top option',
			'is'       => $count,
			'say'      => $say,
		);

		return array(
			'title'         => 'Archive.org offline',
			'intro'         => 'The Archive.org stand-in starts offline, with two new links (O1 and O2) waiting to be archived. Jobs only run when you press Run next job. Use the switch below to bring it back online when the checklist says so.',
			'queue'         => true,
			'online_toggle' => true,
			'items'         => array(
				array(
					'id'     => 'widget-offline',
					'do'     => 'Open the WordPress Dashboard.',
					'expect' => 'The Wayback Link Fixer widget says "Archive.org API is offline. Processes will be delayed".',
					'link'   => 'admin:index.php',
					'when'   => array( $dashboard, $online( 'no' ) ),
					'checks' => array(
						array(
							'type'     => 'text',
							'selector' => '#iawmlf_dashboard_widget',
							'has'      => 'Archive.org API is offline',
							'say'      => 'The widget says Archive.org is offline',
						),
					),
				),
				array(
					'id'     => 'bulk-disabled',
					'do'     => 'Open Link Fixer, Links.',
					'expect' => 'The Bulk actions dropdown only offers "API Offline, options disabled".',
					'link'   => 'admin:admin.php?page=iawmlf-links',
					'when'   => array( $links, $online( 'no' ) ),
					'checks' => array(
						array(
							'type'     => 'text',
							'selector' => '#bulk-action-selector-top',
							'has'      => 'API Offline, options disabled',
							'say'      => 'The dropdown offers "API Offline, options disabled"',
						),
						$bulk( 2, 'That is the only action (plus "Bulk actions")' ),
					),
				),
				array(
					'id'     => 'job-deferred',
					'do'     => 'Press Run next job once.',
					'expect' => 'O1\'s "Find or create a snapshot" job does not ask Archive.org anything: it is put back to try again in 1 hour.',
					'when'   => array(
						$online( 'no' ),
						array(
							'type'   => 'action',
							'hook'   => 'iawmlf_find_or_create_snapshot',
							'status' => 'complete',
							'url'    => $o1,
							'min'    => 1,
						),
					),
					'checks' => array(
						array(
							'type'    => 'action',
							'hook'    => 'iawmlf_find_or_create_snapshot',
							'status'  => 'pending',
							'url'     => $o1,
							'min'     => 1,
							'due_min' => 55 * MINUTE_IN_SECONDS,
							'due_max' => 65 * MINUTE_IN_SECONDS,
							'say'     => 'O1\'s job is back in the queue, due in about 1 hour',
						),
						array(
							'type'    => 'calls',
							'method'  => 'get_latest_snapshot',
							'url_has' => 'o1-archived',
							'min'     => 0,
							'max'     => 0,
							'say'     => 'Archive.org was not asked about O1',
						),
					),
				),
				array(
					'id'     => 'widget-online',
					'do'     => 'Press "Bring back online" above, then open the Dashboard.',
					'expect' => 'The widget says Archive.org API services are online.',
					'link'   => 'admin:index.php',
					'when'   => array( $dashboard, $online( 'yes' ) ),
					'checks' => array(
						array(
							'type'     => 'text',
							'selector' => '#iawmlf_dashboard_widget',
							'has'      => 'services are online',
							'say'      => 'The widget says Archive.org is online',
						),
					),
				),
				array(
					'id'     => 'bulk-back',
					'do'     => 'Open Link Fixer, Links.',
					'expect' => 'All four bulk actions are back.',
					'link'   => 'admin:admin.php?page=iawmlf-links',
					'when'   => array( $links, $online( 'yes' ) ),
					'checks' => array( $bulk( 5, 'The dropdown has the four bulk actions (plus "Bulk actions")' ) ),
				),
				array(
					'id'     => 'o2-archived',
					'do'     => 'Press Run next job until O2 (o2-new-snapshot) is archived.',
					'expect' => 'Now Archive.org is back, O2 gets a new snapshot and is archived.',
					'when'   => array(
						array(
							'type'  => 'link_row',
							'url'   => $o2,
							'field' => 'archived',
							'is'    => true,
						),
					),
					'checks' => array(
						array(
							'type'    => 'calls',
							'method'  => 'create_snapshot',
							'url_has' => 'o2-new-snapshot',
							'min'     => 1,
							'max'     => 1,
							'say'     => 'One new snapshot was asked for',
						),
					),
				),
				array(
					'id'     => 'o1-archived',
					'do'     => 'Keep pressing Run next job (O1\'s job was put back an hour, so it comes last).',
					'expect' => 'O1 gets its existing archive URL.',
					'when'   => array(
						array(
							'type'  => 'link_row',
							'url'   => $o1,
							'field' => 'process',
							'is'    => 'done',
						),
					),
					'checks' => array(
						array(
							'type'  => 'link_row',
							'url'   => $o1,
							'field' => 'archived',
							'is'    => true,
							'say'   => 'O1 has an archive URL',
						),
					),
				),
			),
		);
	}
);
