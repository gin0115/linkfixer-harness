<?php
/**
 * Base for a seeded scenario: its posts, its link rows and what should happen to each link.
 *
 * @package LinkFixer_Harness
 */

namespace LinkFixer_Harness;

use Internet_Archive\Wayback_Machine_Link_Fixer\Link\Link;
use Internet_Archive\Wayback_Machine_Link_Fixer\Link\Link_Repository;
use Internet_Archive\Wayback_Machine_Link_Fixer\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * A scenario only describes its posts and links; seeding, reset, ageing and the panel data live here.
 */
abstract class Scenario {

	/**
	 * Unique slug, used in URLs (?lfh_go=slug) and REST calls.
	 *
	 * @return string
	 */
	abstract public function slug(): string;

	/**
	 * Plain English name.
	 *
	 * @return string
	 */
	abstract public function title(): string;

	/**
	 * Plain English summary of what the scenario proves.
	 *
	 * @return string
	 */
	abstract public function summary(): string;

	/**
	 * The posts, by role.
	 *
	 * Each has title and intro, and optionally:
	 * excluded  - true to put the post in the Link Fixer excluded posts list.
	 * overrides - option => value, forced for views of that post only (pre_option_{option}).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	abstract public function posts(): array;

	/**
	 * The links, each built with link().
	 *
	 * @return array<int, array<string, mixed>>
	 */
	abstract public function links(): array;

	/**
	 * Builds a link definition.
	 *
	 * id         - short id shown on the page.
	 * label      - plain English label.
	 * url        - the link URL.
	 * history    - seeded checks, [http code, days ago].
	 * broken     - seeded broken flag.
	 * exclusion  - none, global, builtin, manual, system or post_filter.
	 * posts      - post roles the link appears in.
	 * expect     - what should happen, or role => text (with a "default" key).
	 * archive    - whether the link has an archive URL.
	 * below_fold - put the link in the last paragraph.
	 * static     - a hand written link: no database row, not in the post's link data.
	 * pattern    - for exclusion "global", the rule added to the settings exclusion list.
	 * filter_in  - for exclusion "post_filter", the post roles the filter excludes it in.
	 *
	 * @param array<string, mixed> $args The values.
	 *
	 * @return array<string, mixed>
	 */
	protected static function link( array $args ): array {
		return array_merge(
			array(
				'id'         => '',
				'label'      => '',
				'url'        => '',
				'history'    => array(),
				'broken'     => false,
				'exclusion'  => 'none',
				'posts'      => array(),
				'expect'     => '',
				'archive'    => true,
				'below_fold' => false,
				'static'     => false,
				'pattern'    => '',
				'filter_in'  => array(),
			),
			$args
		);
	}

	/**
	 * A harness URL.
	 *
	 * @param string $slug   Unique slug.
	 * @param string $script Script path, for example "check/404".
	 *
	 * @return string
	 */
	protected static function url( string $slug, string $script ): string {
		return 'https://' . Script::HOST . '/' . $slug . '/' . $script;
	}

	/**
	 * Three failed checks, 12, 8 and 4 days ago, so a check is due on load.
	 *
	 * @return array<int, array{0:int, 1:float}>
	 */
	protected static function failed_history(): array {
		return array( array( 404, 12 ), array( 404, 8 ), array( 404, 4 ) );
	}

	/**
	 * The option holding what was seeded.
	 *
	 * @return string
	 */
	private function registry_key(): string {
		return 'lfh_scenario_' . str_replace( '-', '_', $this->slug() );
	}

	/**
	 * The option counting page loads.
	 *
	 * @return string
	 */
	public function load_count_key(): string {
		return 'lfh_load_count_' . str_replace( '-', '_', $this->slug() );
	}

	/**
	 * What was seeded, or null.
	 *
	 * @return array<string, mixed>|null
	 */
	public function registry(): ?array {
		$registry = get_option( $this->registry_key() );
		return is_array( $registry ) ? $registry : null;
	}

	/**
	 * The role of a seeded post, or null if the post is not part of this scenario.
	 *
	 * @param integer $post_id The post.
	 *
	 * @return string|null
	 */
	public function role_for_post( int $post_id ): ?string {
		$registry = $this->registry();
		if ( null === $registry ) {
			return null;
		}
		$role = array_search( $post_id, $registry['posts'], true );
		return false === $role ? null : (string) $role;
	}

	/**
	 * The options a post forces for its own page views.
	 *
	 * @param string $role The post role.
	 *
	 * @return array<string, mixed>
	 */
	public function overrides_for_role( string $role ): array {
		$posts = $this->posts();
		return (array) ( $posts[ $role ]['overrides'] ?? array() );
	}

	/**
	 * Why a link is excluded in a given post, or "none".
	 *
	 * @param array<string, mixed> $def  The link definition.
	 * @param string               $role The post role.
	 *
	 * @return string
	 */
	public function exclusion_for( array $def, string $role ): string {
		$posts = $this->posts();
		if ( ! empty( $posts[ $role ]['excluded'] ) ) {
			return 'post';
		}
		if ( 'post_filter' === $def['exclusion'] ) {
			return in_array( $role, $def['filter_in'], true ) ? 'post_filter' : 'none';
		}
		return $def['exclusion'];
	}

	/**
	 * What should happen to a link in a given post, for humans.
	 *
	 * @param array<string, mixed> $def  The link definition.
	 * @param string               $role The post role.
	 *
	 * @return string
	 */
	public function expect_for( array $def, string $role ): string {
		if ( 'post' === $this->exclusion_for( $def, $role ) ) {
			return 'This whole post is excluded: no link data, no checker script, nothing happens to this link.';
		}
		if ( is_array( $def['expect'] ) ) {
			return (string) ( $def['expect'][ $role ] ?? ( $def['expect']['default'] ?? '' ) );
		}
		return (string) $def['expect'];
	}

	/**
	 * Post id => URLs the per-post filter excludes.
	 *
	 * @return array<int, string[]>
	 */
	public function post_filters(): array {
		$registry = $this->registry();
		return null === $registry ? array() : (array) ( $registry['post_filters'] ?? array() );
	}

	/**
	 * URL of the first post.
	 *
	 * @return string
	 */
	public function first_url(): string {
		$registry = $this->registry();
		return null === $registry ? '' : (string) get_permalink( (int) reset( $registry['posts'] ) );
	}

	/**
	 * Seeds the scenario from scratch.
	 *
	 * @return array<string, mixed> The registry.
	 */
	public function seed(): array {
		$this->reset();

		// Onboarding done, so nothing redirects to the setup wizard.
		update_option( Settings::POST_ACTIVATION_ONBOARDING_KEY, Settings::ONBOARDING_COMPLETED_OPTION );
		update_option( Settings::SETUP_WIZARD_COMPLETED_KEY, true );
		update_option( Settings::SETUP_WIZARD_STEP_KEY, 'complete' );
		if ( ! get_option( Settings::ONBOARDING_DATE_KEY ) ) {
			update_option( Settings::ONBOARDING_DATE_KEY, current_time( 'mysql' ) );
		}

		// Off while the posts are inserted, so save_post does not scan them.
		update_option( Settings::PROCESS_LINKS, false );
		update_option( Settings::ALLOWED_POST_TYPES, array( 'post', 'page' ) );
		update_option( Settings::FIXER_OPTION, Settings::FIXER_OPTION_REPLACE_LINK );
		update_option( Settings::LINK_ICON, Settings::LINK_ICON_NONE );
		update_option( Settings::LINK_CHECK_DURATION_IN_DAYS, 3 );
		update_option( Settings::MINIMUM_CHECKS_BEFORE_BROKEN, 3 );

		update_option( 'lfh_archive_online', 'yes' );
		delete_transient( 'iawmlf_archive_api_online' );

		// Posts.
		$posts = array();
		foreach ( $this->posts() as $role => $post ) {
			$posts[ $role ] = (int) wp_insert_post(
				array(
					'post_title'   => $post['title'],
					'post_content' => $this->content( $role, $post['intro'] ),
					'post_status'  => 'publish',
					'post_type'    => 'post',
					'meta_input'   => array(
						'_lfh_scenario' => $this->slug(),
						'_lfh_role'     => $role,
					),
				)
			);
		}

		// Links.
		$repository = new Link_Repository();
		$link_ids   = array();
		$patterns   = array();
		foreach ( $this->links() as $def ) {
			if ( $def['static'] ) {
				continue;
			}

			$link = new Link( $def['url'] );

			if ( $def['archive'] ) {
				$link->set_archived_href( Fake_Snapshot_Client::archive_url( $def['url'] ) );
			}

			foreach ( $def['history'] as $check ) {
				$link->add_check( (int) $check[0], gmdate( 'Y-m-d H:i:s', time() - (int) round( $check[1] * DAY_IN_SECONDS ) ) );
			}

			if ( $def['broken'] ) {
				$link->set_broken();
			} else {
				$link->set_valid();
			}

			if ( 'manual' === $def['exclusion'] ) {
				$link->set_excluded( true )->set_message( sprintf( Link::MANUAL_EXCLUSION_TEMPLATE, 'harness', gmdate( 'Y-m-d' ) ) );
			} elseif ( 'system' === $def['exclusion'] ) {
				$link->set_excluded( true )->set_message( 'Harness: Archive.org returned error:no-access' );
			} elseif ( 'global' === $def['exclusion'] && '' !== $def['pattern'] ) {
				$patterns[] = $def['pattern'];
			}

			$link->set_done();
			$link                   = $repository->upsert( $link );
			$link_ids[ $def['id'] ] = (int) $link->get_id();
		}

		if ( ! empty( $patterns ) ) {
			update_option( Settings::LINK_EXCLUSIONS, array_values( array_unique( array_merge( (array) get_option( Settings::LINK_EXCLUSIONS, array() ), $patterns ) ) ) );
		}

		// Link meta for each post. Excluded links are included on purpose, so the render time exclusion is what is tested.
		$post_filters = array();
		foreach ( $posts as $role => $post_id ) {
			$ids = array();
			foreach ( $this->links() as $def ) {
				if ( ! in_array( $role, $def['posts'], true ) ) {
					continue;
				}
				if ( isset( $link_ids[ $def['id'] ] ) ) {
					$ids[] = $link_ids[ $def['id'] ];
				}
				if ( 'post_filter' === $def['exclusion'] && in_array( $role, $def['filter_in'], true ) ) {
					$post_filters[ $post_id ][] = $def['url'];
				}
			}
			update_post_meta( $post_id, Settings::LINK_META_KEY, $ids );
		}

		// Excluded posts.
		$excluded = array_map( 'absint', (array) get_option( Settings::LINK_FIXER_EXCLUDED_POSTS, array() ) );
		foreach ( $this->posts() as $role => $post ) {
			if ( ! empty( $post['excluded'] ) ) {
				$excluded[] = $posts[ $role ];
			}
		}
		update_option( Settings::LINK_FIXER_EXCLUDED_POSTS, array_values( array_unique( array_filter( $excluded ) ) ) );

		update_option( Settings::PROCESS_LINKS, true );

		$registry = array(
			'posts'        => $posts,
			'links'        => $link_ids,
			'post_filters' => $post_filters,
			'seeded'       => time(),
		);
		update_option( $this->registry_key(), $registry, false );

		return $registry;
	}

	/**
	 * Removes everything this scenario created.
	 *
	 * @return void
	 */
	public function reset(): void {
		// Posts.
		$post_ids = get_posts(
			array(
				'post_type'   => 'any',
				'post_status' => 'any',
				'numberposts' => -1,
				'fields'      => 'ids',
				'meta_key'    => '_lfh_scenario', // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'  => $this->slug(), // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		);
		foreach ( $post_ids as $post_id ) {
			wp_delete_post( (int) $post_id, true );
		}

		// Excluded posts list.
		$excluded = array_map( 'absint', (array) get_option( Settings::LINK_FIXER_EXCLUDED_POSTS, array() ) );
		update_option( Settings::LINK_FIXER_EXCLUDED_POSTS, array_values( array_diff( $excluded, array_map( 'absint', $post_ids ) ) ) );

		// Links, and their call history so the scripts start again.
		$repository = new Link_Repository();
		$urls       = array();
		foreach ( $this->links() as $def ) {
			$urls[] = $def['url'];
			for ( $i = 0; $i < 5; $i++ ) {
				$link = $repository->find_by_url( $def['url'] );
				if ( null === $link || ! $repository->delete_link( $link ) ) {
					break;
				}
			}
		}
		Call_Log::clear_urls( $urls );

		delete_option( $this->registry_key() );
		delete_option( $this->load_count_key() );
	}

	/**
	 * Moves every seeded check back in time.
	 *
	 * @param float $days Days to move back.
	 *
	 * @return integer Number of links changed.
	 */
	public function age( float $days ): int {
		global $wpdb;

		$registry = $this->registry();
		if ( null === $registry ) {
			return 0;
		}

		$table   = Settings::get_link_table_name();
		$seconds = (int) round( $days * DAY_IN_SECONDS );
		$changed = 0;

		foreach ( $registry['links'] as $link_id ) {
			$checks = json_decode( (string) $wpdb->get_var( $wpdb->prepare( "SELECT checks FROM $table WHERE id = %d", $link_id ) ), true ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
			if ( ! is_array( $checks ) || empty( $checks ) ) {
				continue;
			}

			foreach ( $checks as &$check ) {
				$time = strtotime( $check['date'] . ' UTC' );
				if ( false !== $time ) {
					$check['date'] = gmdate( 'Y-m-d H:i:s', $time - $seconds );
				}
			}
			unset( $check );

			$wpdb->update( $table, array( 'checks' => wp_json_encode( $checks ) ), array( 'id' => $link_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			++$changed;
		}

		return $changed;
	}

	/**
	 * The data the panel needs for one of this scenario's posts, or null.
	 *
	 * @param integer $post_id The post being viewed.
	 *
	 * @return array<string, mixed>|null
	 */
	public function page_data( int $post_id ): ?array {
		$registry = $this->registry();
		$role     = $this->role_for_post( $post_id );
		if ( null === $registry || null === $role ) {
			return null;
		}

		$repository = new Link_Repository();
		$links      = array();

		foreach ( $this->links() as $def ) {
			if ( ! in_array( $role, $def['posts'], true ) ) {
				continue;
			}

			$link = isset( $registry['links'][ $def['id'] ] ) ? $repository->find_by_id( (int) $registry['links'][ $def['id'] ] ) : null;

			$links[] = array(
				'id'         => $def['id'],
				'label'      => $def['label'],
				'url'        => $def['url'],
				'expect'     => $this->expect_for( $def, $role ),
				'exclusion'  => $this->exclusion_for( $def, $role ),
				'below_fold' => $def['below_fold'],
				'static'     => $def['static'],
				'script'     => Script::parse( $def['url'] ),
				'next_check' => Script::current( 'check', 'check_single', $def['url'] ),
				'db'         => null === $link ? null : array(
					'id'       => (int) $link->get_id(),
					'archived' => (string) $link->get_archived_href(),
					'checks'   => $link->get_checks(),
					'broken'   => $link->is_broken(),
					'excluded' => $link->is_excluded(),
					'message'  => $link->get_message(),
				),
			);
		}

		$posts = array();
		foreach ( $registry['posts'] as $post_role => $id ) {
			$posts[ $post_role ] = array(
				'id'    => (int) $id,
				'title' => get_the_title( $id ),
				'url'   => get_permalink( $id ),
			);
		}

		return array(
			'scenario'       => $this->slug(),
			'scenario_title' => $this->title(),
			'summary'        => $this->summary(),
			'role'           => $role,
			'post_id'        => $post_id,
			'overrides'      => $this->overrides_for_role( $role ),
			'posts'          => $posts,
			'links'          => $links,
		);
	}

	/**
	 * Builds a post's content: 30 paragraphs of lorem ipsum with the links spread through them.
	 *
	 * @param string $role  The post role.
	 * @param string $intro The intro text.
	 *
	 * @return string
	 */
	protected function content( string $role, string $intro ): string {
		$total = 30;
		$defs  = array_values(
			array_filter(
				$this->links(),
				static fn( $def ) => in_array( $role, $def['posts'], true )
			)
		);

		// Uneven gaps, so most links are only checked once scrolled into view.
		$jitter = array( -1, 0, 1, 0, -1, 1, 0 );
		$placed = array_fill( 0, $total, array() );
		$count  = count( $defs );
		foreach ( $defs as $index => $def ) {
			$slot = $def['below_fold']
				? $total - 1
				: (int) round( ( $index + 1 ) * ( $total - 4 ) / ( $count + 1 ) ) + $jitter[ $index % count( $jitter ) ];

			$placed[ max( 0, min( $total - 2, $slot ) ) + ( $def['below_fold'] ? 1 : 0 ) ][] = sprintf(
				'<a href="%1$s" data-lfh-id="%2$s" title="%4$s">%2$s: %3$s</a>',
				esc_url( $def['url'] ),
				esc_attr( $def['id'] ),
				esc_html( $def['label'] ),
				esc_attr( 'Expected: ' . $this->expect_for( $def, $role ) )
			);
		}

		$content = '<p><strong>' . esc_html( $intro ) . '</strong></p>';
		for ( $i = 0; $i < $total; $i++ ) {
			$words = explode( ' ', self::lorem( $i ) );

			// Drop each link into the middle of the paragraph.
			if ( ! empty( $placed[ $i ] ) ) {
				$middle = (int) floor( count( $words ) / 2 );
				array_splice( $words, $middle, 0, array( implode( ' and ', $placed[ $i ] ) ) );
			}

			$content .= '<p>' . implode( ' ', $words ) . '</p>';
		}

		return $content;
	}

	/**
	 * A paragraph of lorem ipsum, the same every time for the same number.
	 *
	 * @param integer $paragraph The paragraph number.
	 *
	 * @return string
	 */
	protected static function lorem( int $paragraph ): string {
		$words = array( 'lorem', 'ipsum', 'dolor', 'sit', 'amet', 'consectetur', 'adipiscing', 'elit', 'sed', 'do', 'eiusmod', 'tempor', 'incididunt', 'ut', 'labore', 'et', 'dolore', 'magna', 'aliqua', 'enim', 'ad', 'minim', 'veniam', 'quis', 'nostrud', 'exercitation', 'ullamco', 'laboris', 'nisi', 'aliquip', 'ex', 'ea', 'commodo', 'consequat', 'duis', 'aute', 'irure', 'in', 'reprehenderit', 'voluptate', 'velit', 'esse', 'cillum', 'fugiat', 'nulla', 'pariatur', 'excepteur', 'sint', 'occaecat', 'cupidatat', 'non', 'proident', 'sunt', 'culpa', 'qui', 'officia', 'deserunt', 'mollit', 'anim', 'id', 'est', 'laborum' );
		$count = count( $words );

		$sentences = array();
		$position  = $paragraph * 7;
		for ( $s = 0; $s < 4 + ( $paragraph % 3 ); $s++ ) {
			$length   = 8 + ( ( $paragraph + $s ) % 7 );
			$sentence = array();
			for ( $w = 0; $w < $length; $w++ ) {
				$sentence[] = $words[ ( $position + $w * 3 + $s ) % $count ];
			}
			$position   += $length;
			$sentences[] = ucfirst( implode( ' ', $sentence ) ) . '.';
		}

		return implode( ' ', $sentences );
	}
}
