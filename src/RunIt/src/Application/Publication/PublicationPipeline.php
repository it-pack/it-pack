<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use ItPack\RunIt\Application\Factory\PublisherStackFactory;
use ItPack\RunIt\Contract\Application\Publication\ArtifactFilterInterface;
use ItPack\RunIt\Contract\Application\Publication\InternalHealthReporterInterface;
use ItPack\RunIt\Contract\Application\Publication\PublicationFailurePolicyInterface;
use ItPack\RunIt\Contract\Application\Publication\PublicationGuardInterface;
use ItPack\RunIt\Contract\Application\Publication\RedactorInterface;
use ItPack\RunIt\Contract\Application\Publication\SamplingPolicyInterface;
use ItPack\RunIt\Contract\Port\ArtifactByteEstimatorInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Contract\Port\MonotonicClockInterface;
use ItPack\RunIt\Contract\Port\PublisherStackFactoryInterface;

/**
 * Composes the standard RunIt publication pipeline from its parts.
 *
 * The pipeline is built outside-in so a publish call flows through the stages in the
 * canonical order:
 *
 *     guard → fail-safe → filter → redact → sample → buffer → sink
 *
 * Filtering drops early (before any expensive mapping); redaction and sampling
 * run before the buffer. The redact/sample pair is delegated to the injected
 * {@see PublisherStackFactoryInterface} so the pipeline and the SDK builder share
 * one wrapping authority (REVIEW R-11), and the sink is the only required part;
 * every other stage is optional and skipped when omitted.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class PublicationPipeline implements PublicationPipelineInterface
{
    private readonly PublisherStackFactoryInterface $stackFactory;

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
        ?PublisherStackFactoryInterface $stackFactory = null,
    ) {
        $this->stackFactory = $stackFactory ?? new PublisherStackFactory();
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

        $publisher = $this->stackFactory->compose($publisher, $this->redactor, $this->sampling);

        if ($this->filter instanceof ArtifactFilterInterface) {
            $publisher = new FilteringArtifactPublisher($this->filter, $publisher);
        }

        $publisher = new FailSafeArtifactPublisher($publisher, $this->failurePolicy ?? new FailOpenPolicy());

        return new GuardedArtifactPublisher($this->guard ?? new PublicationGuard(), $publisher, $this->health);
    }
}
