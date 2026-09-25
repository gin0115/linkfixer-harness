<?php
/**
 * Suite: broken links on the page, over three visits days apart.
 *
 * @package LinkFixer_Harness
 */

namespace LinkFixer_Harness;

defined( 'ABSPATH' ) || exit;

/**
 * Uses the mixed-links scenario.
 */
class Suite_Links_On_Page extends Suite {

	/**
	 * Slug.
	 *
	 * @return string
	 */
	public function slug(): string {
		return 'links-on-page';
	}

	/**
	 * Plain English name.
	 *
	 * @return string
	 */
	public function title(): string {
		return 'Broken links on the page';
	}

	/**
	 * What it proves.
	 *
	 * @return string
	 */
	public function summary(): string {
		return 'A post with links in every state is visited three times, four days apart. Broken links are swapped to the archived copy, links that work again are restored, a link is only treated as broken after three failures, and excluded links are never touched.';
	}

	/**
	 * The steps.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function steps(): array {
		return array(
			array(
				'title'  => 'First visit: every link does what it should as the visitor scrolls down the post',
				'setup'  => array( array( 'seed', 'mixed-links' ) ),
				'open'   => 'post:mixed-links:main',
				'checks' => array(
					array( 'type' => 'links' ),
					array(
						'type'   => 'script',
						'loaded' => true,
					),
					array(
						'type'     => 'link_row',
						'scenario' => 'mixed-links',
						'id'       => 'L01',
						'field'    => 'broken',
						'is'       => true,
						'say'      => 'L01, broken before, is still broken after its check',
					),
					array(
						'type'     => 'link_row',
						'scenario' => 'mixed-links',
						'id'       => 'L02',
						'field'    => 'broken',
						'is'       => false,
						'say'      => 'L02, broken before, works again and is no longer broken',
					),
					array(
						'type'     => 'link_row',
						'scenario' => 'mixed-links',
						'id'       => 'L08',
						'field'    => 'checks',
						'is'       => 2,
						'say'      => 'L08: when the link checker service is down nothing is recorded (still 2 checks)',
					),
				),
			),
			array(
				'title'  => 'Four days later: a link failing for the second time is still left alone, and a link that works again is restored',
				'setup'  => array( array( 'age', 'mixed-links', 4 ) ),
				'open'   => 'post:mixed-links:main',
				'checks' => array(
					array( 'type' => 'links' ),
					array(
						'type'    => 'link',
						'id'      => 'L04',
						'swapped' => false,
						'say'     => 'L04 has failed twice and is still not swapped',
					),
					array(
						'type'     => 'link_row',
						'scenario' => 'mixed-links',
						'id'       => 'L04',
						'field'    => 'checks',
						'is'       => 2,
					),
					array(
						'type'    => 'link',
						'id'      => 'L06',
						'swapped' => false,
						'say'     => 'L06 was broken, is checked again, works, and is restored',
					),
				),
			),
			array(
				'title'  => 'Eight days later: on its third failure the link is swapped to the archived copy',
				'setup'  => array( array( 'age', 'mixed-links', 4 ) ),
				'open'   => 'post:mixed-links:main',
				'checks' => array(
					array( 'type' => 'links' ),
					array(
						'type'    => 'link',
						'id'      => 'L04',
						'swapped' => true,
						'say'     => 'L04 has now failed three times in a row and is swapped',
					),
					array(
						'type'     => 'link_row',
						'scenario' => 'mixed-links',
						'id'       => 'L04',
						'field'    => 'broken',
						'is'       => true,
					),
					array(
						'type'    => 'link',
						'id'      => 'L07',
						'swapped' => false,
						'say'     => 'L07 keeps flipping between working and failing, so it is never swapped',
					),
				),
			),
			array(
				'title'  => 'A link excluded in one post by a filter is still fixed in another post',
				'open'   => 'post:mixed-links:other',
				'checks' => array(
					array( 'type' => 'links' ),
					array(
						'type'    => 'link',
						'id'      => 'X05',
						'swapped' => true,
						'say'     => 'X05 is swapped here, where it is not excluded',
					),
				),
			),
			array(
				'title'  => 'Nothing happens on a post that is on the excluded posts list',
				'open'   => 'post:mixed-links:excluded',
				'checks' => array(
					array( 'type' => 'links' ),
					array(
						'type'   => 'script',
						'loaded' => false,
						'say'    => 'The Link Fixer script is not loaded on this post',
					),
				),
			),
		);
	}
}
