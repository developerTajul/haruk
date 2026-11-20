<?php

declare(strict_types=1);

/**
 * Admin settings template.
 *
 * @package DevlinkStudios\StarterPlugin
 *
 * @var string $plugin_name   Plugin display name.
 * @var string $api_key       Currently stored API key.
 * @var string $notice        Notice identifier from the last submission.
 * @var string $save_action   admin-post action handling the submission.
 * @var string $nonce_action  Nonce action for the submission.
 * @var string $nonce_field   Nonce field name for the submission.
 * @var string $api_key_field Form field name of the API key input.
 */

if (!defined('ABSPATH')) {
    exit;
}

$dlsStarterNotices = [
    'saved' => [
        'type' => 'success',
        'message' => __('Settings saved.', 'dls-starter-plugin'),
    ],
    'invalid-api-key' => [
        'type' => 'error',
        'message' => __('The API key could not be saved. It must not exceed 255 characters.', 'dls-starter-plugin'),
    ],
];

$dlsStarterNotice = $dlsStarterNotices[$notice] ?? null;
?>
<div class="wrap dls-starter-admin">
    <h1><?php echo esc_html($plugin_name); ?></h1>

    <?php if ($dlsStarterNotice !== null) : ?>
        <div class="notice notice-<?php echo esc_attr($dlsStarterNotice['type']); ?> is-dismissible">
            <p><?php echo esc_html($dlsStarterNotice['message']); ?></p>
        </div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="<?php echo esc_attr($save_action); ?>" />
        <?php wp_nonce_field($nonce_action, $nonce_field); ?>

        <h2><?php esc_html_e('API Configuration', 'dls-starter-plugin'); ?></h2>
        <p class="description">
            <?php esc_html_e('Credentials used to authenticate outbound integrations and inbound webhooks.', 'dls-starter-plugin'); ?>
        </p>

        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">
                    <label for="<?php echo esc_attr($api_key_field); ?>"><?php esc_html_e('API Key', 'dls-starter-plugin'); ?></label>
                </th>
                <td>
                    <input
                        type="text"
                        name="<?php echo esc_attr($api_key_field); ?>"
                        id="<?php echo esc_attr($api_key_field); ?>"
                        class="regular-text"
                        value="<?php echo esc_attr($api_key); ?>"
                        autocomplete="off"
                    />
                    <p class="description">
                        <?php esc_html_e('Leave empty to disable API-key authentication for external webhook callers.', 'dls-starter-plugin'); ?>
                    </p>
                </td>
            </tr>
        </table>

        <?php submit_button(__('Save Changes', 'dls-starter-plugin')); ?>
    </form>

    <hr />

    <h2><?php esc_html_e('Connection Test', 'dls-starter-plugin'); ?></h2>
    <p class="description">
        <?php esc_html_e('Sends a nonce-protected request to the plugin AJAX endpoint to confirm it is reachable.', 'dls-starter-plugin'); ?>
    </p>
    <p>
        <button type="button" class="button" id="dls-starter-test-ajax">
            <?php esc_html_e('Run AJAX health check', 'dls-starter-plugin'); ?>
        </button>
        <span id="dls-starter-ajax-result" class="dls-starter-ajax-result" aria-live="polite"></span>
    </p>
</div>
