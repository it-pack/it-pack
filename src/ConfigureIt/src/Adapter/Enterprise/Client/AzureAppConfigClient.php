<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Adapter\Enterprise\Client;

use ItPack\ConfigureIt\Adapter\Enterprise\Contract\HttpClient;
use ItPack\ConfigureIt\Adapter\Enterprise\Contract\RemoteKeyValueClient;
use ItPack\ConfigureIt\Adapter\Enterprise\Http\HttpResponse;
use ItPack\ConfigureIt\Adapter\Enterprise\TreeBuilder;

/**
 * Azure App Configuration client.
 *
 * Translates an `azure-appconfig://<store>/<prefix>` resource into a `GET
 * /kv?key=<prefix>*` request against `<store>.azconfig.io` and nests the items
 * by their colon-delimited keys (e.g. `app:database:host`) into a single
 * configuration tree. Authentication (HMAC) is supplied via the injected
 * {@see HttpClient} headers. Implements the {@see RemoteKeyValueClient} port
 * (ROADMAP Phase 8).
 */
final readonly class AzureAppConfigClient implements RemoteKeyValueClient
{
    /**
     * @param array<string, string> $headers extra headers (e.g. HMAC auth).
     */
    public function __construct(
        private HttpClient $http,
        private array      $headers = [],
    ) {
    }

    public function fetch(string $uri): string
    {
        $parts = parse_url($uri);
        $store = $parts['host'] ?? '';
        $prefix = ltrim($parts['path'] ?? '', '/');

        $endpoint = sprintf(
            'https://%s.azconfig.io/kv?key=%s*&api-version=1.0',
            $store,
            urlencode($prefix),
        );

        $response = $this->http->request('GET', $endpoint, $this->headers);
        $tree = $this->toTree($response);

        return json_encode($tree, JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    private function toTree(HttpResponse $response): array
    {
        $data = $this->decode($response);
        $items = is_array($data['items'] ?? null) ? $data['items'] : [];

        $tree = [];

        foreach ($items as $item) {
            $item = is_array($item) ? $item : [];
            $key = TreeBuilder::asString($item['key'] ?? null);
            $segments = array_values(array_filter(
                explode(':', $key),
                static fn (string $segment): bool => $segment !== '',
            ));

            if ($segments === []) {
                continue;
            }

            TreeBuilder::setPath($tree, $segments, $item['value'] ?? null);
        }

        return $tree;
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(HttpResponse $response): array
    {
        $decoded = $response->json();

        return is_array($decoded) ? $decoded : [];
    }
}
