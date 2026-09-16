<?php

declare(strict_types=1);

namespace WPRC\Core\Data\ERP;

defined('ABSPATH') || exit;

final readonly class OpportunityData
{
    public function __construct(
        public string $externalId,
        public string $name,
        public string $status = '',
        public string $companyExternalId = '',
        public string $employeeExternalId = '',
        public string $comments = ''
    ) {
    }
}
