<?php
/**
 * Plugin Name: Link Fixer Harness site: display modes and link icons
 * Description: Seeds five posts, each forcing its own fixer mode and link icon for its own page views: replace, replace with the icon before, replace with the icon after, check only, do nothing.
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
		require_once __DIR__ . '/Scenario_Display_Modes.php';
		add_filter( 'lfh_scenarios', static fn( $scenarios ) => array_merge( $scenarios, array( new Scenario_Display_Modes() ) ) );
	}
);
