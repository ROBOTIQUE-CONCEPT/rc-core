<?php

declare(strict_types=1);

namespace WPRC\Core\Logging;

use WPRC\Core\Settings\Settings;
use WPRC\Core\Site\SiteContext;

defined('ABSPATH') || exit;

final class RetentionScheduler
{
    public const HOOK = 'rc_core_daily_maintenance';

    public function __construct(
        private readonly LogRepository $repository,
        private readonly Settings $settings,
        private readonly SiteContext $sites
    ) {
    }

    public function init(): void
    {
        add_action(self::HOOK, [$this, 'run']);

        if (!is_multisite() || is_main_site()) {
            add_action('init', [$this, 'ensureScheduled'], 5);
        }
    }

    public function ensureScheduled(): void
    {
        if (wp_next_scheduled(self::HOOK) !== false) {
            return;
        }

        wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::HOOK);
    }

    public function run(): void
    {
        foreach ($this->sites->siteIds() as $siteId) {
            $this->sites->onSite($siteId, function (): void {
                $this->repository->pruneOlderThan($this->settings->logRetentionDays());
                $this->repository->enforceMaxRows($this->settings->logMaxRows());
            });
        }
    }

    public function scheduleOnPrimarySite(): void
    {
        $this->sites->onSite($this->primarySiteId(), function (): void {
            $this->ensureScheduled();
        });
    }

    public function unscheduleOnPrimarySite(): void
    {
        $this->sites->onSite($this->primarySiteId(), static function (): void {
            $timestamp = wp_next_scheduled(self::HOOK);
            while ($timestamp !== false) {
                wp_unschedule_event($timestamp, self::HOOK);
                $timestamp = wp_next_scheduled(self::HOOK);
            }
        });
    }

    private function primarySiteId(): int
    {
        if (is_multisite() && function_exists('get_main_site_id')) {
            return (int) get_main_site_id();
        }

        return (int) get_current_blog_id();
    }
}
