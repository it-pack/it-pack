<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Core;

use ItPack\ConfigureIt\Contract\ConfigurationDocumentInterface;
use ItPack\ConfigureIt\Contract\ConfigurationGraphInterface;
use ItPack\ConfigureIt\Contract\ConfigurationPipelineInterface;
use ItPack\ConfigureIt\Contract\ConfigurationProcessorInterface;
use ItPack\ConfigureIt\Execution\Occurrence;
use ItPack\ConfigureIt\Execution\ResolutionReporter;

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
        public ?ResolutionReporter $reporter = null,
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
            $nodes = $this->flatten($document->toArray(), '', $origin, $priority);

            foreach ($nodes as $node) {
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

    /**
     * @param array $data
     * @param string $prefix
     * @param string $source
     * @param int $priority
     * @return list<ConfigurationNode>
     */
    private function flatten(array $data, string $prefix, string $source, int $priority): array
    {
        $key = $prefix === '' ? null : Key::fromString($prefix);
        $nodes = [];

        foreach ($data as $segment => $value) {
            $child = $key === null ? Key::fromString((string) $segment) : $key->child((string) $segment);

            if (is_array($value) && Key::isAssociative($value)) {
                $nodes = [...$nodes, ...$this->flatten($value, $child->toString(), $source, $priority)];
            } else {
                $nodes[] = new ConfigurationNode(
                    key: $child->toString(),
                    value: $value,
                    source: $source,
                    priority: $priority,
                    metadata: ['origin' => $source, 'format' => 'document'],
                );
            }
        }

        return $nodes;
    }
}
