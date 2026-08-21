<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Adapter\Enterprise;

use ItPack\ConfigureIt\Contract\ConfigurationSourceInterface;
use ItPack\ConfigureIt\Contract\ValueObject\ConfigurationResourceInterface;
use ItPack\ConfigureIt\ValueObject\ConfigurationResourceFactory;
use ItPack\ConfigureIt\ValueObject\Format;

/**
 * Exposes an etcd v2 key prefix as a single JSON resource.
 *
 * The resource URI encodes the node host/port and the key prefix, e.g.
 * `etcd://localhost:2379/app` reads the key subtree under `/app`. The companion
 * {@see \ItPack\ConfigureIt\Adapter\Enterprise\Client\EtcdClient} (wired
 * through {@see RemoteJsonReader}) fetches and nests it into JSON. Discovery
 * only — never reads or parses.
 *
 * @see docs/ADDITIONAL_FEATURES_PLAN.md §6 "Remote & Distributed Configuration"
 */
final readonly class EtcdSource implements ConfigurationSourceInterface
{
    public function __construct(
        private string                                 $host = 'localhost',
        private int                                    $port = 2379,
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
            sprintf('etcd://%s:%d/%s', $this->host, $this->port, ltrim($this->prefix, '/')),
            Format::JSON,
        );
    }
}
