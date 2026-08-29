<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Domain;

use ItPack\CommandIt\Behavior\Domain\LabelableBehavior;
use ItPack\CommandIt\Contract\Domain\InputKindInterface;

/**
 * The closed kind of a command input entry.
 *
 * @api
 */
enum InputKind: string implements InputKindInterface
{
    use LabelableBehavior;

    case Argument = 'argument';
    case Option = 'option';
}
