<?php

declare(strict_types=1);

namespace WPRC\Core\InternalApi;

use WP_Error;

defined('ABSPATH') || exit;

/**
 * Signed internal HTTP client for explicit www <-> my application boundaries.
 */
final class Client
{
    public function __construct(private readonly RequestSigner $signer)
    {
    }

    /**
     * @param array<string,mixed> $query
     * @param array<string,mixed>|string|null $body
     * @param array<string,string> $headers
     * @return array{status:int,body:string,decoded:mixed,error:?WP_Error}
     */
    public function request(
        string $method,
        string $baseUrl,
        string $route,
        array $query = [],
        array|string|null $body = null,
        array $headers = [],
        int $timeout = 8
    ): array {
        $method = strtoupper($method);
        $route = '/' . ltrim($route, '/');
        $url = rtrim($baseUrl, '/') . $route;
        if ($query !== []) {
            $url = add_query_arg($query, $url);
        }

        $encodedBody = '';
        if (is_array($body)) {
            $encoded = wp_json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $encodedBody = is_string($encoded) ? $encoded : '';
        } elseif (is_string($body)) {
            $encodedBody = $body;
        }

        $timestamp = time();
        $nonce = wp_generate_uuid4();
        $signature = $this->signer->sign($method, $route, $query, $encodedBody, $timestamp, $nonce);

        $requestHeaders = array_merge([
            'Accept' => 'application/json',
            'X-RC-Timestamp' => (string) $timestamp,
            'X-RC-Nonce' => $nonce,
            'X-RC-Signature' => $signature,
        ], $headers);

        if ($encodedBody !== '') {
            $requestHeaders['Content-Type'] = $requestHeaders['Content-Type'] ?? 'application/json';
        }

        $response = wp_remote_request($url, [
            'method' => $method,
            'timeout' => max(1, $timeout),
            'redirection' => 0,
            'httpversion' => '1.1',
            'sslverify' => true,
            'headers' => $requestHeaders,
            'body' => $encodedBody,
        ]);

        if ($response instanceof WP_Error) {
            return ['status' => 0, 'body' => '', 'decoded' => null, 'error' => $response];
        }

        $rawBody = (string) wp_remote_retrieve_body($response);
        $decoded = $rawBody !== '' ? json_decode($rawBody, true) : null;
        if (JSON_ERROR_NONE !== json_last_error()) {
            $decoded = null;
        }

        return [
            'status' => (int) wp_remote_retrieve_response_code($response),
            'body' => $rawBody,
            'decoded' => $decoded,
            'error' => null,
        ];
    }
}
