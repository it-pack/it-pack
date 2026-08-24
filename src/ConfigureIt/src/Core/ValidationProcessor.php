<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Core;

use ItPack\ConfigureIt\Contract\ConfigurationGraphInterface;
use ItPack\ConfigureIt\Contract\ConfigurationProcessorInterface;
use ItPack\ConfigureIt\Contract\ConfigurationSchemaInterface;
use ItPack\ConfigureIt\Contract\ProvenanceAwareProcessorInterface;
use ItPack\ConfigureIt\Exception\ValidationException;
use ItPack\ConfigureIt\ValueObject\ProcessorStage;
use ItPack\ConfigureIt\ValueObject\ValidationMessage;

/**
 * Validates the graph against a schema as a discrete pipeline stage.
 *
 * Wraps a {@see ConfigurationSchemaInterface}; on failure it raises the domain
 * {@see ValidationException} with a path-indexed message, mirroring the resolver's
 * post-pipeline validation. This is the "Validation" stage; composing it lets
 * validation run between other processors (e.g. after casting) rather than only
 * at the end.
 *
 * @see docs/architecture/PIPELINE.md "Validation"
 */
final readonly class ValidationProcessor implements ConfigurationProcessorInterface, ProvenanceAwareProcessorInterface
{
    public function __construct(
        private ConfigurationSchemaInterface $schema,
    ) {
    }

    public function stageName(): string
    {
        return ProcessorStage::Validation->value;
    }

    public function process(ConfigurationGraphInterface $graph): ConfigurationGraphInterface
    {
        $result = $this->schema->validate($graph);

        if ($result->valid()) {
            $stage = ProcessorStage::Validation->value;

            $validated = new ConfigurationGraph();

            foreach ($graph->nodes() as $node) {
                $validated->put($node->withProvenance($node->provenance()->validated($stage)));
            }

            return $validated;
        }

        $lines = ['Configuration validation failed:'];
        $messages = [];

        foreach ($result->errors() as $error) {
            $messages[] = new ValidationMessage($error['path'], $error['message']);
            $lines[] = \sprintf('  %s: %s', $error['path'], $error['message']);
        }

        throw new ValidationException(\implode("\n", $lines), messages: $messages);
    }
}
