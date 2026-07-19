<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Contract;

/**
 * The ordered sequence of processors that transforms configuration documents
 * into a configuration graph. Deterministic by construction.
 *
 * @see docs/architecture/PIPELINE.md "Configuration Pipeline"
 */
interface ConfigurationPipelineInterface
{
    /**
     * @param iterable<ConfigurationDocumentInterface> $documents
     */
    public function run(iterable $documents): ConfigurationGraphInterface;
}
