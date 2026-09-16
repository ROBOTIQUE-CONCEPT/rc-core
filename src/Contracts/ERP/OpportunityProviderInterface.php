<?php

declare(strict_types=1);

namespace WPRC\Core\Contracts\ERP;

use WPRC\Core\Data\ERP\OpportunityCreateData;
use WPRC\Core\Data\ERP\OpportunityData;

defined('ABSPATH') || exit;

interface OpportunityProviderInterface
{
    public function find(string $externalId): ?OpportunityData;

    /**
     * Resolve several opportunities in one call.
     *
     * @param string[] $externalIds
     * @return array<string, OpportunityData> Indexed by externalId; ids that
     *         do not resolve are simply absent from the result (no exception).
     */
    public function findMany(array $externalIds): array;

    /** @return OpportunityData[] */
    public function search(string $term = '', ?string $companyExternalId = null, bool $openOnly = true, int $limit = 50): array;

    public function create(OpportunityCreateData $data): OpportunityData;
}
