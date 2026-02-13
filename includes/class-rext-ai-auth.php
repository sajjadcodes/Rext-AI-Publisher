<?php
/**
 * Rext AI Authentication Class
 *
 * Handles API key authentication and rate limiting.
 *
 * @package Rext_AI
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Rext_AI_Auth
 *
 * Manages authentication for API requests.
 */
class Rext_AI_Auth {

    /**
     * Rate limit: requests per window
     *
     * @var int
     */
    private $rate_limit = 100;

    /**
     * Rate limit window in seconds
     *
     * @var int
     */
    private $rate_window = 60;

    /**
     * Signature timestamp tolerance in seconds
     *
     * @var int
     */
    private $signature_tolerance = 300;

    /**
     * Constructor
     */
    public function __construct() {
        // Initialize any needed hooks
    }

    /**
     * Authenticate API request
     *
     * @param WP_REST_Request $request The REST request object.
     * @return bool|WP_Error True if authenticated, WP_Error otherwise.
     */
    public function authenticate($request) {
        // Check if plugin is enabled
        if (!get_option('rext_ai_enabled', true)) {
            Rext_AI_Logger::warning('Authentication attempt while plugin disabled');
            return new WP_Error(
                'rext_ai_disabled',
                __('Rext AI integration is currently disabled.', 'rext-ai'),
                array('status' => 503)
            );
        }

        // Get API key from request
        $api_key = $this->get_api_key_from_request($request);

        if (empty($api_key)) {
            Rext_AI_Logger::warning('Authentication failed: No API key provided');
            return new WP_Error(
                'rext_ai_missing_key',
                __('API key is required.', 'rext-ai'),
                array('status' => 401)
            );
        }

        // Validate API key
        if (!$this->validate_api_key($api_key)) {
            Rext_AI_Logger::warning('Authentication failed: Invalid API key', array(
                'key_prefix' => substr($api_key, 0, 10) . '...',
            ));
            return new WP_Error(
                'rext_ai_invalid_key',
                __('Invalid API key.', 'rext-ai'),
                array('status' => 401)
            );
        }

        // Check rate limit
        $rate_check = $this->check_rate_limit();
        if (is_wp_error($rate_check)) {
            return $rate_check;
        }

        // Optional: Verify request signature
        $signature = $request->get_header('X-Rext-Signature');
        if (!empty($signature)) {
            $signature_check = $this->verify_signature($request, $api_key);
            if (is_wp_error($signature_check)) {
                return $signature_check;
            }
        }

        // Update last connected timestamp
        update_option('rext_ai_last_connected', current_time('mysql'));

        Rext_AI_Logger::debug('Authentication successful');
        return true;
    }

    /**
     * Get API key from request headers
     *
     * @param WP_REST_Request $request The REST request object.
     * @return string|null The API key or null.
     */
    private function get_api_key_from_request($request) {
        // Try X-Rext-API-Key header first
        $api_key = $request->get_header('X-Rext-API-Key');
        if (!empty($api_key)) {
            return sanitize_text_field($api_key);
        }

        // Try Authorization Bearer header
        $auth_header = $request->get_header('Authorization');
        if (!empty($auth_header) && stripos($auth_header, 'Bearer ') === 0) {
            return sanitize_text_field(substr($auth_header, 7));
        }

        return null;
    }

    /**
     * Validate API key using timing-safe comparison
     *
     * @param string $provided_key The provided API key.
     * @return bool True if valid.
     */
    private function validate_api_key($provided_key) {
        $stored_key = get_option('rext_ai_api_key', '');

        if (empty($stored_key)) {
            return false;
        }

        // Use timing-safe comparison to prevent timing attacks
        return hash_equals($stored_key, $provided_key);
    }

    /**
     * Check rate limit
     *
     * @return bool|WP_Error True if within limits, WP_Error if exceeded.
     */
    private function check_rate_limit() {
        $ip = $this->get_client_ip();
        $transient_key = 'rext_ai_rate_' . md5($ip);

        $current = get_transient($transient_key);

        if ($current === false) {
            // First request in this window
            set_transient($transient_key, 1, $this->rate_window);
            return true;
        }

        if ($current >= $this->rate_limit) {
            Rext_AI_Logger::warning('Rate limit exceeded', array(
                'ip'         => $ip,
                'requests'   => $current,
                'limit'      => $this->rate_limit,
            ));
            return new WP_Error(
                'rext_ai_rate_limited',
                __('Rate limit exceeded. Please try again later.', 'rext-ai'),
                array(
                    'status'           => 429,
                    'retry_after'      => $this->rate_window,
                    'x-ratelimit-limit' => $this->rate_limit,
                    'x-ratelimit-remaining' => 0,
                )
            );
        }

        // Increment counter
        set_transient($transient_key, $current + 1, $this->rate_window);

        return true;
    }

    /**
     * Verify request signature
     *
     * @param WP_REST_Request $request The REST request object.
     * @param string          $api_key The API key.
     * @return bool|WP_Error True if valid, WP_Error otherwise.
     */
    private function verify_signature($request, $api_key) {
        $signature = $request->get_header('X-Rext-Signature');
        $timestamp = $request->get_header('X-Rext-Timestamp');

        if (empty($timestamp)) {
            return new WP_Error(
                'rext_ai_missing_timestamp',
                __('Request timestamp is required when using signatures.', 'rext-ai'),
                array('status' => 401)
            );
        }

        // Check timestamp is not too old
        $timestamp_int = intval($timestamp);
        $current_time = time();

        if (abs($current_time - $timestamp_int) > $this->signature_tolerance) {
            Rext_AI_Logger::warning('Request signature timestamp expired', array(
                'timestamp'   => $timestamp,
                'current'     => $current_time,
                'difference'  => abs($current_time - $timestamp_int),
            ));
            return new WP_Error(
                'rext_ai_timestamp_expired',
                __('Request timestamp has expired.', 'rext-ai'),
                array('status' => 401)
            );
        }

        // Build signature payload
        $body = $request->get_body();
        $payload = $timestamp . $body;

        // Calculate expected signature
        $expected_signature = hash_hmac('sha256', $payload, $api_key);

        // Verify signature
        if (!hash_equals($expected_signature, $signature)) {
            Rext_AI_Logger::warning('Invalid request signature');
            return new WP_Error(
                'rext_ai_invalid_signature',
                __('Invalid request signature.', 'rext-ai'),
                array('status' => 401)
            );
        }

        return true;
    }

    /**
     * Get client IP address
     *
     * @return string The client IP address.
     */
    private function get_client_ip() {
        $ip_keys = array(
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        );

        foreach ( $ip_keys as $key ) {
            if ( ! empty( $_SERVER[ $key ] ) ) {
                $ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
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
     * Generate a new API key
     *
     * @return string The new API key.
     */
    public function generate_api_key() {
        $new_key = 'rext_' . bin2hex(random_bytes(32));
        update_option('rext_ai_api_key', $new_key);

        Rext_AI_Logger::info('API key regenerated');

        // Trigger webhook
        do_action('rext_ai_api_key_regenerated');

        return $new_key;
    }

    /**
     * Get masked API key for display
     *
     * @return string The masked API key.
     */
    public function get_masked_api_key() {
        $api_key = get_option('rext_ai_api_key', '');

        if (empty($api_key)) {
            return '';
        }

        $visible_start = 10;
        $visible_end = 4;

        return substr($api_key, 0, $visible_start) . str_repeat('•', 20) . substr($api_key, -$visible_end);
    }

    /**
     * Check if a specific permission is granted
     *
     * @param string $permission The permission to check.
     * @return bool True if permission is granted.
     */
    public function has_permission($permission) {
        $permissions = get_option('rext_ai_permissions', array());

        return isset($permissions[$permission]) && $permissions[$permission] === true;
    }

    /**
     * Permission check callback for REST API
     *
     * @param string $permission The required permission.
     * @return callable Permission callback function.
     */
    public function permission_callback($permission) {
        return function($request) use ($permission) {
            // First authenticate
            $auth_result = $this->authenticate($request);
            if (is_wp_error($auth_result)) {
                return $auth_result;
            }

            // Then check permission
            if (!$this->has_permission($permission)) {
                Rext_AI_Logger::warning('Permission denied', array(
                    'permission' => $permission,
                ));
                return new WP_Error(
                    'rext_ai_permission_denied',
                    sprintf(
                        /* translators: %s: permission name */
                        __('Permission denied: %s is not enabled.', 'rext-ai'),
                        $permission
                    ),
                    array('status' => 403)
                );
            }

            return true;
        };
    }

    /**
     * Basic authentication callback (no specific permission required)
     *
     * @return callable Authentication callback function.
     */
    public function auth_callback() {
        return function($request) {
            return $this->authenticate($request);
        };
    }

    /**
     * Get remaining rate limit
     *
     * @return int Remaining requests in current window.
     */
    public function get_rate_limit_remaining() {
        $ip = $this->get_client_ip();
        $transient_key = 'rext_ai_rate_' . md5($ip);
        $current = get_transient($transient_key);

        if ($current === false) {
            return $this->rate_limit;
        }

        return max(0, $this->rate_limit - $current);
    }

    /**
     * Get rate limit information
     *
     * @return array Rate limit info.
     */
    public function get_rate_limit_info() {
        return array(
            'limit'     => $this->rate_limit,
            'remaining' => $this->get_rate_limit_remaining(),
            'window'    => $this->rate_window,
        );
    }
}
