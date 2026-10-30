<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Conformance\Result;

use Stringable;

/**
 * A single conformance finding produced by a verifier.
 *
 * The violation is a plain, serializable record: it carries a stable identifier
 * (so suites can reference a specific rule), a human-readable message, and a
 * {@see ViolationSeverity}. Verifiers never throw on a failing rule; they record
 * a violation instead so a whole port can be assessed in one pass.
 *
 * @see https://w3id.org/itpack/asyncit#ConformanceViolation
 * @see https://w3id.org/itpack/runit#Execution
 */
final readonly class ConformanceViolation implements Stringable
{
    public function __construct(
        public string $id,
        public string $message,
        public ViolationSeverity $severity = ViolationSeverity::Error,
    ) {
    }

    public function __toString(): string
    {
        return sprintf('[%s] %s: %s', $this->severity->value, $this->id, $this->message);
    }

    public function isError(): bool
    {
        return $this->severity === ViolationSeverity::Error;
    }
}
