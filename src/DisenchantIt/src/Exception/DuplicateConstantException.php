<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Exception;

use ItPack\DisenchantIt\Contract\DisenchantItExceptionInterface;

/**
 * Thrown when a placement targets a class constant that already exists with a
 * differing value, or when two placements would declare the same constant name
 * in the same class. Prevents emitting a PHP fatal (`Cannot redeclare class
 * constant`) or silently overwriting an existing constant.
 *
 * @see \ItPack\DisenchantIt\Adapter\LineBasedRewriter
 */
final class DuplicateConstantException extends \RuntimeException implements DisenchantItExceptionInterface
{
    public function __construct(string $constantName, string $className, ?\Throwable $previous = null)
    {
        parent::__construct(
            sprintf(
                'Constant %s::%s already exists and cannot be re-declared with a different value.',
                $className,
                $constantName,
            ),
            0,
            $previous,
        );
    }
}
