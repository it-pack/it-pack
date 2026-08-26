<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Core;

use function implode;

use ItPack\ConfigureIt\Contract\ConfigurationGraphInterface;
use ItPack\ConfigureIt\Contract\ConfigurationNodeInterface;
use ItPack\ConfigureIt\Contract\ConfigurationSchemaInterface;
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
 * @see docs/architecture/PIPELINE.md "Validation"
 */
final readonly class ValidationProcessor extends AbstractNodeRewritingProcessor
{
    public function __construct(
        private ConfigurationSchemaInterface $schema,
        ProcessorStage $stage = ProcessorStage::Validation,
    ) {
        parent::__construct($stage);
    }

    protected function before(ConfigurationGraphInterface $graph): ConfigurationGraphInterface
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

    protected function rewrite(ConfigurationNodeInterface $node): ConfigurationNodeInterface
    {
        return $node->withProvenance($node->provenance()->validated($this->stage->value));
    }
}
