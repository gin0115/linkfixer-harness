<?php
/**
 * Server side checks the checklist panel asks about.
 *
 * @package LinkFixer_Harness
 */

namespace LinkFixer_Harness;

use Internet_Archive\Wayback_Machine_Link_Fixer\Link\Link;
use Internet_Archive\Wayback_Machine_Link_Fixer\Link\Link_Repository;

defined( 'ABSPATH' ) || exit;

/**
 * Evaluates checks with a "type" of option, action, link_row or calls.
 */
class Checks {

	public const SERVER_TYPES = array( 'option', 'action', 'link_row', 'calls' );

	/**
	 * Whether a check is answered on the server.
	 *
	 * @param array<string, mixed> $check The check.
	 *
	 * @return boolean
	 */
	public static function is_server( array $check ): bool {
		return in_array( $check['type'] ?? '', self::SERVER_TYPES, true );
	}

	/**
	 * Runs one server side check.
	 *
	 * option   - name, is (null means not set).
	 * action   - hook, status (one or a list), count or min, optional args and within (seconds from now).
	 * link_row - url, field (broken, excluded, archived, process, checks, last_code, redirect, message), is.
	 * calls    - method, url_has, min.
	 *
	 * @param array<string, mixed> $check The check.
	 *
	 * @return array{say:string, pass:bool, detail:string}
	 */
	public static function run( array $check ): array {
		try {
			return self::evaluate( $check );
		} catch ( \Throwable $e ) {
			return array(
				'say'    => (string) ( $check['say'] ?? $check['type'] ),
				'pass'   => false,
				'detail' => get_class( $e ) . ': ' . $e->getMessage(),
			);
		}
	}

	/**
	 * Evaluates a check.
	 *
	 * @param array<string, mixed> $check The check.
	 *
	 * @return array{say:string, pass:bool, detail:string}
	 */
	private static function evaluate( array $check ): array {
		switch ( $check['type'] ) {
			case 'option':
				$actual = get_option( $check['name'], null );
				return array(
					'say'    => $check['say'] ?? 'Setting ' . $check['name'] . ' is ' . wp_json_encode( $check['is'] ),
					'pass'   => self::same( $actual, $check['is'] ),
					'detail' => 'found ' . wp_json_encode( $actual ),
				);

			case 'action':
				$count = count( self::action_ids( $check ) );
				return array(
					'say'    => $check['say'] ?? sprintf( 'Background job "%1$s" (%2$s)', $check['hook'], implode( ' or ', (array) $check['status'] ) ),
					'pass'   => isset( $check['count'] ) ? (int) $check['count'] === $count : $count >= (int) ( $check['min'] ?? 1 ),
					'detail' => 'found ' . $count,
				);

			case 'link_row':
				$link   = ( new Link_Repository() )->find_by_url( (string) $check['url'] );
				$actual = null === $link ? null : self::link_field( $link, (string) $check['field'] );
				return array(
					'say'    => $check['say'] ?? sprintf( 'Link %1$s: %2$s is %3$s', $check['url'], $check['field'], wp_json_encode( $check['is'] ) ),
					'pass'   => null !== $link && self::same( $actual, $check['is'] ),
					'detail' => null === $link ? 'link row not found' : 'found ' . wp_json_encode( $actual ),
				);

			case 'calls':
				global $wpdb;
				$table = Call_Log::table();
				$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE method = %s AND url LIKE %s", $check['method'], '%' . $wpdb->esc_like( (string) $check['url_has'] ) . '%' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
				return array(
					'say'    => $check['say'] ?? sprintf( 'Archive.org was asked %1$s about %2$s', $check['method'], $check['url_has'] ),
					'pass'   => $count >= (int) ( $check['min'] ?? 1 ),
					'detail' => 'found ' . $count,
				);
		}

		return array(
			'say'    => 'Unknown check ' . $check['type'],
			'pass'   => false,
			'detail' => '',
		);
	}

	/**
	 * Action Scheduler action ids matching a check.
	 *
	 * The status may be a list: Action Scheduler's async runner can finish a job before anyone looks for it pending.
	 *
	 * @param array<string, mixed> $check hook, status, optional args and within.
	 *
	 * @return int[]
	 */
	private static function action_ids( array $check ): array {
		$ids = array();
		foreach ( (array) $check['status'] as $status ) {
			$query = array(
				'hook'     => $check['hook'],
				'status'   => $status,
				'per_page' => -1,
			);
			if ( isset( $check['args'] ) ) {
				$query['args'] = $check['args'];
			}
			$ids = array_merge( $ids, array_map( 'intval', (array) as_get_scheduled_actions( $query, 'ids' ) ) );
		}

		// ActionScheduler_DBStore::get_date() is the due date for a pending job and the run date for any other.
		if ( isset( $check['within'] ) ) {
			$from = time() - (int) $check['within'];
			$to   = time() + (int) $check['within'];
			$ids  = array_values(
				array_filter(
					$ids,
					static function ( $id ) use ( $from, $to ) {
						$time = \ActionScheduler::store()->get_date( $id )->getTimestamp();
						return $time >= $from && $time <= $to;
					}
				)
			);
		}

		return $ids;
	}

	/**
	 * Reads one field of a link.
	 *
	 * @param Link   $link  The link.
	 * @param string $field The field.
	 *
	 * @return mixed
	 */
	private static function link_field( Link $link, string $field ) {
		switch ( $field ) {
			case 'broken':
				return $link->is_broken();
			case 'excluded':
				return $link->is_excluded();
			case 'archived':
				return '' !== (string) $link->get_archived_href();
			case 'process':
				return $link->get_archive_process();
			case 'checks':
				return count( $link->get_checks() );
			case 'last_code':
				$last = $link->get_last_check();
				return null === $last ? null : (int) $last['http_code'];
			case 'redirect':
				return (string) $link->get_redirect_href();
			case 'message':
				return $link->get_message();
		}
		return null;
	}

	/**
	 * Loose comparison: booleans by truthiness, lists in any order, everything else as strings.
	 *
	 * @param mixed $actual   Found value.
	 * @param mixed $expected Expected value.
	 *
	 * @return boolean
	 */
	private static function same( $actual, $expected ): bool {
		if ( null === $expected ) {
			return null === $actual;
		}
		if ( is_bool( $expected ) ) {
			return (bool) $actual === $expected;
		}
		if ( is_array( $expected ) ) {
			if ( ! is_array( $actual ) ) {
				return false;
			}
			$a = array_map( 'strval', array_values( $actual ) );
			$b = array_map( 'strval', array_values( $expected ) );
			sort( $a );
			sort( $b );
			return $a === $b;
		}
		return (string) $actual === (string) $expected;
	}
}
