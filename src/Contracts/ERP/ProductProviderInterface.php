<?php

declare(strict_types=1);

namespace WPRC\Core\Contracts\ERP;

use WPRC\Core\Data\ERP\ProductData;

defined('ABSPATH') || exit;

interface ProductProviderInterface
{
    public function find(string $externalId): ?ProductData;

    /**
     * Resolve several products in one call.
     *
     * @param string[] $externalIds
     * @return array<string, ProductData> Indexed by externalId; ids that do
     *         not resolve are simply absent from the result (no exception).
     */
    public function findMany(array $externalIds): array;

    /** @return ProductData[] */
    public function search(string $term = '', int $limit = 50, bool $includeDisabled = false): array;

    /**
     * Return the ERP product catalog through the Core cache layer.
     *
     * @return ProductData[]
     */
    public function all(bool $includeDisabled = false, int $limit = 1000): array;

    /**
     * Write back the RC-side identifier of a reconciled product onto its
     * Axonaut record's `internal_id` field, establishing a strong two-way
     * link between the ERP and the RC application (Axonaut → RC via
     * `internal_id`, RC → Axonaut via the existing `_rc_product_erp_*`
     * reference). Returns false on any transport/HTTP failure rather than
     * throwing: a failed write-back must not block the caller from keeping
     * the RC-side record it already created.
     */
    public function updateInternalId(string $externalId, string $internalId): bool;
}
