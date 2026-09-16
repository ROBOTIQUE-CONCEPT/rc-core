<?php

declare(strict_types=1);

namespace WPRC\Core\UI;

defined('ABSPATH') || exit;

/** Presentation surfaces exposed by RC business modules. */
final class Surface
{
    public const ADMIN = 'admin';
    public const INTERNAL = 'internal';
    public const EXTERNAL = 'external';

    /** @return string[] */
    public static function all(): array
    {
        return [self::ADMIN, self::INTERNAL, self::EXTERNAL];
    }

    public static function isValid(string $surface): bool
    {
        return in_array($surface, self::all(), true);
    }
}
