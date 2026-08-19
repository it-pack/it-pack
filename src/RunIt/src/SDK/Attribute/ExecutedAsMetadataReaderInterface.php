<?php

declare(strict_types=1);

namespace ItPack\RunIt\SDK\Attribute;

/**
 * Resolves {@see ExecutedAs} metadata attached to a callable.
 *
 * Implementations may use reflection, but they must do so on the cold path:
 * the SDK calls {@see self::read()} when wrapping a callable, not on every
 * invocation. Results should be cached so repeated wrapping of the same target
 * performs no redundant reflection.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
interface ExecutedAsMetadataReaderInterface
{
    /**
     * Returns the execution metadata declared on the callable, or null when none is present.
     */
    public function read(callable $operation): ?ExecutedAs;
}
