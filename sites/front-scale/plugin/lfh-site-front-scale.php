<?php
/**
 * Plugin Name: Link Fixer Harness site: Front end link checks at scale
 * Description: One post with 40 links all due a check and all on screen as the page opens. The checklist panel counts the link checks the page sends, how many at once, and how long they take.
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
		require_once __DIR__ . '/Scenario_Front_Scale.php';
		add_filter( 'lfh_scenarios', static fn( $scenarios ) => array_merge( $scenarios, array( new Scenario_Front_Scale() ) ) );
	}
);

add_filter(
	'lfh_checklist',
	static function () {
		$count   = Scenario_Front_Scale::COUNT;
		$fetches = static fn( $field, $test, $value, $say ) => array(
			'type'  => 'fetches',
			'field' => $field,
			$test   => $value,
			'say'   => $say,
		);
		$checks  = static fn( $min, $max, $say ) => array(
			'type'    => 'calls',
			'method'  => 'check_single',
			'url_has' => 'harness.test/f',
			'min'     => $min,
			'max'     => $max,
			'say'     => $say,
		);
		$settled = $fetches( 'done', 'min', $count, '' );

		return array(
			'title' => 'Front end link checks at scale',
			'intro' => 'One post with 40 links, F01 to F40, all due a check and all on screen as it opens. The Archive.org stand-in takes half a second to answer about each. Open the post as a visitor would.',
			'items' => array(
				array(
					'id'     => 'all-checked',
					'do'     => 'Open the post "Harness: 40 links due a check" and wait for the checks to finish.',
					'expect' => 'The page sends one check per link, and Archive.org is asked about each link once.',
					'link'   => 'front:/?lfh_go=front-scale',
					'when'   => array( $settled ),
					'checks' => array(
						$fetches( 'count', 'is', $count, 'The page sent 40 checks, one per link' ),
						$checks( $count, $count, 'Archive.org was asked about each link once' ),
						$fetches( 'seconds', 'max', 60, 'All 40 answered within a minute' ),
					),
				),
				array(
					'id'     => 'at-once',
					'do'     => 'Look at how the checks were sent.',
					'expect' => 'No more than 6 checks wait at the same time. Each one holds a PHP worker on the server while Archive.org answers, so a post with many links should not take all of them at once.',
					'when'   => array( $settled ),
					'checks' => array( $fetches( 'most', 'max', 6, 'At most 6 checks at once' ) ),
				),
				array(
					'id'     => 'second-visit',
					'do'     => 'Reload the post.',
					'expect' => 'Nothing is checked again: every link was checked moments ago.',
					'when'   => array( $fetches( 'count', 'is', 0, '' ), $checks( $count, null, '' ) ),
					'checks' => array( $checks( 0, $count, 'No link was checked again' ) ),
				),
			),
		);
	}
);
