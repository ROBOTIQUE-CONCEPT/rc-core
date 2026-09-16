<?php

declare(strict_types=1);

namespace WPRC\Core\Translation;

defined('ABSPATH') || exit;

/**
 * Optional Polylang adapter owned by Core.
 *
 * Core itself remains usable on sites where Polylang is not active. In that
 * case source technical keys are returned unchanged and callers may decide
 * how to handle them.
 */
final class PolylangAdapter
{
    public function available(): bool
    {
        return function_exists('pll_register_string') && function_exists('pll__');
    }

    public function register(string $name, string $source, string $group, bool $multiline): void
    {
        if (!function_exists('pll_register_string')) {
            return;
        }

        pll_register_string($name, $source, $group, $multiline);
    }


    public function translate(string $source): string
    {
        if (!function_exists('pll__')) {
            return $source;
        }

        $translated = pll__($source);
        return is_string($translated) ? $translated : $source;
    }
}
