<?php
/**
 * The "Link Fixer Tests" admin page, and loading the runner on every page.
 *
 * @package LinkFixer_Harness
 */

namespace LinkFixer_Harness;

defined( 'ABSPATH' ) || exit;

/**
 * Tests page.
 */
class Tests_Page {

	public const SLUG = 'lfh-tests';

	/**
	 * Registers the hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( self::class, 'register' ) );
		add_action( 'admin_init', array( self::class, 'skip_wizard_redirect' ), 0 );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue' ) );
	}

	/**
	 * URL of the page.
	 *
	 * @return string
	 */
	public static function url(): string {
		return admin_url( 'admin.php?page=' . self::SLUG );
	}

	/**
	 * Adds the menu page.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_menu_page( 'Link Fixer Tests', 'Link Fixer Tests', 'manage_options', self::SLUG, array( self::class, 'render' ), 'dashicons-yes-alt', 2 );
	}

	/**
	 * Setup_Wizard::maybe_trigger_onboarding_wizard() redirects every admin page while onboarding is
	 * pending, unless iawmlf_onboarding is in the query. A run stopped half way through the wizard
	 * suite must not lock this page, so it gets that parameter.
	 *
	 * @return void
	 */
	public static function skip_wizard_redirect(): void {
		if ( isset( $_GET['page'] ) && self::SLUG === $_GET['page'] && ! isset( $_GET['iawmlf_onboarding'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$_GET['iawmlf_onboarding'] = '1';
		}
	}

	/**
	 * Loads the runner for logged in users, on the front end and in admin.
	 *
	 * @return void
	 */
	public static function enqueue(): void {
		if ( ! is_user_logged_in() ) {
			return;
		}

		wp_enqueue_script( 'lfh-runner', LFH_URL . 'assets/runner.js', array(), LFH_VERSION, true );
		wp_enqueue_style( 'lfh-runner', LFH_URL . 'assets/runner.css', array(), LFH_VERSION );

		$config = array(
			'rest_root' => esc_url_raw( rest_url() ),
			'nonce'     => wp_create_nonce( 'wp_rest' ),
			'tests_url' => self::url(),
			'on_tests'  => is_admin() && isset( $_GET['page'] ) && self::SLUG === $_GET['page'], // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'suites'    => Suites::describe(),
		);

		wp_add_inline_script( 'lfh-runner', 'window.LFH_RUNNER = ' . wp_json_encode( $config ) . ';', 'before' );
	}

	/**
	 * Renders the page.
	 *
	 * @return void
	 */
	public static function render(): void {
		$runs   = Runner::runs();
		$latest = empty( $runs ) ? null : end( $runs );

		echo '<div class="wrap lfh-tests">';
		echo '<h1>Link Fixer Tests</h1>';
		echo '<p class="lfh-intro">These tests drive the Internet Archive Wayback Machine Link Fixer through real pages on this site, with Archive.org replaced by a scripted stand-in. Press a Run button and watch: the site moves from page to page by itself, and every step turns green or red. You do not need to click anything else.</p>';
		echo '<p><button type="button" class="button button-primary button-hero" data-lfh-run="all">Run all tests</button></p>';

		echo '<div class="lfh-cards">';
		foreach ( Suites::describe() as $suite ) {
			$result = null === $latest ? null : self::find_suite( $latest, $suite['slug'] );

			echo '<div class="lfh-card">';
			printf( '<h2>%s %s</h2>', wp_kses_post( self::badge( $result ) ), esc_html( $suite['title'] ) );
			printf( '<p>%s</p>', esc_html( $suite['summary'] ) );
			echo '<ol class="lfh-steps">';
			foreach ( $suite['steps'] as $index => $title ) {
				$step = $result['steps'][ $index ] ?? null;
				printf( '<li class="%1$s">%2$s</li>', esc_attr( null === $step ? '' : ( $step['pass'] ? 'lfh-pass' : 'lfh-fail' ) ), esc_html( $title ) );
			}
			echo '</ol>';
			printf( '<p><button type="button" class="button" data-lfh-run="%s">Run this suite</button></p>', esc_attr( $suite['slug'] ) );
			echo '</div>';
		}
		echo '</div>';

		echo '<h2>Latest run</h2>';
		if ( null === $latest ) {
			echo '<p>No tests have been run on this site yet.</p>';
		} else {
			self::render_run( $latest );
		}

		echo '</div>';
	}

	/**
	 * Finds a suite in a run.
	 *
	 * @param array<string, mixed> $run  The run.
	 * @param string               $slug The suite.
	 *
	 * @return array<string, mixed>|null
	 */
	private static function find_suite( array $run, string $slug ): ?array {
		foreach ( (array) ( $run['suites'] ?? array() ) as $suite ) {
			if ( ( $suite['slug'] ?? '' ) === $slug ) {
				return $suite;
			}
		}
		return null;
	}

	/**
	 * A pass or fail badge for a suite result.
	 *
	 * @param array<string, mixed>|null $result The suite result.
	 *
	 * @return string
	 */
	private static function badge( ?array $result ): string {
		if ( null === $result || empty( $result['steps'] ) ) {
			return '<span class="lfh-badge">not run</span>';
		}
		$steps  = (array) $result['steps'];
		$passed = count( array_filter( $steps, static fn( $step ) => ! empty( $step['pass'] ) ) );
		$class  = count( $steps ) === $passed ? 'lfh-badge-pass' : 'lfh-badge-fail';
		return sprintf( '<span class="lfh-badge %1$s">%2$d / %3$d</span>', $class, $passed, count( $steps ) );
	}

	/**
	 * Renders a stored run.
	 *
	 * @param array<string, mixed> $run The run.
	 *
	 * @return void
	 */
	private static function render_run( array $run ): void {
		$steps  = 0;
		$passed = 0;
		foreach ( (array) ( $run['suites'] ?? array() ) as $suite ) {
			foreach ( (array) ( $suite['steps'] ?? array() ) as $step ) {
				++$steps;
				$passed += empty( $step['pass'] ) ? 0 : 1;
			}
		}

		printf(
			'<p class="lfh-summary %1$s"><strong>%2$s</strong> %3$d of %4$d steps passed. Started %5$s%6$s.</p>',
			esc_attr( $steps === $passed && ! empty( $run['finished'] ) ? 'lfh-pass' : 'lfh-fail' ),
			esc_html( empty( $run['finished'] ) ? 'Not finished.' : ( $steps === $passed ? 'Everything passed.' : 'Some steps failed.' ) ),
			(int) $passed,
			(int) $steps,
			esc_html( (string) ( $run['started'] ?? '' ) ),
			empty( $run['finished'] ) ? '' : esc_html( ', finished ' . $run['finished'] )
		);
		echo '<p><button type="button" class="button" data-lfh-copy>Copy report</button></p>';

		foreach ( (array) ( $run['suites'] ?? array() ) as $suite ) {
			printf( '<h3>%s %s</h3>', wp_kses_post( self::badge( $suite ) ), esc_html( (string) ( $suite['title'] ?? '' ) ) );
			echo '<ol class="lfh-report">';
			foreach ( (array) ( $suite['steps'] ?? array() ) as $step ) {
				printf( '<li class="%1$s"><span class="lfh-mark">%2$s</span> %3$s', esc_attr( empty( $step['pass'] ) ? 'lfh-fail' : 'lfh-pass' ), empty( $step['pass'] ) ? '&#10007;' : '&#10003;', esc_html( (string) ( $step['title'] ?? '' ) ) );
				echo '<details><summary>Details for developers</summary><ul>';
				if ( ! empty( $step['error'] ) ) {
					printf( '<li class="lfh-fail">Setup failed: %s</li>', esc_html( (string) $step['error'] ) );
				}
				foreach ( (array) ( $step['checks'] ?? array() ) as $check ) {
					printf(
						'<li class="%1$s">%2$s %3$s <em>%4$s</em></li>',
						esc_attr( empty( $check['pass'] ) ? 'lfh-fail' : 'lfh-pass' ),
						empty( $check['pass'] ) ? '&#10007;' : '&#10003;',
						esc_html( (string) ( $check['say'] ?? '' ) ),
						esc_html( (string) ( $check['detail'] ?? '' ) )
					);
				}
				echo '</ul></details></li>';
			}
			echo '</ol>';
		}

		printf( '<script type="application/json" id="lfh-latest-run">%s</script>', wp_json_encode( $run, JSON_HEX_TAG | JSON_HEX_AMP ) );
	}
}
