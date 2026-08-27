<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Core;

use function implode;

use ItPack\ConfigureIt\Contract\ConfigurationGraphInterface;
use ItPack\ConfigureIt\Contract\ConfigurationGraphRewriterInterface;
use ItPack\ConfigureIt\Contract\ConfigurationNodeInterface;
use ItPack\ConfigureIt\Contract\ConfigurationProcessorInterface;
use ItPack\ConfigureIt\Contract\ConfigurationSchemaInterface;
use ItPack\ConfigureIt\Contract\ProvenanceAwareProcessorInterface;
use ItPack\ConfigureIt\Exception\ValidationException;
use ItPack\ConfigureIt\ValueObject\ProcessorStage;
use ItPack\ConfigureIt\ValueObject\ValidationMessage;

use function sprintf;

/**
 * Validates the graph against a schema as a discrete pipeline stage.
 *
 * Wraps a {@see ConfigurationSchemaInterface}; on failure it raises the domain
 * {@see ValidationException} with a path-indexed message, mirroring the resolver's
 * post-pipeline validation. This is the "Validation" stage; composing it lets
 * validation run between other processors (e.g. after casting) rather than only
 * at the end.
 *
 * The immutable per-node rewrite loop (and the optional whole-graph
 * {@see self::before()} hook) is delegated to an injected
 * {@see ConfigurationGraphRewriter} collaborator rather than an abstract base
 * class (see docs/architecture/decisions/ADR-0011-Rewriter-Decomposition.md).
 *
 * @see docs/architecture/PIPELINE.md "Validation"
 */
final readonly class ValidationProcessor implements
    ConfigurationProcessorInterface,
    ProvenanceAwareProcessorInterface
{
    public function __construct(
        private ConfigurationSchemaInterface $schema,
        private ConfigurationGraphRewriterInterface $rewriter,
        private ProcessorStage $stage = ProcessorStage::Validation,
    ) {
    }

    public function name(): string
    {
        return 'validation-processor';
    }

    public function stageName(): string
    {
        return $this->stage->value;
    }

    public function process(ConfigurationGraphInterface $graph): ConfigurationGraphInterface
    {
        return $this->rewriter->rewrite(
            $graph,
            fn (ConfigurationNodeInterface $node): ConfigurationNodeInterface => $this->rewriteNode($node),
            fn (ConfigurationGraphInterface $graph): ConfigurationGraphInterface => $this->before($graph),
        );
    }

    private function before(ConfigurationGraphInterface $graph): ConfigurationGraphInterface
    {
        $result = $this->schema->validate($graph);

        if ($result->valid()) {
            return $graph;
        }

        $lines = ['Configuration validation failed:'];
        $messages = [];

        foreach ($result->errors() as $error) {
            $messages[] = new ValidationMessage($error['path'], $error['message']);
            $lines[] = sprintf('  %s: %s', $error['path'], $error['message']);
        }

        throw new ValidationException(implode("\n", $lines), messages: $messages);
    }

    private function rewriteNode(ConfigurationNodeInterface $node): ConfigurationNodeInterface
    {
        return $node->withProvenance($node->provenance()->validated($this->stage->value));
    }
}
