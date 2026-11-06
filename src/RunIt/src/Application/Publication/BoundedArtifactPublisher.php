<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use Closure;
use ItPack\RunIt\Behavior\Application\Publication\DelegatesPublisherLifecycleBehavior;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\ExecutionOwnedInterface;
use ItPack\RunIt\Contract\Domain\OccurrenceInterface;
use ItPack\RunIt\Contract\Domain\SnapshotInterface;
use ItPack\RunIt\Contract\Port\ArtifactByteEstimatorInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Contract\Port\CloseablePublisherInterface;
use ItPack\RunIt\Contract\Port\FlushablePublisherInterface;
use ItPack\RunIt\Domain\Shared\SecurityLimits;
use Throwable;

/**
 * Artifact publisher that enforces the data-protection bounds before delegating.
 *
 * It is the last gate before any external adapter: when an artifact would exceed
 * a configured bound (per-execution artifact count, occurrence `data` size, or
 * snapshot payload size) the artifact is dropped rather than published, and the
 * drop is reported through an optional callback so the failure path stays
 * observable. Dropping follows the publication pipeline's fail-open-with-fallback
 * posture: a single oversized artifact never replaces the business result or
 * aborts the execution.
 *
 * Payload sizes come from {@see ArtifactByteEstimatorInterface} (default:
 * {@see PayloadSectionByteEstimator}), measured natively instead of by encoding
 * every artifact on this hottest of paths. The per-execution count map is bounded
 * by {@see SecurityLimits::maxTrackedExecutions} (oldest entry evicted when exceeded,
 * deterministic insertion order) and an execution's counter is released as soon as
 * its terminal snapshot passes through, so the map cannot accumulate across
 * persistent-worker requests.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class BoundedArtifactPublisher implements
    ArtifactPublisherInterface,
    FlushablePublisherInterface,
    CloseablePublisherInterface
{
    use DelegatesPublisherLifecycleBehavior;

    private const string DROP_REASON_ARTIFACT_COUNT = 'artifact_count';

    private const string DROP_REASON_OCCURRENCE_PAYLOAD = 'occurrence_payload';

    private const string DROP_REASON_SNAPSHOT_PAYLOAD = 'snapshot_payload';

    /**
     * @var array<string, int>
     */
    private array $countsPerExecution = [];

    /**
     * @param Closure(string, ExecutionArtifactInterface): void|null $onDropped receives the bound that was exceeded
     */
    public function __construct(
        private readonly SecurityLimits $limits,
        private readonly ArtifactPublisherInterface $inner,
        private readonly ?Closure $onDropped = null,
        private readonly ArtifactByteEstimatorInterface $byteEstimator = new PayloadSectionByteEstimator(),
    ) {
    }

    public function publish(ExecutionArtifactInterface $artifact): void
    {
        $executionId = $artifact instanceof ExecutionOwnedInterface ? $artifact->executionId()->toString() : null;

        // A terminal snapshot ends its execution's artifact budget: the counter is
        // released on every exit path (published, payload-dropped, or count-dropped),
        // so an oversized terminal snapshot can never strand it until generic
        // eviction (Phase 1, T1.2).
        $isTerminalSnapshot = $artifact instanceof SnapshotInterface
            && $artifact->classification()->isTerminal();

        if ($executionId !== null) {
            $this->trackExecution($executionId);

            if ($this->countsPerExecution[$executionId] > $this->limits->maxArtifactsPerExecution) {
                if ($isTerminalSnapshot) {
                    unset($this->countsPerExecution[$executionId]);
                }

                $this->drop(self::DROP_REASON_ARTIFACT_COUNT, $artifact);

                return;
            }
        }

        if ($artifact instanceof OccurrenceInterface
            && $this->byteEstimator->estimate($artifact) > $this->limits->maxOccurrencePayloadBytes
        ) {
            $this->drop(self::DROP_REASON_OCCURRENCE_PAYLOAD, $artifact);

            return;
        }

        if ($artifact instanceof SnapshotInterface) {
            if ($isTerminalSnapshot && $executionId !== null) {
                unset($this->countsPerExecution[$executionId]);
            }

            if ($this->byteEstimator->estimate($artifact) > $this->limits->maxSnapshotPayloadBytes) {
                $this->drop(self::DROP_REASON_SNAPSHOT_PAYLOAD, $artifact);

                return;
            }
        }

        $this->inner->publish($artifact);
    }

    private function trackExecution(string $executionId): void
    {
        if (isset($this->countsPerExecution[$executionId])) {
            ++$this->countsPerExecution[$executionId];

            return;
        }

        if (count($this->countsPerExecution) >= $this->limits->maxTrackedExecutions) {
            $oldest = array_key_first($this->countsPerExecution);

            if (is_string($oldest)) {
                unset($this->countsPerExecution[$oldest]);
            }
        }

        $this->countsPerExecution[$executionId] = 1;
    }

    private function drop(string $reason, ExecutionArtifactInterface $artifact): void
    {
        if (!$this->onDropped instanceof Closure) {
            return;
        }

        try {
            ($this->onDropped)($reason, $artifact);
        } catch (Throwable $throwable) {
            // A throwing drop callback must never break publication flow: the
            // drop notice plus the handler fault land in the fallback sink.
            new FallbackErrorLogReporter()->report(BoundDropException::forReason($reason), $throwable);
        }
    }
}
