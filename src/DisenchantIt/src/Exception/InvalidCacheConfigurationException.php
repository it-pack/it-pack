<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Exception;

use InvalidArgumentException;
use ItPack\DisenchantIt\Adapter\Detection\ParsedSourceCache;
use ItPack\DisenchantIt\Contract\DisenchantItExceptionInterface;

/**
 * Thrown when a ParsedSourceCache is configured with a bound below one entry.
 * The cache must always be able to hold at least the single source being
 * scanned; anything smaller is a configuration error, fail-closed.
 *
 * @see \ItPack\DisenchantIt\Contract\Detection\ParsedSourceCacheInterface::DEFAULT_MAX_ENTRIES
 */
final class InvalidCacheConfigurationException extends InvalidArgumentException implements DisenchantItExceptionInterface
{
    public function __construct(int $maxEntries, ?\Throwable $previous = null)
    {
        parent::__construct(
            sprintf(
                'ParsedSourceCache maxEntries must be at least 1, got %d.',
                $maxEntries,
            ),
            0,
            $previous,
        );
    }
}
