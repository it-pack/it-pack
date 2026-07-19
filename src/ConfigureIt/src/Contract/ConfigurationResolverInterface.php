<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Contract;

use ItPack\ConfigureIt\Exception\ResolutionException;

/**
 * Coordinates the entire resolution process and produces the final
 * immutable configuration snapshot.
 *
 * The resolver orchestrates — it does not parse. It wires sources, readers,
 * loaders, the pipeline, the schema and the snapshot factory.
 *
 * @see docs/architecture/MODEL.md "Configuration Resolver"
 */
interface ConfigurationResolverInterface
{
    /**
     * @param iterable<ConfigurationSourceInterface> $sources
     * @throws ResolutionException when resolution cannot complete.
     */
    public function resolve(iterable $sources): ConfigurationSnapshotInterface;
}
