<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Psr\Event;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Adapter\Psr\Event\DefaultPsrEventToOccurrenceMapper;
use ItPack\RunIt\Adapter\Psr\Event\OccurrenceEvent;
use ItPack\RunIt\Adapter\Psr\Event\OccurrenceEventWrapper;
use ItPack\RunIt\Adapter\Psr\Event\OccurrencePublisher;
use ItPack\RunIt\Contract\Application\Publication\PublicationFailurePolicyInterface;
use ItPack\RunIt\Contract\Domain\OccurrenceInterface;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Occurrence\Occurrence;
use ItPack\RunIt\Domain\Occurrence\OccurrenceId;
use ItPack\RunIt\Domain\Occurrence\OccurrenceType;
use ItPack\RunIt\Domain\Shared\Instant;
use RuntimeException;
use Tests\Support\Fixture\Psr\RecordingEventDispatcher;

final class OccurrenceEventTest extends Unit
{
    public function testWrapperPreservesOccurrenceImmutability(): void
    {
        $occurrence = $this->occurrence();
        $event = new OccurrenceEventWrapper()->wrap($occurrence);

        self::assertInstanceOf(OccurrenceEvent::class, $event);
        self::assertSame($occurrence, $event->occurrence);
        self::assertFalse($event->isPropagationStopped());

        $event->stopPropagation();
        self::assertTrue($event->isPropagationStopped());
    }

    public function testPublisherDispatchesOccurrenceEvent(): void
    {
        $dispatcher = new RecordingEventDispatcher();
        $publisher = new OccurrencePublisher($dispatcher);
        $occurrence = $this->occurrence();

        $publisher->publish($occurrence);

        self::assertCount(1, $dispatcher->dispatched());
        self::assertInstanceOf(OccurrenceEvent::class, $dispatcher->dispatched()[0]);
        self::assertSame($occurrence, $dispatcher->dispatched()[0]->occurrence);
    }

    public function testPublisherIgnoresNonOccurrenceArtifacts(): void
    {
        $dispatcher = new RecordingEventDispatcher();
        $publisher = new OccurrencePublisher($dispatcher);

        $publisher->publish(
            \ItPack\RunIt\Domain\Observation\Observation::info(
                \ItPack\RunIt\Domain\Observation\ObservationId::fromString('o1'),
                ExecutionId::fromString('e1'),
                'msg',
            ),
        );

        self::assertCount(0, $dispatcher->dispatched());
    }

    public function testListenerOrderingAndStopPropagation(): void
    {
        $dispatcher = new RecordingEventDispatcher();
        $publisher = new OccurrencePublisher($dispatcher);

        $dispatcher->addListener(static function (OccurrenceEvent $event): void {
            $event->stopPropagation();
        });
        $dispatcher->addListener(static function (): void {
            throw new \LogicException('must not run');
        });

        $publisher->publish($this->occurrence());

        self::assertSame(['listener_0'], $dispatcher->listenerCalls());
    }

    public function testListenerExceptionFollowsConfiguredPolicy(): void
    {
        $dispatcher = new RecordingEventDispatcher();
        $policy = new class () implements PublicationFailurePolicyInterface {
            /** @var list<\Throwable> */
            private array $errors = [];

            public function handle(
                \Throwable $error,
                \ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface $artifact
            ): void {
                $this->errors[] = $error;
            }

            /**
             * @return list<\Throwable>
             */
            public function collected(): array
            {
                return $this->errors;
            }
        };

        $dispatcher->addListener(static function (): void {
            throw new RuntimeException('listener failed');
        });

        $publisher = new OccurrencePublisher($dispatcher, $policy);
        $publisher->publish($this->occurrence());

        self::assertCount(1, $policy->collected());
        self::assertInstanceOf(RuntimeException::class, $policy->collected()[0]);
    }

    public function testListenerExceptionRethrowsWithoutPolicy(): void
    {
        $dispatcher = new RecordingEventDispatcher();
        $dispatcher->addListener(static function (): void {
            throw new RuntimeException('listener failed');
        });

        $publisher = new OccurrencePublisher($dispatcher);

        $this->expectException(RuntimeException::class);
        $publisher->publish($this->occurrence());
    }

    public function testMapperMapsEventToOccurrence(): void
    {
        $mapper = new DefaultPsrEventToOccurrenceMapper(
            OccurrenceType::fromString('itpack.psr.event.v1'),
            ExecutionId::fromString('e-bound'),
            new RandomIdentifierGenerator(),
        );

        $event = new class () {
            public string $action = 'created';
            public int $count = 2;
        };

        $occurrence = $mapper->map($event);

        self::assertInstanceOf(OccurrenceInterface::class, $occurrence);
        self::assertSame('itpack.psr.event.v1', $occurrence->type()->toString());

        /** @var array{action: string, count: int} $data */
        $data = $occurrence->data();
        self::assertSame('created', $data['action']);
        self::assertSame(2, $data['count']);
    }

    public function testCustomDataExtractorOverridesTheDefaultProjection(): void
    {
        $mapper = new DefaultPsrEventToOccurrenceMapper(
            OccurrenceType::fromString('itpack.psr.event.v1'),
            ExecutionId::fromString('e-bound'),
            new RandomIdentifierGenerator(),
            'psr.event',
            null,
            static fn (object $event): array => ['extracted' => true],
        );

        $occurrence = $mapper->map(new \stdClass());

        self::assertSame(['extracted' => true], $occurrence->data());
    }

    public function testObjectAndResourceEventPropertiesAreSkippedInDefaultProjection(): void
    {
        $mapper = new DefaultPsrEventToOccurrenceMapper(
            OccurrenceType::fromString('itpack.psr.event.v1'),
            ExecutionId::fromString('e-bound'),
            new RandomIdentifierGenerator(),
        );

        $event = new class () {
            public string $name = 'kept';
            public object $entity;

            public function __construct()
            {
                $this->entity = new \stdClass();
            }
        };

        $data = $mapper->map($event)->data();

        self::assertSame(['name' => 'kept'], $data);
    }

    public function testMapperHonoursPerCallTypeOverride(): void
    {
        $mapper = new DefaultPsrEventToOccurrenceMapper(
            OccurrenceType::fromString('itpack.psr.event.v1'),
            ExecutionId::fromString('e-bound'),
            new RandomIdentifierGenerator(),
        );

        $occurrence = $mapper->map(new \stdClass(), 'itpack.override.v2');

        self::assertSame('itpack.override.v2', $occurrence->type()->toString());
    }

    private function occurrence(): Occurrence
    {
        return Occurrence::create(
            OccurrenceId::fromString('oc-1'),
            ExecutionId::fromString('e1'),
            OccurrenceType::fromString('itpack.test.event.v1'),
            'test-source',
            Instant::now(),
            ['detail' => 'x'],
        );
    }
}
