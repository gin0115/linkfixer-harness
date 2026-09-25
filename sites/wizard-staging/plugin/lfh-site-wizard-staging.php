<?php
/**
 * Plugin Name: Link Fixer Harness site: setup wizard (staging)
 * Description: A fresh install on a staging site (WP_ENVIRONMENT_TYPE staging). The checklist panel walks through the two step setup wizard and ticks off each step as it sees it happen.
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
			'title' => 'Setup wizard (staging)',
			'intro' => 'A fresh install of the Link Fixer on a staging site. The wizard has only two steps, because your own posts are never archived from staging. Do each step in order; each one ticks itself off when the panel sees it happen.',
			'items' => array(
				array(
					'id'     => 'redirect',
					'do'     => 'Open the WordPress dashboard.',
					'expect' => 'You are sent straight to the setup wizard, on step 1 of 2.',
					'link'   => 'admin:index.php',
					'when'   => array( $text( $progress, 'Step 1 of' ) ),
					'checks' => array(
						$text( $progress, 'Step 1 of 2', 'It says step 1 of 2' ),
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
					),
				),
				array(
					'id'     => 'step-2',
					'do'     => 'Press "Configure the Link Fixer".',
					'expect' => 'Step 2 of 2, "Choose what to fix", with Posts and Pages already ticked. The button says "Finish Setup", because there is no step 3 on staging.',
					'when'   => array( $text( $heading, 'Choose what to fix' ) ),
					'checks' => array(
						$text( $progress, 'Step 2 of 2', 'It says step 2 of 2' ),
						$text( $next, 'Finish Setup', 'The button says "Finish Setup"' ),
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
					),
				),
				array(
					'id'     => 'finished',
					'do'     => 'Leave Posts and Pages ticked and press "Finish Setup".',
					'expect' => '"You\'re all set!". The Link Fixer is on for posts and pages and a scan of existing posts started, but archiving of your own posts was never switched on.',
					'when'   => array( $text( $done, 'all set' ) ),
					'checks' => array(
						$option( 'iawmlf_setup_wizard_completed', true, 'The wizard is marked as finished' ),
						$option( 'iawmlf_process_links', true, 'The Link Fixer is switched on' ),
						$option( 'iawmlf_post_types', array( 'post', 'page' ), 'It fixes links in posts and pages' ),
						$option( 'iawmlf_allow_own_content_submissions', null, 'Archiving your own posts was never set' ),
						array(
							'type'   => 'action',
							'hook'   => 'iawmlf_scan_existing_posts',
							'status' => array( 'pending', 'complete' ),
							'min'    => 1,
							'within' => 600,
							'say'    => 'A scan of existing posts was queued to run straight away',
						),
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
					'id'     => 'settings',
					'do'     => 'Open Link Fixer, Advanced Settings.',
					'expect' => 'The Auto Archiver section says "Non-production environment detected - auto archiving is disabled".',
					'link'   => 'admin:admin.php?page=iawmlf_settings',
					'when'   => array(
						array(
							'type' => 'url',
							'has'  => 'page=iawmlf_settings',
						),
					),
					'checks' => array(
						$text( '#iawmlf_settings_auto_archiver_section p.description.staging', 'Non-production environment detected', 'The "Non-production environment detected" message is shown' ),
					),
				),
			),
		);
	}
);
