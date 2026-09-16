<?php

declare(strict_types=1);

namespace WPRC\Core\Data\Assets;

defined('ABSPATH') || exit;

final class TechnicalModelData
{
    /** @param array<int,array<string,mixed>> $axes */
    public function __construct(
        public readonly string $uid,
        public readonly string $reference,
        public readonly ?string $manufacturerUid,
        public readonly ?string $familyUid,
        public readonly ?float $payloadKg,
        public readonly ?float $reachMm,
        public readonly ?float $massKg,
        public readonly ?float $repeatabilityMm,
        public readonly ?string $structure,
        public readonly ?int $axesCount,
        public readonly ?string $ipBase,
        public readonly ?string $ipWrist,
        public readonly string $status,
        public readonly array $axes = []
    ) {
    }
}
