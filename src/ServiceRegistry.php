<?php

declare(strict_types=1);

namespace WPRC\Core;

use Closure;
use InvalidArgumentException;

defined('ABSPATH') || exit;

/**
 * Public cross-plugin registry.
 *
 * Use this registry for contracts intentionally shared between independent RC
 * plugins. Internal plugin repositories remain owned by their plugin.
 */
final class ServiceRegistry
{
    /** @var array<string, Closure():object> */
    private array $factories = [];

    /** @var array<string, object> */
    private array $instances = [];

    /** @param Closure():object $factory */
    public function bind(string $contract, Closure $factory, bool $replace = false): void
    {
        $this->guard($contract, $replace);
        $this->factories[$contract] = $factory;
        unset($this->instances[$contract]);
    }

    public function instance(string $contract, object $service, bool $replace = false): void
    {
        if (!$service instanceof $contract) {
            throw new InvalidArgumentException(sprintf('Service for "%s" must implement that contract.', $contract));
        }
        $this->guard($contract, $replace);
        $this->instances[$contract] = $service;
        unset($this->factories[$contract]);
    }

    public function has(string $contract): bool
    {
        return isset($this->instances[$contract]) || isset($this->factories[$contract]);
    }

    public function get(string $contract): object
    {
        if (isset($this->instances[$contract])) {
            return $this->instances[$contract];
        }
        if (!isset($this->factories[$contract])) {
            throw new InvalidArgumentException(sprintf('No service registered for contract "%s".', $contract));
        }
        $service = ($this->factories[$contract])();
        if (!$service instanceof $contract) {
            throw new InvalidArgumentException(sprintf('Factory for "%s" returned an incompatible service.', $contract));
        }
        return $this->instances[$contract] = $service;
    }

    public function forget(string $contract): void
    {
        unset($this->instances[$contract], $this->factories[$contract]);
    }

    private function guard(string $contract, bool $replace): void
    {
        if (!interface_exists($contract) && !class_exists($contract)) {
            throw new InvalidArgumentException(sprintf('Unknown service contract "%s".', $contract));
        }
        if (!$replace && $this->has($contract)) {
            throw new InvalidArgumentException(sprintf('A service is already registered for "%s".', $contract));
        }
    }
}
