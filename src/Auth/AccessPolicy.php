<?php

declare(strict_types=1);

namespace WPRC\Core\Auth;

use WP_User;
use WPRC\Core\UI\Surface;

defined('ABSPATH') || exit;

/**
 * Platform access policy. Capabilities are declared by Core so business modules
 * can reason about surfaces without knowing role names.
 */
final class AccessPolicy
{
    public const CAP_BACKEND = 'rc_backend_access';
    public const CAP_PORTAL = 'rc_portal_access';
    public const CAP_INTERNAL = 'rc_internal_access';
    public const CAP_EXTERNAL = 'rc_external_access';
    public const CAP_CUSTOMER = 'rc_customer_access';
    public const CAP_PARTNER = 'rc_partner_access';

    /** @return string[] */
    public static function capabilities(): array
    {
        return [
            self::CAP_BACKEND,
            self::CAP_PORTAL,
            self::CAP_INTERNAL,
            self::CAP_EXTERNAL,
            self::CAP_CUSTOMER,
            self::CAP_PARTNER,
        ];
    }

    public function canAccessBackend(?WP_User $user = null): bool
    {
        $user ??= wp_get_current_user();
        if ($user->ID <= 0) {
            return false;
        }

        return is_super_admin($user->ID) || user_can($user, self::CAP_BACKEND);
    }

    public function canAccessPortal(?WP_User $user = null): bool
    {
        $user ??= wp_get_current_user();
        if ($user->ID <= 0 || $this->canAccessBackend($user)) {
            return false;
        }

        return user_can($user, self::CAP_PORTAL)
            && (
                user_can($user, self::CAP_INTERNAL)
                || user_can($user, self::CAP_EXTERNAL)
                || user_can($user, self::CAP_CUSTOMER)
                || user_can($user, self::CAP_PARTNER)
            );
    }

    public function surfaceForUser(?WP_User $user = null): ?string
    {
        $user ??= wp_get_current_user();
        if ($this->canAccessBackend($user)) {
            return Surface::ADMIN;
        }
        if (!$this->canAccessPortal($user)) {
            return null;
        }
        if (user_can($user, self::CAP_INTERNAL)) {
            return Surface::INTERNAL;
        }
        if (user_can($user, self::CAP_EXTERNAL) || user_can($user, self::CAP_CUSTOMER) || user_can($user, self::CAP_PARTNER)) {
            return Surface::EXTERNAL;
        }

        return null;
    }
}
