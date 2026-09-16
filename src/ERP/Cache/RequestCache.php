<?php

declare(strict_types=1);

namespace WPRC\Core\ERP\Cache;

defined('ABSPATH') || exit;

/**
 * Per-PHP-request identity map used before the persistent object cache.
 */
final class RequestCache
{
    /** @var array<string,mixed> */
    private array $items = [];

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->items);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->items[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->items[$key] = $value;
    }

    public function delete(string $key): void
    {
        unset($this->items[$key]);
    }

    public function clear(): void
    {
        $this->items = [];
    }

    public function clearPrefix(string $prefix): void
    {
        foreach (array_keys($this->items) as $key) {
            if (str_starts_with($key, $prefix)) {
                unset($this->items[$key]);
            }
        }
    }
}
