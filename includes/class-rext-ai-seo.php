<?php
/**
 * Rext AI SEO Class
 *
 * Handles SEO plugin detection and meta field management.
 *
 * @package Rext_AI
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Rext_AI_SEO
 *
 * Manages SEO plugin integration for the Rext AI plugin.
 */
class Rext_AI_SEO {

    /**
     * Supported SEO plugins configuration
     *
     * @var array
     */
    private $plugins = array(
        'yoast' => array(
            'name'      => 'Yoast SEO',
            'file'      => 'wordpress-seo/wp-seo.php',
            'alt_file'  => 'wordpress-seo-premium/wp-seo-premium.php',
            'prefix'    => '_yoast_wpseo_',
            'fields'    => array(
                'focus_keyword'    => 'focuskw',
                'meta_title'       => 'title',
                'meta_description' => 'metadesc',
                'canonical_url'    => 'canonical',
                'robots_noindex'   => 'meta-robots-noindex',
                'robots_nofollow'  => 'meta-robots-nofollow',
            ),
        ),
        'rank_math' => array(
            'name'      => 'Rank Math',
            'file'      => 'seo-by-rank-math/rank-math.php',
            'prefix'    => 'rank_math_',
            'fields'    => array(
                'focus_keyword'    => 'focus_keyword',
                'meta_title'       => 'title',
                'meta_description' => 'description',
                'canonical_url'    => 'canonical_url',
                'robots'           => 'robots',
            ),
        ),
        'aioseo' => array(
            'name'      => 'All in One SEO',
            'file'      => 'all-in-one-seo-pack/all_in_one_seo_pack.php',
            'alt_file'  => 'all-in-one-seo-pack-pro/all_in_one_seo_pack.php',
            'uses_table' => true,
            'table'     => 'aioseo_posts',
            'fields'    => array(
                'focus_keyword'    => 'keyphrases',
                'meta_title'       => 'title',
                'meta_description' => 'description',
                'canonical_url'    => 'canonical_url',
                'robots_noindex'   => 'robots_noindex',
                'robots_nofollow'  => 'robots_nofollow',
            ),
        ),
        'seopress' => array(
            'name'      => 'SEOPress',
            'file'      => 'wp-seopress/seopress.php',
            'alt_file'  => 'wp-seopress-pro/seopress-pro.php',
            'prefix'    => '_seopress_',
            'fields'    => array(
                'focus_keyword'    => 'analysis_target_kw',
                'meta_title'       => 'titles_title',
                'meta_description' => 'titles_desc',
                'canonical_url'    => 'robots_canonical',
                'robots_noindex'   => 'robots_index',
                'robots_nofollow'  => 'robots_follow',
            ),
        ),
    );

    /**
     * Detected plugin key
     *
     * @var string|null
     */
    private $detected_plugin = null;

    /**
     * Constructor
     */
    public function __construct() {
        // Detect plugin on init
        add_action('plugins_loaded', array($this, 'detect_plugin'), 20);
    }

    /**
     * Detect active SEO plugin
     *
     * @return array|null Plugin info or null if none detected.
     */
    public function detect_plugin() {
        if ($this->detected_plugin !== null) {
            return $this->detected_plugin;
        }

        // Check plugins in priority order
        $priority_order = array('yoast', 'rank_math', 'aioseo', 'seopress');

        foreach ($priority_order as $key) {
            $plugin = $this->plugins[$key];

            // Check main file
            if ($this->is_plugin_active($plugin['file'])) {
                $this->detected_plugin = array(
                    'key'  => $key,
                    'name' => $plugin['name'],
                );
                return $this->detected_plugin;
            }

            // Check alternative file (premium versions)
            if (!empty($plugin['alt_file']) && $this->is_plugin_active($plugin['alt_file'])) {
                $this->detected_plugin = array(
                    'key'  => $key,
                    'name' => $plugin['name'] . ' (Premium)',
                );
                return $this->detected_plugin;
            }
        }

        $this->detected_plugin = null;
        return null;
    }

    /**
     * Check if a plugin is active
     *
     * @param string $plugin_file Plugin file path.
     * @return bool True if active.
     */
    private function is_plugin_active($plugin_file) {
        if (!function_exists('is_plugin_active')) {
            include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        return is_plugin_active($plugin_file);
    }

    /**
     * Get supported fields for detected plugin
     *
     * @return array Supported field names.
     */
    public function get_supported_fields() {
        $detected = $this->detect_plugin();

        if (!$detected) {
            return array();
        }

        $plugin_config = $this->plugins[$detected['key']];

        return array_keys($plugin_config['fields']);
    }

    /**
     * Get SEO meta for a post
     *
     * @param int $post_id Post ID.
     * @return array|WP_Error SEO meta data or WP_Error.
     */
    public function get_meta($post_id) {
        $post = get_post($post_id);
        if (!$post) {
            return new WP_Error('post_not_found', __('Post not found.', 'rext-ai'), array('status' => 404));
        }

        $detected = $this->detect_plugin();

        if (!$detected) {
            return array(
                'plugin'  => null,
                'message' => __('No supported SEO plugin detected.', 'rext-ai'),
                'data'    => array(),
            );
        }

        $plugin_key = $detected['key'];
        $plugin_config = $this->plugins[$plugin_key];

        $data = array();

        // Handle AIOSEO separately (uses custom table)
        if (!empty($plugin_config['uses_table'])) {
            $data = $this->get_aioseo_meta($post_id);
        } else {
            // Standard post meta approach
            $prefix = $plugin_config['prefix'];

            foreach ($plugin_config['fields'] as $standard_field => $plugin_field) {
                $meta_key = $prefix . $plugin_field;
                $value = get_post_meta($post_id, $meta_key, true);

                // Handle Rank Math robots array
                if ($plugin_key === 'rank_math' && $standard_field === 'robots') {
                    $data['robots'] = is_array($value) ? implode(', ', $value) : $value;
                } else {
                    $data[$standard_field] = $value;
                }
            }
        }

        return array(
            'plugin' => $detected,
            'data'   => $data,
        );
    }

    /**
     * Update SEO meta for a post
     *
     * @param int   $post_id Post ID.
     * @param array $meta    SEO meta data.
     * @return array|WP_Error Updated meta data or WP_Error.
     */
    public function update_meta($post_id, $meta) {
        $post = get_post($post_id);
        if (!$post) {
            return new WP_Error('post_not_found', __('Post not found.', 'rext-ai'), array('status' => 404));
        }

        $detected = $this->detect_plugin();

        if (!$detected) {
            return new WP_Error(
                'no_seo_plugin',
                __('No supported SEO plugin detected.', 'rext-ai'),
                array('status' => 400)
            );
        }

        $plugin_key = $detected['key'];
        $plugin_config = $this->plugins[$plugin_key];

        // Handle AIOSEO separately
        if (!empty($plugin_config['uses_table'])) {
            return $this->update_aioseo_meta($post_id, $meta);
        }

        // Standard post meta approach
        $prefix = $plugin_config['prefix'];
        $updated = array();

        foreach ($meta as $standard_field => $value) {
            if (!isset($plugin_config['fields'][$standard_field])) {
                continue;
            }

            $plugin_field = $plugin_config['fields'][$standard_field];
            $meta_key = $prefix . $plugin_field;

            // Sanitize value
            $sanitized_value = $this->sanitize_meta_value($standard_field, $value);

            // Handle Rank Math robots array
            if ($plugin_key === 'rank_math' && $standard_field === 'robots') {
                $sanitized_value = $this->parse_robots_string($value);
            }

            // Handle Yoast robots fields
            if ($plugin_key === 'yoast') {
                if ($standard_field === 'robots') {
                    $robots = $this->parse_robots_string($value);
                    if (in_array('noindex', $robots)) {
                        update_post_meta($post_id, $prefix . 'meta-robots-noindex', '1');
                    }
                    if (in_array('nofollow', $robots)) {
                        update_post_meta($post_id, $prefix . 'meta-robots-nofollow', '1');
                    }
                    continue;
                }
            }

            update_post_meta($post_id, $meta_key, $sanitized_value);
            $updated[$standard_field] = $sanitized_value;
        }

        Rext_AI_Logger::info('SEO meta updated', array(
            'post_id' => $post_id,
            'plugin'  => $detected['name'],
            'fields'  => array_keys($updated),
        ));

        return array(
            'plugin'  => $detected,
            'updated' => $updated,
        );
    }

    /**
     * Get AIOSEO meta from custom table
     *
     * @param int $post_id Post ID.
     * @return array SEO meta data.
     */
    private function get_aioseo_meta($post_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aioseo_posts';

        // Check if table exists
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) !== $table_name ) {
            return array();
        }

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE post_id = %d",
            $post_id
        ));

        if (!$row) {
            return array();
        }

        $data = array(
            'meta_title'       => $row->title ?? '',
            'meta_description' => $row->description ?? '',
            'canonical_url'    => $row->canonical_url ?? '',
        );

        // Parse keyphrases JSON
        if (!empty($row->keyphrases)) {
            $keyphrases = json_decode($row->keyphrases, true);
            if (!empty($keyphrases['focus']['keyphrase'])) {
                $data['focus_keyword'] = $keyphrases['focus']['keyphrase'];
            }
        }

        // Parse robots
        $robots = array();
        if (!empty($row->robots_noindex)) {
            $robots[] = 'noindex';
        }
        if (!empty($row->robots_nofollow)) {
            $robots[] = 'nofollow';
        }
        $data['robots'] = !empty($robots) ? implode(', ', $robots) : 'index, follow';

        return $data;
    }

    /**
     * Update AIOSEO meta in custom table
     *
     * @param int   $post_id Post ID.
     * @param array $meta    SEO meta data.
     * @return array|WP_Error Update result.
     */
    private function update_aioseo_meta($post_id, $meta) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aioseo_posts';

        // Check if table exists
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) !== $table_name ) {
            return new WP_Error(
                'aioseo_table_missing',
                __('AIOSEO posts table not found.', 'rext-ai'),
                array('status' => 500)
            );
        }

        // Check if row exists
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE post_id = %d",
            $post_id
        ));

        $data = array();
        $updated = array();

        // Map standard fields to AIOSEO columns
        if (isset($meta['meta_title'])) {
            $data['title'] = sanitize_text_field($meta['meta_title']);
            $updated['meta_title'] = $data['title'];
        }

        if (isset($meta['meta_description'])) {
            $data['description'] = sanitize_textarea_field($meta['meta_description']);
            $updated['meta_description'] = $data['description'];
        }

        if (isset($meta['canonical_url'])) {
            $data['canonical_url'] = esc_url_raw($meta['canonical_url']);
            $updated['canonical_url'] = $data['canonical_url'];
        }

        if (isset($meta['focus_keyword'])) {
            $keyword = sanitize_text_field($meta['focus_keyword']);
            $data['keyphrases'] = wp_json_encode(array(
                'focus' => array(
                    'keyphrase' => $keyword,
                ),
            ));
            $updated['focus_keyword'] = $keyword;
        }

        if (isset($meta['robots'])) {
            $robots = $this->parse_robots_string($meta['robots']);
            $data['robots_noindex'] = in_array('noindex', $robots) ? 1 : 0;
            $data['robots_nofollow'] = in_array('nofollow', $robots) ? 1 : 0;
            $updated['robots'] = $meta['robots'];
        }

        if (empty($data)) {
            return array(
                'plugin'  => $this->detect_plugin(),
                'updated' => array(),
            );
        }

        // Update timestamp
        $data['updated'] = current_time('mysql');

        if ($existing) {
            $wpdb->update($table_name, $data, array('post_id' => $post_id));
        } else {
            $data['post_id'] = $post_id;
            $data['created'] = current_time('mysql');
            $wpdb->insert($table_name, $data);
        }

        Rext_AI_Logger::info('AIOSEO meta updated', array(
            'post_id' => $post_id,
            'fields'  => array_keys($updated),
        ));

        return array(
            'plugin'  => $this->detect_plugin(),
            'updated' => $updated,
        );
    }

    /**
     * Sanitize meta value based on field type
     *
     * @param string $field Field name.
     * @param mixed  $value Value to sanitize.
     * @return mixed Sanitized value.
     */
    private function sanitize_meta_value($field, $value) {
        switch ($field) {
            case 'focus_keyword':
            case 'meta_title':
                return sanitize_text_field($value);

            case 'meta_description':
                return sanitize_textarea_field($value);

            case 'canonical_url':
                return esc_url_raw($value);

            case 'robots':
            case 'robots_noindex':
            case 'robots_nofollow':
                return sanitize_text_field($value);

            default:
                return sanitize_text_field($value);
        }
    }

    /**
     * Parse robots string into array
     *
     * @param string $robots Robots string (e.g., "index, follow" or "noindex, nofollow").
     * @return array Array of robots directives.
     */
    private function parse_robots_string($robots) {
        if (is_array($robots)) {
            return array_map('trim', $robots);
        }

        $directives = array_map('trim', explode(',', strtolower($robots)));

        // Filter to valid directives
        $valid = array('index', 'noindex', 'follow', 'nofollow', 'none', 'noarchive', 'nosnippet', 'noimageindex');

        return array_filter($directives, function($d) use ($valid) {
            return in_array($d, $valid);
        });
    }

    /**
     * Get list of supported SEO plugins
     *
     * @return array List of supported plugins.
     */
    public function get_supported_plugins() {
        $list = array();

        foreach ($this->plugins as $key => $config) {
            $list[$key] = $config['name'];
        }

        return $list;
    }

    /**
     * Check if any SEO plugin is active
     *
     * @return bool True if a supported SEO plugin is active.
     */
    public function has_seo_plugin() {
        return $this->detect_plugin() !== null;
    }
}
