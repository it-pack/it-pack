<?php

declare(strict_types=1);

namespace ItPack\ConfigureItTesting\Fake;

use ItPack\ConfigureIt\Contract\ConfigurationSourceInterface;
use ItPack\ConfigureIt\Contract\ValueObject\ConfigurationResourceInterface;
use ItPack\ConfigureIt\ValueObject\ConfigurationResource;
use ItPack\ConfigureIt\ValueObject\Format;

/**
 * In-memory {@see ConfigurationSourceInterface} for tests.
 *
 * Build it from explicit {@see ConfigurationResourceInterface} objects or from a
 * simple `[uri => format]` map via {@see fromArray()}.
 */
final class FakeConfigurationSource implements ConfigurationSourceInterface
{
    /** @var array<ConfigurationResourceInterface> */
    private array $resources;

    public function __construct(ConfigurationResourceInterface ...$resources)
    {
        $this->resources = $resources;
    }

    /**
     * @param array<string, string> $spec map of `uri => format` (format as string, e.g. `'json'`).
     */
    public static function fromArray(array $spec): self
    {
        $resources = [];

        foreach ($spec as $uri => $format) {
            $resources[] = new ConfigurationResource((string) $uri, Format::fromString((string) $format));
        }

        return new self(...$resources);
    }

    public function discover(): iterable
    {
        return $this->resources;
    }
}
