<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Exception;

use InvalidArgumentException;

/**
 * Default implementation of {@see SerializationExceptionInterface}.
 *
 * Raised by serializer ports when caller-supplied payload data contains values that cannot be
 * reduced to a recursively-primitive, deterministically-serializable structure.
 *
 * @api
 */
final class SerializationException extends InvalidArgumentException implements SerializationExceptionInterface
{
}
