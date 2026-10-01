<?php
/**
 * Scenario: 60 links for running the Links report bulk actions on all of them at once.
 *
 * @package LinkFixer_Harness
 */

namespace LinkFixer_Harness;

defined( 'ABSPATH' ) || exit;

/**
 * Bulk actions at scale.
 */
class Scenario_Bulk_Actions extends Scenario {

	/**
	 * How many links.
	 */
	public const COUNT = 60;

	/**
	 * Snapshots the Archive.org stand-in accepts a minute.
	 */
	public const LIMIT = 10;

	/**
	 * Slug.
	 *
	 * @return string
	 */
	public function slug(): string {
		return 'bulk-actions';
	}

	/**
	 * Plain English name.
	 *
	 * @return string
	 */
	public function title(): string {
		return 'Bulk actions at scale';
	}

	/**
	 * What it proves.
	 *
	 * @return string
	 */
	public function summary(): string {
		return 'The four Links report bulk actions run on 60 links at once, with a slow Archive.org and a limit of 10 snapshots a minute.';
	}

	/**
	 * One post.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function posts(): array {
		return array(
			'bulk' => array(
				'title' => 'Harness: 60 links',
				'intro' => 'Sixty working links, B01 to B60, for the Links report bulk actions.',
			),
		);
	}

	/**
	 * B01 to B60: working, archived in 2020, and Archive.org takes a quarter of a second to answer about each.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function links(): array {
		$links = array();
		for ( $n = 1; $n <= self::COUNT; $n++ ) {
			$url     = self::url( sprintf( 'b%02d', $n ), 'check/200/wait/0.25' );
			$links[] = self::link(
				array(
					'id'       => sprintf( 'B%02d', $n ),
					'label'    => 'Working',
					'url'      => $url,
					'history'  => array( array( 200, 4 ) ),
					'archived' => 'https://web.archive.org/web/20200101000000/' . $url,
					'posts'    => array( 'bulk' ),
				)
			);
		}
		return $links;
	}

	/**
	 * Seeds, clears any snapshot jobs and sets the stand-in's snapshot limit.
	 *
	 * @return array<string, mixed> The registry.
	 */
	public function seed(): array {
		foreach ( array( 'iawmlf_check_snapshot_status', 'iawmlf_check_validator_status', 'iawmlf_create_new_snapshot' ) as $hook ) {
			as_unschedule_all_actions( $hook );
		}

		$registry = parent::seed();
		update_option( 'lfh_snapshot_limit', self::LIMIT );

		return $registry;
	}
}
