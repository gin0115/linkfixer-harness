<?php
/**
 * Scenario: the links an editor works with once the reporting screens are opened to editors.
 *
 * @package LinkFixer_Harness
 */

namespace LinkFixer_Harness;

defined( 'ABSPATH' ) || exit;

/**
 * Editor permissions.
 */
class Scenario_Permissions extends Scenario {

	/**
	 * Slug.
	 *
	 * @return string
	 */
	public function slug(): string {
		return 'permissions';
	}

	/**
	 * Plain English name.
	 *
	 * @return string
	 */
	public function title(): string {
		return 'Editor permissions';
	}

	/**
	 * What it proves.
	 *
	 * @return string
	 */
	public function summary(): string {
		return 'With iawmlf_reporting_page_capability set to edit_posts, an editor gets the reporting screens but not the settings or the setup wizard.';
	}

	/**
	 * Two posts: one with links, one on the Link Fixer excluded posts list.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function posts(): array {
		return array(
			'report'   => array(
				'title' => 'Harness: an editor\'s links',
				'intro' => 'Three links for the editor: E1 is broken, E2 and E3 work.',
			),
			'excluded' => array(
				'title'    => 'Harness: an excluded post',
				'intro'    => 'This post is on the Link Fixer excluded posts list.',
				'excluded' => true,
			),
		);
	}

	/**
	 * Three links.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function links(): array {
		return array(
			self::link(
				array(
					'id'      => 'E1',
					'label'   => 'Broken',
					'url'     => self::url( 'e1-broken', 'check/404' ),
					'history' => self::failed_history(),
					'broken'  => true,
					'posts'   => array( 'report' ),
				)
			),
			self::link(
				array(
					'id'      => 'E2',
					'label'   => 'Working',
					'url'     => self::url( 'e2-working', 'check/200' ),
					'history' => array( array( 200, 4 ) ),
					'posts'   => array( 'report', 'excluded' ),
				)
			),
			self::link(
				array(
					'id'      => 'E3',
					'label'   => 'Working, for the editor to exclude',
					'url'     => self::url( 'e3-exclude', 'check/200' ),
					'history' => array( array( 200, 4 ) ),
					'posts'   => array( 'report' ),
				)
			),
		);
	}
}
