<?php
/**
 * Plugin Name: Link Fixer Harness site: links on the page
 * Description: Seeds one post with a link in every state (broken, recovered, newly broken, flaky, offline checker, 403, 429, no archive, below the fold, every exclusion type), plus a second post and an excluded post.
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
		require_once __DIR__ . '/Scenario_Mixed_Links.php';
		add_filter( 'lfh_scenarios', static fn( $scenarios ) => array_merge( $scenarios, array( new Scenario_Mixed_Links() ) ) );
	}
);
