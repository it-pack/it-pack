<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Adapter\Enterprise\Client;

use ItPack\ConfigureIt\Adapter\Enterprise\Contract\HttpClient;
use ItPack\ConfigureIt\Adapter\Enterprise\Contract\RemoteKeyValueClient;

/**
 * HashiCorp Vault client (KV v2).
 *
 * Translates a `vault://<host>/<path>` resource into a `GET /v1/<path>` request
 * against the Vault server and returns the secret `data` as a JSON document
 * ready for the {@see \ItPack\ConfigureIt\Adapter\JsonLoader}. Authentication
 * (Vault token / TLS) is supplied via the injected {@see HttpClient} headers.
 * Implements the {@see RemoteKeyValueClient} port, reusing the Phase 8 plumbing.
 *
 * @see docs/ADDITIONAL_FEATURES_PLAN.md §5 "Secrets & Redaction"
 */
final readonly class VaultClient implements RemoteKeyValueClient
{
    /**
     * @param array<string, string> $headers extra headers (e.g. `X-Vault-Token`).
     */
    public function __construct(
        private HttpClient $http,
        private array      $headers = [],
    ) {
    }

    public function fetch(string $uri): string
    {
        $parts = parse_url($uri);
        $host = $parts['host'] ?? 'localhost:8200';
        $path = ltrim($parts['path'] ?? '', '/');

        $endpoint = sprintf('https://%s/v1/%s', $host, $path);

        $response = $this->http->request('GET', $endpoint, $this->headers);
        $data = $response->json();

        $data = is_array($data) ? $data : [];

        // KV v2 wraps the secret in data.data; KV v1 returns data directly.
        $secrets = $data['data'] ?? $data;
        $secrets = is_array($secrets) && isset($secrets['data']) && is_array($secrets['data'])
            ? $secrets['data']
            : $secrets;

        return json_encode($secrets, JSON_THROW_ON_ERROR);
    }
}
