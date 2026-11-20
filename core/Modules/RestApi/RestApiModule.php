<?php

declare(strict_types=1);

namespace DevlinkStudios\StarterPlugin\Modules\RestApi;

use DevlinkStudios\StarterPlugin\Contracts\ModuleInterface;
use DevlinkStudios\StarterPlugin\Support\Options;

/**
 * REST API module: public health check and API-key protected webhook.
 *
 * @since 1.0.0
 */
class RestApiModule implements ModuleInterface
{
    /**
     * REST namespace.
     */
    private const NAMESPACE = 'dls-starter/v1';

    /**
     * Request header carrying the API key for external callers.
     */
    private const API_KEY_HEADER = 'X-DLS-API-Key';

    /**
     * Maximum nesting depth accepted for webhook payloads.
     */
    private const MAX_PAYLOAD_DEPTH = 5;

    /**
     * Maximum number of top-level webhook fields accepted.
     */
    private const MAX_PAYLOAD_FIELDS = 100;

    /**
     * Create a new RestApiModule instance.
     *
     * @param Options|null $options Option accessor; defaults to a new instance.
     */
    public function __construct(private readonly Options $options = new Options())
    {
    }

    /**
     * Register all REST API endpoints for the module.
     *
     * @return void
     * @see ModuleInterface::register()
     */
    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerHealthCheckEndpoint']);
        add_action('rest_api_init', [$this, 'registerWebhookEndpoint']);
    }

    /**
     * Register the public health check endpoint.
     *
     * @return void
     */
    public function registerHealthCheckEndpoint(): void
    {
        register_rest_route(self::NAMESPACE, '/status', [
            'methods' => 'GET',
            'callback' => [$this, 'getStatus'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Build the health check response.
     *
     * @return \WP_REST_Response
     */
    public function getStatus(): \WP_REST_Response
    {
        $status = [
            'status' => 'ok',
            'version' => defined('DLS_STARTER_VERSION') ? DLS_STARTER_VERSION : '0.0.0',
            'environment' => 'wordpress',
            'api_key_configured' => $this->hasApiKey(),
        ];

        return new \WP_REST_Response($status, 200);
    }

    /**
     * Register the protected webhook endpoint.
     *
     * @return void
     */
    public function registerWebhookEndpoint(): void
    {
        register_rest_route(self::NAMESPACE, '/webhook', [
            'methods' => 'POST',
            'callback' => [$this, 'handleWebhook'],
            'permission_callback' => [$this, 'checkWebhookPermission'],
        ]);
    }

    /**
     * Authorize a webhook request.
     *
     * Site administrators authenticate through their session; external callers
     * must present the configured API key in the `X-DLS-API-Key` header. When no
     * API key is configured, external access is denied.
     *
     * @param \WP_REST_Request $request Incoming request.
     * @return bool True when the caller is authorized.
     */
    public function checkWebhookPermission(\WP_REST_Request $request): bool
    {
        if (current_user_can('manage_options')) {
            return true;
        }

        $configuredKey = (string) $this->options->get('api_key', '');

        if ($configuredKey === '') {
            return false;
        }

        $providedKey = (string) $request->get_header(self::API_KEY_HEADER);

        if ($providedKey === '') {
            return false;
        }

        return hash_equals($configuredKey, $providedKey);
    }

    /**
     * Handle the inbound webhook payload.
     *
     * @param \WP_REST_Request $request Incoming request.
     * @return \WP_REST_Response|\WP_Error Sanitized acknowledgement or an error.
     */
    public function handleWebhook(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $payload = $request->get_json_params();

        if (!is_array($payload) || $payload === []) {
            $payload = $request->get_body_params();
        }

        if (!is_array($payload) || $payload === []) {
            return new \WP_Error(
                'dls_starter_empty_payload',
                __('The webhook payload is empty.', 'dls-starter-plugin'),
                ['status' => 400]
            );
        }

        if (count($payload) > self::MAX_PAYLOAD_FIELDS) {
            return new \WP_Error(
                'dls_starter_payload_too_large',
                __('The webhook payload contains too many fields.', 'dls-starter-plugin'),
                ['status' => 413]
            );
        }

        $sanitized = $this->sanitizePayload($payload);

        return new \WP_REST_Response(
            [
                'received' => true,
                'fields' => array_keys($sanitized),
                'data' => $sanitized,
                'message' => __('Webhook received successfully.', 'dls-starter-plugin'),
            ],
            200
        );
    }

    /**
     * Determine whether an API key is configured.
     *
     * @return bool
     */
    private function hasApiKey(): bool
    {
        return (string) $this->options->get('api_key', '') !== '';
    }

    /**
     * Recursively sanitize a payload before it is echoed back to the caller.
     *
     * @param array<array-key, mixed> $payload Raw payload.
     * @param int $depth Current nesting depth.
     * @return array<string, mixed> Sanitized payload.
     */
    private function sanitizePayload(array $payload, int $depth = 0): array
    {
        if ($depth >= self::MAX_PAYLOAD_DEPTH) {
            return [];
        }

        $clean = [];

        foreach ($payload as $key => $value) {
            $safeKey = sanitize_key((string) $key);

            if ($safeKey === '') {
                continue;
            }

            if (is_array($value)) {
                $clean[$safeKey] = $this->sanitizePayload($value, $depth + 1);
                continue;
            }

            if (is_scalar($value)) {
                $clean[$safeKey] = sanitize_text_field((string) $value);
            }
        }

        return $clean;
    }
}
