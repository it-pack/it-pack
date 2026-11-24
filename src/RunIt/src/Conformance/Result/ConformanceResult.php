<?php

declare(strict_types=1);

namespace ItPack\RunIt\Conformance\Result;

use Countable;
use Iterator;
use IteratorAggregate;
use ItPack\RunIt\Contract\Conformance\Result\ConformanceResultInterface;
use ItPack\RunIt\Contract\Conformance\Result\ConformanceViolationInterface;
use Traversable;

/**
 * The collected outcome of verifying one port implementation against its
 * conformance contract.
 *
 * A result aggregates {@see ConformanceViolationInterface}s. It is intentionally
 * framework-agnostic: it holds only data plus a few query helpers, so any test
 * runner (Codeception Unit, PHPUnit, Pest, or a custom command) can inspect it
 * and translate it into that runner's assertion or reporting model.
 *
 * @implements IteratorAggregate<int, ConformanceViolationInterface>
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final readonly class ConformanceResult implements IteratorAggregate, Countable, ConformanceResultInterface
{
    /**
     * @param ConformanceViolationInterface[] $violations
     */
    public function __construct(
        public string $port,
        public array $violations = [],
    ) {
    }

    public function add(ConformanceViolationInterface $violation): self
    {
        return new self($this->port, [...$this->violations, $violation]);
    }

    public function isConformant(): bool
    {
        return array_all(
            $this->violations,
            fn (ConformanceViolationInterface $violation): bool =>
            !$violation->isError()
        );
    }

    /**
     * @return ConformanceViolationInterface[]
     */
    public function errors(): array
    {
        return array_values(array_filter(
            $this->violations,
            static fn (ConformanceViolationInterface $v): bool => $v->isError(),
        ));
    }

    /**
     * @return ConformanceViolationInterface[]
     */
    public function warnings(): array
    {
        return array_values(array_filter(
            $this->violations,
            static fn (ConformanceViolationInterface $v): bool => $v->severity() === 'warning',
        ));
    }

    public function count(): int
    {
        return count($this->violations);
    }

    /**
     * @return Iterator<int, ConformanceViolationInterface>
     */
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
            $warnings
        );
    }
}
