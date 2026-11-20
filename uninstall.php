<?php
/**
 * Uninstall routine executed by WordPress when the plugin is deleted.
 *
 * Removes every plugin-owned option, transient, and site option from the
 * database. Cleanup is delegated to the Installer so no global helper function
 * is declared and the removal logic stays testable.
 *
 * @package DevlinkStudios\StarterPlugin
 */

declare(strict_types=1);

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/*
|--------------------------------------------------------------------------
| Preferred path: plugin classes
|--------------------------------------------------------------------------
|
| Classes are loaded through the Composer autoloader. The plugin itself is not
| bootstrapped during uninstallation, so the autoloader is required here.
|
*/
$dlsStarterAutoloader = __DIR__ . '/vendor/autoload.php';

if (file_exists($dlsStarterAutoloader)) {
    require_once $dlsStarterAutoloader;
}

unset($dlsStarterAutoloader);

if (class_exists(\DevlinkStudios\StarterPlugin\Bootstrap\Installer::class)) {
    (new \DevlinkStudios\StarterPlugin\Bootstrap\Installer())->uninstall();

    return;
}

/*
|--------------------------------------------------------------------------
| Fallback: autoloader unavailable
|--------------------------------------------------------------------------
|
| Keeps deletion from leaving data behind when `composer install` was never run.
| Only the current site is cleaned, because the multisite API is not reachable
| without the plugin's classes.
|
*/
(static function (): void {
    global $wpdb;

    if (!isset($wpdb) || !($wpdb instanceof \wpdb)) {
        return;
    }

    $prefixes = [
        'dls_starter_',
        '_transient_dls_starter_',
        '_transient_timeout_dls_starter_',
    ];

    foreach ($prefixes as $prefix) {
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM `{$wpdb->options}` WHERE `option_name` LIKE %s",
                $wpdb->esc_like($prefix) . '%'
            )
        );
    }

    wp_cache_delete('alloptions', 'options');
    wp_cache_delete('notoptions', 'options');
    wp_cache_flush();
})();
