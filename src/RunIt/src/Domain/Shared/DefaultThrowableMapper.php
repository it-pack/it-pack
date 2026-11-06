<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Shared;

use ItPack\RunIt\Contract\Domain\ThrowableDescriptionInterface;
use ItPack\RunIt\Contract\Domain\ThrowableMapperInterface;
use Throwable;

/**
 * The default safe throwable mapper.
 *
 * It normalizes a `Throwable` into an immutable {@see ThrowableDescription}
 * without ever retaining the original exception. Capture is policy-driven:
 *
 *  - The message is truncated to {@see self::DEFAULT_MAX_MESSAGE_LENGTH} bytes.
 *  - The code is captured as an integer: `Throwable::getCode()` is declared
 *    `int` for SPL exceptions, but extension-provided exceptions have
 *    historically exposed non-int codes through shadowed properties, so the
 *    value is coerced defensively rather than trusted.
 *  - Previous causes are captured recursively, up to
 *    {@see self::DEFAULT_MAX_CAUSE_DEPTH} levels.
 *  - The stack trace is included only when explicitly enabled, and then
 *    truncated to {@see self::DEFAULT_MAX_TRACE_LENGTH} bytes.
 *
 * A negative bound disables the corresponding truncation, which callers may use
 * when a wider capture is intentionally permitted by their policy.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#ThrowableDescription
 */
final readonly class DefaultThrowableMapper implements ThrowableMapperInterface
{
    public const int DEFAULT_MAX_MESSAGE_LENGTH = 1024;

    public const int DEFAULT_MAX_TRACE_LENGTH = 8192;

    public const int DEFAULT_MAX_CAUSE_DEPTH = 5;

    /**
     * @param bool $includeTrace whether to capture the stack trace
     * @param int  $maxMessageLength maximum message bytes; negative disables truncation
     * @param int  $maxTraceLength maximum trace bytes; negative disables truncation
     * @param int  $maxCauseDepth maximum number of captured previous-cause levels
     */
    public function __construct(
        private bool $includeTrace = false,
        private int $maxMessageLength = self::DEFAULT_MAX_MESSAGE_LENGTH,
        private int $maxTraceLength = self::DEFAULT_MAX_TRACE_LENGTH,
        private int $maxCauseDepth = self::DEFAULT_MAX_CAUSE_DEPTH,
    ) {
    }

    public function describe(Throwable $throwable): ThrowableDescriptionInterface
    {
        return $this->describeAt($throwable, 0);
    }

    private function describeAt(Throwable $throwable, int $depth): ThrowableDescriptionInterface
    {
        $previous = null;
        $cause = $throwable->getPrevious();

        if ($cause instanceof Throwable && $depth < $this->maxCauseDepth) {
            $previous = $this->describeAt($cause, $depth + 1);
        }

        $trace = null;
        if ($this->includeTrace) {
            $trace = $throwable->getTraceAsString();

            if ($this->maxTraceLength >= 0 && strlen($trace) > $this->maxTraceLength) {
                $trace = substr($trace, 0, $this->maxTraceLength);
            }
        }

        // Defensive coercion: extension exceptions (e.g. PDO-style) have
        // historically surfaced non-int codes despite the native `int` hint.
        $code = (int) $throwable->getCode();
        $message = $throwable->getMessage();

        if ($this->maxMessageLength >= 0 && strlen($message) > $this->maxMessageLength) {
            $message = substr($message, 0, $this->maxMessageLength);
        }

        return ThrowableDescription::create(
            $throwable::class,
            $message,
            $code,
            $previous,
            $trace,
        );
    }
}
