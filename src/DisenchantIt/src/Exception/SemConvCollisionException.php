<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Exception;

use ItPack\DisenchantIt\Contract\DisenchantItExceptionInterface;
use ItPack\DisenchantIt\Contract\SemConv\SemConv;

/**
 * Thrown by the SemConv collector when two placements resolve to the same
 * constant name but different canonical values — a genuine naming conflict
 * that must be resolved by hand (rename one, or exclude one from collection).
 *
 * @see \ItPack\DisenchantIt\Adapter\SemConv\SemConvCollector
 */
final class SemConvCollisionException extends \RuntimeException implements DisenchantItExceptionInterface
{
    /**
     * @param list<string> $conflictingNames
     */
    public function __construct(array $conflictingNames, ?\Throwable $previous = null)
    {
        $message = sprintf(
            'SemConv collection found name collisions with differing values for: %s. '
            . 'These constant names are used for different values in different classes — '
            . 'resolve the naming conflict manually before generating a shared SemConv file '
            . '(rename one of them, or accept them as genuinely separate concepts and exclude '
            . 'one from the collection).',
            implode(', ', $conflictingNames),
        );

        parent::__construct($message, 0, $previous);
    }
}
