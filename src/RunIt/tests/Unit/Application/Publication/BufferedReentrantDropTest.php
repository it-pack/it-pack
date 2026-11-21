<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Publication;

use Codeception\Attribute\DataProvider;
use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Application\Publication\BufferConfiguration;
use ItPack\RunIt\Application\Publication\BufferedArtifactPublisher;
use ItPack\RunIt\Application\Publication\BufferOverflowException;
use ItPack\RunIt\Application\Publication\OverflowStrategy;
use ItPack\RunIt\Contract\Application\Publication\InternalHealthReporterInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Observation\Observation;
use ItPack\RunIt\Domain\Observation\ObservationId;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use Tests\Support\Fixture\DeterministicArtifactByteEstimator;
use Tests\Support\Fixture\DeterministicMonotonicClock;
use Throwable;

#[CoversClass(BufferedArtifactPublisher::class)]
final class BufferedReentrantDropTest extends Unit
{
    /** @return iterable<string, array{string, OverflowStrategy}> */
    public static function scenarios(): iterable
    {
        yield 'rejection' => ['rejection', OverflowStrategy::DropNewest];
        yield 'fail rejection' => ['rejection', OverflowStrategy::Fail];
        yield 'overflow newest' => ['overflow', OverflowStrategy::DropNewest];
        yield 'overflow oldest' => ['overflow', OverflowStrategy::DropOldest];
        yield 'overflow priority' => ['overflow', OverflowStrategy::DropLowPriority];
        yield 'overflow fail' => ['overflow', OverflowStrategy::Fail];
        yield 'close' => ['close', OverflowStrategy::DropNewest];
        yield 'expiry' => ['expiry', OverflowStrategy::DropNewest];
    }

    #[DataProvider('scenarios')]
    public function testReporterCannotRepopulateBuffer(string $scenario, OverflowStrategy $strategy): void
    {
        $clock = new DeterministicMonotonicClock();
        $sink = new InMemoryArtifactPublisher();
        $health = new class () implements InternalHealthReporterInterface {
            public BufferedArtifactPublisher $buffer;

            public int $calls = 0;

            public function report(Throwable $error, ?ExecutionArtifactInterface $artifact = null): void
            {
                if (!$artifact instanceof ExecutionArtifactInterface) {
                    throw new LogicException('Expected dropped artifact.');
                }

                ++$this->calls;
                $this->buffer->publish($artifact);
                $this->buffer->publish($artifact);
            }
        };
        $buffer = new BufferedArtifactPublisher(
            new BufferConfiguration($scenario === 'rejection' ? 0 : 1, 100, 1, 1, 0, 0, $strategy),
            $sink,
            $clock,
            new DeterministicArtifactByteEstimator(10),
            $health,
        );
        $health->buffer = $buffer;
        $artifact = Observation::info(ObservationId::fromString('item'), ExecutionId::fromString('exec'), 'message');
        $thrown = null;
        try {
            $buffer->publish($artifact);
            if ($scenario === 'overflow') {
                $buffer->publish($artifact);
            } elseif ($scenario === 'close') {
                $buffer->close();
            } elseif ($scenario === 'expiry') {
                $clock->advanceSeconds(2);
                $buffer->flush();
            }
        } catch (BufferOverflowException $bufferOverflowException) {
            $thrown = $bufferOverflowException;
        }

        self::assertSame($strategy === OverflowStrategy::Fail, $thrown instanceof BufferOverflowException);
        self::assertSame(1, $health->calls);
        self::assertSame(3, $buffer->droppedCount());
        $retained = $scenario === 'overflow' ? 1 : 0;
        self::assertSame($retained, $buffer->bufferedCount());
        self::assertSame($retained * 10, $buffer->estimatedBytes());
        self::assertSame([], $sink->published());
    }

    public function testReporterFailureResetsGuardAndPreservesException(): void
    {
        $failure = new RuntimeException('reporter failure');
        $health = new class ($failure) implements InternalHealthReporterInterface {
            public BufferedArtifactPublisher $buffer;

            public int $calls = 0;

            public function __construct(private readonly RuntimeException $failure)
            {
            }

            public function report(Throwable $error, ?ExecutionArtifactInterface $artifact = null): void
            {
                if (!$artifact instanceof ExecutionArtifactInterface) {
                    throw new LogicException('Expected dropped artifact.');
                }

                ++$this->calls;
                $this->buffer->publish($artifact);
                if ($this->calls === 1) {
                    throw $this->failure;
                }
            }
        };
        $buffer = new BufferedArtifactPublisher(
            new BufferConfiguration(1, 100, 60, 1, 0, 0, OverflowStrategy::DropOldest),
            new InMemoryArtifactPublisher(),
            new DeterministicMonotonicClock(),
            new DeterministicArtifactByteEstimator(10),
            $health,
        );
        $health->buffer = $buffer;
        $artifact = Observation::info(ObservationId::fromString('item'), ExecutionId::fromString('exec'), 'message');
        $buffer->publish($artifact);
        try {
            $buffer->publish($artifact);
            self::fail('Reporter failure must propagate.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame($failure, $runtimeException);
        }

        self::assertSame(2, $buffer->droppedCount());
        self::assertSame(0, $buffer->estimatedBytes());
        $buffer->publish($artifact);
        $buffer->publish($artifact);
        self::assertSame(2, $health->calls);
        self::assertSame(4, $buffer->droppedCount());
        self::assertSame(1, $buffer->bufferedCount());
        self::assertSame(10, $buffer->estimatedBytes());
    }

    public function testReporterCanCloseBufferWithoutRecursiveDropReporting(): void
    {
        $health = new class () implements InternalHealthReporterInterface {
            public BufferedArtifactPublisher $buffer;

            public int $calls = 0;

            public function report(Throwable $error, ?ExecutionArtifactInterface $artifact = null): void
            {
                ++$this->calls;
                $this->buffer->close();
            }
        };
        $buffer = new BufferedArtifactPublisher(
            new BufferConfiguration(2, 100, 60, 2, 0, 0, OverflowStrategy::DropNewest),
            new InMemoryArtifactPublisher(),
            new DeterministicMonotonicClock(),
            new DeterministicArtifactByteEstimator(10),
            $health,
        );
        $health->buffer = $buffer;
        $artifact = Observation::info(ObservationId::fromString('item'), ExecutionId::fromString('exec'), 'message');
        $buffer->publish($artifact);
        $buffer->publish($artifact);
        $buffer->publish($artifact);
        self::assertSame(1, $health->calls);
        self::assertSame(3, $buffer->droppedCount());
        self::assertSame(0, $buffer->bufferedCount());
        self::assertSame(0, $buffer->estimatedBytes());
    }
}
