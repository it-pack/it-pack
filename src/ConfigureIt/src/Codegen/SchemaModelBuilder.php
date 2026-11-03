<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Codegen;

use ItPack\ConfigureIt\Codegen\Model\DtoModel;
use ItPack\ConfigureIt\Codegen\Model\Property;
use Nette\Schema\Elements\Structure;
use Nette\Schema\Elements\Type;
use Nette\Schema\Schema as NetteSchema;
use ReflectionClass;
use ReflectionException;

/**
 * Walks a Nette Schema definition and produces the set of DTO models to generate.
 *
 * Mirrors the introspection in {@see \ItPack\ConfigureIt\Adapter\NativeConfigurationSchema}
 * but richer: it captures nested structures (each becomes its own DTO), list/map
 * element types, and scalar read methods. Relies on Nette element internals via
 * reflection — acceptable here because this is an adapter-side code generator.
 *
 * @see docs/architecture/MODEL.md "Typed Snapshot" (codegen companion, E2.3)
 */
final class SchemaModelBuilder
{
    /**
     * @return list<DtoModel>
     */
    public function build(NetteSchema $schema, string $rootClassName): array
    {
        $models = [];

        $root = $this->modelFor($schema, $rootClassName, $models, true);

        array_unshift($models, $root);

        return $models;
    }

    /**
     * @param list<DtoModel> $models collected nested models (mutated)
     */
    private function modelFor(NetteSchema $schema, string $ownerClass, array &$models, bool $isRoot = false): DtoModel
    {
        if ($schema instanceof Structure) {
            return $this->modelFromStructure($schema, $ownerClass, $models, $isRoot);
        }

        if ($schema instanceof Type) {
            /** @var string $type */
            $type = $this->readPrivate($schema, 'type');

            if ('array' === $type || 'list' === $type) {
                return $this->wrapScalar('value', 'list' === $type ? 'list' : 'map', 'mixed', 'get', $ownerClass);
            }

            $mapped = $this->mapScalar($type);

            return new DtoModel(
                $ownerClass,
                [new Property('value', 'scalar', $mapped[0], $mapped[1])],
                $isRoot,
            );
        }

        return new DtoModel(
            $ownerClass,
            [new Property('value', 'scalar', 'mixed', 'get')],
            $isRoot,
        );
    }

    /**
     * @param list<DtoModel> $models
     */
    private function modelFromStructure(
        Structure $structure,
        string $ownerClass,
        array &$models,
        bool $isRoot,
    ): DtoModel {
        /** @var array<string, NetteSchema> $items */
        $items = $this->readPrivate($structure, 'items');

        $properties = [];

        foreach ($items as $name => $child) {
            $properties[] = $this->propertyFor($name, $child, $ownerClass, $models);
        }

        return new DtoModel($ownerClass, $properties, $isRoot);
    }

    /**
     * @param list<DtoModel> $models
     */
    private function propertyFor(string $name, NetteSchema $child, string $ownerClass, array &$models): Property
    {
        if ($child instanceof Structure) {
            $nested = $ownerClass . $this->studly($name);
            $models[] = $this->modelFromStructure($child, $nested, $models, false);

            return new Property($name, 'structure', $nested, 'get', $nested);
        }

        if ($child instanceof Type) {
            /** @var string $type */
            $type = $this->readPrivate($child, 'type');

            if ('list' === $type || 'array' === $type) {
                return $this->propertyForCollection(
                    $name,
                    'list' === $type ? 'list' : 'map',
                    $child,
                    $ownerClass,
                    $models,
                );
            }

            $mapped = $this->mapScalar($type);

            return new Property($name, 'scalar', $mapped[0], $mapped[1]);
        }

        return new Property($name, 'scalar', 'mixed', 'get');
    }

    /**
     * @param list<DtoModel> $models
     */
    private function propertyForCollection(
        string $name,
        string $kind,
        Type $child,
        string $ownerClass,
        array &$models,
    ): Property {
        $itemsValue = $this->readPrivate($child, 'itemsValue');

        if ($itemsValue instanceof Structure) {
            $nested = $ownerClass . $this->studly($name) . 'Item';
            $models[] = $this->modelFromStructure($itemsValue, $nested, $models, false);

            return new Property(
                $name,
                $kind,
                'list' === $kind ? "array<int, $nested>" : "array<string, $nested>",
                'get',
                $nested,
            );
        }

        if ($itemsValue instanceof Type) {
            /** @var string $elementType */
            $elementType = $this->readPrivate($itemsValue, 'type');
            $mapped = $this->mapScalar($elementType);

            return new Property(
                $name,
                $kind,
                'list' === $kind ? "array<int, {$mapped[0]}>" : "array<string, {$mapped[0]}>",
                'get',
                null,
                $mapped[0],
                $mapped[1],
            );
        }

        return new Property(
            $name,
            $kind,
            'list' === $kind ? 'array<int, mixed>' : 'array<string, mixed>',
            'get',
            null,
            'mixed',
            'get',
        );
    }

    /**
     * @return array{0: string, 1: string} [phpType, readMethod]
     */
    private function mapScalar(string $type): array
    {
        return match ($type) {
            'int' => ['int', 'int'],
            'float' => ['float', 'float'],
            'bool' => ['bool', 'bool'],
            'string' => ['string', 'string'],
            'email', 'unicode' => ['string', 'string'],
            default => ['mixed', 'get'],
        };
    }

    private function wrapScalar(
        string $name,
        string $kind,
        string $phpType,
        string $readMethod,
        string $ownerClass,
    ): DtoModel {
        return new DtoModel(
            $ownerClass,
            [
                new Property(
                    $name,
                    $kind,
                    'list' === $kind ? "array<int, $phpType>" : "array<string, $phpType>",
                    $readMethod,
                ),
            ],
            false,
        );
    }

    private function studly(string $name): string
    {
        return str_replace(' ', '', ucwords(str_replace(['_', '-', '.'], ' ', $name)));
    }

    /**
     * @return mixed
     */
    private function readPrivate(object $object, string $property): mixed
    {
        $reflection = new ReflectionClass($object);
        do {
            try {
                $prop = $reflection->getProperty($property);
                $prop->setAccessible(true);

                return $prop->getValue($object);
            } catch (ReflectionException) {
                // property not declared on this class; keep walking up the hierarchy
            }
        } while ($reflection = $reflection->getParentClass());

        throw new ReflectionException(
            'Property ' . $property . ' does not exist on ' . $object::class,
        );
    }
}
