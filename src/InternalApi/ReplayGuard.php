<?php

declare(strict_types=1);

namespace WPRC\Core\InternalApi;

defined('ABSPATH') || exit;

final class ReplayGuard
{
    public const GROUP = 'wprc_internal_api';

    public function claim(string $nonce, int $timestamp, int $windowSeconds = 300): bool
    {
        $now = time();
        $windowSeconds = max(30, min(900, $windowSeconds));
        if ($nonce === '' || abs($now - $timestamp) > $windowSeconds) {
            return false;
        }

        $key = 'nonce:' . hash('sha256', $nonce);
        return wp_cache_add($key, $timestamp, self::GROUP, $windowSeconds * 2);
    }
}
