<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Common;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\Common\JsonSnapshotSerializer;
use ItPack\RunIt\Contract\Exception\SerializationException;
use ItPack\RunIt\Domain\Shared\SecurityLimits;
use JsonSerializable;
use stdClass;

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

        $value = new class () implements JsonSerializable {
            /** @return array<string, bool> */
            public function jsonSerialize(): array
            {
                return ['derived' => true];
            }
        };

        self::assertSame(['derived' => true], $serializer->normalize($value));
    }

    public function testSelfReferentialJsonSerializableIsRejected(): void
    {
        $serializer = new JsonSnapshotSerializer();

        $value = new class () implements JsonSerializable {
            /** @return array<string, object> */
            public function jsonSerialize(): array
            {
                return ['self' => $this];
            }
        };

        $this->expectException(SerializationException::class);
        $this->expectExceptionMessageMatches('/cyclic/i');

        $serializer->normalize($value);
    }

    public function testObjectPayloadExceedingDepthIsRejected(): void
    {
        $serializer = new JsonSnapshotSerializer(new SecurityLimits(maxSerializationDepth: 2));

        $value = new class () implements JsonSerializable {
            /** @return array<string, mixed> */
            public function jsonSerialize(): array
            {
                return ['a' => ['b' => ['c' => 1]]];
            }
        };

        $this->expectException(SerializationException::class);
        $this->expectExceptionMessageMatches('/depth/i');

        $serializer->normalize($value);
    }

    public function testSharedObjectReferenceIsAllowed(): void
    {
        $serializer = new JsonSnapshotSerializer();

        $shared = new class () implements JsonSerializable {
            /** @return array<string, int> */
            public function jsonSerialize(): array
            {
                return ['id' => 1];
            }
        };

        self::assertSame(
            ['first' => ['id' => 1], 'second' => ['id' => 1]],
            $serializer->normalize(['first' => $shared, 'second' => $shared]),
        );
    }

    public function testPlainObjectPayloadIsRejected(): void
    {
        $serializer = new JsonSnapshotSerializer();

        try {
            $serializer->normalize(['entity' => new stdClass()]);
            self::fail('arbitrary objects must be rejected, never serialized');
        } catch (SerializationException $exception) {
            self::assertStringContainsString('unsupported object of type stdClass', $exception->getMessage());
        }
    }

    public function testJsonSerializableBeyondTheDepthLimitIsRejected(): void
    {
        $serializer = new JsonSnapshotSerializer(new SecurityLimits(maxSerializationDepth: 3));

        $leaf = new class () implements JsonSerializable {
            /**
             * @return array<string, mixed>
             */
            public function jsonSerialize(): array
            {
                return ['leaf' => true];
            }
        };

        try {
            $serializer->normalize([[[$leaf]]]);
            self::fail('a JsonSerializable below the depth floor must be rejected');
        } catch (SerializationException $exception) {
            self::assertStringContainsString('maximum depth 3 exceeded', $exception->getMessage());
        }
    }
}
