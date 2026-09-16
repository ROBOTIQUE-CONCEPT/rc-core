<?php

declare(strict_types=1);

namespace WPRC\Core\Connectors\Axonaut\Providers;

use WPRC\Core\Contracts\ERP\EmployeeProviderInterface;
use WPRC\Core\Data\ERP\EmployeeCreateData;
use WPRC\Core\Data\ERP\EmployeeData;
use WPRC\Core\ERP\ProviderException;

defined('ABSPATH') || exit;

final class EmployeeProvider extends AbstractProvider implements EmployeeProviderInterface
{
    public function find(string $externalId): ?EmployeeData
    {
        $externalId = trim($externalId);
        if ($externalId === '') {
            return null;
        }
        $payload = $this->client->get('/employees/' . rawurlencode($externalId), [], 300);
        return is_array($payload) ? $this->map($payload) : null;
    }

    public function findMany(array $externalIds): array
    {
        /** @var array<string, EmployeeData> */
        return $this->findManyByFind($externalIds, [$this, 'find']);
    }

    public function forCompany(string $companyExternalId, string $term = '', int $limit = 50): array
    {
        $companyExternalId = trim($companyExternalId);
        if ($companyExternalId === '') {
            return [];
        }
        $items = $this->extractList($this->client->get('/companies/' . rawurlencode($companyExternalId) . '/employees', [], 60));
        if ($items === []) {
            $items = $this->extractList($this->client->get('/employees', [
                'search' => trim($term),
                'company_id' => $companyExternalId,
            ], 60));
        }
        $needle = $this->normalizeSearch($term);
        $result = [];
        foreach ($items as $item) {
            $mapped = $this->map($item, $companyExternalId);
            if ($mapped === null) {
                continue;
            }
            $haystack = $this->normalizeSearch($mapped->displayName() . ' ' . $mapped->email . ' ' . $mapped->phone);
            if ($needle !== '' && !str_contains($haystack, $needle)) {
                continue;
            }
            $result[] = $mapped;
            if (count($result) >= max(1, min(200, $limit))) {
                break;
            }
        }
        usort($result, static fn (EmployeeData $a, EmployeeData $b): int => strcasecmp($a->displayName(), $b->displayName()));
        return $result;
    }

    public function create(EmployeeCreateData $data): EmployeeData
    {
        $payload = [
            'company_id' => ctype_digit($data->companyExternalId) ? (int) $data->companyExternalId : $data->companyExternalId,
            'firstname' => trim($data->firstName),
            'lastname' => trim($data->lastName),
            'email' => trim($data->email),
        ];
        if ($data->phone !== '') {
            $payload['phone_number'] = $data->phone;
        }
        $decoded = $this->assertCreated($this->client->request('POST', '/employees', ['body' => $payload, 'timeout' => 15]), 'employee');
        $this->client->flushGroup();
        $mapped = $this->map($decoded, $data->companyExternalId);
        if ($mapped === null) {
            throw new ProviderException('Axonaut returned an invalid employee payload.');
        }
        return $mapped;
    }

    /** @param array<string,mixed> $payload */
    private function map(array $payload, string $fallbackCompanyId = ''): ?EmployeeData
    {
        $id = $this->externalId($payload);
        if ($id === '') {
            return null;
        }
        $companyId = $this->nestedId($payload, 'company_id', 'company');
        if ($companyId === '') {
            $companyId = $fallbackCompanyId;
        }
        return new EmployeeData(
            externalId: $id,
            companyExternalId: $companyId,
            firstName: trim((string) ($payload['firstname'] ?? $payload['first_name'] ?? '')),
            lastName: trim((string) ($payload['lastname'] ?? $payload['last_name'] ?? '')),
            email: trim((string) ($payload['email'] ?? '')),
            phone: trim((string) ($payload['phone_number'] ?? $payload['phone'] ?? '')),
            jobTitle: trim((string) ($payload['job_title'] ?? $payload['position'] ?? $payload['role'] ?? ''))
        );
    }
}
