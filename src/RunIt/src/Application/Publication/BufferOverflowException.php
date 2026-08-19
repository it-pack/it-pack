<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use ItPack\RunIt\Contract\Exception\RunItExceptionInterface;

/**
 * Thrown when the buffer overflows under the `Fail` overflow strategy.
 *
 * The surrounding failure policy catches this and applies its own behavior. It is a
 * publication failure, never a business exception, so it must not replace the operation
 * result.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class BufferOverflowException extends \RuntimeException implements RunItExceptionInterface
{
}
