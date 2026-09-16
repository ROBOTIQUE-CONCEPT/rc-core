<?php

declare(strict_types=1);

namespace WPRC\Core\Data\ERP;

defined('ABSPATH') || exit;

final readonly class EmployeeCreateData
{
    public function __construct(
        public string $companyExternalId,
        public string $firstName,
        public string $lastName,
        public string $email,
        public string $phone = ''
    ) {
    }
}
