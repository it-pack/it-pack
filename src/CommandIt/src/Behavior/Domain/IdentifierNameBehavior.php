<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Behavior\Domain;

use ItPack\CommandIt\Contract\Exception\InvalidCommandException;

/**
 * Identifier-format rule shared by every CommandIt name value that is consumed
 * as an identifier (argument names, option names, and any future identifier
 * name). The regex and message live once; owners call {@see self::assertIdentifierName()}
 * from their {@see ValidatedStringValueBehavior::assertAdditionalRules()} hook.
 *
 * @internal
 */
trait IdentifierNameBehavior
{
    protected static function assertIdentifierName(string $value): void
    {
        if (preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*$/', $value) !== 1) {
            throw InvalidCommandException::malformed(
                'Name must be a valid identifier (letters, digits, underscore, hyphen), starting with a letter.',
            );
        }
    }
}
