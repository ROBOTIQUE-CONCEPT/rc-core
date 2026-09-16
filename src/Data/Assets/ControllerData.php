<?php

declare(strict_types=1);

namespace WPRC\Core\Data\Assets;

defined('ABSPATH') || exit;

final class ControllerData
{
    public function __construct(
        public readonly string $uid,
        public readonly ?string $manufacturerUid,
        public readonly string $name,
        public readonly ?string $reference,
        public readonly string $status
    ) {
    }
}
