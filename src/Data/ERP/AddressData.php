<?php

declare(strict_types=1);

namespace WPRC\Core\Data\ERP;

defined('ABSPATH') || exit;

final readonly class AddressData
{
    public function __construct(
        public string $externalId,
        public string $companyExternalId,
        public string $label = '',
        public string $street = '',
        public string $postalCode = '',
        public string $city = '',
        public string $country = '',
        public string $countryCode = ''
    ) {
    }

    public function displayLabel(): string
    {
        $parts = array_values(array_filter([
            trim($this->label),
            trim($this->street),
            trim($this->postalCode . ' ' . $this->city),
            trim($this->country),
        ]));

        return $parts !== [] ? implode(' - ', $parts) : $this->externalId;
    }
}
