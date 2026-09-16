<?php

declare(strict_types=1);

namespace WPRC\Core\Data\Product;

defined('ABSPATH') || exit;

/**
 * Stable public product context shared through RC Core.
 *
 * Local WordPress IDs are transport metadata only. ERP identity is carried as
 * source + external ID so consumers do not need to know the owning module.
 */
final class ProductContext
{
    public function __construct(
        public readonly int $id,
        public readonly string $sku,
        public readonly string $name,
        public readonly ?string $externalId,
        public readonly ?string $imageUrl,
        public readonly ?string $url,
        public readonly ?string $externalSource = null,
        public readonly ?string $type = null
    ) {
    }

    /** @return array{id:int,sku:string,name:string,external_id:?string,image_url:?string,url:?string,external_source:?string,type:?string} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'external_id' => $this->externalId,
            'image_url' => $this->imageUrl,
            'url' => $this->url,
            'external_source' => $this->externalSource,
            'type' => $this->type,
        ];
    }
}
