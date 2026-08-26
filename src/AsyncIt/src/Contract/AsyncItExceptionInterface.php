<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Contract;

/**
 * Marker contract for every AsyncIt-specific exception.
 *
 * Consumers can catch the contract without binding to a concrete failure type.
 * Concrete exceptions implement this interface and remain `final`.
 *
 * @see https://w3id.org/itpack/asyncit#Task
 */
interface AsyncItExceptionInterface
{
}
