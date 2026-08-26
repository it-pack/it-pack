<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use ItPack\RunIt\Behavior\Application\Publication\DelegatesPublisherLifecycleBehavior;
use ItPack\RunIt\Contract\Application\Publication\InternalHealthReporterInterface;
use ItPack\RunIt\Contract\Application\Publication\PublicationFailurePolicyInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Contract\Port\CloseablePublisherInterface;
use ItPack\RunIt\Contract\Port\FlushablePublisherInterface;

/**
 * Artifact publisher that applies a failure policy when the inner publisher throws.
 *
 * The decorator catches the original adapter exception and hands it to the policy. A
 * fail-open policy swallows it; a fail-closed policy rethrows it (the original adapter
 * error, never a business exception); a collect-and-continue policy records it; a
 * report-to-fallback policy forwards the artifact elsewhere. Whatever the policy does,
 * the original business exception and business result are preserved.
 *
 * The publisher also carries the default stack's recursion guard (Phase 0, T0.3): a
 * publish arriving while this publisher is already publishing on the same path is
 * routed to the internal health reporter as a {@see RecursivePublicationException}
 * instead of executing, so self-instrumentation can never loop back through the
 * pipeline.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class FailSafeArtifactPublisher implements
    ArtifactPublisherInterface,
    FlushablePublisherInterface,
    CloseablePublisherInterface
{
    use DelegatesPublisherLifecycleBehavior;

    private int $publishDepth = 0;

    public function __construct(
        private ArtifactPublisherInterface        $inner,
        private PublicationFailurePolicyInterface $policy,
        private ?InternalHealthReporterInterface  $health = null,
    ) {
    }

    public function publish(ExecutionArtifactInterface $artifact): void
    {
        if ($this->publishDepth > 0) {
            $this->health?->report(RecursivePublicationException::forNestedPublish(), $artifact);

            return;
        }

        ++$this->publishDepth;

        try {
            try {
                $this->inner->publish($artifact);
            } catch (\Throwable $publicationError) {
                // Report before the policy runs: a fail-closed policy rethrows,
                // and without this ordering its faults would be invisible to
                // the health channel (Phase 1, T1.2).
                $this->health?->report($publicationError, $artifact);

                $this->policy->handle($publicationError, $artifact);
            }
        } finally {
            --$this->publishDepth;
        }
    }

}
