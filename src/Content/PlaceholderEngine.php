<?php

declare(strict_types=1);

namespace WPRC\Core\Content;

use InvalidArgumentException;
use WPRC\Core\Contracts\PlaceholderProviderInterface;
use WPRC\Core\Data\Content\PlaceholderContext;

defined('ABSPATH') || exit;

/**
 * Lightweight, explicit placeholder renderer shared by RC plugins.
 *
 * Syntax:
 *   [{product_brand}]
 *   [{product_brand|Robotique Concept}]
 *
 * Unknown or empty placeholders render as an empty string on the frontend,
 * unless an explicit fallback is supplied. Values are escaped as plain text
 * before insertion into rendered HTML.
 */
final class PlaceholderEngine
{
    /** @var array<string,PlaceholderProviderInterface> */
    private array $providers = [];

    /**
     * Register a provider for its declared placeholder names.
     */
    public function registerProvider(PlaceholderProviderInterface $provider, bool $replace = false): void
    {
        foreach ($provider->placeholders() as $placeholder) {
            $placeholder = $this->normalizeName($placeholder);
            if ($placeholder === '') {
                throw new InvalidArgumentException('RC Core placeholder names cannot be empty.');
            }
            if (!$replace && isset($this->providers[$placeholder])) {
                throw new InvalidArgumentException(sprintf('RC Core placeholder "%s" is already registered.', $placeholder));
            }
            $this->providers[$placeholder] = $provider;
        }
    }

    public function has(string $placeholder): bool
    {
        return isset($this->providers[$this->normalizeName($placeholder)]);
    }

    /**
     * Replace placeholders in already-rendered text/HTML.
     */
    public function render(string $content, ?PlaceholderContext $context = null): string
    {
        if ($content === '' || !str_contains($content, '[{')) {
            return $content;
        }

        $context ??= PlaceholderContext::current();

        $rendered = preg_replace_callback(
            '/\[\{([A-Za-z][A-Za-z0-9_.-]*)(?:\|([^{}\]]*))?\}\]/',
            function (array $matches) use ($context): string {
                $placeholder = $this->normalizeName((string) ($matches[1] ?? ''));
                $fallback = array_key_exists(2, $matches) ? (string) $matches[2] : '';
                $value = null;

                if ($placeholder !== '' && isset($this->providers[$placeholder])) {
                    $value = $this->providers[$placeholder]->resolve($placeholder, $context);
                }

                $value = apply_filters('wprc/core/placeholders/value', $value, $placeholder, $context);
                $replacement = $this->normalizeValue($value);

                if ($replacement === '') {
                    $replacement = $fallback;
                }

                return esc_html($replacement);
            },
            $content
        );

        return is_string($rendered) ? $rendered : $content;
    }

    private function normalizeName(string $placeholder): string
    {
        return strtolower(trim($placeholder));
    }

    private function normalizeValue(mixed $value): string
    {
        if ($value === null || $value === false) {
            return '';
        }
        if ($value === true) {
            return '1';
        }
        if (!is_scalar($value)) {
            return '';
        }

        return trim((string) $value);
    }
}
