<?php

declare(strict_types=1);

namespace DevlinkStudios\StarterPlugin\Database;

use InvalidArgumentException;
use RuntimeException;
use wpdb;

/**
 * Abstract repository encapsulating `$wpdb` access for custom tables.
 *
 * Concrete repositories extend this class, implement {@see tableName()}, and
 * publish the query helpers they need. Table names are validated before they
 * are interpolated into SQL; all dynamic values are passed through
 * `$wpdb::prepare()`.
 *
 * @since 1.0.0
 */
abstract class BaseRepository
{
    /**
     * Create a new repository instance.
     *
     * @since 1.0.0
     * @param wpdb|null $db Database connection; defaults to the global instance.
     * @throws RuntimeException When no database connection is available.
     */
    public function __construct(private ?wpdb $db = null)
    {
        if ($this->db === null) {
            global $wpdb;
            $this->db = $wpdb instanceof wpdb ? $wpdb : null;
        }

        if ($this->db === null) {
            throw new RuntimeException('A database connection is required to use a repository.');
        }
    }

    /**
     * Get the fully prefixed table name.
     *
     * @since 1.0.0
     * @return string Table name including the WordPress table prefix.
     * @throws InvalidArgumentException When the subclass returns an unsafe table name.
     */
    protected function getTableName(): string
    {
        $table = $this->tableName();

        if (preg_match('/^[A-Za-z0-9_]+$/', $table) !== 1) {
            throw new InvalidArgumentException(
                sprintf('Unsafe table name "%s": only letters, digits, and underscores are allowed.', $table)
            );
        }

        return $this->database()->prefix . 'dls_starter_' . $table;
    }

    /**
     * Get the unprefixed table name.
     *
     * @since 1.0.0
     * @return string Table name without the plugin or WordPress prefix.
     */
    abstract protected function tableName(): string;

    /**
     * Find a record by its primary key.
     *
     * @since 1.0.0
     * @param int $id Record ID.
     * @return object|null Found record or null when absent.
     */
    protected function findById(int $id): ?object
    {
        $table = $this->getTableName();
        $query = $this->database()->prepare("SELECT * FROM `{$table}` WHERE id = %d", $id);
        $result = $this->database()->get_row($query);

        return is_object($result) ? $result : null;
    }

    /**
     * Delete a record by its primary key.
     *
     * @since 1.0.0
     * @param int $id Record ID.
     * @return bool True when a row was deleted.
     */
    protected function deleteById(int $id): bool
    {
        $table = $this->getTableName();
        $query = $this->database()->prepare("DELETE FROM `{$table}` WHERE id = %d", $id);

        return $this->database()->query($query) !== false;
    }

    /**
     * Count all records in the table.
     *
     * The table name is validated by {@see getTableName()} and contains no user
     * input, so no placeholders are required in this query.
     *
     * @since 1.0.0
     * @return int Total number of rows.
     */
    protected function countAll(): int
    {
        $table = $this->getTableName();

        return (int) $this->database()->get_var("SELECT COUNT(*) FROM `{$table}`");
    }

    /**
     * Get the initialized WordPress database connection.
     *
     * @return wpdb
     */
    private function database(): wpdb
    {
        return $this->db ?? throw new RuntimeException('A database connection is required to use a repository.');
    }
}
