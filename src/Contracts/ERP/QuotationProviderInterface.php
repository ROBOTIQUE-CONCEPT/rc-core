<?php

declare(strict_types=1);

namespace WPRC\Core\Contracts\ERP;

use WPRC\Core\Data\ERP\QuotationCreateData;
use WPRC\Core\Data\ERP\QuotationData;
use WPRC\Core\Data\ERP\QuotationTemplateData;

defined('ABSPATH') || exit;

interface QuotationProviderInterface
{
    public function find(string $externalId): ?QuotationData;

    /**
     * Resolve several quotations in one call.
     *
     * @param string[] $externalIds
     * @return array<string, QuotationData> Indexed by externalId; ids that do
     *         not resolve are simply absent from the result (no exception).
     */
    public function findMany(array $externalIds): array;

    /** @return QuotationData[] */
    public function search(string $term = '', ?string $companyExternalId = null, int $limit = 50): array;

    public function create(QuotationCreateData $data): QuotationData;

    /** @return QuotationTemplateData[] */
    public function templates(string $term = '', int $limit = 100): array;
}
