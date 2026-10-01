<?php
/**
 * Plugin Name: Link Fixer Harness site: Advanced Settings, excluded posts
 * Description: A post with an ampersand in its title, a page and a draft, for the excluded posts searches in Advanced Settings and the Plugins screen link.
 * Version: 0.1.0
 * Requires PHP: 7.4
 * Requires Plugins: linkfixer-harness
 * Author: Glynn Quelch
 * License: GPL-2.0-or-later
 *
 * @package LinkFixer_Harness
 */

namespace LinkFixer_Harness;

use Internet_Archive\Wayback_Machine_Link_Fixer\Settings\Settings;

defined( 'ABSPATH' ) || exit;

add_action(
	'lfh_loaded',
	static function () {
		require_once __DIR__ . '/Scenario_Settings_Exclusions.php';
		add_filter( 'lfh_scenarios', static fn( $scenarios ) => array_merge( $scenarios, array( new Scenario_Settings_Exclusions() ) ) );
	}
);

add_filter(
	'lfh_checklist',
	static function () {
		$scenario = Scenarios::get( 'settings-exclusions' );
		$registry = $scenario ? (array) $scenario->registry() : array();
		$posts    = (array) ( $registry['posts'] ?? array() );

		$fixer    = '#iawmlf_excluded_posts';
		$archiver = '#iawmlf_excluded_archiver_posts';
		$page     = array(
			'type' => 'param',
			'name' => 'page',
			'is'   => 'iawmlf_settings',
		);
		$typed    = static fn( $list, $is ) => array(
			'type'     => 'value',
			'selector' => $list . ' .iawmlf-post-search__input',
			'is'       => $is,
		);
		$answered = static fn( $list ) => array(
			'type'     => 'exists',
			'selector' => $list . ' .iawmlf-post-search__item, ' . $list . ' .iawmlf-post-search__no-results',
		);
		$dropdown = static fn( $list, $key, $text, $say ) => array(
			'type'     => 'text',
			'selector' => $list . ' .iawmlf-post-search__dropdown',
			$key       => $text,
			'say'      => $say,
		);

		return array(
			'title' => 'Advanced Settings: excluded posts',
			'intro' => 'Three posts to find: "Harness: Tips & Tricks" (a post with an ampersand in its title), "Harness: About us" (a page) and "Harness: Draft tips" (a draft). Both excluded posts lists start empty.',
			'items' => array(
				array(
					'id'     => 'search',
					'do'     => 'In Link Fixer, Advanced Settings, type "Tips" in the Link Fixer\'s "Exclude posts" search box.',
					'expect' => '"Harness: Tips & Tricks" is offered, written as it appears on the site.',
					'link'   => 'admin:admin.php?page=iawmlf_settings',
					'when'   => array( $page, $typed( $fixer, 'Tips' ), $answered( $fixer ) ),
					'checks' => array(
						$dropdown( $fixer, 'has', 'Harness: Tips & Tricks', '"Harness: Tips & Tricks" is offered' ),
						$dropdown( $fixer, 'lacks', '&amp;', 'The title does not show "&amp;"' ),
					),
				),
				array(
					'id'     => 'amp',
					'do'     => 'Clear the box and type "amp".',
					'expect' => 'Only "Sample Page" is offered ("amp" is in "Sample"). "Harness: Tips & Tricks" has no "amp" in its title, so it is not offered, and no title shows "&amp;".',
					'when'   => array( $page, $typed( $fixer, 'amp' ), $answered( $fixer ) ),
					'checks' => array(
						$dropdown( $fixer, 'has', 'Sample Page', '"Sample Page" is offered' ),
						$dropdown( $fixer, 'lacks', 'Tips', '"Harness: Tips & Tricks" is not offered' ),
						$dropdown( $fixer, 'lacks', '&amp;', 'No title shows "&amp;"' ),
					),
				),
				array(
					'id'     => 'exclude-save',
					'do'     => 'Search "Tips" again, choose "Harness: Tips & Tricks" and press Save Changes.',
					'expect' => 'After the page reloads it is on the Link Fixer\'s excluded posts list, written as "Harness: Tips & Tricks".',
					'when'   => array(
						$page,
						array(
							'type' => 'option',
							'name' => Settings::LINK_FIXER_EXCLUDED_POSTS,
							'has'  => (int) ( $posts['tips'] ?? 0 ),
						),
					),
					'checks' => array(
						array(
							'type'     => 'text',
							'selector' => $fixer . ' .iawmlf-exclusion-list__item',
							'has'      => 'Harness: Tips & Tricks',
							'say'      => 'The list shows "Harness: Tips & Tricks"',
						),
					),
				),
				array(
					'id'     => 'archiver-page',
					'do'     => 'In the Auto Archiver\'s "Exclude posts" search box, type "About".',
					'expect' => 'The page "Harness: About us" is offered: pages are archived too, so they can be excluded.',
					'when'   => array( $page, $typed( $archiver, 'About' ), $answered( $archiver ) ),
					'checks' => array( $dropdown( $archiver, 'has', 'Harness: About us', '"Harness: About us" is offered' ) ),
				),
				array(
					'id'     => 'draft',
					'do'     => 'In the Link Fixer\'s "Exclude posts" search box, type "Draft".',
					'expect' => '"Harness: Draft tips" is offered, so it can be excluded before it is published (publishing scans its links straight away).',
					'when'   => array( $page, $typed( $fixer, 'Draft' ), $answered( $fixer ) ),
					'checks' => array( $dropdown( $fixer, 'has', 'Harness: Draft tips', '"Harness: Draft tips" is offered' ) ),
				),
				array(
					'id'     => 'plugins-link',
					'do'     => 'Open Plugins.',
					'expect' => 'The Link Fixer\'s row has a "Settings" link to Advanced Settings.',
					'link'   => 'admin:plugins.php',
					'when'   => array(
						array(
							'type'     => 'exists',
							'selector' => 'body.plugins-php',
						),
					),
					'checks' => array(
						array(
							'type'     => 'exists',
							'selector' => 'tr[data-slug="internet-archive-wayback-machine-link-fixer"] .row-actions a[href*="page=iawmlf_settings"]',
							'say'      => 'There is a "Settings" link to Advanced Settings',
						),
					),
				),
			),
		);
	}
);
