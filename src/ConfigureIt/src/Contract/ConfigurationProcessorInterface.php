<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Contract;

/**
 * Performs exactly one transformation on a configuration graph.
 *
 * Processors are composable, independent and replaceable. Each stage of the
 * pipeline (normalize, merge, validate, interpolate, defaults, freeze) is a
 * processor.
 *
 * @see docs/architecture/PIPELINE.md "Processors"
 */
interface ConfigurationProcessorInterface
{
    public function process(ConfigurationGraphInterface $graph): ConfigurationGraphInterface;
}
