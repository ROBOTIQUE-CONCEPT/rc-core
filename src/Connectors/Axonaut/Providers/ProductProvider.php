<?php

declare(strict_types=1);

namespace WPRC\Core\Connectors\Axonaut\Providers;

use WPRC\Core\Contracts\ERP\ProductProviderInterface;
use WPRC\Core\Data\ERP\ProductData;

defined('ABSPATH') || exit;

final class ProductProvider extends AbstractProvider implements ProductProviderInterface
{
    private const DETAIL_TTL = 300;
    private const COLLECTION_TTL = 300;
    private const SEARCH_TTL = 120;

    public function find(string $externalId): ?ProductData
    {
        $externalId = trim($externalId);
        if ($externalId === '') {
            return null;
        }

        $payload = $this->client->get(
            '/products/' . rawurlencode($externalId),
            [],
            (int) apply_filters('wprc/erp/products/detail_cache_ttl', self::DETAIL_TTL, $externalId)
        );

        return is_array($payload) ? $this->map($payload) : null;
    }

    public function findMany(array $externalIds): array
    {
        /** @var array<string, ProductData> */
        return $this->findManyByFind($externalIds, [$this, 'find']);
    }

    public function search(string $term = '', int $limit = 50, bool $includeDisabled = false): array
    {
        $params = ['with_disabled' => $includeDisabled ? 'true' : 'false'];
        if (trim($term) !== '') {
            $params['name'] = trim($term);
        }

        $items = $this->extractList($this->client->get(
            '/products',
            $params,
            (int) apply_filters('wprc/erp/products/search_cache_ttl', self::SEARCH_TTL, $term, $includeDisabled)
        ));

        return $this->mapList($items, $limit);
    }

    public function all(bool $includeDisabled = false, int $limit = 1000): array
    {
        $params = ['with_disabled' => $includeDisabled ? 'true' : 'false'];
        $items = $this->extractList($this->client->get(
            '/products',
            $params,
            (int) apply_filters('wprc/erp/products/collection_cache_ttl', self::COLLECTION_TTL, $includeDisabled)
        ));

        return $this->mapList($items, $limit);
    }

    public function updateInternalId(string $externalId, string $internalId): bool
    {
        $externalId = trim($externalId);
        if ($externalId === '') {
            return false;
        }

        $response = $this->client->request('PUT', '/products/' . rawurlencode($externalId), [
            'body' => ['internal_id' => trim($internalId)],
        ]);

        if (! in_array($response['status'], [200, 201, 204], true)) {
            return false;
        }

        // The detail/collection/search GET caches would otherwise keep
        // serving the pre-update `internal_id` for their remaining TTL.
        $this->client->purge('/products/' . rawurlencode($externalId));

        return true;
    }

    /**
     * @param array<int,mixed> $items
     * @return ProductData[]
     */
    private function mapList(array $items, int $limit): array
    {
        $limit = max(1, min(5000, $limit));
        $result = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $mapped = $this->map($item);
            if ($mapped !== null) {
                $result[] = $mapped;
            }
            if (count($result) >= $limit) {
                break;
            }
        }
        return $result;
    }

    /** @param array<string,mixed> $payload */
    private function map(array $payload): ?ProductData
    {
        $id = $this->externalId($payload);
        if ($id === '') {
            return null;
        }

        $custom = isset($payload['custom_fields']) && is_array($payload['custom_fields'])
            ? $payload['custom_fields']
            : [];
        $productCode = trim((string) ($payload['product_code'] ?? $payload['sku'] ?? $payload['reference'] ?? ''));
        $internalId = trim((string) ($payload['internal_id'] ?? ''));

        // Keep legacy `reference` behavior for existing consumers while exposing
        // the real ERP product code explicitly through `productCode`.
        $reference = $internalId !== '' ? $internalId : $productCode;
        $supplierReference = trim((string) ($payload['supplier_sku'] ?? $payload['supplier_product_code'] ?? $productCode));
        $designation = trim((string) ($payload['designation'] ?? $custom['Désignation'] ?? ''));
        $name = trim((string) ($payload['name'] ?? $designation ?? $reference));

        return new ProductData(
            externalId: $id,
            reference: $reference,
            name: $name,
            sku: trim((string) ($payload['sku'] ?? $productCode)),
            supplierReference: $supplierReference,
            brand: trim((string) ($payload['brand'] ?? $custom['Marque'] ?? $custom['brand'] ?? '')),
            designation: $designation,
            category: trim((string) ($payload['category'] ?? $custom['Catégorie'] ?? '')),
            tariffCode: trim((string) ($payload['tariff_code'] ?? $custom['Code SH'] ?? '')),
            countryOfOrigin: trim((string) ($payload['country_of_origin'] ?? $custom["Pays d'origine"] ?? '')),
            price: $this->nullableFloat($payload['price'] ?? null),
            weight: $this->nullableFloat($payload['weight'] ?? $custom['Poids'] ?? null),
            stock: $this->nullableFloat($payload['stock'] ?? null),
            imageUrl: trim((string) ($payload['img'] ?? $payload['image'] ?? '')),
            descriptionFr: trim((string) ($payload['desc_fr'] ?? $custom['Woo|Desc_FR'] ?? '')),
            descriptionEn: trim((string) ($payload['desc_en'] ?? $custom['Woo|Desc_EN'] ?? '')),
            disabled: !empty($payload['disabled']),
            productCode: $productCode,
            internalId: $internalId,
            nativeType: trim((string) ($payload['type'] ?? '')),
            unit: trim((string) ($payload['unit'] ?? '')),
            description: trim((string) ($payload['description'] ?? '')),
            taxRate: $this->nullableFloat($payload['tax_rate'] ?? null),
            priceWithTax: $this->nullableFloat($payload['price_with_tax'] ?? null),
            jobCosting: $this->nullableFloat($payload['job_costing'] ?? null),
            location: trim((string) ($payload['location'] ?? '')),
            stockThreshold: $this->nullableFloat($payload['stock_threshold'] ?? null),
            weightedAverageCost: $this->nullableFloat($payload['weighted_average_cost'] ?? null),
            ecoParticipation: $this->nullableFloat($payload['eco_participation'] ?? null),
            taxDeee: $this->nullableFloat($payload['tax_deee'] ?? null),
            customFields: $custom
        );
    }

    private function nullableFloat(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}
