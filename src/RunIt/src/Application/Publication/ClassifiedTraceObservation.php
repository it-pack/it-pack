<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use ItPack\RunIt\Behavior\Domain\SequencedBehavior;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use ItPack\RunIt\Contract\Domain\ObservationIdInterface;
use ItPack\RunIt\Contract\Domain\ObservationInterface;
use ItPack\RunIt\Contract\Domain\ObservationSeverityInterface;
use ItPack\RunIt\Contract\Domain\RedactedArtifactInterface;
use ItPack\RunIt\Contract\Domain\StringIdentifierInterface;
use ItPack\RunIt\Contract\Domain\ThrowableDescriptionInterface;
use ItPack\RunIt\Domain\Observation\Observation;
use ItPack\RunIt\Domain\Observation\ObservationSeverity;

/** @internal A validated redacted observation with trace severity. */
final readonly class ClassifiedTraceObservation implements ObservationInterface, RedactedArtifactInterface
{
    use SequencedBehavior;

    public function __construct(private Observation $observation)
    {
    }

    public function artifactType(): string
    {
        return 'observation';
    }

    public function id(): ObservationIdInterface
    {
        return $this->observation->id();
    }

    public function executionId(): ExecutionIdInterface
    {
        return $this->observation->executionId();
    }

    public function activityId(): ?StringIdentifierInterface
    {
        return $this->observation->activityId();
    }

    public function severity(): ObservationSeverityInterface
    {
        return ObservationSeverity::Trace;
    }

    public function message(): string
    {
        return $this->observation->message();
    }

    public function diagnosticCode(): ?string
    {
        return $this->observation->diagnosticCode();
    }

    public function throwableDescription(): ?ThrowableDescriptionInterface
    {
        return $this->observation->throwableDescription();
    }

    public function attributes(): array
    {
        return $this->observation->attributes();
    }

    public function timestamp(): int
    {
        return $this->observation->timestamp();
    }

    public function sequence(): int
    {
        return $this->observation->sequence();
    }

    public function normalize(): array
    {
        $fields = $this->observation->normalize();
        $fields['severity'] = ObservationSeverity::Trace->value;
        $fields['level'] = ObservationSeverity::Trace->level();
        return $fields;
    }
}
