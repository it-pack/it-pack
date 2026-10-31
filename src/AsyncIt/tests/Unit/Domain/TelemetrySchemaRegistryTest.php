<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use InvalidArgumentException;
use ItPack\AsyncIt\Contract\TelemetrySchemaRegistryInterface;
use ItPack\AsyncIt\Domain\TelemetrySchemaRegistry;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ItPack\AsyncIt\Domain\TelemetrySchemaRegistry
 * @covers \ItPack\AsyncIt\Contract\TelemetrySchemaRegistryInterface
 */
final class TelemetrySchemaRegistryTest extends TestCase
{
    public function testRegisterAndGetByVersion(): void
    {
        $registry = new TelemetrySchemaRegistry();

        $schema = ['type' => 'object', 'properties' => ['kind' => ['type' => 'string']]];
        $registry->register('occurrence', 1, $schema);

        self::assertTrue($registry->has('occurrence', 1));
        self::assertSame($schema, $registry->get('occurrence', 1));
    }

    public function testGetReturnsNullForMissingVersion(): void
    {
        $registry = new TelemetrySchemaRegistry();
        $registry->register('occurrence', 1, ['type' => 'object']);

        self::assertNull($registry->get('occurrence', 2));
        self::assertFalse($registry->has('occurrence', 2));
    }

    public function testLatestReturnsHighestVersion(): void
    {
        $registry = new TelemetrySchemaRegistry();
        $registry->register('measurement', 1, ['version' => 1]);
        $registry->register('measurement', 3, ['version' => 3]);
        $registry->register('measurement', 2, ['version' => 2]);

        self::assertSame(['version' => 3], $registry->latest('measurement'));
    }

    public function testLatestReturnsNullForUnknownType(): void
    {
        $registry = new TelemetrySchemaRegistry();

        self::assertNull($registry->latest('unknown'));
    }

    public function testTypesReturnsRegisteredTypes(): void
    {
        $registry = new TelemetrySchemaRegistry();
        $registry->register('occurrence', 1, []);
        $registry->register('observation', 1, []);
        $registry->register('measurement', 1, []);

        self::assertSame(['occurrence', 'observation', 'measurement'], $registry->types());
    }

    public function testRegisterRejectsVersionBelowOne(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $registry = new TelemetrySchemaRegistry();
        $registry->register('occurrence', 0, []);
    }

    public function testImplementsInterface(): void
    {
        $registry = new TelemetrySchemaRegistry();

        self::assertInstanceOf(TelemetrySchemaRegistryInterface::class, $registry);
    }
}
