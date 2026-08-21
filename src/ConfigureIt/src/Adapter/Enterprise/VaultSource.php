<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Adapter\Enterprise;

use ItPack\ConfigureIt\Contract\ConfigurationSourceInterface;
use ItPack\ConfigureIt\Contract\SecretSourceInterface;
use ItPack\ConfigureIt\Contract\ValueObject\ConfigurationResourceInterface;
use ItPack\ConfigureIt\ValueObject\ConfigurationResourceFactory;
use ItPack\ConfigureIt\ValueObject\Format;

/**
 * Exposes a HashiCorp Vault KV secrets engine as a single JSON resource.
 *
 * The resource URI encodes the host and the KV path, e.g.
 * `vault://vault.example.com:8200/secret/data/app` reads the KV v2 secret at
 * that path. The companion {@see \ItPack\ConfigureIt\Adapter\Enterprise\Client\VaultClient}
 * (wired through {@see RemoteJsonReader}) fetches and returns the secret data as
 * JSON.
 *
 * Discovery only — never reads or parses. Implements the `ConfigurationSource`
 * and {@see SecretSourceInterface} ports so resolved secrets are auto-flagged
 * sensitive (M4 — Epic E5.1). Reuses the Phase 8 `RemoteKeyValueClient` +
 * `HttpClient` plumbing.
 */
final readonly class VaultSource implements ConfigurationSourceInterface, SecretSourceInterface
{
    public function __construct(
        private string                       $host = 'localhost:8200',
        private string                       $path = 'secret/data/app',
        private ConfigurationResourceFactory $resourceFactory = new ConfigurationResourceFactory(),
    ) {
    }

    /**
     * @return iterable<ConfigurationResourceInterface>
     */
    public function discover(): iterable
    {
        yield $this->resourceFactory->build(
            sprintf('vault://%s/%s', $this->host, ltrim($this->path, '/')),
            Format::JSON,
        );
    }
}
