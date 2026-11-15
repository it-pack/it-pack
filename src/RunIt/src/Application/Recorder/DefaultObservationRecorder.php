<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Recorder;

use Closure;
use ItPack\RunIt\Application\Publication\ProvenanceAuthority;
use ItPack\RunIt\Behavior\Application\InstantClockBehavior;
use ItPack\RunIt\Contract\Application\ObservationRecorderInterface;
use ItPack\RunIt\Contract\Domain\ActivityIdInterface;
use ItPack\RunIt\Contract\Domain\ArtifactSequenceInterface;
use ItPack\RunIt\Contract\Domain\AttributeCollectionInterface;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use ItPack\RunIt\Contract\Domain\Factory\StringIdentifierFactoryInterface;
use ItPack\RunIt\Contract\Domain\ObservationIdInterface;
use ItPack\RunIt\Contract\Domain\ObservationInterface;
use ItPack\RunIt\Contract\Domain\ObservationSeverityInterface;
use ItPack\RunIt\Contract\Domain\SequenceAllocatorInterface;
use ItPack\RunIt\Contract\Domain\ThrowableDescriptionInterface;
use ItPack\RunIt\Contract\Domain\ThrowableMapperInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Contract\Port\MonotonicClockInterface;
use ItPack\RunIt\Domain\Observation\Observation;
use ItPack\RunIt\Domain\Observation\ObservationSeverity;
use RuntimeException;
use Throwable;

/**
 * Default observation recorder.
 *
 * It maps a throwable through the configured policy, fills ownership, timing, and sequence
 * automatically, publishes the observation, and returns it. The active activity (if any) is
 * bound from the supplied closure.
 *
 * @see https://w3id.org/itpack/runit#Observation
 */
final readonly class DefaultObservationRecorder implements ObservationRecorderInterface
{
    use InstantClockBehavior;

    /**
     * @param Closure(): ?ActivityIdInterface $currentActivityId
     */
    public function __construct(
        private ExecutionIdInterface             $executionId,
        private Closure                          $currentActivityId,
        private SequenceAllocatorInterface       $sequenceAllocator,
        private MonotonicClockInterface          $clock,
        private ArtifactPublisherInterface       $publisher,
        private ThrowableMapperInterface         $throwableMapper,
        /** @var StringIdentifierFactoryInterface<ObservationIdInterface> */
        private StringIdentifierFactoryInterface $observationIdFactory,
        private ProvenanceAuthority              $authority = new ProvenanceAuthority(),
    ) {
    }

    public function record(
        string $message,
        ?ObservationSeverityInterface $severity = null,
        ?AttributeCollectionInterface $attributes = null,
        ?Throwable $throwable = null,
        ?string $diagnosticCode = null,
    ): ObservationInterface {
        $severity ??= ObservationSeverity::Info;
        $id = $this->observationIdFactory->build();
        $description = $throwable instanceof Throwable ? $this->throwableMapper->describe($throwable) : null;
        $observation = $this->instantiate(
            $severity,
            $id,
            $message,
            $this->activityId(),
            $diagnosticCode,
            $attributes,
            $description
        );

        // Issue an SDK-controlled export ID for the observation and register the
        // execution→observation edge so provenance can be verified at publication.
        try {
            $exportId = $this->authority->bindArtifact(
                'observation',
                'observation',
                $this->executionId->toString(),
                $id->toString(),
                'execution-observation',
            );

            $this->authority->attest($observation, $exportId);
            $activityBusinessId = $observation->activityId()?->toString();
            if ($exportId !== null && $activityBusinessId !== null) {
                $executionId = $this->authority->executionIdForExportId($exportId);
                $activityExportId = $executionId !== null
                    ? $this->authority->exportIdForBusinessId($activityBusinessId, $executionId, 'activity')
                    : null;
                if ($activityExportId !== null) {
                    $this->authority->bindActivityRelationship($exportId, $activityExportId);
                }
            }
        } catch (Throwable) {
            $this->authority->reportUnverified(new RuntimeException('Observation publication preparation failed.'));
            return $observation;
        }

        $this->publisher->publish($observation);

        return $observation;
    }

    private function instantiate(
        ObservationSeverityInterface $severity,
        ObservationIdInterface $id,
        string $message,
        ?ActivityIdInterface $activityId,
        ?string $diagnosticCode,
        ?AttributeCollectionInterface $attributes,
        ?ThrowableDescriptionInterface $description,
    ): ObservationInterface {
        $now = $this->now();
        $sequence = $this->sequence();
        $args = [
            $id,
            $this->executionId,
            $message,
            $activityId,
            $diagnosticCode,
            $attributes,
            $description,
            $now,
            $sequence,
        ];

        if ($severity === ObservationSeverity::Debug) {
            return Observation::debug(...$args);
        }

        if ($severity === ObservationSeverity::Info) {
            return Observation::info(...$args);
        }

        if ($severity === ObservationSeverity::Notice) {
            return Observation::notice(...$args);
        }

        if ($severity === ObservationSeverity::Warning) {
            return Observation::warning(...$args);
        }

        if ($severity === ObservationSeverity::Error) {
            return Observation::error(...$args);
        }

        if ($severity === ObservationSeverity::Critical) {
            return Observation::critical(...$args);
        }

        if ($severity === ObservationSeverity::Alert) {
            return Observation::alert(...$args);
        }

        if ($severity === ObservationSeverity::Emergency) {
            return Observation::emergency(...$args);
        }

        return Observation::info(...$args);
    }

    private function activityId(): ?ActivityIdInterface
    {
        return ($this->currentActivityId)();
    }

    private function sequence(): ArtifactSequenceInterface
    {
        return $this->sequenceAllocator->allocate($this->executionId);
    }
}
