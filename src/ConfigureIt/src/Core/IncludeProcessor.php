<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Core;

use ItPack\ConfigureIt\Contract\ConfigurationGraphInterface;
use ItPack\ConfigureIt\Contract\ConfigurationLoaderFactoryInterface;
use ItPack\ConfigureIt\Contract\ConfigurationNodeInterface;
use ItPack\ConfigureIt\Contract\ConfigurationProcessorInterface;
use ItPack\ConfigureIt\Contract\ConfigurationReaderInterface;
use ItPack\ConfigureIt\Contract\DocumentNormalizerInterface;
use ItPack\ConfigureIt\Contract\ProvenanceAwareProcessorInterface;
use ItPack\ConfigureIt\Exception\ResolutionException;
use ItPack\ConfigureIt\ValueObject\ConfigurationResourceFactory;
use ItPack\ConfigureIt\ValueObject\Format;

/**
 * Imports external configuration files referenced from the graph.
 *
 * Any node whose value is `"@include <relative-path>"` is replaced by the nodes
 * parsed from that file (format derived from the extension). Included nodes take
 * a higher priority than the directive node, so an import overrides the including
 * document's sibling keys. The directive node itself is dropped. This is the
 * "Include / Import" pipeline stage.
 *
 * Single-pass: nested includes require adding the processor more than once to the
 * pipeline (or composing it after other stages).
 *
 * @see docs/architecture/PIPELINE.md "Include"
 */
final readonly class IncludeProcessor implements ConfigurationProcessorInterface, ProvenanceAwareProcessorInterface
{
    /**
     * @param string $directive prefix that marks an include directive value.
     */
    public function __construct(
        private ConfigurationReaderInterface    $reader,
        private ConfigurationLoaderFactoryInterface $loaders,
        private DocumentNormalizerInterface     $normalizer = new DocumentNormalizer(),
        private string                           $baseDirectory = '.',
        private ConfigurationResourceFactory     $resourceFactory = new ConfigurationResourceFactory(),
        private string                           $directive = '@include',
    ) {
    }

    public function stageName(): string
    {
        return 'IncludeProcessor';
    }

    public function process(ConfigurationGraphInterface $graph): ConfigurationGraphInterface
    {
        $kept = [];
        $included = [];

        foreach ($graph->nodes() as $node) {
            $value = $node->value();

            if (\is_string($value) && \str_starts_with($value, $this->directive . ' ')) {
                $included = [...$included, ...$this->include(\trim(\substr($value, \strlen($this->directive) + 1)), $node)];
                continue;
            }

            $kept[] = $node;
        }

        $next = new ConfigurationGraph($kept);

        foreach ($included as $node) {
            $next->put($node);
        }

        return $next;
    }

    /**
     * @return list<ConfigurationNodeInterface>
     */
    private function include(string $relative, ConfigurationNodeInterface $directive): array
    {
        $path = $this->baseDirectory . \DIRECTORY_SEPARATOR . \ltrim($relative, \DIRECTORY_SEPARATOR);

        try {
            $format = Format::fromString(\pathinfo($path, PATHINFO_EXTENSION));
        } catch (\InvalidArgumentException $e) {
            throw new ResolutionException(sprintf('Cannot determine format of included file "%s".', $path), 0, $e);
        }

        $resource = $this->resourceFactory->build($path, $format);

        if (!$this->reader->supports($resource)) {
            throw new ResolutionException(sprintf('Included configuration "%s" does not exist.', $path));
        }

        $raw = $this->reader->read($resource);
        $document = $this->loaders->build($format)->load($raw);

        $nodes = $this->normalizer->normalize($document, $directive->priority() + 1, 'include:' . $relative);

        $parent = $directive->key();

        $pos = \strrpos($parent, '.');

        if ($pos === false) {
            return $nodes;
        }

        $prefix = \substr($parent, 0, $pos) . '.';

        return \array_map(
            static fn (ConfigurationNodeInterface $node): ConfigurationNodeInterface => new ConfigurationNode(
                key: $prefix . $node->key(),
                value: $node->value(),
                source: $node->source(),
                priority: $node->priority(),
                metadata: $node->metadata(),
                provenance: $node->provenance(),
            ),
            $nodes,
        );
    }
}
