<?php

declare(strict_types=1);

namespace DevlinkStudios\StarterPlugin\Bootstrap;

use DevlinkStudios\StarterPlugin\Contracts\ModuleInterface;
use DevlinkStudios\StarterPlugin\Modules\Admin\AdminModule;
use DevlinkStudios\StarterPlugin\Modules\Ajax\AjaxModule;
use DevlinkStudios\StarterPlugin\Modules\Frontend\FrontendModule;
use DevlinkStudios\StarterPlugin\Modules\RestApi\RestApiModule;

/**
 * Main plugin bootstrap class.
 *
 * @since 1.0.0
 */
class Plugin
{
    /**
     * Array of actively resolved modules (context-aware).
     *
     * @var array<string, object>
     */
    private array $activeModules = [];

    /**
     * Initialize the plugin.
     *
     * @since 1.0.0
     */
    public function init(): void
    {
        $this->resolveModules();
        $this->registerHooks();
    }

    /**
     * Resolve active modules context-aware.
     *
     * Modules are resolved with the pure factory pattern (no reflection) so that
     * contexts that never render a module (e.g. frontend requests for the admin
     * module) do not pay its instantiation cost.
     *
     * @since 1.0.0
     */
    private function resolveModules(): void
    {
        // Admin module - only on admin pages
        if (is_admin()) {
            $this->activeModules['admin'] = $this->resolveModule(AdminModule::class);
        }

        // Frontend module - always active for shortcode rendering
        $this->activeModules['frontend'] = $this->resolveModule(FrontendModule::class);

        // REST API module - always registered
        $this->activeModules['rest-api'] = $this->resolveModule(RestApiModule::class);

        // AJAX module - only on AJAX requests
        if (defined('DOING_AJAX') && DOING_AJAX) {
            $this->activeModules['ajax'] = $this->resolveModule(AjaxModule::class);
        }
    }

    /**
     * Resolve a module instance.
     *
     * Instances are created through the Composer autoloader via the pure factory
     * pattern; constructor dependencies fall back to their own defaults. To move
     * to a service container (e.g. devlinkstudios/wp-service-container), replace
     * the instantiation below with a container resolution call.
     *
     * @since 1.0.0
     * @param string $moduleClass Fully-qualified class name.
     * @return object Resolved module instance.
     * @throws \RuntimeException When the module class cannot be autoloaded.
     */
    private function resolveModule(string $moduleClass): object
    {
        if (!class_exists($moduleClass)) {
            throw new \RuntimeException(sprintf('Module class "%s" not found.', $moduleClass));
        }

        return new $moduleClass();
    }

    /**
     * Register all module hooks.
     *
     * @since 1.0.0
     */
    private function registerHooks(): void
    {
        foreach ($this->activeModules as $module) {
            if ($module instanceof ModuleInterface) {
                $module->register();
            }
        }
    }

    /**
     * Get actively resolved modules (context-aware).
     *
     * @since 1.0.0
     * @return array Array of actively resolved modules
     */
    public function getActiveModules(): array
    {
        return $this->activeModules;
    }
}