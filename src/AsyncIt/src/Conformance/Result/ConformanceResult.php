<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Conformance\Result;

use Countable;
use IteratorAggregate;
use Traversable;

/**
 * The collected outcome of verifying one port implementation against its
 * conformance contract.
 *
 * A result aggregates {@see ConformanceViolation}s. It is intentionally
 * framework-agnostic: it holds only data plus a few query helpers, so any test
 * runner (Codeception Unit, PHPUnit, Pest, or a custom command) can inspect it
 * and translate it into that runner's assertion or reporting model.
 *
 * @implements IteratorAggregate<int, ConformanceViolation>
 *
 * @see https://w3id.org/itpack/asyncit#ConformanceResult
 * @see https://w3id.org/itpack/runit#Execution
 */
final readonly class ConformanceResult implements IteratorAggregate, Countable
{
    /**
     * @param ConformanceViolation[] $violations
     */
    public function __construct(
        public string $port,
        public array $violations = [],
    ) {
    }

    public function add(ConformanceViolation $violation): self
    {
        return new self($this->port, [...$this->violations, $violation]);
    }

    public function isConformant(): bool
    {
        return array_all($this->violations, fn (ConformanceViolation $violation): bool => !$violation->isError());
    }

    /**
     * @return ConformanceViolation[]
     */
    public function errors(): array
    {
        return array_values(array_filter(
            $this->violations,
            static fn (ConformanceViolation $violation): bool => $violation->isError(),
        ));
    }

    /**
     * @return ConformanceViolation[]
     */
    public function warnings(): array
    {
        return array_values(array_filter(
            $this->violations,
            static fn (ConformanceViolation $violation): bool => $violation->severity === ViolationSeverity::Warning,
        ));
    }

    public function count(): int
    {
        return count($this->violations);
    }

    public function getIterator(): Traversable
    {
        yield from $this->violations;
    }

    public function summary(): string
    {
        $errors = count($this->errors());
        $warnings = count($this->warnings());

        return sprintf(
            '%s: %s (%d errors, %d warnings)',
            $this->port,
            $this->isConformant() ? 'PASS' : 'FAIL',
            $errors,
            $warnings,
        );
    }
}
