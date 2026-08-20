<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Adapter\Enterprise;

use ItPack\ConfigureIt\Contract\ConfigurationSourceInterface;
use ItPack\ConfigureIt\Contract\ValueObject\ConfigurationResourceInterface;
use ItPack\ConfigureIt\ValueObject\ConfigurationResourceFactory;
use ItPack\ConfigureIt\ValueObject\Format;

/**
 * Exposes a Kubernetes ConfigMap (projected as a volume of files) as a single
 * ENV resource.
 *
 * Discovery only — never reads or parses. The companion {@see VolumeReader}
 * reads the mount directory. Each file is one key; its trimmed contents the
 * value. Implements the `ConfigurationSource` port.
 *
 * @see docs/architecture/MODEL.md "Configuration Source"
 */
final readonly class ConfigMapSource implements ConfigurationSourceInterface
{
    private const SCHEME = 'configmap://';

    public function __construct(
        private string                       $mountPath,
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
