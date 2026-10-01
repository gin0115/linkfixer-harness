<?php
/**
 * Scenario: content written before the Link Fixer was installed, for its scan of existing posts.
 *
 * @package LinkFixer_Harness
 */

namespace LinkFixer_Harness;

use Internet_Archive\Wayback_Machine_Link_Fixer\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Scanning existing posts.
 */
class Scenario_Post_Scan extends Scenario {

	/**
	 * The posts the scan should reach.
	 */
	public const SCANNABLE = array( 's1', 's2', 's3', 's4', 's5', 'page' );

	/**
	 * Slug.
	 *
	 * @return string
	 */
	public function slug(): string {
		return 'post-scan';
	}

	/**
	 * Plain English name.
	 *
	 * @return string
	 */
	public function title(): string {
		return 'Scanning existing posts';
	}

	/**
	 * What it proves.
	 *
	 * @return string
	 */
	public function summary(): string {
		return 'The scan of existing posts works through published posts and pages in batches, skipping drafts and excluded posts, and onboarding ends when it is done.';
	}

	/**
	 * Five posts, a page, an excluded post and a draft.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function posts(): array {
		$posts = array();
		for ( $n = 1; $n <= 5; $n++ ) {
			$posts[ 's' . $n ] = array(
				'title' => 'Harness: existing post ' . $n,
				'intro' => 'Written before the Link Fixer was installed.',
			);
		}

		return array_merge(
			$posts,
			array(
				'page'     => array(
					'title' => 'Harness: existing page',
					'intro' => 'A page, written before the Link Fixer was installed.',
					'type'  => 'page',
				),
				'excluded' => array(
					'title'    => 'Harness: existing excluded post',
					'intro'    => 'On the Link Fixer excluded posts list.',
					'excluded' => true,
				),
				'draft'    => array(
					'title'  => 'Harness: existing draft',
					'intro'  => 'A draft: not scanned until it is published.',
					'status' => 'draft',
				),
			)
		);
	}

	/**
	 * One link in each post, left for the scan to find.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function links(): array {
		$links = array();
		foreach ( array_keys( $this->posts() ) as $role ) {
			$links[] = self::link(
				array(
					'id'     => strtoupper( $role ),
					'label'  => 'Found by the scan',
					'url'    => self::url( 'scan-' . $role, 'check/200' ),
					'posts'  => array( $role ),
					'static' => true,
				)
			);
		}
		return $links;
	}

	/**
	 * Seeds, then makes the posts look unscanned and turns the scan on.
	 *
	 * @return array<string, mixed> The registry.
	 */
	public function seed(): array {
		foreach ( array( 'iawmlf_scan_existing_posts', 'iawmlf_find_or_create_snapshot' ) as $hook ) {
			as_unschedule_all_actions( $hook );
		}

		$registry = parent::seed();
		$posts    = array_map( 'intval', array_values( $registry['posts'] ) );

		// Content from before the Link Fixer was installed has not been scanned.
		foreach ( $posts as $post_id ) {
			delete_post_meta( $post_id, Settings::LINK_META_KEY );
		}

		// Anything else on the site counts as scanned, so the numbers only cover these posts.
		$others = get_posts(
			array(
				'post_type'    => Settings::get_allowed_post_types(),
				'post_status'  => 'any',
				'numberposts'  => -1,
				'fields'       => 'ids',
				'post__not_in' => $posts,
			)
		);
		foreach ( $others as $post_id ) {
			if ( ! metadata_exists( 'post', (int) $post_id, Settings::LINK_META_KEY ) ) {
				update_post_meta( (int) $post_id, Settings::LINK_META_KEY, array() );
			}
		}

		update_option( Settings::SCAN_EXISTING_POSTS, true );
		delete_transient( 'iawmlf_dashboard_onboarding_stats' );
		delete_transient( 'iawmlf_dashboard_stats' );

		return $registry;
	}
}
