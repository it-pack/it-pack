<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Conformance\Result;

/**
 * Contract for a single conformance finding produced by a verifier.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
interface ConformanceViolationInterface
{
    public function __toString(): string;

    public function id(): string;

    public function isError(): bool;

    public function severity(): string;

    public function message(): string;

    /**
     * @return array<string, mixed>
     */
    public function attributes(): array;
}
