<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Domain\ValueObject;

use ItPack\CommandIt\Behavior\Domain\ValidatedStringValueBehavior;
use ItPack\CommandIt\Contract\ValueObject\CommandExecutionIdInterface;

/**
 * The stable identifier of a single command execution.
 *
 * @api
 */
final readonly class CommandExecutionId implements CommandExecutionIdInterface
{
    use ValidatedStringValueBehavior;
}
