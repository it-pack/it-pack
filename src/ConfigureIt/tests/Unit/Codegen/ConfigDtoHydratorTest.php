<?php

declare(strict_types=1);

namespace Tests\Unit\Codegen;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Codegen\ConfigDtoHydrator;
use ItPack\ConfigureIt\Codegen\GeneratedConfigDtoInterface;
use ItPack\ConfigureIt\Contract\ConfigurationSnapshotInterface;
use ItPack\ConfigureIt\Core\ConfigurationSnapshotFactory;
use ItPack\ConfigureIt\Exception\ConfigurationException;

/**
 * @covers \ItPack\ConfigureIt\Codegen\ConfigDtoHydrator
 */

final class FakeDto implements GeneratedConfigDtoInterface
{
    public function toArray(): array
    {
        return ['ok' => true];
    }

    public static function fromSnapshot(ConfigurationSnapshotInterface $snapshot): self
    {
        return new self();
    }
}

final class FakeDtoWithoutFactory implements GeneratedConfigDtoInterface
{
    public function toArray(): array
    {
        return [];
    }
}

class ConfigDtoHydratorTest extends Unit
{
    private function snapshot(): ConfigurationSnapshotInterface
    {
        return new ConfigurationSnapshotFactory()->build(['a' => 1]);
    }

    public function testHydratesDtoFromSnapshot(): void
    {
        $dto = new ConfigDtoHydrator()->hydrate(FakeDto::class, $this->snapshot());

        self::assertInstanceOf(GeneratedConfigDtoInterface::class, $dto);
        self::assertInstanceOf(FakeDto::class, FakeDto::fromSnapshot($this->snapshot()));
        verify($dto->toArray())->equals(['ok' => true]);
    }

    public function testThrowsWhenDtoClassNotLoaded(): void
    {
        $this->expectException(ConfigurationException::class);

        new ConfigDtoHydrator()->hydrate('ItPack\ConfigureIt\Codegen\NonexistentDto', $this->snapshot());
    }

    public function testThrowsWhenDtoLacksFactory(): void
    {
        $this->expectException(ConfigurationException::class);

        new ConfigDtoHydrator()->hydrate(FakeDtoWithoutFactory::class, $this->snapshot());
    }
}
