<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Codegen\Model;

/**
 * A single property of a generated DTO, derived from one schema element.
 *
 * @see \ItPack\ConfigureIt\Codegen\SchemaModelBuilder
 */
final class Property
{
    /**
     * @param string      $kind        one of 'scalar' | 'structure' | 'list' | 'map'
     * @param string      $phpType     declaration type (e.g. 'int', 'AppConfigDb', 'array<int, int>')
     * @param string      $readMethod  snapshot accessor for scalar kinds ('int'|'float'|'bool'|'string'|'get'|'list')
     * @param string|null $nestedClass DTO class for structure / list-of-structure / map-of-structure elements
     * @param string|null $elementType PHP type of list/map elements
     * @param string|null $elementRead scalar read method for list/map elements (null when element is a structure)
     */
    public function __construct(
        public string  $name,
        public string  $kind,
        public string  $phpType,
        public string  $readMethod,
        public ?string $nestedClass = null,
        public ?string $elementType = null,
        public ?string $elementRead = null,
    ) {
    }
}
