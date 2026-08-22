<?php

declare(strict_types=1);

namespace ItPack\ConfigureItExplorer\Graph;

use ItPack\ConfigureIt\Contract\ConfigurationGraphInterface;

/**
 * Supplies a resolved configuration graph to the explorer.
 *
 * Keeping this behind an interface lets the web controller stay testable: the
 * default {@see FromFileGraphProvider} reuses the Core `GraphFromFile` service
 * so the explorer is a thin, read-only adapter over the existing resolution
 * pipeline (no duplicate loading logic).
 */
interface GraphProviderInterface
{
    public function build(string $path): ConfigurationGraphInterface;
}
