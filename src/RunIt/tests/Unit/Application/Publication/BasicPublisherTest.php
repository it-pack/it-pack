<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Publication;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Adapter\Null\NullArtifactPublisher;
use ItPack\RunIt\Application\Publication\CallbackArtifactPublisher;
use ItPack\RunIt\Application\Publication\CompositeArtifactPublisher;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Observation\Observation;
use ItPack\RunIt\Domain\Observation\ObservationId;
use RuntimeException;

/**
 * @group application
 * @group publication
 */
final class BasicPublisherTest extends Unit
{
    private function artifact(): ExecutionArtifactInterface
    {
        return Observation::info(ObservationId::fromString('obs-1'), ExecutionId::fromString('exec-1'), 'hello');
    }

    public function testNullPublisherDiscards(): void
    {
        $publisher = new NullArtifactPublisher();
        $publisher->publish($this->artifact());

        $this->expectNotToPerformAssertions();
    }

    public function testInMemoryPublisherRetainsOrder(): void
    {
        $publisher = new InMemoryArtifactPublisher();
        $publisher->publish(Observation::info(ObservationId::fromString('a'), ExecutionId::fromString('e'), '1'));
        $publisher->publish(Observation::info(ObservationId::fromString('b'), ExecutionId::fromString('e'), '2'));

        self::assertCount(2, $publisher->published());
        self::assertSame('a', $publisher->published()[0]->normalize()['id']);
        self::assertSame('b', $publisher->published()[1]->normalize()['id']);
    }

    public function testCallbackPublisherForwards(): void
    {
        $seen = [];
        $publisher = new CallbackArtifactPublisher(static function (ExecutionArtifactInterface $artifact) use (&$seen): void {
            $seen[] = $artifact;
        });

        $artifact = $this->artifact();
        $publisher->publish($artifact);

        self::assertSame([$artifact], $seen);
    }

    public function testEmptyCompositePublishesNothing(): void
    {
        $composite = new CompositeArtifactPublisher([]);
        $composite->publish($this->artifact());

        $this->expectNotToPerformAssertions();
    }

    public function testCompositePreservesOrderAcrossPublishers(): void
    {
        $first = new InMemoryArtifactPublisher();
        $second = new InMemoryArtifactPublisher();

        $composite = new CompositeArtifactPublisher([$first, $second]);
        $composite->publish($this->artifact());

        self::assertCount(1, $first->published());
        self::assertCount(1, $second->published());
    }

    public function testCompositeAttemptsAllSinksAndSurfacesFirstFailure(): void
    {
        $good = new InMemoryArtifactPublisher();
        $bad = new CallbackArtifactPublisher(static function (): void {
            throw new RuntimeException('sink failed');
        });

        $composite = new CompositeArtifactPublisher([$good, $bad]);

        $thrown = null;
        try {
            $composite->publish($this->artifact());
        } catch (RuntimeException $error) {
            $thrown = $error;
        }

        self::assertNotNull($thrown);
        self::assertSame('sink failed', $thrown->getMessage());
        self::assertCount(1, $good->published());
    }

    public function testCompositeFlushDelegatesToFlushableSinks(): void
    {
        $flushed = 0;
        $inner = new CallbackArtifactPublisher(
            static function (): void {
            },
            static function () use (&$flushed): void {
                $flushed++;
            },
        );

        $composite = new CompositeArtifactPublisher([$inner]);
        $composite->flush();

        self::assertSame(1, $flushed);
    }
}
