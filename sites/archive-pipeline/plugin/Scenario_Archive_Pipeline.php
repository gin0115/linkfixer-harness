<?php
/**
 * Scenario: one post with eight links, each taking a different path through the archiving jobs.
 *
 * @package LinkFixer_Harness
 */

namespace LinkFixer_Harness;

use Internet_Archive\Wayback_Machine_Link_Fixer\WP_Post\WP_Post_Controller;

defined( 'ABSPATH' ) || exit;

/**
 * Archive pipeline.
 */
class Scenario_Archive_Pipeline extends Scenario {

	/**
	 * The Link Fixer's archiving job hooks, cleared on reseed.
	 */
	private const HOOKS = array(
		'iawmlf_find_or_create_snapshot',
		'iawmlf_create_new_snapshot',
		'iawmlf_check_snapshot_status',
		'iawmlf_update_archive_url',
		'iawmlf_link_access_validator',
		'iawmlf_check_validator_status',
	);

	/**
	 * Slug.
	 *
	 * @return string
	 */
	public function slug(): string {
		return 'archive-pipeline';
	}

	/**
	 * Plain English name.
	 *
	 * @return string
	 */
	public function title(): string {
		return 'Background archiving and retries';
	}

	/**
	 * What it proves.
	 *
	 * @return string
	 */
	public function summary(): string {
		return 'Eight links, each taking a different path through the archiving jobs: found, saved, retried, rate limited, refused, never finishing, blocking bots, and redirecting.';
	}

	/**
	 * The post.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function posts(): array {
		return array(
			'pipeline' => array(
				'title' => 'Harness: archive pipeline',
				'intro' => 'Eight links, each scripted to take a different path through the archiving jobs. Watch them in the harness panel\'s Background jobs list.',
			),
		);
	}

	/**
	 * A pipeline link URL.
	 *
	 * @param string $slug   The slug.
	 * @param string $script The script.
	 *
	 * @return string
	 */
	public static function link_url( string $slug, string $script ): string {
		return self::url( $slug, $script );
	}

	/**
	 * The eight links. They are "static": the Link Fixer's own scan creates their rows, not the seed.
	 *
	 * P8's script ends with final/moved, because the fake adds "-moved" to the end of the URL.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function links(): array {
		$links = array(
			'P1' => array( 'Already archived', 'p1-archived', 'check/200/archive/yes' ),
			'P2' => array( 'Saved first time', 'p2-saved', 'check/200/archive/none,yes/save/ok/status/pending,success' ),
			'P3' => array( 'Save retried', 'p3-save-retried', 'check/200/archive/none,yes/save/offline,ok/status/success' ),
			'P4' => array( 'Daily limit', 'p4-daily-limit', 'check/200/archive/none/save/limit' ),
			'P5' => array( 'Refused (no-access)', 'p5-no-access', 'check/200/archive/none/save/ok/status/no-access' ),
			'P6' => array( 'Never finishes', 'p6-never-finishes', 'check/200/archive/none/save/ok/status/pending' ),
			'P7' => array( 'Blocks bots (403)', 'p7-blocks-bots', 'check/403/archive/yes/save/ok/status/no-access' ),
			'P8' => array( 'Redirects', 'p8-redirects', 'check/200/archive/none,yes/save/ok/status/success/final/moved' ),
		);

		$defs = array();
		foreach ( $links as $id => $link ) {
			$defs[] = self::link(
				array(
					'id'     => $id,
					'label'  => $link[0],
					'url'    => self::url( $link[1], $link[2] ),
					'posts'  => array( 'pipeline' ),
					'static' => true,
				)
			);
		}
		return $defs;
	}

	/**
	 * Seeds the post, then lets the Link Fixer's own scan find the links and queue their first jobs.
	 *
	 * @return array<string, mixed> The registry.
	 */
	public function seed(): array {
		foreach ( self::HOOKS as $hook ) {
			as_unschedule_all_actions( $hook );
		}

		$registry = parent::seed();

		// What publishing a post runs: find the links, create their rows, queue "find or create a snapshot".
		( new WP_Post_Controller() )->process_links_in_content( (int) $registry['posts']['pipeline'] );

		return $registry;
	}
}
