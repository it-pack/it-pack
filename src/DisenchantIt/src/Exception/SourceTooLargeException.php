<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Exception;

use ItPack\DisenchantIt\Contract\DisenchantItExceptionInterface;

/**
 * Thrown when a source string exceeds the configured size bound before parsing.
 * Bounding input size keeps a single maliciously/accidentally huge file from
 * exhausting memory in the (expensive) parse+traverse step.
 *
 * @see \ItPack\DisenchantIt\Contract\Detection\StringLiteralDetectorInterface::MAX_SOURCE_BYTES
 */
final class SourceTooLargeException extends \RuntimeException implements DisenchantItExceptionInterface
{
    public function __construct(int $actualBytes, int $maxBytes, ?\Throwable $previous = null)
    {
        parent::__construct(
            sprintf(
                'Source is %d bytes, exceeding the configured limit of %d bytes.',
                $actualBytes,
                $maxBytes,
            ),
            0,
            $previous,
        );
    }
}
