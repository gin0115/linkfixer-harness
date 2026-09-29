<?php
/**
 * Scenario: an onboarded site with default settings and no Archive.org keys. No posts or links.
 *
 * @package LinkFixer_Harness
 */

namespace LinkFixer_Harness;

defined( 'ABSPATH' ) || exit;

/**
 * Settings.
 */
class Scenario_Settings extends Scenario {

	/**
	 * Slug.
	 *
	 * @return string
	 */
	public function slug(): string {
		return 'settings';
	}

	/**
	 * Plain English name.
	 *
	 * @return string
	 */
	public function title(): string {
		return 'Advanced Settings';
	}

	/**
	 * What it proves.
	 *
	 * @return string
	 */
	public function summary(): string {
		return 'The Advanced Settings page: Archive.org keys, settings that show and hide, and saving values.';
	}

	/**
	 * No posts.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function posts(): array {
		return array();
	}

	/**
	 * No links.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function links(): array {
		return array();
	}
}
