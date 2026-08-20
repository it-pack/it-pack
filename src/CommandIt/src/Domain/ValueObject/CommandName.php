<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Domain\ValueObject;

use ItPack\CommandIt\Behavior\Domain\ValidatedStringValueBehavior;
use ItPack\CommandIt\Contract\Exception\InvalidCommandException;
use ItPack\CommandIt\Contract\ValueObject\CommandNameInterface;

/**
 * The validated, extensible name of a command, for example `cache:clear`.
 *
 * Names are free-form and extensible: a consumer supplies whatever name
 * describes the work. They are validated for safety (no whitespace, control
 * characters, or unbounded length) but never constrained to a fixed set.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#commandName
 */
final readonly class CommandName implements CommandNameInterface
{
    use ValidatedStringValueBehavior;

    protected static function assertAdditionalRules(string $value): void
    {
        if (preg_match('/^[a-zA-Z][a-zA-Z0-9_.:-]*$/', $value) !== 1) {
            throw InvalidCommandException::malformed(
                'Command name must be a valid identifier, optionally namespaced with dots or colons.',
            );
        }
    }
}
