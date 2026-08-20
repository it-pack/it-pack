<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Domain;

use ItPack\CommandIt\Contract\Domain\InputKindInterface;

/**
 * The closed kind of a command input entry.
 *
 * @api
 */
enum InputKind: string implements InputKindInterface
{
    case Argument = 'argument';
    case Option = 'option';

    public function label(): string
    {
        return $this->value;
    }
}
