<?php

declare(strict_types=1);

namespace WPRC\Core\Reference;

use WPRC\Core\Database\TableNames;
use WPRC\Core\Support\UidGenerator;

defined('ABSPATH') || exit;

final class ManufacturerRepository
{
    public function __construct(private readonly TableNames $tables, private readonly UidGenerator $uids) {}

    public function find(int $id): ?Manufacturer
    {
        if ($id <= 0) return null;
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->tables->manufacturers()} WHERE id=%d", $id), ARRAY_A);
        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function findByUid(string $uid): ?Manufacturer
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->tables->manufacturers()} WHERE uid=%s", trim($uid)), ARRAY_A);
        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function findBySlug(string $slug): ?Manufacturer
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->tables->manufacturers()} WHERE slug=%s", sanitize_title($slug)), ARRAY_A);
        return is_array($row) ? $this->hydrate($row) : null;
    }

    /** @return Manufacturer[] */
    public function all(bool $activeOnly = false): array
    {
        global $wpdb;
        $where = $activeOnly ? " WHERE status='active'" : '';
        $rows = $wpdb->get_results("SELECT * FROM {$this->tables->manufacturers()}{$where} ORDER BY name ASC", ARRAY_A);
        return array_map(fn(array $row): Manufacturer => $this->hydrate($row), is_array($rows) ? $rows : []);
    }

    public function save(?int $id, string $name, ?string $slug = null, string $status = 'active', ?string $uid = null): Manufacturer
    {
        global $wpdb;
        $name = sanitize_text_field($name);
        if ($name === '') throw new \RuntimeException(__('Le nom du fabricant est obligatoire.', 'rc-core'));
        $slug = sanitize_title($slug ?: $name);
        $status = in_array($status, ['active','archived'], true) ? $status : 'active';
        $existing = $id ? $this->find($id) : null;
        $uid = $existing?->uid ?? trim((string)$uid);
        if ($uid === '') $uid = 'MFR-' . $this->uids->generateUnique(fn(string $candidate): bool => $this->findByUid('MFR-' . $candidate) instanceof Manufacturer);
        $duplicate = $this->findBySlug($slug);
        if ($duplicate && (!$existing || $duplicate->id !== $existing->id)) throw new \RuntimeException(__('Ce fabricant existe déjà.', 'rc-core'));
        $now = current_time('mysql', true);
        $record = ['uid'=>$uid,'name'=>$name,'slug'=>$slug,'status'=>$status,'updated_at'=>$now];
        if ($existing) {
            $wpdb->update($this->tables->manufacturers(), $record, ['id'=>$existing->id]);
            return $this->find($existing->id) ?? $existing;
        }
        $record['created_at'] = $now;
        $wpdb->insert($this->tables->manufacturers(), $record);
        $saved = $this->find((int)$wpdb->insert_id);
        if (!$saved) throw new \RuntimeException(__('Impossible d’enregistrer le fabricant.', 'rc-core'));
        return $saved;
    }

    public function importPreservingId(int $id, string $uid, string $name, string $slug, string $status): void
    {
        if ($id <= 0 || $this->find($id)) return;
        global $wpdb;
        $now = current_time('mysql', true);
        $wpdb->replace($this->tables->manufacturers(), [
            'id'=>$id,'uid'=>$uid,'name'=>$name,'slug'=>$slug,
            'status'=>in_array($status,['active','archived','inactive'],true) ? ($status === 'active' ? 'active' : 'archived') : 'active',
            'created_at'=>$now,'updated_at'=>$now,
        ]);
    }

    private function hydrate(array $row): Manufacturer
    {
        return new Manufacturer((int)$row['id'], (string)$row['uid'], (string)$row['name'], (string)$row['slug'], (string)$row['status']);
    }
}
