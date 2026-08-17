<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Occurrence;

use Codeception\Test\Unit;
use ItPack\RunIt\Contract\Exception\InvalidContextValueException;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Occurrence\Occurrence;
use ItPack\RunIt\Domain\Occurrence\OccurrenceId;
use ItPack\RunIt\Domain\Occurrence\OccurrenceType;
use ItPack\RunIt\Domain\Shared\AttributeCollection;
use ItPack\RunIt\Domain\Shared\Instant;

final class OccurrenceTest extends Unit
{
    public function testStableVersionedTypeIsAcceptedAndParsed(): void
    {
        $type = OccurrenceType::fromString('itpack.configuration.loaded.v1');

        self::assertSame('itpack.configuration.loaded.v1', $type->toString());
        self::assertSame('1', $type->version());
    }

    public function testClassNamesAreRejectedAsTypes(): void
    {
        $this->expectException(\ItPack\RunIt\Contract\Exception\InvalidStringValueException::class);

        // A PHP class name is not a stable, versioned occurrence type.
        OccurrenceType::fromString('RuntimeException');
    }

    public function testOccurrenceCarriesDataAndVersion(): void
    {
        $occurrence = Occurrence::create(
            OccurrenceId::fromString('occ-1'),
            ExecutionId::fromString('exec-1'),
            OccurrenceType::fromString('itpack.configuration.loaded.v1'),
            'config-service',
            Instant::of(1000),
            ['path' => '/etc/app.yaml'],
            null,
            'tenant-7',
            'https://schema.example/config.v1.json',
            'application/json',
            AttributeCollection::empty()->with('priority', 'high'),
        );

        self::assertSame('itpack.configuration.loaded.v1', $occurrence->type()->toString());
        self::assertSame('1', $occurrence->type()->version());
        self::assertSame('config-service', $occurrence->source());
        self::assertSame('tenant-7', $occurrence->subject());
        self::assertSame(['path' => '/etc/app.yaml'], $occurrence->data());
        self::assertSame(['priority' => 'high'], $occurrence->extensionAttributes());
        self::assertSame(1000, $occurrence->timestamp());
    }

    public function testNestedObjectDataIsRejected(): void
    {
        $this->expectException(InvalidContextValueException::class);

        Occurrence::create(
            OccurrenceId::fromString('occ-2'),
            ExecutionId::fromString('exec-1'),
            OccurrenceType::fromString('itpack.system.event.v2'),
            'svc',
            Instant::of(1),
            ['nested' => new \stdClass()],
        );
    }

    public function testNormalizationIsDeterministic(): void
    {
        $occurrence = Occurrence::create(
            OccurrenceId::fromString('occ-3'),
            ExecutionId::fromString('exec-1'),
            OccurrenceType::fromString('itpack.system.event.v2'),
            'svc',
            Instant::of(1000),
            ['count' => 3],
        );

        $expected = [
            'id' => 'occ-3',
            'execution_id' => 'exec-1',
            'activity_id' => null,
            'type' => 'itpack.system.event.v2',
            'version' => '2',
            'source' => 'svc',
            'subject' => null,
            'time' => 1000,
            'data' => ['count' => 3],
            'data_schema' => null,
            'content_type' => 'application/json',
            'extension_attributes' => [],
            'sequence' => 0,
        ];

        self::assertSame($expected, $occurrence->normalize());
        self::assertSame($occurrence->normalize(), $occurrence->normalize());
    }
}
