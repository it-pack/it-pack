<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Execution;

use ItPack\RunIt\Behavior\Domain\ValidatedStringIdentifierBehavior;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;

/**
 * The identity of one execution.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#executionId
 */
final class ExecutionId implements ExecutionIdInterface
{
    use ValidatedStringIdentifierBehavior;
}
