<?php

declare(strict_types=1);

namespace WPRC\Core\Contracts\ERP;

use WPRC\Core\Data\ERP\AddressData;

defined('ABSPATH') || exit;

interface AddressProviderInterface
{
    public function find(string $companyExternalId, string $externalId): ?AddressData;

    /** @return AddressData[] */
    public function forCompany(string $companyExternalId): array;
}
