<?php

declare(strict_types=1);

namespace WPRC\Core\Connectors\Turnstile;

use WPRC\Core\Connectors\ConnectorCredentials;
use WPRC\Core\Contracts\LoggerInterface;
use WPRC\Core\Logging\SecurityLogRateLimiter;
use WP_Error;

defined('ABSPATH') || exit;

final class TurnstileClient
{
    private const VERIFY_ENDPOINT = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function __construct(
        private readonly ConnectorCredentials $credentials,
        private readonly LoggerInterface $logger,
        private readonly SecurityLogRateLimiter $rateLimiter
    ) {
    }

    /** @return array{site_key:string,secret_key:string,source:string} */
    public function credentials(): array
    {
        return $this->credentials->turnstile();
    }

    public function isConfigured(): bool
    {
        return $this->credentials->turnstileConfigured();
    }

    /**
     * @return array{success:bool,error_codes:array<int,string>,transport_error:?string,body:array<string,mixed>|null}
     */
    public function verify(string $token, string $remoteIp = '', string $idempotencyKey = '', int $timeout = 8): array
    {
        $credentials = $this->credentials();
        if ($credentials['secret_key'] === '') {
            return [
                'success' => false,
                'error_codes' => ['missing-input-secret'],
                'transport_error' => null,
                'body' => null,
            ];
        }

        $body = [
            'secret' => $credentials['secret_key'],
            'response' => $token,
        ];
        if ($remoteIp !== '') {
            $body['remoteip'] = $remoteIp;
        }
        if ($idempotencyKey !== '') {
            $body['idempotency_key'] = $idempotencyKey;
        }

        $response = wp_remote_post(self::VERIFY_ENDPOINT, [
            'timeout' => max(1, $timeout),
            'redirection' => 0,
            'sslverify' => true,
            'body' => $body,
        ]);

        if ($response instanceof WP_Error) {
            if ($this->rateLimiter->allow('turnstile_transport_error', $remoteIp, 60)) {
                $this->logger->error('Turnstile verification transport failed.', 'turnstile', [
                    'error' => $response->get_error_message(),
                ], 'transport_error');
            }

            return [
                'success' => false,
                'error_codes' => ['request-failed'],
                'transport_error' => $response->get_error_message(),
                'body' => null,
            ];
        }

        $decoded = json_decode((string) wp_remote_retrieve_body($response), true);
        $decoded = is_array($decoded) ? $decoded : null;
        $errors = $decoded !== null && is_array($decoded['error-codes'] ?? null)
            ? array_values(array_map('strval', $decoded['error-codes']))
            : [];

        $success = $decoded !== null && !empty($decoded['success']);
        if (!$success && $this->rateLimiter->allow('turnstile_verification_failed', $remoteIp, 30)) {
            $this->logger->warning('Turnstile verification failed.', 'turnstile', [
                'error_codes' => $errors,
                'hostname' => is_string($decoded['hostname'] ?? null) ? $decoded['hostname'] : '',
                'action' => is_string($decoded['action'] ?? null) ? $decoded['action'] : '',
                'http_status' => wp_remote_retrieve_response_code($response),
            ], 'verification_failed');
        }

        return [
            'success' => $success,
            'error_codes' => $errors,
            'transport_error' => null,
            'body' => $decoded,
        ];
    }
}
