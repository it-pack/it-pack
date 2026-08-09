<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Domain;

/**
 * Contract for the kind or category of an execution.
 *
 * Kinds are extensible strings, not a closed enum.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#executionKind
 */
interface ExecutionKindInterface extends StringValueInterface
{
}
