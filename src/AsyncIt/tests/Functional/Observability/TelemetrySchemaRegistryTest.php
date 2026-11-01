<?php

declare(strict_types=1);

namespace Tests\Functional\Observability;

use ItPack\AsyncIt\Domain\TelemetrySchemaRegistry;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ItPack\AsyncIt\Domain\TelemetrySchemaRegistry
 * @covers \ItPack\AsyncIt\Application\Scheduler\FiberTaskScheduler
 */
final class TelemetrySchemaRegistryTest extends TestCase
{
    public function testTelemetrySchemaRegistryStoresAndRetrievesSchemas(): void
    {
        $registry = new TelemetrySchemaRegistry();

        $schema = [
            'type' => 'object',
            'properties' => [
                'taskId' => ['type' => 'string'],
                'status' => ['type' => 'string'],
            ],
        ];

        $registry->register('task-completed', 1, $schema);

        $retrieved = $registry->get('task-completed', 1);
        self::assertSame($schema, $retrieved);
    }

    public function testTelemetrySchemaRegistryReturnsNullForUnknown(): void
    {
        $registry = new TelemetrySchemaRegistry();

        self::assertNull($registry->get('unknown-schema', 1));
    }

    public function testTelemetrySchemaRegistryLatest(): void
    {
        $registry = new TelemetrySchemaRegistry();

        $registry->register('task-completed', 1, ['v' => 1]);
        $registry->register('task-completed', 2, ['v' => 2]);

        self::assertSame(['v' => 2], $registry->latest('task-completed'));
    }

    public function testTelemetrySchemaRegistryHas(): void
    {
        $registry = new TelemetrySchemaRegistry();

        $registry->register('task-completed', 1, ['v' => 1]);

        self::assertTrue($registry->has('task-completed', 1));
        self::assertFalse($registry->has('task-completed', 2));
    }
}
