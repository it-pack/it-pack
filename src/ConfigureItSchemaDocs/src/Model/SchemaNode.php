<?php

declare(strict_types=1);

namespace ItPack\ConfigureItSchemaDocs\Model;

/**
 * Normalised, framework-independent view of a Nette Schema definition.
 *
 * The introspector ({@see \ItPack\ConfigureItSchemaDocs\SchemaIntrospector})
 * walks the `Nette\Schema\Expect` graph (via reflection, because the element
 * classes keep their shape private) and produces a tree of these nodes. Renderers
 * then turn the tree into Markdown or JSON-Schema without ever touching Nette
 * types again.
 */
final class SchemaNode
{
    public const KIND_OBJECT = 'object';
    public const KIND_ARRAY = 'array';
    public const KIND_SCALAR = 'scalar';
    public const KIND_ENUM = 'enum';
    public const KIND_UNION = 'union';

    /** @var array<string, self> */
    public array $properties;

    /** @var list<self> */
    public array $anyOf;

    /** @var list<mixed> */
    public array $enumValues;

    /**
     * @param array{0:?float,1:?float}|null $range
     * @param array<string, self>           $properties
     * @param list<self>                    $anyOf
     * @param list<mixed>                   $enumValues
     */
    public function __construct(
        public string $kind,
        public ?string $type,
        public bool $nullable = false,
        public mixed $default = null,
        public bool $hasDefault = false,
        public bool $required = false,
        public bool $deprecated = false,
        public ?string $description = null,
        public ?array $range = null,
        public ?string $pattern = null,
        array $properties = [],
        public ?self $additionalProperties = null,
        public ?self $items = null,
        array $enumValues = [],
        array $anyOf = [],
    ) {
        /* @var array<string, self> $properties */
        $this->properties = $properties;
        /* @var list<mixed> $enumValues */
        $this->enumValues = $enumValues;
        /* @var list<self> $anyOf */
        $this->anyOf = $anyOf;
    }
}
