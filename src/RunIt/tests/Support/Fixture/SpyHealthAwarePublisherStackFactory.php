<?php

declare(strict_types=1);

namespace Tests\Support\Fixture;

use ItPack\RunIt\Contract\Application\Publication\InternalHealthReporterInterface;
use ItPack\RunIt\Contract\Application\Publication\RedactorInterface;
use ItPack\RunIt\Contract\Application\Publication\SamplingPolicyInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Contract\Port\HealthAwarePublisherStackFactoryInterface;

/**
 * Health-aware publisher stack factory spy that captures the injected health reporter.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class SpyHealthAwarePublisherStackFactory implements HealthAwarePublisherStackFactoryInterface
{
    public ?InternalHealthReporterInterface $health = null;

    public function compose(
        ArtifactPublisherInterface $base,
        ?RedactorInterface $redactor,
        ?SamplingPolicyInterface $samplingPolicy,
    ): ArtifactPublisherInterface {
        return $base;
    }

    public function composeWithHealth(
        ArtifactPublisherInterface $base,
        ?RedactorInterface $redactor,
        ?SamplingPolicyInterface $samplingPolicy,
        InternalHealthReporterInterface $health,
    ): ArtifactPublisherInterface {
        $this->health = $health;

        return $base;
    }
}
