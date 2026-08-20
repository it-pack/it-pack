<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Adapter\Enterprise\Client;

use ItPack\ConfigureIt\Adapter\Enterprise\Contract\HttpClient;
use ItPack\ConfigureIt\Adapter\Enterprise\Contract\RemoteKeyValueClient;
use ItPack\ConfigureIt\Adapter\Enterprise\Http\HttpResponse;
use ItPack\ConfigureIt\Adapter\Enterprise\TreeBuilder;

/**
 * GCP Secret Manager client.
 *
 * Translates a `gcp-secretmanager://<project>/<prefix>` resource into a secret
 * listing followed by per-secret `:access` calls, nesting each resolved secret
 * by its dash-delimited id (e.g. `app-database-host`) into a single
 * configuration tree. The base64 `payload.data` is decoded. Authentication (OIDC
 * token) is supplied via the injected {@see HttpClient} headers. Implements the
 * {@see RemoteKeyValueClient} port (ROADMAP Phase 8).
 */
final readonly class GcpSecretManagerClient implements RemoteKeyValueClient
{
    /**
     * @param array<string, string> $headers extra headers (e.g. bearer token).
     */
    public function __construct(
        private HttpClient $http,
        private array      $headers = [],
    ) {
    }

    public function fetch(string $uri): string
    {
        $parts = parse_url($uri);
        $project = $parts['host'] ?? '';
        $prefix = ltrim($parts['path'] ?? '', '/');

        $listUrl = sprintf(
            'https://secretmanager.googleapis.com/v1/projects/%s/secrets',
            urlencode($project),
        );

        $list = $this->decode($this->http->request('GET', $listUrl, $this->headers));
        $secrets = is_array($list['secrets'] ?? null) ? $list['secrets'] : [];

        $tree = [];

        foreach ($secrets as $secret) {
            $secret = is_array($secret) ? $secret : [];
            $name = TreeBuilder::asString($secret['name'] ?? null);
            $id = (string) preg_replace('#^projects/[^/]+/secrets/#', '', $name);

            if ($prefix !== '' && !str_starts_with($id, $prefix)) {
                continue;
            }

            $accessed = $this->decode($this->http->request('GET', $name . ':access', $this->headers));
            $payload = is_array($accessed['payload'] ?? null) ? $accessed['payload'] : [];
            $encoded = TreeBuilder::asString($payload['data'] ?? null);
            $decoded = base64_decode($encoded, true);

            $segments = array_values(array_filter(
                explode('-', $id),
                static fn (string $segment): bool => $segment !== '',
            ));

            if ($segments === []) {
                continue;
            }

            TreeBuilder::setPath($tree, $segments, $decoded === false ? $encoded : $decoded);
        }

        return json_encode($tree, JSON_THROW_ON_ERROR);
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
