<?php

declare(strict_types=1);

namespace WPRC\Core\Contracts\ERP;

use WPRC\Core\Data\ERP\EmployeeCreateData;
use WPRC\Core\Data\ERP\EmployeeData;

defined('ABSPATH') || exit;

interface EmployeeProviderInterface
{
    public function find(string $externalId): ?EmployeeData;

    /**
     * Resolve several employees in one call.
     *
     * @param string[] $externalIds
     * @return array<string, EmployeeData> Indexed by externalId; ids that do
     *         not resolve are simply absent from the result (no exception).
     */
    public function findMany(array $externalIds): array;

    /** @return EmployeeData[] */
    public function forCompany(string $companyExternalId, string $term = '', int $limit = 50): array;

    public function create(EmployeeCreateData $data): EmployeeData;
}
