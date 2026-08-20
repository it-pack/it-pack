<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Adapter;

use ItPack\ConfigureIt\Contract\ConfigurationReaderInterface;
use ItPack\ConfigureIt\Contract\ValueObject\ConfigurationResourceInterface;
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
        if (!$this->supports($resource)) {
            throw new ReadException(sprintf('Configuration file "%s" does not exist.', $resource->uri()));
        }

        $content = file_get_contents($resource->uri());

        if ($content === false) {
            throw new ReadException(sprintf('Unable to read configuration file "%s".', $resource->uri()));
        }

        return $content;
    }

    public function supports(ConfigurationResourceInterface $resource): bool
    {
        try {
            return is_file($resource->uri());
        } catch (\Throwable) {
           return false;
       }
    }
}
