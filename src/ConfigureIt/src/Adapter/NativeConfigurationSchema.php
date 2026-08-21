<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Adapter;

use ItPack\ConfigureIt\Contract\ConfigurationGraphInterface;
use ItPack\ConfigureIt\Contract\ConfigurationSchemaInterface;
use ItPack\ConfigureIt\Contract\ProcessorInterface;
use ItPack\ConfigureIt\Contract\SchemaInterface;
use ItPack\ConfigureIt\Contract\ValueObject\ValidationResultFactoryInterface;
use ItPack\ConfigureIt\Contract\ValueObject\ValidationResultInterface;
use ItPack\ConfigureIt\ValueObject\ValidationResult;
use ItPack\ConfigureIt\ValueObject\ValidationResultFactory;
use Nette\Schema\Schema as Ne;
use Nette\Schema\ValidationException;

/**
 * Validates a configuration graph against a Nette Schema definition.
 *
 * Isolates the `nette/schema` dependency behind the `ConfigurationSchema` port
 * (ADR-0002). The Core never sees Nette types; vendor exceptions are translated
 * into the domain {@see ValidationResult}.
 *
 * @see docs/architecture/MODEL.md "Configuration Schema"
 */
final readonly class NativeConfigurationSchema implements ConfigurationSchemaInterface
{
    public function __construct(
        Ne                                         $schema,
        private ProcessorInterface               $processor = new Processor(),
        private ValidationResultFactoryInterface $resultFactory = new ValidationResultFactory(),
    ) {
        $this->schema = new Schema($schema);
    }

    private SchemaInterface $schema;

    public function validate(ConfigurationGraphInterface $graph): ValidationResultInterface
    {
        try {
            $this->processor->process($this->schema, $graph->toArray());
        } catch (ValidationException $e) {
            return $this->resultFactory->build(false, $this->normalize($e));
        }

        return $this->resultFactory->build(true);
    }

    /**
     * @return list<array{path: string, message: string}>
     */
    private function normalize(ValidationException $e): array
    {
        $errors = [];

        foreach ($e->getMessageObjects() as $message) {
            $path = implode('.', $message->path);

            $errors[] = [
                'path' => $path !== '' ? $path : '(root)',
                'message' => $message->toString(),
            ];
        }

        return $errors;
    }
}
