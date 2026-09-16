<?php

declare(strict_types=1);

namespace WPRC\Core\UI;

use WP_User;

defined('ABSPATH') || exit;

/** Runtime context passed to a registered module page renderer. */
final class PageContext
{
    public function __construct(
        public readonly WP_User $user,
        public readonly string $surface,
        public readonly string $route,
        public readonly string $remainder = ''
    ) {
    }

    public function nonce(string $action): string
    {
        return wp_create_nonce($action);
    }

    public function restNonce(): string
    {
        return wp_create_nonce('wp_rest');
    }
}
