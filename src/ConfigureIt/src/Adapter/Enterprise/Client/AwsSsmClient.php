<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Adapter\Enterprise\Client;

use ItPack\ConfigureIt\Adapter\Enterprise\Contract\HttpClient;
use ItPack\ConfigureIt\Adapter\Enterprise\Contract\RemoteKeyValueClient;
use ItPack\ConfigureIt\Adapter\Enterprise\Http\HttpResponse;
use ItPack\ConfigureIt\Adapter\Enterprise\TreeBuilder;

/**
 * AWS Systems Manager Parameter Store client.
 *
 * Translates a `aws-ssm://<path>` resource into a recursive
 * `GetParametersByPath` JSON-RPC call and nests the returned parameters by their
 * (slash-delimited) names into a single configuration tree. The actual request
 * signing (SigV4) and TLS are the responsibility of the injected
 * {@see HttpClient} — this adapter only composes the logical request and parses
 * the response. Implements the {@see RemoteKeyValueClient} port (ROADMAP Phase 8).
 */
final readonly class AwsSsmClient implements RemoteKeyValueClient
{
    /**
     * @param array<string, string> $headers extra headers (e.g. pre-signed auth).
     */
    public function __construct(
        private HttpClient $http,
        private string     $region,
        private array      $headers = [],
    ) {
    }

    public function fetch(string $uri): string
    {
        $path = parse_url($uri, PHP_URL_PATH) ?? '/';

        $endpoint = sprintf('https://ssm.%s.amazonaws.com/', $this->region);

        $body = json_encode([
            'Path' => $path,
            'Recursive' => true,
            'WithDecryption' => true,
            'MaxResults' => 100,
        ], JSON_THROW_ON_ERROR);

        $headers = [
            'Content-Type' => 'application/x-amz-json-1.1',
            'X-Amz-Target' => 'AmazonSSM.GetParametersByPath',
            ...$this->headers,
        ];

        $response = $this->http->request('POST', $endpoint, $headers, $body);
        $tree = $this->toTree($response);

        return json_encode($tree, JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    private function toTree(HttpResponse $response): array
    {
        $data = $this->decode($response);
        $parameters = is_array($data['Parameters'] ?? null) ? $data['Parameters'] : [];

        $tree = [];

        foreach ($parameters as $parameter) {
            $parameter = is_array($parameter) ? $parameter : [];
            $name = TreeBuilder::asString($parameter['Name'] ?? null);
            $segments = array_values(array_filter(
                explode('/', $name),
                static fn (string $segment): bool => $segment !== '',
            ));

            if ($segments === []) {
                continue;
            }

            TreeBuilder::setPath($tree, $segments, $parameter['Value'] ?? null);
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
