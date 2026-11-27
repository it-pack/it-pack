<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Factory;

use ItPack\RunIt\Application\Publication\ClassifyingArtifactPublisher;
use ItPack\RunIt\Application\Publication\DefaultLifecycleClassifier;
use ItPack\RunIt\Application\Publication\DefaultMeasurementClassifier;
use ItPack\RunIt\Application\Publication\DefaultObservationClassifier;
use ItPack\RunIt\Application\Publication\DefaultOccurrenceClassifier;
use ItPack\RunIt\Application\Publication\ErrorLogHealthReporter;
use ItPack\RunIt\Application\Publication\ProvenanceAuthority;
use ItPack\RunIt\Application\Publication\RedactingArtifactPublisher;
use ItPack\RunIt\Application\Publication\SamplingArtifactPublisher;
use ItPack\RunIt\Contract\Application\Publication\InternalHealthReporterInterface;
use ItPack\RunIt\Contract\Application\Publication\RedactorInterface;
use ItPack\RunIt\Contract\Application\Publication\SamplingPolicyInterface;
use ItPack\RunIt\Contract\Domain\SecurityLimitsInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Contract\Port\HealthAwarePublisherStackFactoryInterface;
use ItPack\RunIt\Contract\Port\PublisherStackFactoryInterface;
use ItPack\RunIt\Domain\Shared\SecurityLimits;

/**
 * Default {@see PublisherStackFactoryInterface}.
 *
 * Artifacts are verified and classified before reaching sampling or the sink.
 * Without a shared provenance authority, artifacts cannot be verified and are
 * reported and dropped.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class PublisherStackFactory implements HealthAwarePublisherStackFactoryInterface
{
    public function composeWithHealth(
        ArtifactPublisherInterface $base,
        ?RedactorInterface $redactor,
        ?SamplingPolicyInterface $samplingPolicy,
        InternalHealthReporterInterface $health,
    ): ArtifactPublisherInterface {
        return $this->compose($base, $redactor, $samplingPolicy, $health);
    }

    public function compose(
        ArtifactPublisherInterface $base,
        ?RedactorInterface $redactor,
        ?SamplingPolicyInterface $samplingPolicy,
        ?InternalHealthReporterInterface $health = null,
        ?SecurityLimitsInterface $limits = null,
        ?ProvenanceAuthority $authority = null,
    ): ArtifactPublisherInterface {
        $health ??= new ErrorLogHealthReporter();
        $authority ??= new ProvenanceAuthority($health);
        $composed = $base;

        // The innermost classifier bounds the projection before sink delivery;
        // bounded fields are not necessarily safe for every custom composition.
        $composed = new ClassifyingArtifactPublisher($composed, $health, $limits ?? new SecurityLimits());

        // Sampling runs after redaction and the default SDK classifiers.
        if ($samplingPolicy instanceof SamplingPolicyInterface) {
            $composed = new SamplingArtifactPublisher($samplingPolicy, $composed, $health);
        }

        // Classification is independent of the configured redactor.
        $composed = new DefaultObservationClassifier($composed, $health, $limits ?? new SecurityLimits());
        $composed = new DefaultOccurrenceClassifier($composed, $health, $limits ?? new SecurityLimits());
        $composed = new DefaultMeasurementClassifier($composed, $health);
        $composed = new DefaultLifecycleClassifier($composed, $health, $limits ?? new SecurityLimits());

        // Redaction runs outermost so the redactor sees the concrete artifact
        // type (SnapshotInterface, ObservationInterface, etc.) and can mask
        // throwable messages, observation messages, and sensitive keys.
        $composed = new RedactingArtifactPublisher($redactor, $composed, authority: $authority);

        return $composed;
    }
}
