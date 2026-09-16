<?php

declare(strict_types=1);

namespace WPRC\Core\Security\Capabilities;

use WPRC\Core\Contracts\LoggerInterface;
use WPRC\Core\Site\SiteContext;

defined('ABSPATH') || exit;

/** Network Admin matrix for RC roles/capabilities and application-site users. */
final class PermissionsPage
{
    public function __construct(
        private readonly CapabilityRegistry $capabilities,
        private readonly RolePolicy $policy,
        private readonly RoleManager $roles,
        private readonly SiteContext $sites,
        private readonly LoggerInterface $logger
    ) {
    }

    public function registerMenu(): void
    {
        if (!is_multisite()) {
            return;
        }

        add_submenu_page(
            'settings.php',
            __('Rôles & permissions RC', 'rc-core'),
            __('Rôles & permissions RC', 'rc-core'),
            'manage_network_users',
            'rc-core-permissions',
            [$this, 'render']
        );
    }

    public function handleSave(): void
    {
        if (!is_multisite() || !is_network_admin() || !current_user_can('manage_network_users')) {
            return;
        }

        $action = isset($_POST['rc_core_permissions_action'])
            ? sanitize_key((string) wp_unslash($_POST['rc_core_permissions_action']))
            : '';
        if ($action === '') {
            return;
        }

        check_admin_referer('rc_core_permissions');

        if ($action === 'save_policy') {
            $posted = isset($_POST['roles']) && is_array($_POST['roles']) ? wp_unslash($_POST['roles']) : [];
            $matrix = [];
            foreach ($this->policy->assignableRoles() as $role) {
                $values = isset($posted[$role]) && is_array($posted[$role]) ? $posted[$role] : [];
                foreach ($this->capabilities->all() as $capability) {
                    $matrix[$role][$capability] = !empty($values[$capability]);
                }
            }
            $this->policy->replace($matrix);
            $this->policy->registerRoles(rc_core()->roles());
            $this->roles->applyApplicationSite();
            $this->logger->info('RC role policy updated.', 'core', ['site_id' => $this->sites->applicationSiteId()], 'role_policy_updated');
        }

        if ($action === 'save_users') {
            $assignments = isset($_POST['user_roles']) && is_array($_POST['user_roles']) ? wp_unslash($_POST['user_roles']) : [];
            $this->saveAssignments($assignments);
            $this->logger->info('RC application user roles updated.', 'core', ['site_id' => $this->sites->applicationSiteId()], 'user_roles_updated');
        }

        wp_safe_redirect(add_query_arg(['page' => 'rc-core-permissions', 'updated' => '1'], network_admin_url('settings.php')));
        exit;
    }

    public function render(): void
    {
        if (!current_user_can('manage_network_users')) {
            wp_die(esc_html__('Accès refusé.', 'rc-core'));
        }

        $matrix = $this->policy->matrix();
        $labels = $this->policy->roleLabels();
        $grouped = $this->capabilities->byModule();
        $applicationSite = get_blog_details($this->sites->applicationSiteId());

        echo '<div class="wrap"><h1>' . esc_html__('Rôles & permissions RC', 'rc-core') . '</h1>';
        if (isset($_GET['updated'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Configuration enregistrée.', 'rc-core') . '</p></div>';
        }
        echo '<p>' . esc_html(sprintf(
            __('Les profils ci-dessous sont appliqués uniquement au site métier : %s', 'rc-core'),
            $applicationSite ? $applicationSite->siteurl : '#' . $this->sites->applicationSiteId()
        )) . '</p>';

        echo '<h2>' . esc_html__('Matrice des capabilities', 'rc-core') . '</h2>';
        echo '<form method="post">';
        wp_nonce_field('rc_core_permissions');
        echo '<input type="hidden" name="rc_core_permissions_action" value="save_policy">';
        echo '<div style="overflow:auto"><table class="widefat striped" style="min-width:1100px"><thead><tr><th>' . esc_html__('Capability', 'rc-core') . '</th>';
        foreach ($labels as $label) {
            echo '<th style="text-align:center">' . esc_html($label) . '</th>';
        }
        echo '</tr></thead><tbody>';

        foreach ($grouped as $module => $capabilities) {
            echo '<tr><th colspan="' . esc_attr((string) (count($labels) + 1)) . '"><strong>' . esc_html(strtoupper($module)) . '</strong></th></tr>';
            foreach ($capabilities as $capability) {
                echo '<tr><td><code>' . esc_html($capability) . '</code></td>';
                foreach ($labels as $role => $_label) {
                    $checked = !empty($matrix[$role][$capability]);
                    echo '<td style="text-align:center"><input type="checkbox" name="roles[' . esc_attr($role) . '][' . esc_attr($capability) . ']" value="1" ' . checked($checked, true, false) . '></td>';
                }
                echo '</tr>';
            }
        }
        echo '</tbody></table></div>';
        submit_button(__('Enregistrer les permissions', 'rc-core'));
        echo '</form>';

        echo '<hr><h2>' . esc_html__('Utilisateurs du site métier', 'rc-core') . '</h2>';
        echo '<form method="post">';
        wp_nonce_field('rc_core_permissions');
        echo '<input type="hidden" name="rc_core_permissions_action" value="save_users">';
        echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Utilisateur', 'rc-core') . '</th><th>' . esc_html__('Email', 'rc-core') . '</th><th>' . esc_html__('Rôle sur my', 'rc-core') . '</th></tr></thead><tbody>';
        foreach (get_users(['blog_id' => 0, 'number' => 500, 'orderby' => 'display_name', 'order' => 'ASC']) as $user) {
            $current = $this->roleForUser((int) $user->ID);
            echo '<tr><td>' . esc_html($user->display_name ?: $user->user_login) . '</td><td>' . esc_html($user->user_email) . '</td><td><select name="user_roles[' . esc_attr((string) $user->ID) . ']">';
            if ($current !== '' && !isset($labels[$current])) {
                echo '<option value="__keep__" selected>' . esc_html(sprintf(__('Conserver le rôle actuel : %s', 'rc-core'), $current)) . '</option>';
            }
            echo '<option value=""' . selected($current, '', false) . '>' . esc_html__('Aucun accès applicatif', 'rc-core') . '</option>';
            foreach ($labels as $role => $label) {
                echo '<option value="' . esc_attr($role) . '"' . selected($current, $role, false) . '>' . esc_html($label) . '</option>';
            }
            echo '</select></td></tr>';
        }
        echo '</tbody></table>';
        submit_button(__('Enregistrer les rôles utilisateurs', 'rc-core'));
        echo '</form></div>';
    }

    /** @param array<mixed,mixed> $assignments */
    private function saveAssignments(array $assignments): void
    {
        // Ensure custom RC roles exist on the application site even when the
        // super-admin reaches this page before visiting my after an upgrade.
        $this->roles->applyApplicationSite();

        $allowed = array_fill_keys($this->policy->assignableRoles(), true);
        $siteId = $this->sites->applicationSiteId();

        foreach ($assignments as $userId => $role) {
            $userId = absint($userId);
            if ($userId <= 0 || is_super_admin($userId)) {
                continue;
            }
            $role = sanitize_key((string) $role);
            if ($role === '__keep__') {
                continue;
            }
            if ($role !== '' && !isset($allowed[$role])) {
                continue;
            }

            if ($role === '') {
                if (is_user_member_of_blog($userId, $siteId)) {
                    remove_user_from_blog($userId, $siteId);
                }
                continue;
            }

            if (!is_user_member_of_blog($userId, $siteId)) {
                add_user_to_blog($siteId, $userId, $role);
                continue;
            }

            $this->sites->onSite($siteId, static function () use ($userId, $role): void {
                $user = new \WP_User($userId);
                $user->set_role($role);
            });
        }
    }

    private function roleForUser(int $userId): string
    {
        $siteId = $this->sites->applicationSiteId();
        if (!is_user_member_of_blog($userId, $siteId)) {
            return '';
        }

        return $this->sites->onSite($siteId, static function () use ($userId): string {
            $user = new \WP_User($userId);
            return isset($user->roles[0]) ? (string) $user->roles[0] : '';
        });
    }
}
