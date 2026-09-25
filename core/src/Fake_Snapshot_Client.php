<?php
/**
 * Fake snapshot client, driven by the link URL script.
 *
 * @package LinkFixer_Harness
 */

namespace LinkFixer_Harness;

use Exception;
use Internet_Archive\Wayback_Machine_Link_Fixer\Wayback_Machine\Snapshot_Client;
use Internet_Archive\Wayback_Machine_Link_Fixer\Wayback_Machine\Exception\Exceeded_Snapshot_Limit_Exception;
use Internet_Archive\Wayback_Machine_Link_Fixer\Wayback_Machine\Exception\Service_Offline_Exception;

defined( 'ABSPATH' ) || exit;

/**
 * Stands in for HTTP_Snapshot_Client (archive.org/wayback/available, web.archive.org/save).
 */
class Fake_Snapshot_Client implements Snapshot_Client {

	private const TIMESTAMP  = '20240101000000';
	private const JOB_PREFIX = 'lfh-';

	/**
	 * The archive URL the fake returns for a URL.
	 *
	 * @param string $url The URL.
	 *
	 * @return string
	 */
	public static function archive_url( string $url ): string {
		return 'https://web.archive.org/web/' . self::TIMESTAMP . '/' . $url;
	}

	/**
	 * Whether a snapshot exists.
	 *
	 * @param string $url The URL.
	 *
	 * @return boolean
	 */
	public function has_snapshot( string $url ): bool {
		return null !== $this->get_latest_snapshot( $url );
	}

	/**
	 * Returns the scripted latest snapshot.
	 *
	 * @param string $url The URL.
	 *
	 * @return array{status:int, available:bool, url:string, timestamp:string}|null
	 */
	public function get_latest_snapshot( string $url ): ?array {
		return $this->snapshot( 'get_latest_snapshot', $url );
	}

	/**
	 * Returns the scripted closest snapshot.
	 *
	 * @param string    $url  The URL.
	 * @param \DateTime $date Unused.
	 *
	 * @return array{status:int, available:bool, url:string, timestamp:string}|null
	 */
	public function get_closest_snapshot( string $url, \DateTime $date ): ?array {
		return $this->snapshot( 'get_closest_snapshot', $url );
	}

	/**
	 * Builds a snapshot result from the "archive" script.
	 *
	 * @param string $method The client method.
	 * @param string $url    The URL.
	 *
	 * @return array{status:int, available:bool, url:string, timestamp:string}|null
	 */
	private function snapshot( string $method, string $url ): ?array {
		$value = Script::current( 'archive', $method, $url );

		$result = 'yes' === $value
			? array(
				'status'    => 200,
				'available' => true,
				'url'       => self::archive_url( $url ),
				'timestamp' => self::TIMESTAMP,
			)
			: null;

		Call_Log::record( 'snapshot', $method, $url, array( 'returned' => $result ) );

		return $result;
	}

	/**
	 * Returns a job id, or throws what the "save" script says.
	 *
	 * @param string $url The URL.
	 *
	 * @return string
	 *
	 * @throws Service_Offline_Exception         When scripted offline.
	 * @throws Exceeded_Snapshot_Limit_Exception When scripted limit.
	 * @throws Exception                         When scripted error.
	 */
	public function create_snapshot( string $url ): string {
		$value = Script::current( 'save', 'create_snapshot', $url );

		switch ( $value ) {
			case 'offline':
				Call_Log::record( 'snapshot', 'create_snapshot', $url, array( 'threw' => 'Service_Offline_Exception' ) );
				throw Service_Offline_Exception::create( 'Response:503' );

			case 'limit':
				Call_Log::record( 'snapshot', 'create_snapshot', $url, array( 'threw' => 'Exceeded_Snapshot_Limit_Exception' ) );
				throw Exceeded_Snapshot_Limit_Exception::create();

			case 'error':
				Call_Log::record( 'snapshot', 'create_snapshot', $url, array( 'threw' => 'Exception' ) );
				throw new Exception( 'Harness: scripted save error' );
		}

		$job_id = self::JOB_PREFIX . rtrim( strtr( base64_encode( $url ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		Call_Log::record( 'snapshot', 'create_snapshot', $url, array( 'returned' => $job_id ) );

		return $job_id;
	}

	/**
	 * Returns the status the "status" script says for the job's URL.
	 *
	 * @param string $ref_code The job id.
	 *
	 * @return array<string, string>
	 *
	 * @throws Exception When the job id was not made by this fake.
	 */
	public function get_snapshot_status( string $ref_code ): array {
		if ( 0 !== strpos( $ref_code, self::JOB_PREFIX ) ) {
			throw new Exception( 'Invalid snapshot job id' );
		}

		$url   = (string) base64_decode( strtr( substr( $ref_code, strlen( self::JOB_PREFIX ) ), '-_', '+/' ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$value = Script::current( 'status', 'get_snapshot_status', $url );

		$result = array(
			'job_id'  => $ref_code,
			'status'  => in_array( $value, array( 'error', 'no-access' ), true ) ? 'error' : $value,
			'message' => in_array( $value, array( 'error', 'no-access' ), true ) ? 'Harness: scripted ' . $value : '',
		);

		if ( 'no-access' === $value ) {
			$result['status_ext'] = 'error:no-access';
		}

		Call_Log::record( 'snapshot', 'get_snapshot_status', $url, array( 'returned' => $result ) );

		return $result;
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
