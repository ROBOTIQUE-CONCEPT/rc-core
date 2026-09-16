<?php

declare(strict_types=1);

namespace WPRC\Core\Data\ERP;

defined('ABSPATH') || exit;

final readonly class CompanyData
{
    public function __construct(
        public string $externalId,
        public string $name,
        public string $currency = '',
        public bool $isCustomer = false,
        public bool $isProspect = false,
        public string $email = '',
        public string $phone = '',
        public string $street = '',
        public string $postalCode = '',
        public string $city = '',
        public string $country = ''
    ) {
    }
}
