<?php

declare(strict_types=1);

namespace WPRC\Core\Connectors\Axonaut\Providers;

use WPRC\Core\Connectors\Axonaut\AxonautClient;
use WPRC\Core\ERP\ProviderException;

defined('ABSPATH') || exit;

abstract class AbstractProvider
{
    public function __construct(protected readonly AxonautClient $client)
    {
    }

    /** @return array<int,array<string,mixed>> */
    protected function extractList(mixed $response, array $keys = ['data', 'results', 'items']): array
    {
        if (!is_array($response)) {
            return [];
        }
        if (array_is_list($response)) {
            return array_values(array_filter($response, 'is_array'));
        }
        foreach ($keys as $key) {
            if (isset($response[$key]) && is_array($response[$key]) && array_is_list($response[$key])) {
                return array_values(array_filter($response[$key], 'is_array'));
            }
        }
        return [];
    }

    /** @param array<string,mixed> $payload */
    protected function externalId(array $payload): string
    {
        foreach (['id', 'external_id', 'uuid'] as $key) {
            if (isset($payload[$key]) && is_scalar($payload[$key])) {
                $value = trim((string) $payload[$key]);
                if ($value !== '') {
                    return $value;
                }
            }
        }
        return '';
    }

    /** @param array<string,mixed> $response */
    protected function assertCreated(array $response, string $entity): array
    {
        $status = (int) ($response['status'] ?? 0);
        $decoded = $response['decoded'] ?? null;
        if (in_array($status, [200, 201], true) && is_array($decoded)) {
            return $decoded;
        }

        $message = $this->errorMessage($response);
        throw new ProviderException(
            $message !== '' ? $message : sprintf('Axonaut %s creation failed.', $entity),
            $status
        );
    }

    /** @param array<string,mixed> $response */
    protected function errorMessage(array $response): string
    {
        $decoded = $response['decoded'] ?? null;
        if (is_array($decoded)) {
            $candidates = [
                $decoded['error']['message'] ?? null,
                $decoded['message'] ?? null,
                $decoded['error'] ?? null,
            ];
            foreach ($candidates as $candidate) {
                if (is_scalar($candidate) && trim((string) $candidate) !== '') {
                    return trim((string) $candidate);
                }
            }
        }
        $body = trim((string) ($response['body'] ?? ''));
        return $body;
    }

    protected function normalizeSearch(string $value): string
    {
        return strtolower(remove_accents(trim($value)));
    }

    protected function scalarId(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    /** @param array<string,mixed> $payload */
    protected function nestedId(array $payload, string $scalarKey, string $objectKey): string
    {
        $id = $this->scalarId($payload[$scalarKey] ?? '');
        if ($id !== '') {
            return $id;
        }
        $object = $payload[$objectKey] ?? null;
        if (is_array($object)) {
            return $this->scalarId($object['id'] ?? '');
        }
        return '';
    }

    /**
     * Shared `findMany()` strategy: dedupe the requested ids and resolve each
     * one through the provider's own `find()`. Axonaut does not expose a
     * generic "IN" filter across these endpoints, so a batch is a loop over
     * unitary lookups — but each of those already goes through
     * `AxonautClient::get()`'s request cache / persistent cache / cache lock,
     * so an id already resolved individually (or by a previous `findMany()`
     * call) costs zero network calls here, and vice versa.
     *
     * An id that Axonaut does not know about is simply absent from the
     * result (no exception). A hard failure of an individual request
     * (timeout, HTTP error) is not swallowed: it propagates as
     * `ProviderException`, aborting the batch, since that reflects a failure
     * of the request itself rather than a missing entity.
     *
     * @param string[] $externalIds
     * @param callable(string): (object|null) $finder
     * @return array<string, object> Indexed by externalId.
     */
    protected function findManyByFind(array $externalIds, callable $finder): array
    {
        $result = [];
        $seen = [];
        foreach ($externalIds as $externalId) {
            $id = trim((string) $externalId);
            if ($id === '' || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $entity = $finder($id);
            if ($entity !== null) {
                $result[$id] = $entity;
            }
        }
        return $result;
    }
}
