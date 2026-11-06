<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Factory;

use ItPack\RunIt\Application\Publication\RedactingArtifactPublisher;
use ItPack\RunIt\Application\Publication\SamplingArtifactPublisher;
use ItPack\RunIt\Contract\Application\Publication\RedactorInterface;
use ItPack\RunIt\Contract\Application\Publication\SamplingPolicyInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Contract\Port\PublisherStackFactoryInterface;

/**
 * Default {@see PublisherStackFactoryInterface}.
 *
 * Mirrors the publisher-stack wiring previously inlined in
 * {@see \ItPack\RunIt\SDK\Builder\RunItBuilder::build()}: the redactor (when
 * configured) wraps the base publisher, and the sampler (when configured) wraps
 * the result. Publish calls therefore reach the sampler before the redactor.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class PublisherStackFactory implements PublisherStackFactoryInterface
{
    public function compose(
        ArtifactPublisherInterface $base,
        ?RedactorInterface $redactor,
        ?SamplingPolicyInterface $samplingPolicy,
    ): ArtifactPublisherInterface {
        $composed = $base;

        if ($redactor instanceof RedactorInterface) {
            $composed = new RedactingArtifactPublisher($redactor, $composed);
        }

        if ($samplingPolicy instanceof SamplingPolicyInterface) {
            $composed = new SamplingArtifactPublisher($samplingPolicy, $composed);
        }

        return $composed;
    }
}
