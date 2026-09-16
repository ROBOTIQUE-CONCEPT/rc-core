<?php

declare(strict_types=1);

namespace WPRC\Core\Data\ERP;

defined('ABSPATH') || exit;

final readonly class OpportunityCreateData
{
    public function __construct(
        public string $name,
        public string $companyExternalId,
        public string $employeeExternalId = '',
        public string $comments = '',
        public string $ownerEmail = '',
        public string $pipelineName = '',
        public string $pipelineStepName = ''
    ) {
    }
}
