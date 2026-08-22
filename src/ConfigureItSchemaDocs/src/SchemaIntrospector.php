<?php

declare(strict_types=1);

namespace ItPack\ConfigureItSchemaDocs;

use ItPack\ConfigureItSchemaDocs\Model\SchemaNode;
use Nette\Schema\DynamicParameter;
use Nette\Schema\Elements\AnyOf;
use Nette\Schema\Elements\Structure;
use Nette\Schema\Elements\Type;
use Nette\Schema\Schema;
use ReflectionProperty;

/**
 * Reflects a `Nette\Schema\Expect` definition into a {@see SchemaNode} tree.
 *
 * Nette keeps the element shape private (the `Elements\*` classes expose no
 * public accessors for `type`, `items`, `default`, ...), so we read them through
 * {@see \ReflectionProperty}. This is intentionally the same boundary-crossing
 * the Core already does in `NativeConfigurationSchema::scalarTypes()` — the
 * introspector lives in an adapter and never lets Nette types leak into the
 * rendered output.
 */
final class SchemaIntrospector
{
    /** @var array<int, true> SPL object ids currently on the traversal stack (cycle guard). */
    private array $seen = [];

    public function introspect(Schema $schema): SchemaNode
    {
        $this->seen = [];

        return $this->walk($schema);
    }

    private function walk(Schema $schema): SchemaNode
    {
        $id = spl_object_id($schema);

        if (isset($this->seen[$id])) {
            // Recursive definition: stop descent and render the cycle as opaque.
            return new SchemaNode(SchemaNode::KIND_SCALAR, 'mixed');
        }

        $this->seen[$id] = true;

        try {
            if ($schema instanceof Structure) {
                return $this->walkStructure($schema);
            }

            if ($schema instanceof AnyOf) {
                return $this->walkAnyOf($schema);
            }

            if ($schema instanceof Type) {
                return $this->walkType($schema);
            }

            return new SchemaNode(SchemaNode::KIND_SCALAR, 'mixed');
        } finally {
            unset($this->seen[$id]);
        }
    }

    private function walkStructure(Structure $schema): SchemaNode
    {
        /** @var array<string, Schema> $items */
        $items = $this->readPrivate($schema, 'items');

        $properties = [];
        $required = [];

        foreach ($items as $name => $child) {
            $node = $this->walk($child);
            $node->required = $this->isRequired($child);
            $properties[$name] = $node;

            if ($node->required) {
                $required[] = $name;
            }
        }

        /** @var Schema|null $other */
        $other = $this->readPrivate($schema, 'otherItems');

        $node = new SchemaNode(
            kind: SchemaNode::KIND_OBJECT,
            type: 'object',
            properties: $properties,
            additionalProperties: null === $other ? null : $this->walk($other),
        );

        $node->required = true === $this->readPrivate($schema, 'required');

        /** @var string|null $deprecated */
        $deprecated = $this->readPrivate($schema, 'deprecated');
        $node->deprecated = is_string($deprecated) && '' !== $deprecated;

        return $node;
    }

    private function walkType(Type $schema): SchemaNode
    {
        /** @var string $rawType */
        $rawType = $this->readPrivate($schema, 'type');
        [$baseType, $nullable] = $this->normalizeType($rawType);

        $node = new SchemaNode(
            kind: SchemaNode::KIND_SCALAR,
            type: $baseType,
            nullable: $nullable,
        );

        /** @var Schema|null $itemsValue */
        $itemsValue = $this->readPrivate($schema, 'itemsValue');

        if ($itemsValue instanceof Schema) {
            $child = $this->walk($itemsValue);

            if ('array' === $baseType) {
                $node->kind = SchemaNode::KIND_ARRAY;

                if ($this->isListType($rawType)) {
                    $node->items = $child;
                } else {
                    $node->additionalProperties = $child;
                }
            }
        }

        /** @var array{0:?float,1:?float} $range */
        $range = $this->readPrivate($schema, 'range');

        if (null !== $range[0] || null !== $range[1]) {
            $node->range = $range;
        }

        /** @var string|null $pattern */
        $pattern = $this->readPrivate($schema, 'pattern');

        if (is_string($pattern)) {
            $node->pattern = $pattern;
        }

        $node->default = $this->readPrivate($schema, 'default');
        $node->hasDefault = true;
        $node->required = true === $this->readPrivate($schema, 'required');

        /** @var string|null $deprecated */
        $deprecated = $this->readPrivate($schema, 'deprecated');
        $node->deprecated = is_string($deprecated) && '' !== $deprecated;

        return $node;
    }

    private function walkAnyOf(AnyOf $schema): SchemaNode
    {
        /** @var array<int, mixed> $set */
        $set = $this->readPrivate($schema, 'set');

        $scalars = [];
        $schemas = [];

        foreach ($set as $member) {
            if ($member instanceof Schema) {
                $schemas[] = $this->walk($member);
            } else {
                $scalars[] = $member;
            }
        }

        $nullable = false;

        if (in_array(null, $scalars, true)) {
            $nullable = true;
            $scalars = array_values(array_filter(
                $scalars,
                static fn (mixed $value): bool => null !== $value,
            ));
        }

        if ([] === $schemas && [] !== $scalars) {
            $node = new SchemaNode(
                kind: SchemaNode::KIND_ENUM,
                type: $this->commonScalarType($scalars),
                nullable: $nullable,
                enumValues: $scalars,
            );

            $node->default = $this->readPrivate($schema, 'default');
            $node->hasDefault = true;
            $node->required = true === $this->readPrivate($schema, 'required');

            /** @var string|null $deprecated */
            $deprecated = $this->readPrivate($schema, 'deprecated');
            $node->deprecated = is_string($deprecated) && '' !== $deprecated;

            return $node;
        }

        $anyOf = $schemas;

        if ([] !== $scalars) {
            $anyOf[] = new SchemaNode(
                kind: SchemaNode::KIND_ENUM,
                type: $this->commonScalarType($scalars),
                enumValues: $scalars,
            );
        }

        $node = new SchemaNode(
            kind: SchemaNode::KIND_UNION,
            type: null,
            nullable: $nullable,
            anyOf: $anyOf,
        );

        $node->default = $this->readPrivate($schema, 'default');
        $node->hasDefault = true;
        $node->required = true === $this->readPrivate($schema, 'required');

        /** @var string|null $deprecated */
        $deprecated = $this->readPrivate($schema, 'deprecated');
        $node->deprecated = is_string($deprecated) && '' !== $deprecated;

        return $node;
    }

    /**
     * @return array{0:?string,1:bool}
     */
    private function normalizeType(string $raw): array
    {
        $type = $raw;
        $prefix = preg_quote(DynamicParameter::class, '/');
        $type = preg_replace('/^'.$prefix.'\|/', '', $type) ?? $type;

        $nullable = false;

        if (str_starts_with($type, 'null|')) {
            $nullable = true;
            $type = substr($type, 5);
        }

        $base = match ($type) {
            'bool' => 'boolean',
            'int' => 'integer',
            'float' => 'number',
            'string', 'email', 'unicode' => 'string',
            'list', 'array' => 'array',
            'null' => 'null',
            default => null,
        };

        return [$base, $nullable];
    }

    private function isListType(string $raw): bool
    {
        return 'list' === $raw || str_starts_with($raw, 'list|');
    }

    /**
     * @param list<mixed> $scalars
     */
    private function commonScalarType(array $scalars): ?string
    {
        $types = [];

        foreach ($scalars as $scalar) {
            $types[] = $this->scalarJsonType($scalar);
        }

        $types = array_values(array_unique(array_filter($types)));

        return 1 === count($types) ? $types[0] : null;
    }

    private function scalarJsonType(mixed $value): ?string
    {
        return match (gettype($value)) {
            'string' => 'string',
            'integer' => 'integer',
            'double' => 'number',
            'boolean' => 'boolean',
            default => null,
        };
    }

    private function isRequired(Schema $schema): bool
    {
        return true === $this->readPrivate($schema, 'required');
    }

    private function readPrivate(object $object, string $property): mixed
    {
        // Private properties are readable via reflection without setAccessible()
        // since PHP 8.1; the call is a deprecated no-op on PHP 8.5+.
        $reflection = new ReflectionProperty($object, $property);

        return $reflection->getValue($object);
    }
}
