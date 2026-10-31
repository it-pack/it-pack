<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Artifact;

use InvalidArgumentException;
use ItPack\AsyncIt\Application\Artifact\ArtifactNormalizer;
use ItPack\AsyncIt\Contract\TelemetrySchemaRegistryInterface;
use ItPack\AsyncIt\Domain\TelemetrySchemaRegistry;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ItPack\AsyncIt\Application\Artifact\ArtifactNormalizer
 */
final class ArtifactNormalizerTest extends TestCase
{
    public function testNormalizeReturnsVersionTaggedArray(): void
    {
        $registry = new TelemetrySchemaRegistry();
        $registry->register('occurrence', 1, ['type' => 'object']);

        $normalizer = new ArtifactNormalizer($registry);

        $result = $normalizer->normalize('occurrence', 1, ['kind' => 'task.spawned.v1', 'taskId' => 't1']);

        self::assertSame('occurrence', $result['schemaType']);
        self::assertSame(1, $result['schemaVersion']);
        self::assertSame(['kind' => 'task.spawned.v1', 'taskId' => 't1'], $result['payload']);
    }

    public function testNormalizeThrowsForUnregisteredSchemaInFailClosedMode(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $registry = new TelemetrySchemaRegistry();
        $normalizer = new ArtifactNormalizer($registry);

        $normalizer->normalize('occurrence', 1, ['kind' => 'task.spawned.v1']);
    }

    public function testNormalizePassesThroughUnregisteredSchemaInPassThroughMode(): void
    {
        $registry = new TelemetrySchemaRegistry();
        $normalizer = ArtifactNormalizer::passThrough($registry);

        $result = $normalizer->normalize('occurrence', 1, ['kind' => 'task.spawned.v1', 'taskId' => 't1']);

        self::assertSame('occurrence', $result['schemaType']);
        self::assertSame(1, $result['schemaVersion']);
        self::assertSame(['kind' => 'task.spawned.v1', 'taskId' => 't1'], $result['payload']);
    }

    public function testPassThroughStillValidatesRegisteredSchema(): void
    {
        $registry = new TelemetrySchemaRegistry();
        $registry->register('measurement', 2, ['type' => 'object']);

        $normalizer = ArtifactNormalizer::passThrough($registry);

        $result = $normalizer->normalize('measurement', 2, ['durationNs' => 1000]);

        self::assertSame('measurement', $result['schemaType']);
        self::assertSame(2, $result['schemaVersion']);
        self::assertSame(['durationNs' => 1000], $result['payload']);
    }

    public function testAcceptsInterface(): void
    {
        $registry = new TelemetrySchemaRegistry();

        self::assertInstanceOf(TelemetrySchemaRegistryInterface::class, $registry);
    }
}
