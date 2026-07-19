<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Adapter;

use ItPack\ConfigureIt\Contract\ConfigurationResourceInterface;
use ItPack\ConfigureIt\Contract\ConfigurationSourceInterface;
use ItPack\ConfigureIt\ValueObject\ConfigurationResource;
use ItPack\ConfigureIt\ValueObject\Format;

/**
 * Exposes process environment variables as a single ENV resource.
 *
 * Discovery only — never reads values here. Implements the `ConfigurationSource`
 * port. The companion {@see EnvReader} performs the actual read.
 *
 * @see docs/architecture/MODEL.md "Environment"
 */
final class EnvSource implements ConfigurationSourceInterface
{
    public function __construct(
        private readonly string $uri = 'env://environment',
    ) {
    }

    /**
     * @return iterable<ConfigurationResourceInterface>
     */
    public function discover(): iterable
    {
        yield new ConfigurationResource($this->uri, Format::ENV);
    }
}
