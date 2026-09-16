<?php

declare(strict_types=1);

namespace WPRC\Core\Data\ERP;

defined('ABSPATH') || exit;

final readonly class CompanyCreateData
{
    public function __construct(
        public string $name,
        public string $currency = 'EUR',
        public bool $isCustomer = false,
        public bool $isProspect = true,
        public string $comments = '',
        public string $ownerEmail = ''
    ) {
    }
}
