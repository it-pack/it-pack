<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Publication;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\Common\DefaultArtifactByteEstimator;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Application\Publication\BufferConfiguration;
use ItPack\RunIt\Application\Publication\BufferedArtifactPublisher;
use ItPack\RunIt\Application\Publication\BufferOverflowException;
use ItPack\RunIt\Application\Publication\OverflowStrategy;
use ItPack\RunIt\Contract\Application\Publication\InternalHealthReporterInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Port\ArtifactByteEstimatorInterface;
use ItPack\RunIt\Contract\Port\MonotonicClockInterface;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Observation\Observation;
use ItPack\RunIt\Domain\Observation\ObservationId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use RuntimeException;
use Tests\Support\Fixture\DeterministicArtifactByteEstimator;
use Tests\Support\Fixture\DeterministicMonotonicClock;
use Throwable;
use TypeError;

/**
 * @group application
 * @group publication
 */
#[CoversClass(BufferedArtifactPublisher::class)]
#[UsesClass(DefaultArtifactByteEstimator::class)]
#[UsesClass(SystemMonotonicClock::class)]
#[UsesClass(InMemoryArtifactPublisher::class)]
#[UsesClass(BufferConfiguration::class)]
#[UsesClass(BufferOverflowException::class)]
#[UsesClass(ExecutionId::class)]
#[UsesClass(Observation::class)]
#[UsesClass(ObservationId::class)]
final class BufferingTest extends Unit
{
    private MonotonicClockInterface $clock;

    private ArtifactByteEstimatorInterface $estimator;

    protected function setUp(): void
    {
        $this->clock = new SystemMonotonicClock();
        $this->estimator = new DefaultArtifactByteEstimator();
    }

    private function artifact(string $severity = 'info', string $id = 'obs'): ExecutionArtifactInterface
    {
        $executionId = ExecutionId::fromString('exec-1');
        $observationId = ObservationId::fromString($id);

        return match ($severity) {
            'debug' => Observation::debug($observationId, $executionId, 'msg'),
            'info' => Observation::info($observationId, $executionId, 'msg'),
            'notice' => Observation::notice($observationId, $executionId, 'msg'),
            'warning' => Observation::warning($observationId, $executionId, 'msg'),
            'error' => Observation::error($observationId, $executionId, 'msg'),
            'critical' => Observation::critical($observationId, $executionId, 'msg'),
            'alert' => Observation::alert($observationId, $executionId, 'msg'),
            'emergency' => Observation::emergency($observationId, $executionId, 'msg'),
            default => Observation::info($observationId, $executionId, 'msg'),
        };
    }

    private function idOf(ExecutionArtifactInterface $artifact): string
    {
        $id = $artifact->normalize()['id'] ?? null;

        return is_string($id) ? $id : '';
    }

    private function config(
        int $maxItems,
        OverflowStrategy $overflow,
        int $batchSize = 1024,
        int $budget = 4096,
        int $maxBytes = 10_000_000
    ): BufferConfiguration {
        return new BufferConfiguration(
            maxItems: $maxItems,
            maxEstimatedBytes: $maxBytes,
            maxAgeSeconds: 60,
            batchSize: $batchSize,
            flushIntervalSeconds: 5,
            shutdownFlushBudget: $budget,
            overflow: $overflow,
        );
    }

    public function testDeterministicEstimatorUsesExplicitSizesForArbitraryIds(): void
    {
        $id = '12345678-1234-1234-1234-123456789012';
        $estimator = new DeterministicArtifactByteEstimator(100, [$id => 101]);

        self::assertSame(101, $estimator->estimate($this->artifact(id: $id)));
        self::assertSame(100, $estimator->estimate($this->artifact(id: '99999999-1234-1234-1234-123456789012')));
    }

    public function testByteAdmissionCurrentlyAcceptsOversizedFirstAndProjectedItemsForEveryStrategy(): void
    {
        foreach (OverflowStrategy::cases() as $strategy) {
            $sink = new InMemoryArtifactPublisher();
            $buffer = new BufferedArtifactPublisher(
                $this->config(10, $strategy, maxBytes: 100),
                $sink,
                new DeterministicMonotonicClock(1_000, 0),
                new DeterministicArtifactByteEstimator(60, ['oversized' => 101]),
            );

            $oversized = $this->artifact(id: 'oversized');
            $buffer->publish($oversized);
            self::assertSame(101, $buffer->estimatedBytes(), $strategy->name);
            self::assertSame(1, $buffer->bufferedCount());
            self::assertSame(0, $buffer->droppedCount());
            $buffer->flush();
            self::assertSame([$oversized], $sink->published());
            self::assertSame(0, $buffer->estimatedBytes());

            $first = $this->artifact(id: 'first');
            $second = $this->artifact(id: 'second');
            $buffer->publish($first);
            $buffer->publish($second);
            self::assertSame(120, $buffer->estimatedBytes(), $strategy->name);
            self::assertSame(2, $buffer->bufferedCount());
            self::assertSame(0, $buffer->droppedCount());
            $buffer->flush();
            self::assertSame([$oversized, $first, $second], $sink->published());
            self::assertSame(0, $buffer->estimatedBytes());
        }
    }

    public function testByteOverflowReplacementCurrentlyEvictsOnlyOneItem(): void
    {
        foreach ([OverflowStrategy::DropOldest, OverflowStrategy::DropLowPriority] as $strategy) {
            $sink = new InMemoryArtifactPublisher();
            $buffer = new BufferedArtifactPublisher(
                $this->config(10, $strategy, maxBytes: 100),
                $sink,
                new DeterministicMonotonicClock(1_000, 0),
                new DeterministicArtifactByteEstimator(50, ['incoming' => 80]),
            );

            $first = $this->artifact(id: 'first');
            $second = $this->artifact(id: 'second');
            $incoming = $this->artifact('error', 'incoming');
            $buffer->publish($first);
            $buffer->publish($second);
            self::assertSame(100, $buffer->estimatedBytes());

            $buffer->publish($incoming);
            self::assertSame(130, $buffer->estimatedBytes(), $strategy->name);
            self::assertSame(2, $buffer->bufferedCount());
            self::assertSame(1, $buffer->droppedCount());
            $buffer->flush();
            self::assertSame([$second, $incoming], $sink->published());
            self::assertSame(0, $buffer->estimatedBytes());
        }
    }

    public function testIntegerOverflowDuringAdmissionLeavesTheAppendedItemUnaccounted(): void
    {
        $sink = new InMemoryArtifactPublisher();
        $buffer = new BufferedArtifactPublisher(
            $this->config(10, OverflowStrategy::DropNewest, maxBytes: PHP_INT_MAX),
            $sink,
            new DeterministicMonotonicClock(),
            new DeterministicArtifactByteEstimator(1, ['first' => PHP_INT_MAX - 1, 'second' => 2]),
        );
        $first = $this->artifact(id: 'first');
        $second = $this->artifact(id: 'second');
        $buffer->publish($first);

        try {
            $buffer->publish($second);
            self::fail('Integer overflow must currently surface as a TypeError.');
        } catch (TypeError) {
            self::assertSame(2, $buffer->bufferedCount());
            self::assertSame(PHP_INT_MAX - 1, $buffer->estimatedBytes());
            self::assertSame(0, $buffer->droppedCount());
        }

        $buffer->flush();
        self::assertSame([$first, $second], $sink->published());
        self::assertSame(-2, $buffer->estimatedBytes());
    }

    public function testFailingDropReporterInterruptsReplacementAfterEviction(): void
    {
        $failure = new RuntimeException('health failure');
        $health = new readonly class ($failure) implements InternalHealthReporterInterface {
            public function __construct(private RuntimeException $failure)
            {
            }

            public function report(Throwable $error, ?ExecutionArtifactInterface $artifact = null): void
            {
                throw $this->failure;
            }
        };
        $sink = new InMemoryArtifactPublisher();
        $buffer = new BufferedArtifactPublisher(
            $this->config(1, OverflowStrategy::DropOldest),
            $sink,
            new DeterministicMonotonicClock(),
            new DeterministicArtifactByteEstimator(10),
            $health,
        );
        $buffer->publish($this->artifact(id: 'first'));

        try {
            $buffer->publish($this->artifact(id: 'second'));
            self::fail('The reporter failure must currently propagate.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame($failure, $runtimeException);
        }

        self::assertSame(0, $buffer->bufferedCount());
        self::assertSame(0, $buffer->estimatedBytes());
        self::assertSame(1, $buffer->droppedCount());
        $buffer->flush();
        self::assertSame([], $sink->published());
    }

    public function testBlockingOverflowCanAppendAnOversizedItemAfterSpaceIsFreed(): void
    {
        $sink = new InMemoryArtifactPublisher();
        $buffer = null;
        $buffer = new BufferedArtifactPublisher(
            $this->config(1, OverflowStrategy::BlockWithTimeout, maxBytes: 100),
            $sink,
            new DeterministicMonotonicClock(),
            new DeterministicArtifactByteEstimator(50, ['incoming' => 101]),
            sleep: static function (int $microseconds) use (&$buffer): void {
                self::assertInstanceOf(BufferedArtifactPublisher::class, $buffer);
                $buffer->flush();
            },
        );
        $first = $this->artifact(id: 'first');
        $incoming = $this->artifact(id: 'incoming');
        $buffer->publish($first);
        $buffer->publish($incoming);

        self::assertSame([$first], $sink->published());
        self::assertSame(1, $buffer->bufferedCount());
        self::assertSame(101, $buffer->estimatedBytes());
        self::assertSame(0, $buffer->droppedCount());
        $buffer->flush();
        self::assertSame([$first, $incoming], $sink->published());
        self::assertSame(0, $buffer->estimatedBytes());
    }

    public function testDropNewestBoundsBuffer(): void
    {
        $sink = new InMemoryArtifactPublisher();
        $buffer = new BufferedArtifactPublisher(
            $this->config(2, OverflowStrategy::DropNewest),
            $sink,
            $this->clock,
            $this->estimator
        );

        $buffer->publish($this->artifact('info', 'a'));
        $buffer->publish($this->artifact('info', 'b'));
        $buffer->publish($this->artifact('info', 'c'));

        self::assertSame(2, $buffer->bufferedCount());
        self::assertSame(1, $buffer->droppedCount());

        $buffer->flush();
        self::assertCount(2, $sink->published());
    }

    public function testDropOldestBoundsBuffer(): void
    {
        $sink = new InMemoryArtifactPublisher();
        $buffer = new BufferedArtifactPublisher(
            $this->config(2, OverflowStrategy::DropOldest),
            $sink,
            $this->clock,
            $this->estimator
        );

        $buffer->publish($this->artifact('info', 'a'));
        $buffer->publish($this->artifact('info', 'b'));
        $buffer->publish($this->artifact('info', 'c'));

        $buffer->flush();
        self::assertSame(
            ['b', 'c'],
            [$sink->published()[0]->normalize()['id'], $sink->published()[1]->normalize()['id']]
        );
        self::assertSame(1, $buffer->droppedCount());
    }

    public function testDropLowPriorityKeepsHighPriority(): void
    {
        $sink = new InMemoryArtifactPublisher();
        $buffer = new BufferedArtifactPublisher(
            $this->config(2, OverflowStrategy::DropLowPriority),
            $sink,
            $this->clock,
            $this->estimator
        );

        $buffer->publish($this->artifact('info', 'low-1'));
        $buffer->publish($this->artifact('info', 'low-2'));
        $buffer->publish($this->artifact('error', 'high-1'));

        $buffer->flush();
        self::assertSame(
            ['low-2', 'high-1'],
            [$sink->published()[0]->normalize()['id'], $sink->published()[1]->normalize()['id']]
        );
    }

    public function testDropLowPriorityDropsIncomingLow(): void
    {
        $sink = new InMemoryArtifactPublisher();
        $buffer = new BufferedArtifactPublisher(
            $this->config(1, OverflowStrategy::DropLowPriority),
            $sink,
            $this->clock,
            $this->estimator
        );

        $buffer->publish($this->artifact('info', 'low-1'));
        $buffer->publish($this->artifact('info', 'low-2'));

        self::assertSame(1, $buffer->bufferedCount());
        self::assertSame(1, $buffer->droppedCount());
    }

    public function testFailStrategyThrows(): void
    {
        $sink = new InMemoryArtifactPublisher();
        $buffer = new BufferedArtifactPublisher(
            $this->config(1, OverflowStrategy::Fail),
            $sink,
            $this->clock,
            $this->estimator
        );

        $buffer->publish($this->artifact('info', 'a'));

        $thrown = null;
        try {
            $buffer->publish($this->artifact('info', 'b'));
        } catch (BufferOverflowException $bufferOverflowException) {
            $thrown = $bufferOverflowException;
        }

        self::assertNotNull($thrown);
        self::assertSame(1, $buffer->bufferedCount());
    }

    public function testBlockWithTimeoutWaitsThroughTheInjectableSleepSeam(): void
    {
        $sleeps = [];

        $buffer = new BufferedArtifactPublisher(
            $this->config(1, OverflowStrategy::BlockWithTimeout),
            new InMemoryArtifactPublisher(),
            $this->clock,
            $this->estimator,
            sleep: static function (int $microseconds) use (&$sleeps): void {
                $sleeps[] = $microseconds;
            },
        );

        // Fill the single slot, then block: the injected seam records the wait
        // instead of actually sleeping, and the deadline eventually drops the
        // incoming artifact (nothing drains because nothing flushes here).
        $buffer->publish($this->artifact('info', 'obs-1'));
        $buffer->publish($this->artifact('info', 'obs-2'));

        self::assertNotEmpty($sleeps, 'the blocking strategy must wait through the sleep seam');
        self::assertSame(100, $sleeps[0], 'the wait quantum is the documented 100 microseconds');
        self::assertSame(1, $buffer->droppedCount(), 'after the deadline the incoming artifact is dropped');
    }

    public function testFlushRespectsBatchSize(): void
    {
        $sink = new InMemoryArtifactPublisher();
        $buffer = new BufferedArtifactPublisher(
            $this->config(10, OverflowStrategy::DropOldest, batchSize: 2),
            $sink,
            $this->clock,
            $this->estimator
        );

        for ($i = 0; $i < 5; $i++) {
            $buffer->publish($this->artifact('info', (string) $i));
        }

        $buffer->flush();
        self::assertCount(2, $sink->published());
        self::assertSame(3, $buffer->bufferedCount());

        $buffer->flush();
        self::assertCount(4, $sink->published());
    }

    public function testCloseRespectsShutdownBudgetAndDropsRest(): void
    {
        $sink = new InMemoryArtifactPublisher();
        $buffer = new BufferedArtifactPublisher(
            $this->config(10, OverflowStrategy::DropOldest, batchSize: 1024, budget: 1),
            $sink,
            $this->clock,
            $this->estimator
        );

        for ($i = 0; $i < 3; $i++) {
            $buffer->publish($this->artifact('info', (string) $i));
        }

        $buffer->close();

        self::assertCount(1, $sink->published());
        self::assertSame(0, $buffer->bufferedCount());
        self::assertSame(2, $buffer->droppedCount());
    }

    public function testCloseReportsRemainderDropsThroughHealth(): void
    {
        /** @var list<array{0: Throwable, 1: ExecutionArtifactInterface}> $reported */
        $reported = [];
        $health = new class ($reported) implements InternalHealthReporterInterface {
            /**
             * @param list<array{0: Throwable, 1: ExecutionArtifactInterface}> $sink
             */
            public function __construct(private array &$sink)
            {
            }

            public function report(Throwable $error, ExecutionArtifactInterface $artifact): void
            {
                $sink = &$this->sink;
                $sink[] = [$error, $artifact];
            }
        };

        $sink = new InMemoryArtifactPublisher();
        $buffer = new BufferedArtifactPublisher(
            $this->config(10, OverflowStrategy::DropOldest, batchSize: 1024, budget: 1),
            $sink,
            $this->clock,
            $this->estimator,
            $health,
        );

        for ($i = 0; $i < 3; $i++) {
            $buffer->publish($this->artifact('info', (string) $i));
        }

        $buffer->close();

        self::assertSame(2, $buffer->droppedCount());
        self::assertCount(2, $reported, 'remainder drops at close() must be reported to the health channel');
        self::assertInstanceOf(BufferOverflowException::class, $reported[0][0]);
    }

    public function testMemoryStaysBoundedUnderSustainedLoad(): void
    {
        $sink = new InMemoryArtifactPublisher();
        $buffer = new BufferedArtifactPublisher(
            $this->config(10, OverflowStrategy::DropOldest),
            $sink,
            $this->clock,
            $this->estimator
        );

        for ($i = 0; $i < 1000; $i++) {
            $buffer->publish($this->artifact('info', (string) $i));
        }

        self::assertLessThanOrEqual(10, $buffer->bufferedCount());
        self::assertGreaterThan(0, $buffer->estimatedBytes());
    }

    public function testDefaultConfigurationBuffersAndFlushes(): void
    {
        $config = BufferConfiguration::default();
        $sink = new InMemoryArtifactPublisher();
        $buffer = new BufferedArtifactPublisher($config, $sink, $this->clock, $this->estimator);

        $buffer->publish($this->artifact('info', 'a'));
        $buffer->flush();

        self::assertCount(1, $sink->published());
    }

    public function testPublishAutoFlushesWhenTheFlushIntervalHasElapsed(): void
    {
        $clock = new DeterministicMonotonicClock(1_000, 0);
        $sink = new InMemoryArtifactPublisher();
        $buffer = new BufferedArtifactPublisher(
            $this->config(10, OverflowStrategy::DropOldest),
            $sink,
            $clock,
            $this->estimator
        );

        $buffer->publish($this->artifact('info', 'obs-1'));
        self::assertSame([], $sink->published(), 'within the interval nothing drains');

        $clock->advanceSeconds(6);
        $buffer->publish($this->artifact('info', 'obs-2'));

        self::assertCount(2, $sink->published(), 'crossing the flush interval drains the buffer');
        self::assertSame(0, $buffer->bufferedCount());
    }

    public function testDropLowPriorityFallsBackToDroppingOldestWhenEverythingIsHighPriority(): void
    {
        $sink = new InMemoryArtifactPublisher();
        $buffer = new BufferedArtifactPublisher(
            $this->config(2, OverflowStrategy::DropLowPriority),
            $sink,
            $this->clock,
            $this->estimator
        );

        $buffer->publish($this->artifact('warning', 'obs-high-1'));
        $buffer->publish($this->artifact('error', 'obs-high-2'));
        $buffer->publish($this->artifact('critical', 'obs-high-3'));

        self::assertSame(2, $buffer->bufferedCount());
        self::assertSame(
            1,
            $buffer->droppedCount(),
            'the oldest high-priority item is dropped when no low-priority item exists'
        );

        $buffer->flush();

        self::assertSame(
            'obs-high-2',
            $this->idOf($sink->published()[0]),
            'the newest high-priority items are retained'
        );
    }

    public function testBlockWithTimeoutUsesTheDefaultSleepWhenNoSeamIsInjected(): void
    {
        $buffer = new BufferedArtifactPublisher(
            $this->config(1, OverflowStrategy::BlockWithTimeout),
            new InMemoryArtifactPublisher(),
            $this->clock,
            $this->estimator,
        );

        $buffer->publish($this->artifact('info', 'obs-1'));
        $buffer->publish($this->artifact('info', 'obs-2'));

        self::assertSame(1, $buffer->droppedCount(), 'the default wait still respects the blocking budget');
    }

    public function testDrainDropsItemsOlderThanMaxAgeAndReportsThem(): void
    {
        $health = new class () implements InternalHealthReporterInterface {
            /** @var list<Throwable> */
            public array $reported = [];

            public function report(Throwable $error, ?ExecutionArtifactInterface $artifact = null): void
            {
                $this->reported[] = $error;
            }
        };
        $clock = new DeterministicMonotonicClock(1_000, 0);
        $sink = new InMemoryArtifactPublisher();
        $config = new BufferConfiguration(
            maxItems: 10,
            maxEstimatedBytes: 10_000_000,
            maxAgeSeconds: 60,
            batchSize: 1024,
            flushIntervalSeconds: 0,
            shutdownFlushBudget: 4096,
            overflow: OverflowStrategy::DropOldest,
        );
        $buffer = new BufferedArtifactPublisher($config, $sink, $clock, $this->estimator, $health);

        $buffer->publish($this->artifact('info', 'obs-stale'));

        $clock->advanceSeconds(61);
        $buffer->flush();

        self::assertSame([], $sink->published(), 'an item past its max age must never reach the inner publisher');
        self::assertSame(1, $buffer->droppedCount());
        self::assertCount(1, $health->reported);
    }
}
