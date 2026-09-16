<?php

declare(strict_types=1);

namespace WPRC\Core\InternalApi;

use RuntimeException;
use WPRC\Core\Settings\Settings;

defined('ABSPATH') || exit;

/**
 * Network-shared secret used only for RC internal site-to-site requests.
 */
final class InternalApiSecret
{
    private const SETTING = 'internal_api_secret';

    public function __construct(private readonly Settings $settings)
    {
    }

    public function value(): string
    {
        if (defined('RC_INTERNAL_API_SECRET') && is_string(RC_INTERNAL_API_SECRET) && trim(RC_INTERNAL_API_SECRET) !== '') {
            return trim(RC_INTERNAL_API_SECRET);
        }

        $secret = trim((string) $this->settings->get(self::SETTING, ''));
        if ($secret !== '') {
            return $secret;
        }

        $secret = wp_generate_password(64, true, true);
        if (!$this->settings->set(self::SETTING, $secret)) {
            throw new RuntimeException('Unable to persist the RC internal API secret.');
        }

        return $secret;
    }
}
