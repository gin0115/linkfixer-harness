<?php
/**
 * Plugin Name: Link Fixer Harness site: Link formats
 * Description: The same kind of broken link written twelve ways, as authors write them. The checklist panel checks the browser replaces every one with its archive.
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
		require_once __DIR__ . '/Scenario_Link_Formats.php';
		add_filter( 'lfh_scenarios', static fn( $scenarios ) => array_merge( $scenarios, array( new Scenario_Link_Formats() ) ) );

		// The scan queues jobs for the new links; they wait, so the links stay as seeded.
		Queue::manual();
	}
);

add_filter(
	'lfh_checklist',
	static function () {
		$scenario = new Scenario_Link_Formats();
		$checks   = array();
		foreach ( $scenario->links() as $def ) {
			$checks[] = array(
				'type'     => 'exists',
				'selector' => 'a[data-lfh-id="' . $def['id'] . '"][href*="archive.org/web/"]',
				'say'      => $def['id'] . ' (' . strtolower( $def['label'] ) . ') is replaced with its archive',
			);
		}

		return array(
			'title' => 'Link formats',
			'intro' => 'Twelve broken links, each archived and checked yesterday, each written a different way: capitals, a trailing slash, a space, an accent, the default port, an ampersand, a fragment, a dot segment, spaces around it, an accented host. The browser rewrites some of these addresses; the Link Fixer has to match each one to its stored data.',
			'items' => array(
				array(
					'id'     => 'all-replaced',
					'do'     => 'Open the post "Harness: link formats".',
					'expect' => 'Every one of the twelve links is replaced with its archive (Fixer mode is "Replace link").',
					'link'   => 'front:/?lfh_go=link-formats',
					'when'   => array(
						array(
							'type'     => 'exists',
							'selector' => 'a[data-lfh-id="V1"][href*="archive.org/web/"]',
						),
					),
					'checks' => $checks,
				),
			),
		);
	}
);
