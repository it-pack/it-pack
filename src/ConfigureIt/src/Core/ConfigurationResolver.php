<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Core;

use ItPack\ConfigureIt\Contract\ConfigurationLoaderInterface;
use ItPack\ConfigureIt\Contract\ConfigurationReaderInterface;
use ItPack\ConfigureIt\Contract\ConfigurationResolverInterface;
use ItPack\ConfigureIt\Contract\ConfigurationSchemaInterface;
use ItPack\ConfigureIt\Contract\ConfigurationSnapshotInterface;
use ItPack\ConfigureIt\Contract\ConfigurationSourceInterface;
use ItPack\ConfigureIt\Exception\ResolutionException;
use ItPack\ConfigureIt\Exception\ValidationException;
use ItPack\ConfigureIt\Execution\Occurrence;
use ItPack\ConfigureIt\Execution\ResolutionReporter;
use ItPack\ConfigureIt\ValueObject\ValidationResult;

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
     * @param iterable<ConfigurationLoaderInterface> $loaders
     * @param iterable<ConfigurationSourceInterface> $sources default sources used when resolve() is called without arguments.
     */
    public function __construct(
        private ConfigurationReaderInterface  $reader,
        private iterable                      $loaders,
        private ConfigurationPipeline         $pipeline,
        private ?ConfigurationSchemaInterface $schema = null,
        private ?ResolutionReporter           $reporter = null,
        private iterable                      $sources = [],
    ) {
    }

    /**
     * @param iterable<ConfigurationSourceInterface> $sources when empty, the sources supplied at construction are used.
     */
    public function resolve(iterable $sources = []): ConfigurationSnapshotInterface
    {
        $effectiveSources = $sources === [] ? $this->sources : $sources;

        $documents = [];

        foreach ($effectiveSources as $source) {
            foreach ($source->discover() as $resource) {
                $raw = $this->reader->read($resource);
                $loader = $this->loaderFor($resource->format());
                $parsed = $loader->load($raw);

                $documents[] = new \ItPack\ConfigureIt\Core\ConfigurationDocument(
                    $parsed->toArray(),
                    $resource->format(),
                    $resource->uri(),
                );
            }
        }

        $graph = $this->pipeline->run($documents);

        if ($this->schema instanceof ConfigurationSchemaInterface) {
            $result = $this->schema->validate($graph);

            $this->reporter?->record(new Occurrence(
                'ConfigurationValidated',
                microtime(true),
                ['valid' => $result->valid()],
            ));

            if (!$result->valid()) {
                throw new ValidationException($this->describe($result));
            }
        }

        $snapshot = ConfigurationSnapshot::fromGraph($graph);

        $this->reporter?->record(new Occurrence(
            'ConfigurationResolved',
            microtime(true),
            ['nodes' => count($graph->nodes())],
        ));
        $this->reporter?->record(new Occurrence(
            'SnapshotCreated',
            microtime(true),
        ));

        return $snapshot;
    }

    private function loaderFor(\ItPack\ConfigureIt\ValueObject\Format $format): ConfigurationLoaderInterface
    {
        foreach ($this->loaders as $loader) {
            if ($loader->supports($format)) {
                return $loader;
            }
        }

        throw new ResolutionException(sprintf('No loader supports format "%s".', $format->value));
    }

    private function describe(ValidationResult $result): string
    {
        $lines = ['Configuration validation failed:'];

        foreach ($result->errors() as $error) {
            $lines[] = sprintf('  %s: %s', $error['path'], $error['message']);
        }

        return implode("\n", $lines);
    }
}
