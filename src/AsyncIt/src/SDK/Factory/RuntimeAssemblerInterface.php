<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\SDK\Factory;

use ItPack\AsyncIt\Application\Factory\RuntimeAdapterAssemblerInterface;
use ItPack\AsyncIt\SDK\AsyncRuntimeInterface;

/**
 * Contract for the reference-runtime composition root.
 *
 * @see https://w3id.org/itpack/asyncit#RuntimeAdapter
 * @see https://w3id.org/itpack/runit#Execution
 */
interface RuntimeAssemblerInterface
{
    public static function create(
        ?RuntimeAdapterAssemblerInterface $assembler = null,
    ): AsyncRuntimeInterface;
}
