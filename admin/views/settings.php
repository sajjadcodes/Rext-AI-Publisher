<?php
/**
 * Settings Page View
 *
 * @package Rext_AI
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get current values
$enabled = get_option('rext_ai_enabled', true);
$api_key = get_option('rext_ai_api_key', '');
$permissions = get_option('rext_ai_permissions', array());
$last_connected = get_option('rext_ai_last_connected');

// Auth instance for masked key
$auth = new Rext_AI_Auth();
$masked_key = $auth->get_masked_api_key();

// SEO detection
$seo = rext_ai()->seo;
$detected_seo = $seo ? $seo->detect_plugin() : null;

// Connection status
$admin = rext_ai()->admin;
$connection_status = $admin->get_connection_status();
$stats = $admin->get_stats();

// Default permissions
$default_permissions = array(
    'create_posts'      => __('Create Posts', 'rext-ai'),
    'edit_posts'        => __('Edit Posts', 'rext-ai'),
    'delete_posts'      => __('Delete Posts', 'rext-ai'),
    'upload_media'      => __('Upload Media', 'rext-ai'),
    'manage_categories' => __('Manage Categories', 'rext-ai'),
    'manage_tags'       => __('Manage Tags', 'rext-ai'),
);
?>

<div class="wrap rext-ai-admin">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <!-- Connection Status Banner -->
    <div class="rext-ai-status <?php echo $connection_status['connected'] ? 'rext-ai-status--connected' : 'rext-ai-status--disconnected'; ?>">
        <div class="rext-ai-status__indicator"></div>
        <div class="rext-ai-status__text">
            <?php if ($connection_status['connected']) : ?>
                <strong><?php esc_html_e('Connected', 'rext-ai'); ?></strong>
                <?php if (!empty($connection_status['last_connected_human'])) : ?>
                    <span class="rext-ai-status__time">
                        <?php
                        printf(
                            /* translators: %s: time ago */
                            esc_html__('Last activity: %s', 'rext-ai'),
                            esc_html($connection_status['last_connected_human'])
                        );
                        ?>
                    </span>
                <?php endif; ?>
            <?php else : ?>
                <strong><?php esc_html_e('Not Connected', 'rext-ai'); ?></strong>
                <span class="rext-ai-status__time"><?php esc_html_e('Waiting for connection from Rext AI', 'rext-ai'); ?></span>
            <?php endif; ?>
        </div>
        <div class="rext-ai-status__stats">
            <span><?php printf(esc_html__('%d posts', 'rext-ai'), $stats['total_posts']); ?></span>
            <span><?php printf(esc_html__('%d logs today', 'rext-ai'), $stats['logs_today']); ?></span>
        </div>
    </div>

    <form method="post" action="">
        <?php wp_nonce_field('rext_ai_settings_nonce', 'rext_ai_nonce'); ?>

        <!-- API Configuration -->
        <div class="rext-ai-card">
            <h2><?php esc_html_e('API Configuration', 'rext-ai'); ?></h2>

            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Enable Integration', 'rext-ai'); ?></th>
                    <td>
                        <label class="rext-ai-toggle">
                            <input type="checkbox" name="rext_ai_enabled" value="1" <?php checked($enabled); ?>>
                            <span class="rext-ai-toggle__slider"></span>
                        </label>
                        <p class="description"><?php esc_html_e('Enable or disable the Rext AI integration.', 'rext-ai'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php esc_html_e('Site URL', 'rext-ai'); ?></th>
                    <td>
                        <div class="rext-ai-copy-field">
                            <code id="rext-ai-site-url"><?php echo esc_url(get_site_url()); ?></code>
                            <button type="button" class="button rext-ai-copy-btn" data-target="rext-ai-site-url">
                                <?php esc_html_e('Copy', 'rext-ai'); ?>
                            </button>
                        </div>
                        <p class="description"><?php esc_html_e('Your WordPress site URL for the Rext AI integration.', 'rext-ai'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php esc_html_e('API Key', 'rext-ai'); ?></th>
                    <td>
                        <div class="rext-ai-api-key-wrapper">
                            <div class="rext-ai-api-key-display">
                                <code id="rext-ai-api-key-masked"><?php echo esc_html($masked_key); ?></code>
                                <code id="rext-ai-api-key-full" style="display: none;"><?php echo esc_html($api_key); ?></code>
                            </div>
                            <div class="rext-ai-api-key-actions">
                                <button type="button" class="button rext-ai-toggle-key-btn" id="rext-ai-toggle-key">
                                    <?php esc_html_e('Show', 'rext-ai'); ?>
                                </button>
                                <button type="button" class="button rext-ai-copy-btn" data-target="rext-ai-api-key-full">
                                    <?php esc_html_e('Copy', 'rext-ai'); ?>
                                </button>
                                <button type="button" class="button button-secondary rext-ai-regenerate-btn" id="rext-ai-regenerate-key">
                                    <?php esc_html_e('Regenerate', 'rext-ai'); ?>
                                </button>
                            </div>
                        </div>
                        <p class="description"><?php esc_html_e('Use this API key to authenticate requests from Rext AI.', 'rext-ai'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php esc_html_e('API Endpoint', 'rext-ai'); ?></th>
                    <td>
                        <div class="rext-ai-copy-field">
                            <code id="rext-ai-api-endpoint"><?php echo esc_url(rest_url('rext-ai/v1/')); ?></code>
                            <button type="button" class="button rext-ai-copy-btn" data-target="rext-ai-api-endpoint">
                                <?php esc_html_e('Copy', 'rext-ai'); ?>
                            </button>
                        </div>
                        <p class="description"><?php esc_html_e('The REST API endpoint for Rext AI integration.', 'rext-ai'); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Permissions -->
        <div class="rext-ai-card">
            <h2><?php esc_html_e('Permissions', 'rext-ai'); ?></h2>
            <p class="rext-ai-card__description"><?php esc_html_e('Control what actions Rext AI can perform on your site.', 'rext-ai'); ?></p>

            <div class="rext-ai-permissions-grid">
                <?php foreach ($default_permissions as $key => $label) : ?>
                    <label class="rext-ai-permission">
                        <input type="checkbox"
                               name="rext_ai_permissions[<?php echo esc_attr($key); ?>]"
                               value="1"
                               <?php checked(!empty($permissions[$key])); ?>>
                        <span class="rext-ai-permission__label"><?php echo esc_html($label); ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- SEO Integration -->
        <div class="rext-ai-card">
            <h2><?php esc_html_e('SEO Integration', 'rext-ai'); ?></h2>

            <?php if ($detected_seo) : ?>
                <div class="rext-ai-notice rext-ai-notice--success">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <span>
                        <?php
                        echo wp_kses(
                            sprintf(
                                /* translators: %s: SEO plugin name */
                                __( 'Detected: %s', 'rext-ai' ),
                                '<strong>' . esc_html( $detected_seo['name'] ) . '</strong>'
                            ),
                            array( 'strong' => array() )
                        );
                        ?>
                    </span>
                </div>
                <p class="description">
                    <?php esc_html_e('SEO metadata from Rext AI will be automatically saved using this plugin.', 'rext-ai'); ?>
                </p>
            <?php else : ?>
                <div class="rext-ai-notice rext-ai-notice--info">
                    <span class="dashicons dashicons-info"></span>
                    <span><?php esc_html_e('No supported SEO plugin detected.', 'rext-ai'); ?></span>
                </div>
                <p class="description">
                    <?php esc_html_e('Supported SEO plugins:', 'rext-ai'); ?>
                    <strong>Yoast SEO, Rank Math, All in One SEO, SEOPress</strong>
                </p>
            <?php endif; ?>
        </div>

        <!-- Quick Start Guide -->
        <div class="rext-ai-card rext-ai-card--guide">
            <h2><?php esc_html_e('Quick Start Guide', 'rext-ai'); ?></h2>

            <ol class="rext-ai-steps">
                <li>
                    <span class="rext-ai-step__number">1</span>
                    <span class="rext-ai-step__text"><?php esc_html_e('Copy your Site URL and API Key from above', 'rext-ai'); ?></span>
                </li>
                <li>
                    <span class="rext-ai-step__number">2</span>
                    <span class="rext-ai-step__text"><?php esc_html_e('Go to Rext AI and add a new WordPress connection', 'rext-ai'); ?></span>
                </li>
                <li>
                    <span class="rext-ai-step__number">3</span>
                    <span class="rext-ai-step__text"><?php esc_html_e('Paste your Site URL and API Key in Rext AI', 'rext-ai'); ?></span>
                </li>
                <li>
                    <span class="rext-ai-step__number">4</span>
                    <span class="rext-ai-step__text"><?php esc_html_e('Start publishing content directly from Rext AI!', 'rext-ai'); ?></span>
                </li>
            </ol>

            <p>
                <a href="https://docs.rext.ai/wordpress" target="_blank" class="button button-link">
                    <?php esc_html_e('View Documentation', 'rext-ai'); ?>
                    <span class="dashicons dashicons-external"></span>
                </a>
            </p>
        </div>

        <?php submit_button(__('Save Settings', 'rext-ai')); ?>
    </form>
</div>

<!-- Regenerate Key Modal/Toast -->
<div id="rext-ai-toast" class="rext-ai-toast" style="display: none;">
    <span class="rext-ai-toast__message"></span>
</div>
