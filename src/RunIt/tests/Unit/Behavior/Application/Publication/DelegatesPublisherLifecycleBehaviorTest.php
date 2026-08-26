<?php

declare(strict_types=1);

namespace Tests\Unit\Behavior\Application\Publication;

use Codeception\Test\Unit;
use ItPack\RunIt\Application\Publication\FilteringArtifactPublisher;
use ItPack\RunIt\Contract\Application\Publication\ArtifactFilterInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Contract\Port\CloseablePublisherInterface;
use ItPack\RunIt\Contract\Port\FlushablePublisherInterface;

/**
 * Verifies the shared decorator lifecycle delegation through a real decorator
 * (`FilteringArtifactPublisher`): flush and close reach the inner publisher
 * only when it supports them, so decorators stay safe in front of plain sinks.
 *
 * @group behavior
 * @group publication
 */
final class DelegatesPublisherLifecycleBehaviorTest extends Unit
{
    public function testFlushAndCloseDelegateToASupportingInner(): void
    {
        $inner = new LifecycleSpy();
        $decorator = new FilteringArtifactPublisher($this->acceptAll(), $inner);

        $decorator->flush();
        $decorator->close();

        self::assertSame(['flush', 'close'], $inner->calls);
    }

    public function testAPlainInnerIsLeftUntouched(): void
    {
        $inner = new PlainSink();
        $decorator = new FilteringArtifactPublisher($this->acceptAll(), $inner);

        $decorator->flush();
        $decorator->close();

        self::assertCount(0, $inner->published);
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

final class PlainSink implements ArtifactPublisherInterface
{
    /** @var list<ExecutionArtifactInterface> */
    public array $published = [];

    public function publish(ExecutionArtifactInterface $artifact): void
    {
        $this->published[] = $artifact;
    }
}
