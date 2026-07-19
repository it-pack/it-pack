<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Contract;

use ItPack\ConfigureIt\ValueObject\Format;

/**
 * A discovered configuration resource.
 *
 * A resource is an opaque handle to configuration (file, directory, URL,
 * environment, stream, secret, database record). It is never parsed; it only
 * exposes where it lives and in which format.
 *
 * @see docs/architecture/MODEL.md "Configuration Resource"
 */
interface ConfigurationResourceInterface
{
    public function uri(): string;

    public function format(): Format;
}
