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
            $this->authority->bindActivityRelationship($exportId, $observation->activityId()?->toString());
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
        return match ($severity) {
            ObservationSeverity::Debug => Observation::debug(
                $id,
                $this->executionId,
                $message,
                $activityId,
                $diagnosticCode,
                $attributes,
                $description,
                $this->now(),
                $this->sequence()
            ),
            ObservationSeverity::Notice => Observation::notice(
                $id,
                $this->executionId,
                $message,
                $activityId,
                $diagnosticCode,
                $attributes,
                $description,
                $this->now(),
                $this->sequence()
            ),
            ObservationSeverity::Warning => Observation::warning(
                $id,
                $this->executionId,
                $message,
                $activityId,
                $diagnosticCode,
                $attributes,
                $description,
                $this->now(),
                $this->sequence()
            ),
            ObservationSeverity::Error => Observation::error(
                $id,
                $this->executionId,
                $message,
                $activityId,
                $diagnosticCode,
                $attributes,
                $description,
                $this->now(),
                $this->sequence()
            ),
            ObservationSeverity::Critical => Observation::critical(
                $id,
                $this->executionId,
                $message,
                $activityId,
                $diagnosticCode,
                $attributes,
                $description,
                $this->now(),
                $this->sequence()
            ),
            ObservationSeverity::Alert => Observation::alert(
                $id,
                $this->executionId,
                $message,
                $activityId,
                $diagnosticCode,
                $attributes,
                $description,
                $this->now(),
                $this->sequence()
            ),
            ObservationSeverity::Emergency => Observation::emergency(
                $id,
                $this->executionId,
                $message,
                $activityId,
                $diagnosticCode,
                $attributes,
                $description,
                $this->now(),
                $this->sequence()
            ),
            default => Observation::info(
                $id,
                $this->executionId,
                $message,
                $activityId,
                $diagnosticCode,
                $attributes,
                $description,
                $this->now(),
                $this->sequence()
            ),
        };
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
