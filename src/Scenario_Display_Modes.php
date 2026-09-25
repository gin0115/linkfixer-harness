<?php
/**
 * Scenario: the same links on five pages, each page forcing its own fixer mode and link icon.
 *
 * @package LinkFixer_Harness
 */

namespace LinkFixer_Harness;

use Internet_Archive\Wayback_Machine_Link_Fixer\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Display modes and link icons.
 */
class Scenario_Display_Modes extends Scenario {

	/**
	 * Slug.
	 *
	 * @return string
	 */
	public function slug(): string {
		return 'display-modes';
	}

	/**
	 * Plain English name.
	 *
	 * @return string
	 */
	public function title(): string {
		return 'Display modes and link icons';
	}

	/**
	 * What it proves.
	 *
	 * @return string
	 */
	public function summary(): string {
		return 'The same links on five pages, one per setting: replace links, replace with the icon before or after, check only, and do nothing. Each page sets its own mode for that page view only.';
	}

	/**
	 * The five pages: prefix, title, fixer mode, link icon.
	 *
	 * @return array<string, array{0:string, 1:string, 2:string, 3:string}>
	 */
	private static function pages(): array {
		return array(
			'replace'     => array( 'R', 'Harness: mode replace, no icon', Settings::FIXER_OPTION_REPLACE_LINK, Settings::LINK_ICON_NONE ),
			'icon_before' => array( 'B', 'Harness: mode replace, icon before', Settings::FIXER_OPTION_REPLACE_LINK, 'ia_logo_before' ),
			'icon_after'  => array( 'A', 'Harness: mode replace, icon after', Settings::FIXER_OPTION_REPLACE_LINK, 'ia_logo_after' ),
			'check_only'  => array( 'C', 'Harness: mode check only', Settings::FIXER_OPTION_CHECK_ONLY, 'ia_logo_before' ),
			'do_nothing'  => array( 'N', 'Harness: mode do nothing', Settings::FIXER_OPTION_DO_NOTHING, 'ia_logo_before' ),
		);
	}

	/**
	 * The posts, by role, each forcing its own mode and icon.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function posts(): array {
		$posts = array();
		foreach ( self::pages() as $role => $page ) {
			$posts[ $role ] = array(
				'title'     => $page[1],
				'intro'     => sprintf( 'This page forces the fixer mode "%1$s" and the link icon "%2$s" for its own page views only. The saved settings are not changed. Scroll through: each link is checked once it comes into view.', $page[2], $page[3] ),
				'overrides' => array(
					Settings::FIXER_OPTION => $page[2],
					Settings::LINK_ICON    => $page[3],
				),
			);
		}
		return $posts;
	}

	/**
	 * Five links per page, each page with its own URLs so checks on one page never change another.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function links(): array {
		$links = array();

		foreach ( self::pages() as $role => $page ) {
			list( $prefix, , $mode, $icon ) = $page;

			$slug      = 'dm-' . str_replace( '_', '-', $role );
			$side      = 'ia_logo_before' === $icon ? 'before' : ( 'ia_logo_after' === $icon ? 'after' : '' );
			$icon_text = '' === $side ? 'No icon, because this page has no link icon.' : 'The Internet Archive icon shows ' . $side . ' the link text.';

			if ( Settings::FIXER_OPTION_DO_NOTHING === $mode ) {
				$swapped = 'Do nothing mode: the Link Fixer script, link data and icon CSS are not loaded, so this link is not checked, not swapped and has no icon.';
				$checked = $swapped;
				$recent  = $swapped;
				$static  = 'The icon CSS is not loaded in do nothing mode, so this hand-written archive link has no icon, even though an icon is set.';
			} elseif ( Settings::FIXER_OPTION_CHECK_ONLY === $mode ) {
				$swapped = 'Checked on load and stays broken, but NOT swapped because this page is in check only mode. No icon, because it still points at the original.';
				$checked = 'Checked on load, returns 200, not swapped.';
				$recent  = 'Checked recently, so no check. Not swapped because this page is in check only mode.';
				$static  = 'The icon CSS still loads in check only mode, and it matches any archive link, so this hand-written archive link gets the icon ' . $side . ' it.';
			} else {
				$swapped = 'Checked on load, 404, stays broken, swapped to the archive. ' . $icon_text;
				$checked = 'Checked on load, returns 200, not swapped, no icon.';
				$recent  = 'Checked recently, so no check. Swapped from its stored broken state. ' . $icon_text;
				$static  = '' === $side
					? 'Typed into the post. The Link Fixer never touches it, and this page has no icon.'
					: 'Typed into the post. The Link Fixer never touches it, but the icon CSS matches any archive link, so it gets the icon ' . $side . ' it too.';
			}

			$links[] = self::link(
				array(
					'id'      => $prefix . '1',
					'label'   => 'Broken, stays broken',
					'url'     => self::url( $slug . '-1-broken', 'check/404' ),
					'history' => self::failed_history(),
					'broken'  => true,
					'posts'   => array( $role ),
					'expect'  => $swapped,
				)
			);
			$links[] = self::link(
				array(
					'id'      => $prefix . '2',
					'label'   => 'Broken, now fixed',
					'url'     => self::url( $slug . '-2-fixed', 'check/200' ),
					'history' => self::failed_history(),
					'broken'  => true,
					'posts'   => array( $role ),
					'expect'  => Settings::FIXER_OPTION_DO_NOTHING === $mode ? $checked : 'Broken when the page loads. Checked on load, returns 200, recovers, not swapped, no icon.',
				)
			);
			$links[] = self::link(
				array(
					'id'      => $prefix . '3',
					'label'   => 'Broken, checked recently',
					'url'     => self::url( $slug . '-3-recent', 'check/200' ),
					'history' => array( array( 404, 10 ), array( 404, 6 ), array( 404, 0.02 ) ),
					'broken'  => true,
					'posts'   => array( $role ),
					'expect'  => $recent,
				)
			);
			$links[] = self::link(
				array(
					'id'      => $prefix . '4',
					'label'   => 'Healthy',
					'url'     => self::url( $slug . '-4-healthy', 'check/200' ),
					'history' => array( array( 200, 4 ) ),
					'posts'   => array( $role ),
					'expect'  => $checked,
				)
			);
			$links[] = self::link(
				array(
					'id'     => $prefix . '5',
					'label'  => 'Hand-written archive link',
					'url'    => 'https://web.archive.org/web/2020/https://example.org/lfh-' . $slug,
					'posts'  => array( $role ),
					'static' => true,
					'expect' => $static,
				)
			);
		}

		return $links;
	}
}
