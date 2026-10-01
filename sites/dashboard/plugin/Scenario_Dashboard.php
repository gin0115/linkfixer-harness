<?php
/**
 * Scenario: seven links for the Link Fixer Dashboard once onboarding is over.
 *
 * @package LinkFixer_Harness
 */

namespace LinkFixer_Harness;

use Internet_Archive\Wayback_Machine_Link_Fixer\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Dashboard.
 */
class Scenario_Dashboard extends Scenario {

	/**
	 * Slug.
	 *
	 * @return string
	 */
	public function slug(): string {
		return 'dashboard';
	}

	/**
	 * Plain English name.
	 *
	 * @return string
	 */
	public function title(): string {
		return 'Dashboard';
	}

	/**
	 * What it proves.
	 *
	 * @return string
	 */
	public function summary(): string {
		return 'The Link Fixer Dashboard after onboarding: the six numbers, the tables they link to, Recent Link Checks, Latest Links and the WordPress Dashboard widget.';
	}

	/**
	 * One post.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function posts(): array {
		return array(
			'dash' => array(
				'title' => 'Harness: dashboard links',
				'intro' => 'Seven links, D1 to D7, one for each number on the Link Fixer Dashboard.',
			),
		);
	}

	/**
	 * D1 to D7. Each last check is a day older than the one before, so Recent Link Checks has a known order.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function links(): array {
		$posts = array( 'dash' );

		return array(
			self::link(
				array(
					'id'      => 'D1',
					'label'   => 'Broken, archived',
					'url'     => self::url( 'd1-broken-archived', 'check/404' ),
					'history' => array( array( 404, 9 ), array( 404, 5 ), array( 404, 1 ) ),
					'broken'  => true,
					'posts'   => $posts,
				)
			),
			self::link(
				array(
					'id'      => 'D2',
					'label'   => 'Broken, archived',
					'url'     => self::url( 'd2-broken-archived', 'check/404' ),
					'history' => array( array( 404, 10 ), array( 404, 6 ), array( 404, 2 ) ),
					'broken'  => true,
					'posts'   => $posts,
				)
			),
			self::link(
				array(
					'id'      => 'D3',
					'label'   => 'Broken, no archive',
					'url'     => self::url( 'd3-broken-no-archive', 'check/404/archive/none' ),
					'history' => array( array( 404, 11 ), array( 404, 7 ), array( 404, 3 ) ),
					'broken'  => true,
					'archive' => false,
					'posts'   => $posts,
				)
			),
			self::link(
				array(
					'id'      => 'D4',
					'label'   => 'Working, archived',
					'url'     => self::url( 'd4-working-archived', 'check/200' ),
					'history' => array( array( 200, 4 ) ),
					'posts'   => $posts,
				)
			),
			self::link(
				array(
					'id'      => 'D5',
					'label'   => 'Working, no archive',
					'url'     => self::url( 'd5-working-no-archive', 'check/200/archive/none' ),
					'history' => array( array( 200, 5 ) ),
					'archive' => false,
					'posts'   => $posts,
				)
			),
			self::link(
				array(
					'id'      => 'D6',
					'label'   => 'New, never checked',
					'url'     => self::url( 'd6-new', 'check/200' ),
					'archive' => false,
					'process' => 'new',
					'posts'   => $posts,
				)
			),
			self::link(
				array(
					'id'        => 'D7',
					'label'     => 'Broken, archived, excluded by hand',
					'url'       => self::url( 'd7-excluded', 'check/404' ),
					'history'   => array( array( 404, 12 ), array( 404, 8 ), array( 404, 6 ) ),
					'broken'    => true,
					'exclusion' => 'manual',
					'posts'     => $posts,
				)
			),
		);
	}

	/**
	 * Seeds, ends onboarding, adds working Archive.org keys and clears the Dashboard's caches.
	 *
	 * @return array<string, mixed> The registry.
	 */
	public function seed(): array {
		$registry = parent::seed();

		// Onboarding lasts 7 days; after that the Dashboard shows the overview.
		update_option( Settings::ONBOARDING_DATE_KEY, gmdate( 'Y-m-d H:i:s', time() - 8 * DAY_IN_SECONDS ) );

		update_option( Settings::ARCHIVE_ORG_ACCESS_KEY, 'valid-access' );
		update_option( Settings::ARCHIVE_ORG_SECRET_KEY, 'valid-secret' );
		update_option( Settings::ALLOW_OWN_CONTENT_SUBMISSIONS, true );
		update_option( Settings::SCAN_EXISTING_POSTS, false );

		foreach ( array( 'iawmlf_dashboard_stats', 'iawmlf_dashboard_onboarding_stats', 'iawmlf_account_details' ) as $transient ) {
			delete_transient( $transient );
		}

		return $registry;
	}
}
