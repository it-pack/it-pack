<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Exception;

use ItPack\RunIt\Contract\Exception\RunItExceptionInterface;
use RuntimeException;

/**
 * Raised when a nested activity would exceed the configured activity-depth bound.
 *
 * The bound protects the runtime from unbounded activity nesting (a denial-of-service
 * or runaway-recursion path). The message names the offending depth so the violation
 * is diagnosable without echoing any payload.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Activity
 */
final class ActivityDepthExceededException extends RuntimeException implements RunItExceptionInterface
{
    public static function forDepth(int $depth, int $maximum): self
    {
        return new self(
            sprintf('Activity depth must not exceed %d, got %d.', $maximum, $depth),
        );
    }
}
