<?php

declare(strict_types=1);

namespace WPRC\Core\Logging;

use WPRC\Core\Contracts\LoggerInterface;
use WPRC\Core\Settings\Settings;
use WP_Error;
use WP_User;

defined('ABSPATH') || exit;

/**
 * Logs a deliberately small set of native WordPress security events.
 *
 * No request-wide, REST or generic action hooks are registered here: only
 * infrequent authentication/account/plugin events with operational value.
 */
final class WordPressSecurityEvents
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly Settings $settings,
        private readonly SecurityLogRateLimiter $rateLimiter
    ) {
    }

    public function init(): void
    {
        if (!$this->settings->loggingEnabled() || !$this->settings->wordpressSecurityLoggingEnabled()) {
            return;
        }

        add_action('wp_login_failed', [$this, 'loginFailed'], 10, 2);
        add_action('wp_login', [$this, 'loginSucceeded'], 10, 2);
        add_action('user_register', [$this, 'userRegistered'], 10, 1);
        add_action('delete_user', [$this, 'userDeleted'], 10, 3);
        add_action('set_user_role', [$this, 'userRoleChanged'], 10, 3);
        // Accept only the WP_User argument: the second hook argument is the plaintext new password.
        add_action('password_reset', [$this, 'passwordReset'], 10, 1);
        add_action('activated_plugin', [$this, 'pluginActivated'], 10, 2);
        add_action('deactivated_plugin', [$this, 'pluginDeactivated'], 10, 2);
    }

    public function loginFailed(string $username, WP_Error $error): void
    {
        if (!$this->rateLimiter->allow('wp_login_failed', null, 30)) {
            return;
        }

        $this->logger->warning(
            'WordPress login failed.',
            'wordpress-security',
            [
                'username' => sanitize_text_field($username),
                'error_codes' => array_values(array_map('sanitize_key', $error->get_error_codes())),
            ],
            'login_failed'
        );
    }

    public function loginSucceeded(string $userLogin, WP_User $user): void
    {
        $this->logger->info(
            'WordPress login succeeded.',
            'wordpress-security',
            [
                'target_user_id' => (int) $user->ID,
                'username' => sanitize_user($userLogin),
                'roles' => array_values(array_map('sanitize_key', (array) $user->roles)),
            ],
            'login_succeeded'
        );
    }

    public function userRegistered(int $userId): void
    {
        $user = get_userdata($userId);

        $this->logger->info(
            'WordPress user registered.',
            'wordpress-security',
            [
                'target_user_id' => $userId,
                'username' => $user instanceof WP_User ? $user->user_login : '',
                'roles' => $user instanceof WP_User ? array_values((array) $user->roles) : [],
            ],
            'user_registered'
        );
    }

    public function userDeleted(int $userId, ?int $reassign, WP_User $user): void
    {
        $this->logger->warning(
            'WordPress user deleted.',
            'wordpress-security',
            [
                'target_user_id' => $userId,
                'username' => $user->user_login,
                'roles' => array_values((array) $user->roles),
                'reassign_to_user_id' => $reassign,
            ],
            'user_deleted'
        );
    }

    /** @param string[] $oldRoles */
    public function userRoleChanged(int $userId, string $role, array $oldRoles): void
    {
        $this->logger->warning(
            'WordPress user role changed.',
            'wordpress-security',
            [
                'target_user_id' => $userId,
                'new_role' => sanitize_key($role),
                'old_roles' => array_values(array_map('sanitize_key', $oldRoles)),
            ],
            'user_role_changed'
        );
    }

    public function passwordReset(WP_User $user): void
    {
        $this->logger->warning(
            'WordPress password reset.',
            'wordpress-security',
            [
                'target_user_id' => (int) $user->ID,
                'username' => $user->user_login,
            ],
            'password_reset'
        );
    }

    public function pluginActivated(string $plugin, bool $networkWide): void
    {
        $this->logger->warning(
            'WordPress plugin activated.',
            'wordpress-security',
            [
                'plugin' => sanitize_text_field($plugin),
                'network_wide' => $networkWide,
            ],
            'plugin_activated'
        );
    }

    public function pluginDeactivated(string $plugin, bool $networkDeactivating): void
    {
        $this->logger->warning(
            'WordPress plugin deactivated.',
            'wordpress-security',
            [
                'plugin' => sanitize_text_field($plugin),
                'network_wide' => $networkDeactivating,
            ],
            'plugin_deactivated'
        );
    }
}
