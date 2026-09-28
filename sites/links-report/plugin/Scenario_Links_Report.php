<?php
/**
 * Scenario: twelve links in every state the Links report can show.
 *
 * @package LinkFixer_Harness
 */

namespace LinkFixer_Harness;

defined( 'ABSPATH' ) || exit;

/**
 * Links report.
 */
class Scenario_Links_Report extends Scenario {

	/**
	 * Slug.
	 *
	 * @return string
	 */
	public function slug(): string {
		return 'links-report';
	}

	/**
	 * Plain English name.
	 *
	 * @return string
	 */
	public function title(): string {
		return 'Links report';
	}

	/**
	 * What it proves.
	 *
	 * @return string
	 */
	public function summary(): string {
		return 'Twelve links in every state the Links report can show, for filtering, searching, bulk actions and the link details screen.';
	}

	/**
	 * One post holding every link, so the details screen can show where each link was found.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function posts(): array {
		return array(
			'report' => array(
				'title' => 'Harness: report links',
				'intro' => 'This post holds the twelve links used by the Links report site. Go to Link Fixer, Links.',
			),
		);
	}

	/**
	 * The twelve links.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function links(): array {
		$failed = self::failed_history();
		$posts  = array( 'report' );

		return array(
			self::link(
				array(
					'id'      => 'A1',
					'label'   => 'Broken, with an archive',
					'url'     => self::url( 'a1-broken-archive', 'check/404' ),
					'history' => $failed,
					'broken'  => true,
					'posts'   => $posts,
				)
			),
			self::link(
				array(
					'id'      => 'A2',
					'label'   => 'Working',
					'url'     => self::url( 'a2-working', 'check/200' ),
					'history' => array( array( 200, 4 ) ),
					'posts'   => $posts,
				)
			),
			self::link(
				array(
					'id'      => 'A3',
					'label'   => 'Broken, no archive',
					'url'     => self::url( 'a3-no-archive', 'check/404/archive/none' ),
					'history' => $failed,
					'broken'  => true,
					'archive' => false,
					'posts'   => $posts,
				)
			),
			self::link(
				array(
					'id'      => 'A4',
					'label'   => 'New, not processed yet',
					'url'     => self::url( 'a4-new', 'check/200' ),
					'archive' => false,
					'process' => 'new',
					'posts'   => $posts,
				)
			),
			self::link(
				array(
					'id'      => 'A5',
					'label'   => 'Pending',
					'url'     => self::url( 'a5-pending', 'check/200' ),
					'archive' => false,
					'process' => 'pending',
					'posts'   => $posts,
				)
			),
			self::link(
				array(
					'id'        => 'A6',
					'label'     => 'Excluded by hand',
					'url'       => self::url( 'a6-manual', 'check/404' ),
					'history'   => $failed,
					'broken'    => true,
					'exclusion' => 'manual',
					'posts'     => $posts,
				)
			),
			self::link(
				array(
					'id'        => 'A7',
					'label'     => 'Excluded by a settings rule',
					'url'       => self::url( 'a7-rule', 'check/404' ),
					'history'   => $failed,
					'broken'    => true,
					'exclusion' => 'global',
					'pattern'   => '*harness.test/a7-rule*',
					'posts'     => $posts,
				)
			),
			self::link(
				array(
					'id'        => 'A8',
					'label'     => 'Excluded by the built-in list (LinkedIn)',
					'url'       => 'https://www.linkedin.com/in/lfh-harness-a8',
					'history'   => $failed,
					'broken'    => true,
					'exclusion' => 'builtin',
					'posts'     => $posts,
				)
			),
			self::link(
				array(
					'id'      => 'A9',
					'label'   => 'For "Check link status"',
					'url'     => self::url( 'a9-check', 'check/404' ),
					'history' => array( array( 200, 10 ) ),
					'posts'   => $posts,
				)
			),
			self::link(
				array(
					'id'       => 'A10',
					'label'    => 'For "Update to latest snapshot"',
					'url'      => self::url( 'a10-update', 'check/200' ),
					'history'  => array( array( 200, 4 ) ),
					'archived' => 'https://web.archive.org/web/20200101000000/' . self::url( 'a10-update', 'check/200' ),
					'posts'    => $posts,
				)
			),
			self::link(
				array(
					'id'      => 'A11',
					'label'   => 'For "Create new snapshot"',
					'url'     => self::url( 'a11-new-snapshot', 'check/200/save/ok/status/success' ),
					'history' => array( array( 200, 4 ) ),
					'posts'   => $posts,
				)
			),
			self::link(
				array(
					'id'      => 'A12',
					'label'   => 'For "Verify link allows checking"',
					'url'     => self::url( 'a12-validate', 'check/200/save/ok/status/no-access' ),
					'history' => array( array( 200, 4 ) ),
					'posts'   => $posts,
				)
			),
		);
	}
}
