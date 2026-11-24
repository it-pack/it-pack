<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Recorder;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Application\Publication\ProvenanceAuthority;
use ItPack\RunIt\Application\Recorder\DefaultMeasurementRecorder;
use ItPack\RunIt\Contract\Domain\ActivityIdInterface;
use ItPack\RunIt\Contract\Domain\MeasurementInterface;
use ItPack\RunIt\Contract\Domain\SequenceAllocatorInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Contract\Port\MonotonicClockInterface;
use ItPack\RunIt\Domain\Activity\ActivityDefinition;
use ItPack\RunIt\Domain\Activity\ActivityId;
use ItPack\RunIt\Domain\Activity\ActivityKind;
use ItPack\RunIt\Domain\Activity\ActivityName;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Support\Fixture\DeterministicMonotonicClock;
use Tests\Support\Fixture\RecordingArtifactPublisher;
use Tests\Support\Fixture\RunItTestKit;

/**
 * @group application
 * @group recorder
 */
#[CoversClass(DefaultMeasurementRecorder::class)]
final class DefaultMeasurementRecorderTest extends Unit
{
    private RunItTestKit $kit;

    protected function setUp(): void
    {
        $this->kit = new RunItTestKit();
    }

    /**
     * @return list<MeasurementInterface>
     */
    private function measurements(): array
    {
        return $this->kit->artifactsOf(MeasurementInterface::class);
    }

    public function testExecutionLevelMeasurementBindsExecutionAndPublishes(): void
    {
        $this->kit->runner->run(
            ExecutionDefinition::create(ExecutionName::fromString('op'), ExecutionKind::fromString('task')),
            function ($scope): null {
                $scope->measurements()->counter('hits', 3);

                return null;
            },
        );

        $measurements = $this->measurements();
        self::assertCount(1, $measurements);
        self::assertSame('hits', $measurements[0]->name()->toString());
        self::assertSame(3, $measurements[0]->value());
        self::assertNull($measurements[0]->activityId(), 'Execution-level measurements must not bind an activity.');
    }

    public function testActivityLevelMeasurementBindsActivity(): void
    {
        $this->kit->runner->run(
            ExecutionDefinition::create(ExecutionName::fromString('op'), ExecutionKind::fromString('task')),
            function ($scope): null {
                $scope->activities()->run(
                    ActivityDefinition::create(
                        ActivityName::fromString('load'),
                        ActivityKind::fromString('io')
                    ),
                    function ($activityScope): null {
                        $activityScope->measurements()->gauge('latency', 12.5);

                        return null;
                    },
                );

                return null;
            },
        );

        $measurements = $this->measurements();
        self::assertCount(1, $measurements);
        self::assertNotNull($measurements[0]->activityId(), 'Activity-level measurements must bind the activity.');
    }

    public function testDurationMeasurementIsPublished(): void
    {
        $publisher = new RecordingArtifactPublisher();
        $recorder = $this->recorder($publisher);
        $measurement = $recorder->duration('latency', 12.5);

        self::assertInstanceOf(MeasurementInterface::class, $measurement);
        self::assertSame('latency', $measurement->name()->toString());
        self::assertSame(12.5, $measurement->value());
        self::assertSame(1, count($publisher->published()));
    }

    public function testSizeMeasurementIsPublished(): void
    {
        $publisher = new RecordingArtifactPublisher();
        $recorder = $this->recorder($publisher);
        $measurement = $recorder->size('bytes', 1024);

        self::assertInstanceOf(MeasurementInterface::class, $measurement);
        self::assertSame('bytes', $measurement->name()->toString());
        self::assertSame(1024, $measurement->value());
        self::assertSame(1, count($publisher->published()));
    }

    public function testRatioMeasurementIsPublished(): void
    {
        $publisher = new RecordingArtifactPublisher();
        $recorder = $this->recorder($publisher);
        $measurement = $recorder->ratio('ratio', 0.75);

        self::assertInstanceOf(MeasurementInterface::class, $measurement);
        self::assertSame('ratio', $measurement->name()->toString());
        self::assertSame(0.75, $measurement->value());
        self::assertSame(1, count($publisher->published()));
    }

    public function testShouldDropSkipsPublishing(): void
    {
        $publisher = new RecordingArtifactPublisher();
        $recorder = new DefaultMeasurementRecorder(
            ExecutionId::fromString('exec-1'),
            fn (): null => null,
            $this->allocator(),
            $this->clock(),
            $publisher,
            authority: new ProvenanceAuthority(),
            shouldDrop: fn (): bool => true,
        );

        $measurement = $recorder->counter('hits', 1);

        self::assertSame(0, count($publisher->published()));
        self::assertInstanceOf(MeasurementInterface::class, $measurement);
    }

    public function testActivityExportIdPathIsUsed(): void
    {
        $publisher = new RecordingArtifactPublisher();
        $activityId = ActivityId::fromString('act-1');
        $recorder = new DefaultMeasurementRecorder(
            ExecutionId::fromString('exec-1'),
            fn (): null => null,
            $this->allocator(),
            $this->clock(),
            $publisher,
            authority: new ProvenanceAuthority(),
            verifiedActivityExportId: fn (): array => [true, $activityId, 'export-1'],
        );

        $measurement = $recorder->counter('hits', 1);

        self::assertSame('act-1', $measurement->activityId()?->toString());
        self::assertSame(1, count($publisher->published()));
    }

    public function testLegacyActivityPathReturnsActivityId(): void
    {
        $publisher = new RecordingArtifactPublisher();
        $recorder = new DefaultMeasurementRecorder(
            ExecutionId::fromString('exec-1'),
            fn (): ActivityIdInterface => ActivityId::fromString('act-1'),
            $this->allocator(),
            $this->clock(),
            $publisher,
            authority: new ProvenanceAuthority(),
        );

        $measurement = $recorder->counter('hits', 1);

        self::assertSame('act-1', $measurement->activityId()?->toString());
        self::assertSame(1, count($publisher->published()));
    }

    private function recorder(?ArtifactPublisherInterface $publisher = null): DefaultMeasurementRecorder
    {
        return new DefaultMeasurementRecorder(
            ExecutionId::fromString('exec-1'),
            fn (): null => null,
            $this->allocator(),
            $this->clock(),
            $publisher ?? new RecordingArtifactPublisher(),
            authority: new ProvenanceAuthority(),
        );
    }

    private function allocator(): SequenceAllocatorInterface
    {
        return new InMemorySequenceAllocator();
    }

    private function clock(): MonotonicClockInterface
    {
        return new DeterministicMonotonicClock(1_000, 0);
    }
}
