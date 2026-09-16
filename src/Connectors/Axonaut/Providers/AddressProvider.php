<?php

declare(strict_types=1);

namespace WPRC\Core\Connectors\Axonaut\Providers;

use WPRC\Core\Contracts\ERP\AddressProviderInterface;
use WPRC\Core\Data\ERP\AddressData;

defined('ABSPATH') || exit;

final class AddressProvider extends AbstractProvider implements AddressProviderInterface
{
    public function find(string $companyExternalId, string $externalId): ?AddressData
    {
        $externalId = trim($externalId);
        if ($externalId === '') {
            return null;
        }
        foreach ($this->forCompany($companyExternalId) as $address) {
            if ($address->externalId === $externalId) {
                return $address;
            }
        }
        return null;
    }

    public function forCompany(string $companyExternalId): array
    {
        $companyExternalId = trim($companyExternalId);
        if ($companyExternalId === '') {
            return [];
        }
        $items = $this->extractList($this->client->get('/companies/' . rawurlencode($companyExternalId) . '/addresses', [], 60));
        $result = [];
        foreach ($items as $item) {
            $mapped = $this->map($item, $companyExternalId);
            if ($mapped !== null) {
                $result[] = $mapped;
            }
        }
        return $result;
    }

    /** @param array<string,mixed> $payload */
    private function map(array $payload, string $companyId): ?AddressData
    {
        $id = $this->externalId($payload);
        if ($id === '') {
            return null;
        }
        return new AddressData(
            externalId: $id,
            companyExternalId: $companyId,
            label: trim((string) ($payload['label'] ?? $payload['name'] ?? '')),
            street: trim((string) ($payload['street_address'] ?? $payload['address_street'] ?? $payload['street'] ?? '')),
            postalCode: trim((string) ($payload['zip_code'] ?? $payload['address_zip_code'] ?? '')),
            city: trim((string) ($payload['city'] ?? $payload['address_city'] ?? '')),
            country: trim((string) ($payload['country'] ?? $payload['address_country'] ?? '')),
            countryCode: trim((string) ($payload['country_code'] ?? $payload['address_countryCode'] ?? ''))
        );
    }
}
