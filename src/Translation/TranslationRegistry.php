<?php

declare(strict_types=1);

namespace WPRC\Core\Translation;

use InvalidArgumentException;

defined('ABSPATH') || exit;

/**
 * Runtime registry for stable Robotique Concept translation keys.
 *
 * Core owns the translation infrastructure only. Each RC module registers its
 * own technical keys and fallback copy through TranslationService::registerCatalog().
 */
final class TranslationRegistry
{
    public const DOMAIN = 'RCONCEPT';

    /**
     * @var array<string,array{owner:string,fallback:string,multiline:bool}>
     */
    private array $definitions = [];

    /**
     * Register a module-owned translation catalog.
     *
     * @param array<string,string|array{fallback?:string,multiline?:bool}> $definitions
     */
    public function registerCatalog(string $owner, array $definitions): void
    {
        $owner = sanitize_key($owner);
        if ($owner === '') {
            throw new InvalidArgumentException('RC Core translation catalog owner cannot be empty.');
        }

        foreach ($definitions as $key => $definition) {
            $key = $this->normalizeKey((string) $key);
            if ($key === '') {
                throw new InvalidArgumentException('RC Core translation keys cannot be empty.');
            }

            if (is_string($definition)) {
                $fallback = $definition;
                $multiline = true;
            } elseif (is_array($definition)) {
                $fallback = isset($definition['fallback']) && is_string($definition['fallback'])
                    ? $definition['fallback']
                    : '';
                $multiline = (bool) ($definition['multiline'] ?? true);
            } else {
                throw new InvalidArgumentException(sprintf('Invalid RC translation definition for "%s".', $key));
            }

            if (isset($this->definitions[$key])) {
                $existing = $this->definitions[$key];
                if (
                    $existing['owner'] !== $owner
                    || $existing['fallback'] !== $fallback
                    || $existing['multiline'] !== $multiline
                ) {
                    throw new InvalidArgumentException(sprintf(
                        'RC translation key "%s" is already registered by "%s".',
                        $key,
                        $existing['owner']
                    ));
                }
                continue;
            }

            $this->definitions[$key] = [
                'owner' => $owner,
                'fallback' => $fallback,
                'multiline' => $multiline,
            ];
        }
    }

    /**
     * @return array<string,array{owner:string,fallback:string,multiline:bool}>
     */
    public function all(): array
    {
        return $this->definitions;
    }

    /**
     * @return array{owner:string,fallback:string,multiline:bool}|null
     */
    public function definition(string $key): ?array
    {
        $key = $this->normalizeKey($key);
        return $this->definitions[$key] ?? null;
    }

    public function has(string $key): bool
    {
        return isset($this->definitions[$this->normalizeKey($key)]);
    }

    public function source(string $key): string
    {
        return self::DOMAIN . '_' . $this->normalizeKey($key);
    }

    public function fallback(string $key): string
    {
        return $this->definition($key)['fallback'] ?? '';
    }

    public function multiline(string $key): bool
    {
        return $this->definition($key)['multiline'] ?? true;
    }

    public function owner(string $key): string
    {
        return $this->definition($key)['owner'] ?? '';
    }

    public function normalizeKey(string $key): string
    {
        $key = trim($key);
        if (str_starts_with($key, self::DOMAIN . '_')) {
            $key = substr($key, strlen(self::DOMAIN) + 1);
        }

        return strtolower($key);
    }
}
