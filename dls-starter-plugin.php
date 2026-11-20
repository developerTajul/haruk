<?php
/**
 * Plugin Name:       DLS Starter Plugin
 * Plugin URI:        https://devlinkstudios.com/
 * Description:       A lightweight, modular, and container-ready WordPress starter plugin.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * Author:            DevlinkStudios
 * Author URI:        https://devlinkstudios.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       dls-starter-plugin
 * Domain Path:       /languages
 *
 * @package DevlinkStudios\StarterPlugin
 */

declare(strict_types=1);

use DevlinkStudios\StarterPlugin\Bootstrap\Installer;
use DevlinkStudios\StarterPlugin\Bootstrap\Plugin;

/**
 * Prevent direct access.
 */
if (!defined('ABSPATH')) {
    exit;
}

/*
|--------------------------------------------------------------------------
| Environment guard
|--------------------------------------------------------------------------
|
| The plugin targets PHP 8.1+. Bail out with an actionable admin notice
| instead of surfacing an opaque parse or type error.
|
*/
if (version_compare(PHP_VERSION, '8.1', '<')) {
    add_action('admin_notices', static function (): void {
        printf(
            '<div class="notice notice-error"><p>%s</p></div>',
            esc_html(
                sprintf(
                    /* translators: 1: required PHP version, 2: current PHP version. */
                    __('DLS Starter Plugin requires PHP %1$s or higher. You are running PHP %2$s.', 'dls-starter-plugin'),
                    '8.1',
                    PHP_VERSION
                )
            )
        );
    });

    return;
}

/*
|--------------------------------------------------------------------------
| Plugin constants
|--------------------------------------------------------------------------
*/
if (!defined('DLS_STARTER_VERSION')) {
    define('DLS_STARTER_VERSION', '1.0.0');
}

if (!defined('DLS_STARTER_FILE')) {
    define('DLS_STARTER_FILE', __FILE__);
}

if (!defined('DLS_STARTER_PATH')) {
    define('DLS_STARTER_PATH', plugin_dir_path(__FILE__));
}

if (!defined('DLS_STARTER_URL')) {
    define('DLS_STARTER_URL', plugin_dir_url(__FILE__));
}

if (!defined('DLS_STARTER_BASENAME')) {
    define('DLS_STARTER_BASENAME', plugin_basename(__FILE__));
}

/*
|--------------------------------------------------------------------------
| Composer autoloader
|--------------------------------------------------------------------------
|
| All plugin classes are resolved through PSR-4. Run `composer install`
| (or `composer dump-autoload -o`) when deploying.
|
*/
$dlsStarterAutoloader = DLS_STARTER_PATH . 'vendor/autoload.php';

if (file_exists($dlsStarterAutoloader)) {
    require_once $dlsStarterAutoloader;
} else {
    add_action('admin_notices', static function (): void {
        printf(
            '<div class="notice notice-error"><p>%s</p></div>',
            esc_html__(
                'DLS Starter Plugin is missing its Composer autoloader. Run "composer install" in the plugin directory.',
                'dls-starter-plugin'
            )
        );
    });

    return;
}

unset($dlsStarterAutoloader);

/*
|--------------------------------------------------------------------------
| Bootstrap
|--------------------------------------------------------------------------
*/

/**
 * Main plugin class.
 *
 * Thin entry point that owns the bootstrap instance so the container can be
 * swapped or extended without touching this file.
 *
 * @since 1.0.0
 */
final class DLS_Starter_Plugin
{
    /**
     * Plugin bootstrap instance.
     *
     * @since 1.0.0
     * @var Plugin
     */
    private Plugin $bootstrap;

    /**
     * Initialize the plugin.
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        $this->bootstrap = new Plugin();
        $this->bootstrap->init();
    }

    /**
     * Retrieve the bootstrap instance.
     *
     * @since 1.0.0
     * @return Plugin
     */
    public function getBootstrap(): Plugin
    {
        return $this->bootstrap;
    }
}

/**
 * Instantiate the plugin.
 *
 * @since 1.0.0
 */
$dlsStarterPlugin = new DLS_Starter_Plugin();

/*
|--------------------------------------------------------------------------
| Lifecycle hooks
|--------------------------------------------------------------------------
|
| Activation and deactivation routines are delegated to the Installer so the
| bootstrap classes stay free of procedural logic.
|
*/
register_activation_hook(
    __FILE__,
    static function (): void {
        (new Installer())->activate();
    }
);

register_deactivation_hook(
    __FILE__,
    static function (): void {
        (new Installer())->deactivate();
    }
);
