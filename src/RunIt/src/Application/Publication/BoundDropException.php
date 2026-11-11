<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use ItPack\RunIt\Contract\Exception\RunItExceptionInterface;
use RuntimeException;

/**
 * Internal notice carried when a data-protection bound drops an artifact.
 *
 * Dropping is the bound's intended fail-open decision, not a pipeline failure.
 * The notice exists so bound-exceeded drops stay observable through the
 * configured health callback as a recognizably RunIt artifact instead of being
 * silently discarded.
 *
 * @internal
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class BoundDropException extends RuntimeException implements RunItExceptionInterface
{
    private const string REASON_TEMPLATE = 'Artifact dropped by data-protection bound "%s".';

    public static function forReason(string $reason): self
    {
        return new self(
            sprintf(
                self::REASON_TEMPLATE,
                $reason,
            ),
        );
    }
}
