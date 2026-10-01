<?php
/**
 * Scenario: broken links on the blog home page, in blocks and in a synced pattern.
 *
 * @package LinkFixer_Harness
 */

namespace LinkFixer_Harness;

use Internet_Archive\Wayback_Machine_Link_Fixer\Link\Link_Repository;
use Internet_Archive\Wayback_Machine_Link_Fixer\Settings\Settings;
use Internet_Archive\Wayback_Machine_Link_Fixer\WP_Post\WP_Post_Controller;

defined( 'ABSPATH' ) || exit;

/**
 * Where links appear.
 */
class Scenario_Where_Links_Appear extends Scenario {

	/**
	 * The synced pattern's id while seeding.
	 *
	 * @var integer
	 */
	private $pattern_id = 0;

	/**
	 * Slug.
	 *
	 * @return string
	 */
	public function slug(): string {
		return 'where-links-appear';
	}

	/**
	 * Plain English name.
	 *
	 * @return string
	 */
	public function title(): string {
		return 'Where links appear';
	}

	/**
	 * What it proves.
	 *
	 * @return string
	 */
	public function summary(): string {
		return 'Broken links are fixed on the blog home page as well as on their own post, and inside buttons, image links and synced patterns.';
	}

	/**
	 * Three posts. The excluded one is newest, so it is first on the home page.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function posts(): array {
		return array(
			'excluded' => array(
				'title'    => 'Harness: newest post (excluded)',
				'intro'    => 'The newest post, on the Link Fixer excluded posts list. Its link W5 is left alone.',
				'excluded' => true,
			),
			'older'    => array(
				'title' => 'Harness: older post',
				'intro' => 'An older post with the broken link W1.',
			),
			'blocks'   => array(
				'title' => 'Harness: links in blocks',
				'intro' => 'W2 is a Button block, W3 an image link, W4 is inside a synced pattern.',
			),
		);
	}

	/**
	 * W1 and W5 are seeded broken and archived; W2 to W4 are left for the Link Fixer's scan to find.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function links(): array {
		$broken = array(
			'history' => array( array( 404, 3 ), array( 404, 2 ), array( 404, 1 ) ),
			'broken'  => true,
		);

		return array(
			self::link( array_merge( $broken, array( 'id' => 'W1', 'label' => 'In an older post', 'url' => self::url( 'w1-older', 'check/404' ), 'posts' => array( 'older' ) ) ) ),
			self::link( array_merge( $broken, array( 'id' => 'W5', 'label' => 'In the excluded post', 'url' => self::url( 'w5-excluded-post', 'check/404' ), 'posts' => array( 'excluded' ) ) ) ),
			self::link( array( 'id' => 'W2', 'label' => 'Button block', 'url' => self::url( 'w2-button', 'check/404' ), 'posts' => array( 'blocks' ), 'static' => true ) ),
			self::link( array( 'id' => 'W3', 'label' => 'Image link', 'url' => self::url( 'w3-image', 'check/404' ), 'posts' => array( 'blocks' ), 'static' => true ) ),
			self::link( array( 'id' => 'W4', 'label' => 'In a synced pattern', 'url' => self::url( 'w4-pattern', 'check/404' ), 'posts' => array(), 'static' => true ) ),
		);
	}

	/**
	 * Short posts, so all three fit on the home page's first screen. The blocks post is block markup.
	 *
	 * @param string $role  The post's role.
	 * @param string $intro The intro.
	 *
	 * @return string
	 */
	protected function content( string $role, string $intro ): string {
		if ( 'blocks' !== $role ) {
			$links = array_map(
				static fn( $def ) => sprintf( '<a href="%1$s" data-lfh-id="%2$s">%2$s: %3$s</a>', esc_url( $def['url'] ), esc_attr( $def['id'] ), esc_html( $def['label'] ) ),
				array_filter( $this->links(), static fn( $def ) => in_array( $role, $def['posts'], true ) )
			);
			return '<p><strong>' . esc_html( $intro ) . '</strong> ' . implode( ' ', $links ) . '</p>';
		}

		return '<!-- wp:paragraph --><p><strong>' . esc_html( $intro ) . '</strong></p><!-- /wp:paragraph -->'
			. '<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="' . esc_url( self::url( 'w2-button', 'check/404' ) ) . '" data-lfh-id="W2">W2 Button block</a></div><!-- /wp:button --></div><!-- /wp:buttons -->'
			. '<!-- wp:image {"linkDestination":"custom"} --><figure class="wp-block-image"><a href="' . esc_url( self::url( 'w3-image', 'check/404' ) ) . '" data-lfh-id="W3"><img src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7" alt="W3 image link" width="40" height="40"/></a></figure><!-- /wp:image -->'
			. '<!-- wp:block {"ref":' . (int) $this->pattern_id . '} /-->';
	}

	/**
	 * Seeds the synced pattern, the posts and links, dates the posts, then lets the scan find the blocks post's links.
	 *
	 * @return array<string, mixed> The registry.
	 */
	public function seed(): array {
		foreach ( get_posts( array( 'post_type' => 'wp_block', 'title' => 'Harness: synced pattern', 'numberposts' => -1, 'post_status' => 'any', 'fields' => 'ids' ) ) as $old ) {
			wp_delete_post( (int) $old, true );
		}

		// An administrator's content is saved as written.
		kses_remove_filters();
		$this->pattern_id = (int) wp_insert_post(
			array(
				'post_type'    => 'wp_block',
				'post_status'  => 'publish',
				'post_title'   => 'Harness: synced pattern',
				'post_content' => '<!-- wp:paragraph --><p><a href="' . esc_url( self::url( 'w4-pattern', 'check/404' ) ) . '" data-lfh-id="W4">W4 in a synced pattern</a></p><!-- /wp:paragraph -->',
			)
		);
		$registry         = parent::seed();
		kses_init_filters();

		// Newest first on the home page: the excluded post, then the older post, then the blocks post.
		global $wpdb;
		$offsets = array(
			'excluded' => 0,
			'older'    => HOUR_IN_SECONDS,
			'blocks'   => 2 * HOUR_IN_SECONDS,
		);
		foreach ( $offsets as $role => $offset ) {
			$time = time() + 60 - $offset;
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->posts,
				array(
					'post_date'     => get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $time ) ),
					'post_date_gmt' => gmdate( 'Y-m-d H:i:s', $time ),
				),
				array( 'ID' => (int) $registry['posts'][ $role ] )
			);
			clean_post_cache( (int) $registry['posts'][ $role ] );
		}

		// The blocks post's links: whatever the scan finds, marked broken and archived like W1.
		$post_id = (int) $registry['posts']['blocks'];
		( new WP_Post_Controller() )->process_links_in_content( $post_id );
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

		$registry['pattern'] = $this->pattern_id;
		return $registry;
	}
}
