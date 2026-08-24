<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Core;

use ItPack\ConfigureIt\Contract\ConfigurationGraphInterface;
use ItPack\ConfigureIt\Contract\ConfigurationLoaderFactoryInterface;
use ItPack\ConfigureIt\Contract\ConfigurationNodeInterface;
use ItPack\ConfigureIt\Contract\ConfigurationProcessorInterface;
use ItPack\ConfigureIt\Contract\ConfigurationReaderInterface;
use ItPack\ConfigureIt\Contract\DocumentNormalizerInterface;
use ItPack\ConfigureIt\Contract\Execution\ExecutionContextInterface;
use ItPack\ConfigureIt\Contract\ExecutionContextAwareProcessorInterface;
use ItPack\ConfigureIt\Contract\ProvenanceAwareProcessorInterface;
use ItPack\ConfigureIt\Exception\ResolutionException;
use ItPack\ConfigureIt\Execution\ExecutionContext;
use ItPack\ConfigureIt\ValueObject\ConfigurationResourceFactory;
use ItPack\ConfigureIt\ValueObject\Format;
use ItPack\ConfigureIt\ValueObject\ProcessorStage;

/**
 * Imports external configuration files referenced from the graph.
 *
 * Any node whose value is `"@include <relative-path>"` is replaced by the nodes
 * parsed from that file (format derived from the extension). Included nodes take
 * a higher priority than the directive node, so an import overrides the including
 * document's sibling keys. The directive node itself is dropped. This is the
 * "Include / Import" pipeline stage.
 *
 * The directive accepts an optional `@if <condition>` suffix for conditional
 * inclusion, e.g. `"@include db.yaml @if env=prod"`. The supported condition
 * grammar is `env=<v> | tenant=<v> | profile=<v>`, evaluated against the
 * {@see ExecutionContextInterface}; when the condition is not met the include is
 * skipped and the directive node is dropped. Conditions are matched via
 * {@see ExecutionContextInterface::environment()} / `attribute()`.
 *
 * Single-pass: nested includes require adding the processor more than once to the
 * pipeline (or composing it after other stages).
 *
 * @see docs/architecture/PIPELINE.md "Include"
 * @see docs/ADDITIONAL_FEATURES_PLAN.md §3 "Layered Composition & Environments"
 */
final readonly class IncludeProcessor implements ConfigurationProcessorInterface, ProvenanceAwareProcessorInterface, ExecutionContextAwareProcessorInterface
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
        private ExecutionContextInterface       $context = new ExecutionContext(),
    ) {
    }

    public function stageName(): string
    {
        return ProcessorStage::Include->value;
    }

    public function withExecutionContext(ExecutionContextInterface $context): static
    {
        return new self(
            reader: $this->reader,
            loaders: $this->loaders,
            normalizer: $this->normalizer,
            baseDirectory: $this->baseDirectory,
            resourceFactory: $this->resourceFactory,
            directive: $this->directive,
            context: $context,
        );
    }

    public function process(ConfigurationGraphInterface $graph): ConfigurationGraphInterface
    {
        $kept = [];
        $included = [];

        foreach ($graph->nodes() as $node) {
            $value = $node->value();

            if (\is_string($value) && \str_starts_with($value, $this->directive . ' ')) {
                [$path, $condition] = $this->parseDirective($value);

                if ($condition !== null && !$this->conditionMatches($condition)) {
                    continue;
                }

                $included = [...$included, ...$this->include($path, $node)];
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
     * Split a directive value into its path and optional `@if <condition>` suffix.
     *
     * @return array{0: string, 1: ?string}
     */
    private function parseDirective(string $value): array
    {
        $body = \trim(\substr($value, \strlen($this->directive) + 1));

        if (\preg_match('/^(?P<path>\S+)(?:\s+@if\s+(?P<cond>.+))?$/', $body, $m) === 1) {
            $condition = isset($m['cond']) ? \trim($m['cond']) : null;

            return [$m['path'], $condition];
        }

        return [$body, null];
    }

    /**
     * Evaluate an `@if` condition against the execution context.
     *
     * Grammar: `env=<v> | tenant=<v> | profile=<v>` (matched via environment /
     * attribute). An unrecognized condition is a resolution error.
     */
    private function conditionMatches(string $condition): bool
    {
        if (\preg_match('/^(env|tenant|profile)=(\S+)$/', $condition, $m) !== 1) {
            throw new ResolutionException(sprintf('Invalid @include condition "%s".', $condition));
        }

        [$kind, $expected] = [$m[1], $m[2]];

        return match ($kind) {
            'env' => $this->context->environment() === $expected,
            'tenant', 'profile' => $this->context->attribute($kind) === $expected,
        };
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

        // Included nodes inherit the layer of the directive that pulled them in,
        // so layer attribution stays accurate across conditional includes (E3.3).
        $layerId = $document->layerId() ?? $directive->provenance()->layerId();

        $nodes = $this->normalizer->normalize($document, $directive->priority() + 1, 'include:' . $relative, $layerId);

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
