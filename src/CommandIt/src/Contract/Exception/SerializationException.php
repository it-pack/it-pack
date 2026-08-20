<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Contract\Exception;

/**
 * Thrown when data cannot be safely normalized for serialization.
 *
 * @api
 */
final class SerializationException extends \RuntimeException implements SerializationExceptionInterface
{
}
