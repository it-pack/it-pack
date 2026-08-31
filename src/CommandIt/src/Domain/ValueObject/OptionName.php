<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Domain\ValueObject;

use ItPack\CommandIt\Behavior\Domain\IdentifierNameBehavior;
use ItPack\CommandIt\Behavior\Domain\ValidatedStringValueBehavior;
use ItPack\CommandIt\Contract\ValueObject\OptionNameInterface;

/**
 * The validated name of a command option.
 *
 * Option names are identifiers consumed by handlers and surfaced through the
 * CLI adapter, so they follow a stricter identifier format than free-form names.
 *
 * @api
 */
final readonly class OptionName implements OptionNameInterface
{
    use ValidatedStringValueBehavior;
    use IdentifierNameBehavior;

    protected static function assertAdditionalRules(string $value): void
    {
        self::assertIdentifierName($value);
    }
}
