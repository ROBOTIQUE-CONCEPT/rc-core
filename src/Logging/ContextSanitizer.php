<?php

declare(strict_types=1);

namespace WPRC\Core\Logging;

defined('ABSPATH') || exit;

final class ContextSanitizer
{
    private const REDACTED = '[REDACTED]';

    /** @param array<string,mixed> $context @return array<string,mixed> */
    public function sanitize(array $context): array
    {
        return $this->walk($context, 0);
    }

    /** @param array<string,mixed> $values @return array<string,mixed> */
    private function walk(array $values, int $depth): array
    {
        if ($depth >= 5) {
            return ['_truncated' => true];
        }

        $clean = [];
        $count = 0;

        foreach ($values as $key => $value) {
            if (++$count > 100) {
                $clean['_truncated'] = true;
                break;
            }

            $key = (string) $key;
            if ($this->isSensitiveKey($key)) {
                $clean[$key] = self::REDACTED;
                continue;
            }

            if (is_array($value)) {
                $clean[$key] = $this->walk($value, $depth + 1);
                continue;
            }

            if (is_object($value)) {
                $clean[$key] = sprintf('[object:%s]', get_class($value));
                continue;
            }

            if (is_resource($value)) {
                $clean[$key] = '[resource]';
                continue;
            }

            if (is_string($value)) {
                $clean[$key] = function_exists('mb_substr') ? mb_substr($value, 0, 4000) : substr($value, 0, 4000);
                continue;
            }

            $clean[$key] = $value;
        }

        return $clean;
    }

    private function isSensitiveKey(string $key): bool
    {
        return (bool) preg_match('/(?:pass(?:word)?|secret|token|api[_-]?key|authorization|cookie|credential|private[_-]?key|nonce)/i', $key);
    }
}
