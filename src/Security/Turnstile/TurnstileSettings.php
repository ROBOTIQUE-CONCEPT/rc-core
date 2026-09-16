<?php

declare(strict_types=1);

namespace WPRC\Core\Security\Turnstile;

use WPRC\Core\Connectors\Turnstile\TurnstileClient;
use WPRC\Core\Settings\Settings;

defined('ABSPATH') || exit;

/**
 * Runtime configuration for Cloudflare Turnstile.
 *
 * Credentials remain owned by the WordPress Connectors integration. This class
 * only stores non-secret runtime behavior in RC Core settings.
 */
final class TurnstileSettings
{
    private const SETTINGS_KEY = 'turnstile';

    /** @var array<string,mixed>|null */
    private ?array $cache = null;

    public function __construct(
        private readonly Settings $settings,
        private readonly TurnstileClient $client
    ) {
    }

    /** @return array<string,mixed> */
    public function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $value = $this->settings->get(self::SETTINGS_KEY, []);
        $this->cache = is_array($value) ? $value : [];

        return $this->cache;
    }

    /** @param array<string,mixed> $values */
    public function replace(array $values): bool
    {
        $sanitized = $this->sanitize($values);
        $updated = $this->settings->set(self::SETTINGS_KEY, $sanitized);
        $this->cache = $sanitized;

        return $updated;
    }

    public function enabled(): bool
    {
        return $this->bool('enabled', false);
    }

    public function enabledForNativeForms(): bool
    {
        return $this->bool('enable_native_forms', true);
    }


    public function enabledForLogin(): bool
    {
        return $this->bool('enable_login', false);
    }

    public function enabledForRegistration(): bool
    {
        return $this->bool('enable_registration', false);
    }

    public function enabledForLostPassword(): bool
    {
        return $this->bool('enable_lost_password', false);
    }

    public function enabledForComments(): bool
    {
        return $this->bool('enable_comments', false);
    }

    public function enabledForPortalLogin(): bool
    {
        return $this->bool('enable_portal_login', true);
    }

    public function enabledForWooLogin(): bool
    {
        return $this->bool('enable_woo_login', false);
    }

    public function enabledForWooRegistration(): bool
    {
        return $this->bool('enable_woo_registration', false);
    }

    public function skipLoggedInComments(): bool
    {
        return $this->bool('skip_logged_in_comments', true);
    }

    public function theme(): string
    {
        $value = (string) ($this->all()['theme'] ?? 'auto');

        return in_array($value, ['auto', 'light', 'dark'], true) ? $value : 'auto';
    }

    public function size(): string
    {
        $value = (string) ($this->all()['size'] ?? 'normal');

        return in_array($value, ['normal', 'flexible', 'compact'], true) ? $value : 'normal';
    }

    public function failureMode(): string
    {
        $value = (string) ($this->all()['failure_mode'] ?? 'closed');

        return in_array($value, ['closed', 'open'], true) ? $value : 'closed';
    }

    public function failOpen(): bool
    {
        return $this->failureMode() === 'open';
    }

    public function isConfigured(): bool
    {
        return $this->client->isConfigured();
    }

    public function siteKey(): string
    {
        $credentials = $this->client->credentials();

        return (string) ($credentials['site_key'] ?? '');
    }

    public function shouldProtect(string $context): bool
    {
        return $this->enabled()
            && $this->isConfigured()
            && $this->isContextEnabled($context);
    }

    public function isContextEnabled(string $context): bool
    {
        return match ($context) {
            'native_form' => $this->enabledForNativeForms(),
            'login' => $this->enabledForLogin(),
            'registration' => $this->enabledForRegistration(),
            'lost_password' => $this->enabledForLostPassword(),
            'comment' => $this->enabledForComments(),
            'portal_login' => $this->enabledForPortalLogin(),
            'woo_login' => $this->enabledForWooLogin(),
            'woo_registration' => $this->enabledForWooRegistration(),
            default => false,
        };
    }

    /** @param array<string,mixed> $values @return array<string,mixed> */
    private function sanitize(array $values): array
    {
        return [
            'enabled' => !empty($values['enabled']),
            'enable_native_forms' => $this->toBool($values['enable_native_forms'] ?? true),
            'enable_login' => $this->toBool($values['enable_login'] ?? false),
            'enable_registration' => $this->toBool($values['enable_registration'] ?? false),
            'enable_lost_password' => $this->toBool($values['enable_lost_password'] ?? false),
            'enable_comments' => $this->toBool($values['enable_comments'] ?? false),
            'enable_portal_login' => $this->toBool($values['enable_portal_login'] ?? true),
            'enable_woo_login' => $this->toBool($values['enable_woo_login'] ?? false),
            'enable_woo_registration' => $this->toBool($values['enable_woo_registration'] ?? false),
            'skip_logged_in_comments' => $this->toBool($values['skip_logged_in_comments'] ?? true),
            'theme' => in_array((string) ($values['theme'] ?? 'auto'), ['auto', 'light', 'dark'], true)
                ? (string) $values['theme']
                : 'auto',
            'size' => in_array((string) ($values['size'] ?? 'normal'), ['normal', 'flexible', 'compact'], true)
                ? (string) $values['size']
                : 'normal',
            'failure_mode' => in_array((string) ($values['failure_mode'] ?? 'closed'), ['closed', 'open'], true)
                ? (string) $values['failure_mode']
                : 'closed',
        ];
    }

    private function bool(string $key, bool $default): bool
    {
        $all = $this->all();

        return array_key_exists($key, $all) ? $this->toBool($all[$key]) : $default;
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array($value, [1, '1', 'true', 'yes', 'on'], true);
    }
}
