<?php

declare(strict_types=1);

namespace WPRC\Core\Data\ERP;

defined('ABSPATH') || exit;

/**
 * Normalized ERP product DTO.
 *
 * Native ERP fields are kept distinct from module-owned enrichment. Custom
 * fields are exposed read-only to support controlled legacy migrations and
 * must not become the canonical RC data model.
 */
final readonly class ProductData
{
    /** @param array<string,mixed> $customFields */
    public function __construct(
        public string $externalId,
        public string $reference,
        public string $name,
        public string $sku = '',
        public string $supplierReference = '',
        public string $brand = '',
        public string $designation = '',
        public string $category = '',
        public string $tariffCode = '',
        public string $countryOfOrigin = '',
        public ?float $price = null,
        public ?float $weight = null,
        public ?float $stock = null,
        public string $imageUrl = '',
        public string $descriptionFr = '',
        public string $descriptionEn = '',
        public bool $disabled = false,
        public string $productCode = '',
        public string $internalId = '',
        public string $nativeType = '',
        public string $unit = '',
        public string $description = '',
        public ?float $taxRate = null,
        public ?float $priceWithTax = null,
        public ?float $jobCosting = null,
        public string $location = '',
        public ?float $stockThreshold = null,
        public ?float $weightedAverageCost = null,
        public ?float $ecoParticipation = null,
        public ?float $taxDeee = null,
        public array $customFields = []
    ) {
    }
}
