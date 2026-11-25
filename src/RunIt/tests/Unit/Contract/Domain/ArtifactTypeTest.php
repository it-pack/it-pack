<?php

declare(strict_types=1);

namespace Tests\Unit\Contract\Domain;

use Codeception\Test\Unit;
use ItPack\RunIt\Contract\Domain\ArtifactType;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\ExecutionInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use ValueError;

#[CoversClass(ArtifactType::class)]
final class ArtifactTypeTest extends Unit
{
    public function testCasesCoverAllTypes(): void
    {
        self::assertSame('execution', ArtifactType::Execution->value);
        self::assertSame('observation', ArtifactType::Observation->value);
        self::assertSame('activity', ArtifactType::Activity->value);
        self::assertSame('measurement', ArtifactType::Measurement->value);
        self::assertSame('occurrence', ArtifactType::Occurrence->value);
        self::assertSame('snapshot', ArtifactType::Snapshot->value);
        self::assertSame('unknown', ArtifactType::UNKNOWN);
    }

    public function testValidAndType(): void
    {
        $execution = $this->createMock(ExecutionInterface::class);

        self::assertTrue(ArtifactType::valid($execution));
        self::assertSame('execution', ArtifactType::type($execution));
        self::assertSame(ArtifactType::Execution, ArtifactType::fromInstance($execution));
    }

    public function testTryFromAndFromArtifact(): void
    {
        $execution = $this->createMock(ExecutionInterface::class);

        self::assertSame(ArtifactType::Execution, ArtifactType::tryFromArtifact($execution));
        self::assertSame(ArtifactType::Execution, ArtifactType::fromArtifact($execution));
    }

    public function testInterfaceAndIsInstance(): void
    {
        self::assertSame(ExecutionInterface::class, ArtifactType::Execution->interface());
        $execution = $this->createMock(ExecutionInterface::class);
        self::assertTrue(ArtifactType::Execution->isInstance($execution));
    }

    public function testFromArtifactThrowsForUnknown(): void
    {
        $unknown = $this->createMock(ExecutionArtifactInterface::class);

        $this->expectException(ValueError::class);
        ArtifactType::fromArtifact($unknown);
    }

    public function testTryFromArtifactReturnsNullForUnknown(): void
    {
        $unknown = $this->createMock(ExecutionArtifactInterface::class);

        self::assertNull(ArtifactType::tryFromArtifact($unknown));
    }
}
