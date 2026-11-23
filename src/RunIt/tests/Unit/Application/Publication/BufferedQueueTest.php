<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Publication;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Application\Publication\BufferConfiguration;
use ItPack\RunIt\Application\Publication\BufferedArtifactPublisher;
use ItPack\RunIt\Application\Publication\OverflowStrategy;
use ItPack\RunIt\Contract\Application\Publication\InternalHealthReporterInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionProperty;
use Tests\Support\Fixture\DeterministicArtifactByteEstimator;
use Tests\Support\Fixture\DeterministicMonotonicClock;
use Tests\Support\Fixture\PublicationTestArtifactFactory;
use Throwable;

#[CoversClass(BufferedArtifactPublisher::class)]
final class BufferedQueueTest extends Unit
{
    private function publisher(
        ArtifactPublisherInterface $sink,
        int $capacity,
        OverflowStrategy $strategy,
        ?InternalHealthReporterInterface $health = null,
    ): BufferedArtifactPublisher {
        return new BufferedArtifactPublisher(
            new BufferConfiguration($capacity, 100000, 60, $capacity, 0, $capacity, $strategy),
            $sink,
            new DeterministicMonotonicClock(),
            new DeterministicArtifactByteEstimator(1),
            $health,
        );
    }

    /**
     * @throws Throwable
     */
    public function testChurnCompactsPriorityHolesAndPreservesOrder(): void
    {
        $sink = new InMemoryArtifactPublisher();
        $health = new class () implements InternalHealthReporterInterface {
            /** @var list<?ExecutionArtifactInterface> */
            public array $dropped = [];

            public function report(Throwable $error, ?ExecutionArtifactInterface $artifact = null): void
            {
                $this->dropped[] = $artifact;
            }
        };
        $publisher = $this->publisher($sink, 2050, OverflowStrategy::DropLowPriority, $health);
        $first = PublicationTestArtifactFactory::priorityArtifact('first', true);
        $low = [];
        $incoming = [];
        $publisher->publish($first);
        for ($i = 0; $i < 2049; ++$i) {
            $low[] = PublicationTestArtifactFactory::priorityArtifact('low-' . $i);
            $publisher->publish($low[$i]);
        }

        for ($i = 0; $i < 2050; ++$i) {
            $incoming[] = PublicationTestArtifactFactory::priorityArtifact('incoming-' . $i, true);
            $publisher->publish($incoming[$i]);
        }

        self::assertSame(2050, $publisher->bufferedCount());
        self::assertSame(2050, $publisher->estimatedBytes());
        self::assertSame(2050, $publisher->droppedCount());
        self::assertSame([...$low, $first], $health->dropped);
        $tail = new ReflectionProperty(BufferedArtifactPublisher::class, 'tail');
        self::assertLessThan(2050 + 1024, $tail->getValue($publisher));
        $publisher->close();
        self::assertSame($incoming, $sink->published());
        self::assertSame(0, $publisher->estimatedBytes());
        self::assertSame(0, $tail->getValue($publisher));
    }

    /**
     * @throws Throwable
     */
    public function testHealthCallbackFlushesOnceAfterSparsePriorityCompaction(): void
    {
        $sink = new InMemoryArtifactPublisher();
        $health = new class () implements InternalHealthReporterInterface {
            public ?BufferedArtifactPublisher $publisher = null;

            /** @var list<?ExecutionArtifactInterface> */
            public array $dropped = [];

            /** @var array{int, int, int}|null */
            public ?array $stateAtCompaction = null;

            public function report(Throwable $error, ?ExecutionArtifactInterface $artifact = null): void
            {
                $this->dropped[] = $artifact;
                if (count($this->dropped) !== 1024 || !$this->publisher instanceof BufferedArtifactPublisher) {
                    return;
                }

                $tail = new ReflectionProperty(BufferedArtifactPublisher::class, 'tail');
                $tailValue = $tail->getValue($this->publisher);
                if (!is_int($tailValue)) {
                    return;
                }

                $this->stateAtCompaction = [
                    $this->publisher->bufferedCount(),
                    $this->publisher->estimatedBytes(),
                    $tailValue,
                ];
                $this->publisher->flush();
            }
        };
        $publisher = $this->publisher($sink, 1025, OverflowStrategy::DropLowPriority, $health);
        $health->publisher = $publisher;
        $first = PublicationTestArtifactFactory::priorityArtifact('first', true);
        $publisher->publish($first);
        $low = [];
        for ($i = 0; $i < 1024; ++$i) {
            $low[] = PublicationTestArtifactFactory::priorityArtifact('low-' . $i);
            $publisher->publish($low[$i]);
        }

        $incoming = [];
        for ($i = 0; $i < 1024; ++$i) {
            $incoming[] = PublicationTestArtifactFactory::priorityArtifact('incoming-' . $i, true);
            $publisher->publish($incoming[$i]);
        }

        self::assertSame([1024, 1024, 1024], $health->stateAtCompaction);
        self::assertSame($low, $health->dropped);
        self::assertSame([$first, ...array_slice($incoming, 0, 1023)], $sink->published());
        self::assertSame(1, $publisher->bufferedCount());
        self::assertSame(1, $publisher->estimatedBytes());
        self::assertSame(1024, $publisher->droppedCount());
        $publisher->flush();
        self::assertSame([$first, ...$incoming], $sink->published());
        self::assertSame(0, $publisher->bufferedCount());
        self::assertSame(0, $publisher->estimatedBytes());
    }

    /**
     * @throws Throwable
     */
    public function testAppendCompactsBeforeIndexOverflow(): void
    {
        $sink = new InMemoryArtifactPublisher();
        $publisher = $this->publisher($sink, 3, OverflowStrategy::DropNewest);
        $first = PublicationTestArtifactFactory::priorityArtifact('first');
        $second = PublicationTestArtifactFactory::priorityArtifact('second');
        $publisher->publish($first);
        $buffer = new ReflectionProperty(BufferedArtifactPublisher::class, 'buffer');
        $items = $buffer->getValue($publisher);
        self::assertIsArray($items);
        $buffer->setValue($publisher, [PHP_INT_MAX - 1 => $items[0]]);
        $head = new ReflectionProperty(BufferedArtifactPublisher::class, 'head');
        $tail = new ReflectionProperty(BufferedArtifactPublisher::class, 'tail');
        $head->setValue($publisher, PHP_INT_MAX - 1);
        $tail->setValue($publisher, PHP_INT_MAX);

        $publisher->publish($second);
        self::assertSame(2, $publisher->bufferedCount());
        self::assertSame(2, $publisher->estimatedBytes());
        $publisher->close();
        self::assertSame([$first, $second], $sink->published());
    }

    /**
     * @throws Throwable
     */
    public function testSinkCanPublishDuringDrainAfterHeadRemoval(): void
    {
        $first = PublicationTestArtifactFactory::priorityArtifact('first');
        $second = PublicationTestArtifactFactory::priorityArtifact('second');
        $third = PublicationTestArtifactFactory::priorityArtifact('third');
        $sink = new class ($third) implements ArtifactPublisherInterface {
            public ?BufferedArtifactPublisher $publisher = null;

            /** @var list<ExecutionArtifactInterface> */
            public array $received = [];

            public function __construct(private readonly ExecutionArtifactInterface $incoming)
            {
            }

            public function publish(ExecutionArtifactInterface $artifact): void
            {
                $this->received[] = $artifact;
                if (count($this->received) === 1) {
                    $this->publisher?->publish($this->incoming);
                }
            }
        };
        $publisher = $this->publisher($sink, 3, OverflowStrategy::DropNewest);
        $sink->publisher = $publisher;
        $publisher->publish($first);
        $publisher->publish($second);
        $publisher->flush();

        self::assertSame([$first, $second, $third], $sink->received);
        self::assertSame(0, $publisher->bufferedCount());
        self::assertSame(0, $publisher->estimatedBytes());
    }
}
