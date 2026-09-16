<?php

declare(strict_types=1);

namespace WPRC\Core\ERP\Cache;

defined('ABSPATH') || exit;

/**
 * Small distributed lock backed by the WordPress object cache.
 *
 * With Redis Object Cache, wp_cache_add() is atomic across PHP workers.
 */
final class CacheLock
{
    public const GROUP = 'wprc_erp_locks';

    public function acquire(string $key, int $ttl = 10): ?string
    {
        $token = wp_generate_uuid4();
        return wp_cache_add($key, $token, self::GROUP, max(1, $ttl)) ? $token : null;
    }

    public function release(string $key, string $token): void
    {
        $current = wp_cache_get($key, self::GROUP);
        if (is_string($current) && hash_equals($current, $token)) {
            wp_cache_delete($key, self::GROUP);
        }
    }
}
