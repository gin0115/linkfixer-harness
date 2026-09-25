<?php
/**
 * Suite: display modes and link icons, one page per setting.
 *
 * @package LinkFixer_Harness
 */

namespace LinkFixer_Harness;

defined( 'ABSPATH' ) || exit;

/**
 * Uses the display-modes scenario.
 */
class Suite_Display_Modes extends Suite {

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
		return 'The same links on five pages, one per setting. "Replace" swaps broken links, the icon shows before or after archived links, "check only" checks but never swaps, and "do nothing" loads nothing at all.';
	}

	/**
	 * The steps.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function steps(): array {
		$icon_css = '#iawmlf-link-icons-inline-css';

		return array(
			array(
				'title'  => 'Replace mode with no icon: broken links are swapped, no icon anywhere',
				'setup'  => array( array( 'seed', 'display-modes' ) ),
				'open'   => 'post:display-modes:replace',
				'checks' => array(
					array( 'type' => 'links' ),
					array(
						'type'   => 'script',
						'loaded' => true,
					),
					array(
						'type'     => 'missing',
						'selector' => $icon_css,
						'say'      => 'No link icon stylesheet on the page',
					),
				),
			),
			array(
				'title'  => 'Replace mode, icon before: archived links show the Internet Archive icon before the text',
				'open'   => 'post:display-modes:icon_before',
				'checks' => array(
					array( 'type' => 'links' ),
					array(
						'type'     => 'exists',
						'selector' => $icon_css,
						'say'      => 'The link icon stylesheet is on the page',
					),
				),
			),
			array(
				'title'  => 'Replace mode, icon after: archived links show the Internet Archive icon after the text',
				'open'   => 'post:display-modes:icon_after',
				'checks' => array(
					array( 'type' => 'links' ),
					array(
						'type'     => 'exists',
						'selector' => $icon_css,
						'say'      => 'The link icon stylesheet is on the page',
					),
				),
			),
			array(
				'title'  => 'Check only: links are checked but never swapped, so only the hand-written archive link gets the icon',
				'open'   => 'post:display-modes:check_only',
				'checks' => array(
					array( 'type' => 'links' ),
					array(
						'type'   => 'script',
						'loaded' => true,
					),
				),
			),
			array(
				'title'  => 'Do nothing: no script, no link data and no icon, even though an icon is set',
				'open'   => 'post:display-modes:do_nothing',
				'checks' => array(
					array( 'type' => 'links' ),
					array(
						'type'   => 'script',
						'loaded' => false,
						'say'    => 'The Link Fixer script is not loaded',
					),
					array(
						'type'     => 'missing',
						'selector' => '.__iawmlf-post-loop-links',
						'say'      => 'No link data on the page',
					),
					array(
						'type'     => 'missing',
						'selector' => $icon_css,
						'say'      => 'No link icon stylesheet on the page',
					),
				),
			),
		);
	}
}
