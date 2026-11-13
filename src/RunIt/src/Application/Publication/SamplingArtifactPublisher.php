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
use ItPack\RunIt\Domain\Shared\ArtifactField;
use RuntimeException;
use Throwable;

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
final class SamplingArtifactPublisher implements
    ArtifactPublisherInterface,
    FlushablePublisherInterface,
    CloseablePublisherInterface
{
    use DelegatesPublisherLifecycleBehavior;

    private bool $publishingHealthReport = false;

    public function __construct(
        private readonly SamplingPolicyInterface          $policy,
        private readonly ArtifactPublisherInterface       $inner,
        private readonly ?InternalHealthReporterInterface $health = null,
    ) {
    }

    public function publish(ExecutionArtifactInterface $artifact): void
    {
        if ($this->policy->sample($artifact)) {
            $this->inner->publish($artifact);

            return;
        }

        if (!$this->health instanceof InternalHealthReporterInterface || $this->publishingHealthReport) {
            return;
        }

        try {
            $this->publishingHealthReport = true;
            $this->health->report(SamplingDropException::forArtifact(), new RedactedArtifact([
                ArtifactField::ID => 'redacted',
                ArtifactField::TYPE => 'runit.artifact.redacted',
            ]));
        } catch (Throwable $throwable) {
            // Preserve fail-open containment: contain the reporter fault and attempt
            // an independent, bounded, payload-free fallback notice so the sampling
            // drop is not silently lost. Contain fallback failures too.
            try {
                new FallbackErrorLogReporter()->report(
                    SamplingDropException::forArtifact(),
                    new RuntimeException('Health reporter failed: ' . $throwable::class),
                );
            } catch (Throwable) {
                // No further independent sink remains; preserve the business outcome.
            }
        } finally {
            $this->publishingHealthReport = false;
        }
    }

}
