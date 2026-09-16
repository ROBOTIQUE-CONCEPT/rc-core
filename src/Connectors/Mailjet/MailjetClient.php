<?php

declare(strict_types=1);

namespace WPRC\Core\Connectors\Mailjet;

use WPRC\Core\Connectors\ConnectorCredentials;
use WPRC\Core\Contracts\LoggerInterface;

defined('ABSPATH') || exit;

/**
 * Low-level Mailjet Send API v3.1 transport.
 */
final class MailjetClient
{
    private const DEFAULT_ENDPOINT = 'https://api.mailjet.com/v3.1/send';

    public function __construct(
        private readonly ConnectorCredentials $credentials,
        private readonly LoggerInterface $logger
    ) {
    }

    public function isConfigured(): bool
    {
        $credentials = $this->credentials->mailjet();
        return $credentials['api_key'] !== '' && $credentials['api_secret'] !== '';
    }

    /** @return array{Email:string,Name:string} */
    public function defaultFrom(): array
    {
        return [
            'Email' => sanitize_email((string) get_option('admin_email')),
            'Name' => sanitize_text_field((string) get_option('blogname')),
        ];
    }

    public function sendMessage(MailjetMessage $message, int $timeout = 8): MailjetResult
    {
        if (!$message->hasRecipients()) {
            return MailjetResult::failure('missing_recipients');
        }

        return $this->sendPayload($message->toMailjetPayload($this->defaultFrom()), $timeout);
    }

    /** @param array<string,mixed> $payload */
    public function sendPayload(array $payload, int $timeout = 8): MailjetResult
    {
        $credentials = $this->credentials->mailjet();
        $apiKey = $credentials['api_key'];
        $apiSecret = $credentials['api_secret'];

        if ($apiKey === '' || $apiSecret === '') {
            return MailjetResult::failure('missing_mailjet_credentials');
        }

        $body = wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($body) || $body === '') {
            return MailjetResult::failure('invalid_mailjet_payload');
        }

        $response = wp_remote_post(self::DEFAULT_ENDPOINT, [
            'timeout' => max(1, $timeout),
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode($apiKey . ':' . $apiSecret),
            ],
            'body' => $body,
        ]);

        if (is_wp_error($response)) {
            $this->logger->error('Mailjet API request failed.', 'mailjet', [
                'error' => $response->get_error_message(),
            ], 'mailjet_transport_error');
            return MailjetResult::failure($response->get_error_message());
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $raw = (string) wp_remote_retrieve_body($response);
        $decoded = json_decode($raw, true);
        $status = is_array($decoded) ? ($decoded['Messages'][0]['Status'] ?? null) : null;
        $success = $code >= 200 && $code < 300 && in_array($status, ['success', 'queued'], true);

        if ($success) {
            return MailjetResult::success($code, $decoded);
        }

        $error = $this->extractError($decoded, $raw);
        $this->logger->error('Mailjet API send failed.', 'mailjet', [
            'code' => $code,
            'error' => $error,
        ], 'mailjet_send_failed');

        return MailjetResult::failure($error, $code, $decoded);
    }

    private function extractError(mixed $decoded, string $raw): string
    {
        if (is_array($decoded) && !empty($decoded['Messages'][0]['Errors'][0]['ErrorMessage'])) {
            return (string) $decoded['Messages'][0]['Errors'][0]['ErrorMessage'];
        }
        if (is_array($decoded) && !empty($decoded['ErrorMessage'])) {
            return (string) $decoded['ErrorMessage'];
        }
        return $raw !== '' ? substr($raw, 0, 1000) : 'mailjet_send_failed';
    }
}
