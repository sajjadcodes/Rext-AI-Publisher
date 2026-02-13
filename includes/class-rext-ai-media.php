<?php
/**
 * Rext AI Media Class
 *
 * Handles media uploads and sideloading.
 *
 * @package Rext_AI
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Rext_AI_Media
 *
 * Manages media operations for the Rext AI plugin.
 */
class Rext_AI_Media {

    /**
     * Allowed mime types for upload
     *
     * @var array
     */
    private $allowed_types = array(
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/svg+xml',
        'application/pdf',
        'video/mp4',
        'video/webm',
        'audio/mpeg',
        'audio/wav',
    );

    /**
     * Constructor
     */
    public function __construct() {
        // Load required files for media handling
        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }
        if (!function_exists('wp_generate_attachment_metadata')) {
            require_once(ABSPATH . 'wp-admin/includes/image.php');
        }
        if (!function_exists('media_handle_sideload')) {
            require_once(ABSPATH . 'wp-admin/includes/media.php');
        }
    }

    /**
     * Upload media from file upload
     *
     * @param array $files  Files from request ($_FILES).
     * @param array $params Additional parameters.
     * @return array|WP_Error Media data on success, WP_Error on failure.
     */
    public function upload_media($files, $params = array()) {
        if (empty($files) || empty($files['file'])) {
            return new WP_Error('no_file', __('No file provided.', 'rext-ai'), array('status' => 400));
        }

        $file = $files['file'];

        // Validate file
        $validation = $this->validate_upload($file);
        if (is_wp_error($validation)) {
            return $validation;
        }

        // Handle upload
        $upload_overrides = array(
            'test_form' => false,
            'mimes'     => $this->get_allowed_mimes(),
        );

        $uploaded = wp_handle_upload($file, $upload_overrides);

        if (isset($uploaded['error'])) {
            Rext_AI_Logger::error('Media upload failed', array('error' => $uploaded['error']));
            return new WP_Error('upload_failed', $uploaded['error'], array('status' => 400));
        }

        // Create attachment
        $attachment_id = $this->create_attachment($uploaded, $params);

        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }

        Rext_AI_Logger::info('Media uploaded', array(
            'id'       => $attachment_id,
            'filename' => basename($uploaded['file']),
        ));

        return $this->get_single_media($attachment_id);
    }

    /**
     * Sideload media from URL
     *
     * @param array $params Parameters including 'url'.
     * @return array|WP_Error Media data on success, WP_Error on failure.
     */
    public function sideload_media($params) {
        if (empty($params['url'])) {
            return new WP_Error('no_url', __('URL is required.', 'rext-ai'), array('status' => 400));
        }

        $url = esc_url_raw($params['url']);

        // Validate URL
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return new WP_Error('invalid_url', __('Invalid URL provided.', 'rext-ai'), array('status' => 400));
        }

        // Download file to temp location
        $temp_file = download_url($url, 30);

        if (is_wp_error($temp_file)) {
            Rext_AI_Logger::error('Media sideload failed', array(
                'url'   => $url,
                'error' => $temp_file->get_error_message(),
            ));
            return new WP_Error('download_failed', $temp_file->get_error_message(), array('status' => 400));
        }

        // Get filename from URL or params
        $filename = !empty($params['filename'])
            ? sanitize_file_name($params['filename'])
            : $this->get_filename_from_url($url);

        // Prepare file array for sideloading
        $file_array = array(
            'name'     => $filename,
            'tmp_name' => $temp_file,
        );

        // Validate mime type
        $file_info = wp_check_filetype($filename);
        if (!$this->is_allowed_type($file_info['type'])) {
            @unlink($temp_file);
            return new WP_Error('invalid_type', __('File type not allowed.', 'rext-ai'), array('status' => 400));
        }

        // Get post ID if provided
        $post_id = !empty($params['post_id']) ? intval($params['post_id']) : 0;

        // Sideload the file
        $attachment_id = media_handle_sideload($file_array, $post_id);

        // Clean up temp file if still exists
        if (file_exists($temp_file)) {
            @unlink($temp_file);
        }

        if (is_wp_error($attachment_id)) {
            Rext_AI_Logger::error('Media sideload failed', array(
                'url'   => $url,
                'error' => $attachment_id->get_error_message(),
            ));
            return $attachment_id;
        }

        // Update attachment metadata if provided
        if (!empty($params['title'])) {
            wp_update_post(array(
                'ID'         => $attachment_id,
                'post_title' => sanitize_text_field($params['title']),
            ));
        }

        if (!empty($params['alt_text'])) {
            update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field($params['alt_text']));
        }

        if (!empty($params['caption'])) {
            wp_update_post(array(
                'ID'           => $attachment_id,
                'post_excerpt' => sanitize_textarea_field($params['caption']),
            ));
        }

        if (!empty($params['description'])) {
            wp_update_post(array(
                'ID'           => $attachment_id,
                'post_content' => sanitize_textarea_field($params['description']),
            ));
        }

        Rext_AI_Logger::info('Media sideloaded', array(
            'id'  => $attachment_id,
            'url' => $url,
        ));

        return $this->get_single_media($attachment_id);
    }

    /**
     * Get list of media
     *
     * @param array $args Query arguments.
     * @return array Media data with pagination info.
     */
    public function get_media($args = array()) {
        $defaults = array(
            'per_page' => 20,
            'page'     => 1,
            'type'     => '',
        );

        $args = wp_parse_args($args, $defaults);

        $query_args = array(
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'posts_per_page' => $args['per_page'],
            'paged'          => $args['page'],
            'orderby'        => 'date',
            'order'          => 'DESC',
        );

        // Filter by mime type if specified
        if (!empty($args['type'])) {
            $query_args['post_mime_type'] = sanitize_text_field($args['type']);
        }

        $query = new WP_Query($query_args);

        $items = array();
        foreach ($query->posts as $attachment) {
            $items[] = $this->format_media_item($attachment);
        }

        return array(
            'items' => $items,
            'total' => $query->found_posts,
            'pages' => $query->max_num_pages,
        );
    }

    /**
     * Get single media item
     *
     * @param int $attachment_id Attachment ID.
     * @return array|WP_Error Media data on success, WP_Error on failure.
     */
    public function get_single_media($attachment_id) {
        $attachment = get_post($attachment_id);

        if (!$attachment || $attachment->post_type !== 'attachment') {
            return new WP_Error('not_found', __('Media not found.', 'rext-ai'), array('status' => 404));
        }

        return $this->format_media_item($attachment);
    }

    /**
     * Format media item for API response
     *
     * @param WP_Post $attachment Attachment post object.
     * @return array Formatted media data.
     */
    private function format_media_item($attachment) {
        $metadata = wp_get_attachment_metadata($attachment->ID);
        $url = wp_get_attachment_url($attachment->ID);

        $data = array(
            'id'          => $attachment->ID,
            'title'       => $attachment->post_title,
            'filename'    => basename(get_attached_file($attachment->ID)),
            'url'         => $url,
            'alt_text'    => get_post_meta($attachment->ID, '_wp_attachment_image_alt', true),
            'caption'     => $attachment->post_excerpt,
            'description' => $attachment->post_content,
            'mime_type'   => $attachment->post_mime_type,
            'uploaded_at' => $attachment->post_date,
        );

        // Add image-specific data
        if (wp_attachment_is_image($attachment->ID)) {
            $data['sizes'] = array();

            // Get all registered image sizes
            $sizes = get_intermediate_image_sizes();
            foreach ($sizes as $size) {
                $image = wp_get_attachment_image_src($attachment->ID, $size);
                if ($image) {
                    $data['sizes'][$size] = array(
                        'url'    => $image[0],
                        'width'  => $image[1],
                        'height' => $image[2],
                    );
                }
            }

            // Add original dimensions
            if (!empty($metadata['width']) && !empty($metadata['height'])) {
                $data['width'] = $metadata['width'];
                $data['height'] = $metadata['height'];
            }
        }

        // Add file size
        $file_path = get_attached_file($attachment->ID);
        if (file_exists($file_path)) {
            $data['file_size'] = filesize($file_path);
        }

        return $data;
    }

    /**
     * Create attachment from uploaded file
     *
     * @param array $uploaded Upload result from wp_handle_upload.
     * @param array $params   Additional parameters.
     * @return int|WP_Error Attachment ID on success, WP_Error on failure.
     */
    private function create_attachment($uploaded, $params = array()) {
        $file_path = $uploaded['file'];
        $file_type = $uploaded['type'];
        $file_url = $uploaded['url'];

        // Prepare attachment data
        $attachment = array(
            'guid'           => $file_url,
            'post_mime_type' => $file_type,
            'post_title'     => !empty($params['title'])
                ? sanitize_text_field($params['title'])
                : preg_replace('/\.[^.]+$/', '', basename($file_path)),
            'post_content'   => !empty($params['description'])
                ? sanitize_textarea_field($params['description'])
                : '',
            'post_excerpt'   => !empty($params['caption'])
                ? sanitize_textarea_field($params['caption'])
                : '',
            'post_status'    => 'inherit',
        );

        // Insert attachment
        $attachment_id = wp_insert_attachment($attachment, $file_path);

        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }

        // Generate attachment metadata
        $metadata = wp_generate_attachment_metadata($attachment_id, $file_path);
        wp_update_attachment_metadata($attachment_id, $metadata);

        // Set alt text if provided
        if (!empty($params['alt_text'])) {
            update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field($params['alt_text']));
        }

        // Attach to post if specified
        if (!empty($params['post_id'])) {
            wp_update_post(array(
                'ID'          => $attachment_id,
                'post_parent' => intval($params['post_id']),
            ));
        }

        return $attachment_id;
    }

    /**
     * Validate uploaded file
     *
     * @param array $file File data from $_FILES.
     * @return bool|WP_Error True if valid, WP_Error otherwise.
     */
    private function validate_upload($file) {
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $error_messages = array(
                UPLOAD_ERR_INI_SIZE   => __('File exceeds server upload limit.', 'rext-ai'),
                UPLOAD_ERR_FORM_SIZE  => __('File exceeds form upload limit.', 'rext-ai'),
                UPLOAD_ERR_PARTIAL    => __('File was only partially uploaded.', 'rext-ai'),
                UPLOAD_ERR_NO_FILE    => __('No file was uploaded.', 'rext-ai'),
                UPLOAD_ERR_NO_TMP_DIR => __('Missing temporary folder.', 'rext-ai'),
                UPLOAD_ERR_CANT_WRITE => __('Failed to write file to disk.', 'rext-ai'),
                UPLOAD_ERR_EXTENSION  => __('File upload stopped by extension.', 'rext-ai'),
            );

            $message = isset($error_messages[$file['error']])
                ? $error_messages[$file['error']]
                : __('Unknown upload error.', 'rext-ai');

            return new WP_Error('upload_error', $message, array('status' => 400));
        }

        // Check mime type
        $file_info = wp_check_filetype($file['name']);
        if (!$this->is_allowed_type($file_info['type'])) {
            return new WP_Error('invalid_type', __('File type not allowed.', 'rext-ai'), array('status' => 400));
        }

        // Check file size (max 50MB)
        $max_size = 50 * 1024 * 1024;
        if ($file['size'] > $max_size) {
            return new WP_Error('file_too_large', __('File size exceeds 50MB limit.', 'rext-ai'), array('status' => 400));
        }

        return true;
    }

    /**
     * Check if mime type is allowed
     *
     * @param string $mime_type Mime type to check.
     * @return bool True if allowed.
     */
    private function is_allowed_type($mime_type) {
        if (empty($mime_type)) {
            return false;
        }

        // Also check WordPress allowed mimes
        $wp_allowed = get_allowed_mime_types();

        return in_array($mime_type, $this->allowed_types) || in_array($mime_type, $wp_allowed);
    }

    /**
     * Get allowed mime types array for wp_handle_upload
     *
     * @return array Allowed mime types.
     */
    private function get_allowed_mimes() {
        $mimes = array();
        $wp_mimes = get_allowed_mime_types();

        foreach ($wp_mimes as $ext => $mime) {
            if (in_array($mime, $this->allowed_types) || strpos($mime, 'image/') === 0) {
                $mimes[$ext] = $mime;
            }
        }

        return $mimes;
    }

    /**
     * Extract filename from URL
     *
     * @param string $url URL to extract filename from.
     * @return string Filename.
     */
    private function get_filename_from_url($url) {
        $path = wp_parse_url($url, PHP_URL_PATH);
        $filename = basename($path);

        // Remove query strings from filename
        if (strpos($filename, '?') !== false) {
            $filename = substr($filename, 0, strpos($filename, '?'));
        }

        // Ensure filename has extension
        if (empty(pathinfo($filename, PATHINFO_EXTENSION))) {
            // Try to determine from content type
            $filename .= '.jpg'; // Default fallback
        }

        return sanitize_file_name($filename);
    }

    /**
     * Delete media
     *
     * @param int  $attachment_id Attachment ID.
     * @param bool $force         Whether to bypass trash.
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    public function delete_media($attachment_id, $force = false) {
        $attachment = get_post($attachment_id);

        if (!$attachment || $attachment->post_type !== 'attachment') {
            return new WP_Error('not_found', __('Media not found.', 'rext-ai'), array('status' => 404));
        }

        $result = wp_delete_attachment($attachment_id, $force);

        if (!$result) {
            return new WP_Error('delete_failed', __('Failed to delete media.', 'rext-ai'), array('status' => 500));
        }

        Rext_AI_Logger::info('Media deleted', array('id' => $attachment_id));

        return true;
    }
}
