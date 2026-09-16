<?php

declare(strict_types=1);

namespace WPRC\Core\Contracts;

use WPRC\Core\Container;

defined('ABSPATH') || exit;

/**
 * Contract implemented by every RC business module.
 *
 * A module may depend on RC Core only. Registration is the dependency wiring
 * phase; boot is the WordPress runtime hook phase.
 */
interface ModuleInterface
{
    public function id(): string;

    public function version(): string;

    /** Minimum compatible RC Core version. */
    public function minimumCoreVersion(): string;

    public function register(Container $container): void;

    public function boot(): void;
}
