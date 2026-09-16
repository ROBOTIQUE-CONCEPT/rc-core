<?php

declare(strict_types=1);

namespace WPRC\Core\Database;

defined('ABSPATH') || exit;

final class TableNames
{
    public function logs(): string
    {
        return $this->prefix() . 'core_logs';
    }

    public function manufacturers(): string
    {
        return $this->prefix() . 'core_manufacturers';
    }

    private function prefix(): string
    {
        global $wpdb;
        return (string) $wpdb->prefix;
    }
}
