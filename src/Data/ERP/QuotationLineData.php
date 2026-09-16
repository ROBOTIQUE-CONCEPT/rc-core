<?php

declare(strict_types=1);

namespace WPRC\Core\Data\ERP;

defined('ABSPATH') || exit;

final readonly class QuotationLineData
{
    public function __construct(
        public string $productExternalId,
        public float $quantity = 1.0
    ) {
    }
}
