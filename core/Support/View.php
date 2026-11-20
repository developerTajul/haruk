<?php

declare(strict_types=1);

namespace DevlinkStudios\StarterPlugin\Support;

use InvalidArgumentException;

/**
 * Secure, isolated PHP template renderer that decouples presentation markup from controller scope.
 */
class View
{
    /**
     * Render a template and output the content directly.
     *
     * @param string $template
     * @param array<string, mixed> $data
     * @return void
     */
    public static function render(string $template, array $data = []): void
    {
        echo static::make($template, $data);
    }

    /**
     * Render a template and return the content as a string using output buffering.
     *
     * @param string $template
     * @param array<string, mixed> $data
     * @return string
     * @throws InvalidArgumentException
     */
    public static function make(string $template, array $data = []): string
    {
        if (str_contains($template, '..')) {
            throw new InvalidArgumentException(sprintf('Invalid template path "%s": directory traversal not allowed.', $template));
        }

        $filePath = static::resolvePath($template);

        if (!file_exists($filePath)) {
            throw new InvalidArgumentException(sprintf('Template file not found at path "%s".', $filePath));
        }

        return static::renderIsolated($filePath, $data);
    }

    /**
     * Resolve the full file path for a template.
     *
     * @param string $template
     * @return string
     */
    private static function resolvePath(string $template): string
    {
        $template = ltrim($template, '/\\');

        if (!str_ends_with($template, '.php')) {
            $template .= '.php';
        }

        $basePath = defined('DLS_STARTER_PATH')
            ? DLS_STARTER_PATH
            : dirname(__DIR__, 2);

        return rtrim($basePath, '/\\') . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . $template;
    }

    /**
     * Render the template file inside an isolated static closure.
     *
     * @param string $filePath
     * @param array<string, mixed> $data
     * @return string
     */
    private static function renderIsolated(string $filePath, array $data): string
    {
        $renderClosure = static function (string $__file, array $__data): string {
            extract($__data, EXTR_SKIP);
            ob_start();
            include $__file;
            return (string) ob_get_clean();
        };

        return $renderClosure($filePath, $data);
    }
}
