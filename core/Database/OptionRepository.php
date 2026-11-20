<?php

declare(strict_types=1);

namespace DevlinkStudios\StarterPlugin\Database;

use InvalidArgumentException;
use RuntimeException;
use wpdb;

/**
 * Repository for plugin-owned rows in the WordPress options and site options
 * tables.
 *
 * WordPress exposes no API for removing options by prefix, so removal is done
 * with prepared `DELETE ... LIKE` statements. Identifiers are validated and the
 * prefix is always bound through `$wpdb::prepare()`.
 *
 * @since 1.0.0
 */
final class OptionRepository
{
    /**
     * Columns that may be targeted. Acts as a whitelist for interpolation.
     */
    private const ALLOWED_COLUMNS = ['option_name', 'meta_key'];

    /**
     * WordPress database connection.
     *
     * @var wpdb
     */
    private wpdb $db;

    /**
     * Create a new repository instance.
     *
     * @param wpdb|null $db Database connection; defaults to the global instance.
     * @throws RuntimeException When no database connection is available.
     */
    public function __construct(?wpdb $db = null)
    {
        if ($db === null) {
            global $wpdb;
            $db = $wpdb;
        }

        if (!$db instanceof wpdb) {
            throw new RuntimeException('A database connection is required to use a repository.');
        }

        $this->db = $db;
    }

    /**
     * Delete single-site options whose name starts with the given prefix.
     *
     * @param string $prefix Option name prefix, without `%`.
     * @return int Number of deleted rows.
     */
    public function deleteOptionsByPrefix(string $prefix): int
    {
        return $this->deleteByPrefix((string) $this->db->options, 'option_name', $prefix);
    }

    /**
     * Delete single-site transients whose name starts with the given prefix.
     *
     * Removes both the transient value and its timeout counterpart.
     *
     * @param string $prefix Transient name prefix, without `_transient_`.
     * @return int Number of deleted rows.
     */
    public function deleteTransientsByPrefix(string $prefix): int
    {
        $table = (string) $this->db->options;

        return $this->deleteByPrefix($table, 'option_name', '_transient_' . $prefix)
            + $this->deleteByPrefix($table, 'option_name', '_transient_timeout_' . $prefix);
    }

    /**
     * Delete network-wide options and site transients for the given prefix.
     *
     * Only meaningful on multisite installations; the caller decides whether the
     * network table exists.
     *
     * @param string $prefix Option name prefix, without `%`.
     * @return int Number of deleted rows.
     */
    public function deleteNetworkOptionsByPrefix(string $prefix): int
    {
        $table = (string) $this->db->sitemeta;

        return $this->deleteByPrefix($table, 'meta_key', $prefix)
            + $this->deleteByPrefix($table, 'meta_key', '_site_transient_' . $prefix)
            + $this->deleteByPrefix($table, 'meta_key', '_site_transient_timeout_' . $prefix);
    }

    /**
     * Invalidate cached option state after direct database removal.
     *
     * @return void
     */
    public function flushOptionCache(): void
    {
        wp_cache_delete('alloptions', 'options');
        wp_cache_delete('notoptions', 'options');
        wp_cache_flush();
    }

    /**
     * Delete rows in a whitelisted table/column whose value matches a prefix.
     *
     * @param string $table  Table name.
     * @param string $column Column holding the option or meta key.
     * @param string $prefix Prefix matched with a trailing `%` wildcard.
     * @return int Number of deleted rows.
     * @throws InvalidArgumentException When a table or column name is not allowed.
     */
    private function deleteByPrefix(string $table, string $column, string $prefix): int
    {
        if (preg_match('/^[A-Za-z0-9_]+$/', $table) !== 1) {
            throw new InvalidArgumentException(sprintf('Unsafe table name "%s".', $table));
        }

        if (!in_array($column, self::ALLOWED_COLUMNS, true)) {
            throw new InvalidArgumentException(sprintf('Unsupported column "%s".', $column));
        }

        if ($prefix === '') {
            return 0;
        }

        $query = $this->db->prepare(
            "DELETE FROM `{$table}` WHERE `{$column}` LIKE %s",
            $this->db->esc_like($prefix) . '%'
        );

        $deleted = $this->db->query($query);

        return is_int($deleted) ? $deleted : 0;
    }
}
