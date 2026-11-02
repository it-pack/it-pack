<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Conformance\Contract;

use Countable;
use IteratorAggregate;
use ItPack\AsyncIt\Conformance\Result\ConformanceViolationInterface;

/**
 * The collected outcome of verifying one port implementation against its
 * conformance contract.
 *
 * Backs {@see ConformanceResult} so results remain mockable and the conformance
 * harness stays interface-driven.
 *
 * @extends IteratorAggregate<int, ConformanceViolationInterface>
 *
 * @see https://w3id.org/itpack/asyncit#ConformanceResult
 * @see https://w3id.org/itpack/runit#Execution
 * @api
 */
interface ConformanceResultInterface extends Countable, IteratorAggregate
{
    /**
     * Appends a violation and returns a new result instance (immutable).
     */
    public function add(ConformanceViolationInterface $violation): self;

    /**
     * Whether the result has no error-level violations.
     */
    public function isConformant(): bool;

    /**
     * Returns all error-level violations.
     *
     * @return ConformanceViolationInterface[]
     */
    public function errors(): array;

    /**
     * Returns all warning-level violations.
     *
     * @return ConformanceViolationInterface[]
     */
    public function warnings(): array;

    /**
     * Returns a human-readable summary of the result.
     */
    public function summary(): string;
}
