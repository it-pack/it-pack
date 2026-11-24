<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Psr\Event;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\Psr\Event\DefaultPsrEventToOccurrenceMapper;
use ItPack\RunIt\Contract\Port\IdentifierGeneratorInterface;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Occurrence\OccurrenceType;
use ItPack\RunIt\Domain\Shared\Instant;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(DefaultPsrEventToOccurrenceMapper::class)]
final class DefaultPsrEventToOccurrenceMapperTest extends Unit
{
    public function testMapWithDefaultTypeAndNoDataExtractor(): void
    {
        $mapper = new DefaultPsrEventToOccurrenceMapper(
            OccurrenceType::fromString('itpack.event.v1'),
            ExecutionId::fromString('exec-1'),
            new class () implements IdentifierGeneratorInterface {
                public function generate(): string
                {
                    return 'occ-gen-1';
                }
            },
        );

        $event = new class () {
            public string $name = 'test';
            public int $count = 5;
        };

        $occurrence = $mapper->map($event);

        self::assertSame('occ-gen-1', $occurrence->id()->toString());
        self::assertSame('exec-1', $occurrence->executionId()->toString());
        self::assertSame('itpack.event.v1', $occurrence->type()->toString());
        self::assertSame('psr.event', $occurrence->source());
    }

    public function testMapWithOverriddenType(): void
    {
        $mapper = new DefaultPsrEventToOccurrenceMapper(
            OccurrenceType::fromString('itpack.default.v1'),
            ExecutionId::fromString('exec-1'),
            new class () implements IdentifierGeneratorInterface {
                public function generate(): string
                {
                    return 'occ-gen-2';
                }
            },
        );

        $event = new class () {
            public string $name = 'test';
        };

        $occurrence = $mapper->map($event, 'itpack.override.v2');

        self::assertSame('itpack.override.v2', $occurrence->type()->toString());
        self::assertSame('2', $occurrence->type()->version());
    }

    public function testMapWithCustomSourceAndTime(): void
    {
        $mapper = new DefaultPsrEventToOccurrenceMapper(
            OccurrenceType::fromString('itpack.event.v1'),
            ExecutionId::fromString('exec-1'),
            new class () implements IdentifierGeneratorInterface {
                public function generate(): string
                {
                    return 'occ-gen-3';
                }
            },
            'custom.source',
            Instant::of(1700000000),
        );

        $event = new class () {
            public string $name = 'test';
        };

        $occurrence = $mapper->map($event);

        self::assertSame('custom.source', $occurrence->source());
        self::assertSame(1700000000, $occurrence->time()->epochSeconds());
    }

    public function testMapWithCallableDataExtractor(): void
    {
        $mapper = new DefaultPsrEventToOccurrenceMapper(
            OccurrenceType::fromString('itpack.event.v1'),
            ExecutionId::fromString('exec-1'),
            new class () implements IdentifierGeneratorInterface {
                public function generate(): string
                {
                    return 'occ-gen-4';
                }
            },
            'psr.event',
            null,
            fn (object $event): array => ['extracted' => true],
        );

        $event = new class () {
            public string $name = 'extracted-value';
        };

        $occurrence = $mapper->map($event);

        self::assertSame(['extracted' => true], $occurrence->data());
    }

    public function testMapSkipsObjectPropertiesInDefaultExtraction(): void
    {
        $mapper = new DefaultPsrEventToOccurrenceMapper(
            OccurrenceType::fromString('itpack.event.v1'),
            ExecutionId::fromString('exec-1'),
            new class () implements IdentifierGeneratorInterface {
                public function generate(): string
                {
                    return 'occ-gen-5';
                }
            },
        );

        $event = new class () {
            public string $name = 'test';
            public mixed $skip;

            public function __construct()
            {
                $this->skip = new \stdClass();
            }
        };

        $occurrence = $mapper->map($event);

        self::assertSame(['name' => 'test'], $occurrence->data());
    }

    public function testMapWithEmptyEventDataReturnsNull(): void
    {
        $mapper = new DefaultPsrEventToOccurrenceMapper(
            OccurrenceType::fromString('itpack.event.v1'),
            ExecutionId::fromString('exec-1'),
            new class () implements IdentifierGeneratorInterface {
                public function generate(): string
                {
                    return 'occ-gen-6';
                }
            },
        );

        $event = new class () {};

        $occurrence = $mapper->map($event);

        self::assertNull($occurrence->data());
    }
}
