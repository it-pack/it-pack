<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Adapter\Enterprise;

use ItPack\ConfigureIt\Contract\ConfigurationSourceInterface;
use ItPack\ConfigureIt\Contract\SecretSourceInterface;
use ItPack\ConfigureIt\Contract\ValueObject\ConfigurationResourceInterface;
use ItPack\ConfigureIt\ValueObject\ConfigurationResourceFactory;
use ItPack\ConfigureIt\ValueObject\Format;

/**
 * Exposes a Kubernetes Secret (projected as a volume of files) as a single ENV
 * resource. Each file is one key; its trimmed contents the value.
 *
 * Discovery only — never reads or parses. The companion {@see VolumeReader}
 * reads the mount directory. Implements the `ConfigurationSource` port.
 *
 * @see docs/architecture/MODEL.md "Configuration Source"
 */
final readonly class KubernetesSecretSource implements ConfigurationSourceInterface, SecretSourceInterface
{
    private const SCHEME = 'k8s-secret://';

    public function __construct(
        private string                       $mountPath = '/var/run/secrets',
        private ConfigurationResourceFactory $resourceFactory = new ConfigurationResourceFactory(),
    ) {
    }

    /**
     * @return iterable<ConfigurationResourceInterface>
     */
    public function discover(): iterable
    {
        yield $this->resourceFactory->build(self::SCHEME . $this->mountPath, Format::ENV);
    }
}
