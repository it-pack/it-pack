<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Domain\ValueObject;

use ItPack\CommandIt\Behavior\Domain\ValidatedStringValueBehavior;
use ItPack\CommandIt\Contract\ValueObject\ValidatedStringInterface;

/**
 * A generic validated, immutable string.
 *
 * @api
 */
final readonly class ValidatedString implements ValidatedStringInterface
{
    use ValidatedStringValueBehavior;
}
