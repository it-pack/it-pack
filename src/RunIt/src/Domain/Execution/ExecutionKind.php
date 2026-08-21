<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Execution;

use ItPack\RunIt\Behavior\Domain\ValidatedStringValueBehavior;
use ItPack\RunIt\Contract\Domain\ExecutionKindInterface;

/**
 * The kind or category of an execution, for example `http.request`,
 * `cli.command`, or `configuration.resolution`.
 *
 * Kinds are open, validated strings rather than a closed enum: new kinds
 * appear as the ecosystem grows and the domain must not change to accept them.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#executionKind
 */
final class ExecutionKind implements ExecutionKindInterface
{
    use ValidatedStringValueBehavior;
}
