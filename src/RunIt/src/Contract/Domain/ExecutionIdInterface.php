<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Domain;

/**
 * Contract for the identifier of an execution.
 *
 * An execution identifier is unique to exactly one execution and never
 * changes. Every artifact reports the execution it belongs to through this
 * identity rather than through an owning in-memory collection.
 *
 * An execution identifier is not a correlation identifier: correlation is
 * shared across related executions and may originate from a remote caller,
 * while an execution identifier is minted for one execution only.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#executionId
 */
interface ExecutionIdInterface extends StringIdentifierInterface
{
}
