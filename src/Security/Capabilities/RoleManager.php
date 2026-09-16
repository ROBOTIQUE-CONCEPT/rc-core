<?php

declare(strict_types=1);

namespace WPRC\Core\Security\Capabilities;

use WPRC\Core\Site\SiteContext;

defined('ABSPATH') || exit;

/** Applies declarative RC roles to the WordPress application site. */
final class RoleManager
{
    public function __construct(
        private readonly RoleRegistry $roles,
        private readonly SiteContext $sites
    ) {
    }

    /** Backward-compatible current-context apply. */
    public function apply(): void
    {
        $this->applyCurrentSite();
    }

    public function applyApplicationSite(): void
    {
        $this->applyToSite($this->sites->applicationSiteId());
    }

    public function applyToSite(int $siteId): void
    {
        $this->sites->onSite($siteId, fn (): bool => $this->applyCurrentSite());
    }

    public function applyCurrentSite(): bool
    {
        foreach ($this->roles->all() as $roleId => $definition) {
            $role = get_role($roleId);
            if ($role === null) {
                add_role($roleId, $definition['label'], $definition['capabilities']);
                $role = get_role($roleId);
            }
            if ($role === null) {
                continue;
            }

            // Only mutate capabilities explicitly governed by the RC policy.
            foreach ($definition['capabilities'] as $capability => $grant) {
                if ($grant && !$role->has_cap($capability)) {
                    $role->add_cap($capability, true);
                } elseif (!$grant && $role->has_cap($capability)) {
                    $role->remove_cap($capability);
                }
            }
        }

        return true;
    }
}
