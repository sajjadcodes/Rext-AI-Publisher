<?php
/**
 * Rext AI Posts Class
 *
 * Handles post creation, updating, and management.
 *
 * @package Rext_AI
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Rext_AI_Posts
 *
 * Manages post operations for the Rext AI plugin.
 */
class Rext_AI_Posts {

    /**
     * Constructor
     */
    public function __construct() {
        // Hook into post status changes for webhooks
        add_action('transition_post_status', array($this, 'handle_status_transition'), 10, 3);
    }

    /**
     * Create a new post
     *
     * @param array $data Post data from API request.
     * @return array|WP_Error Post data on success, WP_Error on failure.
     */
    public function create_post($data) {
        // Sanitize and validate data
        $title = isset($data['title']) ? sanitize_text_field($data['title']) : '';
        $content = isset($data['content']) ? wp_kses_post($data['content']) : '';
        $excerpt = isset($data['excerpt']) ? sanitize_textarea_field($data['excerpt']) : '';
        $status = isset($data['status']) ? sanitize_text_field($data['status']) : 'draft';
        $slug = isset($data['slug']) ? sanitize_title($data['slug']) : '';
        $rext_content_id = isset($data['rext_content_id']) ? sanitize_text_field($data['rext_content_id']) : '';

        // Validate required fields
        if (empty($title)) {
            return new WP_Error('missing_title', __('Post title is required.', 'rext-ai'), array('status' => 400));
        }

        // Validate status
        $allowed_statuses = array('publish', 'draft', 'pending', 'future', 'private');
        if (!in_array($status, $allowed_statuses)) {
            $status = 'draft';
        }

        // Resolve author
        $author_id = $this->resolve_author($data);

        // Prepare post data
        $post_data = array(
            'post_title'   => $title,
            'post_content' => $content,
            'post_excerpt' => $excerpt,
            'post_status'  => $status,
            'post_author'  => $author_id,
            'post_type'    => 'post',
        );

        // Add slug if provided
        if (!empty($slug)) {
            $post_data['post_name'] = $slug;
        }

        // Handle scheduled posts
        if ($status === 'future' && !empty($data['scheduled_date'])) {
            $scheduled_date = $this->parse_date($data['scheduled_date']);
            if ($scheduled_date) {
                $post_data['post_date'] = $scheduled_date;
                $post_data['post_date_gmt'] = get_gmt_from_date($scheduled_date);
            }
        }

        // Insert post
        $post_id = wp_insert_post($post_data, true);

        if (is_wp_error($post_id)) {
            Rext_AI_Logger::error('Failed to create post', array(
                'error' => $post_id->get_error_message(),
                'title' => $title,
            ));
            return $post_id;
        }

        // Handle categories
        if (!empty($data['categories'])) {
            $this->set_categories($post_id, $data['categories']);
        }

        // Handle tags.
        if ( ! empty( $data['tags'] ) ) {
            $tags = is_array( $data['tags'] )
                ? array_map( 'sanitize_text_field', $data['tags'] )
                : sanitize_text_field( $data['tags'] );
            wp_set_post_tags( $post_id, $tags );
        }

        // Handle featured image.
        $featured_image_id = null;
        if (!empty($data['featured_image'])) {
            $featured_image_id = intval($data['featured_image']);
            set_post_thumbnail($post_id, $featured_image_id);
        } elseif (!empty($data['featured_image_url'])) {
            $media = rext_ai()->media;
            $result = $media->sideload_media(array(
                'url'     => $data['featured_image_url'],
                'post_id' => $post_id,
            ));
            if (!is_wp_error($result)) {
                $featured_image_id = $result['id'];
                set_post_thumbnail($post_id, $featured_image_id);
            }
        }

        // Handle SEO meta
        if (!empty($data['seo'])) {
            $seo = rext_ai()->seo;
            $seo->update_meta($post_id, $data['seo']);
        }

        // Store Rext AI meta
        update_post_meta($post_id, '_rext_ai_content_id', $rext_content_id);
        update_post_meta($post_id, '_rext_ai_created', current_time('mysql'));

        // Track in custom table
        $this->track_post($post_id, $rext_content_id, $status);

        // Log creation
        Rext_AI_Logger::info('Post created', array(
            'post_id'         => $post_id,
            'title'           => $title,
            'status'          => $status,
            'rext_content_id' => $rext_content_id,
        ));

        // Return post data
        $post = get_post($post_id);
        return array(
            'id'              => $post_id,
            'title'           => $post->post_title,
            'slug'            => $post->post_name,
            'status'          => $post->post_status,
            'url'             => get_permalink($post_id),
            'edit_url'        => get_edit_post_link($post_id, 'raw'),
            'author'          => intval($post->post_author),
            'published_at'    => $post->post_date,
            'rext_content_id' => $rext_content_id,
            'featured_image'  => $featured_image_id ? wp_get_attachment_url($featured_image_id) : null,
        );
    }

    /**
     * Update an existing post
     *
     * @param int   $post_id The post ID.
     * @param array $data    Post data to update.
     * @return array|WP_Error Updated post data on success, WP_Error on failure.
     */
    public function update_post($post_id, $data) {
        // Verify post exists and is a Rext AI post
        $post = get_post($post_id);
        if (!$post) {
            return new WP_Error('post_not_found', __('Post not found.', 'rext-ai'), array('status' => 404));
        }

        // Prepare update data
        $update_data = array('ID' => $post_id);

        if (isset($data['title'])) {
            $update_data['post_title'] = sanitize_text_field($data['title']);
        }

        if (isset($data['content'])) {
            $update_data['post_content'] = wp_kses_post($data['content']);
        }

        if (isset($data['excerpt'])) {
            $update_data['post_excerpt'] = sanitize_textarea_field($data['excerpt']);
        }

        if (isset($data['status'])) {
            $status = sanitize_text_field($data['status']);
            $allowed_statuses = array('publish', 'draft', 'pending', 'future', 'private');
            if (in_array($status, $allowed_statuses)) {
                $update_data['post_status'] = $status;
            }
        }

        if (isset($data['slug'])) {
            $update_data['post_name'] = sanitize_title($data['slug']);
        }

        // Handle scheduled date
        if (isset($data['scheduled_date']) && isset($update_data['post_status']) && $update_data['post_status'] === 'future') {
            $scheduled_date = $this->parse_date($data['scheduled_date']);
            if ($scheduled_date) {
                $update_data['post_date'] = $scheduled_date;
                $update_data['post_date_gmt'] = get_gmt_from_date($scheduled_date);
            }
        }

        // Update post
        $result = wp_update_post($update_data, true);

        if (is_wp_error($result)) {
            Rext_AI_Logger::error('Failed to update post', array(
                'post_id' => $post_id,
                'error'   => $result->get_error_message(),
            ));
            return $result;
        }

        // Handle categories
        if (isset($data['categories'])) {
            $this->set_categories($post_id, $data['categories']);
        }

        // Handle tags.
        if ( isset( $data['tags'] ) ) {
            $tags = is_array( $data['tags'] )
                ? array_map( 'sanitize_text_field', $data['tags'] )
                : sanitize_text_field( $data['tags'] );
            wp_set_post_tags( $post_id, $tags );
        }

        // Handle featured image
        if (isset($data['featured_image'])) {
            if (empty($data['featured_image'])) {
                delete_post_thumbnail($post_id);
            } else {
                set_post_thumbnail($post_id, intval($data['featured_image']));
            }
        } elseif (isset($data['featured_image_url'])) {
            $media = rext_ai()->media;
            $result = $media->sideload_media(array(
                'url'     => $data['featured_image_url'],
                'post_id' => $post_id,
            ));
            if (!is_wp_error($result)) {
                set_post_thumbnail($post_id, $result['id']);
            }
        }

        // Handle SEO meta
        if (!empty($data['seo'])) {
            $seo = rext_ai()->seo;
            $seo->update_meta($post_id, $data['seo']);
        }

        // Update tracking
        $this->update_tracking($post_id);

        // Log update
        Rext_AI_Logger::info('Post updated', array(
            'post_id' => $post_id,
            'title'   => get_the_title($post_id),
        ));

        // Return updated post data
        return $this->get_post($post_id);
    }

    /**
     * Delete a post
     *
     * @param int  $post_id The post ID.
     * @param bool $force   Whether to permanently delete.
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    public function delete_post($post_id, $force = false) {
        $post = get_post($post_id);
        if (!$post) {
            return new WP_Error('post_not_found', __('Post not found.', 'rext-ai'), array('status' => 404));
        }

        $title = $post->post_title;

        if ($force) {
            $result = wp_delete_post($post_id, true);
        } else {
            $result = wp_trash_post($post_id);
        }

        if (!$result) {
            Rext_AI_Logger::error('Failed to delete post', array('post_id' => $post_id));
            return new WP_Error('delete_failed', __('Failed to delete post.', 'rext-ai'), array('status' => 500));
        }

        // Update tracking table
        $this->update_tracking_status($post_id, $force ? 'deleted' : 'trashed');

        // Log deletion
        Rext_AI_Logger::info($force ? 'Post deleted' : 'Post trashed', array(
            'post_id' => $post_id,
            'title'   => $title,
        ));

        return true;
    }

    /**
     * Get a single post
     *
     * @param int $post_id The post ID.
     * @return array|WP_Error Post data on success, WP_Error on failure.
     */
    public function get_post($post_id) {
        $post = get_post($post_id);
        if (!$post) {
            return new WP_Error('post_not_found', __('Post not found.', 'rext-ai'), array('status' => 404));
        }

        $featured_image_id = get_post_thumbnail_id($post_id);

        return array(
            'id'              => $post->ID,
            'title'           => $post->post_title,
            'content'         => $post->post_content,
            'excerpt'         => $post->post_excerpt,
            'slug'            => $post->post_name,
            'status'          => $post->post_status,
            'url'             => get_permalink($post_id),
            'edit_url'        => get_edit_post_link($post_id, 'raw'),
            'author'          => array(
                'id'   => intval($post->post_author),
                'name' => get_the_author_meta('display_name', $post->post_author),
            ),
            'categories'      => wp_get_post_categories($post_id, array('fields' => 'all')),
            'tags'            => wp_get_post_tags($post_id, array('fields' => 'all')),
            'featured_image'  => $featured_image_id ? array(
                'id'  => $featured_image_id,
                'url' => wp_get_attachment_url($featured_image_id),
            ) : null,
            'rext_content_id' => get_post_meta($post_id, '_rext_ai_content_id', true),
            'rext_created_at' => get_post_meta($post_id, '_rext_ai_created', true),
            'published_at'    => $post->post_date,
            'modified_at'     => $post->post_modified,
        );
    }

    /**
     * Get list of Rext AI posts
     *
     * @param array $args Query arguments.
     * @return array Posts data with pagination info.
     */
    public function get_posts($args = array()) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'rext_ai_posts';

        $defaults = array(
            'per_page' => 20,
            'page'     => 1,
            'status'   => '',
        );

        $args = wp_parse_args($args, $defaults);

        // Build query
        $where = array('1=1');
        $values = array();

        if (!empty($args['status'])) {
            $where[] = 'status = %s';
            $values[] = $args['status'];
        }

        $where_clause = implode(' AND ', $where);
        $offset = ($args['page'] - 1) * $args['per_page'];

        // Get total count.
        if ( ! empty( $values ) ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $count_query = $wpdb->prepare( "SELECT COUNT(*) FROM `{$table_name}` WHERE {$where_clause}", $values );
        } else {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $count_query = "SELECT COUNT(*) FROM `{$table_name}` WHERE 1=1";
        }
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $total = (int) $wpdb->get_var( $count_query );

        // Get posts.
        $per_page = absint( $args['per_page'] );
        $values[] = $per_page;
        $values[] = $offset;
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $query = $wpdb->prepare(
            "SELECT * FROM `{$table_name}` WHERE {$where_clause} ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $values
        );

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $tracked_posts = $wpdb->get_results( $query );

        // Enrich with WordPress post data
        $posts = array();
        foreach ($tracked_posts as $tracked) {
            $post = get_post($tracked->wp_post_id);
            if ($post) {
                $posts[] = array(
                    'id'              => $post->ID,
                    'title'           => $post->post_title,
                    'status'          => $post->post_status,
                    'url'             => get_permalink($post->ID),
                    'rext_content_id' => $tracked->rext_content_id,
                    'published_at'    => $tracked->published_at,
                    'last_synced_at'  => $tracked->last_synced_at,
                    'created_at'      => $tracked->created_at,
                );
            }
        }

        return array(
            'posts' => $posts,
            'total' => $total,
            'pages' => ceil($total / $args['per_page']),
        );
    }

    /**
     * Resolve author from provided data
     *
     * @param array $data Post data containing author info.
     * @return int Author user ID.
     */
    private function resolve_author($data) {
        // Try author ID first
        if (!empty($data['author'])) {
            $user = get_user_by('ID', intval($data['author']));
            if ($user && user_can($user, 'edit_posts')) {
                return $user->ID;
            }
        }

        // Try author email
        if (!empty($data['author_email'])) {
            $user = get_user_by('email', sanitize_email($data['author_email']));
            if ($user && user_can($user, 'edit_posts')) {
                return $user->ID;
            }
        }

        // Default to first admin
        $admins = get_users(array(
            'role'   => 'administrator',
            'number' => 1,
            'orderby' => 'ID',
            'order'  => 'ASC',
        ));

        if (!empty($admins)) {
            return $admins[0]->ID;
        }

        // Fallback to current user or 1
        return get_current_user_id() ?: 1;
    }

    /**
     * Set post categories
     *
     * @param int   $post_id    The post ID.
     * @param array $categories Array of category names or IDs.
     */
    private function set_categories($post_id, $categories) {
        $category_ids = array();

        foreach ($categories as $category) {
            if (is_numeric($category)) {
                // It's an ID
                $term = get_term(intval($category), 'category');
                if ($term && !is_wp_error($term)) {
                    $category_ids[] = $term->term_id;
                }
            } else {
                // It's a name - find or create
                $term = get_term_by('name', $category, 'category');
                if ($term) {
                    $category_ids[] = $term->term_id;
                } else {
                    // Create new category
                    $result = wp_insert_term(sanitize_text_field($category), 'category');
                    if (!is_wp_error($result)) {
                        $category_ids[] = $result['term_id'];
                        Rext_AI_Logger::info('Category created', array('name' => $category));
                    }
                }
            }
        }

        if (!empty($category_ids)) {
            wp_set_post_categories($post_id, $category_ids);
        }
    }

    /**
     * Parse date string to MySQL format
     *
     * @param string $date_string Date string (ISO 8601 or similar).
     * @return string|false MySQL datetime string or false.
     */
    private function parse_date($date_string) {
        $timestamp = strtotime($date_string);
        if ($timestamp === false) {
            return false;
        }
        return gmdate('Y-m-d H:i:s', $timestamp);
    }

    /**
     * Track post in custom table
     *
     * @param int    $post_id         WordPress post ID.
     * @param string $rext_content_id Rext AI content ID.
     * @param string $status          Post status.
     */
    private function track_post($post_id, $rext_content_id, $status) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'rext_ai_posts';

        $published_at = ($status === 'publish') ? current_time('mysql') : null;

        $wpdb->insert(
            $table_name,
            array(
                'wp_post_id'      => $post_id,
                'rext_content_id' => $rext_content_id,
                'status'          => $status,
                'published_at'    => $published_at,
                'last_synced_at'  => current_time('mysql'),
            ),
            array('%d', '%s', '%s', '%s', '%s')
        );
    }

    /**
     * Update post tracking
     *
     * @param int $post_id WordPress post ID.
     */
    private function update_tracking($post_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'rext_ai_posts';

        $post = get_post($post_id);
        if (!$post) {
            return;
        }

        $wpdb->update(
            $table_name,
            array(
                'status'         => $post->post_status,
                'last_synced_at' => current_time('mysql'),
            ),
            array('wp_post_id' => $post_id),
            array('%s', '%s'),
            array('%d')
        );
    }

    /**
     * Update tracking status
     *
     * @param int    $post_id WordPress post ID.
     * @param string $status  New status.
     */
    private function update_tracking_status($post_id, $status) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'rext_ai_posts';

        $wpdb->update(
            $table_name,
            array('status' => $status),
            array('wp_post_id' => $post_id),
            array('%s'),
            array('%d')
        );
    }

    /**
     * Handle post status transitions
     *
     * @param string  $new_status New post status.
     * @param string  $old_status Old post status.
     * @param WP_Post $post       Post object.
     */
    public function handle_status_transition($new_status, $old_status, $post) {
        // Only handle posts created by Rext AI
        $rext_content_id = get_post_meta($post->ID, '_rext_ai_content_id', true);
        if (empty($rext_content_id)) {
            return;
        }

        // Update tracking
        $this->update_tracking($post->ID);

        // Update published_at when post is published
        if ($new_status === 'publish' && $old_status !== 'publish') {
            global $wpdb;
            $table_name = $wpdb->prefix . 'rext_ai_posts';
            $wpdb->update(
                $table_name,
                array('published_at' => current_time('mysql')),
                array('wp_post_id' => $post->ID),
                array('%s'),
                array('%d')
            );
        }
    }

    /**
     * Get post by Rext content ID
     *
     * @param string $rext_content_id Rext AI content ID.
     * @return int|false WordPress post ID or false if not found.
     */
    public function get_post_by_rext_id($rext_content_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'rext_ai_posts';

        $post_id = $wpdb->get_var($wpdb->prepare(
            "SELECT wp_post_id FROM $table_name WHERE rext_content_id = %s",
            $rext_content_id
        ));

        return $post_id ? intval($post_id) : false;
    }
}
