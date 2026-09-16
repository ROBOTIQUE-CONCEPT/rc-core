<?php

declare(strict_types=1);

namespace WPRC\Core\InternalApi;

defined('ABSPATH') || exit;

final class RequestSigner
{
    public function __construct(private readonly InternalApiSecret $secret)
    {
    }

    /** @param array<string,mixed> $query */
    public function sign(
        string $method,
        string $path,
        array $query,
        string $body,
        int $timestamp,
        string $nonce
    ): string {
        return hash_hmac('sha256', $this->canonical($method, $path, $query, $body, $timestamp, $nonce), $this->secret->value());
    }

    /** @param array<string,mixed> $query */
    public function verify(
        string $signature,
        string $method,
        string $path,
        array $query,
        string $body,
        int $timestamp,
        string $nonce
    ): bool {
        if ($signature === '') {
            return false;
        }
        return hash_equals($this->sign($method, $path, $query, $body, $timestamp, $nonce), $signature);
    }

    /** @param array<string,mixed> $query */
    private function canonical(string $method, string $path, array $query, string $body, int $timestamp, string $nonce): string
    {
        $query = $this->canonicalize($query);
        return implode("\n", [
            strtoupper($method),
            '/' . ltrim($path, '/'),
            (string) wp_json_encode($query, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            hash('sha256', $body),
            (string) $timestamp,
            $nonce,
        ]);
    }

    /** @return array<string,mixed> */
    private function canonicalize(array $value): array
    {
        ksort($value);
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = array_is_list($item)
                    ? array_map(fn (mixed $child): mixed => is_array($child) ? $this->canonicalize($child) : $this->normalizeScalar($child), $item)
                    : $this->canonicalize($item);
                continue;
            }
            $value[$key] = $this->normalizeScalar($item);
        }
        return $value;
    }

    private function normalizeScalar(mixed $value): mixed
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        return is_scalar($value) ? (string) $value : '';
    }
}
