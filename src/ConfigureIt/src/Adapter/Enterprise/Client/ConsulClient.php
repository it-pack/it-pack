<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Adapter\Enterprise\Client;

use ItPack\ConfigureIt\Adapter\Enterprise\Contract\HttpClient;
use ItPack\ConfigureIt\Adapter\Enterprise\Contract\RemoteKeyValueClient;
use RuntimeException;

/**
 * HashiCorp Consul KV client (REST API) translating the vendor response into a
 * single nested JSON document ready for the {@see \ItPack\ConfigureIt\Adapter\JsonLoader}.
 *
 * The {@see RemoteKeyValueClient::fetch()} URI encodes the agent and the KV
 * prefix, e.g. `consul://localhost:8500/app` reads
 * `GET http://localhost:8500/v1/kv/app?recurse`. TLS is available via
 * `consul+tls://…` (upstream `https://`); other schemes are rejected. Auth
 * tokens (ACL) ride via a decorating {@see HttpClient} that adds headers.
 *
 * Consul returns a flat base64-encoded array (`[{"Key":"app/database/host",
 * "Value":"<base64>"}]`); this client nests each entry under its key path
 * *relative to the prefix* so the resolved tree is rooted at the prefix
 * (mirroring the AWS/GCP adapters).
 *
 * Implementations translate vendor APIs; tests inject a
 * {@see \ItPack\ConfigureIt\Adapter\Enterprise\Http\FakeHttpClient}. The Core
 * never depends on this — it lives entirely in the adapter layer (ADR-0002).
 *
 * @see docs/ADDITIONAL_FEATURES_PLAN.md §6 "Remote & Distributed Configuration"
 */
final readonly class ConsulClient implements RemoteKeyValueClient
{
    private const string PLAIN_SCHEME = 'consul';
    private const string TLS_SCHEME = 'consul+tls';

    /** URI scheme → upstream URL scheme. */
    private const array UPSTREAM_SCHEMES = [
        self::PLAIN_SCHEME => 'http',
        self::TLS_SCHEME => 'https',
    ];

    public function __construct(
        private HttpClient $http,
        private int        $defaultPort = 8500,
    ) {
    }

    public function fetch(string $uri): string
    {
        $parts = parse_url($uri);

        if (false === $parts || !isset($parts['host'])) {
            throw new RuntimeException(sprintf('ConsulClient: cannot parse URI "%s".', $uri));
        }

        $scheme = self::UPSTREAM_SCHEMES[$parts['scheme'] ?? self::PLAIN_SCHEME] ?? null;

        if (null === $scheme) {
            throw new RuntimeException(sprintf(
                'ConsulClient: unsupported URI scheme "%s://" in "%s" (expected "%s://" or "%s://").',
                $parts['scheme'] ?? '',
                $uri,
                self::PLAIN_SCHEME,
                self::TLS_SCHEME,
            ));
        }

        $host = $parts['host'];
        $port = $parts['port'] ?? $this->defaultPort;
        $prefix = ltrim($parts['path'] ?? '', '/');

        $url = sprintf('%s://%s:%d/v1/kv/%s?recurse', $scheme, $host, $port, $prefix);

        $response = $this->http->request('GET', $url);

        if (!$response->isSuccess()) {
            throw new RuntimeException(sprintf('Consul KV request "%s" failed: %d.', $url, $response->status));
        }

        $entries = $response->json();

        if (!is_array($entries)) {
            throw new RuntimeException(sprintf('Consul KV response for "%s" was not a list.', $url));
        }

        $tree = [];

        foreach ($entries as $entry) {
            if (!is_array($entry) || !isset($entry['Key'])) {
                continue;
            }

            $keyValue = $entry['Key'];
            $relative = $this->relativeKey(is_string($keyValue) ? $keyValue : '', $prefix);

            if ('' === $relative) {
                continue;
            }

            $rawValue = $entry['Value'] ?? '';
            $raw = is_string($rawValue) ? $rawValue : '';
            $decoded = '' === $raw ? '' : base64_decode($raw, true);
            $value = is_string($decoded) ? $decoded : '';
            $this->assign($tree, $relative, $value);
        }

        return json_encode($tree, JSON_THROW_ON_ERROR);
    }

    private function relativeKey(string $key, string $prefix): string
    {
        if ('' === $prefix) {
            return $key;
        }

        if (str_starts_with($key, $prefix . '/')) {
            return substr($key, strlen($prefix) + 1);
        }

        if ($key === $prefix) {
            return '';
        }

        return $key;
    }

    /**
     * @param array<mixed> $tree
     */
    private function assign(array &$tree, string $relativeKey, string $value): void
    {
        $segments = explode('/', $relativeKey);
        $node = &$tree;

        foreach ($segments as $segment) {
            if ('' === $segment) {
                continue;
            }

            if (!isset($node[$segment]) || !is_array($node[$segment])) {
                $node[$segment] = [];
            }

            $node = &$node[$segment];
        }

        $node = $value;
    }
}
