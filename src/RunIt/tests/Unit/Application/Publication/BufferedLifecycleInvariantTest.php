<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Publication;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Application\Publication\BufferConfiguration;
use ItPack\RunIt\Application\Publication\BufferedArtifactPublisher;
use ItPack\RunIt\Application\Publication\OverflowStrategy;
use ItPack\RunIt\Contract\Application\Publication\BatchFlushObserverInterface;
use ItPack\RunIt\Contract\Application\Publication\InternalHealthReporterInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Observation\Observation;
use ItPack\RunIt\Domain\Observation\ObservationId;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use Tests\Support\Fixture\DeterministicArtifactByteEstimator;
use Tests\Support\Fixture\DeterministicMonotonicClock;
use Throwable;

#[CoversClass(BufferedArtifactPublisher::class)]
final class BufferedLifecycleInvariantTest extends Unit
{
    public function testThrowingReporterAfterNonHeadPriorityEvictionCanBeRetried(): void
    {
        $failure = new RuntimeException('reporter failure');
        $health = new class ($failure) implements InternalHealthReporterInterface {
            /** @var list<?ExecutionArtifactInterface> */
            public array $reported = [];

            public function __construct(private readonly RuntimeException $failure)
            {
            }

            public function report(Throwable $error, ?ExecutionArtifactInterface $artifact = null): void
            {
                $this->reported[] = $artifact;
                if (count($this->reported) === 1) {
                    throw $this->failure;
                }
            }
        };
        $sink = new InMemoryArtifactPublisher();
        $buffer = new BufferedArtifactPublisher(
            new BufferConfiguration(3, 100, 60, 3, 0, 3, OverflowStrategy::DropLowPriority),
            $sink,
            new DeterministicMonotonicClock(),
            new DeterministicArtifactByteEstimator(10, ['low' => 20, 'last' => 30, 'incoming' => 40]),
            $health,
        );
        $first = $this->artifact('first', true);
        $low = $this->artifact('low');
        $last = $this->artifact('last', true);
        $incoming = $this->artifact('incoming', true);
        $buffer->publish($first);
        $buffer->publish($low);
        $buffer->publish($last);

        try {
            $buffer->publish($incoming);
            self::fail('The reporter failure must propagate.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame($failure, $runtimeException);
        }

        self::assertSame([$low], $health->reported);
        self::assertSame([], $sink->published());
        self::assertSame(2, $buffer->bufferedCount());
        self::assertSame(40, $buffer->estimatedBytes());
        self::assertSame(1, $buffer->droppedCount());

        $buffer->publish($incoming);
        self::assertSame(3, $buffer->bufferedCount());
        self::assertSame(80, $buffer->estimatedBytes());
        self::assertSame(1, $buffer->droppedCount());
        $buffer->flush();
        self::assertSame([$first, $last, $incoming], $sink->published());
        self::assertSame([$low], $health->reported);
        self::assertSame(0, $buffer->bufferedCount());
        self::assertSame(0, $buffer->estimatedBytes());
    }

    public function testCloseObserverFailureAfterRemainderDropsCanBeRetried(): void
    {
        $failure = new RuntimeException('observer failure');
        $observer = new class ($failure) implements BatchFlushObserverInterface {
            /** @var list<array{int, int, int}> */
            public array $reports = [];

            public function __construct(private readonly RuntimeException $failure)
            {
            }

            public function onFlush(int $flushedCount, int $durationMicroseconds, int $remainingBuffered): void
            {
                $this->reports[] = [$flushedCount, $durationMicroseconds, $remainingBuffered];
                if (count($this->reports) === 1) {
                    throw $this->failure;
                }
            }
        };
        $health = new class () implements InternalHealthReporterInterface {
            /** @var list<?ExecutionArtifactInterface> */
            public array $reported = [];

            public function report(Throwable $error, ?ExecutionArtifactInterface $artifact = null): void
            {
                $this->reported[] = $artifact;
            }
        };
        $sink = new InMemoryArtifactPublisher();
        $buffer = new BufferedArtifactPublisher(
            new BufferConfiguration(3, 100, 60, 3, 0, 1, OverflowStrategy::DropNewest),
            $sink,
            new DeterministicMonotonicClock(),
            new DeterministicArtifactByteEstimator(10, ['second' => 20, 'third' => 30]),
            $health,
            $observer,
        );
        $first = $this->artifact('first');
        $second = $this->artifact('second');
        $third = $this->artifact('third');
        $buffer->publish($first);
        $buffer->publish($second);
        $buffer->publish($third);
        self::assertSame(60, $buffer->estimatedBytes());

        try {
            $buffer->close();
            self::fail('The observer failure must propagate.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame($failure, $runtimeException);
        }

        self::assertSame([$first], $sink->published());
        self::assertSame([$second, $third], $health->reported);
        self::assertSame([[1, 0, 0]], $observer->reports);
        self::assertSame(0, $buffer->bufferedCount());
        self::assertSame(0, $buffer->estimatedBytes());
        self::assertSame(2, $buffer->droppedCount());

        $buffer->close();
        self::assertSame([$first], $sink->published());
        self::assertSame([$second, $third], $health->reported);
        self::assertSame([[1, 0, 0], [0, 0, 0]], $observer->reports);
        self::assertSame(0, $buffer->bufferedCount());
        self::assertSame(0, $buffer->estimatedBytes());
        self::assertSame(2, $buffer->droppedCount());
    }

    private function artifact(string $id, bool $high = false): Observation
    {
        $observationId = ObservationId::fromString($id);
        $executionId = ExecutionId::fromString('execution');

        return $high
            ? Observation::error($observationId, $executionId, 'message')
            : Observation::info($observationId, $executionId, 'message');
    }
}
