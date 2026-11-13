<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Factory;

use ItPack\RunIt\Application\Publication\ClassifyingArtifactPublisher;
use ItPack\RunIt\Application\Publication\ProvenanceAuthority;
use ItPack\RunIt\Application\Publication\RedactingArtifactPublisher;
use ItPack\RunIt\Application\Publication\SamplingArtifactPublisher;
use ItPack\RunIt\Contract\Application\Publication\InternalHealthReporterInterface;
use ItPack\RunIt\Contract\Application\Publication\RedactorInterface;
use ItPack\RunIt\Contract\Application\Publication\SamplingPolicyInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Contract\Port\HealthAwarePublisherStackFactoryInterface;
use ItPack\RunIt\Contract\Port\PublisherStackFactoryInterface;
use ItPack\RunIt\Domain\Shared\SecurityLimits;

/**
 * Default {@see PublisherStackFactoryInterface}.
 *
 * The redactor (when configured) wraps the sampling layer, so publish calls
 * reach the redactor before the sampler. The default redactor masks observation
 * messages and throwable details as well as explicitly configured sensitive keys.
 * Other fields can pass through unchanged, so this order alone does not make
 * every sampler input safe.
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
        ?SecurityLimits $limits = null,
        ?ProvenanceAuthority $authority = null,
    ): ArtifactPublisherInterface {
        $composed = $base;

        // Classification is innermost: it bounds the redacted result before the
        // sink sees it. Even artifacts that pass the deny-list redactor
        // unchanged are projected to a safe, bounded form.
        if ($redactor instanceof RedactorInterface || $samplingPolicy instanceof SamplingPolicyInterface
            || $authority instanceof ProvenanceAuthority) {
            $composed = new ClassifyingArtifactPublisher($composed, $health, $limits ?? new SecurityLimits());
        }

        // Sampling runs after redaction so the sampler sees redacted artifacts,
        // not originals with sensitive values.
        if ($samplingPolicy instanceof SamplingPolicyInterface) {
            $composed = new SamplingArtifactPublisher($samplingPolicy, $composed, $health);
        }

        // Redaction runs outermost so the redactor sees the concrete artifact
        // type (SnapshotInterface, ObservationInterface, etc.) and can mask
        // throwable messages, observation messages, and sensitive keys.
        if ($redactor instanceof RedactorInterface || $authority instanceof ProvenanceAuthority) {
            $composed = new RedactingArtifactPublisher($redactor, $composed, authority: $authority);
        }

        return $composed;
    }
}
