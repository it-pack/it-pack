<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Adapter\Enterprise\Client;

use ItPack\ConfigureIt\Adapter\Enterprise\Contract\RemoteKeyValueClient;

/**
 * In-memory {@see RemoteKeyValueClient} for unit tests — returns a fixed JSON
 * body regardless of the requested URI.
 */
final readonly class FakeRemoteKeyValueClient implements RemoteKeyValueClient
{
    public function __construct(private string $body)
    {
    }

    public function fetch(string $uri): string
    {
        return $this->body;
    }
}
