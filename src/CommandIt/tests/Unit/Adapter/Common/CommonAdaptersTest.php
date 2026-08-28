<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Common;

use Codeception\Test\Unit;
use ItPack\CommandIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\CommandIt\Adapter\Common\SafeSerializer;
use ItPack\CommandIt\Adapter\Common\SystemClock;
use ItPack\CommandIt\Contract\Exception\SerializationException;

/**
 * @covers \ItPack\CommandIt\Adapter\Common\SystemClock
 * @covers \ItPack\CommandIt\Adapter\Common\RandomIdentifierGenerator
 * @covers \ItPack\CommandIt\Adapter\Common\SafeSerializer
 */
final class CommonAdaptersTest extends Unit
{
    public function testSystemClockReturnsIntegers(): void
    {
        $clock = new SystemClock();

        self::assertGreaterThan(0, $clock->monotonicNanoseconds());
        self::assertLessThanOrEqual(\time() + 2, $clock->nowSeconds());
    }

    public function testIdentifierGeneratorProducesUniqueUuids(): void
    {
        $generator = new RandomIdentifierGenerator();
        $seen = [];

        // Many draws so the version/variant nibble assertions are deterministic
        // rather than lucky on a single random sample.
        for ($i = 0; $i < 64; ++$i) {
            $id = $generator->generate();

            self::assertMatchesRegularExpression(
                '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
                $id,
            );
            $seen[$id] = true;
        }

        self::assertCount(64, $seen);
    }

    public function testSafeSerializerNormalizesPrimitives(): void
    {
        $serializer = new SafeSerializer();

        self::assertSame(['a' => 1, 'b' => 'x'], $serializer->normalize(['a' => 1, 'b' => 'x']));
        self::assertSame('application/json', $serializer->contentType());
    }

    public function testSafeSerializerRejectsClosures(): void
    {
        $serializer = new SafeSerializer();

        $this->expectException(SerializationException::class);
        $serializer->normalize(static fn () => 1);
    }

    public function testSafeSerializerRejectsArbitraryObjects(): void
    {
        $serializer = new SafeSerializer();

        $this->expectException(SerializationException::class);
        $serializer->normalize(new \stdClass());
    }

    public function testSafeSerializerReducersStringable(): void
    {
        $serializer = new SafeSerializer();

        self::assertSame('str', $serializer->normalize(new class () implements \Stringable {
            public function __toString(): string
            {
                return 'str';
            }
        }));
    }

    public function testSafeSerializerUsesToArray(): void
    {
        $serializer = new SafeSerializer();

        $object = new class () {
            /**
             * @return array<string, mixed>
             */
            public function toArray(): array
            {
                return ['k' => 'v'];
            }
        };

        self::assertSame(['k' => 'v'], $serializer->normalize($object));
    }
}
