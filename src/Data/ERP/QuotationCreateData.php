<?php

declare(strict_types=1);

namespace WPRC\Core\Data\ERP;

defined('ABSPATH') || exit;

final readonly class QuotationCreateData
{
    /** @param QuotationLineData[] $lines */
    public function __construct(
        public string $companyExternalId,
        public string $opportunityExternalId,
        public array $lines,
        public string $templateExternalId = ''
    ) {
    }
}
