<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Common;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\Common\JsonSnapshotSerializer;
use ItPack\RunIt\Contract\Exception\SerializationException;
use ItPack\RunIt\Domain\Shared\SecurityLimits;

/**
 * @group adapter
 * @group security
 */
final class JsonSnapshotSerializerTest extends Unit
{
    public function testPrimitivePayloadNormalizes(): void
    {
        $serializer = new JsonSnapshotSerializer();

        self::assertSame(['a' => 1, 'b' => 'x'], $serializer->normalize(['a' => 1, 'b' => 'x']));
    }

    public function testResourceIsRejected(): void
    {
        $serializer = new JsonSnapshotSerializer();

        $this->expectException(SerializationException::class);
        $this->expectExceptionMessageMatches('/resource/i');

        $serializer->normalize(['stream' => fopen('php://memory', 'rb')]);
    }

    public function testClosureIsRejected(): void
    {
        $serializer = new JsonSnapshotSerializer();

        $this->expectException(SerializationException::class);
        $this->expectExceptionMessageMatches('/closure/i');

        $serializer->normalize(['fn' => static fn () => 1]);
    }

    public function testNonFiniteFloatIsRejected(): void
    {
        $serializer = new JsonSnapshotSerializer();

        $this->expectException(SerializationException::class);
        $this->expectExceptionMessageMatches('/non-finite/i');

        $serializer->normalize(['value' => INF]);
    }

    public function testInvalidUtf8IsRejected(): void
    {
        $serializer = new JsonSnapshotSerializer();

        $this->expectException(SerializationException::class);

        $serializer->normalize(['malformed' => "\xC3\x28"]);
    }

    public function testDepthLimitIsEnforced(): void
    {
        $serializer = new JsonSnapshotSerializer(new SecurityLimits(maxSerializationDepth: 2));

        $this->expectException(SerializationException::class);
        $this->expectExceptionMessageMatches('/depth/i');

        $serializer->normalize(['a' => ['b' => ['c' => ['d' => 1]]]]);
    }

    public function testPayloadSizeLimitIsEnforced(): void
    {
        $serializer = new JsonSnapshotSerializer(new SecurityLimits(maxSerializationBytes: 20));

        $this->expectException(SerializationException::class);
        $this->expectExceptionMessageMatches('/maximum size/i');

        $serializer->normalize(['data' => str_repeat('x', 1000)]);
    }

    public function testJsonSerializableObjectIsAllowed(): void
    {
        $serializer = new JsonSnapshotSerializer();

        $value = new class () implements \JsonSerializable {
            /** @return array<string, bool> */
            public function jsonSerialize(): array
            {
                return ['derived' => true];
            }
        };

        self::assertSame(['derived' => true], $serializer->normalize($value));
    }
}
