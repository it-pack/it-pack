<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Common;

use Codeception\Test\Unit;
use ItPack\CommandIt\Adapter\Common\SafeSerializer;
use ItPack\CommandIt\Contract\Exception\SerializationExceptionInterface;
use stdClass;

/**
 * @covers \ItPack\CommandIt\Adapter\Common\SafeSerializer
 */
final class SafeSerializerTest extends Unit
{
    private SafeSerializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = new SafeSerializer();
    }

    public function testScalarsPassThrough(): void
    {
        self::assertSame(5, $this->serializer->normalize(5));
        self::assertSame('x', $this->serializer->normalize('x'));
        self::assertNull($this->serializer->normalize(null));
    }

    public function testNestedArraysNormalizeRecursively(): void
    {
        self::assertSame(['a' => ['b' => 1]], $this->serializer->normalize(['a' => ['b' => 1]]));
    }

    public function testStringableObjectBecomesString(): void
    {
        $value = new class () implements \Stringable {
            public function __toString(): string
            {
                return 's';
            }
        };

        self::assertSame('s', $this->serializer->normalize($value));
    }

    public function testObjectWithToArrayReducesToPrimitives(): void
    {
        $value = new class () {
            /**
             * @return array<string, mixed>
             */
            public function toArray(): array
            {
                return ['k' => 'v'];
            }
        };

        self::assertSame(['k' => 'v'], $this->serializer->normalize($value));
    }

    public function testArbitraryObjectIsRejected(): void
    {
        $this->expectException(SerializationExceptionInterface::class);

        $this->serializer->normalize(new stdClass());
    }

    public function testResourceIsRejected(): void
    {
        $this->expectException(SerializationExceptionInterface::class);

        $this->serializer->normalize(\fopen('php://memory', 'rb'));
    }

    public function testClosureIsRejected(): void
    {
        $this->expectException(SerializationExceptionInterface::class);

        $this->serializer->normalize(static fn () => null);
    }

    public function testArrayContainingObjectIsRejected(): void
    {
        $this->expectException(SerializationExceptionInterface::class);

        $this->serializer->normalize(['item' => new stdClass()]);
    }
}
