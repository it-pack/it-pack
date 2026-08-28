<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Contract\Port;

/**
 * Policy that redacts sensitive data and bounds attribute size/cardinality
 * before external publication.
 *
 * Implementations decide which context keys are sensitive (e.g. `password`,
 * `token`), how values are masked, and the maximum string length / array
 * cardinality / nesting depth permitted. The goal is to keep secrets, oversized
 * payloads, and high-cardinality structures out of published observations and
 * occurrences.
 *
 * @api
 */
interface RedactionPolicyInterface
{
    /**
     * Default replacement for redacted values. This is the *policy* mask, applied
     * by {@see self::redact()} when the safe wrapper (`RedactingExecutionReporter`)
     * is used. It is distinct from {@see \ItPack\CommandIt\Contract\Domain\CommandContextInterface::REDACTED}
     * (the always-on classification mask); the two-stage model is documented in
     * the user guide.
     */
    public const DEFAULT_MARKER = '***redacted***';

    /**
     * Returns a redacted copy of the given context.
     *
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function redact(array $context): array;
}
