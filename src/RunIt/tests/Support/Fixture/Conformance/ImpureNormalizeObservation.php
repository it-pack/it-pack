<?php

declare(strict_types=1);

namespace Tests\Support\Fixture\Conformance;

use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use ItPack\RunIt\Contract\Domain\ObservationIdInterface;
use ItPack\RunIt\Contract\Domain\ObservationInterface;
use ItPack\RunIt\Contract\Domain\ObservationSeverityInterface;
use ItPack\RunIt\Contract\Domain\StringIdentifierInterface;
use ItPack\RunIt\Contract\Domain\ThrowableDescriptionInterface;

/**
 * Observation that delegates everything except {@see normalize()}, which changes
 * between calls, so mutation-detection branches of the verifiers can fire.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class ImpureNormalizeObservation implements ObservationInterface
{
    private int $invocations = 0;

    public function __construct(private readonly ObservationInterface $inner)
    {
    }

    public function normalize(): array
    {
        return ['normalize.invocations' => ++$this->invocations];
    }

    public function id(): ObservationIdInterface
    {
        return $this->inner->id();
    }

    public function severity(): ObservationSeverityInterface
    {
        return $this->inner->severity();
    }

    public function message(): string
    {
        return $this->inner->message();
    }

    public function diagnosticCode(): ?string
    {
        return $this->inner->diagnosticCode();
    }

    public function throwableDescription(): ?ThrowableDescriptionInterface
    {
        return $this->inner->throwableDescription();
    }

    public function executionId(): ExecutionIdInterface
    {
        return $this->inner->executionId();
    }

    public function activityId(): ?StringIdentifierInterface
    {
        return $this->inner->activityId();
    }

    public function timestamp(): int
    {
        return $this->inner->timestamp();
    }

    public function sequence(): int
    {
        return $this->inner->sequence();
    }

    public function attributes(): array
    {
        return $this->inner->attributes();
    }

    public function count(): int
    {
        return $this->inner->count();
    }
}
