<?php
/**
 * Scenario: posts at each point of the Auto Archiver's 28 day cycle.
 *
 * @package LinkFixer_Harness
 */

namespace LinkFixer_Harness;

use Internet_Archive\Wayback_Machine_Link_Fixer\Event\Process_Local_Post_Event;
use Internet_Archive\Wayback_Machine_Link_Fixer\Event\Scan_Own_Posts_Event;
use Internet_Archive\Wayback_Machine_Link_Fixer\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Auto Archiver.
 */
class Scenario_Auto_Archiver extends Scenario {

	/**
	 * Slug.
	 *
	 * @return string
	 */
	public function slug(): string {
		return 'auto-archiver';
	}

	/**
	 * Plain English name.
	 *
	 * @return string
	 */
	public function title(): string {
		return 'Auto Archiver';
	}

	/**
	 * What it proves.
	 *
	 * @return string
	 */
	public function summary(): string {
		return 'The Auto Archiver snapshots your own posts: on publish or update after an hour, and again once 28 days have passed, except posts excluded from it.';
	}

	/**
	 * Four posts.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function posts(): array {
		return array(
			'fresh'    => array(
				'title' => 'Harness: never archived',
				'intro' => 'This post has never been archived.',
			),
			'recent'   => array(
				'title' => 'Harness: archived 3 days ago',
				'intro' => 'This post was archived 3 days ago.',
			),
			'stale'    => array(
				'title' => 'Harness: archived 30 days ago',
				'intro' => 'This post was archived 30 days ago.',
			),
			'excluded' => array(
				'title' => 'Harness: excluded from auto archiving',
				'intro' => 'This post is on the Auto Archiver excluded posts list.',
			),
		);
	}

	/**
	 * No links: the Auto Archiver snapshots the posts themselves.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function links(): array {
		return array();
	}

	/**
	 * Seeds the posts with auto archiving off, then dates them and turns it on.
	 *
	 * @return array<string, mixed> The registry.
	 */
	public function seed(): array {
		as_unschedule_all_actions( Process_Local_Post_Event::HANDLE );
		as_unschedule_all_actions( Scan_Own_Posts_Event::HANDLE );

		// Off while the posts are inserted, so save_post does not queue them.
		update_option( Settings::ALLOW_OWN_CONTENT_SUBMISSIONS, false );

		$registry = parent::seed();
		$posts    = $registry['posts'];

		update_post_meta( $posts['recent'], Settings::OWN_LINK_LAST_PROCESSED, time() - 3 * DAY_IN_SECONDS );
		update_post_meta( $posts['stale'], Settings::OWN_LINK_LAST_PROCESSED, time() - 30 * DAY_IN_SECONDS );
		update_option( Settings::AUTO_ARCHIVER_EXCLUDED_POSTS, array( $posts['excluded'] ) );

		update_option( Settings::ALLOWED_OWN_CONTENT_POST_TYPES, array( 'post', 'page' ) );
		update_option( Settings::ROUTINELY_UPDATE_WAYBACK_MACHINE, true );
		update_option( Settings::ROUTINELY_UPDATE_WAYBACK_MACHINE_INTERVAL, 28 );
		update_option( Settings::ALLOW_OWN_CONTENT_SUBMISSIONS, true );

		return $registry;
	}
}
