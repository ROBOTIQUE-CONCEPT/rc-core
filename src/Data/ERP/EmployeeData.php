<?php

declare(strict_types=1);

namespace WPRC\Core\Data\ERP;

defined('ABSPATH') || exit;

final readonly class EmployeeData
{
    public function __construct(
        public string $externalId,
        public string $companyExternalId,
        public string $firstName,
        public string $lastName,
        public string $email = '',
        public string $phone = '',
        public string $jobTitle = ''
    ) {
    }

    public function displayName(): string
    {
        return trim($this->firstName . ' ' . $this->lastName);
    }
}
