<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Adapter\Enterprise;

use ItPack\ConfigureIt\Contract\ConfigurationSourceInterface;
use ItPack\ConfigureIt\Contract\ValueObject\ConfigurationResourceInterface;
use ItPack\ConfigureIt\ValueObject\ConfigurationResourceFactory;
use ItPack\ConfigureIt\ValueObject\Format;

/**
 * Exposes an Azure App Configuration store as a single JSON resource.
 *
 * The resource URI encodes the store name and key prefix, e.g.
 * `azure-appconfig://mystore/app` lists keys `app*` from `mystore`. The
 * companion {@see \ItPack\ConfigureIt\Adapter\Enterprise\Client\AzureAppConfigClient}
 * (wired through {@see RemoteJsonReader}) fetches and nests the key/values.
 *
 * Discovery only — never reads or parses. Implements the `ConfigurationSource`
 * port. See ROADMAP Phase 8.
 */
final readonly class AzureAppConfigSource implements ConfigurationSourceInterface
{
    public function __construct(
        private string                       $store,
        private string                       $prefix = '',
        private ConfigurationResourceFactory $resourceFactory = new ConfigurationResourceFactory(),
    ) {
    }

    /**
     * @return iterable<ConfigurationResourceInterface>
     */
    public function discover(): iterable
    {
        yield $this->resourceFactory->build(
            sprintf('azure-appconfig://%s/%s', $this->store, $this->prefix),
            Format::JSON,
        );
    }
}
