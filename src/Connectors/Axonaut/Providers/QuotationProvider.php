<?php

declare(strict_types=1);

namespace WPRC\Core\Connectors\Axonaut\Providers;

use WPRC\Core\Contracts\ERP\QuotationProviderInterface;
use WPRC\Core\Data\ERP\QuotationCreateData;
use WPRC\Core\Data\ERP\QuotationData;
use WPRC\Core\Data\ERP\QuotationTemplateData;
use WPRC\Core\ERP\ProviderException;

defined('ABSPATH') || exit;

final class QuotationProvider extends AbstractProvider implements QuotationProviderInterface
{
    public function find(string $externalId): ?QuotationData
    {
        $externalId = trim($externalId);
        if ($externalId === '') {
            return null;
        }
        $payload = $this->client->get('/quotations/' . rawurlencode($externalId), [], 300);
        return is_array($payload) ? $this->map($payload) : null;
    }

    public function findMany(array $externalIds): array
    {
        /** @var array<string, QuotationData> */
        return $this->findManyByFind($externalIds, [$this, 'find']);
    }

    public function search(string $term = '', ?string $companyExternalId = null, int $limit = 50): array
    {
        $companyExternalId = $companyExternalId !== null ? trim($companyExternalId) : '';
        $endpoint = $companyExternalId !== ''
            ? '/companies/' . rawurlencode($companyExternalId) . '/quotations'
            : '/quotations';
        $items = $this->extractList($this->client->get($endpoint, [], 60), ['data', 'results', 'items', 'quotations']);
        $needle = $this->normalizeSearch($term);
        $result = [];
        foreach ($items as $item) {
            $mapped = $this->map($item);
            if ($mapped === null) {
                continue;
            }
            if ($companyExternalId !== '' && $mapped->companyExternalId !== '' && $mapped->companyExternalId !== $companyExternalId) {
                continue;
            }
            $haystack = $this->normalizeSearch($mapped->number . ' ' . $mapped->title . ' ' . $mapped->status . ' ' . $mapped->externalId);
            if ($needle !== '' && !str_contains($haystack, $needle)) {
                continue;
            }
            $result[] = $mapped;
            if (count($result) >= max(1, min(200, $limit))) {
                break;
            }
        }
        usort($result, static fn (QuotationData $a, QuotationData $b): int => strcasecmp($a->number . $a->title, $b->number . $b->title));
        return $result;
    }

    public function create(QuotationCreateData $data): QuotationData
    {
        $products = [];
        foreach ($data->lines as $line) {
            if (!$line instanceof \WPRC\Core\Data\ERP\QuotationLineData || trim($line->productExternalId) === '') {
                continue;
            }
            $products[] = [
                'id' => ctype_digit($line->productExternalId) ? (int) $line->productExternalId : $line->productExternalId,
                'quantity' => max(0.0001, $line->quantity),
            ];
        }
        if ($products === []) {
            throw new ProviderException('Quotation requires at least one ERP product line.');
        }
        $payload = [
            'company_id' => ctype_digit($data->companyExternalId) ? (int) $data->companyExternalId : $data->companyExternalId,
            'opportunity_id' => ctype_digit($data->opportunityExternalId) ? (int) $data->opportunityExternalId : $data->opportunityExternalId,
            'products' => $products,
        ];
        if ($data->templateExternalId !== '') {
            $payload['theme_id'] = ctype_digit($data->templateExternalId) ? (int) $data->templateExternalId : $data->templateExternalId;
        }
        $decoded = $this->assertCreated($this->client->request('POST', '/quotations', ['body' => $payload, 'timeout' => 20]), 'quotation');
        $this->client->flushGroup();
        $mapped = $this->map($decoded);
        if ($mapped === null) {
            throw new ProviderException('Axonaut returned an invalid quotation payload.');
        }
        return $mapped;
    }

    public function templates(string $term = '', int $limit = 100): array
    {
        $items = $this->extractList($this->client->get('/themes', [], 3600));
        $needle = $this->normalizeSearch($term);
        $result = [];
        foreach ($items as $item) {
            $id = $this->externalId($item);
            if ($id === '') {
                continue;
            }
            $name = trim((string) ($item['name'] ?? $item['title'] ?? $item['label'] ?? $item['theme_name'] ?? ''));
            $type = trim((string) ($item['type'] ?? $item['document_type'] ?? $item['kind'] ?? ''));
            if ($name === '') {
                $name = 'Thème #' . $id;
            }
            if ($needle !== '' && !str_contains($this->normalizeSearch($name . ' ' . $type . ' ' . $id), $needle)) {
                continue;
            }
            $result[] = new QuotationTemplateData($id, $name, $type);
            if (count($result) >= max(1, min(200, $limit))) {
                break;
            }
        }
        usort($result, static fn (QuotationTemplateData $a, QuotationTemplateData $b): int => strcasecmp($a->name, $b->name));
        return $result;
    }

    /** @param array<string,mixed> $payload */
    private function map(array $payload): ?QuotationData
    {
        $id = $this->externalId($payload);
        if ($id === '') {
            return null;
        }
        $total = null;
        foreach (['total', 'total_incl_tax', 'total_with_tax', 'amount'] as $key) {
            if (isset($payload[$key]) && is_numeric($payload[$key])) {
                $total = (float) $payload[$key];
                break;
            }
        }
        return new QuotationData(
            externalId: $id,
            number: trim((string) ($payload['number'] ?? $payload['quotation_number'] ?? $payload['reference'] ?? '')),
            title: trim((string) ($payload['title'] ?? $payload['name'] ?? '')),
            status: trim((string) ($payload['status'] ?? $payload['state'] ?? '')),
            companyExternalId: $this->nestedId($payload, 'company_id', 'company'),
            opportunityExternalId: $this->nestedId($payload, 'opportunity_id', 'opportunity'),
            total: $total
        );
    }
}
