<?php

declare(strict_types=1);

namespace DevlinkStudios\StarterPlugin\Modules\Frontend;

use DevlinkStudios\StarterPlugin\Contracts\ModuleInterface;
use DevlinkStudios\StarterPlugin\Support\View;

/**
 * Frontend module: shortcode rendering and on-demand frontend assets.
 *
 * Assets are registered on `wp_enqueue_scripts` and only enqueued when the
 * shortcode actually renders, so unrelated pages stay asset-free.
 *
 * @since 1.0.0
 */
class FrontendModule implements ModuleInterface
{
    /**
     * Script/style handle shared by the frontend assets.
     */
    private const HANDLE = 'dls-starter-frontend';

    /**
     * Register all hooks and handlers for the frontend module.
     *
     * @return void
     * @see ModuleInterface::register()
     */
    public function register(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'registerAssets']);
        add_shortcode('dls_starter', [$this, 'renderShortcode']);
    }

    /**
     * Register (but do not enqueue) the frontend assets.
     *
     * @return void
     */
    public function registerAssets(): void
    {
        $cssPath = DLS_STARTER_PATH . 'assets/css/frontend.css';
        $jsPath = DLS_STARTER_PATH . 'assets/js/frontend.js';

        if (file_exists($cssPath)) {
            wp_register_style(
                self::HANDLE,
                DLS_STARTER_URL . 'assets/css/frontend.css',
                [],
                (string) filemtime($cssPath)
            );
        }

        if (!file_exists($jsPath)) {
            return;
        }

        wp_register_script(
            self::HANDLE,
            DLS_STARTER_URL . 'assets/js/frontend.js',
            [],
            (string) filemtime($jsPath),
            true
        );

        wp_localize_script(self::HANDLE, 'dlsStarterFrontend', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'action' => 'dls_starter_action',
            'nonce' => wp_create_nonce('dls_starter_action'),
        ]);
    }

    /**
     * Render the `[dls_starter]` shortcode.
     *
     * @param array<string, mixed>|string $attributes Shortcode attributes.
     * @return string Rendered markup.
     */
    public function renderShortcode(array|string $attributes = []): string
    {
        $attributes = shortcode_atts(
            [
                'title' => '',
            ],
            is_array($attributes) ? $attributes : [],
            'dls_starter'
        );

        wp_enqueue_style(self::HANDLE);
        wp_enqueue_script(self::HANDLE);

        $title = is_string($attributes['title']) ? trim($attributes['title']) : '';

        return View::make('frontend/shortcode', [
            'plugin_name' => __('DLS Starter Plugin', 'dls-starter-plugin'),
            'title' => $title,
        ]);
    }
}
