<?php

declare(strict_types=1);

namespace WPRC\Core\Translation;

use WPRC\Core\Content\PlaceholderEngine;
use WPRC\Core\Data\Content\PlaceholderContext;

defined('ABSPATH') || exit;

/**
 * Central RC translation service.
 *
 * Processing order is intentionally:
 *   technical key -> Polylang translation -> Core placeholder rendering.
 *
 * RC modules own their translation catalogs. Core only owns registration,
 * translation lookup and rendering.
 */
final class TranslationService
{
    private bool $initialized = false;

    public function __construct(
        private readonly TranslationRegistry $registry,
        private readonly PolylangAdapter $polylang,
        private readonly PlaceholderEngine $placeholders
    ) {
    }

    public function init(): void
    {
        if ($this->initialized) {
            return;
        }
        $this->initialized = true;

        add_action('admin_init', [$this, 'registerStrings'], 20);
    }

    /**
     * Register a translation catalog owned by an RC plugin or theme.
     *
     * @param array<string,string|array{fallback?:string,multiline?:bool}> $definitions
     */
    public function registerCatalog(string $owner, array $definitions): void
    {
        $this->registry->registerCatalog($owner, $definitions);

        // Normal load order registers catalogs before admin_init. This branch
        // keeps late-loaded integrations deterministic as well.
        if (did_action('admin_init') > 0) {
            $this->registerDefinitions($definitions);
        }
    }

    public function registerStrings(): void
    {
        if (!$this->polylang->available()) {
            return;
        }

        foreach ($this->registry->all() as $key => $definition) {
            $source = $this->registry->source($key);
            $this->polylang->register(
                $source,
                $source,
                TranslationRegistry::DOMAIN,
                (bool) $definition['multiline']
            );
        }
    }

    public function translate(string $key, ?PlaceholderContext $context = null): string
    {
        $source = $this->registry->source($key);
        $translated = $this->polylang->translate($source);

        if ($translated === $source) {
            $fallback = $this->registry->fallback($key);
            if ($fallback !== '') {
                $translated = $fallback;
            }
        }

        return $this->placeholders->render(
            $translated,
            $context ?? PlaceholderContext::current()
        );
    }

    public function translateForPost(string $key, int $postId): string
    {
        $postId = absint($postId);
        if ($postId <= 0) {
            return $this->translate($key);
        }

        return $this->translate(
            $key,
            new PlaceholderContext(
                $postId,
                (int) get_queried_object_id() ?: null
            )
        );
    }

    /**
     * @param array<string,string|array{fallback?:string,multiline?:bool}> $definitions
     */
    private function registerDefinitions(array $definitions): void
    {
        if (!$this->polylang->available()) {
            return;
        }

        foreach (array_keys($definitions) as $key) {
            $source = $this->registry->source((string) $key);
            $this->polylang->register(
                $source,
                $source,
                TranslationRegistry::DOMAIN,
                $this->registry->multiline((string) $key)
            );
        }
    }
}
