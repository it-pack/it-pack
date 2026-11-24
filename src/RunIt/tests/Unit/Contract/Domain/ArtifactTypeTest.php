<?php

declare(strict_types=1);

namespace Tests\Unit\Contract\Domain;

use Codeception\Test\Unit;
use ItPack\RunIt\Contract\Domain\ArtifactType;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\ExecutionInterface;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Measurement\Measurement;
use ItPack\RunIt\Domain\Measurement\MeasurementName;
use ItPack\RunIt\Domain\Observation\Observation;
use ItPack\RunIt\Domain\Observation\ObservationId;
use PHPUnit\Framework\Attributes\CoversClass;
use ValueError;

#[CoversClass(ArtifactType::class)]
final class ArtifactTypeTest extends Unit
{
    public function testAllCasesHaveValues(): void
    {
        self::assertSame('execution', ArtifactType::Execution->value);
        self::assertSame('observation', ArtifactType::Observation->value);
        self::assertSame('activity', ArtifactType::Activity->value);
        self::assertSame('measurement', ArtifactType::Measurement->value);
        self::assertSame('occurrence', ArtifactType::Occurrence->value);
        self::assertSame('snapshot', ArtifactType::Snapshot->value);
        self::assertSame('unknown', ArtifactType::UNKNOWN);
    }

    public function testTypesListContainsAllCases(): void
    {
        self::assertContains('execution', ArtifactType::TYPES);
        self::assertContains('observation', ArtifactType::TYPES);
        self::assertContains('activity', ArtifactType::TYPES);
        self::assertContains('measurement', ArtifactType::TYPES);
        self::assertContains('occurrence', ArtifactType::TYPES);
        self::assertContains('snapshot', ArtifactType::TYPES);
        self::assertCount(6, ArtifactType::TYPES);
    }

    public function testInterfacesMap(): void
    {
        self::assertArrayHasKey(ArtifactType::Execution->value, ArtifactType::INTERFACES);
        self::assertArrayHasKey(ArtifactType::Observation->value, ArtifactType::INTERFACES);
        self::assertArrayHasKey(ArtifactType::Activity->value, ArtifactType::INTERFACES);
        self::assertArrayHasKey(ArtifactType::Measurement->value, ArtifactType::INTERFACES);
        self::assertArrayHasKey(ArtifactType::Occurrence->value, ArtifactType::INTERFACES);
        self::assertArrayHasKey(ArtifactType::Snapshot->value, ArtifactType::INTERFACES);
    }

    public function testValidWithTypedArtifact(): void
    {
        $measurement = Measurement::counter(
            MeasurementName::fromString('hits'),
            ExecutionId::fromString('exec-1'),
            1,
        );

        self::assertTrue(ArtifactType::valid($measurement));
    }

    public function testTypeFromTypedArtifact(): void
    {
        $measurement = Measurement::counter(
            MeasurementName::fromString('hits'),
            ExecutionId::fromString('exec-1'),
            1,
        );

        self::assertSame('measurement', ArtifactType::type($measurement));
    }

    public function testFromInstanceMapsObservation(): void
    {
        self::assertSame(
            ArtifactType::Observation,
            ArtifactType::fromInstance(
                Observation::info(
                    ObservationId::fromString('obs-1'),
                    ExecutionId::fromString('exec-1'),
                    'msg'
                )
            ),
        );
    }

    public function testTryFromArtifactReturnsNullForUnknown(): void
    {
        $unknown = new class () implements ExecutionArtifactInterface {
            public function normalize(): array
            {
                return [];
            }
        };

        self::assertNull(ArtifactType::tryFromArtifact($unknown));
    }

    public function testFromArtifactThrowsForUnknown(): void
    {
        $this->expectException(ValueError::class);

        $unknown = new class () implements ExecutionArtifactInterface {
            public function normalize(): array
            {
                return [];
            }
        };

        ArtifactType::fromArtifact($unknown);
    }

    public function testInterfaceReturnsFqcn(): void
    {
        self::assertSame(
            ExecutionInterface::class,
            ArtifactType::Execution->interface()
        );
    }

    public function testIsInstance(): void
    {
        $measurement = Measurement::counter(
            MeasurementName::fromString('hits'),
            ExecutionId::fromString('exec-1'),
            1,
        );

        self::assertTrue(ArtifactType::Measurement->isInstance($measurement));
        self::assertFalse(ArtifactType::Observation->isInstance($measurement));
    }
}
