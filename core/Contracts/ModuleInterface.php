<?php

declare(strict_types=1);

/**
 * Core Contracts Layer - ModuleInterface.php
 *
 * Interface that all modules must implement.
 *
 * @author DevlinkStudios
 * @package DevlinkStudios\StarterPlugin\Contracts
 * @license GPL-2.0-or-later
 */

namespace DevlinkStudios\StarterPlugin\Contracts;

/**
 * Module interface that all plugin modules must implement.
 *
 * @since 1.0.0
 */
interface ModuleInterface
{
    /**
     * Register module hooks and handlers.
     *
     * @since 1.0.0
     * @return void
     */
    public function register(): void;
}