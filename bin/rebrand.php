#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * DLS Starter Plugin - One-Command Rebrander.
 *
 * @author DevlinkStudios
 * @license GPL-2.0-or-later
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only be run from the command line.\n");
    exit(1);
}

$args = $argv;
array_shift($args);
if (($args[0] ?? null) === '--') {
    array_shift($args);
}

$options = [];
$booleanFlags = ['dry-run', 'clean'];
$valueFlags = ['name', 'slug', 'vendor', 'vendor-namespace', 'namespace'];
for ($i = 0, $count = count($args); $i < $count; $i++) {
    $argument = $args[$i];
    if (!str_starts_with($argument, '--')) {
        continue;
    }

    $argument = substr($argument, 2);
    if (str_contains($argument, '=')) {
        [$key, $value] = explode('=', $argument, 2);
    } else {
        $key = $argument;
        $value = null;
    }

    if (in_array($key, $booleanFlags, true)) {
        $options[$key] = true;
        continue;
    }

    if (!in_array($key, $valueFlags, true)) {
        fwrite(STDERR, sprintf("Unknown option: --%s\n", $key));
        exit(1);
    }

    if ($value === null) {
        if (!isset($args[$i + 1]) || str_starts_with($args[$i + 1], '--')) {
            fwrite(STDERR, sprintf("Option --%s requires a value.\n", $key));
            exit(1);
        }
        $value = $args[++$i];
    }
    $options[$key] = $value;
}

$requiredFlags = ['name', 'slug', 'vendor', 'namespace'];
$errors = [];
foreach ($requiredFlags as $flag) {
    if (!isset($options[$flag]) || $options[$flag] === '') {
        $errors[] = sprintf('--%s is required', $flag);
    }
}

$name = (string) ($options['name'] ?? '');
$slug = (string) ($options['slug'] ?? '');
$vendor = (string) ($options['vendor'] ?? '');
$namespace = (string) ($options['namespace'] ?? '');
$vendorNamespace = (string) ($options['vendor-namespace'] ?? '');

if ($slug !== '' && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1) {
    $errors[] = '--slug must contain lowercase letters and numbers separated by single hyphens';
}
if ($vendor !== '' && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $vendor) !== 1) {
    $errors[] = '--vendor must contain lowercase letters and numbers separated by single hyphens';
}
if ($namespace !== '' && preg_match('/^[A-Z][A-Za-z0-9]*$/', $namespace) !== 1) {
    $errors[] = '--namespace must be a valid StudlyCase identifier';
}
if ($vendorNamespace !== '' && preg_match('/^[A-Z][A-Za-z0-9]*$/', $vendorNamespace) !== 1) {
    $errors[] = '--vendor-namespace must be a valid StudlyCase identifier';
}
if ($name !== '' && trim($name) === '') {
    $errors[] = '--name must not be empty';
}
if ($name === 'DLS Starter Plugin' || $slug === 'dls-starter-plugin' || $namespace === 'StarterPlugin') {
    $errors[] = 'Required values must not match the default starter name, slug, or namespace';
}

if ($errors !== []) {
    fwrite(STDERR, "ERRORS:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, "  - {$error}\n");
    }
    exit(1);
}

$vendorStudly = $vendorNamespace;
if ($vendorStudly === '') {
    $vendorParts = preg_split('/[-_]+/', $vendor) ?: [];
    $vendorStudly = implode('', array_map(static fn (string $part): string => ucfirst($part), $vendorParts));
}

$snakeSlug = str_replace('-', '_', $slug);
$rootNamespace = $vendorStudly . '\\' . $namespace;
$tokenMap = [
    'DevlinkStudios\\StarterPlugin' => $rootNamespace,
    'devlinkstudios/wp-starter-plugin' => $vendor . '/' . $slug,
    'DLS_STARTER_' => strtoupper($snakeSlug) . '_',
    'dls_starter_' => strtolower($snakeSlug) . '_',
    'dls-starter-plugin' => $slug,
    'dls-starter/v1' => $slug . '/v1',
    'DLS Starter Plugin' => $name,
    'DLS Starter' => $name,
];

$rootDirectory = dirname(__DIR__);
$excludedDirectories = ['vendor', '.git', 'bin'];
$filesToScan = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($rootDirectory, RecursiveDirectoryIterator::SKIP_DOTS)
);
foreach ($iterator as $fileInfo) {
    if (!$fileInfo->isFile() || !in_array($fileInfo->getExtension(), ['php', 'json', 'md'], true)) {
        continue;
    }

    $relativePath = substr($fileInfo->getPathname(), strlen($rootDirectory) + 1);
    $segments = preg_split('~[\\\\/]~', $relativePath) ?: [];
    if (array_intersect($segments, $excludedDirectories) !== [] || $fileInfo->getBasename() === 'rebrand.php') {
        continue;
    }
    $filesToScan[] = $fileInfo->getPathname();
}
sort($filesToScan);

$plannedChanges = [];
$replacementRegexKeys = array_keys($tokenMap);
usort($replacementRegexKeys, static fn (string $left, string $right): int => strlen($right) <=> strlen($left));
$replacementRegex = '~' . implode('|', array_map(static fn (string $token): string => preg_quote($token, '~'), $replacementRegexKeys)) . '~';
$entryFile = $rootDirectory . DIRECTORY_SEPARATOR . 'dls-starter-plugin.php';
$entryTarget = $rootDirectory . DIRECTORY_SEPARATOR . $slug . '.php';

foreach ($filesToScan as $filePath) {
    $content = file_get_contents($filePath);
    if ($content === false) {
        fwrite(STDERR, sprintf("Unable to read file: %s\n", $filePath));
        exit(1);
    }

    $matches = [];
    preg_match_all($replacementRegex, $content, $matches);
    $counts = array_count_values($matches[0] ?? []);
    $replacements = [];
    foreach ($counts as $token => $occurrences) {
        $replacement = $tokenMap[$token];
        if ($token !== $replacement) {
            $replacements[] = [
                'token' => $token,
                'replacement' => $replacement,
                'count' => $occurrences,
            ];
        }
    }

    $rewritten = strtr($content, $tokenMap);
    $isEntryFile = $filePath === $entryFile;
    if ($replacements !== [] || ($isEntryFile && $slug !== 'dls-starter-plugin')) {
        $plannedChanges[] = [
            'source' => $filePath,
            'target' => $isEntryFile ? $entryTarget : $filePath,
            'replacements' => $replacements,
            'content' => $rewritten,
            'rename' => $isEntryFile && $slug !== 'dls-starter-plugin',
        ];
    }
}

if (!empty($options['dry-run'])) {
    echo "=== REBRAND PREVIEW (dry-run) ===\n";
    echo "Plugin: {$name}\nSlug: {$slug}\nVendor: {$vendor}\nVendor namespace: {$vendorStudly}\nNamespace: {$rootNamespace}\n";
    echo "\nEntry file rename:\n";
    echo '  dls-starter-plugin.php -> ' . $slug . ".php\n";
    echo "\nPlanned file changes (" . count($plannedChanges) . "):\n";

    if ($plannedChanges === []) {
        echo "  (No content replacements required.)\n";
    }
    foreach ($plannedChanges as $change) {
        $sourceLabel = substr($change['source'], strlen($rootDirectory) + 1);
        $targetLabel = substr($change['target'], strlen($rootDirectory) + 1);
        echo sprintf("  %s%s\n", $sourceLabel, $change['rename'] ? ' -> ' . $targetLabel : '');
        if ($change['replacements'] === []) {
            echo "    (rename only; no token replacements)\n";
            continue;
        }
        foreach ($change['replacements'] as $replacement) {
            echo sprintf(
                "    %s -> %s (%d occurrence%s)\n",
                $replacement['token'],
                $replacement['replacement'],
                $replacement['count'],
                $replacement['count'] === 1 ? '' : 's'
            );
        }
    }
    echo "\nNo files will be modified.\n";
    exit(0);
}

if ($slug !== 'dls-starter-plugin' && file_exists($entryTarget)) {
    fwrite(STDERR, sprintf("Refusing to overwrite existing entry file: %s\n", $entryTarget));
    exit(1);
}

foreach ($plannedChanges as $change) {
    $targetPath = $change['target'];
    if ($change['rename'] && !rename($change['source'], $targetPath)) {
        fwrite(STDERR, sprintf("Unable to rename %s to %s\n", $change['source'], $targetPath));
        exit(1);
    }
    if (file_put_contents($targetPath, $change['content']) === false) {
        fwrite(STDERR, sprintf("Unable to write file: %s\n", $targetPath));
        exit(1);
    }
    echo sprintf("Updated: %s\n", substr($targetPath, strlen($rootDirectory) + 1));
}

$command = 'composer dump-autoload -o';
echo "\nRunning: {$command}\n";
$output = [];
$exitCode = 0;
exec($command, $output, $exitCode);
foreach ($output as $line) {
    echo $line . "\n";
}
if ($exitCode !== 0) {
    fwrite(STDERR, sprintf("Composer autoload generation failed with exit code %d; bin/rebrand.php was preserved.\n", $exitCode));
    exit($exitCode);
}

if (!empty($options['clean'])) {
    $scriptPath = __FILE__;
    if (is_file($scriptPath) && !unlink($scriptPath)) {
        fwrite(STDERR, "Rebrand succeeded, but bin/rebrand.php could not be removed.\n");
        exit(1);
    }
    echo "Deleted bin/rebrand.php as requested.\n";
}

echo "\n=== Rebrand complete ===\n";
