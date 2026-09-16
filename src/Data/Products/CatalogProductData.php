<?php

declare(strict_types=1);

namespace WPRC\Core\Data\Products;

defined('ABSPATH') || exit;

/** Stable cross-module DTO for an ERP product enriched by RC Products. */
final readonly class CatalogProductData
{
    public function __construct(
        public string $provider,
        public string $externalId,
        public string $productCode,
        public string $name,
        public string $designation,
        public string $kind,
        public ?string $manufacturerUid,
        public string $unit,
        public ?float $price,
        public ?float $stock,
        public bool $disabled
    ) {
    }

    public function label(): string
    {
        $reference = $this->productCode !== '' ? $this->productCode : $this->externalId;
        $designation = $this->designation !== '' ? $this->designation : $this->name;
        return trim($reference . ' — ' . $designation, " —");
    }
}
