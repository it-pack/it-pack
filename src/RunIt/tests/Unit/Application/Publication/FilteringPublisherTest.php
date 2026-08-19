<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Publication;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Application\Publication\ArtifactTypeFilter;
use ItPack\RunIt\Application\Publication\CompositeArtifactFilter;
use ItPack\RunIt\Application\Publication\ExecutionKindFilter;
use ItPack\RunIt\Application\Publication\FilteringArtifactPublisher;
use ItPack\RunIt\Application\Publication\ObservationSeverityFilter;
use ItPack\RunIt\Application\Publication\OccurrenceTypeFilter;
use ItPack\RunIt\Application\Publication\PublicationRoute;
use ItPack\RunIt\Application\Publication\RoutingArtifactPublisher;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Measurement\Measurement;
use ItPack\RunIt\Domain\Measurement\MeasurementName;
use ItPack\RunIt\Domain\Observation\Observation;
use ItPack\RunIt\Domain\Observation\ObservationId;
use ItPack\RunIt\Domain\Observation\ObservationSeverity;
use ItPack\RunIt\Domain\Occurrence\Occurrence;
use ItPack\RunIt\Domain\Occurrence\OccurrenceId;
use ItPack\RunIt\Domain\Occurrence\OccurrenceType;
use ItPack\RunIt\Domain\Shared\DeterministicHashCalculator;
use ItPack\RunIt\Domain\Shared\Instant;
use ItPack\RunIt\Domain\Snapshot\ExecutionSnapshot;
use ItPack\RunIt\Domain\Snapshot\SnapshotId;
use ItPack\RunIt\Domain\Snapshot\SnapshotType;

/**
 * @group application
 * @group publication
 */
final class FilteringPublisherTest extends Unit
{
    private function observation(string $severity = 'info', string $execution = 'exec-1'): ExecutionArtifactInterface
    {
        $id = ObservationId::fromString('obs');
        $executionId = ExecutionId::fromString($execution);

        return match ($severity) {
            'debug' => Observation::debug($id, $executionId, 'msg'),
            'info' => Observation::info($id, $executionId, 'msg'),
            'notice' => Observation::notice($id, $executionId, 'msg'),
            'warning' => Observation::warning($id, $executionId, 'msg'),
            'error' => Observation::error($id, $executionId, 'msg'),
            'critical' => Observation::critical($id, $executionId, 'msg'),
            'alert' => Observation::alert($id, $executionId, 'msg'),
            'emergency' => Observation::emergency($id, $executionId, 'msg'),
            default => Observation::info($id, $executionId, 'msg'),
        };
    }

    private function occurrence(string $execution = 'exec-1'): ExecutionArtifactInterface
    {
        return Occurrence::create(
            OccurrenceId::fromString('occ'),
            ExecutionId::fromString($execution),
            OccurrenceType::fromString('itpack.test.event.v1'),
            'source',
            Instant::of(1000),
            ['user' => 'x'],
        );
    }

    private function measurement(string $execution = 'exec-1'): ExecutionArtifactInterface
    {
        return Measurement::counter(MeasurementName::fromString('files'), ExecutionId::fromString($execution), 3);
    }

    private function snapshot(string $execution = 'exec-1'): ExecutionArtifactInterface
    {
        return ExecutionSnapshot::create(
            SnapshotId::fromString('snap'),
            ExecutionId::fromString($execution),
            SnapshotType::fromString('test.snapshot'),
            1,
            ['state' => 'ok'],
            new DeterministicHashCalculator(),
        );
    }

    public function testArtifactTypeFilter(): void
    {
        $sink = new InMemoryArtifactPublisher();
        $publisher = new FilteringArtifactPublisher(new ArtifactTypeFilter([Observation::class]), $sink);

        $publisher->publish($this->observation());
        $publisher->publish($this->occurrence());
        $publisher->publish($this->measurement());

        self::assertCount(1, $sink->published());
    }

    public function testExecutionKindFilter(): void
    {
        $sink = new InMemoryArtifactPublisher();
        $resolver = static fn (ExecutionIdInterface $id): ?ExecutionKind => match ($id->toString()) {
            'exec-1' => ExecutionKind::fromString('http.request'),
            default => null,
        };

        $publisher = new FilteringArtifactPublisher(new ExecutionKindFilter($resolver, ['http.request']), $sink);

        $publisher->publish($this->observation('info', 'exec-1'));
        $publisher->publish($this->observation('info', 'exec-2'));

        self::assertCount(1, $sink->published());
    }

    public function testObservationSeverityFilterDropsNoise(): void
    {
        $sink = new InMemoryArtifactPublisher();
        $publisher = new FilteringArtifactPublisher(new ObservationSeverityFilter(ObservationSeverity::Warning->level()), $sink);

        $publisher->publish($this->observation('debug'));
        $publisher->publish($this->observation('info'));
        $publisher->publish($this->observation('warning'));
        $publisher->publish($this->observation('error'));
        $publisher->publish($this->measurement());

        self::assertCount(3, $sink->published());
    }

    public function testOccurrenceTypeFilter(): void
    {
        $sink = new InMemoryArtifactPublisher();
        $publisher = new FilteringArtifactPublisher(new OccurrenceTypeFilter(['itpack.test.event.v1']), $sink);

        $publisher->publish($this->occurrence());
        $publisher->publish($this->observation());

        self::assertCount(1, $sink->published());
    }

    public function testCompositeFilterRequiresAll(): void
    {
        $sink = new InMemoryArtifactPublisher();
        $filter = new CompositeArtifactFilter([
            new ArtifactTypeFilter([Observation::class]),
            new ObservationSeverityFilter(ObservationSeverity::Warning->level()),
        ]);

        $publisher = new FilteringArtifactPublisher($filter, $sink);

        $publisher->publish($this->observation('info'));
        $publisher->publish($this->observation('warning'));
        $publisher->publish($this->occurrence());

        self::assertCount(1, $sink->published());
    }

    public function testRoutingSplitsAndFallsBack(): void
    {
        $observations = new InMemoryArtifactPublisher();
        $occurrences = new InMemoryArtifactPublisher();
        $fallback = new InMemoryArtifactPublisher();

        $publisher = new RoutingArtifactPublisher([
            new PublicationRoute(new OccurrenceTypeFilter(['itpack.test.event.v1']), $occurrences),
            new PublicationRoute(new ArtifactTypeFilter([Observation::class]), $observations),
        ], $fallback);

        $publisher->publish($this->occurrence());
        $publisher->publish($this->observation('info'));
        $publisher->publish($this->measurement());

        self::assertCount(1, $occurrences->published());
        self::assertCount(1, $observations->published());
        self::assertCount(1, $fallback->published());
    }

    public function testSnapshotPassesGenericFilters(): void
    {
        $sink = new InMemoryArtifactPublisher();
        $publisher = new FilteringArtifactPublisher(new ObservationSeverityFilter(ObservationSeverity::Warning->level()), $sink);

        $publisher->publish($this->snapshot());

        self::assertCount(1, $sink->published());
    }
}
