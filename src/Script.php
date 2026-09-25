<?php
/**
 * Reads the scripted Archive.org behaviour out of a link URL.
 *
 * @package LinkFixer_Harness
 */

namespace LinkFixer_Harness;

defined( 'ABSPATH' ) || exit;

/**
 * URL script, for example:
 * https://harness.test/l01/check/404,200/archive/yes/save/offline,ok/status/pending,success/final/moved
 *
 * check   - check_single() result per call: an HTTP code, or "offline" (throws Service_Offline_Exception).
 * archive - get_latest_snapshot() result per call: "yes" or "none".
 * save    - create_snapshot() result per call: "ok", "offline", "limit" or "error".
 * status  - get_snapshot_status() result per call: "pending", "success", "error" or "no-access".
 * final   - get_final_url() result per call: "same" or "moved".
 *
 * The call count for that method and URL picks the value; the last value repeats.
 */
class Script {

	public const HOST = 'harness.test';

	public const DEFAULTS = array(
		'check'   => array( '200' ),
		'archive' => array( 'yes' ),
		'save'    => array( 'ok' ),
		'status'  => array( 'success' ),
		'final'   => array( 'same' ),
	);

	/**
	 * Whether the URL is on the harness host.
	 *
	 * @param string $url The URL.
	 *
	 * @return boolean
	 */
	public static function is_harness_url( string $url ): bool {
		return self::HOST === strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
	}

	/**
	 * Parses the script out of a URL.
	 *
	 * @param string $url The URL.
	 *
	 * @return array<string, string[]>
	 */
	public static function parse( string $url ): array {
		$script = self::DEFAULTS;

		if ( ! self::is_harness_url( $url ) ) {
			return $script;
		}

		$segments = array_values( array_filter( explode( '/', (string) wp_parse_url( $url, PHP_URL_PATH ) ), 'strlen' ) );
		$count    = count( $segments );

		for ( $i = 0; $i < $count - 1; $i++ ) {
			$key = strtolower( $segments[ $i ] );
			if ( array_key_exists( $key, self::DEFAULTS ) ) {
				$values = array_values( array_filter( array_map( 'trim', explode( ',', strtolower( $segments[ $i + 1 ] ) ) ), 'strlen' ) );
				if ( ! empty( $values ) ) {
					$script[ $key ] = $values;
				}
				++$i;
			}
		}

		return $script;
	}

	/**
	 * The value the next call of a method will get for a URL.
	 *
	 * @param string $key    Script key (check, archive, save, status, final).
	 * @param string $method The client method, used to count previous calls.
	 * @param string $url    The URL.
	 *
	 * @return string
	 */
	public static function current( string $key, string $method, string $url ): string {
		$values = self::parse( $url )[ $key ];
		$index  = min( Call_Log::count_calls( $method, $url ), count( $values ) - 1 );
		return $values[ $index ];
	}
}
