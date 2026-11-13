<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Artifact;

use Codeception\Attribute\DataProvider;
use Codeception\Test\Unit;
use ItPack\RunIt\Contract\Domain\ActivityInterface;
use ItPack\RunIt\Contract\Domain\ArtifactType;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\ExecutionInterface;
use ItPack\RunIt\Contract\Domain\MeasurementInterface;
use ItPack\RunIt\Contract\Domain\ObservationInterface;
use ItPack\RunIt\Contract\Domain\OccurrenceInterface;
use ItPack\RunIt\Contract\Domain\SnapshotInterface;
use ItPack\RunIt\Contract\Domain\TypedExecutionArtifactInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use ValueError;

/**
 * Randomized checks of the identifier invariants.
 *
 * The generators are seeded, so a failure reproduces exactly. Each property is
 * checked against a fresh sample on every run of the suite only in the sense
 * that the sample is large; the sequence itself is deterministic.
 */
#[CoversClass(ArtifactType::class)]
#[UsesClass(ValueError::class)]
final class ArtifactTypeTest extends Unit
{
    #[DataProvider('provideCases')]
    public function testInterfaces(string $type): void
    {
        $artifactType = ArtifactType::from($type);
        self::assertTrue(
            is_a(
                $artifactType->interface(),
                ExecutionArtifactInterface::class,
                true
            )
        );
    }

    /**
     * @param class-string<ExecutionArtifactInterface> $interface
     */
    #[DataProvider('provideInterfaceMapping')]
    public function testIsInstanced(string $type, string $interface): void
    {
        $type = ArtifactType::tryFrom($type);
        self::assertInstanceOf(
            ArtifactType::class,
            $type
        );

        /** @var MockObject&ExecutionArtifactInterface $instance */
        $instance = $this->createMock($interface);

        self::assertTrue(
            $type->isInstance($instance)
        );
    }

    #[DataProvider('provideCases')]
    public function testIsInstanceRejetsArbitraryArtifact(string $type): void
    {
        $artifactType = ArtifactType::from($type);
        self::assertFalse(
            $artifactType->isInstance(
                $this->createImplementation()
            )
        );
    }

    #[DataProvider('provideInstances')]
    public function testValid(ExecutionArtifactInterface $instance): void
    {
        self::assertTrue(
            ArtifactType::valid($instance)
        );
    }

    public function testValidTypedExecutionArtifact(): void
    {
        /** @var MockObject&TypedExecutionArtifactInterface $instance */
        $instance = $this->createMock(TypedExecutionArtifactInterface::class);

        self::assertTrue(
            ArtifactType::valid($instance)
        );
    }

    public function testValidRejetsArbitraryArtifact(): void
    {
        self::assertFalse(
            ArtifactType::valid(
                $this->createImplementation()
            )
        );
    }

    #[DataProvider('provideInstances')]
    public function testFromInstance(
        ExecutionInterface
        |ObservationInterface
        |ActivityInterface
        |MeasurementInterface
        |OccurrenceInterface
        |SnapshotInterface  $instance
    ): void {
        $type = ArtifactType::fromInstance($instance);

        self::assertArrayHasKey(
            $type->value,
            ArtifactType::INTERFACES
        );

        self::assertInstanceOf(
            ArtifactType::INTERFACES[$type->value],
            $instance
        );
    }

    #[DataProvider('provideInstances')]
    public function testTryFromArtifact(ExecutionArtifactInterface  $instance): void
    {
        self::assertInstanceOf(
            ArtifactType::class,
            ArtifactType::tryFromArtifact($instance)
        );
    }

    #[DataProvider('provideCases')]
    public function testTryFromArtifactWithTypedExecutionArtifact(string $type): void
    {
        self::assertInstanceOf(
            ArtifactType::class,
            ArtifactType::tryFromArtifact(
                $this->createTypedExecutionArtifactImplementation($type)
            )
        );
    }

    public function testTryFromArtifactRejetsArbitraryArtifact(): void
    {
        self::assertNull(
            ArtifactType::tryFromArtifact(
                $this->createImplementation()
            )
        );
    }

    #[DataProvider('provideInstances')]
    public function testFromArtifact(ExecutionArtifactInterface  $instance): void
    {
        $type = ArtifactType::fromArtifact(
            $instance
        );

        self::assertInstanceOf(
            ArtifactType::INTERFACES[$type->value],
            $instance
        );
    }

    #[DataProvider('provideCases')]
    public function testFromArtifactWithTypedExecutionArtifact(string $type): void
    {
        $instance = $this->createTypedExecutionArtifactImplementation($type);

        $resolved = ArtifactType::fromArtifact(
            $instance
        );

        self::assertSame(
            $instance->artifactType(),
            $resolved->value
        );
    }

    ###########################
    # HELPERS
    ###########################

    private function createImplementation(): ExecutionArtifactInterface
    {
        return new class () implements ExecutionArtifactInterface {
            public function normalize(): array
            {
                return [];
            }
        };
    }

    private function createTypedExecutionArtifactImplementation(
        string $type
    ): MockObject&TypedExecutionArtifactInterface {
        /** @var MockObject&TypedExecutionArtifactInterface $instance */
        $instance = $this->createMock(TypedExecutionArtifactInterface::class);
        $instance->method('artifactType')->willReturn($type);

        return $instance;
    }

    /**
     * @return array<array{0: string}>
     * @phpstan-ignore method.unused
     */
    private function provideCases(): array
    {
        return [
            ['execution'],
            ['observation'],
            ['activity'],
            ['measurement'],
            ['occurrence'],
            ['snapshot'],
        ];
    }

    /**
     * @return array<array{0: MockObject&ExecutionArtifactInterface}>
     * @phpstan-ignore method.unused
     */
    private function provideInstances(): array
    {

        // @phpstan-ignore missingType.iterableValue
        return array_map(
            fn (string $interface): array => [$this->createMock($interface)],
            ArtifactType::INTERFACES
        );
    }

    /**
     * @return array<array{0: string, 1: class-string<ExecutionArtifactInterface>}>
     * @phpstan-ignore method.unused
     */
    private function provideInterfaceMapping(): array
    {
        $result = [];
        foreach (ArtifactType::INTERFACES as $type => $interface) {
            $result[] = [$type, $interface];
        }

        return $result;
    }
}
