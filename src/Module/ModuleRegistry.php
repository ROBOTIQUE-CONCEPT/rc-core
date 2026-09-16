<?php

declare(strict_types=1);

namespace WPRC\Core\Module;

use InvalidArgumentException;
use WPRC\Core\Container;
use WPRC\Core\Contracts\ModuleInterface;

defined('ABSPATH') || exit;

/**
 * Canonical lifecycle registry for RC business modules.
 */
final class ModuleRegistry
{
    /** @var array<string,ModuleInterface> */
    private array $modules = [];

    /** @var array<string,true> */
    private array $booted = [];

    public function __construct(private readonly Container $container)
    {
    }

    public function register(ModuleInterface $module, bool $replace = false): void
    {
        $id = sanitize_key($module->id());
        if ($id === '') {
            throw new InvalidArgumentException('RC module ID cannot be empty.');
        }

        if (!$replace && isset($this->modules[$id])) {
            throw new InvalidArgumentException(sprintf('RC module "%s" is already registered.', $id));
        }

        $minimumCore = trim($module->minimumCoreVersion());
        if ($minimumCore !== '' && version_compare(RC_CORE_VERSION, $minimumCore, '<')) {
            throw new InvalidArgumentException(sprintf(
                'RC module "%s" requires RC Core %s or newer; %s is installed.',
                $id,
                $minimumCore,
                RC_CORE_VERSION
            ));
        }

        $module->register($this->container);
        $this->modules[$id] = $module;
        unset($this->booted[$id]);
    }

    public function bootAll(): void
    {
        foreach ($this->modules as $id => $module) {
            if (isset($this->booted[$id])) {
                continue;
            }
            $module->boot();
            $this->booted[$id] = true;
        }
    }

    public function has(string $id): bool
    {
        return isset($this->modules[sanitize_key($id)]);
    }

    public function get(string $id): ModuleInterface
    {
        $id = sanitize_key($id);
        if (!isset($this->modules[$id])) {
            throw new InvalidArgumentException(sprintf('Unknown RC module "%s".', $id));
        }
        return $this->modules[$id];
    }

    /** @return array<string,ModuleInterface> */
    public function all(): array
    {
        return $this->modules;
    }
}
