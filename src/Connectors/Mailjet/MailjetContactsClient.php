<?php
declare(strict_types=1);

namespace WPRC\Core\Connectors\Mailjet;

use WPRC\Core\Contracts\LoggerInterface;
use WPRC\Core\Connectors\ConnectorCredentials;

defined('ABSPATH') || exit;

/**
 * Mailjet Email API v3 client dedicated to contact management.
 */
final class MailjetContactsClient
{
    private const BASE_URL = 'https://api.mailjet.com/v3/REST';
    private const CACHE_GROUP = 'wprc_mailjet';

    public function __construct(
        private readonly ConnectorCredentials $credentials,
        private readonly LoggerInterface $logger
    ) {
    }

    public function isConfigured(): bool
    {
        $credentials = $this->credentials->mailjet();
        return $credentials['api_key'] !== '' && $credentials['api_secret'] !== '';
    }

    /**
     * @return array{
     *   success:bool,
     *   found:bool,
     *   contact:array<string,mixed>,
     *   lists:array<int,array<string,mixed>>,
     *   metadata:array<int,array<string,mixed>>,
     *   properties:array<string,mixed>,
     *   error:string
     * }
     */
    public function dashboard(string $email): array
    {
        $email = sanitize_email($email);
        if ($email === '' || !is_email($email)) {
            return $this->dashboardFailure('invalid_email');
        }

        if (!$this->isConfigured()) {
            return $this->dashboardFailure('missing_mailjet_credentials');
        }

        $cacheKey = 'dashboard_' . sha1(strtolower($email));
        $cached = wp_cache_get($cacheKey, self::CACHE_GROUP);
        if (is_array($cached)) {
            return $cached;
        }

        $contactResult = $this->getContactByEmail($email);
        if (!$contactResult['success']) {
            return $this->dashboardFailure($contactResult['error']);
        }

        $listsResult = $this->getLists();
        if (!$listsResult['success']) {
            return $this->dashboardFailure($listsResult['error']);
        }

        $metadataResult = $this->getMetadata();
        if (!$metadataResult['success']) {
            return $this->dashboardFailure($metadataResult['error']);
        }

        $subscriptions = [];
        if ($contactResult['found']) {
            $contactId = absint($contactResult['contact']['ID'] ?? 0);
            if ($contactId > 0) {
                $subscriptionsResult = $this->getContactLists($contactId);
                if (!$subscriptionsResult['success']) {
                    return $this->dashboardFailure($subscriptionsResult['error']);
                }
                $subscriptions = $subscriptionsResult['data'];
            }
        }

        $subscriptionMap = [];
        foreach ($subscriptions as $subscription) {
            if (!is_array($subscription)) {
                continue;
            }
            $listId = absint($subscription['ListID'] ?? $subscription['ID'] ?? 0);
            if ($listId > 0) {
                $subscriptionMap[$listId] = $subscription;
            }
        }

        $lists = [];
        foreach ($listsResult['data'] as $list) {
            if (!is_array($list) || !empty($list['IsDeleted'])) {
                continue;
            }

            $listId = absint($list['ID'] ?? 0);
            if ($listId <= 0) {
                continue;
            }

            $subscription = $subscriptionMap[$listId] ?? null;
            $state = 'absent';
            if (is_array($subscription)) {
                $state = !empty($subscription['IsUnsubscribed']) ? 'unsubscribed' : 'subscribed';
            }

            $list['ContactState'] = $state;
            $list['Subscription'] = $subscription;
            $lists[] = $list;
        }

        $result = [
            'success' => true,
            'found' => $contactResult['found'],
            'contact' => $contactResult['contact'],
            'lists' => $lists,
            'metadata' => $metadataResult['data'],
            'properties' => $contactResult['properties'],
            'error' => '',
        ];

        wp_cache_set($cacheKey, $result, self::CACHE_GROUP, 60);
        return $result;
    }

    /** @return array{success:bool,status:int,error:string,data:array<int,mixed>} */
    public function createContact(string $email, string $name, bool $excluded = true): array
    {
        $email = sanitize_email($email);
        if ($email === '' || !is_email($email)) {
            return $this->failure('invalid_email');
        }

        $result = $this->request('POST', '/contact', [], [
            'Email' => $email,
            'Name' => sanitize_text_field($name),
            'IsExcludedFromCampaigns' => $excluded,
        ]);

        if ($result['success']) {
            $this->invalidateContact($email);
        }

        return $result;
    }

    /** @return array{success:bool,status:int,error:string,data:array<int,mixed>} */
    public function setCampaignExclusion(string $email, bool $excluded): array
    {
        $email = sanitize_email($email);
        if ($email === '' || !is_email($email)) {
            return $this->failure('invalid_email');
        }

        $result = $this->request('PUT', '/contact/' . rawurlencode($email), [], [
            'IsExcludedFromCampaigns' => $excluded,
        ]);

        if ($result['success']) {
            $this->invalidateContact($email);
        }

        return $result;
    }

    /** @return array{success:bool,status:int,error:string,data:array<int,mixed>} */
    public function manageList(string $email, string $name, int $listId, string $action): array
    {
        $email = sanitize_email($email);
        if ($email === '' || !is_email($email) || $listId <= 0) {
            return $this->failure('invalid_mailjet_list_request');
        }

        $allowed = ['addforce', 'addnoforce', 'remove', 'unsub'];
        if (!in_array($action, $allowed, true)) {
            return $this->failure('invalid_mailjet_list_action');
        }

        $result = $this->request('POST', '/contactslist/' . $listId . '/managecontact', [], [
            'Email' => $email,
            'Name' => sanitize_text_field($name),
            'Action' => $action,
        ]);

        if ($result['success']) {
            $this->invalidateContact($email);
            wp_cache_delete('lists', self::CACHE_GROUP);
        }

        return $result;
    }

    /**
     * @param array<string,mixed> $properties
     * @return array{success:bool,status:int,error:string,data:array<int,mixed>}
     */
    public function updateProperties(string $email, array $properties): array
    {
        $email = sanitize_email($email);
        if ($email === '' || !is_email($email)) {
            return $this->failure('invalid_email');
        }

        $contactResult = $this->getContactByEmail($email);
        if (!$contactResult['success']) {
            return $this->failure($contactResult['error']);
        }

        if (!$contactResult['found']) {
            return $this->failure('mailjet_contact_not_found', 404);
        }

        $contactId = absint($contactResult['contact']['ID'] ?? 0);
        if ($contactId <= 0) {
            return $this->failure('invalid_mailjet_contact_id');
        }

        $data = [];
        foreach ($properties as $name => $value) {
            $name = trim(sanitize_text_field((string) $name));
            if ($name === '') {
                continue;
            }
            $data[] = ['Name' => $name, 'Value' => $value];
        }

        if ($data === []) {
            return $this->failure('empty_mailjet_properties');
        }

        // The Mailjet API reference defines this endpoint with a numeric Contact ID.
        // Resolve the contact by email first and always use the canonical ID for writes.
        $result = $this->request('PUT', '/contactdata/' . $contactId, [], ['Data' => $data]);
        if ($result['success']) {
            $this->invalidateContact($email);
        }

        return $result;
    }

    /**
     * @return array{success:bool,found:bool,contact:array<string,mixed>,properties:array<string,mixed>,error:string}
     */
    private function getContactByEmail(string $email): array
    {
        // Mailjet explicitly supports the email address on the Contact resource.
        // Resolve the global contact first, then use its numeric ID for ContactData.
        $contactResult = $this->request('GET', '/contact/' . rawurlencode($email));
        if (!$contactResult['success']) {
            if ($contactResult['status'] === 404) {
                return ['success' => true, 'found' => false, 'contact' => [], 'properties' => [], 'error' => ''];
            }

            return ['success' => false, 'found' => false, 'contact' => [], 'properties' => [], 'error' => $contactResult['error']];
        }

        $contact = is_array($contactResult['data'][0] ?? null) ? $contactResult['data'][0] : [];
        $contactId = absint($contact['ID'] ?? 0);
        if ($contact === [] || $contactId <= 0) {
            return ['success' => true, 'found' => false, 'contact' => [], 'properties' => [], 'error' => ''];
        }

        $contactData = $this->request('GET', '/contactdata/' . $contactId);
        if (!$contactData['success'] && $contactData['status'] !== 404) {
            return ['success' => false, 'found' => false, 'contact' => [], 'properties' => [], 'error' => $contactData['error']];
        }

        $contactDataRow = $contactData['success'] && is_array($contactData['data'][0] ?? null)
            ? $contactData['data'][0]
            : [];
        $properties = [];
        $rawProperties = $contactDataRow['Data'] ?? [];
        if (is_array($rawProperties)) {
            foreach ($rawProperties as $property) {
                if (!is_array($property)) {
                    continue;
                }
                $name = isset($property['Name']) ? trim((string) $property['Name']) : '';
                if ($name !== '') {
                    $properties[$name] = $property['Value'] ?? null;
                }
            }
        }

        return ['success' => true, 'found' => true, 'contact' => $contact, 'properties' => $properties, 'error' => ''];
    }

    /** @return array{success:bool,status:int,error:string,data:array<int,mixed>} */
    private function getLists(): array
    {
        $cached = wp_cache_get('lists', self::CACHE_GROUP);
        if (is_array($cached)) {
            return ['success' => true, 'status' => 200, 'error' => '', 'data' => $cached];
        }

        $result = $this->request('GET', '/contactslist', ['Limit' => 1000]);
        if ($result['success']) {
            wp_cache_set('lists', $result['data'], self::CACHE_GROUP, 300);
        }
        return $result;
    }

    /** @return array{success:bool,status:int,error:string,data:array<int,mixed>} */
    private function getMetadata(): array
    {
        $cached = wp_cache_get('metadata', self::CACHE_GROUP);
        if (is_array($cached)) {
            return ['success' => true, 'status' => 200, 'error' => '', 'data' => $cached];
        }

        $result = $this->request('GET', '/contactmetadata', ['Limit' => 1000]);
        if ($result['success']) {
            wp_cache_set('metadata', $result['data'], self::CACHE_GROUP, 300);
        }
        return $result;
    }

    /** @return array{success:bool,status:int,error:string,data:array<int,mixed>} */
    private function getContactLists(int $contactId): array
    {
        return $this->request('GET', '/contact/' . $contactId . '/getcontactslists', ['Limit' => 1000]);
    }

    /**
     * @param array<string,scalar> $query
     * @param array<string,mixed>|null $body
     * @return array{success:bool,status:int,error:string,data:array<int,mixed>}
     */
    private function request(string $method, string $path, array $query = [], ?array $body = null): array
    {
        $credentials = $this->credentials->mailjet();
        if ($credentials['api_key'] === '' || $credentials['api_secret'] === '') {
            return $this->failure('missing_mailjet_credentials');
        }

        $url = self::BASE_URL . '/' . ltrim($path, '/');
        if ($query !== []) {
            $url = add_query_arg($query, $url);
        }

        $args = [
            'method' => strtoupper($method),
            'timeout' => 10,
            'redirection' => 0,
            'sslverify' => true,
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode($credentials['api_key'] . ':' . $credentials['api_secret']),
            ],
        ];

        if ($body !== null) {
            $encoded = wp_json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (!is_string($encoded)) {
                return $this->failure('invalid_mailjet_payload');
            }
            $args['headers']['Content-Type'] = 'application/json';
            $args['body'] = $encoded;
        }

        $response = wp_remote_request($url, $args);
        if (is_wp_error($response)) {
            $this->logger->error('Mailjet contact API transport error.', 'mailjet', [
                'method' => strtoupper($method),
                'path' => $path,
                'error' => $response->get_error_message(),
            ]);
            return $this->failure($response->get_error_message());
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $raw = (string) wp_remote_retrieve_body($response);
        $decoded = $raw !== '' ? json_decode($raw, true) : [];
        $decoded = is_array($decoded) ? $decoded : [];
        $data = isset($decoded['Data']) && is_array($decoded['Data']) ? array_values($decoded['Data']) : [];

        if ($status >= 200 && $status < 300) {
            return ['success' => true, 'status' => $status, 'error' => '', 'data' => $data];
        }

        $error = $this->extractError($decoded, $raw, $status);
        if ($status !== 404) {
            $this->logger->error('Mailjet contact API request failed.', 'mailjet', [
                'method' => strtoupper($method),
                'path' => $path,
                'status' => $status,
                'error' => $error,
            ]);
        }

        return ['success' => false, 'status' => $status, 'error' => $error, 'data' => $data];
    }

    private function invalidateContact(string $email): void
    {
        wp_cache_delete('dashboard_' . sha1(strtolower($email)), self::CACHE_GROUP);
    }

    /** @return array{success:bool,status:int,error:string,data:array<int,mixed>} */
    private function failure(string $error, int $status = 0): array
    {
        return ['success' => false, 'status' => $status, 'error' => $error, 'data' => []];
    }

    /**
     * @return array{success:bool,found:bool,contact:array<string,mixed>,lists:array<int,array<string,mixed>>,metadata:array<int,array<string,mixed>>,properties:array<string,mixed>,error:string}
     */
    private function dashboardFailure(string $error): array
    {
        return [
            'success' => false,
            'found' => false,
            'contact' => [],
            'lists' => [],
            'metadata' => [],
            'properties' => [],
            'error' => $error,
        ];
    }

    /** @param array<string,mixed> $decoded */
    private function extractError(array $decoded, string $raw, int $status): string
    {
        foreach (['ErrorMessage', 'ErrorInfo', 'ErrorIdentifier'] as $key) {
            if (!empty($decoded[$key]) && is_scalar($decoded[$key])) {
                return sanitize_text_field((string) $decoded[$key]);
            }
        }

        if ($raw !== '') {
            return sanitize_textarea_field(substr($raw, 0, 1000));
        }

        return 'mailjet_http_' . $status;
    }
}
