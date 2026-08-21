<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Execution;

use ItPack\RunIt\Behavior\Domain\ValidatedStringValueBehavior;
use ItPack\RunIt\Contract\Domain\ExecutionNameInterface;

/**
 * The human-readable name of an execution, for example `configuration.resolve`.
 *
 * Names are free-form and extensible: a consumer supplies whatever name
 * describes the work. They are validated for safety (no whitespace, control
 * characters, or unbounded length) but never constrained to a fixed set.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#executionName
 */
final class ExecutionName implements ExecutionNameInterface
{
    use ValidatedStringValueBehavior;
}
