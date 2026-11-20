<?php

declare(strict_types=1);

namespace DevlinkStudios\StarterPlugin\Modules\Ajax;

use DevlinkStudios\StarterPlugin\Contracts\ModuleInterface;
use DevlinkStudios\StarterPlugin\Support\FormValidator;
use DevlinkStudios\StarterPlugin\Support\Options;

/**
 * AJAX module: nonce-protected request handling for authenticated and
 * anonymous visitors.
 *
 * Both handlers share the same nonce action. The anonymous handler never
 * receives privileged data; capability-gated values are added only for users
 * that hold `manage_options`.
 *
 * @since 1.0.0
 */
class AjaxModule implements ModuleInterface
{
    /**
     * AJAX action name (prefixed by WordPress as `wp_ajax_*`).
     */
    private const ACTION = 'dls_starter_action';

    /**
     * Nonce action expected in the `security` request field.
     */
    private const NONCE_ACTION = 'dls_starter_action';

    /**
     * Create a new AjaxModule instance.
     *
     * @param Options|null $options Option accessor; defaults to a new instance.
     */
    public function __construct(private readonly Options $options = new Options())
    {
    }

    /**
     * Register all AJAX handlers for the module.
     *
     * @return void
     * @see ModuleInterface::register()
     */
    public function register(): void
    {
        add_action('wp_ajax_' . self::ACTION, [$this, 'handleAjaxAction']);
        add_action('wp_ajax_nopriv_' . self::ACTION, [$this, 'handleAjaxAction']);
    }

    /**
     * Handle the AJAX request.
     *
     * @return void
     */
    public function handleAjaxAction(): void
    {
        if (!$this->verifyNonce()) {
            wp_send_json_error(
                [
                    'code' => 'invalid_nonce',
                    'message' => __('The security check failed. Please reload the page and try again.', 'dls-starter-plugin'),
                ],
                403
            );
        }

        $raw = isset($_POST['data']) && is_string($_POST['data']) ? wp_unslash($_POST['data']) : '';

        // Validate the unsanitized value so `required` can detect empty input.
        $validator = new FormValidator();

        if (!$validator->validate(['data' => $raw], ['data' => 'required|max:255'])) {
            wp_send_json_error(
                [
                    'code' => 'invalid_data',
                    'errors' => $validator->errors(),
                ],
                400
            );
        }

        $response = [
            'processed' => true,
            'data' => sanitize_text_field($raw),
            'privileged' => current_user_can('manage_options'),
        ];

        // Only expose configuration state to users allowed to change it.
        if ($response['privileged']) {
            $response['api_key_configured'] = (string) $this->options->get('api_key', '') !== '';
        }

        wp_send_json_success($response);
    }

    /**
     * Verify the AJAX nonce without terminating the request.
     *
     * @return bool True when the nonce is present and valid.
     */
    private function verifyNonce(): bool
    {
        $nonce = isset($_POST['security']) && is_string($_POST['security'])
            ? sanitize_text_field(wp_unslash($_POST['security']))
            : '';

        return $nonce !== '' && wp_verify_nonce($nonce, self::NONCE_ACTION) !== false;
    }
}
