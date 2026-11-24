<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Conformance\Result;

use Countable;
use Iterator;
use Traversable;

/**
 * Contract for the collected outcome of verifying one port implementation.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
interface ConformanceResultInterface extends Countable
{
    public function add(ConformanceViolationInterface $violation): self;

    public function isConformant(): bool;

    /**
     * @return ConformanceViolationInterface[]
     */
    public function errors(): array;

    /**
     * @return ConformanceViolationInterface[]
     */
    public function warnings(): array;

    public function count(): int;

    /**
     * @return Iterator<ConformanceViolationInterface>
     */
    public function getIterator(): Traversable;

    public function summary(): string;
}
