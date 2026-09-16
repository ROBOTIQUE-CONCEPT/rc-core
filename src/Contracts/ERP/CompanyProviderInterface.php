<?php

declare(strict_types=1);

namespace WPRC\Core\Contracts\ERP;

use WPRC\Core\Data\ERP\CompanyCreateData;
use WPRC\Core\Data\ERP\CompanyData;

defined('ABSPATH') || exit;

interface CompanyProviderInterface
{
    public function find(string $externalId): ?CompanyData;

    /**
     * Resolve several companies in one call.
     *
     * @param string[] $externalIds
     * @return array<string, CompanyData> Indexed by externalId; ids that do
     *         not resolve are simply absent from the result (no exception).
     */
    public function findMany(array $externalIds): array;

    /** @return CompanyData[] */
    public function search(string $term = '', int $limit = 50): array;

    public function create(CompanyCreateData $data): CompanyData;
}
