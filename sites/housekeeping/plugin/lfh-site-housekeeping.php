<?php
/**
 * Plugin Name: Link Fixer Harness site: Failed job clean-up
 * Description: Failed retry jobs from 10 days ago and 2 days ago. The checklist panel covers the daily clean-up removing the old ones, keeping each last attempt, and queueing itself again.
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
		require_once __DIR__ . '/Scenario_Housekeeping.php';
		add_filter( 'lfh_scenarios', static fn( $scenarios ) => array_merge( $scenarios, array( new Scenario_Housekeeping() ) ) );

		// Jobs only run when the panel's buttons say so.
		Queue::manual();
	}
);

add_filter(
	'lfh_checklist',
	static function () {
		$scenario = Scenarios::get( 'housekeeping' );
		$registry = $scenario ? (array) $scenario->registry() : array();
		$h1       = (int) ( $registry['links']['H1'] ?? 0 );
		$h2       = (int) ( $registry['links']['H2'] ?? 0 );
		$jobs     = Scenario_Housekeeping::jobs( $h1, $h2 );

		$failed = static fn( $hook, $count, $say, $args = null ) => array_filter(
			array(
				'type'   => 'action',
				'hook'   => $hook,
				'status' => 'failed',
				'count'  => $count,
				'args'   => $args,
				'say'    => $say,
			),
			static fn( $value ) => null !== $value
		);
		$cleanup = static fn( $status, $extra = array() ) => array_merge(
			array(
				'type'   => 'action',
				'hook'   => Scenario_Housekeeping::HOOK,
				'status' => $status,
			),
			$extra
		);
		$tomorrow = array(
			'due_min' => 1,
			'due_max' => DAY_IN_SECONDS,
			'say'     => 'The clean-up is queued again for midnight',
		);

		return array(
			'title' => 'Failed job clean-up',
			'intro' => 'Failed retry jobs for two links: H1\'s from 10 days ago (older than the 7 day limit), H2\'s from 2 days ago. The daily clean-up ("Clean up old failed jobs") is due now. Jobs only run when you press Run next job.',
			'queue' => true,
			'items' => array(
				array(
					'id'     => 'before',
					'do'     => 'Open Link Fixer, Links.',
					'expect' => 'Before the clean-up there are 12 failed retry jobs: 5 snapshot checks, 3 new snapshots, 2 archive URL updates and 2 verification checks.',
					'link'   => 'admin:admin.php?page=iawmlf-links',
					'when'   => array( $cleanup( 'complete', array( 'count' => 0 ) ) ),
					'checks' => array(
						$failed( 'iawmlf_check_snapshot_status', 5, '5 failed snapshot checks' ),
						$failed( 'iawmlf_create_new_snapshot', 3, '3 failed new snapshots' ),
						$failed( 'iawmlf_update_archive_url', 2, '2 failed archive URL updates' ),
						$failed( 'iawmlf_check_validator_status', 2, '2 failed verification checks' ),
					),
				),
				array(
					'id'     => 'clean',
					'do'     => 'Press Run next job once.',
					'expect' => 'The clean-up keeps only the last attempt of each of H1\'s old jobs, and leaves H2\'s recent ones alone: 6 failed jobs remain.',
					'when'   => array( $cleanup( 'complete', array( 'min' => 1 ) ) ),
					'checks' => array(
						$failed( 'iawmlf_check_snapshot_status', 3, '3 snapshot checks remain (H1\'s last attempt, H2\'s two)' ),
						$failed( 'iawmlf_check_snapshot_status', 1, 'H1\'s last snapshot check attempt is kept', $jobs['iawmlf_check_snapshot_status'][2][0] ),
						$failed( 'iawmlf_create_new_snapshot', 2, '2 new snapshots remain (H1\'s last attempt, H2\'s)' ),
						$failed( 'iawmlf_create_new_snapshot', 1, 'H1\'s last new snapshot attempt is kept', $jobs['iawmlf_create_new_snapshot'][1][0] ),
						$failed( 'iawmlf_update_archive_url', 1, '1 archive URL update remains' ),
						$failed( 'iawmlf_check_validator_status', 1, '1 verification check remains' ),
					),
				),
				array(
					'id'     => 'requeued',
					'do'     => 'Look at the waiting jobs in the panel (do not reload the page).',
					'expect' => 'The clean-up has queued itself again for midnight, as its own code does when it finishes.',
					'when'   => array( $cleanup( 'complete', array( 'min' => 1 ) ) ),
					'checks' => array( $cleanup( 'pending', array_merge( $tomorrow, array( 'min' => 1 ) ) ) ),
				),
			),
		);
	}
);
