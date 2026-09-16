<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

if (!function_exists('rc_core')) {
    function rc_core(): \WPRC\Core\Plugin
    {
        return \WPRC\Core\Plugin::instance();
    }
}

if (!function_exists('rc_uid')) {
    function rc_uid(int $length = 8): string
    {
        return rc_core()->uid()->generate($length);
    }
}

if (!function_exists('rc_placeholders')) {
    /**
     * Render registered RC placeholders in arbitrary content.
     */
    function rc_placeholders(string $content): string
    {
        return rc_core()->placeholders()->render($content);
    }
}

if (!function_exists('rc_register_translations')) {
    /**
     * Register a module-owned catalog of stable RCONCEPT translation keys.
     *
     * @param array<string,string|array{fallback?:string,multiline?:bool}> $definitions
     */
    function rc_register_translations(string $owner, array $definitions): void
    {
        rc_core()->translations()->registerCatalog($owner, $definitions);
    }
}

if (!function_exists('rc_translate')) {
    /**
     * Translate a stable RCONCEPT technical key and render Core placeholders.
     *
     * The key may be passed with or without the "RCONCEPT_" prefix.
     */
    function rc_translate(string $key, ?int $postId = null): string
    {
        return $postId !== null && $postId > 0
            ? rc_core()->translations()->translateForPost($key, $postId)
            : rc_core()->translations()->translate($key);
    }
}

if (!function_exists('rc_translate_e')) {
    /**
     * Echo a translated RC string. Allowed post HTML is preserved after the
     * translation and placeholder rendering pipeline has completed.
     */
    function rc_translate_e(string $key, ?int $postId = null): void
    {
        echo wp_kses_post(rc_translate($key, $postId));
    }
}

if (!function_exists('rc_register_module')) {
    /**
     * Register an RC business module with the Core lifecycle registry.
     */
    function rc_register_module(\WPRC\Core\Contracts\ModuleInterface $module, bool $replace = false): void
    {
        rc_core()->modules()->register($module, $replace);
    }
}

if (!function_exists('rc_register_capabilities')) {
    /**
     * Declare module-owned capabilities through the Core permission framework.
     *
     * @param string[] $capabilities
     */
    function rc_register_capabilities(string $module, array $capabilities): void
    {
        rc_core()->capabilities()->register($module, $capabilities);
    }
}

if (!function_exists('rc_register_ui_page')) {
    /**
     * Register an Admin/Internal/External UI contribution through RC Core.
     *
     * @param callable(\WPRC\Core\UI\PageContext):mixed $renderer
     * @param string[] $styles
     * @param string[] $scripts
     * @param ?string $parentPath Navigation parent path within the same module; null for a root item.
     */
    function rc_register_ui_page(
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
        rc_core()->ui()->register(
            $module,
            $surface,
            $path,
            $label,
            $capability,
            $renderer,
            $priority,
            $navigation,
            $styles,
            $scripts,
            $parentPath
        );
    }
}
