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
    /**
     * A short, stable identifier for this processor (e.g. "casting-processor").
     *
     * Replaces runtime {@see get_class()} discrimination. When a processor also
     * implements {@see ProvenanceAwareProcessorInterface} its
     * {@see ProvenanceAwareProcessorInterface::stageName()} is preferred for
     * provenance trails; this method backs the non-aware fallback.
     */
    public function name(): string;

    public function process(ConfigurationGraphInterface $graph): ConfigurationGraphInterface;
}
