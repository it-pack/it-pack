<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Adapter\Reporting;

use ItPack\CommandIt\Contract\Exception\CommandExceptionInterface;

/**
 * Thrown by {@see CollectingExecutionReporter} when its bounded buffer overflows
 * and the configured {@see OverflowPolicy} is {@see OverflowPolicy::Throw}.
 *
 * @api
 */
final class BufferOverflowException extends \RuntimeException implements CommandExceptionInterface
{
    public static function forLimit(int $limit): self
    {
        return new self(\sprintf('Execution reporter buffer is full (%d entries).', $limit));
    }
}
