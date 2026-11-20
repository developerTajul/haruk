<?php

declare(strict_types=1);

namespace DevlinkStudios\StarterPlugin\Bootstrap;

use DevlinkStudios\StarterPlugin\Database\OptionRepository;

/**
 * Handles plugin activation, deactivation, and uninstallation routines.
 *
 * @since 1.0.0
 */
final class Installer
{
    /**
     * Prefix shared by every option, transient, and site option owned by the
     * plugin.
     */
    private const PREFIX = 'dls_starter_';

    /**
     * Repository used to remove plugin-owned option rows.
     *
     * @var OptionRepository
     */
    private OptionRepository $options;

    /**
     * Create a new installer instance.
     *
     * @param OptionRepository|null $options Repository; defaults to a new instance.
     */
    public function __construct(?OptionRepository $options = null)
    {
        $this->options = $options ?? new OptionRepository();
    }

    /**
     * Execute installation tasks upon plugin activation.
     *
     * @return void
     */
    public function activate(): void
    {
        $this->setDefaultOptions();
        flush_rewrite_rules();
    }

    /**
     * Execute cleanup tasks upon plugin deactivation.
     *
     * @return void
     */
    public function deactivate(): void
    {
        flush_rewrite_rules();
    }

    /**
     * Remove every trace of the plugin from the database.
     *
     * Runs on each site of a multisite network, then clears network-wide
     * options. Invoked from `uninstall.php`.
     *
     * @return void
     */
    public function uninstall(): void
    {
        foreach ($this->siteIds() as $siteId) {
            if ($siteId > 0) {
                switch_to_blog($siteId);
            }

            $this->deleteSiteData();

            if ($siteId > 0) {
                restore_current_blog();
            }
        }

        if (is_multisite()) {
            $this->options->deleteNetworkOptionsByPrefix(self::PREFIX);
        }

        $this->options->flushOptionCache();
    }

    /**
     * Store initial plugin metadata and default settings.
     *
     * @return void
     */
    private function setDefaultOptions(): void
    {
        if (!get_option('dls_starter_installed_at')) {
            update_option('dls_starter_installed_at', time());
        }

        if (defined('DLS_STARTER_VERSION')) {
            update_option('dls_starter_version', DLS_STARTER_VERSION);
        }
    }

    /**
     * Remove plugin-owned options and transients for the current site.
     *
     * @return void
     */
    private function deleteSiteData(): void
    {
        $this->options->deleteOptionsByPrefix(self::PREFIX);
        $this->options->deleteTransientsByPrefix(self::PREFIX);
    }

    /**
     * Resolve the site IDs whose data must be removed.
     *
     * @return list<int> Site IDs, or `[0]` on single-site installations meaning
     *                 "the current site, without switching".
     */
    private function siteIds(): array
    {
        if (!is_multisite()) {
            return [0];
        }

        $siteIds = get_sites([
            'fields' => 'ids',
            'number' => 0,
            'archived' => '',
            'deleted' => '',
            'spam' => '',
        ]);

        return array_values(array_map('intval', is_array($siteIds) ? $siteIds : []));
    }
}
