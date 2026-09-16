<?php

declare(strict_types=1);

namespace WPRC\Core\Connectors;

use WPRC\Core\Site\SiteContext;

defined('ABSPATH') || exit;

final class ConnectorCredentials
{
    public const AXONAUT_SETTING = 'connectors_crm_wprc_axonaut_api_key';
    public const MAILJET_SETTING = 'connectors_email_marketing_wprc_mailjet_application_password';
    public const TURNSTILE_SETTING = 'connectors_security_rc_turnstile_application_password';

    public function __construct(private readonly SiteContext $sites)
    {
    }

    public function axonautApiKey(): string
    {
        $environment = getenv('RC_CORE_AXONAUT_API_KEY');
        if (is_string($environment) && trim($environment) !== '') {
            return trim($environment);
        }

        if (defined('RC_CORE_AXONAUT_API_KEY')) {
            $value = constant('RC_CORE_AXONAUT_API_KEY');
            if (is_scalar($value) && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return (string) $this->onConnectorSite(static function (): string {
            $stored = get_option(self::AXONAUT_SETTING, '');
            return is_scalar($stored) ? trim((string) $stored) : '';
        });
    }


    /** @return array{api_key:string,api_secret:string,source:string} */
    public function mailjet(): array
    {
        $environment = getenv('RC_CORE_MAILJET_CREDENTIALS');
        if (is_string($environment) && ($parsed = $this->parsePair($environment)) !== null) {
            return $parsed + ['source' => 'environment'];
        }

        if (defined('RC_CORE_MAILJET_CREDENTIALS')) {
            $value = constant('RC_CORE_MAILJET_CREDENTIALS');
            if (is_string($value) && ($parsed = $this->parsePair($value)) !== null) {
                return $parsed + ['source' => 'constant'];
            }
        }

        $stored = $this->onConnectorSite(static fn (): mixed => get_option(self::MAILJET_SETTING, []));
        if (is_array($stored)) {
            $username = isset($stored['username']) && is_string($stored['username']) ? trim($stored['username']) : '';
            $password = isset($stored['password']) && is_string($stored['password']) ? trim($stored['password']) : '';
            if ($username !== '' && $password !== '') {
                return ['api_key' => $username, 'api_secret' => $password, 'source' => 'database'];
            }
        }

        return ['api_key' => '', 'api_secret' => '', 'source' => 'none'];
    }

    /** @return array{site_key:string,secret_key:string,source:string} */
    public function turnstile(): array
    {
        $environment = getenv('RC_CORE_TURNSTILE_CREDENTIALS');
        if (is_string($environment) && ($parsed = $this->parsePair($environment)) !== null) {
            return ['site_key' => $parsed['api_key'], 'secret_key' => $parsed['api_secret'], 'source' => 'environment'];
        }

        if (defined('RC_CORE_TURNSTILE_CREDENTIALS')) {
            $value = constant('RC_CORE_TURNSTILE_CREDENTIALS');
            if (is_string($value) && ($parsed = $this->parsePair($value)) !== null) {
                return ['site_key' => $parsed['api_key'], 'secret_key' => $parsed['api_secret'], 'source' => 'constant'];
            }
        }

        $stored = $this->onConnectorSite(static fn (): mixed => get_option(self::TURNSTILE_SETTING, []));
        if (is_array($stored)) {
            $siteKey = isset($stored['username']) && is_string($stored['username']) ? trim($stored['username']) : '';
            $secretKey = isset($stored['password']) && is_string($stored['password']) ? trim($stored['password']) : '';
            if ($siteKey !== '' && $secretKey !== '') {
                return ['site_key' => $siteKey, 'secret_key' => $secretKey, 'source' => 'database'];
            }
        }

        return ['site_key' => '', 'secret_key' => '', 'source' => 'none'];
    }

    public function axonautConfigured(): bool
    {
        return $this->axonautApiKey() !== '';
    }


    public function mailjetConfigured(): bool
    {
        $credentials = $this->mailjet();
        return $credentials['api_key'] !== '' && $credentials['api_secret'] !== '';
    }

    public function turnstileConfigured(): bool
    {
        $credentials = $this->turnstile();
        return $credentials['site_key'] !== '' && $credentials['secret_key'] !== '';
    }


    private function onConnectorSite(callable $callback): mixed
    {
        return $this->sites->onSite($this->sites->connectorSiteId(), $callback);
    }

    /** @return array{api_key:string,api_secret:string}|null */
    private function parsePair(string $value): ?array
    {
        $separator = strpos($value, ':');
        if ($separator === false) {
            return null;
        }

        $apiKey = trim(substr($value, 0, $separator));
        $apiSecret = trim(substr($value, $separator + 1));
        if ($apiKey === '' || $apiSecret === '') {
            return null;
        }

        return ['api_key' => $apiKey, 'api_secret' => $apiSecret];
    }
}
