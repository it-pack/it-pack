<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Domain;

use ItPack\CommandIt\Behavior\Domain\LabelableBehavior;
use ItPack\CommandIt\Contract\Domain\CommandResultStatusInterface;

/**
 * The closed outcome status of a command result.
 *
 * @api
 */
enum CommandResultStatus: string implements CommandResultStatusInterface
{
    use LabelableBehavior;

    case Succeeded = 'succeeded';
    case Failed = 'failed';
}
