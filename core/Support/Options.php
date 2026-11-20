<?php

declare(strict_types=1);

namespace DevlinkStudios\StarterPlugin\Support;

/**
 * Encapsulates WordPress option operations with automatic prefix enforcement.
 */
class Options
{
    private const PREFIX = 'dls_starter_';

    /**
     * Get an option value with automatic prefix enforcement.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return get_option($this->formatKey($key), $default);
    }

    /**
     * Set an option value with automatic prefix enforcement.
     *
     * @param string $key
     * @param mixed $value
     * @param bool $autoload
     * @return bool
     */
    public function set(string $key, mixed $value, bool $autoload = true): bool
    {
        return update_option($this->formatKey($key), $value, $autoload);
    }

    /**
     * Delete an option with automatic prefix enforcement.
     *
     * @param string $key
     * @return bool
     */
    public function delete(string $key): bool
    {
        return delete_option($this->formatKey($key));
    }

    /**
     * Ensure the option key starts with the prefix without double-prefixing.
     *
     * @param string $key
     * @return string
     */
    private function formatKey(string $key): string
    {
        if (str_starts_with($key, self::PREFIX)) {
            return $key;
        }

        return self::PREFIX . $key;
    }
}
