<?php
/**
 * Plugin Name: Rext AI Publisher
 * Plugin URI: https://rext.ai
 * Description: Seamlessly publish content from Rext AI directly to your WordPress site. Supports all major SEO plugins.
 * Version: 1.0.0
 * Author: Rext AI Team
 * Author URI: https://rext.ai
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: rext-ai
 * Domain Path: /languages
 * Requires at least: 5.6
 * Requires PHP: 7.4
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'REXT_AI_VERSION', '1.0.0' );
define( 'REXT_AI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'REXT_AI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'REXT_AI_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'REXT_AI_API_NAMESPACE', 'rext-ai/v1' );
define( 'REXT_AI_MIN_WP_VERSION', '5.6' );
define( 'REXT_AI_MIN_PHP_VERSION', '7.4' );

/**
 * Main Rext AI Plugin Class.
 *
 * @since 1.0.0
 */
final class Rext_AI {

	/**
	 * Single instance of the class.
	 *
	 * @var Rext_AI|null
	 */
	private static $instance = null;

	/**
	 * Whether requirements are met.
	 *
	 * @var bool
	 */
	private $requirements_met = true;

	/**
	 * Plugin components.
	 *
	 * @var Rext_AI_API|null
	 */
	public $api;

	/**
	 * Auth component.
	 *
	 * @var Rext_AI_Auth|null
	 */
	public $auth;

	/**
	 * Posts component.
	 *
	 * @var Rext_AI_Posts|null
	 */
	public $posts;

	/**
	 * Media component.
	 *
	 * @var Rext_AI_Media|null
	 */
	public $media;

	/**
	 * SEO component.
	 *
	 * @var Rext_AI_SEO|null
	 */
	public $seo;

	/**
	 * Admin component.
	 *
	 * @var Rext_AI_Admin|null
	 */
	public $admin;

	/**
	 * Get single instance.
	 *
	 * @return Rext_AI
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->check_requirements();

		if ( ! $this->requirements_met ) {
			return;
		}

		$this->includes();
		$this->init_hooks();
	}

	/**
	 * Check plugin requirements.
	 */
	private function check_requirements() {
		if ( version_compare( PHP_VERSION, REXT_AI_MIN_PHP_VERSION, '<' ) ) {
			$this->requirements_met = false;
			add_action( 'admin_notices', array( $this, 'php_version_notice' ) );
			return;
		}

		global $wp_version;
		if ( version_compare( $wp_version, REXT_AI_MIN_WP_VERSION, '<' ) ) {
			$this->requirements_met = false;
			add_action( 'admin_notices', array( $this, 'wp_version_notice' ) );
		}
	}

	/**
	 * Display PHP version notice.
	 */
	public function php_version_notice() {
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			sprintf(
				/* translators: 1: Required PHP version, 2: Current PHP version. */
				esc_html__( 'Rext AI requires PHP %1$s or higher. Your current version is %2$s.', 'rext-ai' ),
				esc_html( REXT_AI_MIN_PHP_VERSION ),
				esc_html( PHP_VERSION )
			)
		);
	}

	/**
	 * Display WordPress version notice.
	 */
	public function wp_version_notice() {
		global $wp_version;
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			sprintf(
				/* translators: 1: Required WP version, 2: Current WP version. */
				esc_html__( 'Rext AI requires WordPress %1$s or higher. Your current version is %2$s.', 'rext-ai' ),
				esc_html( REXT_AI_MIN_WP_VERSION ),
				esc_html( $wp_version )
			)
		);
	}

	/**
	 * Include required files.
	 */
	private function includes() {
		// Core classes.
		require_once REXT_AI_PLUGIN_DIR . 'includes/class-rext-ai-auth.php';
		require_once REXT_AI_PLUGIN_DIR . 'includes/class-rext-ai-api.php';
		require_once REXT_AI_PLUGIN_DIR . 'includes/class-rext-ai-posts.php';
		require_once REXT_AI_PLUGIN_DIR . 'includes/class-rext-ai-media.php';
		require_once REXT_AI_PLUGIN_DIR . 'includes/class-rext-ai-seo.php';
		require_once REXT_AI_PLUGIN_DIR . 'includes/class-rext-ai-logger.php';

		// Admin classes.
		if ( is_admin() ) {
			require_once REXT_AI_PLUGIN_DIR . 'admin/class-rext-ai-admin.php';
		}
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		// Activation/Deactivation hooks.
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

		// Initialize components after plugins loaded.
		add_action( 'plugins_loaded', array( $this, 'init_components' ) );

		// Load text domain.
		add_action( 'init', array( $this, 'load_textdomain' ) );

		// Register REST API routes.
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * Initialize plugin components.
	 */
	public function init_components() {
		$this->auth  = new Rext_AI_Auth();
		$this->api   = new Rext_AI_API();
		$this->posts = new Rext_AI_Posts();
		$this->media = new Rext_AI_Media();
		$this->seo   = new Rext_AI_SEO();

		if ( is_admin() ) {
			$this->admin = new Rext_AI_Admin();
		}
	}

	/**
	 * Load plugin text domain.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'rext-ai', false, dirname( REXT_AI_PLUGIN_BASENAME ) . '/languages' );
	}

	/**
	 * Register REST API routes.
	 */
	public function register_rest_routes() {
		if ( $this->api ) {
			$this->api->register_routes();
		}
	}

	/**
	 * Plugin activation.
	 */
	public function activate() {
		// Create database tables.
		$this->create_tables();

		// Generate initial API key if not exists.
		if ( ! get_option( 'rext_ai_api_key' ) ) {
			update_option( 'rext_ai_api_key', $this->generate_api_key() );
		}

		// Set default options.
		$defaults = array(
			'rext_ai_enabled'     => true,
			'rext_ai_permissions' => array(
				'create_posts'      => true,
				'edit_posts'        => true,
				'delete_posts'      => false,
				'upload_media'      => true,
				'manage_categories' => true,
				'manage_tags'       => true,
			),
		);

		foreach ( $defaults as $key => $value ) {
			if ( false === get_option( $key ) ) {
				update_option( $key, $value );
			}
		}

		// Flush rewrite rules.
		flush_rewrite_rules();

		// Log activation.
		Rext_AI_Logger::log( 'Plugin activated', 'info' );
	}

	/**
	 * Plugin deactivation.
	 */
	public function deactivate() {
		// Flush rewrite rules.
		flush_rewrite_rules();

		// Log deactivation.
		Rext_AI_Logger::log( 'Plugin deactivated', 'info' );
	}

	/**
	 * Create database tables.
	 */
	private function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Activity log table.
		$table_name = $wpdb->prefix . 'rext_ai_logs';
		$sql        = "CREATE TABLE IF NOT EXISTS $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			action varchar(100) NOT NULL,
			message text NOT NULL,
			data longtext,
			level varchar(20) DEFAULT 'info',
			ip_address varchar(45),
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY action (action),
			KEY level (level),
			KEY created_at (created_at)
		) $charset_collate;";

		// Post tracking table.
		$table_posts = $wpdb->prefix . 'rext_ai_posts';
		$sql_posts   = "CREATE TABLE IF NOT EXISTS $table_posts (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			wp_post_id bigint(20) NOT NULL,
			rext_content_id varchar(100) NOT NULL,
			status varchar(50) DEFAULT 'published',
			published_at datetime,
			last_synced_at datetime,
			metadata longtext,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY wp_post_id (wp_post_id),
			KEY rext_content_id (rext_content_id),
			KEY status (status)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
		dbDelta( $sql_posts );
	}

	/**
	 * Generate secure API key.
	 *
	 * @return string The generated API key.
	 */
	private function generate_api_key() {
		return 'rext_' . bin2hex( random_bytes( 32 ) );
	}

	/**
	 * Prevent cloning.
	 */
	private function __clone() {}

	/**
	 * Prevent unserializing.
	 *
	 * @throws \Exception When attempting to unserialize.
	 */
	public function __wakeup() {
		throw new \Exception( 'Cannot unserialize singleton' );
	}
}

/**
 * Returns the main instance of Rext_AI.
 *
 * @return Rext_AI
 */
function rext_ai() {
	return Rext_AI::instance();
}

// Initialize the plugin.
rext_ai();
