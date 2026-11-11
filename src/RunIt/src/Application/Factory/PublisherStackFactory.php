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
 * The redactor (when configured) wraps the sampling layer, so publish calls
 * reach the redactor before the sampler. The default redactor masks observation
 * messages and throwable details as well as explicitly configured sensitive keys.
 * Other fields can pass through unchanged, so this order alone does not make
 * every sampler input safe.
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

        if ($samplingPolicy instanceof SamplingPolicyInterface) {
            $composed = new SamplingArtifactPublisher($samplingPolicy, $composed);
        }

        if ($redactor instanceof RedactorInterface) {
            $composed = new RedactingArtifactPublisher($redactor, $composed);
        }

        return $composed;
    }
}
