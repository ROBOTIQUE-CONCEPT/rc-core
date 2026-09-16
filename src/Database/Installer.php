<?php

declare(strict_types=1);

namespace WPRC\Core\Database;

use WPRC\Core\Logging\RetentionScheduler;
use WPRC\Core\Site\SiteContext;

defined('ABSPATH') || exit;

final class Installer
{
    public const DB_VERSION = '2026.09.16.1';
    private const VERSION_OPTION = 'wprc_core_db_version';

    public function __construct(
        private readonly TableNames $tables,
        private readonly RetentionScheduler $retention,
        private readonly SiteContext $sites
    ) {
    }

    public function install(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();

        dbDelta("CREATE TABLE {$this->tables->logs()} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            level varchar(10) NOT NULL,
            channel varchar(64) NOT NULL,
            event varchar(128) NULL DEFAULT NULL,
            message text NOT NULL,
            context_payload longtext NULL,
            site_id bigint(20) unsigned NOT NULL DEFAULT 0,
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            ip_address varchar(45) NULL DEFAULT NULL,
            request_id char(8) NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY level_created (level, created_at),
            KEY channel_created (channel, created_at),
            KEY site_created (site_id, created_at),
            KEY event_created (event, created_at),
            KEY ip_created (ip_address, created_at),
            KEY request_id (request_id),
            KEY created_at (created_at)
        ) ENGINE=InnoDB {$charset};");

        if (!is_multisite() || $this->sites->isApplicationSite()) {
            dbDelta("CREATE TABLE {$this->tables->manufacturers()} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                uid varchar(64) NOT NULL,
                name varchar(191) NOT NULL,
                slug varchar(191) NOT NULL,
                status varchar(20) NOT NULL DEFAULT 'active',
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY uid (uid),
                UNIQUE KEY slug (slug),
                KEY status_name (status,name)
            ) ENGINE=InnoDB {$charset};");
        }

        // The replication/identity layers have been retired. Their data is no
        // longer part of the RC architecture and is intentionally removed.
        foreach ([
            $wpdb->prefix . 'core_external_records',
            $wpdb->prefix . 'core_user_external_links',
            $wpdb->prefix . 'rc_external_records',
            $wpdb->prefix . 'rc_user_external_links',
        ] as $obsoleteTable) {
            $wpdb->query('DROP TABLE IF EXISTS `' . str_replace('`', '``', $obsoleteTable) . '`');
        }

        $this->cleanupObsoleteSettings();
        if ($this->sites->isApplicationSite()) {
            $this->migrateLegacyCustomerRole();
        }
        update_option(self::VERSION_OPTION, self::DB_VERSION, false);
        $this->retention->scheduleOnPrimarySite();
    }

    /**
     * One-time role migration: the WordPress role `customer` is renamed to
     * `rc_customer` to align with the platform's `rc_*` role naming
     * convention (see `RolePolicy::roleLabels()`). Any account already
     * holding the legacy role is reassigned first, so no one silently loses
     * access — this runs unconditionally and is safe to run again even if
     * no account ever used the legacy role.
     */
    private function migrateLegacyCustomerRole(): void
    {
        if (get_role('customer') === null) {
            return;
        }

        $legacyUsers = get_users([
            'role' => 'customer',
            'fields' => ['ID'],
        ]);

        foreach ($legacyUsers as $legacyUser) {
            $user = new \WP_User((int) $legacyUser->ID);
            $user->add_role('rc_customer');
            $user->remove_role('customer');
        }

        remove_role('customer');
    }

    /**
     * Remove settings and connector credentials belonging to retired services.
     *
     * This release intentionally drops compatibility with the former identity,
     * workspace and dormant Odoo layers.
     */
    private function cleanupObsoleteSettings(): void
    {
        $settings = is_multisite()
            ? get_site_option('rc_core_settings', [])
            : get_option('rc_core_settings', []);

        if (is_array($settings)) {
            foreach ([
                'identity_site_id',
                'workspace_site_id',
                'odoo_base_url',
                'odoo_database',
                'turnstile_settings_migration_complete',
            ] as $obsoleteKey) {
                unset($settings[$obsoleteKey]);
            }

            // The WooCommerce multi-product request flow was retired in favor
            // of mono-product RC Leads forms on product pages.
            if (isset($settings['turnstile']) && is_array($settings['turnstile'])) {
                unset($settings['turnstile']['enable_woo_cart']);
            }

            if (is_multisite()) {
                update_site_option('rc_core_settings', $settings);
            } else {
                update_option('rc_core_settings', $settings, false);
            }
        }

        // The dormant Odoo connector is deliberately removed. It will be
        // reintroduced with real providers when the migration starts.
        delete_option('connectors_erp_rc_odoo_api_key');
    }

    public function maybeUpgrade(): void
    {
        if ((string) get_option(self::VERSION_OPTION, '') !== self::DB_VERSION) {
            $this->install();
        }
    }
}
