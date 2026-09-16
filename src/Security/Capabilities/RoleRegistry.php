<?php

declare(strict_types=1);

namespace WPRC\Core\Security\Capabilities;

use InvalidArgumentException;

defined('ABSPATH') || exit;

/**
 * Declarative role registry. Role names are not hard-coded by Core; modules or
 * the application layer may register definitions through this common API.
 */
final class RoleRegistry
{
    /** @var array<string,array{label:string,capabilities:array<string,bool>}> */
    private array $roles = [];

    /** @param array<string,bool>|string[] $capabilities */
    public function register(string $role, string $label, array $capabilities, bool $replace = false): void
    {
        $role = sanitize_key($role);
        $label = trim($label);
        if ($role === '' || $label === '') {
            throw new InvalidArgumentException('RC role ID and label cannot be empty.');
        }
        if (!$replace && isset($this->roles[$role])) {
            throw new InvalidArgumentException(sprintf('RC role "%s" is already registered.', $role));
        }

        $normalized = [];
        foreach ($capabilities as $key => $value) {
            if (is_int($key)) {
                $capability = sanitize_key((string) $value);
                $grant = true;
            } else {
                $capability = sanitize_key((string) $key);
                $grant = (bool) $value;
            }
            if ($capability !== '') {
                $normalized[$capability] = $grant;
            }
        }

        $this->roles[$role] = [
            'label' => $label,
            'capabilities' => $normalized,
        ];
    }

    /** @return array<string,array{label:string,capabilities:array<string,bool>}> */
    public function all(): array
    {
        return $this->roles;
    }
}
