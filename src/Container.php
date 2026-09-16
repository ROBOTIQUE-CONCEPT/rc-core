<?php

declare(strict_types=1);

namespace WPRC\Core;

use Closure;
use InvalidArgumentException;
use WPRC\Core\Contracts\ServiceProviderInterface;

defined('ABSPATH') || exit;

/**
 * Small dependency container used by RC plugins.
 *
 * RC Core owns the single container instance. Other RC plugins may register
 * namespaced services in it but must never overwrite Core services silently.
 */
final class Container
{
    /** @var array<string, Closure(self):mixed> */
    private array $definitions = [];

    /** @var array<string, bool> */
    private array $shared = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    /** @var array<string, string> */
    private array $aliases = [];

    /** @param Closure(self):mixed $factory */
    public function singleton(string $id, Closure $factory, bool $replace = false): void
    {
        $this->guardRegistration($id, $replace);
        $this->definitions[$id] = $factory;
        $this->shared[$id] = true;
        unset($this->instances[$id]);
    }

    /** @param Closure(self):mixed $factory */
    public function factory(string $id, Closure $factory, bool $replace = false): void
    {
        $this->guardRegistration($id, $replace);
        $this->definitions[$id] = $factory;
        $this->shared[$id] = false;
        unset($this->instances[$id]);
    }

    public function instance(string $id, mixed $instance, bool $replace = false): void
    {
        $this->guardRegistration($id, $replace);
        $this->instances[$id] = $instance;
        unset($this->definitions[$id], $this->shared[$id]);
    }

    public function alias(string $alias, string $targetId, bool $replace = false): void
    {
        if (!$replace && array_key_exists($alias, $this->aliases)) {
            throw new InvalidArgumentException(sprintf('RC Core service alias "%s" is already registered.', $alias));
        }
        $this->aliases[$alias] = $targetId;
    }

    public function registerProvider(ServiceProviderInterface $provider): void
    {
        $provider->register($this);
    }

    public function has(string $id): bool
    {
        $resolved = $this->resolveId($id);
        return array_key_exists($resolved, $this->instances) || array_key_exists($resolved, $this->definitions);
    }

    public function get(string $id): mixed
    {
        $resolved = $this->resolveId($id);

        if (array_key_exists($resolved, $this->instances)) {
            return $this->instances[$resolved];
        }

        if (!array_key_exists($resolved, $this->definitions)) {
            throw new InvalidArgumentException(sprintf('Unknown RC Core service "%s".', $id));
        }

        $service = ($this->definitions[$resolved])($this);
        if (($this->shared[$resolved] ?? false) === true) {
            $this->instances[$resolved] = $service;
        }

        return $service;
    }

    private function resolveId(string $id): string
    {
        $seen = [];
        while (isset($this->aliases[$id])) {
            if (isset($seen[$id])) {
                throw new InvalidArgumentException(sprintf('Circular RC Core service alias detected for "%s".', $id));
            }
            $seen[$id] = true;
            $id = $this->aliases[$id];
        }
        return $id;
    }

    private function guardRegistration(string $id, bool $replace): void
    {
        if ($id === '') {
            throw new InvalidArgumentException('RC Core service ID cannot be empty.');
        }
        if (!$replace && $this->has($id)) {
            throw new InvalidArgumentException(sprintf('RC Core service "%s" is already registered.', $id));
        }
    }
}
