<?php
/**
 * Plugin Name: Link Fixer Harness site: setup wizard (production)
 * Description: A fresh install on a production site. The checklist panel walks through the three step setup wizard and ticks off each step as it sees it happen.
 * Version: 0.1.0
 * Requires PHP: 7.4
 * Requires Plugins: linkfixer-harness
 * Author: Glynn Quelch
 * License: GPL-2.0-or-later
 *
 * @package LinkFixer_Harness
 */

namespace LinkFixer_Harness;

defined( 'ABSPATH' ) || exit;

add_filter(
	'lfh_checklist',
	static function () {
		// Selectors from templates/admin/wizard/*.php in the Link Fixer.
		$progress = '.iawmlf-wizard__footer__progress__bar p';
		$next     = 'button[name="next-step"]';
		$previous = 'button[name="iawmlf-previous-step"]';
		$heading  = '.iawmlf-wizard__content__intro h3';
		$done     = '.iawmlf-wizard__content__header h2';

		$text   = static fn( $selector, $has, $say = '' ) => array_filter(
			array(
				'type'     => 'text',
				'selector' => $selector,
				'has'      => $has,
				'say'      => $say,
			)
		);
		$option = static fn( $name, $is, $say ) => array(
			'type' => 'option',
			'name' => $name,
			'is'   => $is,
			'say'  => $say,
		);

		return array(
			'title' => 'Setup wizard (production)',
			'intro' => 'A fresh install of the Link Fixer on a live site. Do each step in order; each one ticks itself off when the panel sees it happen.',
			'items' => array(
				array(
					'id'     => 'redirect',
					'do'     => 'Open the WordPress dashboard.',
					'expect' => 'You are sent straight to the setup wizard, on step 1 of 3, with a "Configure the Link Fixer" button and no back button.',
					'link'   => 'admin:index.php',
					'when'   => array( $text( $progress, 'Step 1 of 3' ) ),
					'checks' => array(
						array(
							'type' => 'url',
							'has'  => 'iawmlf_onboarding=1',
							'say'  => 'You were sent here from the dashboard',
						),
						$text( $next, 'Configure the Link Fixer' ),
						array(
							'type'     => 'missing',
							'selector' => $previous,
							'say'      => 'There is no back button',
						),
						$option( 'iawmlf_post_activation_onboarding', 'iawmlf_onboarding_pending', 'Onboarding is still pending' ),
					),
				),
				array(
					'id'     => 'step-2',
					'do'     => 'Press "Configure the Link Fixer".',
					'expect' => 'Step 2 of 3, "Choose what to fix", with Posts and Pages already ticked and a back button to "About".',
					'when'   => array( $text( $heading, 'Choose what to fix' ) ),
					'checks' => array(
						$text( $progress, 'Step 2 of 3' ),
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
						$text( $previous, 'About', 'There is a back button to "About"' ),
						$text( $next, 'Configure the Auto Archiver' ),
					),
				),
				array(
					'id'     => 'step-2-saved',
					'do'     => 'Leave Posts and Pages ticked and press "Configure the Auto Archiver".',
					'expect' => 'Step 3 of 3, "Preserve your content". The Link Fixer is now on for posts and pages, and a scan of your existing posts has been started straight away.',
					'when'   => array(
						$text( $heading, 'Preserve your content' ),
						array(
							'type' => 'url',
							'has'  => 'iawmlf_onboarding=1',
						),
					),
					'checks' => array(
						$text( $progress, 'Step 3 of 3' ),
						$option( 'iawmlf_process_links', true, 'The Link Fixer is switched on' ),
						$option( 'iawmlf_post_types', array( 'post', 'page' ), 'It fixes links in posts and pages' ),
						$option( 'iawmlf_scan_existing_posts', true, 'Existing posts will be scanned' ),
						$option( 'iawmlf_cast_to_https', true, 'Archived links are forced to https' ),
						$option( 'iawmlf_post_activation_onboarding', 'iawmlf_onboarding_completed', 'Onboarding is marked as done' ),
						array(
							'type'   => 'action',
							'hook'   => 'iawmlf_scan_existing_posts',
							// Action Scheduler's async runner usually finishes it within a second.
							'status' => array( 'pending', 'complete' ),
							'min'    => 1,
							'within' => 600,
							'say'    => 'A scan of existing posts was queued to run straight away',
						),
					),
				),
				array(
					'id'     => 'reminder',
					'do'     => 'Before finishing, open the Dashboard again.',
					'expect' => 'This time you are not sent to the wizard. A notice says the plugin is almost ready, with a link to the setup wizard.',
					'link'   => 'admin:index.php',
					'when'   => array(
						array(
							'type'     => 'exists',
							'selector' => 'body.index-php',
						),
						$option( 'iawmlf_setup_wizard_completed', null, 'The wizard is not finished' ),
					),
					'checks' => array(
						$text( '#wpbody-content', 'plugin is almost ready', 'The "almost ready" notice is shown' ),
					),
				),
				array(
					'id'     => 'resume',
					'do'     => 'Follow the notice\'s "run the setup wizard" link.',
					'expect' => 'The wizard carries on at step 3 of 3, "Preserve your content".',
					'link'   => 'admin:admin.php?page=iawmlf-setup-wizard',
					'when'   => array(
						$text( $heading, 'Preserve your content' ),
						array(
							'type'  => 'url',
							'lacks' => 'iawmlf_onboarding',
						),
						array(
							'type'  => 'url',
							'lacks' => 'rerun-wizard',
						),
					),
					'checks' => array(
						$text( $progress, 'Step 3 of 3' ),
						$text( $next, 'Finish Setup' ),
					),
				),
				array(
					'id'     => 'finished',
					'do'     => 'Leave Posts and Pages ticked and press "Finish Setup".',
					'expect' => '"You\'re all set!" with a "Go to the dashboard" button. Archiving of your own posts and pages is on, and they will be archived again regularly.',
					'when'   => array(
						$text( $done, 'all set' ),
						array(
							'type'  => 'url',
							'lacks' => 'rerun-wizard',
						),
					),
					'checks' => array(
						array(
							'type'     => 'exists',
							'selector' => 'a.button-primary[href*="page=iawmlf-dashboard"]',
							'say'      => 'There is a "Go to the dashboard" button',
						),
						$option( 'iawmlf_allow_own_content_submissions', true, 'Your own posts will be archived' ),
						$option( 'iawmlf_allowed_own_content_post_types', array( 'post', 'page' ), 'Posts and pages will be archived' ),
						$option( 'iawmlf_routinely_update_wayback_machine', true, 'They will be archived again regularly' ),
						$option( 'iawmlf_setup_wizard_completed', true, 'The wizard is marked as finished' ),
					),
				),
				array(
					'id'     => 'no-reminder',
					'do'     => 'Open the Dashboard.',
					'expect' => 'It opens normally, with no wizard and no "almost ready" notice.',
					'link'   => 'admin:index.php',
					'when'   => array(
						array(
							'type'     => 'exists',
							'selector' => 'body.index-php',
						),
						$option( 'iawmlf_setup_wizard_completed', true, 'The wizard is finished' ),
					),
					'checks' => array(
						array(
							'type'     => 'text',
							'selector' => '#wpbody-content',
							'lacks'    => 'plugin is almost ready',
							'say'      => 'The "almost ready" notice is gone',
						),
					),
				),
				array(
					'id'     => 'rerun',
					'do'     => 'Open the wizard again with the Open link (it adds rerun-wizard=1 to the address).',
					'expect' => 'The "You\'re all set!" page now has a back button, "← Configure the Auto Archiver".',
					'link'   => 'admin:admin.php?page=iawmlf-setup-wizard&rerun-wizard=1',
					'when'   => array(
						$text( $done, 'all set' ),
						array(
							'type' => 'url',
							'has'  => 'rerun-wizard=1',
						),
					),
					'checks' => array(
						$text( $previous, 'Configure the Auto Archiver', 'There is a back button to "Configure the Auto Archiver"' ),
					),
				),
				array(
					'id'     => 'rerun-back',
					'do'     => 'Press "← Configure the Auto Archiver".',
					'expect' => 'You are back on step 3 of 3 with your earlier choices still ticked. "Finish Setup" returns to "You\'re all set!".',
					'when'   => array(
						$text( $heading, 'Preserve your content' ),
						array(
							'type' => 'url',
							'has'  => 'rerun-wizard=1',
						),
					),
					'checks' => array(
						$text( $progress, 'Step 3 of 3' ),
						array(
							'type'     => 'checked',
							'selector' => '#iawmlf_wizard_post_types_post',
							'is'       => true,
							'say'      => 'Posts is still ticked',
						),
						array(
							'type'     => 'checked',
							'selector' => '#iawmlf_wizard_post_types_page',
							'is'       => true,
							'say'      => 'Pages is still ticked',
						),
					),
				),
				array(
					'id'     => 'settings',
					'do'     => 'Open Link Fixer, Advanced Settings.',
					'expect' => 'The Auto Archiver section describes archiving your content. There is no "Non-production environment detected" message.',
					'link'   => 'admin:admin.php?page=iawmlf_settings',
					'when'   => array(
						array(
							'type' => 'url',
							'has'  => 'page=iawmlf_settings',
						),
					),
					'checks' => array(
						$text( '#iawmlf_settings_auto_archiver_section', 'Keep your content securely archived', 'The Auto Archiver section describes archiving your content' ),
						array(
							'type'     => 'missing',
							'selector' => '#iawmlf_settings_auto_archiver_section p.description.staging',
							'say'      => 'There is no "Non-production environment detected" message',
						),
					),
				),
			),
		);
	}
);
