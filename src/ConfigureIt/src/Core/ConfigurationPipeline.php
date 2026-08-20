<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Core;

use ItPack\ConfigureIt\Contract\ConfigurationDocumentInterface;
use ItPack\ConfigureIt\Contract\ConfigurationGraphInterface;
use ItPack\ConfigureIt\Contract\ConfigurationPipelineInterface;
use ItPack\ConfigureIt\Contract\ConfigurationProcessorInterface;
use ItPack\ConfigureIt\Contract\DocumentNormalizerInterface;
use ItPack\ConfigureIt\Contract\Execution\ResolutionReporterInterface;
use ItPack\ConfigureIt\Execution\Occurrence;

/**
 * The ordered sequence of processors transforming documents into a graph.
 *
 * Documents are first flattened into {@see ConfigurationNode}s (earlier sources
 * get lower priority, later sources higher — so the natural ordering produces
 * last-wins semantics). Processors then each transform the graph deterministically.
 *
 * @see docs/architecture/PIPELINE.md "Configuration Pipeline"
 */
final readonly class ConfigurationPipeline implements ConfigurationPipelineInterface
{
    /**
     * @param list<ConfigurationProcessorInterface> $processors
     */
    public function __construct(
        public array               $processors = [],
        public ?ResolutionReporterInterface $reporter = null,
        private DocumentNormalizerInterface $normalizer = new DocumentNormalizer(),
    ) {
    }

    /**
     * @param iterable<ConfigurationDocumentInterface> $documents
     */
    public function run(iterable $documents): ConfigurationGraphInterface
    {
        $priority = 0;
        $graph = new ConfigurationGraph();

        foreach ($documents as $document) {
            $priority++;
            $origin = $document->origin() ?? 'programmatic';

            foreach ($this->normalizer->normalize($document, $priority, $origin) as $node) {
                $graph->put($node);
            }

            $this->reporter?->record(new Occurrence(
                'ConfigurationLoaded',
                microtime(true),
                ['origin' => $origin, 'priority' => $priority],
            ));
        }

        foreach ($this->processors as $index => $processor) {
            $graph = $processor->process($graph);
        }

        if ($this->processors !== []) {
            $this->reporter?->record(new Occurrence(
                'ConfigurationMerged',
                microtime(true),
                ['processors' => count($this->processors)],
            ));
        }

        return $graph;
    }
}
