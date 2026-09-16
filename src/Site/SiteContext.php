<?php

declare(strict_types=1);

namespace WPRC\Core\Site;

use WPRC\Core\Settings\Settings;

defined('ABSPATH') || exit;

/**
 * Resolves the active RC sites without exposing multisite branching to clients.
 *
 * Site identities are configured once at Network level. Business modules may
 * then reason about "public" or "application" without hard-coding blog IDs or
 * domains.
 */
final class SiteContext
{
    public function __construct(private readonly Settings $settings)
    {
    }

    public function publicSiteId(): int
    {
        return $this->settings->publicSiteId();
    }

    public function applicationSiteId(): int
    {
        return $this->settings->applicationSiteId();
    }

    public function connectorSiteId(): int
    {
        return $this->settings->connectorSiteId();
    }

    public function isPublicSite(): bool
    {
        return (int) get_current_blog_id() === $this->publicSiteId();
    }

    public function isApplicationSite(): bool
    {
        return (int) get_current_blog_id() === $this->applicationSiteId();
    }

    public function isConnectorSite(): bool
    {
        return (int) get_current_blog_id() === $this->connectorSiteId();
    }

    public function publicUrl(string $path = ''): string
    {
        return $this->siteUrl($this->publicSiteId(), $path);
    }

    public function applicationUrl(string $path = ''): string
    {
        return $this->siteUrl($this->applicationSiteId(), $path);
    }

    public function applicationRestUrl(string $route = ''): string
    {
        $base = rtrim($this->applicationUrl('/wp-json/'), '/');
        return $route === '' ? $base : $base . '/' . ltrim($route, '/');
    }

    public function publicRestUrl(string $route = ''): string
    {
        $base = rtrim($this->publicUrl('/wp-json/'), '/');
        return $route === '' ? $base : $base . '/' . ltrim($route, '/');
    }

    /** @return int[] */
    public function siteIds(): array
    {
        if (!is_multisite() || !function_exists('get_sites')) {
            return [(int) get_current_blog_id()];
        }

        return array_map(
            'intval',
            get_sites(['fields' => 'ids', 'number' => 0])
        );
    }

    /** @template T @param callable():T $callback @return T */
    public function onSite(int $siteId, callable $callback): mixed
    {
        if (!is_multisite() || $siteId === get_current_blog_id()) {
            return $callback();
        }

        switch_to_blog($siteId);
        try {
            return $callback();
        } finally {
            restore_current_blog();
        }
    }

    private function siteUrl(int $siteId, string $path = ''): string
    {
        if (!is_multisite() || $siteId === get_current_blog_id()) {
            return home_url($path);
        }

        return get_home_url($siteId, $path);
    }
}
