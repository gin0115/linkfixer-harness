<?php
/**
 * Plugin Name: Link Fixer Harness site: background archiving and retries
 * Description: One post with eight scripted links. Action Scheduler is stopped from running jobs by itself; the panel's Run next job button runs them one at a time, so every retry can be watched.
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
		require_once __DIR__ . '/Scenario_Archive_Pipeline.php';
		add_filter( 'lfh_scenarios', static fn( $scenarios ) => array_merge( $scenarios, array( new Scenario_Archive_Pipeline() ) ) );

		// Jobs only run when the panel's buttons say so.
		Queue::manual();
	}
);

add_filter(
	'lfh_checklist',
	static function () {
		$url = static fn( $id ) => array(
			'P1' => 'https://harness.test/p1-archived/check/200/archive/yes',
			'P2' => 'https://harness.test/p2-saved/check/200/archive/none,yes/save/ok/status/pending,success',
			'P3' => 'https://harness.test/p3-save-retried/check/200/archive/none,yes/save/offline,ok/status/success',
			'P4' => 'https://harness.test/p4-daily-limit/check/200/archive/none/save/limit',
			'P5' => 'https://harness.test/p5-no-access/check/200/archive/none/save/ok/status/no-access',
			'P6' => 'https://harness.test/p6-never-finishes/check/200/archive/none/save/ok/status/pending',
			'P7' => 'https://harness.test/p7-blocks-bots/check/403/archive/yes/save/ok/status/no-access',
			'P8' => 'https://harness.test/p8-redirects/check/200/archive/none,yes/save/ok/status/success/final/moved',
		)[ $id ];

		$row   = static fn( $id, $field, $is, $say = '' ) => array_filter(
			array(
				'type'  => 'link_row',
				'url'   => $url( $id ),
				'field' => $field,
				'is'    => $is,
				'say'   => $say,
			),
			static fn( $value ) => '' !== $value
		);
		$calls = static fn( $method, $slug, $min, $max, $say = '' ) => array_filter(
			array(
				'type'    => 'calls',
				'method'  => $method,
				'url_has' => $slug,
				'min'     => $min,
				'max'     => $max,
				'say'     => $say,
			),
			static fn( $value ) => '' !== $value
		);
		$done  = static fn( $id ) => $row( $id, 'process', 'done' );

		return array(
			'title' => 'Background archiving and retries',
			'intro' => 'Eight links, P1 to P8, each scripted to take a different path through the archiving jobs. Background jobs only run when you press Run next job, so each step can be watched. Run next job always runs the job due soonest, as if time had passed until then.',
			'queue' => true,
			'items' => array(
				array(
					'id'     => 'queued',
					'do'     => 'Look at Background jobs in this panel before running anything.',
					'expect' => 'The post\'s 8 links were found and a "Find or create a snapshot" job is waiting for each. Archive.org has not been asked anything yet.',
					'checks' => array(
						array(
							'type'   => 'action',
							'hook'   => 'iawmlf_find_or_create_snapshot',
							'status' => 'pending',
							'count'  => 8,
							'say'    => '8 "Find or create a snapshot" jobs are waiting',
						),
						$calls( 'get_latest_snapshot', 'harness.test/p', 0, 0, 'Archive.org has not been asked anything yet' ),
					),
				),
				array(
					'id'     => 'p1',
					'do'     => 'Press Run next job until P1 (p1-archived) has run.',
					'expect' => 'P1 already had a copy on the Wayback Machine: it gets that archive URL straight away and is checked once (200). No new snapshot is asked for.',
					'when'   => array( $done( 'P1' ) ),
					'checks' => array(
						$row( 'P1', 'archived', true, 'P1 has an archive URL' ),
						$row( 'P1', 'last_code', 200, 'P1 was checked and returned 200' ),
						$calls( 'create_snapshot', 'p1-archived', 0, 0, 'No new snapshot was asked for' ),
					),
				),
				array(
					'id'     => 'p2-waits',
					'do'     => 'Keep pressing Run next job until P2 (p2-saved) has asked for a new snapshot.',
					'expect' => 'P2 had no copy, so a new snapshot was asked for, and a "Check the new snapshot" job waits 10 minutes before looking at it.',
					'when'   => array(
						$calls( 'create_snapshot', 'p2-saved', 1, 1 ),
						$calls( 'get_snapshot_status', 'p2-saved', 0, 0 ),
					),
					'checks' => array(
						array(
							'type'    => 'action',
							'hook'    => 'iawmlf_check_snapshot_status',
							'status'  => 'pending',
							'url'     => $url( 'P2' ),
							'min'     => 1,
							'due_min' => 8 * MINUTE_IN_SECONDS,
							'due_max' => 11 * MINUTE_IN_SECONDS,
							'say'     => 'P2\'s snapshot check is due in about 10 minutes',
						),
					),
				),
				array(
					'id'     => 'p3-retry',
					'do'     => 'Keep pressing Run next job until P3 (p3-save-retried) has tried to save.',
					'expect' => 'Archive.org was busy, so P3\'s save failed and a retry waits 15 minutes.',
					'when'   => array( $calls( 'create_snapshot', 'p3-save-retried', 1, 1 ) ),
					'checks' => array(
						array(
							'type'    => 'action',
							'hook'    => 'iawmlf_create_new_snapshot',
							'status'  => 'pending',
							'url'     => $url( 'P3' ),
							'min'     => 1,
							'due_min' => 13 * MINUTE_IN_SECONDS,
							'due_max' => 16 * MINUTE_IN_SECONDS,
							'say'     => 'P3\'s retry is due in about 15 minutes',
						),
					),
				),
				array(
					'id'     => 'p4-limit',
					'do'     => 'Keep pressing Run next job until P4 (p4-daily-limit) has tried to save.',
					'expect' => 'Archive.org\'s daily snapshot limit was hit, so P4\'s retry waits 24 hours.',
					'when'   => array( $calls( 'create_snapshot', 'p4-daily-limit', 1, 1 ) ),
					'checks' => array(
						array(
							'type'    => 'action',
							'hook'    => 'iawmlf_create_new_snapshot',
							'status'  => 'pending',
							'url'     => $url( 'P4' ),
							'min'     => 1,
							'due_min' => 23 * HOUR_IN_SECONDS,
							'due_max' => 25 * HOUR_IN_SECONDS,
							'say'     => 'P4\'s retry is due in about 24 hours',
						),
					),
				),
				array(
					'id'     => 'p5-no-access',
					'do'     => 'Keep going until P5 (p5-no-access) has been checked.',
					'expect' => 'Archive.org refused to archive P5 (no-access), so P5 is excluded and finished, with no archive.',
					'when'   => array( $row( 'P5', 'excluded', true ) ),
					'checks' => array(
						$done( 'P5' ),
						$row( 'P5', 'archived', false, 'P5 has no archive URL' ),
					),
				),
				array(
					'id'     => 'p7-validated',
					'do'     => 'Keep going until P7 (p7-blocks-bots) has been verified.',
					'expect' => 'P7 had a copy, but checking it returned 403. The Link Fixer asked Archive.org to try it, Archive.org was refused (no-access), so P7 is excluded.',
					'when'   => array( $row( 'P7', 'excluded', true ) ),
					'checks' => array(
						$row( 'P7', 'last_code', 403, 'P7\'s check returned 403' ),
						$row( 'P7', 'archived', true, 'P7 keeps the archive URL it had' ),
						$calls( 'create_snapshot', 'p7-blocks-bots', 1, 1, 'Archive.org was asked to try P7 once' ),
					),
				),
				array(
					'id'     => 'p2-archived',
					'do'     => 'Keep going until P2 is archived.',
					'expect' => 'The first check of P2\'s snapshot said pending, so it was checked again; the second said success and the new archive URL was saved.',
					'when'   => array( $row( 'P2', 'archived', true ) ),
					'checks' => array(
						$calls( 'get_snapshot_status', 'p2-saved', 2, 2, 'The snapshot was checked exactly twice' ),
						$calls( 'create_snapshot', 'p2-saved', 1, 1, 'Only one snapshot was asked for' ),
						$done( 'P2' ),
					),
				),
				array(
					'id'     => 'p8-redirect',
					'do'     => 'Keep going until P8 (p8-redirects) is archived.',
					'expect' => 'P8 redirects, so the address it redirects to is recorded, and it ends up archived.',
					'when'   => array( $row( 'P8', 'archived', true ) ),
					'checks' => array(
						array(
							'type'  => 'link_row',
							'url'   => $url( 'P8' ),
							'field' => 'redirect',
							'has'   => '-moved',
							'say'   => 'The address P8 redirects to is recorded',
						),
					),
				),
				array(
					'id'     => 'p3-archived',
					'do'     => 'Keep going until P3 is archived.',
					'expect' => 'P3\'s retry (attempt 1) saved successfully and P3 is archived.',
					'when'   => array( $row( 'P3', 'archived', true ) ),
					'checks' => array(
						$calls( 'create_snapshot', 'p3-save-retried', 2, 2, 'P3 was saved on the second try' ),
						$done( 'P3' ),
					),
				),
				array(
					'id'     => 'p6-gives-up',
					'do'     => 'Keep going until P6 (p6-never-finishes) is finished.',
					'expect' => 'P6\'s snapshot stayed pending on every check. After 3 checks the Link Fixer gives up; P6 is finished with no archive.',
					'when'   => array( $done( 'P6' ) ),
					'checks' => array(
						$calls( 'get_snapshot_status', 'p6-never-finishes', 3, 3, 'The snapshot was checked 3 times' ),
						$row( 'P6', 'archived', false, 'P6 has no archive URL' ),
					),
				),
				array(
					'id'     => 'p4-gives-up',
					'do'     => 'Keep going (or press Run all jobs) until P4 is finished.',
					'expect' => 'Every try hit the daily limit. After 4 tries (the first and 3 retries) the Link Fixer gives up; P4 is finished with no archive.',
					'when'   => array( $done( 'P4' ) ),
					'checks' => array(
						$calls( 'create_snapshot', 'p4-daily-limit', 4, 4, 'P4 was tried 4 times' ),
						$row( 'P4', 'archived', false, 'P4 has no archive URL' ),
					),
				),
				array(
					'id'     => 'all-done',
					'do'     => 'Run any jobs still waiting.',
					'expect' => 'Every link is finished and no archiving job is left waiting.',
					'when'   => array( $done( 'P1' ), $done( 'P2' ), $done( 'P3' ), $done( 'P4' ), $done( 'P5' ), $done( 'P6' ), $done( 'P7' ), $done( 'P8' ) ),
					'checks' => array_map(
						static fn( $hook ) => array(
							'type'   => 'action',
							'hook'   => $hook,
							'status' => 'pending',
							'count'  => 0,
						),
						array( 'iawmlf_find_or_create_snapshot', 'iawmlf_create_new_snapshot', 'iawmlf_check_snapshot_status', 'iawmlf_update_archive_url', 'iawmlf_link_access_validator', 'iawmlf_check_validator_status' )
					),
				),
			),
		);
	}
);
