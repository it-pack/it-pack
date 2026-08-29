<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Contract\Exception;

/**
 * Marker for "no handler registered for a command name".
 *
 * Lets consumers catch this configuration/registration error precisely without
 * coupling to the concrete {@see HandlerNotFoundException} class.
 *
 * @api
 */
interface HandlerNotFoundExceptionInterface extends CommandExceptionInterface
{
}
