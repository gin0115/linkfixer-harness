<?php
/**
 * The list of scenarios, and the hooks they share.
 *
 * @package LinkFixer_Harness
 */

namespace LinkFixer_Harness;

defined( 'ABSPATH' ) || exit;

/**
 * Scenario registry.
 */
class Scenarios {

	/**
	 * Scenario instances by slug.
	 *
	 * @var array<string, Scenario>|null
	 */
	private static $all = null;

	/**
	 * Registers the hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_filter( 'iawmlf_exclude_link_from_post', array( self::class, 'filter_post_exclusion' ), 10, 3 );
		add_action( 'wp', array( self::class, 'apply_overrides' ) );
		add_action( 'template_redirect', array( self::class, 'maybe_redirect' ) );
	}

	/**
	 * Every scenario, by slug.
	 *
	 * @return array<string, Scenario>
	 */
	public static function all(): array {
		if ( null === self::$all ) {
			self::$all = array();
			foreach ( array( new Scenario_Mixed_Links(), new Scenario_Display_Modes() ) as $scenario ) {
				self::$all[ $scenario->slug() ] = $scenario;
			}
		}
		return self::$all;
	}

	/**
	 * A scenario by slug.
	 *
	 * @param string $slug The slug.
	 *
	 * @return Scenario|null
	 */
	public static function get( string $slug ): ?Scenario {
		return self::all()[ $slug ] ?? null;
	}

	/**
	 * The scenario a post belongs to.
	 *
	 * @param integer $post_id The post.
	 *
	 * @return Scenario|null
	 */
	public static function for_post( int $post_id ): ?Scenario {
		foreach ( self::all() as $scenario ) {
			if ( null !== $scenario->role_for_post( $post_id ) ) {
				return $scenario;
			}
		}
		return null;
	}

	/**
	 * Forces a scenario post's options for this page view only.
	 *
	 * Runs on "wp", before scripts are enqueued and the content is rendered.
	 *
	 * @return void
	 */
	public static function apply_overrides(): void {
		if ( ! is_singular() ) {
			return;
		}

		$post_id  = (int) get_queried_object_id();
		$scenario = self::for_post( $post_id );
		if ( null === $scenario ) {
			return;
		}

		foreach ( $scenario->overrides_for_role( (string) $scenario->role_for_post( $post_id ) ) as $option => $value ) {
			add_filter( 'pre_option_' . $option, static fn() => $value );
		}
	}

	/**
	 * Excludes a link in the posts a scenario lists for the per-post filter.
	 *
	 * @param boolean                                                  $excluded Current value.
	 * @param \Internet_Archive\Wayback_Machine_Link_Fixer\Link\Link $link     The link.
	 * @param integer                                                  $post_id  The post.
	 *
	 * @return boolean
	 */
	public static function filter_post_exclusion( $excluded, $link, $post_id ) {
		foreach ( self::all() as $scenario ) {
			$filters = $scenario->post_filters();
			if ( isset( $filters[ $post_id ] ) && in_array( $link->get_href(), (array) $filters[ $post_id ], true ) ) {
				return true;
			}
		}
		return $excluded;
	}

	/**
	 * Sends ?lfh_go=<slug> to the scenario's first post, seeding it first if needed.
	 *
	 * @return void
	 */
	public static function maybe_redirect(): void {
		if ( ! isset( $_GET['lfh_go'] ) || ! current_user_can( 'manage_options' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$scenario = self::get( sanitize_key( wp_unslash( $_GET['lfh_go'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( null === $scenario ) {
			return;
		}

		if ( null === $scenario->registry() ) {
			$scenario->seed();
		}

		wp_safe_redirect( $scenario->first_url() );
		exit;
	}
}
