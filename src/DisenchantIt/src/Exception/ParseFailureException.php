<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Exception;

use ItPack\DisenchantIt\Contract\DisenchantItExceptionInterface;

/**
 * Thrown when the nikic parser cannot produce an AST for a source string
 * (syntax error / unrecoverable parse failure). Distinguishes a parse problem
 * from a successful-but-empty scan (which yields no literals, not an error).
 *
 * @see \ItPack\DisenchantIt\Adapter\Detector
 */
final class ParseFailureException extends \RuntimeException implements DisenchantItExceptionInterface
{
    public function __construct(int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct(
            'Source could not be parsed into an AST.',
            $code,
            $previous,
        );
    }
}
