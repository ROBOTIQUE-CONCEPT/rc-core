<?php

declare(strict_types=1);

namespace WPRC\Core\Cache;

use WPRC\Core\Contracts\CacheInterface;

defined('ABSPATH') || exit;

final class WordPressCache implements CacheInterface
{
    public function get(string $key, string $group = 'wprc_core', mixed $default = null): mixed
    {
        $found = false;
        $value = wp_cache_get($key, $group, false, $found);
        return $found ? $value : $default;
    }

    public function set(string $key, mixed $value, string $group = 'wprc_core', int $ttl = 0): bool
    {
        return wp_cache_set($key, $value, $group, max(0, $ttl));
    }

    public function delete(string $key, string $group = 'wprc_core'): bool
    {
        return wp_cache_delete($key, $group);
    }

    public function flushGroup(string $group): bool
    {
        return function_exists('wp_cache_flush_group') ? wp_cache_flush_group($group) : false;
    }
}
