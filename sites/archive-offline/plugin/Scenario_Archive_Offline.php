<?php
/**
 * Scenario: Archive.org is offline, with two new links waiting to be archived.
 *
 * @package LinkFixer_Harness
 */

namespace LinkFixer_Harness;

use Internet_Archive\Wayback_Machine_Link_Fixer\WP_Post\WP_Post_Controller;

defined( 'ABSPATH' ) || exit;

/**
 * Archive.org offline.
 */
class Scenario_Archive_Offline extends Scenario {

	/**
	 * Slug.
	 *
	 * @return string
	 */
	public function slug(): string {
		return 'archive-offline';
	}

	/**
	 * Plain English name.
	 *
	 * @return string
	 */
	public function title(): string {
		return 'Archive.org offline';
	}

	/**
	 * What it proves.
	 *
	 * @return string
	 */
	public function summary(): string {
		return 'With Archive.org offline the Link Fixer says so, disables its bulk actions and puts its jobs back for an hour; once it is back online everything carries on.';
	}

	/**
	 * The post.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function posts(): array {
		return array(
			'offline' => array(
				'title' => 'Harness: while Archive.org is offline',
				'intro' => 'Two new links, found while Archive.org was offline.',
			),
		);
	}

	/**
	 * Two links, left for the Link Fixer's own scan to find.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function links(): array {
		return array(
			self::link(
				array(
					'id'     => 'O1',
					'label'  => 'Already archived',
					'url'    => self::url( 'o1-archived', 'check/200/archive/yes' ),
					'posts'  => array( 'offline' ),
					'static' => true,
				)
			),
			self::link(
				array(
					'id'     => 'O2',
					'label'  => 'Needs a new snapshot',
					'url'    => self::url( 'o2-new-snapshot', 'check/200/archive/none,yes/save/ok/status/success' ),
					'posts'  => array( 'offline' ),
					'static' => true,
				)
			),
		);
	}

	/**
	 * Seeds, takes the Archive.org stand-in offline, then lets the Link Fixer's scan find the links.
	 *
	 * @return array<string, mixed> The registry.
	 */
	public function seed(): array {
		foreach ( array( 'iawmlf_find_or_create_snapshot', 'iawmlf_create_new_snapshot', 'iawmlf_check_snapshot_status', 'iawmlf_update_archive_url' ) as $hook ) {
			as_unschedule_all_actions( $hook );
		}

		$registry = parent::seed();

		update_option( 'lfh_archive_online', 'no' );
		// The Link Fixer caches the online status for an hour.
		delete_transient( 'iawmlf_archive_api_online' );

		( new WP_Post_Controller() )->process_links_in_content( (int) $registry['posts']['offline'] );

		return $registry;
	}
}
