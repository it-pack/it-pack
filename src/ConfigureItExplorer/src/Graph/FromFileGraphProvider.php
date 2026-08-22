<?php

declare(strict_types=1);

namespace ItPack\ConfigureItExplorer\Graph;

use ItPack\ConfigureIt\CLI\GraphFromFile;
use ItPack\ConfigureIt\Contract\ConfigurationGraphInterface;

/**
 * Default graph provider: resolves a single configuration file into a
 * {@see ConfigurationGraphInterface} using the Core `GraphFromFile` service.
 *
 * This is exactly what the `bin/configure-it explain|diff|graph` commands use,
 * so the web explorer and the CLI stay behaviourally identical.
 */
final class FromFileGraphProvider implements GraphProviderInterface
{
    public function build(string $path): ConfigurationGraphInterface
    {
        return GraphFromFile::build($path);
    }
}
