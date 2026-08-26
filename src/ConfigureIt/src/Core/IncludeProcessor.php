<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Core;

use function array_map;
use function basename;
use function dirname;

use InvalidArgumentException;
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

use function ltrim;
use function pathinfo;
use function preg_match;
use function str_starts_with;
use function strlen;
use function strrpos;
use function substr;
use function trim;

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
final readonly class IncludeProcessor implements
    ConfigurationProcessorInterface,
    ProvenanceAwareProcessorInterface,
    ExecutionContextAwareProcessorInterface
{
    /** Recognized `@if` attribute vocabulary (matched against the execution context). */
    private const array CONDITION_ATTRIBUTES = ['env', 'tenant', 'profile'];

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

            if (\is_string($value) && str_starts_with($value, $this->directive . ' ')) {
                [$path, $condition] = $this->parseDirective($value);

                if (null !== $condition && !$this->conditionMatches($condition)) {
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
        $body = trim(substr($value, strlen($this->directive) + 1));

        if (1 === preg_match('/^(?P<path>\S+)(?:\s+@if\s+(?P<cond>.+))?$/', $body, $m)) {
            $condition = isset($m['cond']) ? trim($m['cond']) : null;

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
        $pattern = sprintf('/^(%s)=(\S+)$/', implode('|', self::CONDITION_ATTRIBUTES));

        if (1 !== preg_match($pattern, $condition, $m)) {
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
        $path = $this->containPath($relative);

        try {
            $format = Format::fromString(pathinfo($path, PATHINFO_EXTENSION));
        } catch (InvalidArgumentException $e) {
            throw new ResolutionException(sprintf('Cannot determine format of included file "%s".', $relative), 0, $e);
        }

        $resource = $this->resourceFactory->build($path, $format);

        if (!$this->reader->supports($resource)) {
            throw new ResolutionException(sprintf('Included configuration "%s" does not exist.', $relative));
        }

        $raw = $this->reader->read($resource);
        $document = $this->loaders->build($format)->load($raw);

        // Included nodes inherit the layer of the directive that pulled them in,
        // so layer attribution stays accurate across conditional includes (E3.3).
        $layerId = $document->layerId() ?? $directive->provenance()->layerId();

        $nodes = $this->normalizer->normalize($document, $directive->priority() + 1, 'include:' . $relative, $layerId);

        $parent = $directive->key();

        $pos = strrpos($parent, '.');

        if (false === $pos) {
            return $nodes;
        }

        $prefix = substr($parent, 0, $pos) . '.';

        return array_map(
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

    /**
     * Resolve an include target and guarantee it stays inside the base
     * directory: `..` segments, absolute paths and symlink escapes are all
     * rejected instead of being read.
     */
    private function containPath(string $relative): string
    {
        $base = realpath($this->baseDirectory);

        if (false === $base) {
            throw new ResolutionException(sprintf('Include base directory "%s" does not exist.', $this->baseDirectory));
        }

        $candidate = $base . \DIRECTORY_SEPARATOR . ltrim($relative, \DIRECTORY_SEPARATOR);
        $resolved = realpath($candidate);

        if (false === $resolved) {
            // The file itself need not exist yet (the reader reports missing
            // files); containment is decided on the deepest existing ancestor.
            $parent = realpath(dirname($candidate));
            $resolved = false === $parent
                ? false
                : $parent . \DIRECTORY_SEPARATOR . basename($candidate);
        }

        $contained = is_string($resolved)
            && ($resolved === $base || str_starts_with($resolved, $base . \DIRECTORY_SEPARATOR));

        if (!$contained) {
            throw new ResolutionException(sprintf('Include "%s" escapes the base directory.', $relative));
        }

        return $resolved;
    }
}
