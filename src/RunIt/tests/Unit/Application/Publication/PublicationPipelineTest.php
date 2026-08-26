<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Publication;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\Common\DefaultArtifactByteEstimator;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Application\Publication\ArtifactRedactionConfig;
use ItPack\RunIt\Application\Publication\ArtifactTypeFilter;
use ItPack\RunIt\Application\Publication\BufferConfiguration;
use ItPack\RunIt\Application\Publication\CallbackArtifactPublisher;
use ItPack\RunIt\Application\Publication\DefaultArtifactRedactor;
use ItPack\RunIt\Application\Publication\FailClosedPolicy;
use ItPack\RunIt\Application\Publication\GuardedArtifactPublisher;
use ItPack\RunIt\Application\Publication\NeverSamplePolicy;
use ItPack\RunIt\Application\Publication\ObservationSeverityFilter;
use ItPack\RunIt\Application\Publication\OverflowStrategy;
use ItPack\RunIt\Application\Publication\PublicationPipeline;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Port\ArtifactByteEstimatorInterface;
use ItPack\RunIt\Contract\Port\FlushablePublisherInterface;
use ItPack\RunIt\Contract\Port\MonotonicClockInterface;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Observation\Observation;
use ItPack\RunIt\Domain\Observation\ObservationId;
use ItPack\RunIt\Domain\Occurrence\Occurrence;
use ItPack\RunIt\Domain\Occurrence\OccurrenceId;
use ItPack\RunIt\Domain\Occurrence\OccurrenceType;
use ItPack\RunIt\Domain\Shared\AttributeCollection;
use ItPack\RunIt\Domain\Shared\Instant;
use RuntimeException;

/**
 * @group application
 * @group publication
 */
final class PublicationPipelineTest extends Unit
{
    private MonotonicClockInterface $clock;
    private ArtifactByteEstimatorInterface $estimator;

    protected function setUp(): void
    {
        $this->clock = new SystemMonotonicClock();
        $this->estimator = new DefaultArtifactByteEstimator();
    }
    private function observation(): ExecutionArtifactInterface
    {
        return Observation::info(
            ObservationId::fromString('obs'),
            ExecutionId::fromString('exec-1'),
            'login',
            null,
            null,
            AttributeCollection::fromArray(['user' => 'alice', 'password' => 'hunter2']),
        );
    }

    private function occurrence(): ExecutionArtifactInterface
    {
        return Occurrence::create(
            OccurrenceId::fromString('occ'),
            ExecutionId::fromString('exec-1'),
            OccurrenceType::fromString('itpack.test.event.v1'),
            'source',
            Instant::of(1000),
        );
    }

    public function testBuildWrapsTheSinkWithBufferAndSamplingWhenConfigured(): void
    {
        $pipeline = new PublicationPipeline(
            new InMemoryArtifactPublisher(),
            $this->clock,
            $this->estimator,
            sampling: new NeverSamplePolicy(),
            buffer: new BufferConfiguration(
                maxItems: 8,
                maxEstimatedBytes: 10_000,
                maxAgeSeconds: 60,
                batchSize: 4,
                flushIntervalSeconds: 0,
                shutdownFlushBudget: 1024,
                overflow: OverflowStrategy::DropOldest,
            ),
        );

        $publisher = $pipeline->build();

        self::assertInstanceOf(GuardedArtifactPublisher::class, $publisher, 'the guard is the outermost stage');
        self::assertInstanceOf(FlushablePublisherInterface::class, $publisher);

        $publisher->publish($this->observation());
        $publisher->flush();

        self::assertSame([], new InMemoryArtifactPublisher()->published(), 'sampling dropped everything before the sink');
    }

    public function testPipelineFiltersRedactsAndPublishes(): void
    {
        $sink = new InMemoryArtifactPublisher();
        $pipeline = new PublicationPipeline(
            sink: $sink,
            clock: $this->clock,
            byteEstimator: $this->estimator,
            filter: new ArtifactTypeFilter([Observation::class]),
            redactor: new DefaultArtifactRedactor(new ArtifactRedactionConfig(sensitiveAttributeKeys: ['password'])),
        )->build();

        $pipeline->publish($this->observation());
        $pipeline->publish($this->occurrence());

        self::assertCount(1, $sink->published(), 'The occurrence is filtered out before the sink.');
        /** @var array<string, mixed> $normalized */
        $normalized = $sink->published()[0]->normalize();
        /** @var array<string, mixed> $attrs */
        $attrs = $normalized['attributes'];
        self::assertSame('[redacted]', $attrs['password']);
    }

    public function testPipelineFailClosedPropagates(): void
    {
        $sink = new CallbackArtifactPublisher(static function (): void {
            throw new RuntimeException('transport down');
        });
        $pipeline = new PublicationPipeline(
            sink: $sink,
            clock: $this->clock,
            byteEstimator: $this->estimator,
            failurePolicy: new FailClosedPolicy(),
        )->build();

        $thrown = null;
        try {
            $pipeline->publish($this->observation());
        } catch (RuntimeException $error) {
            $thrown = $error;
        }

        self::assertNotNull($thrown);
    }

    public function testPipelineFailOpenSwallows(): void
    {
        $sink = new InMemoryArtifactPublisher();
        $pipeline = new PublicationPipeline($sink, $this->clock, $this->estimator)->build();

        $pipeline->publish($this->observation());

        self::assertCount(1, $sink->published());
    }

    public function testPipelineSeverityFloorDropsNoiseBeforeSink(): void
    {
        $sink = new InMemoryArtifactPublisher();
        $pipeline = new PublicationPipeline(
            sink: $sink,
            clock: $this->clock,
            byteEstimator: $this->estimator,
            filter: new ObservationSeverityFilter(400),
        )->build();

        $pipeline->publish(Observation::debug(ObservationId::fromString('d'), ExecutionId::fromString('exec-1'), 'noise'));
        $pipeline->publish(Observation::error(ObservationId::fromString('e'), ExecutionId::fromString('exec-1'), 'boom'));

        self::assertCount(1, $sink->published());
    }
}
