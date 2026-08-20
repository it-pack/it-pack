<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Contract\ValueObject;

/**
 * The validated, extensible name of a command.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#commandName
 */
interface CommandNameInterface extends ValidatedStringInterface
{
}
