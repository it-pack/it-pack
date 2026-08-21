<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Adapter\Enterprise;

use ItPack\ConfigureIt\Contract\ConfigurationSourceInterface;
use ItPack\ConfigureIt\Contract\ValueObject\ConfigurationResourceInterface;
use ItPack\ConfigureIt\ValueObject\ConfigurationResourceFactory;
use ItPack\ConfigureIt\ValueObject\Format;

/**
 * Exposes a HashiCorp Consul KV prefix as a single JSON resource.
 *
 * The resource URI encodes the agent host/port and the KV prefix, e.g.
 * `consul://localhost:8500/app` reads the KV subtree under `app`. The companion
 * {@see \ItPack\ConfigureIt\Adapter\Enterprise\Client\ConsulClient} (wired
 * through {@see RemoteJsonReader}) fetches and nests it into JSON. Discovery
 * only — never reads or parses.
 *
 * @see docs/ADDITIONAL_FEATURES_PLAN.md §6 "Remote & Distributed Configuration"
 */
final readonly class ConsulSource implements ConfigurationSourceInterface
{
    public function __construct(
        private string                                 $host = 'localhost',
        private int                                    $port = 8500,
        private string                                 $prefix = 'app',
        private ConfigurationResourceFactory $resourceFactory = new ConfigurationResourceFactory(),
    ) {
    }

    /**
     * @return iterable<ConfigurationResourceInterface>
     */
    public function discover(): iterable
    {
        yield $this->resourceFactory->build(
            sprintf('consul://%s:%d/%s', $this->host, $this->port, ltrim($this->prefix, '/')),
            Format::JSON,
        );
    }
}
