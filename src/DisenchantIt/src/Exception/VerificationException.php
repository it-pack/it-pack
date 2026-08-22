<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Exception;

use ItPack\DisenchantIt\Contract\DisenchantItExceptionInterface;

/**
 * Thrown by the post-rewrite verification observer (e.g. PhpLintObserver) when
 * the rewritten source fails out-of-process verification (such as `php -l`).
 * It is an adapter-level failure that reports the verification result; it must
 * not replace an in-flight business exception raised earlier in the pipeline
 * (see AGENTS.md execution-lifecycle rules).
 */
final class VerificationException extends \RuntimeException implements DisenchantItExceptionInterface
{
    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
