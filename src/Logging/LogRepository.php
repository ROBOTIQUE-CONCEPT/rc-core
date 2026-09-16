<?php

declare(strict_types=1);

namespace WPRC\Core\Logging;

use WPRC\Core\Database\TableNames;

defined('ABSPATH') || exit;

final class LogRepository
{
    public function __construct(private readonly TableNames $tables)
    {
    }

    /** @param array<string,mixed> $row */
    public function insert(array $row): bool
    {
        global $wpdb;

        return $wpdb->insert(
            $this->tables->logs(),
            $row,
            ['%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s']
        ) !== false;
    }

    /**
     * @param array<string,mixed> $args
     * @return array{items:array<int,array<string,mixed>>,total:int}
     */
    public function query(array $args = []): array
    {
        global $wpdb;

        $page = max(1, absint($args['page'] ?? 1));
        $perPage = max(10, min(1000, absint($args['per_page'] ?? 50)));
        $offset = ($page - 1) * $perPage;
        $where = ['1=1'];
        $params = [];

        if (!empty($args['level']) && in_array($args['level'], ['info', 'warning', 'error'], true)) {
            $where[] = 'level = %s';
            $params[] = $args['level'];
        }

        if (!empty($args['channel'])) {
            $where[] = 'channel = %s';
            $params[] = sanitize_key((string) $args['channel']);
        }

        if (!empty($args['site_id'])) {
            $where[] = 'site_id = %d';
            $params[] = absint($args['site_id']);
        }

        if (!empty($args['ip_address'])) {
            $ipAddress = trim((string) $args['ip_address']);
            if (filter_var($ipAddress, FILTER_VALIDATE_IP) !== false) {
                $where[] = 'ip_address = %s';
                $params[] = $ipAddress;
            }
        }

        if (!empty($args['search'])) {
            $like = '%' . $wpdb->esc_like((string) $args['search']) . '%';
            $where[] = '(message LIKE %s OR event LIKE %s OR request_id LIKE %s OR ip_address LIKE %s OR context_payload LIKE %s)';
            array_push($params, $like, $like, $like, $like, $like);
        }

        $whereSql = implode(' AND ', $where);
        $table = $this->tables->logs();
        $countSql = "SELECT COUNT(*) FROM {$table} WHERE {$whereSql}";
        $total = (int) ($params === []
            ? $wpdb->get_var($countSql)
            : $wpdb->get_var($wpdb->prepare($countSql, $params)));

        $sql = "SELECT * FROM {$table} WHERE {$whereSql} ORDER BY id DESC LIMIT %d OFFSET %d";
        $queryParams = array_merge($params, [$perPage, $offset]);
        $items = $wpdb->get_results($wpdb->prepare($sql, $queryParams), ARRAY_A);

        return [
            'items' => is_array($items) ? $items : [],
            'total' => $total,
        ];
    }

    /** @return array<int,string> */
    public function channels(): array
    {
        global $wpdb;
        $table = $this->tables->logs();
        $values = $wpdb->get_col("SELECT DISTINCT channel FROM {$table} ORDER BY channel ASC LIMIT 200");
        return array_values(array_filter(array_map('strval', is_array($values) ? $values : [])));
    }

    public function pruneOlderThan(int $days): int
    {
        global $wpdb;
        $table = $this->tables->logs();
        $days = max(1, $days);
        return (int) $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table} WHERE created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY)",
            $days
        ));
    }

    public function enforceMaxRows(int $maxRows): int
    {
        global $wpdb;
        $table = $this->tables->logs();
        $maxRows = max(1000, $maxRows);
        $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        $excess = $count - $maxRows;
        if ($excess <= 0) {
            return 0;
        }

        return (int) $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table} ORDER BY id ASC LIMIT %d",
            $excess
        ));
    }

    public function purgeAll(): int
    {
        global $wpdb;
        return (int) $wpdb->query('DELETE FROM ' . $this->tables->logs());
    }
}
