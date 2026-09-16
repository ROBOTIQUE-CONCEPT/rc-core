<?php

declare(strict_types=1);

namespace WPRC\Core\Reference;

defined('ABSPATH') || exit;

final readonly class Manufacturer
{
    public function __construct(
        public int $id,
        public string $uid,
        public string $name,
        public string $slug,
        public string $status
    ) {}

    public function isActive(): bool { return $this->status === 'active'; }
}
