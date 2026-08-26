<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Publication;

use Codeception\Test\Unit;
use ItPack\RunIt\Application\Publication\PublicationRoute;
use ItPack\RunIt\Application\Publication\RoutingArtifactPublisher;
use ItPack\RunIt\Contract\Application\Publication\ArtifactFilterInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Contract\Port\CloseablePublisherInterface;
use ItPack\RunIt\Contract\Port\FlushablePublisherInterface;

/**
 * @group application
 * @group publication
 */
final class RoutingArtifactPublisherTest extends Unit
{
    public function testMatchingRouteReceivesTheArtifact(): void
    {
        $route = new RecordingSink();
        $fallback = new RecordingSink();
        $router = new RoutingArtifactPublisher(
            [new PublicationRoute($this->acceptAll(), $route)],
            $fallback,
        );

        $router->publish($this->artifact());

        self::assertCount(1, $route->published);
        self::assertCount(0, $fallback->published);
    }

    public function testUnmatchedArtifactGoesToTheFallbackWhenConfigured(): void
    {
        $route = new RecordingSink();
        $fallback = new RecordingSink();
        $router = new RoutingArtifactPublisher(
            [new PublicationRoute($this->acceptNone(), $route)],
            $fallback,
        );

        $router->publish($this->artifact());

        self::assertCount(0, $route->published);
        self::assertCount(1, $fallback->published);
    }

    public function testUnmatchedArtifactWithoutFallbackIsSilentlyIgnored(): void
    {
        $route = new RecordingSink();
        $router = new RoutingArtifactPublisher(
            [new PublicationRoute($this->acceptNone(), $route)],
        );

        $router->publish($this->artifact());

        self::assertCount(0, $route->published);
    }

    public function testFlushAndCloseAreForwardedToSupportingPublishersOnly(): void
    {
        $flushableRoute = new LifecycleSpy();
        $plainFallback = new RecordingSink();
        $router = new RoutingArtifactPublisher(
            [new PublicationRoute($this->acceptAll(), $flushableRoute)],
            $plainFallback,
        );

        $router->flush();
        $router->close();

        self::assertSame(['flush', 'close'], $flushableRoute->calls);
    }

    public function testFlushAndCloseReachASupportingFallback(): void
    {
        $plainRoute = new RecordingSink();
        $fallback = new LifecycleSpy();
        $router = new RoutingArtifactPublisher(
            [new PublicationRoute($this->acceptAll(), $plainRoute)],
            $fallback,
        );

        $router->flush();
        $router->close();

        self::assertSame(['flush', 'close'], $fallback->calls);
    }

    private function acceptAll(): ArtifactFilterInterface
    {
        return new class () implements ArtifactFilterInterface {
            public function accepts(ExecutionArtifactInterface $artifact): bool
            {
                return true;
            }
        };
    }

    private function acceptNone(): ArtifactFilterInterface
    {
        return new class () implements ArtifactFilterInterface {
            public function accepts(ExecutionArtifactInterface $artifact): bool
            {
                return false;
            }
        };
    }

    private function artifact(): ExecutionArtifactInterface
    {
        return new class () implements ExecutionArtifactInterface {
            public function normalize(): array
            {
                return ['kind' => 'test.artifact'];
            }
        };
    }
}

final class RecordingSink implements ArtifactPublisherInterface
{
    /** @var list<ExecutionArtifactInterface> */
    public array $published = [];

    public function publish(ExecutionArtifactInterface $artifact): void
    {
        $this->published[] = $artifact;
    }
}

final class LifecycleSpy implements ArtifactPublisherInterface, FlushablePublisherInterface, CloseablePublisherInterface
{
    /** @var list<string> */
    public array $calls = [];

    public function publish(ExecutionArtifactInterface $artifact): void
    {
    }

    public function flush(): void
    {
        $this->calls[] = 'flush';
    }

    public function close(): void
    {
        $this->calls[] = 'close';
    }
}
