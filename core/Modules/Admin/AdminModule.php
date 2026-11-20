<?php

declare(strict_types=1);

namespace DevlinkStudios\StarterPlugin\Modules\Admin;

use DevlinkStudios\StarterPlugin\Contracts\ModuleInterface;
use DevlinkStudios\StarterPlugin\Support\FormValidator;
use DevlinkStudios\StarterPlugin\Support\Options;
use DevlinkStudios\StarterPlugin\Support\View;

/**
 * Admin module: settings screen, submission handling, and admin assets.
 *
 * The settings screen uses a single option (`dls_starter_api_key`) and a single
 * submission path (`admin_post_dls_starter_save_settings`). See
 * docs/ai-reports/DECISIONS.md for the rationale behind bypassing the Settings
 * API screen plumbing.
 *
 * @since 1.0.0
 */
class AdminModule implements ModuleInterface
{
    /**
     * Admin page slug shared by the menu entry and the settings screen.
     */
    private const MENU_SLUG = 'dls-starter-plugin';

    /**
     * admin-post action used by the settings form.
     */
    private const SAVE_ACTION = 'dls_starter_save_settings';

    /**
     * Nonce action for the settings form.
     */
    private const NONCE_ACTION = 'dls_starter_save_settings';

    /**
     * Nonce field name for the settings form.
     */
    private const NONCE_FIELD = 'dls_starter_nonce';

    /**
     * Form field name of the API key input.
     */
    private const API_KEY_FIELD = 'dls_starter_api_key';

    /**
     * Query argument carrying the submission notice identifier.
     */
    private const NOTICE_ARG = 'dls_starter_notice';

    /**
     * Admin page hook suffixes owned by this module.
     *
     * @var list<string>
     */
    private array $hookSuffixes = [];

    /**
     * Create a new AdminModule instance.
     *
     * @param Options|null $options Option accessor; defaults to a new instance.
     */
    public function __construct(private readonly Options $options = new Options())
    {
    }

    /**
     * Register all hooks and handlers for the admin module.
     *
     * @return void
     * @see ModuleInterface::register()
     */
    public function register(): void
    {
        add_action('admin_menu', [$this, 'addAdminMenu']);
        add_action('admin_post_' . self::SAVE_ACTION, [$this, 'handleSaveSettings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
    }

    /**
     * Register the top-level menu entry and its settings screen.
     *
     * @return void
     */
    public function addAdminMenu(): void
    {
        $this->hookSuffixes[] = (string) add_menu_page(
            __('DLS Starter Plugin', 'dls-starter-plugin'),
            __('DLS Starter', 'dls-starter-plugin'),
            'manage_options',
            self::MENU_SLUG,
            [$this, 'renderSettingsPage'],
            'dashicons-admin-generic',
            6
        );

        // Reusing the parent slug replaces the auto-generated first submenu item,
        // so the screen has exactly one canonical URL.
        $this->hookSuffixes[] = (string) add_submenu_page(
            self::MENU_SLUG,
            __('DLS Starter Settings', 'dls-starter-plugin'),
            __('Settings', 'dls-starter-plugin'),
            'manage_options',
            self::MENU_SLUG,
            [$this, 'renderSettingsPage']
        );
    }

    /**
     * Enqueue admin assets on plugin screens only.
     *
     * @param string $hookSuffix Current admin page hook suffix.
     * @return void
     */
    public function enqueueAdminAssets(string $hookSuffix = ''): void
    {
        if (!in_array($hookSuffix, $this->hookSuffixes, true)) {
            return;
        }

        $cssPath = DLS_STARTER_PATH . 'assets/css/admin.css';
        $jsPath = DLS_STARTER_PATH . 'assets/js/admin.js';

        if (file_exists($cssPath)) {
            wp_enqueue_style(
                'dls-starter-admin',
                DLS_STARTER_URL . 'assets/css/admin.css',
                [],
                (string) filemtime($cssPath)
            );
        }

        if (!file_exists($jsPath)) {
            return;
        }

        wp_enqueue_script(
            'dls-starter-admin',
            DLS_STARTER_URL . 'assets/js/admin.js',
            [],
            (string) filemtime($jsPath),
            true
        );

        wp_localize_script('dls-starter-admin', 'dlsStarterAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'action' => 'dls_starter_action',
            'nonce' => wp_create_nonce('dls_starter_action'),
            'i18n' => [
                'running' => __('Running…', 'dls-starter-plugin'),
                'success' => __('AJAX endpoint responded successfully.', 'dls-starter-plugin'),
                'failure' => __('The AJAX endpoint did not respond as expected.', 'dls-starter-plugin'),
            ],
        ]);
    }

    /**
     * Persist the submitted settings.
     *
     * @return void
     */
    public function handleSaveSettings(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(
                esc_html__('You do not have permission to access this page.', 'dls-starter-plugin'),
                '',
                ['response' => 403]
            );
        }

        check_admin_referer(self::NONCE_ACTION, self::NONCE_FIELD);

        $submitted = $_POST[self::API_KEY_FIELD] ?? null;

        // Reject malformed input instead of silently clearing the stored key.
        if ($submitted !== null && !is_string($submitted)) {
            $this->redirectWithNotice('invalid-api-key');
            return;
        }

        $raw = is_string($submitted) ? wp_unslash($submitted) : '';

        $validator = new FormValidator();

        if (!$validator->validate(['api_key' => $raw], ['api_key' => 'max:255'])) {
            $this->redirectWithNotice('invalid-api-key');
            return;
        }

        $this->options->set('api_key', sanitize_text_field($raw));
        $this->redirectWithNotice('saved');
    }

    /**
     * Render the settings screen.
     *
     * @return void
     */
    public function renderSettingsPage(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(
                esc_html__('You do not have permission to access this page.', 'dls-starter-plugin'),
                '',
                ['response' => 403]
            );
        }

        $notice = isset($_GET[self::NOTICE_ARG]) && is_string($_GET[self::NOTICE_ARG])
            ? sanitize_key(wp_unslash($_GET[self::NOTICE_ARG]))
            : '';

        View::render('admin/settings', [
            'plugin_name' => __('DLS Starter Plugin', 'dls-starter-plugin'),
            'api_key' => (string) $this->options->get('api_key', ''),
            'notice' => $notice,
            'save_action' => self::SAVE_ACTION,
            'nonce_action' => self::NONCE_ACTION,
            'nonce_field' => self::NONCE_FIELD,
            'api_key_field' => self::API_KEY_FIELD,
        ]);
    }

    /**
     * Redirect back to the settings screen with a notice identifier.
     *
     * @param string $notice Notice identifier consumed by the template.
     * @return void
     */
    private function redirectWithNotice(string $notice): void
    {
        wp_safe_redirect(
            add_query_arg(
                [
                    'page' => self::MENU_SLUG,
                    self::NOTICE_ARG => $notice,
                ],
                admin_url('admin.php')
            )
        );
        exit;
    }
}
