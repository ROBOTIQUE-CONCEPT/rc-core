<?php

declare(strict_types=1);

namespace WPRC\Core\Auth;

defined('ABSPATH') || exit;

/** Canonical RC application personas resolved from capabilities, never roles. */
final class Persona
{
    public const ADMIN = 'admin';
    public const INTERNAL = 'internal';
    public const PARTNER = 'partner';
    public const CUSTOMER = 'customer';
    public const EXTERNAL = 'external';
    public const NONE = 'none';

    /** @return string[] */
    public static function all(): array
    {
        return [self::ADMIN, self::INTERNAL, self::PARTNER, self::CUSTOMER, self::EXTERNAL, self::NONE];
    }
}
