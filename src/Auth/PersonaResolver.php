<?php

declare(strict_types=1);

namespace WPRC\Core\Auth;

use WP_User;

defined('ABSPATH') || exit;

/** Resolves application persona from capabilities in deterministic priority order. */
final class PersonaResolver
{
    public function __construct(private readonly AccessPolicy $access)
    {
    }

    public function resolve(?WP_User $user = null): string
    {
        $user ??= wp_get_current_user();
        if ($user->ID <= 0) {
            return Persona::NONE;
        }
        if ($this->access->canAccessBackend($user)) {
            return Persona::ADMIN;
        }
        if (user_can($user, AccessPolicy::CAP_INTERNAL)) {
            return Persona::INTERNAL;
        }
        if (user_can($user, AccessPolicy::CAP_PARTNER)) {
            return Persona::PARTNER;
        }
        if (user_can($user, AccessPolicy::CAP_CUSTOMER)) {
            return Persona::CUSTOMER;
        }
        if (user_can($user, AccessPolicy::CAP_EXTERNAL)) {
            return Persona::EXTERNAL;
        }

        return Persona::NONE;
    }
}
