<?php
/**
 * Scenario: one post holding a link in every state, plus the posts needed for the per-post cases.
 *
 * @package LinkFixer_Harness
 */

namespace LinkFixer_Harness;

defined( 'ABSPATH' ) || exit;

/**
 * Broken links on the page.
 */
class Scenario_Mixed_Links extends Scenario {

	/**
	 * Slug.
	 *
	 * @return string
	 */
	public function slug(): string {
		return 'mixed-links';
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
		return 'Links in every state, checked as a visitor scrolls: broken links are swapped to the archived copy, links that come back are restored, and excluded links are never touched.';
	}

	/**
	 * The posts, by role.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function posts(): array {
		return array(
			'main'     => array(
				'title' => 'Harness: every link state',
				'intro' => 'Every link in this post has a scripted Archive.org response and a seeded check history. They are spread through the text, so scroll down slowly: each one is only checked once it comes into view. The harness panel shows what should happen to each link on this load, and what actually did.',
			),
			'other'    => array(
				'title' => 'Harness: same link in another post',
				'intro' => 'X05 is excluded in "Harness: every link state" by the iawmlf_exclude_link_from_post filter. Here it is not excluded, so it is checked and swapped.',
			),
			'excluded' => array(
				'title'    => 'Harness: excluded post',
				'intro'    => 'This whole post is in the Link Fixer excluded posts list. Nothing on this page should be checked or swapped, including L01 which is broken in the other post.',
				'excluded' => true,
			),
		);
	}

	/**
	 * The links. History is [http code, days ago]; with the defaults a last check 4 days ago is due on load.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function links(): array {
		$failed = self::failed_history();

		return array(
			self::link(
				array(
					'id'      => 'L01',
					'label'   => 'Broken, stays broken',
					'url'     => self::url( 'l01-broken-stays', 'check/404' ),
					'history' => $failed,
					'broken'  => true,
					'posts'   => array( 'main', 'excluded' ),
					'expect'  => 'Last check 4 days ago, so it is checked on load. The checker returns 404, it stays broken and is swapped to the archive.',
				)
			),
			self::link(
				array(
					'id'      => 'L02',
					'label'   => 'Broken, now fixed',
					'url'     => self::url( 'l02-broken-now-fixed', 'check/200' ),
					'history' => $failed,
					'broken'  => true,
					'posts'   => array( 'main' ),
					'expect'  => 'Broken when the page loads. Checked on load, the checker returns 200, so it is no longer broken and is NOT swapped.',
				)
			),
			self::link(
				array(
					'id'      => 'L03',
					'label'   => 'Fine, becomes broken (third strike)',
					'url'     => self::url( 'l03-third-strike', 'check/404' ),
					'history' => array( array( 200, 12 ), array( 404, 8 ), array( 404, 4 ) ),
					'posts'   => array( 'main' ),
					'expect'  => 'Two 404s already. The check on load returns a third 404, so it becomes broken and is swapped on this same load.',
				)
			),
			self::link(
				array(
					'id'     => 'L04',
					'label'  => 'Fine, breaks slowly (no history)',
					'url'    => self::url( 'l04-slow-decay', 'check/404' ),
					'posts'  => array( 'main' ),
					'expect' => 'Never checked, so it is checked on load. One 404 is not enough. After "Age 4 days + reload" twice more it turns broken and is swapped on the third checked load.',
				)
			),
			self::link(
				array(
					'id'      => 'L05',
					'label'   => 'Healthy',
					'url'     => self::url( 'l05-healthy', 'check/200' ),
					'history' => array( array( 200, 4 ) ),
					'posts'   => array( 'main' ),
					'expect'  => 'Checked on load, returns 200, never swapped.',
				)
			),
			self::link(
				array(
					'id'      => 'L06',
					'label'   => 'Broken, checked 30 minutes ago',
					'url'     => self::url( 'l06-recently-checked', 'check/200' ),
					'history' => array( array( 404, 10 ), array( 404, 6 ), array( 404, 0.02 ) ),
					'broken'  => true,
					'posts'   => array( 'main' ),
					'expect'  => 'Checked recently, so NO check on load. It is swapped from its stored broken state. After "Age 4 days + reload" it is checked, returns 200 and recovers.',
				)
			),
			self::link(
				array(
					'id'      => 'L07',
					'label'   => 'Flaky',
					'url'     => self::url( 'l07-flaky', 'check/404,200,404,200' ),
					'history' => array( array( 404, 12 ), array( 200, 8 ), array( 404, 4 ) ),
					'posts'   => array( 'main' ),
					'expect'  => 'Alternates 404 and 200. There is always a 200 in the last 3 checks, so it never becomes broken on any load.',
				)
			),
			self::link(
				array(
					'id'      => 'L08',
					'label'   => 'Checker service offline',
					'url'     => self::url( 'l08-checker-offline', 'check/offline' ),
					'history' => array( array( 404, 8 ), array( 404, 4 ) ),
					'posts'   => array( 'main' ),
					'expect'  => 'The checker service throws Service_Offline_Exception. The REST check returns 500, nothing is recorded, it stays not broken and is never swapped (the 1.4.3 fix).',
				)
			),
			self::link(
				array(
					'id'      => 'L09',
					'label'   => 'Blocks bots (403)',
					'url'     => self::url( 'l09-bot-blocked', 'check/403' ),
					'history' => array( array( 403, 8 ), array( 403, 4 ) ),
					'posts'   => array( 'main' ),
					'expect'  => 'A third 403 on load. 403 is not a valid code, so it becomes broken and is swapped. This is the known false positive for sites that block bots.',
				)
			),
			self::link(
				array(
					'id'      => 'L10',
					'label'   => 'Rate limited (429)',
					'url'     => self::url( 'l10-rate-limited', 'check/429' ),
					'history' => array( array( 429, 12 ), array( 429, 8 ), array( 429, 4 ) ),
					'posts'   => array( 'main' ),
					'expect'  => '429 counts as a valid code, so it is checked but never broken.',
				)
			),
			self::link(
				array(
					'id'      => 'L11',
					'label'   => 'Broken, no archive',
					'url'     => self::url( 'l11-no-archive', 'check/404/archive/none' ),
					'history' => $failed,
					'broken'  => true,
					'posts'   => array( 'main' ),
					'archive' => false,
					'expect'  => 'Broken but has no archive URL, so the front end skips it: no check, no swap.',
				)
			),
			self::link(
				array(
					'id'        => 'X01',
					'label'     => 'Excluded: settings rule',
					'url'       => self::url( 'x01-global-rule', 'check/404' ),
					'history'   => $failed,
					'broken'    => true,
					'exclusion' => 'global',
					'pattern'   => '*harness.test/x01-global-rule*',
					'posts'     => array( 'main' ),
					'expect'    => 'Matches the rule *harness.test/x01-global-rule* in Advanced Settings. Not in the page link data, never checked, never swapped.',
				)
			),
			self::link(
				array(
					'id'        => 'X02',
					'label'     => 'Excluded: built-in list (LinkedIn)',
					'url'       => 'https://www.linkedin.com/in/lfh-harness-x02',
					'history'   => $failed,
					'broken'    => true,
					'exclusion' => 'builtin',
					'posts'     => array( 'main' ),
					'expect'    => 'Matches the built-in *.linkedin.com* rule. Not in the page link data, never checked, never swapped.',
				)
			),
			self::link(
				array(
					'id'        => 'X03',
					'label'     => 'Excluded: manually',
					'url'       => self::url( 'x03-manual', 'check/404' ),
					'history'   => $failed,
					'broken'    => true,
					'exclusion' => 'manual',
					'posts'     => array( 'main' ),
					'expect'    => 'Excluded with the "Exclude this link" checkbox. Not in the page link data, never checked, never swapped.',
				)
			),
			self::link(
				array(
					'id'        => 'X04',
					'label'     => 'Excluded: by the system (no-access)',
					'url'       => self::url( 'x04-system', 'check/404' ),
					'history'   => $failed,
					'broken'    => true,
					'exclusion' => 'system',
					'posts'     => array( 'main' ),
					'expect'    => 'Excluded by the plugin after Archive.org returned error:no-access. Not in the page link data, never checked, never swapped.',
				)
			),
			self::link(
				array(
					'id'        => 'X05',
					'label'     => 'Excluded in this post only (filter)',
					'url'       => self::url( 'x05-post-filter', 'check/404' ),
					'history'   => $failed,
					'broken'    => true,
					'exclusion' => 'post_filter',
					'filter_in' => array( 'main' ),
					'posts'     => array( 'main', 'other' ),
					'expect'    => array(
						'main'  => 'Excluded in this post only by the iawmlf_exclude_link_from_post filter. Not in the page link data, never checked, never swapped.',
						'other' => 'Not excluded in this post: checked on load, the checker returns 404, it stays broken and is swapped.',
					),
				)
			),
			self::link(
				array(
					'id'      => 'E01',
					'label'   => 'Link in the excluded post',
					'url'     => self::url( 'e01-excluded-post', 'check/404' ),
					'history' => $failed,
					'broken'  => true,
					'posts'   => array( 'excluded' ),
					'expect'  => 'The whole post is excluded: no link data, no checker script, nothing swapped.',
				)
			),
			self::link(
				array(
					'id'         => 'L12',
					'label'      => 'Below the fold',
					'url'        => self::url( 'l12-below-fold', 'check/404' ),
					'history'    => $failed,
					'broken'     => true,
					'posts'      => array( 'main' ),
					'below_fold' => true,
					'expect'     => 'Only checked once scrolled into view. Then 404, stays broken, swapped.',
				)
			),
		);
	}
}
