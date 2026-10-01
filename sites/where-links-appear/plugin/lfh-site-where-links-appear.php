<?php
/**
 * Plugin Name: Link Fixer Harness site: Where links appear
 * Description: Broken links on the blog home page, on their own post, in a Button block, an image link and a synced pattern. The checklist panel checks each is replaced.
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
		require_once __DIR__ . '/Scenario_Where_Links_Appear.php';
		add_filter( 'lfh_scenarios', static fn( $scenarios ) => array_merge( $scenarios, array( new Scenario_Where_Links_Appear() ) ) );

		// The scan queues jobs for the links it finds; they wait, so the links stay as seeded.
		Queue::manual();
	}
);

add_filter(
	'lfh_checklist',
	static function () {
		$scenario = Scenarios::get( 'where-links-appear' );
		$registry = $scenario ? (array) $scenario->registry() : array();
		$posts    = (array) ( $registry['posts'] ?? array() );

		$replaced = static fn( $id, $say ) => array(
			'type'     => 'exists',
			'selector' => 'a[data-lfh-id="' . $id . '"][href*="archive.org/web/"]',
			'say'      => $say,
		);
		$on_post  = static fn( $role ) => array(
			'type'     => 'exists',
			'selector' => 'body.postid-' . (int) ( $posts[ $role ] ?? 0 ),
		);

		return array(
			'title' => 'Where links appear',
			'intro' => 'Broken, archived links in different places: W1 in an older post, W5 in the newest post (which is on the Link Fixer excluded posts list, so it is first on the home page), and in "Harness: links in blocks" W2 in a Button block, W3 an image link and W4 inside a synced pattern.',
			'items' => array(
				array(
					'id'     => 'blocks',
					'do'     => 'Open the post "Harness: links in blocks".',
					'expect' => 'W2 (Button block), W3 (image link) and W4 (synced pattern) are all replaced with their archives.',
					'link'   => 'front:/?p=' . (int) ( $posts['blocks'] ?? 0 ),
					'when'   => array( $on_post( 'blocks' ) ),
					'checks' => array(
						$replaced( 'W2', 'W2 in the Button block is replaced' ),
						$replaced( 'W3', 'W3, the image link, is replaced' ),
						$replaced( 'W4', 'W4 in the synced pattern is replaced' ),
					),
				),
				array(
					'id'     => 'older',
					'do'     => 'Open the post "Harness: older post".',
					'expect' => 'W1 is replaced with its archive.',
					'link'   => 'front:/?p=' . (int) ( $posts['older'] ?? 0 ),
					'when'   => array( $on_post( 'older' ) ),
					'checks' => array( $replaced( 'W1', 'W1 is replaced' ) ),
				),
				array(
					'id'     => 'home',
					'do'     => 'Open the site\'s home page (the blog, newest first).',
					'expect' => 'W1 in the older post is replaced there too. W5, in the excluded newest post, is left alone.',
					'link'   => 'front:/',
					'when'   => array(
						array(
							'type'     => 'exists',
							'selector' => 'body.home',
						),
					),
					'checks' => array(
						$replaced( 'W1', 'W1 in the older post is replaced' ),
						array(
							'type'     => 'missing',
							'selector' => 'a[data-lfh-id="W5"][href*="archive.org/web/"]',
							'say'      => 'W5 in the excluded post is left alone',
						),
					),
				),
			),
		);
	}
);
