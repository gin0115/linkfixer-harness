<?php
/**
 * Plugin Name: Link Fixer Harness site: advanced settings
 * Description: An onboarded site with default settings and no Archive.org keys. The checklist panel walks through the keys (wrong, then right), the dashboard widget's account stats, settings that show and hide, and saving values.
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

add_action(
	'lfh_loaded',
	static function () {
		require_once __DIR__ . '/Scenario_Settings.php';
		add_filter( 'lfh_scenarios', static fn( $scenarios ) => array_merge( $scenarios, array( new Scenario_Settings() ) ) );
	}
);

add_filter(
	'lfh_checklist',
	static function () {
		$mask       = '****************';
		$fixer_rows = 'tr.iawmlf_toggle_setting__fixer';
		$icon_row   = 'tr.iawmlf_toggle_setting__fixer_replace';

		$param   = static fn( $name, $is ) => array(
			'type' => 'param',
			'name' => $name,
			'is'   => $is,
		);
		// WordPress strips settings-updated from the address after a save (wp_admin_canonical_url), so
		// "after saving" items are recognised by what is now saved, not by the address.
		$page    = $param( 'page', 'iawmlf_settings' );
		$option  = static fn( $name, $is, $say = '' ) => array_filter(
			array(
				'type' => 'option',
				'name' => $name,
				'is'   => $is,
				'say'  => $say,
			),
			static fn( $value ) => '' !== $value
		);
		$notice  = static fn( $has, $say = '' ) => array_filter(
			array(
				'type' => 'notice',
				'has'  => $has,
				'say'  => $say,
			)
		);
		$no      = static fn( $lacks, $say ) => array(
			'type'  => 'notice',
			'lacks' => $lacks,
			'say'   => $say,
		);
		$value   = static fn( $selector, $is, $say ) => array(
			'type'     => 'value',
			'selector' => $selector,
			'is'       => $is,
			'say'      => $say,
		);
		$visible = static fn( $selector, $is, $say ) => array(
			'type'     => 'visible',
			'selector' => $selector,
			'is'       => $is,
			'say'      => $say,
		);
		$checked = static fn( $selector, $is ) => array(
			'type'     => 'checked',
			'selector' => $selector,
			'is'       => $is,
		);

		return array(
			'title' => 'Advanced Settings',
			'intro' => 'An onboarded site with default settings and no Archive.org keys. Archive.org is the harness stand-in: the keys valid-access / valid-secret are accepted, anything else is refused.',
			'items' => array(
				array(
					'id'     => 'no-keys',
					'do'     => 'Open Link Fixer, Advanced Settings.',
					'expect' => 'A notice says you are in unauthenticated mode (4000 new snapshots a day). Both Archive.org key fields are empty.',
					'link'   => 'admin:admin.php?page=iawmlf_settings',
					'when'   => array( $page, $option( 'iawmlf_archive_api_access', null ) ),
					'checks' => array(
						$notice( 'unauthenticated mode', 'The "unauthenticated mode" notice is shown' ),
						$value( '#iawmlf_archive_api_access', '', 'The access key field is empty' ),
						$value( '#iawmlf_archive_api_secret', '', 'The secret key field is empty' ),
					),
				),
				array(
					'id'     => 'wrong-keys',
					'do'     => 'Type wrong into both key fields and press Save Changes.',
					'expect' => 'The keys are now shown as ****************, the key fields say "The Archive.org API keys are invalid", and a notice says your credentials are invalid.',
					'when'   => array( $page, $option( 'iawmlf_archive_api_access', 'wrong' ) ),
					'checks' => array(
						$value( '#iawmlf_archive_api_access', $mask, 'The access key is shown masked' ),
						$value( '#iawmlf_archive_api_secret', $mask, 'The secret key is shown masked' ),
						$visible( '#invalid_api_creds', true, 'The key fields say the keys are invalid' ),
						$notice( 'credentials are invalid', 'A notice says your credentials are invalid' ),
						$option( 'iawmlf_archive_api_creds_valid', false, 'The keys are saved as not valid' ),
					),
				),
				array(
					'id'     => 'right-keys',
					'do'     => 'Type valid-access and valid-secret into the key fields and press Save Changes.',
					'expect' => 'The keys are masked, and there is no "invalid" or "unauthenticated mode" message any more.',
					'when'   => array( $page, $option( 'iawmlf_archive_api_access', 'valid-access' ) ),
					'checks' => array(
						$value( '#iawmlf_archive_api_access', $mask, 'The access key is shown masked' ),
						$no( 'unauthenticated mode', 'No "unauthenticated mode" notice' ),
						$no( 'credentials are invalid', 'No "credentials are invalid" notice' ),
						$visible( '#invalid_api_creds', false, 'The key fields do not say the keys are invalid' ),
						$option( 'iawmlf_archive_api_creds_valid', true, 'The keys are saved as valid' ),
					),
				),
				array(
					'id'     => 'widget-stats',
					'do'     => 'Open the WordPress Dashboard.',
					'expect' => 'The Wayback Link Fixer widget says Archive.org API services are online and shows Today\'s Snapshots 12/100000.',
					'link'   => 'admin:index.php',
					'when'   => array(
						array(
							'type'     => 'exists',
							'selector' => 'body.index-php',
						),
						$option( 'iawmlf_archive_api_creds_valid', true ),
					),
					'checks' => array(
						array(
							'type'     => 'text',
							'selector' => '#iawmlf_dashboard_widget',
							'has'      => 'services are online',
							'say'      => 'The widget says Archive.org is online',
						),
						array(
							'type'     => 'text',
							'selector' => '#iawmlf_dashboard_widget .iawmlf_dashboard-stats-ratio',
							'has'      => '12/100000',
							'say'      => 'Today\'s Snapshots shows 12/100000',
						),
					),
				),
				array(
					'id'     => 'hide-rows',
					'do'     => 'Back in Advanced Settings, untick "Enable Link Fixer" (do not save).',
					'expect' => 'The Link Fixer settings below it hide straight away.',
					'link'   => 'admin:admin.php?page=iawmlf_settings',
					'when'   => array( $page, $checked( '#iawmlf_process_links', false ), $option( 'iawmlf_process_links', true ) ),
					'checks' => array( $visible( $fixer_rows, false, 'The Link Fixer settings are hidden' ) ),
				),
				array(
					'id'     => 'check-only-icon',
					'do'     => 'Tick "Enable Link Fixer" again and change the fixer mode to "Check only".',
					'expect' => 'The Link Fixer settings show again, but the Link Icon row hides, because an icon only shows on replaced links.',
					'when'   => array(
						$page,
						$checked( '#iawmlf_process_links', true ),
						$value( '#iawmlf_fixer_option', 'check_only', '' ),
					),
					'checks' => array(
						$visible( $fixer_rows, true, 'The Link Fixer settings are showing' ),
						$visible( $icon_row, false, 'The Link Icon row is hidden' ),
					),
				),
				array(
					'id'     => 'replace-icon',
					'do'     => 'Change the fixer mode back to "Replace link".',
					'expect' => 'The Link Icon row shows again.',
					'when'   => array(
						$page,
						$checked( '#iawmlf_process_links', true ),
						$value( '#iawmlf_fixer_option', 'replace_link', '' ),
					),
					'checks' => array( $visible( $icon_row, true, 'The Link Icon row is showing' ) ),
				),
				array(
					'id'     => 'check-values',
					'do'     => 'Set the link check frequency to 7 days and the failures before broken to 5, and press Save Changes.',
					'expect' => 'After the page reloads the fields show 7 and 5.',
					'when'   => array( $page, $option( 'iawmlf_link_check_duration_in_days', '7' ) ),
					'checks' => array(
						$value( '#iawmlf_link_check_duration_in_days', '7', 'The check frequency shows 7' ),
						$value( '#iawmlf_failed_count', '5', 'The failures before broken shows 5' ),
						$option( 'iawmlf_failed_count', '5', 'Failures before broken is saved as 5' ),
					),
				),
				array(
					'id'     => 'exclusion-rule',
					'do'     => 'Type *example.org/private* into the link exclusion box, press Add, then press Save Changes.',
					'expect' => 'After the page reloads the rule is listed under link exclusions.',
					'when'   => array(
						$page,
						array(
							'type' => 'option',
							'name' => 'iawmlf_link_exclusions',
							'has'  => '*example.org/private*',
						),
					),
					'checks' => array(
						array(
							'type'     => 'exists',
							'selector' => '#iawmlf_excluded_links input[value="*example.org/private*"]',
							'say'      => 'The rule is listed',
						),
					),
				),
				array(
					'id'     => 'exclusion-encoded',
					'do'     => 'Add the rule *example.org/my%20page* (an address with a space written as %20) and press Save Changes.',
					'expect' => 'The rule is saved exactly as typed, %20 included, so it matches that address.',
					'when'   => array(
						$page,
						array(
							'type'     => 'exists',
							'selector' => '#iawmlf_excluded_links input[value*="example.org/my"]',
						),
					),
					'checks' => array(
						array(
							'type' => 'option',
							'name' => 'iawmlf_link_exclusions',
							'has'  => '*example.org/my%20page*',
							'say'  => 'The saved rule is *example.org/my%20page*',
						),
					),
				),
				array(
					'id'     => 'check-only-saved',
					'do'     => 'Change the fixer mode to "Check only" and press Save Changes.',
					'expect' => 'After the page reloads the Link Icon row is still hidden.',
					'when'   => array(
						$page,
						$option( 'iawmlf_fixer_option', 'check_only' ),
						$option( 'iawmlf_process_links', true ),
					),
					'checks' => array( $visible( $icon_row, false, 'The Link Icon row is hidden after the reload' ) ),
				),
				array(
					'id'     => 'fixer-off',
					'do'     => 'Untick "Enable Link Fixer" and press Save Changes.',
					'expect' => 'It stays unticked and the Link Fixer settings stay hidden after the page reloads.',
					'when'   => array( $page, $option( 'iawmlf_process_links', false ) ),
					'checks' => array(
						$checked( '#iawmlf_process_links', false ),
						$visible( $fixer_rows, false, 'The Link Fixer settings are hidden' ),
					),
				),
				array(
					'id'     => 'check-only-retick',
					'do'     => 'With "Check only" still selected, tick "Enable Link Fixer" again (do not save).',
					'expect' => 'The Link Fixer settings show again, but the Link Icon row stays hidden.',
					'when'   => array(
						$page,
						$option( 'iawmlf_process_links', false ),
						$checked( '#iawmlf_process_links', true ),
						$value( '#iawmlf_fixer_option', 'check_only', '' ),
					),
					'checks' => array(
						$visible( $fixer_rows, true, 'The Link Fixer settings are showing' ),
						$visible( $icon_row, false, 'The Link Icon row is still hidden' ),
					),
				),
			),
		);
	}
);
