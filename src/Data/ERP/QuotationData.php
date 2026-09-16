<?php

declare(strict_types=1);

namespace WPRC\Core\Data\ERP;

defined('ABSPATH') || exit;

final readonly class QuotationData
{
    public function __construct(
        public string $externalId,
        public string $number = '',
        public string $title = '',
        public string $status = '',
        public string $companyExternalId = '',
        public string $opportunityExternalId = '',
        public ?float $total = null
    ) {
    }
}
