<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Psr\Event;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\Psr\Event\OccurrencePublisher;
use ItPack\RunIt\Contract\Application\Publication\PublicationFailurePolicyInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Occurrence\Occurrence;
use ItPack\RunIt\Domain\Occurrence\OccurrenceId;
use ItPack\RunIt\Domain\Occurrence\OccurrenceType;
use ItPack\RunIt\Domain\Shared\ArtifactSequence;
use ItPack\RunIt\Domain\Shared\AttributeCollection;
use ItPack\RunIt\Domain\Shared\Instant;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\EventDispatcher\EventDispatcherInterface;
use RuntimeException;
use Throwable;

#[CoversClass(OccurrencePublisher::class)]
final class OccurrencePublisherTest extends Unit
{
    public function testPublishNonOccurrenceIsNoOp(): void
    {
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $publisher = new OccurrencePublisher($dispatcher);

        $artifact = new class () implements ExecutionArtifactInterface {
            public function normalize(): array
            {
                return [];
            }
        };

        $publisher->publish($artifact);
    }

    public function testPublishOccurrenceDispatchesEvent(): void
    {
        $reporter = new class () implements EventDispatcherInterface {
            public bool $dispatched = false;
            public function dispatch(object $event): object
            {
                $this->dispatched = true;
                return $event;
            }
        };

        $publisher = new OccurrencePublisher($reporter);

        $occurrence = Occurrence::create(
            OccurrenceId::fromString('occ-1'),
            ExecutionId::fromString('exec-1'),
            OccurrenceType::fromString('itpack.test.v1'),
            'source',
            Instant::of(1000),
            [],
            null,
            null,
            null,
            'application/json',
            AttributeCollection::empty(),
            ArtifactSequence::initial(),
        );

        $publisher->publish($occurrence);

        self::assertTrue($reporter->dispatched);
    }

    public function testPublishDispatchFailureWithPolicyCallsPolicy(): void
    {
        $policyHandled = false;
        $policy = new class () implements PublicationFailurePolicyInterface {
            public function handle(Throwable $error, ExecutionArtifactInterface $artifact): void
            {
                $this->handled = true;
            }
            public bool $handled = false;
        };

        $dispatcher = new class () implements EventDispatcherInterface {
            public function dispatch(object $event): object
            {
                throw new RuntimeException('dispatch failed');
            }
        };

        $publisher = new OccurrencePublisher($dispatcher, $policy);

        $occurrence = Occurrence::create(
            OccurrenceId::fromString('occ-2'),
            ExecutionId::fromString('exec-2'),
            OccurrenceType::fromString('itpack.test.v1'),
            'source',
            Instant::of(1000),
            [],
            null,
            null,
            null,
            'application/json',
            AttributeCollection::empty(),
            ArtifactSequence::initial(),
        );

        $publisher->publish($occurrence);

        self::assertTrue($policy->handled);
    }

    public function testPublishDispatchFailureWithoutPolicyRethrows(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('dispatch failed');

        $dispatcher = new class () implements EventDispatcherInterface {
            public function dispatch(object $event): object
            {
                throw new RuntimeException('dispatch failed');
            }
        };

        $publisher = new OccurrencePublisher($dispatcher);

        $occurrence = Occurrence::create(
            OccurrenceId::fromString('occ-3'),
            ExecutionId::fromString('exec-3'),
            OccurrenceType::fromString('itpack.test.v1'),
            'source',
            Instant::of(1000),
            [],
            null,
            null,
            null,
            'application/json',
            AttributeCollection::empty(),
            ArtifactSequence::initial(),
        );

        $publisher->publish($occurrence);
    }
}
