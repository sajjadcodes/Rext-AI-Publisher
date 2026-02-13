<?php
/**
 * Activity Logs Page View
 *
 * @package Rext_AI
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get filter parameters.
$current_level  = isset( $_GET['level'] ) ? sanitize_text_field( wp_unslash( $_GET['level'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$current_action = isset( $_GET['action_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['action_filter'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$current_page   = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$per_page = 20;

// Get logs
$logs = Rext_AI_Logger::get_logs(array(
    'per_page' => $per_page,
    'page'     => $current_page,
    'level'    => $current_level,
    'action'   => $current_action,
));

$total_logs = Rext_AI_Logger::get_logs_count(array(
    'level'  => $current_level,
    'action' => $current_action,
));

$total_pages = ceil($total_logs / $per_page);

// Get unique actions for filter
$unique_actions = Rext_AI_Logger::get_unique_actions();

// Log level colors
$level_colors = array(
    'debug'   => 'rext-ai-level--debug',
    'info'    => 'rext-ai-level--info',
    'warning' => 'rext-ai-level--warning',
    'error'   => 'rext-ai-level--error',
);
?>

<div class="wrap rext-ai-admin">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <!-- Filters and Actions -->
    <div class="rext-ai-logs-toolbar">
        <form method="get" class="rext-ai-logs-filters">
            <input type="hidden" name="page" value="rext-ai-logs">

            <select name="level" class="rext-ai-filter-select">
                <option value=""><?php esc_html_e('All Levels', 'rext-ai'); ?></option>
                <option value="debug" <?php selected($current_level, 'debug'); ?>><?php esc_html_e('Debug', 'rext-ai'); ?></option>
                <option value="info" <?php selected($current_level, 'info'); ?>><?php esc_html_e('Info', 'rext-ai'); ?></option>
                <option value="warning" <?php selected($current_level, 'warning'); ?>><?php esc_html_e('Warning', 'rext-ai'); ?></option>
                <option value="error" <?php selected($current_level, 'error'); ?>><?php esc_html_e('Error', 'rext-ai'); ?></option>
            </select>

            <select name="action_filter" class="rext-ai-filter-select">
                <option value=""><?php esc_html_e('All Actions', 'rext-ai'); ?></option>
                <?php foreach ($unique_actions as $action) : ?>
                    <option value="<?php echo esc_attr($action); ?>" <?php selected($current_action, $action); ?>>
                        <?php echo esc_html($action); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="button"><?php esc_html_e('Filter', 'rext-ai'); ?></button>

            <?php if ($current_level || $current_action) : ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=rext-ai-logs')); ?>" class="button">
                    <?php esc_html_e('Clear Filters', 'rext-ai'); ?>
                </a>
            <?php endif; ?>
        </form>

        <div class="rext-ai-logs-actions">
            <button type="button" class="button" id="rext-ai-export-logs">
                <span class="dashicons dashicons-download"></span>
                <?php esc_html_e('Export CSV', 'rext-ai'); ?>
            </button>
            <button type="button" class="button button-secondary" id="rext-ai-clear-logs">
                <span class="dashicons dashicons-trash"></span>
                <?php esc_html_e('Clear Logs', 'rext-ai'); ?>
            </button>
        </div>
    </div>

    <!-- Logs Table -->
    <div class="rext-ai-card">
        <?php if (empty($logs)) : ?>
            <div class="rext-ai-empty-state">
                <span class="dashicons dashicons-format-aside"></span>
                <p><?php esc_html_e('No activity logs found.', 'rext-ai'); ?></p>
            </div>
        <?php else : ?>
            <table class="wp-list-table widefat fixed striped rext-ai-logs-table">
                <thead>
                    <tr>
                        <th class="column-time" style="width: 150px;"><?php esc_html_e('Time', 'rext-ai'); ?></th>
                        <th class="column-level" style="width: 80px;"><?php esc_html_e('Level', 'rext-ai'); ?></th>
                        <th class="column-action" style="width: 150px;"><?php esc_html_e('Action', 'rext-ai'); ?></th>
                        <th class="column-message"><?php esc_html_e('Message', 'rext-ai'); ?></th>
                        <th class="column-ip" style="width: 120px;"><?php esc_html_e('IP Address', 'rext-ai'); ?></th>
                        <th class="column-details" style="width: 80px;"><?php esc_html_e('Details', 'rext-ai'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log) : ?>
                        <tr class="rext-ai-log-row">
                            <td class="column-time">
                                <span class="rext-ai-log-time" title="<?php echo esc_attr($log->created_at); ?>">
                                    <?php echo esc_html(human_time_diff(strtotime($log->created_at), time())); ?>
                                    <?php esc_html_e('ago', 'rext-ai'); ?>
                                </span>
                            </td>
                            <td class="column-level">
                                <span class="rext-ai-level-badge <?php echo esc_attr($level_colors[$log->level] ?? ''); ?>">
                                    <?php echo esc_html(ucfirst($log->level)); ?>
                                </span>
                            </td>
                            <td class="column-action">
                                <code class="rext-ai-action-code"><?php echo esc_html($log->action); ?></code>
                            </td>
                            <td class="column-message">
                                <?php echo esc_html($log->message); ?>
                            </td>
                            <td class="column-ip">
                                <code><?php echo esc_html($log->ip_address ?: '-'); ?></code>
                            </td>
                            <td class="column-details">
                                <?php if (!empty($log->data)) : ?>
                                    <button type="button"
                                            class="button button-small rext-ai-toggle-data"
                                            data-log-id="<?php echo esc_attr($log->id); ?>">
                                        <span class="dashicons dashicons-arrow-down-alt2"></span>
                                    </button>
                                <?php else : ?>
                                    <span class="rext-ai-no-data">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php if (!empty($log->data)) : ?>
                            <tr class="rext-ai-log-data-row" id="rext-ai-data-<?php echo esc_attr($log->id); ?>" style="display: none;">
                                <td colspan="6">
                                    <div class="rext-ai-log-data">
                                        <pre><?php echo esc_html(wp_json_encode(json_decode($log->data), JSON_PRETTY_PRINT)); ?></pre>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
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
                            esc_html__('Showing %1$d-%2$d of %3$d logs', 'rext-ai'),
                            (($current_page - 1) * $per_page) + 1,
                            min($current_page * $per_page, $total_logs),
                            $total_logs
                        );
                        ?>
                    </span>

                    <div class="rext-ai-pagination__links">
                        <?php
                        $base_url = add_query_arg(array(
                            'page'          => 'rext-ai-logs',
                            'level'         => $current_level,
                            'action_filter' => $current_action,
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

<!-- Toast Notification -->
<div id="rext-ai-toast" class="rext-ai-toast" style="display: none;">
    <span class="rext-ai-toast__message"></span>
</div>
