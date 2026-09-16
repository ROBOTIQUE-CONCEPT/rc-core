<?php

declare(strict_types=1);

namespace WPRC\Core\UI;

use InvalidArgumentException;
use WP_User;

defined('ABSPATH') || exit;

/** Runtime registry for Admin, Internal and External module interfaces. */
final class UiRegistry
{
    /** @var array<string,array<string,Page>> */
    private array $pages = [];

    /**
     * Register a module page. Routes are always namespaced by module:
     * module=assets, path="" => /assets/
     * module=assets, path="history" => /assets/history/
     *
     * @param callable(PageContext):mixed $renderer
     * @param string[] $styles
     * @param string[] $scripts
     * @param ?string $parentPath Navigation parent path within the same module; null for a root item.
     */
    public function register(
        string $module,
        string $surface,
        string $path,
        string $label,
        string $capability,
        callable $renderer,
        int $priority = 10,
        bool $navigation = true,
        array $styles = [],
        array $scripts = [],
        ?string $parentPath = null
    ): void {
        $module = sanitize_key($module);
        $surface = sanitize_key($surface);
        $path = $this->normalizePath($path);
        $label = trim($label);
        $capability = sanitize_key($capability);

        if ($module === '' || !Surface::isValid($surface) || $label === '' || $capability === '') {
            throw new InvalidArgumentException('Invalid RC UI page definition.');
        }

        $route = trim($module . '/' . $path, '/');
        $navigationParent = null;
        if ($parentPath !== null) {
            $parentPath = $this->normalizePath($parentPath);
            $navigationParent = trim($module . '/' . $parentPath, '/');
            if ($navigationParent === $route) {
                throw new InvalidArgumentException('A RC UI page cannot be its own navigation parent.');
            }
        }

        if (isset($this->pages[$surface][$route])) {
            throw new InvalidArgumentException(sprintf('RC UI route "%s" is already registered for surface "%s".', $route, $surface));
        }

        $this->pages[$surface][$route] = new Page(
            $module,
            $surface,
            $route,
            $label,
            $capability,
            $renderer,
            $priority,
            $navigation,
            array_values(array_filter(array_map('sanitize_key', $styles))),
            array_values(array_filter(array_map('sanitize_key', $scripts))),
            $navigationParent
        );
    }

    /** @return Page[] */
    public function forSurface(string $surface, ?WP_User $user = null, bool $navigationOnly = false): array
    {
        $surface = sanitize_key($surface);
        $user ??= wp_get_current_user();
        $pages = [];

        foreach ($this->pages[$surface] ?? [] as $page) {
            if ($navigationOnly && !$page->navigation) {
                continue;
            }
            if ($user->ID <= 0 || !user_can($user, $page->capability)) {
                continue;
            }
            $pages[] = $page;
        }

        usort($pages, static fn (Page $a, Page $b): int => [$a->priority, $a->label, $a->route] <=> [$b->priority, $b->label, $b->route]);

        return $pages;
    }

    /**
     * Resolve a path to its registered page for a surface.
     *
     * @param bool $checkCapability When true (default, preserves existing
     *        behavior — e.g. `AdminSurface`), a route the user cannot access
     *        is treated as unmatched. When false, the route is matched
     *        regardless of capability, so the caller (e.g. Portal's router)
     *        can distinguish "no such page" (404) from "page exists but the
     *        user lacks `$page->capability`" (403) itself.
     */
    public function match(string $surface, string $path, ?WP_User $user = null, bool $checkCapability = true): ?PageMatch
    {
        $surface = sanitize_key($surface);
        // Only registered route segments are normalized. The unmatched suffix
        // may contain case-sensitive business identifiers (UIDs, references).
        $path = trim(preg_replace('/[\x00-\x1F\x7F]/u', '', $path) ?? '', '/');
        $pathLower = strtolower($path);
        $user ??= wp_get_current_user();
        $matches = [];

        foreach ($this->pages[$surface] ?? [] as $route => $page) {
            if ($checkCapability && ($user->ID <= 0 || !user_can($user, $page->capability))) {
                continue;
            }
            if ($pathLower === $route) {
                $matches[strlen($route)] = new PageMatch($page, '');
                continue;
            }
            if (str_starts_with($pathLower . '/', $route . '/')) {
                $remainder = ltrim(substr($path, strlen($route)), '/');
                $matches[strlen($route)] = new PageMatch($page, $remainder);
            }
        }

        if ($matches === []) {
            return null;
        }
        krsort($matches, SORT_NUMERIC);

        return reset($matches) ?: null;
    }

    /** @return array<string,array<string,Page>> */
    public function all(): array
    {
        return $this->pages;
    }

    private function normalizePath(string $path): string
    {
        $path = trim($path, "/ \t\n\r\0\x0B");
        if ($path === '') {
            return '';
        }

        $segments = array_values(array_filter(explode('/', $path), static fn (string $segment): bool => $segment !== ''));
        $segments = array_map(static fn (string $segment): string => sanitize_title($segment), $segments);

        return implode('/', array_filter($segments));
    }
}
