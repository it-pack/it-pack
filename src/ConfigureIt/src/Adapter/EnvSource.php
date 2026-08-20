<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Adapter;

use ItPack\ConfigureIt\Contract\ValueObject\ConfigurationResourceInterface;
use ItPack\ConfigureIt\Contract\ConfigurationSourceInterface;
use ItPack\ConfigureIt\Contract\ValueObject\ConfigurationResourceFactoryInterface;
use ItPack\ConfigureIt\ValueObject\ConfigurationResource;
use ItPack\ConfigureIt\ValueObject\ConfigurationResourceFactory;
use ItPack\ConfigureIt\ValueObject\Format;

/**
 * Exposes process environment variables as a single ENV resource.
 *
 * Discovery only — never reads values here. Implements the `ConfigurationSource`
 * port. The companion {@see EnvReader} performs the actual read.
 *
 * @see docs/architecture/MODEL.md "Environment"
 */
final readonly class EnvSource implements ConfigurationSourceInterface
{
    public function __construct(
        private string $uri = 'env://environment',
        private ConfigurationResourceFactoryInterface $resourceFactory = new ConfigurationResourceFactory(),
    ) {}

    /**
     * @return iterable<ConfigurationResourceInterface>
     */
    public function discover(): iterable
    {
        yield $this->resourceFactory->build($this->uri, Format::ENV);
    }
}
