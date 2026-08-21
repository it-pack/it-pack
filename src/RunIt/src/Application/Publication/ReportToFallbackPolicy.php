<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use ItPack\RunIt\Contract\Application\Publication\InternalHealthReporterInterface;
use ItPack\RunIt\Contract\Application\Publication\PublicationFailurePolicyInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;

/**
 * Failure policy that reports the failed artifact to a fallback publisher.
 *
 * Used when the primary sink fails but a secondary sink (for example a local log file)
 * must still receive the artifact. Reporting is wrapped so a fallback failure is caught
 * and routed to the health reporter instead of recursing back through the guarded
 * pipeline; the original publication error is never allowed to replace the business result.
 *
 * @see https://w3id.org/itpack/runit#PublicationFailurePolicy
 */
final readonly class ReportToFallbackPolicy implements PublicationFailurePolicyInterface
{
    public function __construct(
        private ArtifactPublisherInterface       $fallback,
        private ?InternalHealthReporterInterface $health = null,
    ) {
    }

    public function handle(\Throwable $error, ExecutionArtifactInterface $artifact): void
    {
        try {
            $this->fallback->publish($artifact);
        } catch (\Throwable $fallbackError) {
            $this->health?->report($fallbackError, $artifact);
        }
    }
}
