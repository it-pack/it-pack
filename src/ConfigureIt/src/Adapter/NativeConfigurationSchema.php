<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Adapter;

use ItPack\ConfigureIt\Contract\ConfigurationGraphInterface;
use ItPack\ConfigureIt\Contract\ConfigurationSchemaInterface;
use ItPack\ConfigureIt\ValueObject\ValidationResult;
use Nette\Schema\Processor;
use Nette\Schema\Schema;
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
        private Schema $schema,
    ) {
    }

    public function validate(ConfigurationGraphInterface $graph): ValidationResult
    {
        $processor = new Processor();

        try {
            $processor->process($this->schema, $graph->toArray());
        } catch (ValidationException $e) {
            return new ValidationResult(false, $this->normalize($e));
        }

        return new ValidationResult(true);
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
                'message' => $message->message,
            ];
        }

        return $errors;
    }
}
