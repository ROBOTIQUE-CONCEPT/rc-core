<?php

declare(strict_types=1);

namespace WPRC\Core\Security\Turnstile;

defined('ABSPATH') || exit;

/**
 * Shared Cloudflare Turnstile widget renderer.
 */
final class TurnstileRenderer
{
    private const SCRIPT_HANDLE = 'wprc-turnstile';

    private bool $scriptFilterAdded = false;

    public function __construct(private readonly TurnstileSettings $settings)
    {
    }

    /** @param array<string,string> $attributes */
    public function render(string $context, array $attributes = []): string
    {
        if (!$this->settings->shouldProtect($context)) {
            return '';
        }

        $this->enqueueScript();

        $classes = trim(
            'cf-turnstile wprc-turnstile wprc-turnstile--' . sanitize_html_class($context)
            . ' ' . ($attributes['class'] ?? '')
        );

        $htmlAttributes = [
            'class' => $classes,
            'data-sitekey' => $this->settings->siteKey(),
            'data-theme' => $this->settings->theme(),
            'data-size' => $this->settings->size(),
        ];

        foreach ($attributes as $name => $value) {
            $name = sanitize_key($name);
            if ($name === '' || $name === 'class') {
                continue;
            }

            $htmlAttributes[$name] = (string) $value;
        }

        $parts = [];
        foreach ($htmlAttributes as $name => $value) {
            $parts[] = $name . '="' . esc_attr($value) . '"';
        }

        return '<div ' . implode(' ', $parts) . '></div>';
    }

    private function enqueueScript(): void
    {
        if (!wp_script_is(self::SCRIPT_HANDLE, 'registered')) {
            wp_register_script(
                self::SCRIPT_HANDLE,
                'https://challenges.cloudflare.com/turnstile/v0/api.js',
                [],
                null,
                true
            );
        }

        wp_enqueue_script(self::SCRIPT_HANDLE);

        if (!$this->scriptFilterAdded) {
            add_filter('script_loader_tag', [$this, 'addScriptAttributes'], 10, 3);
            $this->scriptFilterAdded = true;
        }
    }

    public function addScriptAttributes(string $tag, string $handle, string $src): string
    {
        if ($handle !== self::SCRIPT_HANDLE) {
            return $tag;
        }

        return '<script src="' . esc_url($src) . '" async defer></script>';
    }
}
