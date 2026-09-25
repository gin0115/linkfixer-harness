<?php
/**
 * Plugin Name: Link Fixer Harness
 * Description: Test harness for the Internet Archive Wayback Machine Link Fixer. Fake Archive.org clients driven by the link URL, seeded scenarios, a call log and a floating checklist panel.
 * Version: 0.1.0
 * Requires PHP: 7.4
 * Requires Plugins: internet-archive-wayback-machine-link-fixer
 * Author: Glynn Quelch
 * License: GPL-2.0-or-later
 *
 * @package LinkFixer_Harness
 */

namespace LinkFixer_Harness;

defined( 'ABSPATH' ) || exit;

define( 'LFH_VERSION', '0.1.0' );
define( 'LFH_PATH', plugin_dir_path( __FILE__ ) );
define( 'LFH_URL', plugin_dir_url( __FILE__ ) );

require_once LFH_PATH . 'src/Call_Log.php';
require_once LFH_PATH . 'src/Script.php';
require_once LFH_PATH . 'src/Scenario.php';
require_once LFH_PATH . 'src/Scenario_Mixed_Links.php';
require_once LFH_PATH . 'src/Scenario_Display_Modes.php';
require_once LFH_PATH . 'src/Scenarios.php';
require_once LFH_PATH . 'src/Rest.php';
require_once LFH_PATH . 'src/Widget.php';

register_activation_hook( __FILE__, array( Call_Log::class, 'install' ) );

add_action( 'plugins_loaded', __NAMESPACE__ . '\\boot', 20 );

/**
 * Boots the harness once the Link Fixer has loaded.
 *
 * @return void
 */
function boot(): void {
	// The fakes implement the Link Fixer's interfaces, so it must be loaded.
	if ( ! interface_exists( '\Internet_Archive\Wayback_Machine_Link_Fixer\Wayback_Machine\Snapshot_Client' ) ) {
		add_action(
			'admin_notices',
			static function () {
				echo '<div class="notice notice-error"><p>Link Fixer Harness: the Internet Archive Wayback Machine Link Fixer plugin is not active.</p></div>';
			}
		);
		return;
	}

	Call_Log::maybe_install();

	require_once LFH_PATH . 'src/Fake_Link_Checker_Client.php';
	require_once LFH_PATH . 'src/Fake_Snapshot_Client.php';
	require_once LFH_PATH . 'src/Fake_System_Client.php';

	if ( 'fake' === get_client_mode() ) {
		add_filter( 'iawmlf_snapshot_client', static fn() => new Fake_Snapshot_Client(), 999 );
		add_filter( 'iawmlf_link_checker_client', static fn() => new Fake_Link_Checker_Client(), 999 );
		add_filter( 'iawmlf_system_client', static fn() => new Fake_System_Client(), 999 );
	}

	Call_Log::init();
	Scenarios::init();
	Rest::init();
	Widget::init();
}

/**
 * Whether the fake clients (fake) or the plugin's real HTTP clients (live) are used.
 *
 * @return string
 */
function get_client_mode(): string {
	return 'live' === get_option( 'lfh_client_mode', 'fake' ) ? 'live' : 'fake';
}
