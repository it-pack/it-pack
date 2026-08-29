<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Contract\Exception;

/**
 * Marker for a command execution lifecycle rule violation (e.g. an illegal
 * transition or double completion).
 *
 * Lets consumers catch lifecycle violations precisely without coupling to the
 * concrete {@see CommandExecutionException} class.
 *
 * @api
 */
interface CommandExecutionExceptionInterface extends CommandExceptionInterface
{
}
