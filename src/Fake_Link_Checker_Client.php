<?php
/**
 * Fake link checker, driven by the link URL script.
 *
 * @package LinkFixer_Harness
 */

namespace LinkFixer_Harness;

use Internet_Archive\Wayback_Machine_Link_Fixer\Wayback_Machine\Link_Checker_Client;
use Internet_Archive\Wayback_Machine_Link_Fixer\Wayback_Machine\Exception\Service_Offline_Exception;

defined( 'ABSPATH' ) || exit;

/**
 * Stands in for HTTP_Link_Checker_Client (iabot-api.archive.org/livewebcheck).
 */
class Fake_Link_Checker_Client implements Link_Checker_Client {

	/**
	 * Returns the URL, or the URL with "-moved" appended when scripted "moved".
	 *
	 * @param string $url The URL.
	 *
	 * @return string
	 */
	public function get_final_url( string $url ): string {
		$final  = Script::current( 'final', 'get_final_url', $url );
		$result = 'moved' === $final ? untrailingslashit( $url ) . '-moved' : $url;

		Call_Log::record( 'link_checker', 'get_final_url', $url, array( 'returned' => $result ) );

		return $result;
	}

	/**
	 * Returns the scripted HTTP code, or throws when scripted "offline".
	 *
	 * @param string              $url               The URL.
	 * @param array<string,mixed> $additional_params Unused.
	 *
	 * @return integer
	 *
	 * @throws Service_Offline_Exception When scripted offline.
	 */
	public function check_single( string $url, array $additional_params = array() ): int {
		$value = Script::current( 'check', 'check_single', $url );

		if ( 'offline' === $value ) {
			Call_Log::record( 'link_checker', 'check_single', $url, array( 'threw' => 'Service_Offline_Exception' ) );
			throw Service_Offline_Exception::create( 'Harness: link checker scripted offline' );
		}

		$code = (int) $value;
		Call_Log::record( 'link_checker', 'check_single', $url, array( 'returned' => $code ) );

		return $code;
	}

	/**
	 * Same as the real client: reads the shared online status.
	 *
	 * @return boolean
	 */
	public function is_online(): bool {
		return iawmlf_is_archive_api_online();
	}
}
