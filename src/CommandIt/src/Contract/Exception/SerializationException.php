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
    public static function cyclic(): self
    {
        return new self('Cannot safely normalize cyclic data structure (detected a repeated object or self-referencing array).');
    }

    public static function tooDeep(int $maxDepth): self
    {
        return new self(\sprintf('Cannot safely normalize data nested deeper than %d levels.', $maxDepth));
    }
}
