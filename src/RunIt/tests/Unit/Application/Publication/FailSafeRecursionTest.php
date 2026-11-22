<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Publication;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Application\Publication\FailClosedPolicy;
use ItPack\RunIt\Application\Publication\FailOpenPolicy;
use ItPack\RunIt\Application\Publication\FailSafeArtifactPublisher;
use ItPack\RunIt\Application\Publication\FallbackErrorLogReporter;
use ItPack\RunIt\Application\Publication\RecursivePublicationException;
use ItPack\RunIt\Contract\Application\Publication\InternalHealthReporterInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Exception\RunItExceptionInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Domain\Shared\ArtifactField;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use RuntimeException;
use Throwable;

/**
 * The fail-safe publisher carries the default stack's recursion guard (Phase 0,
 * T0.3): a publish arriving while publication is already active on the same path
 * is reported to the health channel exactly once per attempt and never forwarded.
 */
#[CoversClass(FailSafeArtifactPublisher::class)]
#[UsesClass(InMemoryArtifactPublisher::class)]
#[UsesClass(FailOpenPolicy::class)]
#[UsesClass(FailClosedPolicy::class)]
#[UsesClass(FallbackErrorLogReporter::class)]
#[UsesClass(RecursivePublicationException::class)]
final class FailSafeRecursionTest extends Unit
{
    /** @var list<array{0: Throwable, 1: ExecutionArtifactInterface}> */
    private array $reported = [];

    /**
     * @param list<array{0: Throwable, 1: ExecutionArtifactInterface}> $sink
     */
    private function health(array &$sink): InternalHealthReporterInterface
    {
        return new class ($sink) implements InternalHealthReporterInterface {
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
    }

    public function testNestedPublishIsReportedExactlyOnceAndNotForwarded(): void
    {
        // Simulates self-instrumentation: while the pipeline is publishing, an
        // inner stage republishes through the *same* fail-safe publisher.
        $inner = new InMemoryArtifactPublisher();
        $holder = new ReentrantPipelineHolder();
        $reentrant = new readonly class ($holder) implements ArtifactPublisherInterface {
            public function __construct(private ReentrantPipelineHolder $holder)
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

    public function testReenteringReporterIsNotCalledRecursivelyAndResets(): void
    {
        $holder = new ReentrantPipelineHolder();
        $calls = 0;
        $health = new class ($holder, $calls) implements InternalHealthReporterInterface {
            public function __construct(private readonly ReentrantPipelineHolder $holder, private int &$calls)
            {
            }

            public function report(Throwable $error, ExecutionArtifactInterface $artifact): void
            {
                ++$this->calls;
                $this->holder->publisher?->publish($artifact);
            }
        };
        $inner = new class ($holder) implements ArtifactPublisherInterface {
            public int $calls = 0;

            public function __construct(private readonly ReentrantPipelineHolder $holder)
            {
            }

            public function publish(ExecutionArtifactInterface $artifact): void
            {
                ++$this->calls;
                $this->holder->publisher?->publish($artifact);
            }
        };
        $holder->publisher = new FailSafeArtifactPublisher($inner, new FailOpenPolicy(), $health);

        $holder->publisher->publish(new SelfInstrumentedArtifact());
        $holder->publisher->publish(new SelfInstrumentedArtifact());

        self::assertSame(2, $calls);
        self::assertSame(2, $inner->calls);
    }

    public function testThrowingReenteringReporterDoesNotReplaceFailClosedError(): void
    {
        $holder = new ReentrantPipelineHolder();
        $failure = new RuntimeException('publication failed');
        $health = new class ($holder) implements InternalHealthReporterInterface {
            public int $calls = 0;

            public function __construct(private readonly ReentrantPipelineHolder $holder)
            {
            }

            public function report(Throwable $error, ExecutionArtifactInterface $artifact): void
            {
                ++$this->calls;
                $this->holder->publisher?->publish($artifact);
                throw new RuntimeException('reporter secret');
            }
        };
        $inner = new readonly class ($failure) implements ArtifactPublisherInterface {
            public function __construct(private RuntimeException $failure)
            {
            }

            public function publish(ExecutionArtifactInterface $artifact): void
            {
                throw $this->failure;
            }
        };
        $holder->publisher = new FailSafeArtifactPublisher($inner, new FailClosedPolicy(), $health);
        $caught = [];
        for ($i = 0; $i < 2; ++$i) {
            try {
                $holder->publisher->publish(new SelfInstrumentedArtifact());
            } catch (Throwable $error) {
                $caught[] = $error;
            }
        }

        self::assertSame([$failure, $failure], $caught);
        self::assertSame(2, $health->calls);
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
