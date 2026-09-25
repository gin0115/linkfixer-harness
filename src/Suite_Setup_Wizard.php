<?php
/**
 * Suite: the first run setup wizard, on production and on staging.
 *
 * @package LinkFixer_Harness
 */

namespace LinkFixer_Harness;

defined( 'ABSPATH' ) || exit;

/**
 * Drives templates/admin/wizard/* and Setup_Wizard::handle_form().
 */
class Suite_Setup_Wizard extends Suite {

	private const PROGRESS = '.iawmlf-wizard__footer__progress__bar p';
	private const NEXT     = 'button[name="next-step"]';
	private const PREVIOUS = 'button[name="iawmlf-previous-step"]';
	private const HEADING  = '.iawmlf-wizard__content__intro h3';
	private const DONE     = '.iawmlf-wizard__content__header h2';

	/**
	 * Slug.
	 *
	 * @return string
	 */
	public function slug(): string {
		return 'setup-wizard';
	}

	/**
	 * Plain English name.
	 *
	 * @return string
	 */
	public function title(): string {
		return 'First run setup wizard';
	}

	/**
	 * What it proves.
	 *
	 * @return string
	 */
	public function summary(): string {
		return 'A fresh install sends the administrator to a setup wizard. On a live site it has three steps (about, what to fix, archive your own posts); on a staging site it has two. Each step saves the right settings and the wizard only goes away once it is finished.';
	}

	/**
	 * A text check.
	 *
	 * @param string $selector CSS selector.
	 * @param string $has      Text it must contain.
	 * @param string $say      Plain English line.
	 *
	 * @return array<string, string>
	 */
	private static function text( string $selector, string $has, string $say = '' ): array {
		return array_filter(
			array(
				'type'     => 'text',
				'selector' => $selector,
				'has'      => $has,
				'say'      => $say,
			)
		);
	}

	/**
	 * An option check.
	 *
	 * @param string $name The option.
	 * @param mixed  $is   Expected value.
	 * @param string $say  Plain English line.
	 *
	 * @return array<string, mixed>
	 */
	private static function option( string $name, $is, string $say ): array {
		return array(
			'type' => 'option',
			'name' => $name,
			'is'   => $is,
			'say'  => $say,
		);
	}

	/**
	 * The steps.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function steps(): array {
		$next = array( array( 'click' => self::NEXT ) );

		return array(
			// Live site, the happy path.
			array(
				'title'  => 'A fresh install sends the administrator straight to the setup wizard',
				'setup'  => array( array( 'environment', 'production' ), array( 'reset_wizard' ) ),
				'open'   => 'admin:index.php',
				'checks' => array(
					array(
						'type' => 'url',
						'has'  => 'page=iawmlf-setup-wizard',
						'say'  => 'Opening the dashboard lands on the setup wizard',
					),
					self::text( self::PROGRESS, 'Step 1 of 3', 'It says step 1 of 3' ),
					self::text( self::NEXT, 'Configure the Link Fixer', 'The button says "Configure the Link Fixer"' ),
					array(
						'type'     => 'missing',
						'selector' => self::PREVIOUS,
						'say'      => 'There is no back button on the first step',
					),
				),
				'then'   => $next,
			),
			array(
				'title'  => 'Step 2 asks which content to fix, with posts and pages already ticked',
				'checks' => array(
					self::text( self::HEADING, 'Choose what to fix' ),
					self::text( self::PROGRESS, 'Step 2 of 3' ),
					array(
						'type'     => 'checked',
						'selector' => '#iawmlf_wizard_post_types_post',
						'is'       => true,
						'say'      => 'Posts is ticked',
					),
					array(
						'type'     => 'checked',
						'selector' => '#iawmlf_wizard_post_types_page',
						'is'       => true,
						'say'      => 'Pages is ticked',
					),
					self::text( self::PREVIOUS, 'About', 'There is a back button to "About"' ),
					self::text( self::NEXT, 'Configure the Auto Archiver' ),
				),
				'then'   => $next,
			),
			array(
				'title'  => 'Finishing step 2 turns the Link Fixer on and starts scanning existing posts straight away',
				'checks' => array(
					self::text( self::HEADING, 'Preserve your content', 'Step 3, "Preserve your content", is shown' ),
					self::text( self::PROGRESS, 'Step 3 of 3' ),
					self::option( 'iawmlf_process_links', true, 'The Link Fixer is switched on' ),
					self::option( 'iawmlf_post_types', array( 'post', 'page' ), 'It fixes links in posts and pages' ),
					self::option( 'iawmlf_scan_existing_posts', true, 'Existing posts will be scanned' ),
					self::option( 'iawmlf_cast_to_https', true, 'Archived links are forced to https' ),
					self::option( 'iawmlf_post_activation_onboarding', 'iawmlf_onboarding_completed', 'Onboarding is marked as done' ),
					array(
						'type'       => 'action',
						'hook'       => 'iawmlf_scan_existing_posts',
						'status'     => 'pending',
						'count'      => 1,
						'due_within' => 60,
						'say'        => 'The scan of existing posts is queued to run now, not in 10 minutes',
					),
				),
			),
			array(
				'title'  => 'Until the last step is done, other admin pages show a reminder to finish the wizard',
				'open'   => 'admin:index.php',
				'checks' => array(
					array(
						'type'  => 'url',
						'lacks' => 'iawmlf-setup-wizard',
						'say'   => 'The dashboard is no longer redirected to the wizard',
					),
					self::text( '#wpbody-content', 'plugin is almost ready', 'A reminder says the plugin is almost ready' ),
				),
			),
			array(
				'title'  => 'Opening the wizard again carries on from step 3',
				'open'   => 'admin:admin.php?page=iawmlf-setup-wizard',
				'checks' => array(
					self::text( self::HEADING, 'Preserve your content' ),
					self::text( self::PROGRESS, 'Step 3 of 3' ),
					self::text( self::NEXT, 'Finish Setup' ),
				),
				'then'   => $next,
			),
			array(
				'title'  => 'Finishing step 3 turns on archiving of your own posts and shows the "all set" page',
				'checks' => array(
					self::text( self::DONE, 'all set', 'The page says "You\'re all set!"' ),
					array(
						'type'     => 'exists',
						'selector' => 'a.button-primary[href*="page=iawmlf-dashboard"]',
						'say'      => 'There is a "Go to the dashboard" button',
					),
					self::option( 'iawmlf_allow_own_content_submissions', true, 'Your own posts will be archived' ),
					self::option( 'iawmlf_allowed_own_content_post_types', array( 'post', 'page' ), 'Posts and pages will be archived' ),
					self::option( 'iawmlf_routinely_update_wayback_machine', true, 'They will be archived again regularly' ),
					self::option( 'iawmlf_setup_wizard_completed', true, 'The wizard is marked as finished' ),
				),
			),
			array(
				'title'  => 'Once finished, the dashboard opens normally with no reminder',
				'open'   => 'admin:index.php',
				'checks' => array(
					array(
						'type'  => 'url',
						'lacks' => 'iawmlf-setup-wizard',
					),
					array(
						'type'     => 'text',
						'selector' => '#wpbody-content',
						'lacks'    => 'plugin is almost ready',
						'say'      => 'The reminder is gone',
					),
				),
			),
			array(
				'title'  => 'Re-running the wizard lets you step back from the finished page',
				'open'   => 'admin:admin.php?page=iawmlf-setup-wizard&rerun-wizard=1',
				'checks' => array(
					self::text( self::DONE, 'all set' ),
					self::text( self::PREVIOUS, 'Configure the Auto Archiver', 'There is a back button to "Configure the Auto Archiver"' ),
				),
				'then'   => array( array( 'click' => self::PREVIOUS ) ),
			),
			array(
				'title'  => 'Going back shows step 3 again, and finishing returns to the "all set" page',
				'checks' => array(
					self::text( self::HEADING, 'Preserve your content' ),
				),
				'then'   => $next,
			),
			array(
				'title'  => 'The wizard is finished again',
				'checks' => array(
					self::text( self::DONE, 'all set' ),
					self::option( 'iawmlf_setup_wizard_completed', true, 'The wizard is still marked as finished' ),
				),
			),

			// Staging site.
			array(
				'title'  => 'On a staging site the wizard has only two steps',
				'setup'  => array( array( 'environment', 'staging' ), array( 'reset_wizard' ) ),
				'open'   => 'admin:index.php',
				'checks' => array(
					array(
						'type' => 'url',
						'has'  => 'page=iawmlf-setup-wizard',
					),
					self::text( self::PROGRESS, 'Step 1 of 2', 'It says step 1 of 2' ),
				),
				'then'   => $next,
			),
			array(
				'title'  => 'On staging, step 2 is the last step',
				'checks' => array(
					self::text( self::PROGRESS, 'Step 2 of 2' ),
					self::text( self::NEXT, 'Finish Setup', 'The button says "Finish Setup"' ),
				),
				'then'   => $next,
			),
			array(
				'title'  => 'On staging, finishing turns the Link Fixer on but never archives your own posts',
				'checks' => array(
					self::text( self::DONE, 'all set' ),
					self::option( 'iawmlf_setup_wizard_completed', true, 'The wizard is marked as finished' ),
					self::option( 'iawmlf_process_links', true, 'The Link Fixer is switched on' ),
					self::option( 'iawmlf_allow_own_content_submissions', null, 'Archiving your own posts was never set' ),
				),
			),

			// Edge case: nothing ticked.
			array(
				'title'  => 'Unticking every content type on step 2',
				'setup'  => array( array( 'environment', 'production' ), array( 'reset_wizard' ) ),
				'open'   => 'admin:index.php',
				'checks' => array(
					self::text( self::PROGRESS, 'Step 1 of 3' ),
				),
				'then'   => $next,
			),
			array(
				'title'  => 'Continuing with nothing ticked',
				'checks' => array(
					self::text( self::HEADING, 'Choose what to fix' ),
				),
				'then'   => array(
					array( 'uncheck' => '#iawmlf_wizard_post_types_post' ),
					array( 'uncheck' => '#iawmlf_wizard_post_types_page' ),
					array( 'click' => self::NEXT ),
				),
			),
			array(
				'title'  => 'With nothing ticked the Link Fixer stays off and no scan is queued',
				'checks' => array(
					self::text( self::HEADING, 'Preserve your content', 'The wizard still moves on to step 3' ),
					self::option( 'iawmlf_process_links', false, 'The Link Fixer is switched off' ),
					array(
						'type'   => 'action',
						'hook'   => 'iawmlf_scan_existing_posts',
						'status' => 'pending',
						'count'  => 0,
						'say'    => 'No scan of existing posts is queued',
					),
				),
				'then'   => $next,
			),
			array(
				'title'  => 'The wizard can still be finished, and the site is put back to normal',
				'setup'  => array( array( 'environment', '' ) ),
				'checks' => array(
					self::text( self::DONE, 'all set' ),
				),
			),
		);
	}
}
