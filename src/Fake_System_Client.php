<?php
/**
 * Fake system client, driven by harness options.
 *
 * @package LinkFixer_Harness
 */

namespace LinkFixer_Harness;

use Internet_Archive\Wayback_Machine_Link_Fixer\Wayback_Machine\System_Client;

defined( 'ABSPATH' ) || exit;

/**
 * Stands in for HTTP_System_Client (web.archive.org/save/status/system and /user).
 */
class Fake_System_Client implements System_Client {

	public const VALID_ACCESS_KEY = 'valid-access';
	public const VALID_SECRET_KEY = 'valid-secret';

	/**
	 * Online unless the harness option lfh_archive_online is "no".
	 *
	 * @return boolean
	 */
	public function is_online(): bool {
		$online = 'no' !== get_option( 'lfh_archive_online', 'yes' );
		Call_Log::record( 'system', 'is_online', '', array( 'returned' => $online ) );
		return $online;
	}

	/**
	 * Only the harness keys are valid.
	 *
	 * @param string $access_key The access key.
	 * @param string $secret_key The secret key.
	 *
	 * @return boolean
	 */
	public function is_valid_user( string $access_key, string $secret_key ): bool {
		$valid = self::VALID_ACCESS_KEY === $access_key && self::VALID_SECRET_KEY === $secret_key;
		Call_Log::record( 'system', 'is_valid_user', '', array( 'returned' => $valid ) );
		return $valid;
	}

	/**
	 * Fixed stats for the harness keys, null (as a 401 would give) for anything else.
	 *
	 * @param string $access_key The access key.
	 * @param string $secret_key The secret key.
	 *
	 * @return array{available:int, daily_captures:int, daily_captures_limit:int, processing:int}|null
	 */
	public function get_user_stats( string $access_key, string $secret_key ): ?array {
		$stats = self::VALID_ACCESS_KEY === $access_key && self::VALID_SECRET_KEY === $secret_key
			? array(
				'available'            => 1,
				'daily_captures'       => 12,
				'daily_captures_limit' => 100000,
				'processing'           => 0,
			)
			: null;

		Call_Log::record( 'system', 'get_user_stats', '', array( 'returned' => $stats ) );

		return $stats;
	}
}
