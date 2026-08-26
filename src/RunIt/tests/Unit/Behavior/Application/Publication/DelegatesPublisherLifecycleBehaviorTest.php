<?php

declare(strict_types=1);

namespace Tests\Unit\Behavior\Application\Publication;

use Codeception\Test\Unit;
use ItPack\RunIt\Application\Publication\FilteringArtifactPublisher;
use ItPack\RunIt\Contract\Application\Publication\ArtifactFilterInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use Tests\Support\Fixture\LifecycleSpy;
use Tests\Support\Fixture\RecordingArtifactPublisher;

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
        $inner = new RecordingArtifactPublisher();
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
