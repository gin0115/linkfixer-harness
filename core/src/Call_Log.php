<?php
/**
 * Records every call made to the fake Archive.org clients.
 *
 * @package LinkFixer_Harness
 */

namespace LinkFixer_Harness;

defined( 'ABSPATH' ) || exit;

/**
 * Call log, stored in its own table so concurrent requests cannot overwrite each other.
 */
class Call_Log {

	private const DB_VERSION     = '1';
	private const DB_VERSION_KEY = 'lfh_db_version';

	/**
	 * Random id for the current PHP request.
	 *
	 * @var string|null
	 */
	private static $request_id = null;

	/**
	 * The Action Scheduler action currently running, if any.
	 *
	 * @var array<string, mixed>
	 */
	private static $action = array();

	/**
	 * Calls recorded during this request, sent back as a REST response header.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private static $request_calls = array();

	/**
	 * The call log table name.
	 *
	 * @return string
	 */
	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'lfh_calls';
	}

	/**
	 * Creates the table.
	 *
	 * @return void
	 */
	public static function install(): void {
		global $wpdb;

		$table   = self::table();
		$charset = $wpdb->get_charset_collate();
		$sql     = "CREATE TABLE $table (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			request_id varchar(32) NOT NULL,
			created double NOT NULL,
			context longtext,
			client varchar(40) NOT NULL,
			method varchar(40) NOT NULL,
			url longtext NOT NULL,
			outcome longtext,
			PRIMARY KEY  (id)
		) $charset;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( self::DB_VERSION_KEY, self::DB_VERSION );
	}

	/**
	 * Creates the table if the plugin was loaded without being activated.
	 *
	 * @return void
	 */
	public static function maybe_install(): void {
		if ( self::DB_VERSION !== get_option( self::DB_VERSION_KEY ) ) {
			self::install();
		}
	}

	/**
	 * Registers the hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'action_scheduler_begin_execute', array( self::class, 'begin_action' ), 10, 1 );
		add_action( 'action_scheduler_after_execute', array( self::class, 'end_action' ) );
		add_action( 'action_scheduler_failed_execution', array( self::class, 'end_action' ) );
		add_filter( 'rest_post_dispatch', array( self::class, 'add_rest_header' ), 10, 3 );
	}

	/**
	 * The id shared by every call in this request.
	 *
	 * @return string
	 */
	public static function request_id(): string {
		if ( null === self::$request_id ) {
			self::$request_id = wp_generate_password( 12, false );
		}
		return self::$request_id;
	}

	/**
	 * Notes which Action Scheduler action is running.
	 *
	 * @param integer|string $action_id The action id.
	 *
	 * @return void
	 */
	public static function begin_action( $action_id ): void {
		try {
			$action       = \ActionScheduler::store()->fetch_action( (string) $action_id );
			self::$action = array(
				'type'      => 'action',
				'action_id' => (int) $action_id,
				'hook'      => $action->get_hook(),
				'args'      => $action->get_args(),
			);
		} catch ( \Throwable $e ) {
			self::$action = array(
				'type'      => 'action',
				'action_id' => (int) $action_id,
			);
		}
	}

	/**
	 * Clears the running action.
	 *
	 * @return void
	 */
	public static function end_action(): void {
		self::$action = array();
	}

	/**
	 * Works out what triggered the current call.
	 *
	 * @return array<string, mixed>
	 */
	private static function context(): array {
		if ( ! empty( self::$action ) ) {
			return self::$action;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			global $wp;
			return array(
				'type'  => 'rest',
				'route' => isset( $wp->query_vars['rest_route'] ) ? (string) $wp->query_vars['rest_route'] : '',
			);
		}

		if ( wp_doing_cron() ) {
			return array( 'type' => 'cron' );
		}

		if ( wp_doing_ajax() ) {
			return array(
				'type'   => 'ajax',
				'action' => isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			);
		}

		if ( is_admin() ) {
			return array(
				'type' => 'admin',
				'page' => isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			);
		}

		return array( 'type' => 'front' );
	}

	/**
	 * Records a call.
	 *
	 * @param string               $client  Which fake client (link_checker, snapshot, system).
	 * @param string               $method  The client method.
	 * @param string               $url     The URL the call was about.
	 * @param array<string, mixed> $outcome What was returned or thrown.
	 *
	 * @return void
	 */
	public static function record( string $client, string $method, string $url, array $outcome ): void {
		global $wpdb;

		$row = array(
			'request_id' => self::request_id(),
			'created'    => microtime( true ),
			'context'    => wp_json_encode( self::context() ),
			'client'     => $client,
			'method'     => $method,
			'url'        => $url,
			'outcome'    => wp_json_encode( $outcome ),
		);

		$wpdb->insert( self::table(), $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		$row['id']             = (int) $wpdb->insert_id;
		self::$request_calls[] = self::shape( (object) $row );
	}

	/**
	 * How many times a method has already been called for a URL.
	 *
	 * @param string $method The client method.
	 * @param string $url    The URL.
	 *
	 * @return integer
	 */
	public static function count_calls( string $method, string $url ): int {
		global $wpdb;
		$table = self::table();
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE method = %s AND url = %s", $method, $url ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Calls newer than an id, oldest first.
	 *
	 * @param integer $since_id Only return calls with a higher id.
	 * @param integer $limit    Maximum rows.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function since( int $since_id = 0, int $limit = 500 ): array {
		global $wpdb;
		$table = self::table();
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE id > %d ORDER BY id ASC LIMIT %d", $since_id, $limit ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		return array_map( array( self::class, 'shape' ), (array) $rows );
	}

	/**
	 * The highest call id so far.
	 *
	 * @return integer
	 */
	public static function last_id(): int {
		global $wpdb;
		$table = self::table();
		return (int) $wpdb->get_var( "SELECT MAX(id) FROM $table" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Deletes the calls for the given URLs, which also restarts their scripts.
	 *
	 * @param string[] $urls The URLs.
	 *
	 * @return void
	 */
	public static function clear_urls( array $urls ): void {
		global $wpdb;
		foreach ( $urls as $url ) {
			$wpdb->delete( self::table(), array( 'url' => $url ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
	}

	/**
	 * Adds the calls made during a REST request as a response header.
	 *
	 * @param \WP_HTTP_Response $response The response.
	 * @param \WP_REST_Server   $server   The server.
	 * @param \WP_REST_Request  $request  The request.
	 *
	 * @return \WP_HTTP_Response
	 */
	public static function add_rest_header( $response, $server, $request ) {
		if ( $response instanceof \WP_HTTP_Response ) {
			$response->header( 'X-LFH-Request', self::request_id() );
			$response->header( 'X-LFH-Calls', (string) wp_json_encode( self::$request_calls ) );
		}
		return $response;
	}

	/**
	 * Turns a table row into an array.
	 *
	 * @param object $row The row.
	 *
	 * @return array<string, mixed>
	 */
	public static function shape( $row ): array {
		return array(
			'id'         => (int) $row->id,
			'request_id' => (string) $row->request_id,
			'created'    => (float) $row->created,
			'context'    => json_decode( (string) $row->context, true ),
			'client'     => (string) $row->client,
			'method'     => (string) $row->method,
			'url'        => (string) $row->url,
			'outcome'    => json_decode( (string) $row->outcome, true ),
		);
	}
}
