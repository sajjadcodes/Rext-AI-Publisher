<?php
/**
 * Published Content Page View
 *
 * @package Rext_AI
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$posts_table = $wpdb->prefix . 'rext_ai_posts';

// Get filter parameters
$current_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$per_page = 20;

// Build query
$where = array('1=1');
$values = array();

if (!empty($current_status)) {
    $where[] = 'r.status = %s';
    $values[] = $current_status;
}

$where_clause = implode(' AND ', $where);
$offset = ($current_page - 1) * $per_page;

// Get total count
$count_query = "SELECT COUNT(*) FROM $posts_table r WHERE $where_clause";
if (!empty($values)) {
    $count_query = $wpdb->prepare($count_query, $values);
}
$total_posts = (int) $wpdb->get_var($count_query);
$total_pages = ceil($total_posts / $per_page);

// Get posts
$query = "SELECT r.*, p.post_title, p.post_status as wp_status
          FROM $posts_table r
          LEFT JOIN {$wpdb->posts} p ON r.wp_post_id = p.ID
          WHERE $where_clause
          ORDER BY r.created_at DESC
          LIMIT %d OFFSET %d";
$values[] = $per_page;
$values[] = $offset;
$query = $wpdb->prepare($query, $values);
$posts = $wpdb->get_results($query);

// Status colors
$status_colors = array(
    'publish' => 'rext-ai-status-badge--publish',
    'draft'   => 'rext-ai-status-badge--draft',
    'pending' => 'rext-ai-status-badge--pending',
    'private' => 'rext-ai-status-badge--private',
    'future'  => 'rext-ai-status-badge--future',
    'trash'   => 'rext-ai-status-badge--trash',
    'trashed' => 'rext-ai-status-badge--trash',
    'deleted' => 'rext-ai-status-badge--deleted',
);

// Get status counts for filters
$status_counts = $wpdb->get_results(
    "SELECT status, COUNT(*) as count FROM $posts_table GROUP BY status",
    OBJECT_K
);
?>

<div class="wrap rext-ai-admin">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <!-- Status Filters -->
    <ul class="subsubsub rext-ai-status-filters">
        <li>
            <a href="<?php echo esc_url(admin_url('admin.php?page=rext-ai-content')); ?>"
               class="<?php echo empty($current_status) ? 'current' : ''; ?>">
                <?php esc_html_e('All', 'rext-ai'); ?>
                <span class="count">(<?php echo esc_html($total_posts); ?>)</span>
            </a> |
        </li>
        <?php
        $statuses = array('publish', 'draft', 'pending', 'trashed');
        foreach ($statuses as $index => $status) :
            $count = isset($status_counts[$status]) ? $status_counts[$status]->count : 0;
            if ($count > 0) :
                ?>
                <li>
                    <a href="<?php echo esc_url(add_query_arg('status', $status, admin_url('admin.php?page=rext-ai-content'))); ?>"
                       class="<?php echo $current_status === $status ? 'current' : ''; ?>">
                        <?php echo esc_html(ucfirst($status)); ?>
                        <span class="count">(<?php echo esc_html($count); ?>)</span>
                    </a>
                    <?php if ($index < count($statuses) - 1) : ?> |<?php endif; ?>
                </li>
            <?php
            endif;
        endforeach;
        ?>
    </ul>

    <!-- Content Table -->
    <div class="rext-ai-card" style="margin-top: 20px;">
        <?php if (empty($posts)) : ?>
            <div class="rext-ai-empty-state">
                <span class="dashicons dashicons-media-text"></span>
                <p><?php esc_html_e('No content published via Rext AI yet.', 'rext-ai'); ?></p>
                <p class="description">
                    <?php esc_html_e('Content published from Rext AI will appear here.', 'rext-ai'); ?>
                </p>
            </div>
        <?php else : ?>
            <table class="wp-list-table widefat fixed striped rext-ai-content-table">
                <thead>
                    <tr>
                        <th class="column-title"><?php esc_html_e('Title', 'rext-ai'); ?></th>
                        <th class="column-status" style="width: 100px;"><?php esc_html_e('Status', 'rext-ai'); ?></th>
                        <th class="column-rext-id" style="width: 200px;"><?php esc_html_e('Rext ID', 'rext-ai'); ?></th>
                        <th class="column-published" style="width: 150px;"><?php esc_html_e('Published', 'rext-ai'); ?></th>
                        <th class="column-synced" style="width: 150px;"><?php esc_html_e('Last Synced', 'rext-ai'); ?></th>
                        <th class="column-actions" style="width: 100px;"><?php esc_html_e('Actions', 'rext-ai'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($posts as $post) : ?>
                        <tr>
                            <td class="column-title">
                                <?php if ($post->post_title) : ?>
                                    <strong>
                                        <a href="<?php echo esc_url(get_edit_post_link($post->wp_post_id)); ?>">
                                            <?php echo esc_html($post->post_title); ?>
                                        </a>
                                    </strong>
                                <?php else : ?>
                                    <span class="rext-ai-deleted-post">
                                        <?php esc_html_e('(Post Deleted)', 'rext-ai'); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="column-status">
                                <?php
                                $display_status = $post->wp_status ?: $post->status;
                                $status_class = $status_colors[$display_status] ?? '';
                                ?>
                                <span class="rext-ai-status-badge <?php echo esc_attr($status_class); ?>">
                                    <?php echo esc_html(ucfirst($display_status)); ?>
                                </span>
                            </td>
                            <td class="column-rext-id">
                                <code class="rext-ai-rext-id" title="<?php echo esc_attr($post->rext_content_id); ?>">
                                    <?php
                                    $rext_id = $post->rext_content_id;
                                    echo esc_html(strlen($rext_id) > 20 ? substr($rext_id, 0, 20) . '...' : $rext_id);
                                    ?>
                                </code>
                            </td>
                            <td class="column-published">
                                <?php if ($post->published_at) : ?>
                                    <span title="<?php echo esc_attr($post->published_at); ?>">
                                        <?php echo esc_html(human_time_diff(strtotime($post->published_at), time())); ?>
                                        <?php esc_html_e('ago', 'rext-ai'); ?>
                                    </span>
                                <?php else : ?>
                                    <span class="rext-ai-not-published">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="column-synced">
                                <?php if ($post->last_synced_at) : ?>
                                    <span title="<?php echo esc_attr($post->last_synced_at); ?>">
                                        <?php echo esc_html(human_time_diff(strtotime($post->last_synced_at), time())); ?>
                                        <?php esc_html_e('ago', 'rext-ai'); ?>
                                    </span>
                                <?php else : ?>
                                    <span>—</span>
                                <?php endif; ?>
                            </td>
                            <td class="column-actions">
                                <?php if ($post->post_title) : ?>
                                    <a href="<?php echo esc_url(get_permalink($post->wp_post_id)); ?>"
                                       class="button button-small"
                                       target="_blank"
                                       title="<?php esc_attr_e('View', 'rext-ai'); ?>">
                                        <span class="dashicons dashicons-visibility"></span>
                                    </a>
                                    <a href="<?php echo esc_url(get_edit_post_link($post->wp_post_id)); ?>"
                                       class="button button-small"
                                       title="<?php esc_attr_e('Edit', 'rext-ai'); ?>">
                                        <span class="dashicons dashicons-edit"></span>
                                    </a>
                                <?php else : ?>
                                    <span class="rext-ai-no-actions">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php if ($total_pages > 1) : ?>
                <div class="rext-ai-pagination">
                    <span class="rext-ai-pagination__info">
                        <?php
                        printf(
                            /* translators: 1: first item, 2: last item, 3: total items */
                            esc_html__('Showing %1$d-%2$d of %3$d posts', 'rext-ai'),
                            (($current_page - 1) * $per_page) + 1,
                            min($current_page * $per_page, $total_posts),
                            $total_posts
                        );
                        ?>
                    </span>

                    <div class="rext-ai-pagination__links">
                        <?php
                        $base_url = add_query_arg(array(
                            'page'   => 'rext-ai-content',
                            'status' => $current_status,
                        ), admin_url('admin.php'));

                        // Previous
                        if ($current_page > 1) :
                            ?>
                            <a href="<?php echo esc_url(add_query_arg('paged', $current_page - 1, $base_url)); ?>" class="button">
                                &laquo; <?php esc_html_e('Previous', 'rext-ai'); ?>
                            </a>
                        <?php endif; ?>

                        <span class="rext-ai-pagination__current">
                            <?php
                            printf(
                                /* translators: 1: current page, 2: total pages */
                                esc_html__('Page %1$d of %2$d', 'rext-ai'),
                                $current_page,
                                $total_pages
                            );
                            ?>
                        </span>

                        <?php
                        // Next
                        if ($current_page < $total_pages) :
                            ?>
                            <a href="<?php echo esc_url(add_query_arg('paged', $current_page + 1, $base_url)); ?>" class="button">
                                <?php esc_html_e('Next', 'rext-ai'); ?> &raquo;
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
