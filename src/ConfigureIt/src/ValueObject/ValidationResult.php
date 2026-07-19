<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\ValueObject;

/**
 * Outcome of validating a configuration graph against a schema.
 *
 * Immutably captures whether validation succeeded and, when it did not, the
 * collected diagnostics (path + human-readable message). Aligned with the
 * execution model's "Observation" concept (see docs/EXECUTION.md).
 */
final readonly class ValidationResult
{
    /**
     * @param list<array{path: string, message: string}> $errors
     */
    public function __construct(
        private bool $valid,
        private array $errors = [],
    ) {
    }

    public function valid(): bool
    {
        return $this->valid;
    }

    /**
     * @return list<array{path: string, message: string}>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
