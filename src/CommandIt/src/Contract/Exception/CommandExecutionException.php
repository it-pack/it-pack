<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Contract\Exception;

/**
 * Thrown when a command execution lifecycle rule is violated, for example
 * transitioning a terminal execution or completing it more than once.
 *
 * @api
 */
final class CommandExecutionException extends \LogicException implements CommandExceptionInterface
{
}
