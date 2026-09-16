<?php

declare(strict_types=1);

namespace WPRC\Core\ERP;

defined('ABSPATH') || exit;

/**
 * Request-scoped ERP metrics used to detect redundant or expensive reads.
 */
final class ErpTelemetry
{
    private int $logicalReads = 0;
    private int $requestCacheHits = 0;
    private int $persistentCacheHits = 0;
    private int $remoteCalls = 0;
    private int $remoteErrors = 0;
    private float $remoteDurationMs = 0.0;

    /** @var array<string,int> */
    private array $remoteByEndpoint = [];

    public function logicalRead(): void
    {
        $this->logicalReads++;
    }

    public function requestCacheHit(): void
    {
        $this->requestCacheHits++;
    }

    public function persistentCacheHit(): void
    {
        $this->persistentCacheHits++;
    }

    public function remoteCall(string $method, string $endpoint, float $durationMs, int $status): void
    {
        $this->remoteCalls++;
        $this->remoteDurationMs += max(0.0, $durationMs);
        $key = strtoupper($method) . ' ' . $endpoint;
        $this->remoteByEndpoint[$key] = ($this->remoteByEndpoint[$key] ?? 0) + 1;
        // Axonaut uses HTTP 403 as a pagination negotiation response on
        // some collection endpoints, so it must not inflate error metrics.
        if ($status === 0 || ($status >= 400 && $status !== 403)) {
            $this->remoteErrors++;
        }
    }

    /** @return array<string,mixed> */
    public function snapshot(): array
    {
        return [
            'logical_reads' => $this->logicalReads,
            'request_cache_hits' => $this->requestCacheHits,
            'persistent_cache_hits' => $this->persistentCacheHits,
            'remote_calls' => $this->remoteCalls,
            'remote_errors' => $this->remoteErrors,
            'remote_duration_ms' => round($this->remoteDurationMs, 2),
            'remote_by_endpoint' => $this->remoteByEndpoint,
        ];
    }
}
