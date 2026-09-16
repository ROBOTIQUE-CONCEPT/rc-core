<?php

declare(strict_types=1);

namespace WPRC\Core\Security;

defined('ABSPATH') || exit;

/**
 * Resolves the network peer address seen by PHP.
 *
 * REMOTE_ADDR is intentionally preferred over forwarded headers: values such as
 * X-Forwarded-For are client-controlled unless the reverse-proxy trust chain is
 * explicitly configured. A filter is exposed for infrastructure-specific
 * overrides (Cloudflare, trusted reverse proxy, etc.).
 */
final class RequestIpResolver
{
    public function resolve(): ?string
    {
        $value = isset($_SERVER['REMOTE_ADDR']) && is_string($_SERVER['REMOTE_ADDR'])
            ? trim(wp_unslash($_SERVER['REMOTE_ADDR']))
            : '';

        $ip = filter_var($value, FILTER_VALIDATE_IP) !== false ? $value : null;

        /**
         * Filters the client IP persisted by RC Core.
         *
         * Any replacement value is validated again before use.
         *
         * @param string|null $ip Validated REMOTE_ADDR, or null.
         */
        $filtered = apply_filters('wprc/core/request_ip', $ip);
        if (!is_string($filtered) || filter_var($filtered, FILTER_VALIDATE_IP) === false) {
            return $ip;
        }

        return $filtered;
    }
}
