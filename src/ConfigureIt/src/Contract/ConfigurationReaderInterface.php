<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Contract;

use ItPack\ConfigureIt\Exception\ReadException;

/**
 * Retrieves the raw content of a resource.
 *
 * A reader knows *how* to read (file, http, env, cli, secret) but does not
 * understand formats. It returns the unparsed raw string.
 *
 * @see docs/architecture/MODEL.md "Configuration Reader"
 */
interface ConfigurationReaderInterface
{
    /**
     * @throws ReadException when the resource cannot be read.
     */
    public function read(ConfigurationResourceInterface $resource): string;
}
