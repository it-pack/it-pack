<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\ValueObject;

use ItPack\ConfigureIt\Contract\ValueObject\ValidationResultInterface;

/**
 * Outcome of validating a configuration graph against a schema.
 *
 * Immutably captures whether validation succeeded and, when it did not, the
 * collected diagnostics (path + human-readable message + severity). Aligned with
 * the execution model's "Observation" concept (see docs/EXECUTION.md): warnings
 * are non-blocking and surfaced as observations, only errors block resolution
 * (unless {@see \ItPack\ConfigureIt\Core\ConfigurationResolver} is asked to treat
 * warnings as fatal too).
 */
final readonly class ValidationResult implements ValidationResultInterface
{
    /**
     * @param list<array{path: string, message: string, severity: 'error'|'warning'}> $issues
     */
    public function __construct(
        private bool $valid,
        private array $issues = [],
    ) {
    }

    public function valid(): bool
    {
        return $this->valid;
    }

    /**
     * @return list<array{path: string, message: string, severity: 'error'|'warning'}>
     */
    public function issues(): array
    {
        return $this->issues;
    }

    /**
     * @return list<array{path: string, message: string, severity: 'error'|'warning'}>
     */
    public function errors(): array
    {
        return array_values(array_filter(
            $this->issues,
            static fn (array $issue): bool => 'error' === $issue['severity'],
        ));
    }

    /**
     * @return list<array{path: string, message: string, severity: 'error'|'warning'}>
     */
    public function warnings(): array
    {
        return array_values(array_filter(
            $this->issues,
            static fn (array $issue): bool => 'warning' === $issue['severity'],
        ));
    }

    public function hasErrors(): bool
    {
        return [] !== $this->errors();
    }

    public function hasWarnings(): bool
    {
        return [] !== $this->warnings();
    }
}
