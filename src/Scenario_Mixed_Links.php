<?php
/**
 * Scenario: one post holding a link in every state, plus the posts needed for the per-post cases.
 *
 * @package LinkFixer_Harness
 */

namespace LinkFixer_Harness;

use Internet_Archive\Wayback_Machine_Link_Fixer\Link\Link;
use Internet_Archive\Wayback_Machine_Link_Fixer\Link\Link_Repository;
use Internet_Archive\Wayback_Machine_Link_Fixer\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Seeds, resets and describes the mixed links scenario.
 */
class Scenario_Mixed_Links {

	public const SLUG = 'mixed-links';

	private const REGISTRY           = 'lfh_scenario_mixed_links';
	private const POST_FILTER_OPTION = 'lfh_post_link_exclusions';
	private const GLOBAL_PATTERN     = '*harness.test/x01-global-rule*';

	/**
	 * Registers the hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_filter( 'iawmlf_exclude_link_from_post', array( self::class, 'filter_post_exclusion' ), 10, 3 );
		add_action( 'template_redirect', array( self::class, 'maybe_redirect' ) );
	}

	/**
	 * The posts the scenario creates, by role.
	 *
	 * @return array<string, array{title:string, intro:string}>
	 */
	public static function posts(): array {
		return array(
			'main'     => array(
				'title' => 'Harness: every link state',
				'intro' => 'Every link below has a scripted Archive.org response and a seeded check history. The harness panel shows what should happen to each one on this page load, and what actually did.',
			),
			'other'    => array(
				'title' => 'Harness: same link in another post',
				'intro' => 'X05 is excluded in "Harness: every link state" by the iawmlf_exclude_link_from_post filter. Here it is not excluded, so it is checked and swapped.',
			),
			'excluded' => array(
				'title' => 'Harness: excluded post',
				'intro' => 'This whole post is in the Link Fixer excluded posts list. Nothing on this page should be checked or swapped, including L01 which is broken in the other post.',
			),
		);
	}

	/**
	 * The seeded links.
	 *
	 * History entries are [http code, days ago]. With the defaults (checked every 3 days,
	 * broken after 3 failed checks) a last check 4 days ago is due on load.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function links(): array {
		$failed = array( array( 404, 12 ), array( 404, 8 ), array( 404, 4 ) );

		return array(
			self::def( 'L01', 'Broken, stays broken', self::url( 'l01-broken-stays', 'check/404' ), $failed, true, 'none', array( 'main', 'excluded' ), 'Last check 4 days ago, so it is checked on load. The checker returns 404, it stays broken and is swapped to the archive.' ),
			self::def( 'L02', 'Broken, now fixed', self::url( 'l02-broken-now-fixed', 'check/200' ), $failed, true, 'none', array( 'main' ), 'Broken when the page loads. Checked on load, the checker returns 200, so it is no longer broken and is NOT swapped.' ),
			self::def( 'L03', 'Fine, becomes broken (third strike)', self::url( 'l03-third-strike', 'check/404' ), array( array( 200, 12 ), array( 404, 8 ), array( 404, 4 ) ), false, 'none', array( 'main' ), 'Two 404s already. The check on load returns a third 404, so it becomes broken and is swapped on this same load.' ),
			self::def( 'L04', 'Fine, breaks slowly (no history)', self::url( 'l04-slow-decay', 'check/404' ), array(), false, 'none', array( 'main' ), 'Never checked, so it is checked on load. One 404 is not enough. After "Age 4 days + reload" twice more it turns broken and is swapped on the third checked load.' ),
			self::def( 'L05', 'Healthy', self::url( 'l05-healthy', 'check/200' ), array( array( 200, 4 ) ), false, 'none', array( 'main' ), 'Checked on load, returns 200, never swapped.' ),
			self::def( 'L06', 'Broken, checked 30 minutes ago', self::url( 'l06-recently-checked', 'check/200' ), array( array( 404, 10 ), array( 404, 6 ), array( 404, 0.02 ) ), true, 'none', array( 'main' ), 'Checked recently, so NO check on load. It is swapped from its stored broken state. After "Age 4 days + reload" it is checked, returns 200 and recovers.' ),
			self::def( 'L07', 'Flaky', self::url( 'l07-flaky', 'check/404,200,404,200' ), array( array( 404, 12 ), array( 200, 8 ), array( 404, 4 ) ), false, 'none', array( 'main' ), 'Alternates 404 and 200. There is always a 200 in the last 3 checks, so it never becomes broken on any load.' ),
			self::def( 'L08', 'Checker service offline', self::url( 'l08-checker-offline', 'check/offline' ), array( array( 404, 8 ), array( 404, 4 ) ), false, 'none', array( 'main' ), 'The checker service throws Service_Offline_Exception. The REST check returns 500, nothing is recorded, it stays not broken and is never swapped (the 1.4.3 fix).' ),
			self::def( 'L09', 'Blocks bots (403)', self::url( 'l09-bot-blocked', 'check/403' ), array( array( 403, 8 ), array( 403, 4 ) ), false, 'none', array( 'main' ), 'A third 403 on load. 403 is not a valid code, so it becomes broken and is swapped. This is the known false positive for sites that block bots.' ),
			self::def( 'L10', 'Rate limited (429)', self::url( 'l10-rate-limited', 'check/429' ), array( array( 429, 12 ), array( 429, 8 ), array( 429, 4 ) ), false, 'none', array( 'main' ), '429 counts as a valid code, so it is checked but never broken.' ),
			self::def( 'L11', 'Broken, no archive', self::url( 'l11-no-archive', 'check/404/archive/none' ), $failed, true, 'none', array( 'main' ), 'Broken but has no archive URL, so the front end skips it: no check, no swap.', false ),
			self::def( 'X01', 'Excluded: settings rule', self::url( 'x01-global-rule', 'check/404' ), $failed, true, 'global', array( 'main' ), 'Matches the rule ' . self::GLOBAL_PATTERN . ' in Advanced Settings. Not in the page link data, never checked, never swapped.' ),
			self::def( 'X02', 'Excluded: built-in list (LinkedIn)', 'https://www.linkedin.com/in/lfh-harness-x02', $failed, true, 'builtin', array( 'main' ), 'Matches the built-in *.linkedin.com* rule. Not in the page link data, never checked, never swapped.' ),
			self::def( 'X03', 'Excluded: manually', self::url( 'x03-manual', 'check/404' ), $failed, true, 'manual', array( 'main' ), 'Excluded with the "Exclude this link" checkbox. Not in the page link data, never checked, never swapped.' ),
			self::def( 'X04', 'Excluded: by the system (no-access)', self::url( 'x04-system', 'check/404' ), $failed, true, 'system', array( 'main' ), 'Excluded by the plugin after Archive.org returned error:no-access. Not in the page link data, never checked, never swapped.' ),
			self::def( 'X05', 'Excluded in this post only (filter)', self::url( 'x05-post-filter', 'check/404' ), $failed, true, 'post_filter', array( 'main', 'other' ), 'Excluded in "Harness: every link state" by the iawmlf_exclude_link_from_post filter. In "Harness: same link in another post" it is not excluded: checked on load, 404, swapped.' ),
			self::def( 'E01', 'Link in the excluded post', self::url( 'e01-excluded-post', 'check/404' ), $failed, true, 'none', array( 'excluded' ), 'The whole post is excluded: no link data, no checker script, nothing swapped.' ),
			self::def( 'L12', 'Below the fold', self::url( 'l12-below-fold', 'check/404' ), $failed, true, 'none', array( 'main' ), 'Only checked once scrolled into view. Then 404, stays broken, swapped.', true, true ),
		);
	}

	/**
	 * Builds a link definition.
	 *
	 * @param string   $id         Short id shown on the page.
	 * @param string   $label      Human label.
	 * @param string   $url        The link URL.
	 * @param array    $history    Seeded checks, [code, days ago].
	 * @param boolean  $broken     Seeded broken flag.
	 * @param string   $exclusion  none, global, builtin, manual, system or post_filter.
	 * @param string[] $posts      Post roles the link appears in.
	 * @param string   $expect     What should happen, for humans.
	 * @param boolean  $archive    Whether the link has an archive URL.
	 * @param boolean  $below_fold Whether the link sits below a tall spacer.
	 *
	 * @return array<string, mixed>
	 */
	private static function def( string $id, string $label, string $url, array $history, bool $broken, string $exclusion, array $posts, string $expect, bool $archive = true, bool $below_fold = false ): array {
		return array(
			'id'         => $id,
			'label'      => $label,
			'url'        => $url,
			'history'    => $history,
			'broken'     => $broken,
			'exclusion'  => $exclusion,
			'posts'      => $posts,
			'expect'     => $expect,
			'archive'    => $archive,
			'below_fold' => $below_fold,
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
	private static function url( string $slug, string $script ): string {
		return 'https://' . Script::HOST . '/' . $slug . '/' . $script;
	}

	/**
	 * Why a link is excluded in a given post, or "none".
	 *
	 * @param array<string, mixed> $def  The link definition.
	 * @param string               $role The post role.
	 *
	 * @return string
	 */
	public static function exclusion_for( array $def, string $role ): string {
		if ( 'excluded' === $role ) {
			return 'post';
		}
		if ( 'post_filter' === $def['exclusion'] ) {
			return 'main' === $role ? 'post_filter' : 'none';
		}
		return $def['exclusion'];
	}

	/**
	 * Seeds the scenario from scratch.
	 *
	 * @return array<string, mixed> The registry.
	 */
	public static function seed(): array {
		self::reset();

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
		update_option( Settings::LINK_CHECK_DURATION_IN_DAYS, 3 );
		update_option( Settings::MINIMUM_CHECKS_BEFORE_BROKEN, 3 );

		$patterns = array_values( array_unique( array_merge( (array) get_option( Settings::LINK_EXCLUSIONS, array() ), array( self::GLOBAL_PATTERN ) ) ) );
		update_option( Settings::LINK_EXCLUSIONS, $patterns );

		update_option( 'lfh_archive_online', 'yes' );
		delete_transient( 'iawmlf_archive_api_online' );

		// Posts.
		$posts = array();
		foreach ( self::posts() as $role => $post ) {
			$posts[ $role ] = (int) wp_insert_post(
				array(
					'post_title'   => $post['title'],
					'post_content' => self::content( $role, $post['intro'] ),
					'post_status'  => 'publish',
					'post_type'    => 'post',
					'meta_input'   => array(
						'_lfh_scenario' => self::SLUG,
						'_lfh_role'     => $role,
					),
				)
			);
		}

		// Links.
		$repository = new Link_Repository();
		$link_ids   = array();
		foreach ( self::links() as $def ) {
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
			}

			$link->set_done();
			$link                   = $repository->upsert( $link );
			$link_ids[ $def['id'] ] = (int) $link->get_id();
		}

		// Link meta for each post. Excluded links are included on purpose, so the render time exclusion is what is tested.
		foreach ( $posts as $role => $post_id ) {
			$ids = array();
			foreach ( self::links() as $def ) {
				if ( in_array( $role, $def['posts'], true ) ) {
					$ids[] = $link_ids[ $def['id'] ];
				}
			}
			update_post_meta( $post_id, Settings::LINK_META_KEY, $ids );
		}

		// X05 is excluded in the main post only.
		update_option( self::POST_FILTER_OPTION, array( $posts['main'] => array( self::url( 'x05-post-filter', 'check/404' ) ) ) );

		// The excluded post.
		$excluded   = array_map( 'absint', (array) get_option( Settings::LINK_FIXER_EXCLUDED_POSTS, array() ) );
		$excluded[] = $posts['excluded'];
		update_option( Settings::LINK_FIXER_EXCLUDED_POSTS, array_values( array_unique( array_filter( $excluded ) ) ) );

		update_option( Settings::PROCESS_LINKS, true );

		$registry = array(
			'posts'  => $posts,
			'links'  => $link_ids,
			'seeded' => time(),
		);
		update_option( self::REGISTRY, $registry, false );

		return $registry;
	}

	/**
	 * Builds a post's content.
	 *
	 * @param string $role  The post role.
	 * @param string $intro The intro text.
	 *
	 * @return string
	 */
	private static function content( string $role, string $intro ): string {
		$above = '';
		$below = '';

		foreach ( self::links() as $def ) {
			if ( ! in_array( $role, $def['posts'], true ) ) {
				continue;
			}

			$anchor = sprintf(
				'<a href="%1$s" data-lfh-id="%2$s">%2$s: %3$s</a>',
				esc_url( $def['url'] ),
				esc_attr( $def['id'] ),
				esc_html( $def['label'] )
			);

			if ( $def['below_fold'] ) {
				$below .= '<p>' . $anchor . '</p>';
			} else {
				$above .= '<li>' . $anchor . '</li>';
			}
		}

		$content = '<p>' . esc_html( $intro ) . '</p><ul>' . $above . '</ul>';

		if ( '' !== $below ) {
			$content .= '<p>Scroll down for the below the fold link.</p><div style="height:160vh"></div>' . $below;
		}

		return $content;
	}

	/**
	 * Removes everything the scenario created.
	 *
	 * @return void
	 */
	public static function reset(): void {
		// Posts.
		$post_ids = get_posts(
			array(
				'post_type'   => 'any',
				'post_status' => 'any',
				'numberposts' => -1,
				'fields'      => 'ids',
				'meta_key'    => '_lfh_scenario', // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'  => self::SLUG, // phpcs:ignore WordPress.DB.SlowDBQuery
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
		foreach ( self::links() as $def ) {
			$urls[] = $def['url'];
			for ( $i = 0; $i < 5; $i++ ) {
				$link = $repository->find_by_url( $def['url'] );
				if ( null === $link || ! $repository->delete_link( $link ) ) {
					break;
				}
			}
		}
		Call_Log::clear_urls( $urls );

		delete_option( self::POST_FILTER_OPTION );
		delete_option( self::REGISTRY );
		delete_option( 'lfh_load_count' );
		delete_option( 'lfh_results' );
	}

	/**
	 * Moves every seeded check back in time.
	 *
	 * @param float $days Days to move back.
	 *
	 * @return integer Number of links changed.
	 */
	public static function age( float $days ): int {
		global $wpdb;

		$registry = get_option( self::REGISTRY );
		if ( ! is_array( $registry ) ) {
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
	 * Excludes a link in the posts listed in the harness option.
	 *
	 * @param boolean $excluded Current value.
	 * @param Link    $link     The link.
	 * @param integer $post_id  The post.
	 *
	 * @return boolean
	 */
	public static function filter_post_exclusion( $excluded, $link, $post_id ) {
		$map = (array) get_option( self::POST_FILTER_OPTION, array() );
		if ( isset( $map[ $post_id ] ) && in_array( $link->get_href(), (array) $map[ $post_id ], true ) ) {
			return true;
		}
		return $excluded;
	}

	/**
	 * Sends ?lfh_go=mixed-links to the main post, seeding first if needed.
	 *
	 * @return void
	 */
	public static function maybe_redirect(): void {
		if ( ! isset( $_GET['lfh_go'] ) || self::SLUG !== $_GET['lfh_go'] || ! current_user_can( 'manage_options' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$registry = get_option( self::REGISTRY );
		if ( ! is_array( $registry ) ) {
			$registry = self::seed();
		}

		wp_safe_redirect( get_permalink( $registry['posts']['main'] ) );
		exit;
	}

	/**
	 * The data the panel needs for a harness post, or null for any other post.
	 *
	 * @param integer $post_id The post being viewed.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function page_data( int $post_id ): ?array {
		$registry = get_option( self::REGISTRY );
		if ( ! is_array( $registry ) ) {
			return null;
		}

		$role = array_search( $post_id, $registry['posts'], true );
		if ( false === $role ) {
			return null;
		}

		$repository = new Link_Repository();
		$links      = array();

		foreach ( self::links() as $def ) {
			if ( ! in_array( $role, $def['posts'], true ) ) {
				continue;
			}

			$link = isset( $registry['links'][ $def['id'] ] ) ? $repository->find_by_id( (int) $registry['links'][ $def['id'] ] ) : null;

			$links[] = array(
				'id'         => $def['id'],
				'label'      => $def['label'],
				'url'        => $def['url'],
				'expect'     => $def['expect'],
				'exclusion'  => self::exclusion_for( $def, $role ),
				'below_fold' => $def['below_fold'],
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
			'scenario' => self::SLUG,
			'role'     => $role,
			'post_id'  => $post_id,
			'posts'    => $posts,
			'links'    => $links,
		);
	}
}
