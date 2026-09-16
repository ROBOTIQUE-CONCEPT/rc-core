<?php

declare(strict_types=1);

namespace WPRC\Core\Connectors\Axonaut\Providers;

use WPRC\Core\Contracts\ERP\OpportunityProviderInterface;
use WPRC\Core\Data\ERP\OpportunityCreateData;
use WPRC\Core\Data\ERP\OpportunityData;
use WPRC\Core\ERP\ProviderException;

defined('ABSPATH') || exit;

final class OpportunityProvider extends AbstractProvider implements OpportunityProviderInterface
{
    public function find(string $externalId): ?OpportunityData
    {
        $externalId = trim($externalId);
        if ($externalId === '') {
            return null;
        }
        $payload = $this->client->get('/opportunities/' . rawurlencode($externalId), [], 300);
        return is_array($payload) ? $this->map($payload) : null;
    }

    public function findMany(array $externalIds): array
    {
        /** @var array<string, OpportunityData> */
        return $this->findManyByFind($externalIds, [$this, 'find']);
    }

    public function search(string $term = '', ?string $companyExternalId = null, bool $openOnly = true, int $limit = 50): array
    {
        $params = [];
        if ($openOnly) {
            $params['status'] = 'ongoing';
        }
        $items = $this->extractList($this->client->get('/opportunities', $params, 60));
        $needle = $this->normalizeSearch($term);
        $result = [];
        foreach ($items as $item) {
            $mapped = $this->map($item);
            if ($mapped === null) {
                continue;
            }
            if ($openOnly && !$this->isOpen($item, $mapped->status)) {
                continue;
            }
            if ($companyExternalId !== null && trim($companyExternalId) !== '' && $mapped->companyExternalId !== trim($companyExternalId)) {
                continue;
            }
            $haystack = $this->normalizeSearch($mapped->name . ' ' . $mapped->externalId . ' ' . $mapped->companyExternalId);
            if ($needle !== '' && !str_contains($haystack, $needle)) {
                continue;
            }
            $result[] = $mapped;
            if (count($result) >= max(1, min(200, $limit))) {
                break;
            }
        }
        usort($result, static fn (OpportunityData $a, OpportunityData $b): int => strcasecmp($a->name, $b->name));
        return $result;
    }

    public function create(OpportunityCreateData $data): OpportunityData
    {
        $payload = [
            'name' => trim($data->name),
            'company_id' => ctype_digit($data->companyExternalId) ? (int) $data->companyExternalId : $data->companyExternalId,
            'comments' => $data->comments,
            'business_manager_email' => $data->ownerEmail,
            'pipe_name' => $data->pipelineName,
            'pipe_step_name' => $data->pipelineStepName,
        ];
        if ($data->employeeExternalId !== '') {
            $payload['employee_id'] = ctype_digit($data->employeeExternalId) ? (int) $data->employeeExternalId : $data->employeeExternalId;
        }
        $decoded = $this->assertCreated($this->client->request('POST', '/opportunities', ['body' => $payload, 'timeout' => 15]), 'opportunity');
        $this->client->flushGroup();
        $mapped = $this->map($decoded);
        if ($mapped === null) {
            throw new ProviderException('Axonaut returned an invalid opportunity payload.');
        }
        return $mapped;
    }

    /** @param array<string,mixed> $payload */
    private function map(array $payload): ?OpportunityData
    {
        $id = $this->externalId($payload);
        if ($id === '') {
            return null;
        }
        return new OpportunityData(
            externalId: $id,
            name: trim((string) ($payload['name'] ?? $payload['title'] ?? $payload['label'] ?? ('#' . $id))),
            status: trim((string) ($payload['status'] ?? $payload['state'] ?? $payload['step'] ?? '')),
            companyExternalId: $this->nestedId($payload, 'company_id', 'company'),
            employeeExternalId: $this->nestedId($payload, 'employee_id', 'employee'),
            comments: trim((string) ($payload['comments'] ?? $payload['comment'] ?? ''))
        );
    }

    /** @param array<string,mixed> $payload */
    private function isOpen(array $payload, string $status): bool
    {
        $normalized = remove_accents(strtolower($status));
        foreach (['won', 'lost', 'closed', 'done', 'gagne', 'perdu', 'ferme', 'termine'] as $closed) {
            if ($normalized !== '' && str_contains($normalized, $closed)) {
                return false;
            }
        }
        if (array_key_exists('is_closed', $payload)) {
            return !filter_var($payload['is_closed'], FILTER_VALIDATE_BOOL);
        }
        if (array_key_exists('closed', $payload)) {
            return !filter_var($payload['closed'], FILTER_VALIDATE_BOOL);
        }
        return true;
    }
}
