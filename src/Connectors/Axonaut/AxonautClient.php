<?php



declare(strict_types=1);



namespace WPRC\Core\Connectors\Axonaut;





use WP_Error;

use WPRC\Core\Contracts\CacheInterface;
use WPRC\Core\Contracts\LoggerInterface;
use WPRC\Core\ERP\Cache\CacheLock;
use WPRC\Core\ERP\Cache\ErpCacheKeyFactory;
use WPRC\Core\ERP\Cache\RequestCache;
use WPRC\Core\ERP\ErpTelemetry;



defined('ABSPATH') || exit;





/**

 * Generic Axonaut API connector.

 *

 * This connector intentionally stays transport-oriented and generic.

 * Business-specific projections, formatting and select datasets must live

 * in the caller domain (e.g. CustomFields, services, repositories, etc.).

 */

final class AxonautClient

{

    private string $apiKey;

    private string $apiBaseUrl;



    /**

     * Cache group for wp_cache_* (Redis if object-cache enabled).

     *

     * @var string

     */

    private string $cacheGroup = 'wprc_axonaut';



    /**

     * Default cache TTL in seconds.

     *

     * @var int

     */

    private int $defaultTtl = 60;



    /**

     * Hard safety limit when iterating paginated collections.

     *

     * @var int

     */

    private int $maxPages = 1000;



    private ?LoggerInterface $logger;

    public function __construct(
        string $apiKey = '',
        ?string $apiBaseUrl = null,
        ?LoggerInterface $logger = null,
        private readonly ?CacheInterface $cache = null,
        private readonly ?RequestCache $requestCache = null,
        private readonly ?ErpCacheKeyFactory $cacheKeys = null,
        private readonly ?CacheLock $cacheLock = null,
        private readonly ?ErpTelemetry $telemetry = null
    ) {
        $this->apiBaseUrl = rtrim($apiBaseUrl ?: 'https://axonaut.com/api/v2', '/');
        $this->apiKey = trim($apiKey);
        $this->logger = $logger;
    }



    /**

     * Perform a generic GET request.

     *

     * Behavior:

     * - 200: returns decoded payload as-is.

     * - 403: attempts Axonaut paginated collection mode using header "page".

     * - other statuses / invalid JSON / transport failures: returns null.

     *

     * @param string $endpoint Endpoint starting with "/" or without leading slash.

     * @param array<string, mixed> $params Query parameters.

     * @param int|null $ttl Cache TTL in seconds. Set 0 to disable cache.

     * @return mixed|null

     */

    public function get(string $endpoint = '/', array $params = [], ?int $ttl = null)
    {
        $endpoint = '/' . ltrim($endpoint, '/');
        $ttl = $this->resolveCacheTtl($ttl, $endpoint, $params);
        $url = $this->buildUrl($endpoint, $params);
        $cacheKey = $this->buildCacheKey('GET', $url, $this->apiKey);
        $requestKey = 'axonaut:http:' . $cacheKey;

        $this->telemetry?->logicalRead();

        if ($ttl > 0 && $this->requestCache?->has($requestKey)) {
            $this->telemetry?->requestCacheHit();
            return $this->requestCache->get($requestKey);
        }

        if ($ttl > 0) {
            $marker = new \stdClass();
            $cached = $this->cache !== null
                ? $this->cache->get($cacheKey, $this->cacheGroup, $marker)
                : wp_cache_get($cacheKey, $this->cacheGroup);

            $found = $this->cache !== null ? $cached !== $marker : $cached !== false;
            if ($found) {
                $this->telemetry?->persistentCacheHit();
                $this->requestCache?->set($requestKey, $cached);
                return $cached;
            }
        }

        $lockKey = 'get:' . $cacheKey;
        $lockToken = $ttl > 0 ? $this->cacheLock?->acquire($lockKey, 10) : null;

        // If another worker is already filling the same cache entry, give it a
        // short opportunity to complete before performing a duplicate GET.
        if ($ttl > 0 && $this->cacheLock !== null && $lockToken === null) {
            for ($attempt = 0; $attempt < 8; $attempt++) {
                usleep(25000);
                $marker = new \stdClass();
                $cached = $this->cache !== null
                    ? $this->cache->get($cacheKey, $this->cacheGroup, $marker)
                    : wp_cache_get($cacheKey, $this->cacheGroup);
                $found = $this->cache !== null ? $cached !== $marker : $cached !== false;
                if ($found) {
                    $this->telemetry?->persistentCacheHit();
                    $this->requestCache?->set($requestKey, $cached);
                    return $cached;
                }
            }
        }

        try {
            $response = $this->request('GET', $endpoint, ['params' => $params]);

            if (200 === $response['status']) {
                if ($ttl > 0) {
                    $this->storeCached($cacheKey, $requestKey, $response['decoded'], $ttl);
                }
                return $response['decoded'];
            }

            if (403 !== $response['status']) {
                if ($response['status'] !== 0) {
                    $this->logger?->error('Axonaut unexpected status code.', 'axonaut', ['endpoint' => $endpoint, 'status' => $response['status']]);
                }
                return null;
            }

            $pagesHint = $this->extractPagesHint($response['decoded']);
            $resultsPerPageHint = $this->extractResultsPerPageHint($response['decoded']);
            $paginated = $this->collectPaginated($endpoint, $params, $pagesHint, $resultsPerPageHint);
            if (null === $paginated) {
                return null;
            }

            if ($ttl > 0) {
                $this->storeCached($cacheKey, $requestKey, $paginated, $ttl);
            }
            return $paginated;
        } finally {
            if ($lockToken !== null && $this->cacheLock !== null) {
                $this->cacheLock->release($lockKey, $lockToken);
            }
        }
    }

    /**

     * Execute a raw HTTP request and return a normalized response envelope.

     *

     * @param string $method HTTP method.

     * @param string $endpoint Endpoint starting with "/" or without leading slash.

     * @param array<string, mixed> $args Supported keys: params, headers, timeout, body.

     * @return array{

     *   status:int,

     *   headers:array<string, mixed>,

     *   body:string,

     *   decoded:mixed,

     *   error:?WP_Error

     * }

     */

    public function request(string $method, string $endpoint, array $args = []): array

    {

        $endpoint = '/' . ltrim($endpoint, '/');

        $params   = isset($args['params']) && is_array($args['params']) ? $args['params'] : [];

        $headers  = isset($args['headers']) && is_array($args['headers']) ? $args['headers'] : [];

        $timeout  = isset($args['timeout']) ? (int) $args['timeout'] : 8;

        $body     = $args['body'] ?? null;



        $url = $this->buildUrl($endpoint, $params);



        $requestArgs = [

            'method'      => strtoupper($method),

            'timeout'     => $timeout,

            'redirection' => 0,

            'httpversion' => '1.1',

            'headers'     => $this->buildHeaders($headers),

            'compress'    => true,

            'sslverify'   => true,

        ];



        if (is_array($body)) {

            $encodedBody = wp_json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            if (is_string($encodedBody)) {

                $requestArgs['body'] = $encodedBody;

                $requestArgs['headers']['Content-Type'] = 'application/json';

            }

        } elseif (is_string($body) && $body !== '') {

            $requestArgs['body'] = $body;

        }



        $startedAt = microtime(true);
        $response = wp_remote_request($url, $requestArgs);
        $durationMs = (microtime(true) - $startedAt) * 1000;

        if ($response instanceof WP_Error) {
            $this->telemetry?->remoteCall($method, $endpoint, $durationMs, 0);

            $this->logger?->error('Axonaut transport error.', 'axonaut', ['endpoint' => $endpoint, 'message' => $response->get_error_message()]);

            return [

                'status'  => 0,

                'headers' => [],

                'body'    => '',

                'decoded' => null,

                'error'   => $response,

            ];

        }



        $body    = (string) wp_remote_retrieve_body($response);

        $decoded = null;



        if ('' !== $body) {

            $decoded = json_decode($body, true);

            if (JSON_ERROR_NONE !== json_last_error()) {

                $decoded = null;

            }

        }



        $status = (int) wp_remote_retrieve_response_code($response);
        $this->telemetry?->remoteCall($method, $endpoint, $durationMs, $status);

        return [
            'status'  => $status,
            'headers' => wp_remote_retrieve_headers($response)->getAll(),
            'body'    => $body,
            'decoded' => $decoded,
            'error'   => null,
        ];

    }



    /**

     * Purge a specific cache key for an endpoint and params.

     *

     * @param string $endpoint

     * @param array<string, mixed> $params

     * @return void

     */

    public function purge(string $endpoint, array $params = []): void

    {

        $endpoint = '/' . ltrim($endpoint, '/');

        $url      = $this->buildUrl($endpoint, $params);

        $cacheKey = $this->buildCacheKey('GET', $url, $this->apiKey);



        if ($this->cache !== null) {
            $this->cache->delete($cacheKey, $this->cacheGroup);
        } else {
            wp_cache_delete($cacheKey, $this->cacheGroup);
        }
        $this->requestCache?->delete('axonaut:http:' . $cacheKey);

    }



    /**

     * Purge all object-cache entries for the Axonaut group when backend supports it.

     *

     * @return void

     */

    public function flushGroup(): void

    {

        if ($this->cache !== null) {
            $this->cache->flushGroup($this->cacheGroup);
        } elseif (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group($this->cacheGroup);
        }
        $this->requestCache?->clearPrefix('axonaut:http:');

    }



    /**

     * Collect a paginated Axonaut collection using the "page" header.

     *

     * Returns an aggregated flat array, which matches the shape expected from

     * collection endpoints.

     *

     * Stop conditions are intentionally defensive because some Axonaut endpoints

     * may keep returning a non-empty payload even when the requested page is out

     * of bounds or may repeat the last page.

     *

     * @param string $endpoint

     * @param array<string, mixed> $params

     * @param int|null $pagesHint Optional page count hint extracted from the initial 403 response.

     * @param int|null $resultsPerPageHint Optional page size hint extracted from the initial 403 response.

     * @return array<int, mixed>|null

     */

    private function collectPaginated(

        string $endpoint,

        array $params = [],

        ?int $pagesHint = null,

        ?int $resultsPerPageHint = null

    ): ?array {

        $results        = [];

        $seenItems      = [];

        $seenPageHashes = [];

        $page           = 1;

        $pageCeil       = $pagesHint && $pagesHint > 0 ? min($pagesHint, $this->maxPages) : $this->maxPages;



        while ($page <= $pageCeil) {

            $response = $this->request('GET', $endpoint, [

                'params'  => $params,

                'headers' => [

                    'page' => (string) $page,

                ],

            ]);



            if (200 !== $response['status']) {

                // Never return a partial collection as if it were complete.
                // Callers may use the result for destructive reconciliation.
                return null;

            }



            $chunk = $this->extractCollectionItems($response['decoded']);

            if ([] === $chunk) {

                break;

            }



            $pageHash = md5((string) wp_json_encode($chunk));

            if (isset($seenPageHashes[$pageHash])) {

                // Repeating the last page is a known defensive stop condition
                // when the provider exposes no page count. With an explicit
                // page-count hint, however, repetition before the expected end
                // means the collection cannot be trusted as complete.
                if (null !== $pagesHint && $page <= $pagesHint) {
                    return null;
                }
                break;

            }

            $seenPageHashes[$pageHash] = true;



            $newItems = $this->filterNewItems($chunk, $seenItems);

            if ([] === $newItems) {

                if (null !== $pagesHint && $page <= $pagesHint) {
                    return null;
                }
                break;

            }



            $results = array_merge($results, $newItems);



            if (null !== $resultsPerPageHint && count($chunk) < $resultsPerPageHint) {

                break;

            }



            if (null !== $pagesHint && $page >= $pagesHint) {

                break;

            }



            $page++;

        }



        return [] === $results ? null : $results;

    }



    /**

     * Extract a page count hint from a decoded payload when available.

     *

     * @param mixed $decoded

     * @return int|null

     */

    private function extractPagesHint($decoded): ?int

    {

        if (! is_array($decoded)) {

            return null;

        }



        if (isset($decoded['pages']) && is_numeric($decoded['pages'])) {

            return max(1, (int) $decoded['pages']);

        }



        if (isset($decoded['pagination']) && is_array($decoded['pagination'])) {

            if (isset($decoded['pagination']['pages']) && is_numeric($decoded['pagination']['pages'])) {

                return max(1, (int) $decoded['pagination']['pages']);

            }



            if (isset($decoded['pagination']['total_pages']) && is_numeric($decoded['pagination']['total_pages'])) {

                return max(1, (int) $decoded['pagination']['total_pages']);

            }

        }



        if (isset($decoded['meta']) && is_array($decoded['meta'])) {

            if (isset($decoded['meta']['pages']) && is_numeric($decoded['meta']['pages'])) {

                return max(1, (int) $decoded['meta']['pages']);

            }



            if (isset($decoded['meta']['total_pages']) && is_numeric($decoded['meta']['total_pages'])) {

                return max(1, (int) $decoded['meta']['total_pages']);

            }

        }



        return null;

    }



    /**

     * Extract a page size hint from a decoded payload when available.

     *

     * @param mixed $decoded

     * @return int|null

     */

    private function extractResultsPerPageHint($decoded): ?int

    {

        if (! is_array($decoded)) {

            return null;

        }



        if (isset($decoded['results_per_page']) && is_numeric($decoded['results_per_page'])) {

            return max(1, (int) $decoded['results_per_page']);

        }



        if (isset($decoded['pagination']) && is_array($decoded['pagination'])) {

            if (isset($decoded['pagination']['results_per_page']) && is_numeric($decoded['pagination']['results_per_page'])) {

                return max(1, (int) $decoded['pagination']['results_per_page']);

            }



            if (isset($decoded['pagination']['per_page']) && is_numeric($decoded['pagination']['per_page'])) {

                return max(1, (int) $decoded['pagination']['per_page']);

            }

        }



        if (isset($decoded['meta']) && is_array($decoded['meta'])) {

            if (isset($decoded['meta']['results_per_page']) && is_numeric($decoded['meta']['results_per_page'])) {

                return max(1, (int) $decoded['meta']['results_per_page']);

            }



            if (isset($decoded['meta']['per_page']) && is_numeric($decoded['meta']['per_page'])) {

                return max(1, (int) $decoded['meta']['per_page']);

            }

        }



        return null;

    }



    /**

     * Normalize a decoded collection page into a flat array of items.

     *

     * @param mixed $decoded

     * @return array<int, mixed>

     */

    private function extractCollectionItems($decoded): array

    {

        if (! is_array($decoded)) {

            return [];

        }



        if ($this->isList($decoded)) {

            return $decoded;

        }



        foreach (['data', 'results', 'items'] as $key) {

            if (isset($decoded[$key]) && is_array($decoded[$key]) && $this->isList($decoded[$key])) {

                return $decoded[$key];

            }

        }



        return [];

    }



    /**

     * Keep only items not already seen across previous pages.

     *

     * @param array<int, mixed> $chunk

     * @param array<string, true> $seenItems

     * @return array<int, mixed>

     */

    private function filterNewItems(array $chunk, array &$seenItems): array

    {

        $newItems = [];



        foreach ($chunk as $item) {

            $fingerprint = $this->buildItemFingerprint($item);



            if (isset($seenItems[$fingerprint])) {

                continue;

            }



            $seenItems[$fingerprint] = true;

            $newItems[]              = $item;

        }



        return $newItems;

    }



    /**

     * Build a stable item fingerprint for cross-page deduplication.

     *

     * @param mixed $item

     * @return string

     */

    private function buildItemFingerprint($item): string

    {

        if (is_array($item) && isset($item['id']) && (is_scalar($item['id']) || null === $item['id'])) {

            return 'id:' . (string) $item['id'];

        }



        return 'hash:' . md5((string) wp_json_encode($item));

    }



    /**

     * Build full URL with query args.

     *

     * @param string $endpoint

     * @param array<string, mixed> $params

     * @return string

     */

    private function buildUrl(string $endpoint, array $params = []): string

    {

        $url = $this->apiBaseUrl . $endpoint;



        if (! empty($params)) {

            $url = add_query_arg($this->sanitizeParams($params), $url);

        }



        return $url;

    }



    /**

     * Build HTTP headers.

     *

     * @param array<string, string> $extraHeaders

     * @return array<string, string>

     */

    private function buildHeaders(array $extraHeaders = []): array

    {

        $headers = [

            'Accept'          => 'application/json',

            'Accept-Encoding' => 'gzip',

        ];



        if ('' !== $this->apiKey) {

            $headers['userApiKey'] = $this->apiKey;

        }



        foreach ($extraHeaders as $key => $value) {

            if (! is_string($key) || '' === $key) {

                continue;

            }



            $headers[$key] = (string) $value;

        }



        return $headers;

    }



    /**

     * Keep params scalar/array, remove nulls, trim strings.

     *

     * @param array<string, mixed> $params

     * @return array<string, mixed>

     */

    private function sanitizeParams(array $params): array

    {

        $out = [];



        foreach ($params as $key => $value) {

            if (! is_string($key) || '' === $key) {

                continue;

            }



            if (null === $value) {

                continue;

            }



            if (is_string($value)) {

                $value = trim($value);

            }



            $out[$key] = $value;

        }



        return $out;

    }



    /**

     * Cache key builder.

     * Includes API key hash to avoid cross-context collisions.

     *

     * @param string $method

     * @param string $url

     * @param string $apiKey

     * @return string

     */

    private function buildCacheKey(string $method, string $url, string $apiKey): string

    {

        return $this->cacheKeys !== null
            ? $this->cacheKeys->http('axonaut', $method, $url, $apiKey)
            : 'axonaut:' . md5($method . '|' . $url . '|' . hash('sha256', $apiKey));

    }



    private function storeCached(string $cacheKey, string $requestKey, mixed $value, int $ttl): void
    {
        if ($this->cache !== null) {
            $this->cache->set($cacheKey, $value, $this->cacheGroup, $ttl);
        } else {
            wp_cache_set($cacheKey, $value, $this->cacheGroup, $ttl);
        }
        $this->requestCache?->set($requestKey, $value);
    }

    /**

     * Resolve cache TTL with filter support.

     *

     * @param int|null $ttl

     * @param string $endpoint

     * @param array<string, mixed> $params

     * @return int

     */

    private function resolveCacheTtl(?int $ttl, string $endpoint, array $params): int

    {

        $ttl = null !== $ttl ? $ttl : $this->defaultTtl;



        /**

         * Filter Axonaut cache TTL.

         *

         * @param int $ttl

         * @param string $endpoint

         * @param array<string, mixed> $params

         */

        $ttl = (int) apply_filters('wprc/axonaut/cache_ttl', $ttl, $endpoint, $params);



        return max(0, $ttl);

    }



    /**

     * Polyfill-friendly list detection.

     *

     * @param array<mixed> $value

     * @return bool

     */

    private function isList(array $value): bool

    {

        if (function_exists('array_is_list')) {

            return array_is_list($value);

        }



        return array_keys($value) === range(0, count($value) - 1);

    }

}

