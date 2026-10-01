<?php
/**
 * Scenario: the same broken link written twelve ways, as authors write them.
 *
 * @package LinkFixer_Harness
 */

namespace LinkFixer_Harness;

use Internet_Archive\Wayback_Machine_Link_Fixer\Link\Link_Repository;
use Internet_Archive\Wayback_Machine_Link_Fixer\Settings\Settings;
use Internet_Archive\Wayback_Machine_Link_Fixer\WP_Post\WP_Post_Controller;

defined( 'ABSPATH' ) || exit;

/**
 * Link formats.
 */
class Scenario_Link_Formats extends Scenario {

	/**
	 * Slug.
	 *
	 * @return string
	 */
	public function slug(): string {
		return 'link-formats';
	}

	/**
	 * Plain English name.
	 *
	 * @return string
	 */
	public function title(): string {
		return 'Link formats';
	}

	/**
	 * What it proves.
	 *
	 * @return string
	 */
	public function summary(): string {
		return 'Broken links written the ways authors write them (capitals, spaces, accents, ports, entities, fragments) are all found and replaced in the browser.';
	}

	/**
	 * One post.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function posts(): array {
		return array(
			'formats' => array(
				'title' => 'Harness: link formats',
				'intro' => 'Twelve broken links, each archived, each written a different way. Every one should be replaced with its archive.',
			),
		);
	}

	/**
	 * V1 to V12, exactly as they are written in the post.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function links(): array {
		$formats = array(
			'V1'  => array( 'Plain', 'https://harness.test/v1-plain/check/404' ),
			'V2'  => array( 'Capitals in the host', 'https://HARNESS.TEST/v2-upper-host/check/404' ),
			'V3'  => array( 'Trailing slash', 'https://harness.test/v3-slash/check/404/' ),
			'V4'  => array( 'A space in the path', 'https://harness.test/v4 space/check/404' ),
			'V5'  => array( 'An accent in the path', 'https://harness.test/v5-café/check/404' ),
			'V6'  => array( 'The default port', 'https://harness.test:443/v6-port/check/404' ),
			'V7'  => array( 'A query with an ampersand', 'https://harness.test/v7-query/check/404?a=1&b=2' ),
			'V8'  => array( 'A fragment', 'https://harness.test/v8-fragment/check/404#part' ),
			'V9'  => array( 'Capitals in the scheme', 'HTTPS://harness.test/v9-scheme/check/404' ),
			'V10' => array( 'A dot segment', 'https://harness.test/x/../v10-dots/check/404' ),
			'V11' => array( 'Spaces around the address', ' https://harness.test/v11-padded/check/404 ' ),
			'V12' => array( 'An accent in the host', 'https://bücher.example/v12-idn' ),
		);

		$links = array();
		foreach ( $formats as $id => $format ) {
			$links[] = self::link(
				array(
					'id'     => $id,
					'label'  => $format[0],
					'url'    => $format[1],
					'posts'  => array( 'formats' ),
					'static' => true,
				)
			);
		}
		return $links;
	}

	/**
	 * All the links in one paragraph, so all are on screen as the post opens, each written exactly as given (only escaped for the attribute).
	 *
	 * @param string $role  The post's role.
	 * @param string $intro The intro.
	 *
	 * @return string
	 */
	protected function content( string $role, string $intro ): string {
		$links = array_map(
			static fn( $def ) => sprintf( '<a href="%1$s" data-lfh-id="%2$s">%2$s %3$s</a>', esc_attr( $def['url'] ), esc_attr( $def['id'] ), esc_html( $def['label'] ) ),
			$this->links()
		);
		return '<p><strong>' . esc_html( $intro ) . '</strong></p><p>' . implode( ' · ', $links ) . '</p>';
	}

	/**
	 * Seeds without content filtering, lets the Link Fixer's scan store the links, then marks each broken with an archive.
	 *
	 * @return array<string, mixed> The registry.
	 */
	public function seed(): array {
		// An administrator's content is saved as written.
		kses_remove_filters();
		$registry = parent::seed();
		kses_init_filters();

		$post_id = (int) $registry['posts']['formats'];
		( new WP_Post_Controller() )->process_links_in_content( $post_id );

		// Every link the scan stored: broken, archived, checked yesterday so none is due a check.
		$repository = new Link_Repository();
		foreach ( (array) get_post_meta( $post_id, Settings::LINK_META_KEY, true ) as $link_id ) {
			$link = $repository->find_by_id( (int) $link_id );
			if ( null === $link ) {
				continue;
			}
			$link->set_archived_href( Fake_Snapshot_Client::archive_url( $link->get_href() ) );
			foreach ( array( 3, 2, 1 ) as $days ) {
				$link->add_check( 404, gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS ) );
			}
			$link->set_broken();
			$link->set_done();
			$repository->upsert( $link );
		}

		return $registry;
	}
}
