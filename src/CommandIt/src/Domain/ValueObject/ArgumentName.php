<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Domain\ValueObject;

use ItPack\CommandIt\Behavior\Domain\ValidatedStringValueBehavior;
use ItPack\CommandIt\Contract\Exception\InvalidCommandException;
use ItPack\CommandIt\Contract\ValueObject\ArgumentNameInterface;

/**
 * The validated name of a command argument.
 *
 * Argument names are identifiers consumed by handlers and surfaced through the
 * CLI adapter, so they follow a stricter identifier format than free-form names.
 *
 * @api
 */
final readonly class ArgumentName implements ArgumentNameInterface
{
    use ValidatedStringValueBehavior;

    protected static function assertAdditionalRules(string $value): void
    {
        if (preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*$/', $value) !== 1) {
            throw InvalidCommandException::malformed(
                'Argument name must be a valid identifier (letters, digits, underscore, hyphen), starting with a letter.',
            );
        }
    }
}
