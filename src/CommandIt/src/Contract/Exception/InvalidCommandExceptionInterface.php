<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Contract\Exception;

/**
 * Marker for user-supplied command input that violates a domain validation rule.
 *
 * Lets consumers catch malformed input precisely without coupling to the
 * concrete {@see InvalidCommandException} class.
 *
 * @api
 */
interface InvalidCommandExceptionInterface extends CommandExceptionInterface
{
}
