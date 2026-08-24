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
use ItPack\RunIt\Domain\Shared\ArtifactField;
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
        private ObservationIdInterface $id,
        private ExecutionIdInterface $executionId,
        private ?ActivityIdInterface $activityId,
        private ObservationSeverityInterface $severity,
        private string $message,
        private ?string $diagnosticCode,
        private AttributeCollectionInterface $attributes,
        private ?ThrowableDescriptionInterface $throwableDescription,
        private InstantInterface $instant,
        private ArtifactSequenceInterface $sequence,
    ) {
    }

    /**
     * The single severity-parameterized construction path behind every named
     * constructor: defaults are applied once and the record is assembled once.
     */
    private static function atSeverity(
        ObservationSeverityInterface $severity,
        ObservationIdInterface $id,
        ExecutionIdInterface $executionId,
        string $message,
        ?ActivityIdInterface $activityId,
        ?string $diagnosticCode,
        ?AttributeCollectionInterface $attributes,
        ?ThrowableDescriptionInterface $throwableDescription,
        ?InstantInterface $instant,
        ?ArtifactSequenceInterface $sequence,
    ): self {
        return new self(
            $id,
            $executionId,
            $activityId,
            $severity,
            $message,
            $diagnosticCode,
            $attributes ?? AttributeCollection::empty(),
            $throwableDescription,
            $instant ?? Instant::now(),
            $sequence ?? ArtifactSequence::initial()
        );
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
        return self::atSeverity(ObservationSeverity::Debug, $id, $executionId, $message, $activityId, $diagnosticCode, $attributes, $throwableDescription, $instant, $sequence);
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
        return self::atSeverity(ObservationSeverity::Info, $id, $executionId, $message, $activityId, $diagnosticCode, $attributes, $throwableDescription, $instant, $sequence);
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
        return self::atSeverity(ObservationSeverity::Notice, $id, $executionId, $message, $activityId, $diagnosticCode, $attributes, $throwableDescription, $instant, $sequence);
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
        return self::atSeverity(ObservationSeverity::Warning, $id, $executionId, $message, $activityId, $diagnosticCode, $attributes, $throwableDescription, $instant, $sequence);
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
        return self::atSeverity(ObservationSeverity::Error, $id, $executionId, $message, $activityId, $diagnosticCode, $attributes, $throwableDescription, $instant, $sequence);
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
        return self::atSeverity(ObservationSeverity::Critical, $id, $executionId, $message, $activityId, $diagnosticCode, $attributes, $throwableDescription, $instant, $sequence);
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
        return self::atSeverity(ObservationSeverity::Alert, $id, $executionId, $message, $activityId, $diagnosticCode, $attributes, $throwableDescription, $instant, $sequence);
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
        return self::atSeverity(ObservationSeverity::Emergency, $id, $executionId, $message, $activityId, $diagnosticCode, $attributes, $throwableDescription, $instant, $sequence);
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
            ArtifactField::ID => $this->id->toString(),
            ArtifactField::EXECUTION_ID => $this->executionId->toString(),
            ArtifactField::ACTIVITY_ID => $this->activityId?->toString(),
            ArtifactField::SEVERITY => $this->severity->label(),
            ArtifactField::LEVEL => $this->severity->level(),
            ArtifactField::MESSAGE => $this->message,
            ArtifactField::DIAGNOSTIC_CODE => $this->diagnosticCode,
            ArtifactField::SEQUENCE => $this->sequence->value(),
            ArtifactField::TIMESTAMP => $this->instant->epochSeconds(),
            ArtifactField::ATTRIBUTES => $this->attributes->toArray(),
            ArtifactField::THROWABLE => $this->throwableDescription?->toArray(),
        ];
    }
}
