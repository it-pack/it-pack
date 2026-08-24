<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Publication;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Application\Publication\FailOpenPolicy;
use ItPack\RunIt\Application\Publication\FailSafeArtifactPublisher;
use ItPack\RunIt\Application\Publication\RecursivePublicationException;
use ItPack\RunIt\Contract\Application\Publication\InternalHealthReporterInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Exception\RunItExceptionInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Domain\Shared\ArtifactField;

/**
 * The fail-safe publisher carries the default stack's recursion guard (Phase 0,
 * T0.3): a publish arriving while publication is already active on the same path
 * is reported to the health channel exactly once per attempt and never forwarded.
 */
final class FailSafeRecursionTest extends Unit
{
    /** @var list<array{0: \Throwable, 1: ExecutionArtifactInterface}> */
    private array $reported = [];

    /**
     * @param list<array{0: \Throwable, 1: ExecutionArtifactInterface}> $sink
     */
    private function health(array &$sink): InternalHealthReporterInterface
    {
        return new class ($sink) implements InternalHealthReporterInterface {
            /**
             * @param list<array{0: \Throwable, 1: ExecutionArtifactInterface}> $sink
             */
            public function __construct(private array &$sink)
            {
            }

            public function report(\Throwable $error, ExecutionArtifactInterface $artifact): void
            {
                $sink = &$this->sink;
                $sink[] = [$error, $artifact];
            }
        };
    }

    public function testNestedPublishIsReportedExactlyOnceAndNotForwarded(): void
    {
        // Simulates self-instrumentation: while the pipeline is publishing, an
        // inner stage republishes through the *same* fail-safe publisher.
        $inner = new InMemoryArtifactPublisher();
        $holder = new ReentrantPipelineHolder();
        $reentrant = new class ($holder) implements ArtifactPublisherInterface {
            public function __construct(private readonly ReentrantPipelineHolder $holder)
            {
            }

            public function publish(ExecutionArtifactInterface $artifact): void
            {
                $this->holder->publisher?->publish(new SelfInstrumentedArtifact());
            }
        };

        $safe = new FailSafeArtifactPublisher($reentrant, new FailOpenPolicy(), $this->health($this->reported));
        $holder->publisher = $safe;

        $safe->publish(new SelfInstrumentedArtifact());

        self::assertCount(1, $this->reported, 'the recursive attempt must be reported exactly once');
        self::assertInstanceOf(RecursivePublicationException::class, $this->reported[0][0]);
        self::assertInstanceOf(RunItExceptionInterface::class, $this->reported[0][0]);
        self::assertSame([], $inner->published(), 'a recursive artifact must never reach the inner publisher');
    }

    public function testDepthIsRestoredAfterTheOuterPublishCompletes(): void
    {
        $inner = new InMemoryArtifactPublisher();
        $safe = new FailSafeArtifactPublisher($inner, new FailOpenPolicy(), $this->health($this->reported));

        $safe->publish(new SelfInstrumentedArtifact());
        self::assertCount(1, $inner->published(), 'the outer publish is forwarded normally');

        $safe->publish(new SelfInstrumentedArtifact());
        self::assertCount(2, $inner->published(), 'subsequent publishes work after depth is restored');
        self::assertSame([], $this->reported, 'no recursion was involved in either publish');
    }
}

/**
 * Lets the reentrant publisher reach the publisher that is currently publishing it.
 */
final class ReentrantPipelineHolder
{
    public function __construct(public ?FailSafeArtifactPublisher $publisher = null)
    {
    }
}

final class SelfInstrumentedArtifact implements ExecutionArtifactInterface
{
    public function normalize(): array
    {
        return [ArtifactField::ID => 'self-instrumented'];
    }
}
