<?php

declare(strict_types=1);

namespace WPRC\Core\Contracts;

defined('ABSPATH') || exit;

interface CacheInterface
{
    public function get(string $key, string $group = 'wprc_core', mixed $default = null): mixed;
    public function set(string $key, mixed $value, string $group = 'wprc_core', int $ttl = 0): bool;
    public function delete(string $key, string $group = 'wprc_core'): bool;
    public function flushGroup(string $group): bool;
}
