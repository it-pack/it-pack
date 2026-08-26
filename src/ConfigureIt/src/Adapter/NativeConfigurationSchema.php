<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Adapter;

use ItPack\ConfigureIt\Contract\ConfigurationGraphInterface;
use ItPack\ConfigureIt\Contract\ConfigurationSchemaInterface;
use ItPack\ConfigureIt\Contract\DeclaresScalarTypesInterface;
use ItPack\ConfigureIt\Contract\ProcessorInterface;
use ItPack\ConfigureIt\Contract\SchemaInterface;
use ItPack\ConfigureIt\Contract\ValueObject\ValidationResultFactoryInterface;
use ItPack\ConfigureIt\Contract\ValueObject\ValidationResultInterface;
use ItPack\ConfigureIt\ValueObject\ValidationResult;
use ItPack\ConfigureIt\ValueObject\ValidationResultFactory;
use Nette\Schema\Elements\Structure;
use Nette\Schema\Elements\Type;
use Nette\Schema\Schema as Ne;
use Nette\Schema\ValidationException;
use ReflectionProperty;

/**
 * Validates a configuration graph against a Nette Schema definition.
 *
 * Isolates the `nette/schema` dependency behind the `ConfigurationSchema` port
 * (ADR-0002). The Core never sees Nette types; vendor exceptions are translated
 * into the domain {@see ValidationResult}.
 *
 * @see docs/architecture/MODEL.md "Configuration Schema"
 */
final readonly class NativeConfigurationSchema implements ConfigurationSchemaInterface, DeclaresScalarTypesInterface
{
    private SchemaInterface $schema;
    public function __construct(
        Ne                                         $schema,
        private ProcessorInterface               $processor = new Processor(),
        private ValidationResultFactoryInterface $resultFactory = new ValidationResultFactory(),
    ) {
        $this->schema = new Schema($schema);
    }

    public function validate(ConfigurationGraphInterface $graph): ValidationResultInterface
    {
        try {
            $this->processor->process($this->schema, $graph->toArray());
        } catch (ValidationException $e) {
            $issues = [
                ...$this->normalizeErrors($e),
                ...$this->normalizeWarnings($this->processor->getWarnings()),
            ];

            return $this->resultFactory->build(false, $issues);
        }

        $warnings = $this->normalizeWarnings($this->processor->getWarnings());

        // Warnings are non-blocking: a warning-only result is still valid.
        return $this->resultFactory->build(true, $warnings);
    }

    /**
     * Enumerate expected scalar types by introspecting the Nette definition.
     *
     * Walks {@see Structure} shapes recursively and maps {@see Type} elements to
     * the ConfigureIt scalar vocabulary. Unions ({@see AnyOf}) and open-ended
     * types (mixed, object) are intentionally omitted so the freeze-time
     * {@see \ItPack\ConfigureIt\Core\TypedSnapshot} only validates what is known.
     *
     * @return array<string, string>
     */
    public function scalarTypes(): array
    {
        return $this->walk($this->schema->getAdaptee(), '');
    }

    /**
     * @return array<string, string>
     */
    private function walk(Ne $schema, string $prefix): array
    {
        if ($schema instanceof Structure) {
            /** @var array<string, Ne> $items */
            $items = $this->readPrivate($schema, 'items');
            $map = [];

            foreach ($items as $name => $child) {
                $path = '' === $prefix ? $name : $prefix . '.' . $name;
                $map = [...$map, ...$this->walk($child, $path)];
            }

            return $map;
        }

        if ($schema instanceof Type) {
            if ('' === $prefix) {
                return [];
            }

            /** @var string $type */
            $type = $this->readPrivate($schema, 'type');
            $declared = $this->mapNetteType($type);

            return null === $declared ? [] : [$prefix => $declared];
        }

        return [];
    }

    private function mapNetteType(string $type): ?string
    {
        $type = preg_replace('/^null\|/', '', $type) ?? $type;

        return match ($type) {
            'int', 'float', 'bool', 'string' => $type,
            'list' => 'list',
            'array' => 'map',
            'email', 'unicode' => 'string',
            default => null,
        };
    }

    /**
     * @return mixed
     */
    private function readPrivate(object $object, string $property): mixed
    {
        $reflection = new ReflectionProperty($object, $property);

        return $reflection->getValue($object);
    }

    /**
     * @return list<array{path: string, message: string, severity: 'error'|'warning'}>
     */
    private function normalizeErrors(ValidationException $e): array
    {
        $errors = [];

        foreach ($e->getMessageObjects() as $message) {
            $path = implode('.', $message->path);

            $errors[] = [
                'path' => '' !== $path ? $path : '(root)',
                'message' => $message->toString(),
                'severity' => 'error',
            ];
        }

        return $errors;
    }

    /**
     * Nette keeps deprecations/soft-assertions in a separate warning channel,
     * surfaced after processing without aborting resolution.
     *
     * @param list<string> $warnings
     * @return list<array{path: string, message: string, severity: 'error'|'warning'}>
     */
    private function normalizeWarnings(array $warnings): array
    {
        return array_map(
            static fn (string $message): array => [
                'path' => '(root)',
                'message' => $message,
                'severity' => 'warning',
            ],
            $warnings,
        );
    }
}
