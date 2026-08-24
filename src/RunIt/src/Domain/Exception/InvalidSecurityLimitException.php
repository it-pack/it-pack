<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Exception;

use ItPack\RunIt\Contract\Exception\InvalidValueExceptionInterface;
use RuntimeException;

/**
 * Raised when a security limit is configured below its smallest legal value.
 *
 * Limits are safeguards against data-exfiltration and denial-of-service paths;
 * a limit smaller than one would silently disable the safeguard instead of
 * bounding it, so it is rejected at construction rather than widened.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class InvalidSecurityLimitException extends RuntimeException implements InvalidValueExceptionInterface
{
    public static function forNonPositive(string $name, int $value): self
    {
        return new self(
            sprintf(
                'Security limit %s must be a positive integer, got %d',
                $name,
                $value,
            ),
        );
    }
}
