<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Adapter;

use ItPack\ConfigureIt\Contract\ConfigurationReaderInterface;
use ItPack\ConfigureIt\Contract\ConfigurationResourceInterface;
use ItPack\ConfigureIt\Exception\ReadException;

/**
 * Reads the raw content of a file-backed resource.
 *
 * Uses only the standard library; never interprets format. Implements the
 * `ConfigurationReader` port.
 *
 * @see docs/architecture/MODEL.md "Configuration Reader"
 */
final class FileReader implements ConfigurationReaderInterface
{
    public function read(ConfigurationResourceInterface $resource): string
    {
        $uri = $resource->uri();

        if (!is_file($uri)) {
            throw new ReadException(sprintf('Configuration file "%s" does not exist.', $uri));
        }

        $content = file_get_contents($uri);

        if ($content === false) {
            throw new ReadException(sprintf('Unable to read configuration file "%s".', $uri));
        }

        return $content;
    }
}
