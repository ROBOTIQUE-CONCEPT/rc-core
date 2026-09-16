<?php

declare(strict_types=1);

namespace WPRC\Core\Logging;

use WPRC\Core\Security\RequestIpResolver;

defined('ABSPATH') || exit;

/**
 * Lightweight flood protection for security-event logging.
 *
 * Cross-request throttling is only enabled when a persistent object cache is
 * available. Without one, RC Core avoids adding transient/database writes and
 * lets the normal log retention/max-row safeguards apply.
 */
final class SecurityLogRateLimiter
{
    private const CACHE_GROUP = 'wprc_security';

    public function __construct(private readonly RequestIpResolver $ipResolver)
    {
    }

    public function allow(string $event, ?string $ipAddress = null, int $ttl = 30): bool
    {
        if (!wp_using_ext_object_cache()) {
            return true;
        }

        $ip = $this->normalizeIp($ipAddress) ?? $this->ipResolver->resolve() ?? 'unknown';
        $event = sanitize_key($event);
        $key = 'event:' . hash('sha256', $event . '|' . $ip);

        return wp_cache_add($key, 1, self::CACHE_GROUP, max(1, $ttl));
    }

    private function normalizeIp(?string $ipAddress): ?string
    {
        if ($ipAddress === null) {
            return null;
        }

        $ipAddress = trim($ipAddress);

        return filter_var($ipAddress, FILTER_VALIDATE_IP) !== false ? $ipAddress : null;
    }
}
