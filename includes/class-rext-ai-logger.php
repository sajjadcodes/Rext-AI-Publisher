<?php
/**
 * Rext AI Logger Class
 *
 * Handles activity logging for the Rext AI plugin.
 *
 * @package Rext_AI
 * @since 1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Rext_AI_Logger
 *
 * Manages logging of plugin activities to the database.
 */
class Rext_AI_Logger {

	/**
	 * Log levels.
	 */
	const LEVEL_DEBUG   = 'debug';
	const LEVEL_INFO    = 'info';
	const LEVEL_WARNING = 'warning';
	const LEVEL_ERROR   = 'error';

	/**
	 * Action mapping for auto-detection.
	 *
	 * @var array
	 */
	private static $action_map = array(
		'Post created'              => 'post.create',
		'Post updated'              => 'post.update',
		'Post deleted'              => 'post.delete',
		'Post trashed'              => 'post.trash',
		'Post published'            => 'post.publish',
		'Post status changed'       => 'post.status_change',
		'Media uploaded'            => 'media.upload',
		'Media sideloaded'          => 'media.sideload',
		'Category created'          => 'category.create',
		'Tag created'               => 'tag.create',
		'Authentication successful' => 'auth.success',
		'Authentication failed'     => 'auth.fail',
		'API key regenerated'       => 'api_key.regenerate',
		'Plugin activated'          => 'plugin.activate',
		'Plugin deactivated'        => 'plugin.deactivate',
		'Settings updated'          => 'settings.update',
		'Connection established'    => 'connection.establish',
		'Connection verified'       => 'connection.verify',
		'Rate limit exceeded'       => 'rate_limit.exceeded',
	);

	/**
	 * Log retention period in days.
	 *
	 * @var int
	 */
	private static $retention_days = 30;

	/**
	 * Log a message.
	 *
	 * @param string $message The log message.
	 * @param string $level   The log level.
	 * @param array  $data    Optional additional data.
	 * @param string $action  Optional action code (auto-detected if not provided).
	 * @return int|false The log entry ID or false on failure.
	 */
	public static function log( $message, $level = self::LEVEL_INFO, $data = array(), $action = '' ) {
		// Skip debug logs unless WP_DEBUG is enabled.
		if ( self::LEVEL_DEBUG === $level && ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) ) {
			return false;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'rext_ai_logs';

		// Auto-detect action if not provided.
		if ( empty( $action ) ) {
			$action = self::detect_action( $message );
		}

		// Get client IP.
		$ip_address = self::get_client_ip();

		// Prepare data for storage.
		$data_json = ! empty( $data ) ? wp_json_encode( $data ) : null;

		// Insert log entry.
		$result = $wpdb->insert(
			$table_name,
			array(
				'action'     => sanitize_text_field( $action ),
				'message'    => sanitize_text_field( $message ),
				'data'       => $data_json,
				'level'      => sanitize_key( $level ),
				'ip_address' => sanitize_text_field( $ip_address ),
				'created_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		// Random cleanup (1% chance).
		if ( wp_rand( 1, 100 ) === 1 ) {
			self::cleanup_old_logs();
		}

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Log info level message.
	 *
	 * @param string $message The log message.
	 * @param array  $data    Optional additional data.
	 * @param string $action  Optional action code.
	 * @return int|false
	 */
	public static function info( $message, $data = array(), $action = '' ) {
		return self::log( $message, self::LEVEL_INFO, $data, $action );
	}

	/**
	 * Log warning level message.
	 *
	 * @param string $message The log message.
	 * @param array  $data    Optional additional data.
	 * @param string $action  Optional action code.
	 * @return int|false
	 */
	public static function warning( $message, $data = array(), $action = '' ) {
		return self::log( $message, self::LEVEL_WARNING, $data, $action );
	}

	/**
	 * Log error level message.
	 *
	 * @param string $message The log message.
	 * @param array  $data    Optional additional data.
	 * @param string $action  Optional action code.
	 * @return int|false
	 */
	public static function error( $message, $data = array(), $action = '' ) {
		return self::log( $message, self::LEVEL_ERROR, $data, $action );
	}

	/**
	 * Log debug level message.
	 *
	 * @param string $message The log message.
	 * @param array  $data    Optional additional data.
	 * @param string $action  Optional action code.
	 * @return int|false
	 */
	public static function debug( $message, $data = array(), $action = '' ) {
		return self::log( $message, self::LEVEL_DEBUG, $data, $action );
	}

	/**
	 * Auto-detect action from message.
	 *
	 * @param string $message The log message.
	 * @return string The detected action code.
	 */
	private static function detect_action( $message ) {
		foreach ( self::$action_map as $pattern => $action ) {
			if ( stripos( $message, $pattern ) !== false ) {
				return $action;
			}
		}
		return 'general';
	}

	/**
	 * Get client IP address.
	 *
	 * @return string The client IP address.
	 */
	private static function get_client_ip() {
		$ip_keys = array(
			'HTTP_CF_CONNECTING_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_REAL_IP',
			'REMOTE_ADDR',
		);

		foreach ( $ip_keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
				// Handle comma-separated IPs (X-Forwarded-For).
				if ( strpos( $ip, ',' ) !== false ) {
					$parts = explode( ',', $ip );
					$ip    = trim( $parts[0] );
				}
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}

		return '0.0.0.0';
	}

	/**
	 * Get logs with pagination and filtering.
	 *
	 * @param array $args Query arguments.
	 * @return array Array of log entries.
	 */
	public static function get_logs( $args = array() ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'rext_ai_logs';

		$defaults = array(
			'per_page' => 20,
			'page'     => 1,
			'level'    => '',
			'action'   => '',
			'search'   => '',
			'orderby'  => 'created_at',
			'order'    => 'DESC',
		);

		$args = wp_parse_args( $args, $defaults );

		// Sanitize pagination.
		$per_page = absint( $args['per_page'] );
		$page     = absint( $args['page'] );
		if ( $per_page < 1 ) {
			$per_page = 20;
		}
		if ( $page < 1 ) {
			$page = 1;
		}

		// Build WHERE clause.
		$where  = array( '1=1' );
		$values = array();

		if ( ! empty( $args['level'] ) ) {
			$where[]  = 'level = %s';
			$values[] = sanitize_key( $args['level'] );
		}

		if ( ! empty( $args['action'] ) ) {
			$where[]  = 'action = %s';
			$values[] = sanitize_text_field( $args['action'] );
		}

		if ( ! empty( $args['search'] ) ) {
			$where[]     = '(message LIKE %s OR data LIKE %s)';
			$search_term = '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
			$values[]    = $search_term;
			$values[]    = $search_term;
		}

		$where_clause = implode( ' AND ', $where );

		// Sanitize orderby.
		$allowed_orderby = array( 'id', 'action', 'level', 'created_at' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
		$order           = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

		// Calculate offset.
		$offset = ( $page - 1 ) * $per_page;

		// Add LIMIT/OFFSET values.
		$values[] = $per_page;
		$values[] = $offset;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name and orderby are safe.
		$query = $wpdb->prepare(
			"SELECT * FROM `{$table_name}` WHERE {$where_clause} ORDER BY `{$orderby}` {$order} LIMIT %d OFFSET %d",
			$values
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared above.
		return $wpdb->get_results( $query );
	}

	/**
	 * Get total log count.
	 *
	 * @param array $args Filter arguments.
	 * @return int Total count.
	 */
	public static function get_logs_count( $args = array() ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'rext_ai_logs';

		$where  = array( '1=1' );
		$values = array();

		if ( ! empty( $args['level'] ) ) {
			$where[]  = 'level = %s';
			$values[] = sanitize_key( $args['level'] );
		}

		if ( ! empty( $args['action'] ) ) {
			$where[]  = 'action = %s';
			$values[] = sanitize_text_field( $args['action'] );
		}

		if ( ! empty( $args['search'] ) ) {
			$where[]     = '(message LIKE %s OR data LIKE %s)';
			$search_term = '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
			$values[]    = $search_term;
			$values[]    = $search_term;
		}

		$where_clause = implode( ' AND ', $where );

		if ( ! empty( $values ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$query = $wpdb->prepare( "SELECT COUNT(*) FROM `{$table_name}` WHERE {$where_clause}", $values );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$query = "SELECT COUNT(*) FROM `{$table_name}` WHERE 1=1";
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->get_var( $query );
	}

	/**
	 * Get unique actions for filtering.
	 *
	 * @return array List of unique actions.
	 */
	public static function get_unique_actions() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'rext_ai_logs';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix.
		return $wpdb->get_col( "SELECT DISTINCT action FROM `{$table_name}` ORDER BY action ASC" );
	}

	/**
	 * Cleanup old log entries.
	 *
	 * @return int Number of deleted rows.
	 */
	public static function cleanup_old_logs() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'rext_ai_logs';

		$cutoff_date = gmdate( 'Y-m-d H:i:s', strtotime( '-' . self::$retention_days . ' days' ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->query(
			$wpdb->prepare( "DELETE FROM `{$table_name}` WHERE created_at < %s", $cutoff_date )
		);
	}

	/**
	 * Clear all logs.
	 *
	 * @return int|false Number of deleted rows or false on failure.
	 */
	public static function clear_all_logs() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'rext_ai_logs';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix.
		return $wpdb->query( "TRUNCATE TABLE `{$table_name}`" );
	}

	/**
	 * Export logs to CSV format.
	 *
	 * @param array $args Filter arguments.
	 * @return string CSV content.
	 */
	public static function export_to_csv( $args = array() ) {
		$args['per_page'] = 999999;
		$args['page']     = 1;
		$logs             = self::get_logs( $args );

		$output   = array();
		$output[] = array( 'ID', 'Action', 'Message', 'Level', 'IP Address', 'Data', 'Created At' );

		foreach ( $logs as $log ) {
			$output[] = array(
				$log->id,
				$log->action,
				$log->message,
				$log->level,
				$log->ip_address,
				$log->data,
				$log->created_at,
			);
		}

		$csv = '';
		foreach ( $output as $row ) {
			$csv .= '"' . implode(
				'","',
				array_map(
					function ( $cell ) {
						return str_replace( '"', '""', (string) ( $cell ?? '' ) );
					},
					$row
				)
			) . '"' . "\n";
		}

		return $csv;
	}

	/**
	 * Get log statistics.
	 *
	 * @return array Log statistics.
	 */
	public static function get_stats() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'rext_ai_logs';

		$stats = array(
			'total'    => 0,
			'by_level' => array(),
			'today'    => 0,
			'week'     => 0,
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$stats['total'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table_name}`" );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$levels = $wpdb->get_results( "SELECT level, COUNT(*) as count FROM `{$table_name}` GROUP BY level" );
		foreach ( $levels as $level ) {
			$stats['by_level'][ $level->level ] = (int) $level->count;
		}

		$today          = gmdate( 'Y-m-d 00:00:00' );
		$stats['today'] = (int) $wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare( "SELECT COUNT(*) FROM `{$table_name}` WHERE created_at >= %s", $today )
		);

		$week_ago      = gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) );
		$stats['week'] = (int) $wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare( "SELECT COUNT(*) FROM `{$table_name}` WHERE created_at >= %s", $week_ago )
		);

		return $stats;
	}
}
