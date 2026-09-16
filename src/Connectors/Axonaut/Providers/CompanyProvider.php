<?php

declare(strict_types=1);

namespace WPRC\Core\Connectors\Axonaut\Providers;

use WPRC\Core\Contracts\ERP\CompanyProviderInterface;
use WPRC\Core\Data\ERP\CompanyCreateData;
use WPRC\Core\Data\ERP\CompanyData;

defined('ABSPATH') || exit;

final class CompanyProvider extends AbstractProvider implements CompanyProviderInterface
{
    public function find(string $externalId): ?CompanyData
    {
        $externalId = trim($externalId);
        if ($externalId === '') {
            return null;
        }
        $payload = $this->client->get('/companies/' . rawurlencode($externalId), [], 300);
        return is_array($payload) ? $this->map($payload) : null;
    }

    public function findMany(array $externalIds): array
    {
        /** @var array<string, CompanyData> */
        return $this->findManyByFind($externalIds, [$this, 'find']);
    }

    public function search(string $term = '', int $limit = 50): array
    {
        $items = $this->extractList($this->client->get('/companies', [
            'search' => trim($term),
            'type' => 'all',
            'sort' => 'name',
        ], 60));
        $result = [];
        foreach ($items as $item) {
            $mapped = $this->map($item);
            if ($mapped !== null) {
                $result[] = $mapped;
            }
            if (count($result) >= max(1, min(200, $limit))) {
                break;
            }
        }
        usort($result, static fn (CompanyData $a, CompanyData $b): int => strcasecmp($a->name, $b->name));
        return $result;
    }

    public function create(CompanyCreateData $data): CompanyData
    {
        $payload = [
            'name' => trim($data->name),
            'currency' => $data->currency !== '' ? $data->currency : 'EUR',
            'is_customer' => $data->isCustomer,
            'is_prospect' => $data->isProspect,
        ];
        if ($data->comments !== '') {
            $payload['comments'] = $data->comments;
        }
        if ($data->ownerEmail !== '') {
            $payload['business_manager'] = $data->ownerEmail;
        }
        $decoded = $this->assertCreated($this->client->request('POST', '/companies', ['body' => $payload, 'timeout' => 15]), 'company');
        $this->client->flushGroup();
        $mapped = $this->map($decoded);
        if ($mapped === null) {
            throw new \WPRC\Core\ERP\ProviderException('Axonaut returned an invalid company payload.');
        }
        return $mapped;
    }

    /** @param array<string,mixed> $payload */
    private function map(array $payload): ?CompanyData
    {
        $id = $this->externalId($payload);
        if ($id === '') {
            return null;
        }
        $name = trim((string) ($payload['name'] ?? $payload['company_name'] ?? $payload['label'] ?? ''));
        return new CompanyData(
            externalId: $id,
            name: $name !== '' ? $name : '#' . $id,
            currency: trim((string) ($payload['currency'] ?? '')),
            isCustomer: !empty($payload['is_customer']),
            isProspect: !empty($payload['is_prospect']),
            email: trim((string) ($payload['email'] ?? '')),
            phone: trim((string) ($payload['phone'] ?? $payload['phone_number'] ?? '')),
            street: trim((string) ($payload['address_street'] ?? $payload['street_address'] ?? $payload['street'] ?? '')),
            postalCode: trim((string) ($payload['address_zip_code'] ?? $payload['zip_code'] ?? '')),
            city: trim((string) ($payload['address_city'] ?? $payload['city'] ?? '')),
            country: trim((string) ($payload['address_country'] ?? $payload['country'] ?? ''))
        );
    }
}
