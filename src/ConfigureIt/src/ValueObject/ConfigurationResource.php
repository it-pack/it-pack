<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\ValueObject;

use ItPack\ConfigureIt\Contract\ConfigurationResourceInterface;

/**
 * Immutable identity of a single configuration resource discovered by a source.
 *
 * A resource simply exists; it is never parsed here. It carries only the
 * location (uri) and the declared format so that downstream readers/loaders
 * can be selected without the source knowing how to read or parse.
 *
 * @see docs/architecture/MODEL.md "Configuration Resource"
 */
final readonly class ConfigurationResource implements ConfigurationResourceInterface
{
    public function __construct(
        private string $uri,
        private Format $format,
    ) {
    }

    public function uri(): string
    {
        return $this->uri;
    }

    public function format(): Format
    {
        return $this->format;
    }
}
