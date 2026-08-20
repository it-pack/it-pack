<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Publication;

use Codeception\Test\Unit;
use ItPack\RunIt\Application\Publication\BoundedArtifactPublisher;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Occurrence\Occurrence;
use ItPack\RunIt\Domain\Occurrence\OccurrenceId;
use ItPack\RunIt\Domain\Occurrence\OccurrenceType;
use ItPack\RunIt\Domain\Shared\DeterministicHashCalculator;
use ItPack\RunIt\Domain\Shared\Instant;
use ItPack\RunIt\Domain\Shared\SecurityLimits;
use ItPack\RunIt\Domain\Snapshot\Snapshot;
use ItPack\RunIt\Domain\Snapshot\SnapshotId;
use ItPack\RunIt\Domain\Snapshot\SnapshotType;

/**
 * @group application
 * @group security
 */
final class BoundedArtifactPublisherTest extends Unit
{
    private RecordingPublisher $inner;

    protected function setUp(): void
    {
        $this->inner = new RecordingPublisher();
    }

    private function occurrence(mixed $data): Occurrence
    {
        return Occurrence::create(
            OccurrenceId::fromString('occ-1'),
            ExecutionId::fromString('exec-1'),
            OccurrenceType::fromString('test.event.v1'),
            'source',
            Instant::of(1000),
            $data,
        );
    }

    private function snapshot(mixed $payload): Snapshot
    {
        return Snapshot::create(
            SnapshotId::fromString('snap-1'),
            ExecutionId::fromString('exec-1'),
            SnapshotType::fromString('execution.snapshot'),
            1,
            $payload,
            new DeterministicHashCalculator(),
        );
    }

    public function testOccurrenceWithinLimitIsPublished(): void
    {
        $publisher = new BoundedArtifactPublisher(new SecurityLimits(), $this->inner);

        $publisher->publish($this->occurrence(['k' => 'v']));

        self::assertCount(1, $this->inner->published());
    }

    public function testOversizedOccurrencePayloadIsDropped(): void
    {
        $publisher = new BoundedArtifactPublisher(new SecurityLimits(maxOccurrencePayloadBytes: 5), $this->inner);

        $publisher->publish($this->occurrence(['big' => str_repeat('x', 1000)]));

        self::assertCount(0, $this->inner->published());
    }

    public function testOversizedSnapshotPayloadIsDropped(): void
    {
        $publisher = new BoundedArtifactPublisher(new SecurityLimits(maxSnapshotPayloadBytes: 5), $this->inner);

        $publisher->publish($this->snapshot(['big' => str_repeat('x', 1000)]));

        self::assertCount(0, $this->inner->published());
    }

    public function testPerExecutionArtifactCountIsEnforced(): void
    {
        $publisher = new BoundedArtifactPublisher(new SecurityLimits(maxArtifactsPerExecution: 2), $this->inner);

        $publisher->publish($this->occurrence(['n' => 1]));
        $publisher->publish($this->occurrence(['n' => 2]));
        $publisher->publish($this->occurrence(['n' => 3]));

        self::assertCount(2, $this->inner->published());
    }

    public function testDropReasonIsReported(): void
    {
        $dropped = [];
        $publisher = new BoundedArtifactPublisher(
            new SecurityLimits(maxOccurrencePayloadBytes: 5),
            $this->inner,
            static function (string $reason, ExecutionArtifactInterface $artifact) use (&$dropped): void {
                $dropped[] = $reason;
            },
        );

        $publisher->publish($this->occurrence(['big' => str_repeat('x', 1000)]));

        self::assertSame(['occurrence_payload'], $dropped);
    }
}

final class RecordingPublisher implements ArtifactPublisherInterface
{
    /** @var list<ExecutionArtifactInterface> */
    private array $published = [];

    public function publish(ExecutionArtifactInterface $artifact): void
    {
        $this->published[] = $artifact;
    }

    /** @return list<ExecutionArtifactInterface> */
    public function published(): array
    {
        return $this->published;
    }
}
