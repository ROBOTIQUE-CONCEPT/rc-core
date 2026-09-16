<?php

declare(strict_types=1);

namespace WPRC\Core\ERP\Cache;

defined('ABSPATH') || exit;

/**
 * Canonical cache-key builder for ERP data.
 */
final class ErpCacheKeyFactory
{
    public function http(string $source, string $method, string $url, string $credentialContext = ''): string
    {
        $source = sanitize_key($source);
        return $source . ':' . md5(strtoupper($method) . '|' . $url . '|' . hash('sha256', $credentialContext));
    }

    public function entity(string $source, string $entity, string $externalId): string
    {
        return sprintf(
            '%s:entity:%s:%s',
            sanitize_key($source),
            sanitize_key($entity),
            hash('sha256', trim($externalId))
        );
    }

    /** @param array<string,mixed> $query */
    public function collection(string $source, string $entity, array $query): string
    {
        $query = $this->canonicalize($query);
        return sprintf(
            '%s:collection:%s:%s',
            sanitize_key($source),
            sanitize_key($entity),
            hash('sha256', (string) wp_json_encode($query))
        );
    }

    /** @return array<string,mixed> */
    private function canonicalize(array $value): array
    {
        ksort($value);
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = array_is_list($item)
                    ? array_map(fn (mixed $child): mixed => is_array($child) ? $this->canonicalize($child) : $child, $item)
                    : $this->canonicalize($item);
            }
        }
        return $value;
    }
}
