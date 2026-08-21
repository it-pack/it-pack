<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Publication;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Application\Publication\BufferConfiguration;
use ItPack\RunIt\Application\Publication\BufferedArtifactPublisher;
use ItPack\RunIt\Application\Publication\RecordingBatchFlushObserver;
use ItPack\RunIt\Domain\Execution\Execution;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\Domain\Shared\Instant;

/**
 * @group application
 * @group publication
 */
final class BufferedArtifactPublisherFlushObserverTest extends Unit
{
    public function testFlushNotifiesObserverWithFlushedCount(): void
    {
        $inner = new InMemoryArtifactPublisher();
        $observer = new RecordingBatchFlushObserver();

        $publisher = new BufferedArtifactPublisher(BufferConfiguration::default(), $inner, null, null, null, $observer);

        for ($i = 0; $i < 3; $i++) {
            $publisher->publish($this->execution());
        }

        $publisher->flush();

        self::assertSame(1, $observer->flushCount());
        self::assertSame(3, $observer->totalFlushed());
        self::assertSame(3, $observer->maxBatch());
        self::assertSame(0, $observer->lastRemainingBuffered());
        self::assertGreaterThanOrEqual(0, $observer->lastFlushMicroseconds());
        self::assertSame(3, count($inner->published()));
    }

    public function testCloseNotifiesObserver(): void
    {
        $inner = new InMemoryArtifactPublisher();
        $observer = new RecordingBatchFlushObserver();

        $publisher = new BufferedArtifactPublisher(BufferConfiguration::default(), $inner, null, null, null, $observer);

        for ($i = 0; $i < 2; $i++) {
            $publisher->publish($this->execution());
        }

        $publisher->close();

        self::assertSame(1, $observer->flushCount());
        self::assertSame(2, $observer->totalFlushed());
        self::assertSame(2, count($inner->published()));
    }

    public function testObserverIsOptional(): void
    {
        $inner = new InMemoryArtifactPublisher();

        $publisher = new BufferedArtifactPublisher(BufferConfiguration::default(), $inner);

        $publisher->publish($this->execution());
        $publisher->flush();

        self::assertSame(1, count($inner->published()));
    }

    private function execution(): Execution
    {
        return Execution::create(
            ExecutionId::fromString('exec_' . bin2hex(random_bytes(8))),
            ExecutionDefinition::create(
                ExecutionName::fromString('runit.dogfood.test'),
                ExecutionKind::fromString('cli.command'),
            ),
            Instant::now(),
        );
    }
}
