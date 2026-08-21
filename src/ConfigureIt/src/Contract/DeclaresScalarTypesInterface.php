<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Contract;

/**
 * Optional capability of a {@see ConfigurationSchemaInterface} that can enumerate
 * the scalar types it expects for individual dotted keys.
 *
 * Schemas validate the whole graph; this interface additionally lets the
 * resolver mint a {@see \ItPack\ConfigureIt\Core\TypedSnapshot} that fails fast
 * at freeze time when a declared leaf does not conform. Implemented by adapters
 * that can introspect their underlying definition (e.g. Nette Schema).
 */
interface DeclaresScalarTypesInterface
{
    /**
     * @return array<string, string> dotted path => 'int' | 'float' | 'bool' | 'string' | 'list' | 'map'
     */
    public function scalarTypes(): array;
}
