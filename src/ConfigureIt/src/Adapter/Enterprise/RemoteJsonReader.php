<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Adapter\Enterprise;

use ItPack\ConfigureIt\Adapter\Enterprise\Contract\RemoteKeyValueClient;
use ItPack\ConfigureIt\Contract\ConfigurationReaderInterface;
use ItPack\ConfigureIt\Contract\ValueObject\ConfigurationResourceInterface;
use ItPack\ConfigureIt\Exception\ReadException;
use ItPack\ConfigureIt\ValueObject\Format;

/**
 * Reads a remote parameter-store / secret-manager resource via an injected
 * {@see RemoteKeyValueClient} and returns the JSON body for the
 * {@see \ItPack\ConfigureIt\Adapter\JsonLoader}.
 *
 * The scheme identifies which cloud client owns the request. Knows how to fetch;
 * never parses format. Implements the `ConfigurationReader` port.
 *
 * @see docs/architecture/MODEL.md "Configuration Reader"
 */
final readonly class RemoteJsonReader implements ConfigurationReaderInterface
{
    public function __construct(
        private string               $scheme,
        private RemoteKeyValueClient $client,
    ) {
    }

    public function read(ConfigurationResourceInterface $resource): string
    {
        if (!$this->supports($resource)) {
            throw new ReadException(sprintf('RemoteJsonReader cannot read resource "%s".', $resource->uri()));
        }

        return $this->client->fetch($resource->uri());
    }

    public function supports(ConfigurationResourceInterface $resource): bool
    {
        return $resource->format() === Format::JSON
            && str_starts_with($resource->uri(), $this->scheme . '://');
    }
}
