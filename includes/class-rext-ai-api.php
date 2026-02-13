<?php
/**
 * Rext AI REST API Class
 *
 * Handles all REST API endpoint registrations and responses.
 *
 * @package Rext_AI
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Rext_AI_API
 *
 * Manages REST API endpoints for the Rext AI plugin.
 */
class Rext_AI_API {

    /**
     * API namespace
     *
     * @var string
     */
    private $namespace = 'rext-ai/v1';

    /**
     * Auth instance
     *
     * @var Rext_AI_Auth
     */
    private $auth;

    /**
     * Constructor
     */
    public function __construct() {
        $this->auth = new Rext_AI_Auth();
    }

    /**
     * Register REST API routes
     */
    public function register_routes() {
        // Connection endpoints
        $this->register_connection_routes();

        // Post endpoints
        $this->register_post_routes();

        // Media endpoints
        $this->register_media_routes();

        // Taxonomy endpoints
        $this->register_taxonomy_routes();

        // SEO endpoints
        $this->register_seo_routes();

        // Utility endpoints
        $this->register_utility_routes();
    }

    /**
     * Register connection routes
     */
    private function register_connection_routes() {
        // POST /connect - Initial handshake
        register_rest_route($this->namespace, '/connect', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array($this, 'handle_connect'),
            'permission_callback' => $this->auth->auth_callback(),
        ));

        // GET /verify - Verify connection
        register_rest_route($this->namespace, '/verify', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array($this, 'handle_verify'),
            'permission_callback' => $this->auth->auth_callback(),
        ));

        // POST /disconnect - Revoke access
        register_rest_route($this->namespace, '/disconnect', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array($this, 'handle_disconnect'),
            'permission_callback' => $this->auth->auth_callback(),
        ));

        // GET /site-info - Get site metadata
        register_rest_route($this->namespace, '/site-info', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array($this, 'handle_site_info'),
            'permission_callback' => $this->auth->auth_callback(),
        ));

        // GET /capabilities - Get permissions & features
        register_rest_route($this->namespace, '/capabilities', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array($this, 'handle_capabilities'),
            'permission_callback' => $this->auth->auth_callback(),
        ));
    }

    /**
     * Register post routes
     */
    private function register_post_routes() {
        // POST /posts - Create new post
        register_rest_route($this->namespace, '/posts', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array($this, 'handle_create_post'),
            'permission_callback' => $this->auth->permission_callback('create_posts'),
        ));

        // GET /posts - List Rext AI posts
        register_rest_route($this->namespace, '/posts', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array($this, 'handle_list_posts'),
            'permission_callback' => $this->auth->auth_callback(),
        ));

        // GET /posts/{id} - Get single post
        register_rest_route($this->namespace, '/posts/(?P<id>\d+)', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array($this, 'handle_get_post'),
            'permission_callback' => $this->auth->auth_callback(),
            'args'                => array(
                'id' => array(
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    },
                ),
            ),
        ));

        // PUT /posts/{id} - Update post
        register_rest_route($this->namespace, '/posts/(?P<id>\d+)', array(
            'methods'             => WP_REST_Server::EDITABLE,
            'callback'            => array($this, 'handle_update_post'),
            'permission_callback' => $this->auth->permission_callback('edit_posts'),
            'args'                => array(
                'id' => array(
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    },
                ),
            ),
        ));

        // DELETE /posts/{id} - Delete post
        register_rest_route($this->namespace, '/posts/(?P<id>\d+)', array(
            'methods'             => WP_REST_Server::DELETABLE,
            'callback'            => array($this, 'handle_delete_post'),
            'permission_callback' => $this->auth->permission_callback('delete_posts'),
            'args'                => array(
                'id' => array(
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    },
                ),
            ),
        ));
    }

    /**
     * Register media routes
     */
    private function register_media_routes() {
        // POST /media - Upload file
        register_rest_route($this->namespace, '/media', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array($this, 'handle_upload_media'),
            'permission_callback' => $this->auth->permission_callback('upload_media'),
        ));

        // POST /media/upload-from-url - Sideload from URL
        register_rest_route($this->namespace, '/media/upload-from-url', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array($this, 'handle_sideload_media'),
            'permission_callback' => $this->auth->permission_callback('upload_media'),
        ));

        // GET /media - List media
        register_rest_route($this->namespace, '/media', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array($this, 'handle_list_media'),
            'permission_callback' => $this->auth->auth_callback(),
        ));

        // GET /media/{id} - Get single media
        register_rest_route($this->namespace, '/media/(?P<id>\d+)', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array($this, 'handle_get_media'),
            'permission_callback' => $this->auth->auth_callback(),
            'args'                => array(
                'id' => array(
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    },
                ),
            ),
        ));
    }

    /**
     * Register taxonomy routes
     */
    private function register_taxonomy_routes() {
        // GET /categories - List categories
        register_rest_route($this->namespace, '/categories', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array($this, 'handle_list_categories'),
            'permission_callback' => $this->auth->auth_callback(),
        ));

        // POST /categories - Create category
        register_rest_route($this->namespace, '/categories', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array($this, 'handle_create_category'),
            'permission_callback' => $this->auth->permission_callback('manage_categories'),
        ));

        // GET /tags - List tags
        register_rest_route($this->namespace, '/tags', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array($this, 'handle_list_tags'),
            'permission_callback' => $this->auth->auth_callback(),
        ));

        // POST /tags - Create tag
        register_rest_route($this->namespace, '/tags', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array($this, 'handle_create_tag'),
            'permission_callback' => $this->auth->permission_callback('manage_tags'),
        ));

        // GET /authors - List authors
        register_rest_route($this->namespace, '/authors', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array($this, 'handle_list_authors'),
            'permission_callback' => $this->auth->auth_callback(),
        ));
    }

    /**
     * Register SEO routes
     */
    private function register_seo_routes() {
        // GET /seo-plugin - Detect active SEO plugin
        register_rest_route($this->namespace, '/seo-plugin', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array($this, 'handle_detect_seo_plugin'),
            'permission_callback' => $this->auth->auth_callback(),
        ));

        // GET /seo-meta/{post_id} - Get SEO meta for post
        register_rest_route($this->namespace, '/seo-meta/(?P<post_id>\d+)', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array($this, 'handle_get_seo_meta'),
            'permission_callback' => $this->auth->auth_callback(),
            'args'                => array(
                'post_id' => array(
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    },
                ),
            ),
        ));

        // POST /seo-meta/{post_id} - Update SEO meta
        register_rest_route($this->namespace, '/seo-meta/(?P<post_id>\d+)', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array($this, 'handle_update_seo_meta'),
            'permission_callback' => $this->auth->permission_callback('edit_posts'),
            'args'                => array(
                'post_id' => array(
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    },
                ),
            ),
        ));
    }

    /**
     * Register utility routes
     */
    private function register_utility_routes() {
        // GET /activity-log - Get activity logs
        register_rest_route($this->namespace, '/activity-log', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array($this, 'handle_activity_log'),
            'permission_callback' => $this->auth->auth_callback(),
        ));
    }

    // ========== Connection Handlers ==========

    /**
     * Handle connect request
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response
     */
    public function handle_connect($request) {
        Rext_AI_Logger::info('Connection established', array(
            'user_agent' => $request->get_header('User-Agent'),
        ));

        return new WP_REST_Response(array(
            'success'   => true,
            'message'   => __('Connection established successfully.', 'rext-ai'),
            'site_info' => $this->get_site_info_data(),
        ), 200);
    }

    /**
     * Handle verify request
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response
     */
    public function handle_verify($request) {
        Rext_AI_Logger::debug('Connection verified');

        return new WP_REST_Response(array(
            'success'     => true,
            'message'     => __('Connection is active.', 'rext-ai'),
            'timestamp'   => current_time('c'),
            'rate_limit'  => $this->auth->get_rate_limit_info(),
        ), 200);
    }

    /**
     * Handle disconnect request
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response
     */
    public function handle_disconnect($request) {
        // Regenerate API key to revoke access
        $this->auth->generate_api_key();

        Rext_AI_Logger::info('Connection disconnected, API key regenerated');

        return new WP_REST_Response(array(
            'success' => true,
            'message' => __('Disconnected successfully. API key has been regenerated.', 'rext-ai'),
        ), 200);
    }

    /**
     * Handle site info request
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response
     */
    public function handle_site_info($request) {
        return new WP_REST_Response(array(
            'success'   => true,
            'site_info' => $this->get_site_info_data(),
        ), 200);
    }

    /**
     * Handle capabilities request
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response
     */
    public function handle_capabilities($request) {
        $permissions = get_option('rext_ai_permissions', array());
        $seo = rext_ai()->seo;

        return new WP_REST_Response(array(
            'success'      => true,
            'permissions'  => $permissions,
            'seo_plugin'   => $seo ? $seo->detect_plugin() : null,
            'features'     => array(
                'media_upload'    => true,
                'scheduled_posts' => true,
            ),
        ), 200);
    }

    // ========== Post Handlers ==========

    /**
     * Handle create post request
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response
     */
    public function handle_create_post($request) {
        $posts = rext_ai()->posts;
        $result = $posts->create_post($request->get_json_params());

        if (is_wp_error($result)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => $result->get_error_message(),
                'code'    => $result->get_error_code(),
            ), $this->get_error_status( $result ));
        }

        return new WP_REST_Response(array(
            'success' => true,
            'message' => __('Post created successfully.', 'rext-ai'),
            'data'    => $result,
        ), 201);
    }

    /**
     * Handle list posts request
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response
     */
    public function handle_list_posts($request) {
        $posts = rext_ai()->posts;
        $args = array(
            'per_page' => absint( $request->get_param('per_page') ) ?: 20,
            'page'     => absint( $request->get_param('page') ) ?: 1,
            'status'   => sanitize_key( (string) $request->get_param('status') ),
        );

        $result = $posts->get_posts($args);

        return new WP_REST_Response(array(
            'success' => true,
            'data'    => $result['posts'],
            'total'   => $result['total'],
            'pages'   => $result['pages'],
        ), 200);
    }

    /**
     * Handle get single post request
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response
     */
    public function handle_get_post($request) {
        $post_id = intval($request->get_param('id'));
        $posts = rext_ai()->posts;
        $result = $posts->get_post($post_id);

        if (is_wp_error($result)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => $result->get_error_message(),
            ), 404);
        }

        return new WP_REST_Response(array(
            'success' => true,
            'data'    => $result,
        ), 200);
    }

    /**
     * Handle update post request
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response
     */
    public function handle_update_post($request) {
        $post_id = intval($request->get_param('id'));
        $posts = rext_ai()->posts;
        $result = $posts->update_post($post_id, $request->get_json_params());

        if (is_wp_error($result)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => $result->get_error_message(),
            ), $this->get_error_status( $result ));
        }

        return new WP_REST_Response(array(
            'success' => true,
            'message' => __('Post updated successfully.', 'rext-ai'),
            'data'    => $result,
        ), 200);
    }

    /**
     * Handle delete post request
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response
     */
    public function handle_delete_post($request) {
        $post_id = intval($request->get_param('id'));
        $force = rest_sanitize_boolean( $request->get_param('force') );
        $posts = rext_ai()->posts;
        $result = $posts->delete_post($post_id, $force);

        if (is_wp_error($result)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => $result->get_error_message(),
            ), $this->get_error_status( $result ));
        }

        return new WP_REST_Response(array(
            'success' => true,
            'message' => $force ? __('Post permanently deleted.', 'rext-ai') : __('Post moved to trash.', 'rext-ai'),
        ), 200);
    }

    // ========== Media Handlers ==========

    /**
     * Handle upload media request
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response
     */
    public function handle_upload_media($request) {
        $media = rext_ai()->media;
        $files = $request->get_file_params();
        $params = $request->get_params();

        $result = $media->upload_media($files, $params);

        if (is_wp_error($result)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => $result->get_error_message(),
            ), $this->get_error_status( $result ));
        }

        return new WP_REST_Response(array(
            'success' => true,
            'message' => __('Media uploaded successfully.', 'rext-ai'),
            'data'    => $result,
        ), 201);
    }

    /**
     * Handle sideload media request
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response
     */
    public function handle_sideload_media($request) {
        $media = rext_ai()->media;
        $params = $request->get_json_params();

        $result = $media->sideload_media($params);

        if (is_wp_error($result)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => $result->get_error_message(),
            ), $this->get_error_status( $result ));
        }

        return new WP_REST_Response(array(
            'success' => true,
            'message' => __('Media sideloaded successfully.', 'rext-ai'),
            'data'    => $result,
        ), 201);
    }

    /**
     * Handle list media request
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response
     */
    public function handle_list_media($request) {
        $media = rext_ai()->media;
        $args = array(
            'per_page' => absint( $request->get_param('per_page') ) ?: 20,
            'page'     => absint( $request->get_param('page') ) ?: 1,
        );

        $result = $media->get_media($args);

        return new WP_REST_Response(array(
            'success' => true,
            'data'    => $result['items'],
            'total'   => $result['total'],
            'pages'   => $result['pages'],
        ), 200);
    }

    /**
     * Handle get single media request
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response
     */
    public function handle_get_media($request) {
        $media_id = intval($request->get_param('id'));
        $media = rext_ai()->media;
        $result = $media->get_single_media($media_id);

        if (is_wp_error($result)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => $result->get_error_message(),
            ), 404);
        }

        return new WP_REST_Response(array(
            'success' => true,
            'data'    => $result,
        ), 200);
    }

    // ========== Taxonomy Handlers ==========

    /**
     * Handle list categories request
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response
     */
    public function handle_list_categories($request) {
        $args = array(
            'taxonomy'   => 'category',
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'ASC',
        );

        $categories = get_terms($args);

        $data = array_map(function($term) {
            return array(
                'id'     => $term->term_id,
                'name'   => $term->name,
                'slug'   => $term->slug,
                'parent' => $term->parent,
                'count'  => $term->count,
            );
        }, $categories);

        return new WP_REST_Response(array(
            'success' => true,
            'data'    => $data,
        ), 200);
    }

    /**
     * Handle create category request
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response
     */
    public function handle_create_category($request) {
        $params = $request->get_json_params();
        $name = sanitize_text_field($params['name'] ?? '');
        $slug = sanitize_title($params['slug'] ?? $name);
        $parent = intval($params['parent'] ?? 0);

        if (empty($name)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('Category name is required.', 'rext-ai'),
            ), 400);
        }

        $result = wp_insert_term($name, 'category', array(
            'slug'   => $slug,
            'parent' => $parent,
        ));

        if (is_wp_error($result)) {
            // If term exists, return it
            if ($result->get_error_code() === 'term_exists') {
                $term = get_term($result->get_error_data(), 'category');
                return new WP_REST_Response(array(
                    'success' => true,
                    'message' => __('Category already exists.', 'rext-ai'),
                    'data'    => array(
                        'id'   => $term->term_id,
                        'name' => $term->name,
                        'slug' => $term->slug,
                    ),
                ), 200);
            }

            return new WP_REST_Response(array(
                'success' => false,
                'message' => $result->get_error_message(),
            ), 400);
        }

        Rext_AI_Logger::info('Category created', array('name' => $name, 'id' => $result['term_id']));

        return new WP_REST_Response(array(
            'success' => true,
            'message' => __('Category created successfully.', 'rext-ai'),
            'data'    => array(
                'id'   => $result['term_id'],
                'name' => $name,
                'slug' => $slug,
            ),
        ), 201);
    }

    /**
     * Handle list tags request
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response
     */
    public function handle_list_tags($request) {
        $args = array(
            'taxonomy'   => 'post_tag',
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'ASC',
        );

        $tags = get_terms($args);

        $data = array_map(function($term) {
            return array(
                'id'    => $term->term_id,
                'name'  => $term->name,
                'slug'  => $term->slug,
                'count' => $term->count,
            );
        }, $tags);

        return new WP_REST_Response(array(
            'success' => true,
            'data'    => $data,
        ), 200);
    }

    /**
     * Handle create tag request
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response
     */
    public function handle_create_tag($request) {
        $params = $request->get_json_params();
        $name = sanitize_text_field($params['name'] ?? '');
        $slug = sanitize_title($params['slug'] ?? $name);

        if (empty($name)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('Tag name is required.', 'rext-ai'),
            ), 400);
        }

        $result = wp_insert_term($name, 'post_tag', array(
            'slug' => $slug,
        ));

        if (is_wp_error($result)) {
            if ($result->get_error_code() === 'term_exists') {
                $term = get_term($result->get_error_data(), 'post_tag');
                return new WP_REST_Response(array(
                    'success' => true,
                    'message' => __('Tag already exists.', 'rext-ai'),
                    'data'    => array(
                        'id'   => $term->term_id,
                        'name' => $term->name,
                        'slug' => $term->slug,
                    ),
                ), 200);
            }

            return new WP_REST_Response(array(
                'success' => false,
                'message' => $result->get_error_message(),
            ), 400);
        }

        Rext_AI_Logger::info('Tag created', array('name' => $name, 'id' => $result['term_id']));

        return new WP_REST_Response(array(
            'success' => true,
            'message' => __('Tag created successfully.', 'rext-ai'),
            'data'    => array(
                'id'   => $result['term_id'],
                'name' => $name,
                'slug' => $slug,
            ),
        ), 201);
    }

    /**
     * Handle list authors request
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response
     */
    public function handle_list_authors($request) {
        $users = get_users(array(
            'who'     => 'authors',
            'orderby' => 'display_name',
            'order'   => 'ASC',
        ));

        $data = array_map(function($user) {
            return array(
                'id'           => $user->ID,
                'username'     => $user->user_login,
                'display_name' => $user->display_name,
                'email'        => $user->user_email,
                'avatar'       => get_avatar_url($user->ID),
            );
        }, $users);

        return new WP_REST_Response(array(
            'success' => true,
            'data'    => $data,
        ), 200);
    }

    // ========== SEO Handlers ==========

    /**
     * Handle detect SEO plugin request
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response
     */
    public function handle_detect_seo_plugin($request) {
        $seo = rext_ai()->seo;
        $detected = $seo->detect_plugin();

        return new WP_REST_Response(array(
            'success'          => true,
            'plugin_detected'  => !empty($detected),
            'plugin'           => $detected,
            'supported_fields' => $seo->get_supported_fields(),
        ), 200);
    }

    /**
     * Handle get SEO meta request
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response
     */
    public function handle_get_seo_meta($request) {
        $post_id = intval($request->get_param('post_id'));
        $seo = rext_ai()->seo;
        $result = $seo->get_meta($post_id);

        if (is_wp_error($result)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => $result->get_error_message(),
            ), 404);
        }

        return new WP_REST_Response(array(
            'success' => true,
            'data'    => $result,
        ), 200);
    }

    /**
     * Handle update SEO meta request
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response
     */
    public function handle_update_seo_meta($request) {
        $post_id = intval($request->get_param('post_id'));
        $params = $request->get_json_params();
        $seo = rext_ai()->seo;
        $result = $seo->update_meta($post_id, $params);

        if (is_wp_error($result)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => $result->get_error_message(),
            ), $this->get_error_status( $result ));
        }

        return new WP_REST_Response(array(
            'success' => true,
            'message' => __('SEO meta updated successfully.', 'rext-ai'),
            'data'    => $result,
        ), 200);
    }

    // ========== Utility Handlers ==========

    /**
     * Handle activity log request
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response
     */
    public function handle_activity_log($request) {
        $args = array(
            'per_page' => absint( $request->get_param('per_page') ) ?: 50,
            'page'     => absint( $request->get_param('page') ) ?: 1,
            'level'    => sanitize_key( (string) $request->get_param('level') ),
            'action'   => sanitize_text_field( (string) $request->get_param('action') ),
        );

        $logs = Rext_AI_Logger::get_logs($args);
        $total = Rext_AI_Logger::get_logs_count($args);

        return new WP_REST_Response(array(
            'success' => true,
            'data'    => $logs,
            'total'   => $total,
            'pages'   => ceil($total / $args['per_page']),
        ), 200);
    }

    // ========== Helper Methods ==========

    /**
     * Get the HTTP status code from a WP_Error.
     *
     * @param WP_Error $error The error object.
     * @return int HTTP status code.
     */
    private function get_error_status( $error ) {
        $data = $error->get_error_data();
        if ( is_array( $data ) && isset( $data['status'] ) ) {
            return (int) $data['status'];
        }
        return 400;
    }

    /**
     * Get site info data
     *
     * @return array Site information.
     */
    private function get_site_info_data() {
        global $wp_version;

        return array(
            'name'            => get_bloginfo('name'),
            'description'     => get_bloginfo('description'),
            'url'             => get_site_url(),
            'home'            => get_home_url(),
            'admin_email'     => get_bloginfo('admin_email'),
            'language'        => get_bloginfo('language'),
            'timezone'        => wp_timezone_string(),
            'wordpress_version' => $wp_version,
            'php_version'     => PHP_VERSION,
            'plugin_version'  => REXT_AI_VERSION,
            'multisite'       => is_multisite(),
            'ssl'             => is_ssl(),
        );
    }
}
