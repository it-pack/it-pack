<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Core;

use ItPack\ConfigureIt\Contract\ConfigurationDocumentInterface;
use ItPack\ConfigureIt\Contract\ConfigurationGraphInterface;
use ItPack\ConfigureIt\Contract\ConfigurationNodeInterface;
use ItPack\ConfigureIt\Contract\ConfigurationPipelineInterface;
use ItPack\ConfigureIt\Contract\ConfigurationProcessorInterface;
use ItPack\ConfigureIt\Contract\DocumentNormalizerInterface;
use ItPack\ConfigureIt\Contract\Execution\ResolutionReporterInterface;
use ItPack\ConfigureIt\Contract\ProvenanceAwareProcessorInterface;
use ItPack\ConfigureIt\Execution\Occurrence;
use ItPack\ConfigureIt\ValueObject\OccurrenceType;

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
     * Base priority assigned to the first document; each subsequent document is
     * given a higher priority so later sources win last-wins merges.
     */
    private const DEFAULT_PRIORITY = 0;
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
        $priority = self::DEFAULT_PRIORITY;
        $graph = new ConfigurationGraph();

        foreach ($documents as $document) {
            $priority++;
            $origin = $document->origin() ?? 'programmatic';

            foreach ($this->normalizer->normalize($document, $priority, $origin, $document->layerId()) as $node) {
                $graph->put($node);
            }

            $this->reporter?->record(new Occurrence(
                OccurrenceType::Loaded,
                microtime(true),
                ['origin' => $origin, 'priority' => $priority],
            ));
        }

        foreach ($this->processors as $index => $processor) {
            $before = $graph->nodes();
            $graph = $processor->process($graph);
            $graph = $this->recordStage($graph, $before, $this->stageNameOf($processor));
        }

        if ([] !== $this->processors) {
            $this->reporter?->record(new Occurrence(
                OccurrenceType::Merged,
                microtime(true),
                ['processors' => count($this->processors)],
            ));
        }

        return $graph;
    }

    /**
     * @param array<string, ConfigurationNodeInterface> $before
     */
    /**
     * @param array<string, ConfigurationNodeInterface> $before
     */
    private function recordStage(
        ConfigurationGraphInterface $graph,
        array $before,
        string $stage,
    ): ConfigurationGraphInterface {
        $next = new ConfigurationGraph();

        foreach ($graph->nodes() as $key => $node) {
            $touched = !isset($before[$key]) || serialize($before[$key]->value()) !== serialize($node->value());

            $next->put($touched ? $node->withProvenance($node->provenance()->withProcessor($stage)) : $node);
        }

        return $next;
    }

    private function stageNameOf(ConfigurationProcessorInterface $processor): string
    {
        return $processor instanceof ProvenanceAwareProcessorInterface
            ? $processor->stageName()
            : get_class($processor);
    }
}
