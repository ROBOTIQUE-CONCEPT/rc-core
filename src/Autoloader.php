<?php

declare(strict_types=1);

namespace WPRC\Core;

defined('ABSPATH') || exit;

final class Autoloader
{
    private const PREFIX = 'WPRC\\Core\\';

    public static function register(): void
    {
        spl_autoload_register([self::class, 'autoload']);
    }

    private static function autoload(string $class): void
    {
        if (!str_starts_with($class, self::PREFIX)) {
            return;
        }

        $relative = substr($class, strlen(self::PREFIX));
        $file = RC_CORE_PATH . 'src/' . str_replace('\\', '/', $relative) . '.php';

        if (is_readable($file)) {
            require_once $file;
        }
    }
}
