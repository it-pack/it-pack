<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Observation;

use ItPack\RunIt\Contract\Domain\ActivityIdInterface;
use ItPack\RunIt\Contract\Domain\ArtifactSequenceInterface;
use ItPack\RunIt\Contract\Domain\AttributeCollectionInterface;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use ItPack\RunIt\Contract\Domain\InstantInterface;
use ItPack\RunIt\Contract\Domain\ObservationIdInterface;
use ItPack\RunIt\Contract\Domain\ObservationInterface;
use ItPack\RunIt\Contract\Domain\ObservationSeverityInterface;
use ItPack\RunIt\Contract\Domain\ThrowableDescriptionInterface;
use ItPack\RunIt\Domain\Shared\ArtifactSequence;
use ItPack\RunIt\Domain\Shared\AttributeCollection;
use ItPack\RunIt\Domain\Shared\Instant;

/**
 * An immutable observation artifact.
 *
 * An observation records qualitative information noticed during execution. It carries a
 * stable message template, an optional diagnostic code, a bounded attribute set, and an
 * optional safe throwable description, and never retains a mutable session.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Observation
 */
final readonly class Observation implements ObservationInterface
{
    private function __construct(
        private readonly ObservationIdInterface $id,
        private readonly ExecutionIdInterface $executionId,
        private readonly ?ActivityIdInterface $activityId,
        private readonly ObservationSeverityInterface $severity,
        private readonly string $message,
        private readonly ?string $diagnosticCode,
        private readonly AttributeCollectionInterface $attributes,
        private readonly ?ThrowableDescriptionInterface $throwableDescription,
        private readonly InstantInterface $instant,
        private readonly ArtifactSequenceInterface $sequence,
    ) {
    }

    public static function debug(
        ObservationIdInterface $id,
        ExecutionIdInterface $executionId,
        string $message,
        ?ActivityIdInterface $activityId = null,
        ?string $diagnosticCode = null,
        ?AttributeCollectionInterface $attributes = null,
        ?ThrowableDescriptionInterface $throwableDescription = null,
        ?InstantInterface $instant = null,
        ?ArtifactSequenceInterface $sequence = null,
    ): self {
        return new self($id, $executionId, $activityId, ObservationSeverity::Debug, $message, $diagnosticCode, $attributes ?? AttributeCollection::empty(), $throwableDescription, $instant ?? Instant::now(), $sequence ?? ArtifactSequence::initial());
    }

    public static function info(
        ObservationIdInterface $id,
        ExecutionIdInterface $executionId,
        string $message,
        ?ActivityIdInterface $activityId = null,
        ?string $diagnosticCode = null,
        ?AttributeCollectionInterface $attributes = null,
        ?ThrowableDescriptionInterface $throwableDescription = null,
        ?InstantInterface $instant = null,
        ?ArtifactSequenceInterface $sequence = null,
    ): self {
        return new self($id, $executionId, $activityId, ObservationSeverity::Info, $message, $diagnosticCode, $attributes ?? AttributeCollection::empty(), $throwableDescription, $instant ?? Instant::now(), $sequence ?? ArtifactSequence::initial());
    }

    public static function notice(
        ObservationIdInterface $id,
        ExecutionIdInterface $executionId,
        string $message,
        ?ActivityIdInterface $activityId = null,
        ?string $diagnosticCode = null,
        ?AttributeCollectionInterface $attributes = null,
        ?ThrowableDescriptionInterface $throwableDescription = null,
        ?InstantInterface $instant = null,
        ?ArtifactSequenceInterface $sequence = null,
    ): self {
        return new self($id, $executionId, $activityId, ObservationSeverity::Notice, $message, $diagnosticCode, $attributes ?? AttributeCollection::empty(), $throwableDescription, $instant ?? Instant::now(), $sequence ?? ArtifactSequence::initial());
    }

    public static function warning(
        ObservationIdInterface $id,
        ExecutionIdInterface $executionId,
        string $message,
        ?ActivityIdInterface $activityId = null,
        ?string $diagnosticCode = null,
        ?AttributeCollectionInterface $attributes = null,
        ?ThrowableDescriptionInterface $throwableDescription = null,
        ?InstantInterface $instant = null,
        ?ArtifactSequenceInterface $sequence = null,
    ): self {
        return new self($id, $executionId, $activityId, ObservationSeverity::Warning, $message, $diagnosticCode, $attributes ?? AttributeCollection::empty(), $throwableDescription, $instant ?? Instant::now(), $sequence ?? ArtifactSequence::initial());
    }

    public static function error(
        ObservationIdInterface $id,
        ExecutionIdInterface $executionId,
        string $message,
        ?ActivityIdInterface $activityId = null,
        ?string $diagnosticCode = null,
        ?AttributeCollectionInterface $attributes = null,
        ?ThrowableDescriptionInterface $throwableDescription = null,
        ?InstantInterface $instant = null,
        ?ArtifactSequenceInterface $sequence = null,
    ): self {
        return new self($id, $executionId, $activityId, ObservationSeverity::Error, $message, $diagnosticCode, $attributes ?? AttributeCollection::empty(), $throwableDescription, $instant ?? Instant::now(), $sequence ?? ArtifactSequence::initial());
    }

    public static function critical(
        ObservationIdInterface $id,
        ExecutionIdInterface $executionId,
        string $message,
        ?ActivityIdInterface $activityId = null,
        ?string $diagnosticCode = null,
        ?AttributeCollectionInterface $attributes = null,
        ?ThrowableDescriptionInterface $throwableDescription = null,
        ?InstantInterface $instant = null,
        ?ArtifactSequenceInterface $sequence = null,
    ): self {
        return new self($id, $executionId, $activityId, ObservationSeverity::Critical, $message, $diagnosticCode, $attributes ?? AttributeCollection::empty(), $throwableDescription, $instant ?? Instant::now(), $sequence ?? ArtifactSequence::initial());
    }

    public static function alert(
        ObservationIdInterface $id,
        ExecutionIdInterface $executionId,
        string $message,
        ?ActivityIdInterface $activityId = null,
        ?string $diagnosticCode = null,
        ?AttributeCollectionInterface $attributes = null,
        ?ThrowableDescriptionInterface $throwableDescription = null,
        ?InstantInterface $instant = null,
        ?ArtifactSequenceInterface $sequence = null,
    ): self {
        return new self($id, $executionId, $activityId, ObservationSeverity::Alert, $message, $diagnosticCode, $attributes ?? AttributeCollection::empty(), $throwableDescription, $instant ?? Instant::now(), $sequence ?? ArtifactSequence::initial());
    }

    public static function emergency(
        ObservationIdInterface $id,
        ExecutionIdInterface $executionId,
        string $message,
        ?ActivityIdInterface $activityId = null,
        ?string $diagnosticCode = null,
        ?AttributeCollectionInterface $attributes = null,
        ?ThrowableDescriptionInterface $throwableDescription = null,
        ?InstantInterface $instant = null,
        ?ArtifactSequenceInterface $sequence = null,
    ): self {
        return new self($id, $executionId, $activityId, ObservationSeverity::Emergency, $message, $diagnosticCode, $attributes ?? AttributeCollection::empty(), $throwableDescription, $instant ?? Instant::now(), $sequence ?? ArtifactSequence::initial());
    }

    public function id(): ObservationIdInterface
    {
        return $this->id;
    }

    public function executionId(): \ItPack\RunIt\Contract\Domain\ExecutionIdInterface
    {
        return $this->executionId;
    }

    public function activityId(): ?\ItPack\RunIt\Contract\Domain\StringIdentifierInterface
    {
        return $this->activityId;
    }

    public function severity(): ObservationSeverityInterface
    {
        return $this->severity;
    }

    public function message(): string
    {
        return $this->message;
    }

    public function diagnosticCode(): ?string
    {
        return $this->diagnosticCode;
    }

    public function throwableDescription(): ?ThrowableDescriptionInterface
    {
        return $this->throwableDescription;
    }

    /**
     * @return array<string, int|float|string|bool|null|array<int|string, int|float|string|bool|null>>
     */
    public function attributes(): array
    {
        return $this->attributes->toArray();
    }

    public function timestamp(): int
    {
        return $this->instant->epochSeconds();
    }

    public function sequence(): int
    {
        return $this->sequence->value();
    }

    public function count(): int
    {
        return $this->sequence->value();
    }

    /**
     * @return array<string, mixed>
     */
    public function normalize(): array
    {
        return [
            'id' => $this->id->toString(),
            'execution_id' => $this->executionId->toString(),
            'activity_id' => $this->activityId?->toString(),
            'severity' => $this->severity->name(),
            'level' => $this->severity->level(),
            'message' => $this->message,
            'diagnostic_code' => $this->diagnosticCode,
            'sequence' => $this->sequence->value(),
            'timestamp' => $this->instant->epochSeconds(),
            'attributes' => $this->attributes->toArray(),
            'throwable' => $this->throwableDescription?->toArray(),
        ];
    }
}
