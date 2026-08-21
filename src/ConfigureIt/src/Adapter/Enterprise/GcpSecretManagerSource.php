<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Adapter\Enterprise;

use ItPack\ConfigureIt\Contract\ConfigurationSourceInterface;
use ItPack\ConfigureIt\Contract\SecretSourceInterface;
use ItPack\ConfigureIt\Contract\ValueObject\ConfigurationResourceInterface;
use ItPack\ConfigureIt\ValueObject\ConfigurationResourceFactory;
use ItPack\ConfigureIt\ValueObject\Format;

/**
 * Exposes a GCP Secret Manager project as a single JSON resource.
 *
 * The resource URI encodes the project and an optional secret-id prefix, e.g.
 * `gcp-secretmanager://my-project/app` lists and resolves secrets under `app`.
 * The companion {@see \ItPack\ConfigureIt\Adapter\Enterprise\Client\GcpSecretManagerClient}
 * (wired through {@see RemoteJsonReader}) fetches and nests the secrets.
 *
 * Discovery only — never reads or parses. Implements the `ConfigurationSource`
 * port. See ROADMAP Phase 8.
 */
final readonly class GcpSecretManagerSource implements ConfigurationSourceInterface, SecretSourceInterface
{
    public function __construct(
        private string                       $project,
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
            sprintf('gcp-secretmanager://%s/%s', $this->project, $this->prefix),
            Format::JSON,
        );
    }
}
