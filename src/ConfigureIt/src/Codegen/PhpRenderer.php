<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Codegen;

use ItPack\ConfigureIt\Codegen\Model\DtoModel;
use ItPack\ConfigureIt\Codegen\Model\Property;

/**
 * Renders DTO models into a single, self-contained PHP source file.
 *
 * Each DTO gets a `readonly` class with typed constructor-promoted properties and
 * factory/serialization methods:
 *  - `fromSnapshot()` (root entry only) and `fromSnapshotAt()` (reads every
 *    property relative to a dotted base path), so nested and list/map-of-structure
 *    elements reuse the same reader without passing subtrees;
 *  - `toArray()` for a plain, statically-analyzable view of the values;
 *  - `implements GeneratedConfigDtoInterface` so hydrators can return a known type.
 *
 * Collection properties are declared as `array` at runtime (PHP has no generic
 * `array<T>` syntax) with a `@var array<...>` PHPDoc so static analysis still
 * sees the precise element types. Maps preserve their string keys via
 * `array_combine` (plain `array_map` would reindex and drop them).
 *
 * @see docs/architecture/MODEL.md "Typed Snapshot" (codegen companion, E2.3)
 */
final class PhpRenderer
{
    /**
     * @param list<DtoModel> $models
     */
    public function render(array $models, string $namespace): string
    {
        $blocks = [];

        foreach ($models as $model) {
            $blocks[] = $this->renderClass($model);
        }

        return sprintf(
            "<?php\n\ndeclare(strict_types=1);\n\nnamespace %s;\n\nuse %s;\nuse %s;\n\n%s\n",
            $namespace,
            'ItPack\\ConfigureIt\\Contract\\ConfigurationSnapshotInterface',
            'ItPack\\ConfigureIt\\Codegen\\GeneratedConfigDtoInterface',
            implode("\n\n", $blocks),
        );
    }

    private function renderClass(DtoModel $model): string
    {
        $params = [];
        $reads = [];

        foreach ($model->properties as $property) {
            $params[] = $this->paramLine($property);
            $reads[] = sprintf(
                '            %s: %s,',
                $property->name,
                $this->readExpression($property),
            );
        }

        $constructor = implode("\n", $params);
        $body = implode("\n", $reads);
        $toArray = $this->toArrayList($model);

        $factory = $model->root
            ? "\n\n    public static function fromSnapshot(ConfigurationSnapshotInterface \$s): self\n" .
              "    {\n        return self::fromSnapshotAt(\$s, '');\n    }"
            : '';

        return sprintf(
            "final readonly class %s implements GeneratedConfigDtoInterface\n{\n    public function __construct(\n%s\n    ) {}\n\n" .
            "    public static function fromSnapshotAt(ConfigurationSnapshotInterface \$s, string \$base): self\n" .
            "    {\n        return new self(\n%s\n        );\n    }\n\n" .
            "    public function toArray(): array\n" .
            "    {\n        return [\n%s\n        ];\n    }%s\n}",
            $model->className,
            $constructor,
            $body,
            $toArray,
            $factory,
        );
    }

    private function paramLine(Property $property): string
    {
        if (str_starts_with($property->phpType, 'array')) {
            return sprintf('        /** @var %s */', $property->phpType)
                . "\n        public array $" . $property->name . ',';
        }

        return sprintf('        public %s $%s,', $property->phpType, $property->name);
    }

    /**
     * @return string
     */
    private function toArrayList(DtoModel $model): string
    {
        $lines = [];

        foreach ($model->properties as $property) {
            $lines[] = sprintf("            '%s' => %s,", $property->name, $this->toArrayValue($property));
        }

        return implode("\n", $lines);
    }

    private function toArrayValue(Property $property): string
    {
        if ($property->nestedClass !== null) {
            if ($property->kind === 'structure') {
                return sprintf('$this->%s->toArray()', $property->name);
            }

            return sprintf('array_map(static fn ($v) => $v->toArray(), $this->%s)', $property->name);
        }

        return sprintf('$this->%s', $property->name);
    }

    private function readExpression(Property $property): string
    {
        $path = sprintf(
            "\$base === '' ? %s : \$base . %s",
            var_export($property->name, true),
            var_export('.' . $property->name, true),
        );

        return match ($property->kind) {
            'scalar' => sprintf('$s->%s(%s)', $property->readMethod, $path),
            'structure' => sprintf('%s::fromSnapshotAt($s, %s)', $property->nestedClass, $path),
            'list', 'map' => $this->collectionExpression($property, $path),
            default => throw new \InvalidArgumentException(sprintf('Unknown property kind "%s".', $property->kind)),
        };
    }

    private function collectionExpression(Property $property, string $path): string
    {
        if ($property->nestedClass !== null) {
            if ($property->kind === 'list') {
                return sprintf(
                    'array_map(static fn (int $i) => %s::fromSnapshotAt($s, (%s) . \'.\' . $i), array_keys($s->list(%s)))',
                    $property->nestedClass,
                    $path,
                    $path,
                );
            }

            return sprintf(
                'array_combine(array_keys($s->get(%s)), array_map(static fn (string $k) => %s::fromSnapshotAt($s, (%s) . \'.\' . $k), array_keys($s->get(%s))))',
                $path,
                $property->nestedClass,
                $path,
                $path,
            );
        }

        $elementType = $property->elementType ?? 'mixed';

        if ($property->kind === 'list') {
            return sprintf(
                'array_map(static fn (mixed $v): %s => %s, $s->list(%s))',
                $elementType,
                $this->scalarCast($elementType, $property->elementRead),
                $path,
            );
        }

        $cast = $property->elementRead === 'get' || $property->elementRead === null
            ? sprintf('$s->get(%s)[$k]', $path)
            : sprintf('(%s) $s->get(%s)[$k]', $elementType, $path);

        return sprintf(
            'array_combine(array_keys($s->get(%s)), array_map(static fn (string $k): %s => %s, array_keys($s->get(%s))))',
            $path,
            $elementType,
            $cast,
            $path,
        );
    }

    private function scalarCast(string $phpType, ?string $readMethod): string
    {
        if ($readMethod === 'get' || $readMethod === null) {
            return '$v';
        }

        return sprintf('(%s) $v', $phpType);
    }
}
