<?php
/**
 * MySQL functions the Link Fixer uses that Playground's SQLite database does not have.
 *
 * @package LinkFixer_Harness
 */

namespace LinkFixer_Harness;

defined( 'ABSPATH' ) || exit;

/**
 * Adds them to the SQLite connection, so the Link Fixer's queries work as they do on MySQL.
 */
class Sqlite {

	/**
	 * Adds the functions when WordPress runs on the SQLite database integration.
	 *
	 * @return void
	 */
	public static function init(): void {
		global $wpdb;
		if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_driver' ) ) {
			return;
		}

		$driver = $wpdb->get_driver();
		if ( ! method_exists( $driver, 'get_connection' ) ) {
			return;
		}
		$pdo = $driver->get_connection()->get_pdo();

		// JSON_LENGTH is used by Link_Repository's ORDER_DATE_DESC order.
		$callback = array( self::class, 'json_length' );
		if ( $pdo instanceof \Pdo\Sqlite ) {
			$pdo->createFunction( 'JSON_LENGTH', $callback );
		} else {
			$pdo->sqliteCreateFunction( 'JSON_LENGTH', $callback );
		}
	}

	/**
	 * MySQL's JSON_LENGTH() without a path: the number of items in an array or object, 1 for a scalar.
	 *
	 * @param mixed $json The JSON.
	 *
	 * @return integer|null
	 */
	public static function json_length( $json ): ?int {
		if ( null === $json ) {
			return null;
		}

		$data = json_decode( (string) $json, true );
		if ( JSON_ERROR_NONE !== json_last_error() ) {
			return null;
		}

		return is_array( $data ) ? count( $data ) : 1;
	}
}
