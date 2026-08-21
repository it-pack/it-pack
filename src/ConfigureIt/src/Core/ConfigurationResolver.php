<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Core;

use ItPack\ConfigureIt\Contract\ConfigurationDocumentFactoryInterface;
use ItPack\ConfigureIt\Contract\ConfigurationDocumentInterface;
use ItPack\ConfigureIt\Contract\ConfigurationGraphInterface;
use ItPack\ConfigureIt\Contract\ConfigurationLoaderFactoryInterface;
use ItPack\ConfigureIt\Contract\ConfigurationReaderInterface;
use ItPack\ConfigureIt\Contract\ConfigurationResolverInterface;
use ItPack\ConfigureIt\Contract\ConfigurationSchemaInterface;
use ItPack\ConfigureIt\Contract\ConfigurationSnapshotFactoryInterface;
use ItPack\ConfigureIt\Contract\ConfigurationSnapshotInterface;
use ItPack\ConfigureIt\Contract\ConfigurationSourceInterface;
use ItPack\ConfigureIt\Contract\DeclaresScalarTypesInterface;
use ItPack\ConfigureIt\Contract\Execution\ResolutionReporterInterface;
use ItPack\ConfigureIt\Contract\LayerAwareSourceInterface;
use ItPack\ConfigureIt\Contract\ValueObject\ValidationResultInterface;
use ItPack\ConfigureIt\Exception\ValidationException;
use ItPack\ConfigureIt\Execution\Occurrence;
use ItPack\ConfigureIt\Execution\OccurrenceFactory;

/**
 * Coordinates the entire resolution process and produces the final immutable
 * configuration snapshot.
 *
 * Orchestrates — does not parse. For each source it discovers resources, reads
 * them, selects a loader by format, builds documents, feeds them through the
 * pipeline, validates the resulting graph (optional schema) and freezes a
 * snapshot. Emits Occurrences via the reporter throughout (docs/EXECUTION.md).
 *
 * @see docs/architecture/MODEL.md "Configuration Resolver"
 */
final readonly class ConfigurationResolver implements ConfigurationResolverInterface
{
    /**
     * @param iterable<ConfigurationSourceInterface> $sources default sources used when resolve() is called without arguments.
     */
    public function __construct(
        private ConfigurationReaderInterface          $reader,
        private ConfigurationLoaderFactoryInterface   $loaders,
        private ConfigurationPipeline                 $pipeline,
        private ?ConfigurationSchemaInterface         $schema = null,
        private ?ResolutionReporterInterface          $reporter = null,
        private iterable                              $sources = [],
        private OccurrenceFactory                     $occurrenceFactory = new OccurrenceFactory(),
        private ConfigurationDocumentFactoryInterface $documentFactory = new ConfigurationDocumentFactory(),
        private ConfigurationSnapshotFactoryInterface $snapshotFactory = new ConfigurationSnapshotFactory(),
    ) {
    }

    /**
     * @param iterable<ConfigurationSourceInterface> $sources when empty, the sources supplied at construction are used.
     */
    public function resolve(iterable $sources = []): ConfigurationSnapshotInterface
    {
        return $this->createSnapshot(
            $this->resolveResources($sources)
        );
    }

    private function describe(ValidationResultInterface $result): string
    {
        $lines = ['Configuration validation failed:'];

        foreach ($result->errors() as $error) {
            $lines[] = sprintf('  %s: %s', $error['path'], $error['message']);
        }

        return implode("\n", $lines);
    }

    /**
     * @param array<mixed> $data
     */
    private function createOccurrence(string $name, array $data = []): Occurrence
    {
        return $this->occurrenceFactory->build($name, microtime(true), $data);
    }

    private function validateGraph(ConfigurationGraphInterface $graph): void
    {
        if ($this->schema instanceof ConfigurationSchemaInterface) {
            $result = $this->schema->validate($graph);

            $this->reporter?->record(
                $this->createOccurrence(
                    'ConfigurationValidated',
                    ['valid' => $result->valid()],
                )
            );

            if (!$result->valid()) {
                throw new ValidationException($this->describe($result));
            }
        }
    }

    /**
     * @param array<ConfigurationDocumentInterface> $documents
     */
    private function runPipeline(array $documents): ConfigurationGraphInterface
    {
        $graph = $this->pipeline->run($documents);

        $this->validateGraph($graph);

        $diagnostics = new ConfigurationDiagnostics($this->reporter)->report($graph);

        $this->reporter?->record(
            $this->createOccurrence(
                'ConfigurationResolved',
                [
                    'nodes' => count($graph->nodes()),
                    'overrides' => count($diagnostics->overrides),
                    'unresolved' => count($diagnostics->unresolved),
                ],
            )
        );

        return $graph;
    }

    /**
     * @param array<ConfigurationDocumentInterface> $documents
     */
    private function createSnapshot(array $documents): ConfigurationSnapshotInterface
    {
        $snapshot = $this->snapshotFactory->buildFromGraph(
            $this->runPipeline($documents)
        );

        $this->reporter?->record(
            $this->createOccurrence(
                'SnapshotCreated',
            )
        );

        return $this->freezeTyped($snapshot);
    }

    /**
     * When the schema can enumerate expected scalar types, wrap the snapshot in a
     * {@see TypedSnapshot} so mis-typed leaves fail fast at freeze time. The
     * behaviour is unchanged when no schema, or no type information, is present.
     */
    private function freezeTyped(ConfigurationSnapshot $snapshot): ConfigurationSnapshotInterface
    {
        if ($this->schema instanceof DeclaresScalarTypesInterface) {
            $types = $this->schema->scalarTypes();

            if ($types !== []) {
                return new TypedSnapshot($snapshot, $types);
            }
        }

        return $snapshot;
    }

    /**
     * @param iterable<ConfigurationSourceInterface> $sources
     * @return array<ConfigurationDocumentInterface>
     */
    private function resolveResources(iterable $sources = []): array
    {
        $effectiveSources = $sources === [] ? $this->sources : $sources;

        $documents = [];

        foreach ($effectiveSources as $source) {
            foreach ($source->discover() as $resource) {
                $raw = $this->reader->read($resource);
                $loader = $this->loaders->build($resource->format());
                $parsed = $loader->load($raw);

                $layerId = $source instanceof LayerAwareSourceInterface
                    ? $source->layerId()
                    : null;

                $documents[] = $this->documentFactory->build(
                    $parsed->toArray(),
                    $resource->format(),
                    $resource->uri(),
                    get_class($loader),
                    $layerId,
                );
            }
        }

        return $documents;
    }
}
