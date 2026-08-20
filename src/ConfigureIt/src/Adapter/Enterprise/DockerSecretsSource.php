<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Adapter\Enterprise;

use ItPack\ConfigureIt\Contract\ConfigurationSourceInterface;
use ItPack\ConfigureIt\Contract\ValueObject\ConfigurationResourceInterface;
use ItPack\ConfigureIt\ValueObject\ConfigurationResourceFactory;
use ItPack\ConfigureIt\ValueObject\Format;

/**
 * Exposes Docker / Swarm secrets (mounted as files under a directory) as a
 * single ENV resource. Each file is one key; its trimmed contents the value.
 *
 * Discovery only — never reads or parses. The companion {@see VolumeReader}
 * reads the mount directory. Implements the `ConfigurationSource` port.
 *
 * @see docs/architecture/MODEL.md "Configuration Source"
 */
final readonly class DockerSecretsSource implements ConfigurationSourceInterface
{
    private const SCHEME = 'docker-secret://';

    public function __construct(
        private string                       $mountPath = '/run/secrets',
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
