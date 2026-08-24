<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use ItPack\RunIt\Behavior\Application\Publication\DelegatesPublisherLifecycleBehavior;
use ItPack\RunIt\Contract\Application\Publication\InternalHealthReporterInterface;
use ItPack\RunIt\Contract\Application\Publication\SamplingPolicyInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Contract\Port\CloseablePublisherInterface;
use ItPack\RunIt\Contract\Port\FlushablePublisherInterface;

/**
 * Artifact publisher that drops artifacts the sampling policy rejects.
 *
 * Sampling runs after filtering and redaction, so the pipeline pays the volume cost
 * only for artifacts it will actually emit. A dropped artifact is optionally reported
 * to the internal health reporter so drop counts stay observable without re-entering the
 * guarded publisher.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final readonly class SamplingArtifactPublisher implements ArtifactPublisherInterface, FlushablePublisherInterface, CloseablePublisherInterface
{
    use DelegatesPublisherLifecycleBehavior;

    public function __construct(
        private SamplingPolicyInterface          $policy,
        private ArtifactPublisherInterface       $inner,
        private ?InternalHealthReporterInterface $health = null,
    ) {
    }

    public function publish(ExecutionArtifactInterface $artifact): void
    {
        if ($this->policy->sample($artifact)) {
            $this->inner->publish($artifact);

            return;
        }

        $this->health?->report(SamplingDropException::forArtifact(), $artifact);
    }

}
