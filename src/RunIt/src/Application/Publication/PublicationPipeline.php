<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use ItPack\RunIt\Contract\Application\Publication\ArtifactFilterInterface;
use ItPack\RunIt\Contract\Application\Publication\InternalHealthReporterInterface;
use ItPack\RunIt\Contract\Application\Publication\PublicationFailurePolicyInterface;
use ItPack\RunIt\Contract\Application\Publication\PublicationGuardInterface;
use ItPack\RunIt\Contract\Application\Publication\RedactorInterface;
use ItPack\RunIt\Contract\Application\Publication\SamplingPolicyInterface;
use ItPack\RunIt\Contract\Port\ArtifactByteEstimatorInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Contract\Port\MonotonicClockInterface;

/**
 * Composes the standard RunIt publication pipeline from its parts.
 *
 * The pipeline is built outside-in so a publish call flows through the stages in the
 * canonical order:
 *
 *     guard → fail-safe → filter → redact → sample → buffer → sink
 *
 * Filtering drops early (before any expensive mapping), redaction and sampling run
 * before the buffer, and the guard wraps the whole thing so self-observation and
 * health reporting can never recurse. The sink is the only required part; every other
 * stage is optional and skipped when omitted.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class PublicationPipeline implements PublicationPipelineInterface
{
    public function __construct(
        private readonly ArtifactPublisherInterface $sink,
        private readonly MonotonicClockInterface $clock,
        private readonly ArtifactByteEstimatorInterface $byteEstimator,
        private readonly ?ArtifactFilterInterface $filter = null,
        private readonly ?RedactorInterface $redactor = null,
        private readonly ?SamplingPolicyInterface $sampling = null,
        private readonly ?BufferConfiguration $buffer = null,
        private readonly ?PublicationFailurePolicyInterface $failurePolicy = null,
        private readonly ?InternalHealthReporterInterface $health = null,
        private readonly ?PublicationGuardInterface $guard = null,
    ) {
    }

    public function build(): ArtifactPublisherInterface
    {
        $publisher = $this->sink;

        if ($this->buffer instanceof BufferConfiguration) {
            $publisher = new BufferedArtifactPublisher(
                $this->buffer,
                $publisher,
                $this->clock,
                $this->byteEstimator,
                $this->health
            );
        }

        if ($this->sampling instanceof SamplingPolicyInterface) {
            $publisher = new SamplingArtifactPublisher($this->sampling, $publisher, $this->health);
        }

        if ($this->redactor instanceof RedactorInterface) {
            $publisher = new RedactingArtifactPublisher($this->redactor, $publisher);
        }

        if ($this->filter instanceof ArtifactFilterInterface) {
            $publisher = new FilteringArtifactPublisher($this->filter, $publisher);
        }

        $publisher = new FailSafeArtifactPublisher($publisher, $this->failurePolicy ?? new FailOpenPolicy());

        return new GuardedArtifactPublisher($this->guard ?? new PublicationGuard(), $publisher, $this->health);
    }
}
