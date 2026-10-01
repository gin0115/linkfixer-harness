<?php
/**
 * Scenario: posts to find and exclude in Advanced Settings.
 *
 * @package LinkFixer_Harness
 */

namespace LinkFixer_Harness;

use Internet_Archive\Wayback_Machine_Link_Fixer\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Advanced Settings: excluded posts.
 */
class Scenario_Settings_Exclusions extends Scenario {

	/**
	 * Slug.
	 *
	 * @return string
	 */
	public function slug(): string {
		return 'settings-exclusions';
	}

	/**
	 * Plain English name.
	 *
	 * @return string
	 */
	public function title(): string {
		return 'Advanced Settings: excluded posts';
	}

	/**
	 * What it proves.
	 *
	 * @return string
	 */
	public function summary(): string {
		return 'Finding posts to exclude in Advanced Settings: titles with an ampersand, pages for the Auto Archiver, drafts, saving the list.';
	}

	/**
	 * A post with an ampersand in its title, a page and a draft.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function posts(): array {
		return array(
			'tips'  => array(
				'title' => 'Harness: Tips & Tricks',
				'intro' => 'A title with an ampersand.',
			),
			'about' => array(
				'title' => 'Harness: About us',
				'intro' => 'A page.',
				'type'  => 'page',
			),
			'draft' => array(
				'title'  => 'Harness: Draft tips',
				'intro'  => 'A draft, not published yet.',
				'status' => 'draft',
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
	 * Seeds with empty excluded posts lists and the Auto Archiver on.
	 *
	 * @return array<string, mixed> The registry.
	 */
	public function seed(): array {
		$registry = parent::seed();

		update_option( Settings::LINK_FIXER_EXCLUDED_POSTS, array() );
		update_option( Settings::AUTO_ARCHIVER_EXCLUDED_POSTS, array() );
		update_option( Settings::ALLOW_OWN_CONTENT_SUBMISSIONS, true );
		update_option( Settings::ALLOWED_OWN_CONTENT_POST_TYPES, array( 'post', 'page' ) );

		return $registry;
	}
}
