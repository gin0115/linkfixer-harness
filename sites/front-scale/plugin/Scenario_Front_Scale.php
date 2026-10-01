<?php
/**
 * Scenario: one post with 40 links all due a check, all on screen as the page opens.
 *
 * @package LinkFixer_Harness
 */

namespace LinkFixer_Harness;

defined( 'ABSPATH' ) || exit;

/**
 * Front end link checks at scale.
 */
class Scenario_Front_Scale extends Scenario {

	/**
	 * How many links.
	 */
	public const COUNT = 40;

	/**
	 * Slug.
	 *
	 * @return string
	 */
	public function slug(): string {
		return 'front-scale';
	}

	/**
	 * Plain English name.
	 *
	 * @return string
	 */
	public function title(): string {
		return 'Front end link checks at scale';
	}

	/**
	 * What it proves.
	 *
	 * @return string
	 */
	public function summary(): string {
		return 'A visitor opens a post whose 40 links are all due a check: how many checks the page sends, how many at once, and how long they take.';
	}

	/**
	 * One post.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function posts(): array {
		return array(
			'many' => array(
				'title' => 'Harness: 40 links due a check',
				'intro' => 'Forty working links, F01 to F40, each last checked 4 days ago and archived. Links are checked every 3 days, so all are due.',
			),
		);
	}

	/**
	 * F01 to F40: working, archived, last checked 4 days ago, and Archive.org takes half a second to answer about each.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function links(): array {
		$links = array();
		for ( $n = 1; $n <= self::COUNT; $n++ ) {
			$links[] = self::link(
				array(
					'id'      => sprintf( 'F%02d', $n ),
					'label'   => 'Working, due a check',
					'url'     => self::url( sprintf( 'f%02d', $n ), 'check/200/wait/0.5' ),
					'history' => array( array( 200, 4 ) ),
					'posts'   => array( 'many' ),
				)
			);
		}
		return $links;
	}

	/**
	 * All the links in one paragraph at the top, so all are on screen as the page opens.
	 *
	 * @param string $role  The post's role.
	 * @param string $intro The intro.
	 *
	 * @return string
	 */
	protected function content( string $role, string $intro ): string {
		$links = array_map(
			static fn( $def ) => sprintf( '<a href="%1$s" data-lfh-id="%2$s">%2$s</a>', esc_url( $def['url'] ), esc_attr( $def['id'] ) ),
			$this->links()
		);

		return '<p><strong>' . esc_html( $intro ) . '</strong></p><p>' . implode( ' ', $links ) . '</p><p>' . self::lorem( 0 ) . '</p>';
	}
}
