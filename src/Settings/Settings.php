<?php

declare(strict_types=1);

namespace WPRC\Core\Settings;

defined('ABSPATH') || exit;

/**
 * RC Core settings storage.
 *
 * Settings are site options on a multisite network and regular options on a
 * single-site installation. Consumers do not need to know the architecture.
 */
final class Settings
{
    private const OPTION = 'rc_core_settings';

    /** @return array<string,mixed> */
    public function all(): array
    {
        $value = is_multisite()
            ? get_site_option(self::OPTION, [])
            : get_option(self::OPTION, []);

        return is_array($value) ? $value : [];
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $settings = $this->all();

        return $settings[$key] ?? $default;
    }

    public function set(string $key, mixed $value): bool
    {
        $settings = $this->all();
        $settings[$key] = $value;

        return $this->replace($settings);
    }

    /** @param array<string,mixed> $values */
    public function replace(array $values): bool
    {
        return is_multisite()
            ? update_site_option(self::OPTION, $values)
            : update_option(self::OPTION, $values, false);
    }


    public function publicSiteId(): int
    {
        if (!is_multisite()) {
            return (int) get_current_blog_id();
        }

        $configured = absint($this->get('public_site_id', 0));

        return $configured > 0 ? $configured : $this->mainSiteId();
    }

    public function applicationSiteId(): int
    {
        if (!is_multisite()) {
            return (int) get_current_blog_id();
        }

        $configured = absint($this->get('application_site_id', 0));
        if ($configured > 0) {
            return $configured;
        }

        $publicSiteId = $this->publicSiteId();
        if (function_exists('get_sites')) {
            $siteIds = array_map('intval', get_sites(['fields' => 'ids', 'number' => 0]));
            foreach ($siteIds as $siteId) {
                if ($siteId > 0 && $siteId !== $publicSiteId) {
                    return $siteId;
                }
            }
        }

        return $publicSiteId;
    }

    public function connectorSiteId(): int
    {
        if (!is_multisite()) {
            return (int) get_current_blog_id();
        }

        $configured = absint($this->get('connector_site_id', 0));

        return $configured > 0 ? $configured : $this->mainSiteId();
    }

    public function loggingEnabled(): bool
    {
        return (bool) $this->get('logging_enabled', true);
    }

    public function wordpressSecurityLoggingEnabled(): bool
    {
        return (bool) $this->get('wordpress_security_logging_enabled', true);
    }

    public function logRetentionDays(): int
    {
        return max(1, min(365, absint($this->get('log_retention_days', 30))));
    }

    public function logMaxRows(): int
    {
        return max(1000, min(500000, absint($this->get('log_max_rows', 50000))));
    }

    public function axonautApiUrl(): string
    {
        $value = trim((string) $this->get('axonaut_api_url', 'https://axonaut.com/api/v2'));

        return $value !== '' ? rtrim($value, '/') : 'https://axonaut.com/api/v2';
    }

    private function mainSiteId(): int
    {
        if (is_multisite() && function_exists('get_main_site_id')) {
            return (int) get_main_site_id();
        }

        return (int) get_current_blog_id();
    }
}
