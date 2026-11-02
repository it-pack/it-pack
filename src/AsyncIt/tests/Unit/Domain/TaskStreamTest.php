<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use Generator;
use ItPack\AsyncIt\Application\Clock\DeterministicMonotonicClock;
use ItPack\AsyncIt\Application\Context\MemoryContextStorage;
use ItPack\AsyncIt\Application\Factory\CancellationScopeFactory;
use ItPack\AsyncIt\Application\Factory\FiberFactory;
use ItPack\AsyncIt\Application\Factory\PollerFactory;
use ItPack\AsyncIt\Application\Factory\ReferenceExecutionScopeFactory;
use ItPack\AsyncIt\Application\Factory\SchedulerLifecycleArtifactFactory;
use ItPack\AsyncIt\Application\Factory\TaskFactory;
use ItPack\AsyncIt\Application\Factory\TaskIdFactory;
use ItPack\AsyncIt\Application\Factory\TaskRecordFactory;
use ItPack\AsyncIt\Application\Publisher\NullArtifactPublisher;
use ItPack\AsyncIt\Application\Scheduler\FiberTaskScheduler;
use ItPack\AsyncIt\Application\Scheduler\TaskCancellationService;
use ItPack\AsyncIt\Application\Scheduler\TaskHealthService;
use ItPack\AsyncIt\Application\Scheduler\TaskIoAwaitService;
use ItPack\AsyncIt\Application\Scheduler\TaskLifecyclePublisher;
use ItPack\AsyncIt\Application\Scheduler\TaskTimerService;
use ItPack\AsyncIt\Contract\Port\CapabilityKind;
use ItPack\AsyncIt\Contract\Port\CapabilityStatus;
use ItPack\AsyncIt\Domain\CapabilityDescriptor;
use ItPack\AsyncIt\Domain\TaskStream;
use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Contract\Port\MonotonicClockInterface;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ItPack\AsyncIt\Domain\TaskStream
 */
final class TaskStreamTest extends TestCase
{
    public function testStreamYieldsResultsOneAtATime(): void
    {
        $scheduler = $this->scheduler();
        $results = [];

        $scheduler->spawn(static function () use ($scheduler, &$results): void {
            $generator = static function (): Generator {
                yield static fn (): int => 1;
                yield static fn (): int => 2;
                yield static fn (): int => 3;
            };

            $stream = new TaskStream($scheduler, $generator());
            $stream->rewind();

            while ($stream->valid()) {
                $results[] = $stream->current();
                $stream->next();
            }
        });

        $scheduler->run();

        self::assertSame([1, 2, 3], $results);
    }

    public function testEmptyGeneratorProducesNoItems(): void
    {
        $scheduler = $this->scheduler();
        $results = [];

        $scheduler->spawn(static function () use ($scheduler, &$results): void {
            $generator = static function (): Generator {
                yield from [];
            };

            $stream = new TaskStream($scheduler, $generator());
            $stream->rewind();

            while ($stream->valid()) {
                $results[] = $stream->current();
                $stream->next();
            }
        });

        $scheduler->run();

        self::assertSame([], $results);
    }

    public function testSingleItemStream(): void
    {
        $scheduler = $this->scheduler();
        $result = null;

        $scheduler->spawn(static function () use ($scheduler, &$result): void {
            $generator = static function (): Generator {
                yield static fn (): string => 'only';
            };

            $stream = new TaskStream($scheduler, $generator());
            $stream->rewind();

            self::assertTrue($stream->valid());
            self::assertSame(0, $stream->key());
            $result = $stream->current();

            $stream->next();
        });

        $scheduler->run();

        self::assertSame('only', $result);
    }

    public function testStreamCurrentNullBeforeRewind(): void
    {
        $scheduler = $this->scheduler();
        $current = null;
        $key = null;
        $valid = null;

        $scheduler->spawn(static function () use ($scheduler, &$current, &$key, &$valid): void {
            $generator = static function (): Generator {
                yield static fn (): int => 1;
            };

            $stream = new TaskStream($scheduler, $generator());
            $current = $stream->current();
            $key = $stream->key();
            $valid = $stream->valid();
        });

        $scheduler->run();

        self::assertNull($current);
        self::assertSame(-1, $key);
        self::assertFalse($valid);
    }

    public function testStreamUsesMonotonicClockForDurations(): void
    {
        $clock = new DeterministicMonotonicClock();
        $scheduler = $this->schedulerWithClock($clock);
        $result = null;

        $scheduler->spawn(static function () use ($scheduler, &$result): void {
            $generator = static function (): Generator {
                yield static fn (): int => 7;
            };

            $stream = new TaskStream($scheduler, $generator());
            $stream->rewind();

            $result = $stream->current();
        });

        $scheduler->run();

        self::assertSame(7, $result);
    }

    private function scheduler(): FiberTaskScheduler
    {
        return $this->schedulerWithClock(new SystemMonotonicClock());
    }

    private function schedulerWithClock(MonotonicClockInterface $clock): FiberTaskScheduler
    {
        $executionId = ExecutionId::fromString('exec-stream-1');
        $sequenceAllocator = new InMemorySequenceAllocator();
        $poller = new PollerFactory()->create(new CapabilityDescriptor([
            CapabilityKind::EdgeTriggeredPolling->value => CapabilityStatus::Emulated,
        ]));

        $lifecycleFactory = new SchedulerLifecycleArtifactFactory($sequenceAllocator, $executionId);
        $timerService = new TaskTimerService($clock);
        $ioAwaitService = new TaskIoAwaitService($poller, $timerService);
        $lifecyclePublisher = new TaskLifecyclePublisher(new NullArtifactPublisher(), $lifecycleFactory, $clock);
        $cancellationService = new TaskCancellationService();
        $healthService = new TaskHealthService($poller, $clock);

        return new FiberTaskScheduler(
            new FiberFactory(),
            new TaskRecordFactory(),
            new TaskFactory(
                new TaskIdFactory(new RandomIdentifierGenerator()),
            ),
            new CancellationScopeFactory(),
            $clock,
            $executionId,
            $poller,
            new MemoryContextStorage(),
            new ReferenceExecutionScopeFactory(),
            $timerService,
            $ioAwaitService,
            $lifecyclePublisher,
            $cancellationService,
            $healthService,
        );
    }
}
