<?php

declare(strict_types=1);

/**
 * Frontend shortcode template.
 *
 * @package DevlinkStudios\StarterPlugin
 *
 * @var string $plugin_name Plugin display name.
 * @var string $title       Optional shortcode title attribute.
 */

if (!defined('ABSPATH')) {
    exit;
}

$dlsStarterHeading = $title !== '' ? $title : $plugin_name;
?>
<div class="dls-starter-frontend">
    <h2 class="dls-starter-frontend__title"><?php echo esc_html($dlsStarterHeading); ?></h2>
    <p class="dls-starter-frontend__content">
        <?php esc_html_e('This is the frontend shortcode output of the DLS Starter Plugin.', 'dls-starter-plugin'); ?>
    </p>
</div>
