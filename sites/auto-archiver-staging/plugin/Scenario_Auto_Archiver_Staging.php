<?php
/**
 * Scenario: a staging copy of a site with the Auto Archiver turned on.
 *
 * @package LinkFixer_Harness
 */

namespace LinkFixer_Harness;

use Internet_Archive\Wayback_Machine_Link_Fixer\Event\Process_Local_Post_Event;
use Internet_Archive\Wayback_Machine_Link_Fixer\Event\Scan_Own_Posts_Event;
use Internet_Archive\Wayback_Machine_Link_Fixer\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Auto Archiver on staging.
 */
class Scenario_Auto_Archiver_Staging extends Scenario {

	/**
	 * Slug.
	 *
	 * @return string
	 */
	public function slug(): string {
		return 'auto-archiver-staging';
	}

	/**
	 * Plain English name.
	 *
	 * @return string
	 */
	public function title(): string {
		return 'Auto Archiver on staging';
	}

	/**
	 * What it proves.
	 *
	 * @return string
	 */
	public function summary(): string {
		return 'On a staging site the Auto Archiver stays off even with its setting on, so staging addresses are never archived.';
	}

	/**
	 * One post, due a routine rescan on a production site.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function posts(): array {
		return array(
			'old' => array(
				'title' => 'Harness: archived 30 days ago (staging)',
				'intro' => 'On a production site the routine rescan would archive this post again.',
			),
		);
	}

	/**
	 * No links.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function links(): array {
		return array();
	}

	/**
	 * Seeds with the Auto Archiver settings on, as if copied from production.
	 *
	 * @return array<string, mixed> The registry.
	 */
	public function seed(): array {
		as_unschedule_all_actions( Process_Local_Post_Event::HANDLE );
		as_unschedule_all_actions( Scan_Own_Posts_Event::HANDLE );

		$registry = parent::seed();

		update_post_meta( $registry['posts']['old'], Settings::OWN_LINK_LAST_PROCESSED, time() - 30 * DAY_IN_SECONDS );
		update_option( Settings::ALLOW_OWN_CONTENT_SUBMISSIONS, true );
		update_option( Settings::ROUTINELY_UPDATE_WAYBACK_MACHINE, true );
		update_option( Settings::ALLOWED_OWN_CONTENT_POST_TYPES, array( 'post', 'page' ) );

		return $registry;
	}
}
