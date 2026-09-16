<?php

declare(strict_types=1);

namespace WPRC\Core\Security\Capabilities;

use WPRC\Core\Auth\AccessPolicy;
use WPRC\Core\Settings\Settings;

defined('ABSPATH') || exit;

/** Network-level RC role policy applied to the application site. */
final class RolePolicy
{
    private const SETTINGS_KEY = 'role_policy';

    public function __construct(
        private readonly Settings $settings,
        private readonly CapabilityRegistry $capabilities
    ) {
    }

    /** @return array<string,string> */
    public function roleLabels(): array
    {
        return [
            'administrator' => __('Administrateur du site', 'rc-core'),
            'rc_manager' => __('Responsable RC', 'rc-core'),
            'rc_sales' => __('Commercial', 'rc-core'),
            'rc_support' => __('Support', 'rc-core'),
            'rc_technician' => __('Technicien', 'rc-core'),
            'rc_logistics' => __('Logistique', 'rc-core'),
            'rc_customer' => __('Client', 'rc-core'),
            'rc_partner' => __('Partenaire', 'rc-core'),
        ];
    }

    /** @return string[] */
    public function assignableRoles(): array
    {
        return array_keys($this->roleLabels());
    }

    /** @return array<string,array<string,bool>> */
    public function matrix(): array
    {
        $stored = $this->settings->get(self::SETTINGS_KEY, []);
        $stored = is_array($stored) ? $stored : [];
        $caps = $this->capabilities->all();
        $matrix = [];

        foreach ($this->roleLabels() as $role => $_label) {
            $matrix[$role] = [];
            foreach ($caps as $capability) {
                $default = $this->defaultGrant($role, $capability);
                $matrix[$role][$capability] = isset($stored[$role]) && is_array($stored[$role]) && array_key_exists($capability, $stored[$role])
                    ? (bool) $stored[$role][$capability]
                    : $default;
            }
        }

        return $matrix;
    }

    /** @param array<string,array<string,bool>> $matrix */
    public function replace(array $matrix): bool
    {
        $known = array_fill_keys($this->capabilities->all(), true);
        $normalized = [];
        foreach ($this->roleLabels() as $role => $_label) {
            $normalized[$role] = [];
            $values = isset($matrix[$role]) && is_array($matrix[$role]) ? $matrix[$role] : [];
            foreach ($known as $capability => $_) {
                $normalized[$role][$capability] = !empty($values[$capability]);
            }
        }

        return $this->settings->set(self::SETTINGS_KEY, $normalized);
    }

    private function defaultGrant(string $role, string $capability): bool
    {
        return match ($capability) {
            AccessPolicy::CAP_BACKEND => $role === 'administrator',
            AccessPolicy::CAP_PORTAL => in_array($role, ['rc_manager', 'rc_sales', 'rc_support', 'rc_technician', 'rc_logistics', 'rc_customer', 'rc_partner'], true),
            AccessPolicy::CAP_INTERNAL => in_array($role, ['rc_manager', 'rc_sales', 'rc_support', 'rc_technician', 'rc_logistics'], true),
            AccessPolicy::CAP_EXTERNAL => in_array($role, ['rc_customer', 'rc_partner'], true),
            AccessPolicy::CAP_CUSTOMER => $role === 'rc_customer',
            AccessPolicy::CAP_PARTNER => $role === 'rc_partner',
            default => in_array($role, ['administrator', 'rc_manager'], true),
        };
    }

    public function registerRoles(RoleRegistry $registry): void
    {
        $matrix = $this->matrix();
        foreach ($this->roleLabels() as $role => $label) {
            $base = ['read' => true];
            if (in_array($role, ['administrator', 'rc_manager', 'rc_sales', 'rc_support', 'rc_technician', 'rc_logistics'], true)) {
                $base['upload_files'] = true;
            }
            $registry->register($role, $label, array_merge($base, $matrix[$role] ?? []), true);
        }
    }
}
