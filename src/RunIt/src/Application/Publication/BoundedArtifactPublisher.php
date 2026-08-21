<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use Closure;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\ExecutionOwnedInterface;
use ItPack\RunIt\Contract\Domain\OccurrenceInterface;
use ItPack\RunIt\Contract\Domain\SnapshotInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Contract\Port\CloseablePublisherInterface;
use ItPack\RunIt\Contract\Port\FlushablePublisherInterface;
use ItPack\RunIt\Domain\Shared\ArtifactField;
use ItPack\RunIt\Domain\Shared\SecurityLimits;

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
 * @see https://w3id.org/itpack/runit#Execution
 */
final class BoundedArtifactPublisher implements ArtifactPublisherInterface, FlushablePublisherInterface, CloseablePublisherInterface
{
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
    ) {
    }

    public function publish(ExecutionArtifactInterface $artifact): void
    {
        if ($artifact instanceof ExecutionOwnedInterface) {
            $executionId = $artifact->executionId()->toString();

            $this->countsPerExecution[$executionId] = ($this->countsPerExecution[$executionId] ?? 0) + 1;

            if ($this->countsPerExecution[$executionId] > $this->limits->maxArtifactsPerExecution) {
                $this->drop('artifact_count', $artifact);

                return;
            }
        }

        if ($artifact instanceof OccurrenceInterface) {
            $bytes = strlen((string) json_encode($artifact->normalize()[ArtifactField::DATA] ?? null));
            if ($bytes > $this->limits->maxOccurrencePayloadBytes) {
                $this->drop('occurrence_payload', $artifact);

                return;
            }
        }

        if ($artifact instanceof SnapshotInterface) {
            $bytes = strlen((string) json_encode($artifact->payload()));
            if ($bytes > $this->limits->maxSnapshotPayloadBytes) {
                $this->drop('snapshot_payload', $artifact);

                return;
            }
        }

        $this->inner->publish($artifact);
    }

    public function flush(): void
    {
        if ($this->inner instanceof FlushablePublisherInterface) {
            $this->inner->flush();
        }
    }

    public function close(): void
    {
        if ($this->inner instanceof CloseablePublisherInterface) {
            $this->inner->close();
        }
    }

    private function drop(string $reason, ExecutionArtifactInterface $artifact): void
    {
        if ($this->onDropped !== null) {
            ($this->onDropped)($reason, $artifact);
        }
    }
}
