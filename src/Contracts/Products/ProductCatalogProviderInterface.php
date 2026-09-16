<?php

declare(strict_types=1);

namespace WPRC\Core\Contracts\Products;

use WPRC\Core\Data\Products\CatalogProductData;

defined('ABSPATH') || exit;

/** Read-only cross-module access to the RC-enriched ERP product catalog. */
interface ProductCatalogProviderInterface
{
    public function find(string $provider, string $externalId): ?CatalogProductData;

    /** @return CatalogProductData[] */
    public function search(string $term = '', ?string $kind = null, int $limit = 50): array;
}
