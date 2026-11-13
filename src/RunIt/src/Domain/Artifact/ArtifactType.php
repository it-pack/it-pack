<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Artifact;

use ItPack\RunIt\Contract\Domain\ActivityInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\ExecutionInterface;
use ItPack\RunIt\Contract\Domain\MeasurementInterface;
use ItPack\RunIt\Contract\Domain\ObservationInterface;
use ItPack\RunIt\Contract\Domain\OccurrenceInterface;
use ItPack\RunIt\Contract\Domain\SnapshotInterface;
use ItPack\RunIt\Contract\Domain\TypedExecutionArtifactInterface;
use ValueError;

enum ArtifactType: string
{
    public const string EXECUTION = 'execution';

    public const string OBSERVATION = 'observation';

    public const string ACTIVITY = 'activity';

    public const string MEASUREMENT = 'measurement';

    public const string OCCURRENCE = 'occurrence';

    public const string SNAPSHOT = 'snapshot';

    public const string UNKNOWN = 'unknown';

    public const array INTERFACES = [
        self::EXECUTION => ExecutionInterface::class,
        self::OBSERVATION => ObservationInterface::class,
        self::ACTIVITY => ActivityInterface::class,
        self::MEASUREMENT => MeasurementInterface::class,
        self::OCCURRENCE => OccurrenceInterface::class,
        self::SNAPSHOT => SnapshotInterface::class,
    ];

    case Execution = self::EXECUTION;
    case Observation = self::OBSERVATION;
    case Activity = self::ACTIVITY;
    case Measurement = self::MEASUREMENT;
    case Occurrence = self::OCCURRENCE;
    case Snapshot = self::SNAPSHOT;



    public static function valid(ExecutionArtifactInterface $artifact): bool
    {
        if ($artifact instanceof TypedExecutionArtifactInterface) {
            return true;
        }

        return array_any(self::INTERFACES, static fn (string $interface): bool => $artifact instanceof $interface);
    }

    public static function type(ExecutionArtifactInterface $artifact): string
    {
        if ($artifact instanceof TypedExecutionArtifactInterface) {
            return self::tryFrom($artifact->artifactType())->value ?? self::UNKNOWN;
        }

        foreach (self::INTERFACES as $type => $interface) {
            if ($artifact instanceof $interface) {
                return $type;
            }
        }

        return self::UNKNOWN;
    }

    public static function fromInstance(
        ExecutionInterface
        |ObservationInterface
        |ActivityInterface
        |MeasurementInterface
        |OccurrenceInterface
        |SnapshotInterface $artifact
    ): self {
        return match (true) {
            $artifact instanceof ExecutionInterface => self::Execution,
            $artifact instanceof ObservationInterface => self::Observation,
            $artifact instanceof ActivityInterface => self::Activity,
            $artifact instanceof MeasurementInterface => self::Measurement,
            $artifact instanceof OccurrenceInterface => self::Occurrence,
            $artifact instanceof SnapshotInterface => self::Snapshot,
        };
    }

    public static function tryFromArtifact(ExecutionArtifactInterface $artifact): ?self
    {
        if ($artifact instanceof TypedExecutionArtifactInterface) {
            return self::from($artifact->artifactType());
        }

        foreach (self::INTERFACES as $type => $interface) {
            if ($artifact instanceof $interface) {
                return self::from($type);
            }
        }

        return null;
    }

    public static function fromArtifact(ExecutionArtifactInterface $artifact): self
    {
        $instance = self::tryFromArtifact($artifact);

        if (!$instance instanceof self) {
            throw new ValueError('Unknown artifact type: ' . $artifact::class);
        }

        return $instance;
    }

    public function interface(): string
    {
        return self::INTERFACES[$this->value];
    }

    public function isInstance(ExecutionArtifactInterface $artifact): bool
    {
        return $artifact instanceof ($this->interface());
    }
}
