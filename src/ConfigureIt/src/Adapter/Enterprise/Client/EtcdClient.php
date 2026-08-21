<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Adapter\Enterprise\Client;

use ItPack\ConfigureIt\Adapter\Enterprise\Contract\HttpClient;
use ItPack\ConfigureIt\Adapter\Enterprise\Contract\RemoteKeyValueClient;

/**
 * etcd v2 (REST API) client translating the nested node tree into a single
 * nested JSON document ready for the {@see \ItPack\ConfigureIt\Adapter\JsonLoader}.
 *
 * The {@see RemoteKeyValueClient::fetch()} URI encodes the node and the key
 * prefix, e.g. `etcd://localhost:2379/app` reads
 * `GET http://localhost:2379/v2/keys/app?recursive=true`. etcd returns a tree
 * (`{"node":{"key":"/app","nodes":[{"key":"/app/database/host","value":"db.internal"}]}}`);
 * this client re-roots the values under the prefix (mirroring the AWS/GCP/
 * Consul adapters) so the resolved tree is rooted at the prefix.
 *
 * Implementations translate vendor APIs; tests inject a
 * {@see \ItPack\ConfigureIt\Adapter\Enterprise\Http\FakeHttpClient}. The Core
 * never depends on this — it lives entirely in the adapter layer (ADR-0002).
 *
 * @see docs/ADDITIONAL_FEATURES_PLAN.md §6 "Remote & Distributed Configuration"
 */
final readonly class EtcdClient implements RemoteKeyValueClient
{
    public function __construct(
        private HttpClient $http,
        private int        $defaultPort = 2379,
    ) {
    }

    public function fetch(string $uri): string
    {
        $parts = parse_url($uri);

        if ($parts === false || !isset($parts['host'])) {
            throw new \RuntimeException(sprintf('EtcdClient: cannot parse URI "%s".', $uri));
        }

        $host = $parts['host'];
        $port = $parts['port'] ?? $this->defaultPort;
        $prefix = ltrim($parts['path'] ?? '', '/');

        $url = sprintf('http://%s:%d/v2/keys/%s?recursive=true', $host, $port, $prefix);

        $response = $this->http->request('GET', $url);

        if (!$response->isSuccess()) {
            throw new \RuntimeException(sprintf('etcd request "%s" failed: %d.', $url, $response->status));
        }

        $data = $response->json();

        if (!is_array($data) || !isset($data['node']) || !is_array($data['node'])) {
            throw new \RuntimeException(sprintf('etcd response for "%s" had no node tree.', $url));
        }

        $tree = [];
        $node = $data['node'];
        $this->walk($node, $prefix, $tree);

        return json_encode($tree, JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<mixed> $node
     * @param array<mixed> $tree
     */
    private function walk(array $node, string $prefix, array &$tree): void
    {
        if (isset($node['value'])) {
            $keyValue = $node['key'] ?? '';
            $relative = $this->relativeKey(is_string($keyValue) ? $keyValue : '', $prefix);

            if ($relative !== '') {
                $value = $node['value'];
                $this->assign($tree, $relative, is_string($value) ? $value : '');
            }

            return;
        }

        if (!isset($node['nodes']) || !is_array($node['nodes'])) {
            return;
        }

        foreach ($node['nodes'] as $child) {
            if (is_array($child)) {
                $this->walk($child, $prefix, $tree);
            }
        }
    }

    private function relativeKey(string $key, string $prefix): string
    {
        $key = ltrim($key, '/');

        if ($prefix === '') {
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
            if ($segment === '') {
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
