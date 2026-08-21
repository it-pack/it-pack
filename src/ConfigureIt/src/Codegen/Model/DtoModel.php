<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Codegen\Model;

/**
 * A generated DTO: a class name plus its ordered properties.
 *
 * @see \ItPack\ConfigureIt\Codegen\SchemaModelBuilder
 */
final class DtoModel
{
    /**
     * @param list<Property> $properties
     */
    public function __construct(
        public string  $className,
        public array   $properties,
        public bool    $root = false,
    ) {
    }
}
