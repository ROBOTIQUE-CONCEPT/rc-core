<?php

declare(strict_types=1);

namespace WPRC\Core\Runtime;

defined('ABSPATH') || exit;

/**
 * Generic WordPress runtime context shared by every RC module.
 */
final class RequestContext
{
    public function isAdmin(): bool
    {
        return is_admin();
    }

    public function isAjax(): bool
    {
        return wp_doing_ajax();
    }

    public function isRest(): bool
    {
        return defined('REST_REQUEST') && REST_REQUEST;
    }

    public function isCli(): bool
    {
        return defined('WP_CLI') && WP_CLI;
    }

    public function isCron(): bool
    {
        return wp_doing_cron();
    }

    public function isFrontend(): bool
    {
        return !$this->isAdmin() && !$this->isAjax() && !$this->isRest() && !$this->isCli() && !$this->isCron();
    }

    public function siteId(): int
    {
        return (int) get_current_blog_id();
    }
}
