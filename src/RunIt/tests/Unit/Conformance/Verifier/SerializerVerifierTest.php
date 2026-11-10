<?php

declare(strict_types=1);

namespace Tests\Unit\Conformance\Verifier;

use Codeception\Test\Unit;
use ItPack\RunIt\Conformance\Fixture\RunItPortFixture;
use ItPack\RunIt\Conformance\Verifier\SerializerVerifier;
use ItPack\RunIt\Contract\Port\SerializerInterface;
use RuntimeException;
use stdClass;

/**
 * @group conformance
 */
final class SerializerVerifierTest extends Unit
{
    public function testPortReportsSerializerInterface(): void
    {
        self::assertSame(SerializerInterface::class, new SerializerVerifier()->port());
    }

    public function testConformantSerializerPasses(): void
    {
        $serializer = new class () implements SerializerInterface {
            public function normalize(mixed $data): mixed
            {
                return $data;
            }

            public function contentType(): string
            {
                return 'application/json';
            }
        };

        $result = new SerializerVerifier()->verify($serializer, new RunItPortFixture());

        self::assertTrue($result->isConformant());
        self::assertCount(0, $result);
    }

    public function testContentTypeThrowIsRecorded(): void
    {
        $serializer = new class () implements SerializerInterface {
            public function normalize(mixed $data): mixed
            {
                return $data;
            }

            public function contentType(): string
            {
                throw new RuntimeException('contentType broken');
            }
        };

        $result = new SerializerVerifier()->verify($serializer, new RunItPortFixture());

        self::assertFalse($result->isConformant());
        self::assertCount(1, $result->errors());
        self::assertSame('contentType.throws', $result->errors()[0]->id);
    }

    public function testEmptyContentTypeIsRecorded(): void
    {
        $serializer = new class () implements SerializerInterface {
            public function normalize(mixed $data): mixed
            {
                return $data;
            }

            public function contentType(): string
            {
                return '';
            }
        };

        $result = new SerializerVerifier()->verify($serializer, new RunItPortFixture());

        self::assertFalse($result->isConformant());
        self::assertSame('contentType.empty', $result->errors()[0]->id);
    }

    public function testNormalizeThrowIsRecorded(): void
    {
        $serializer = new class () implements SerializerInterface {
            public function normalize(mixed $data): mixed
            {
                throw new RuntimeException('normalize broken');
            }

            public function contentType(): string
            {
                return 'application/json';
            }
        };

        $result = new SerializerVerifier()->verify($serializer, new RunItPortFixture());

        self::assertFalse($result->isConformant());
        self::assertCount(3, $result->errors());
        self::assertSame('normalize.scalar.throws', $result->errors()[0]->id);
        self::assertSame('normalize.nested.throws', $result->errors()[1]->id);
        self::assertSame('normalize.payload.throws', $result->errors()[2]->id);
    }

    public function testNonPrimitiveNormalizeIsRecorded(): void
    {
        $serializer = new class () implements SerializerInterface {
            public function normalize(mixed $data): mixed
            {
                return new stdClass();
            }

            public function contentType(): string
            {
                return 'application/json';
            }
        };

        $result = new SerializerVerifier()->verify($serializer, new RunItPortFixture());

        self::assertFalse($result->isConformant());
        self::assertSame('normalize.scalar.not-primitive', $result->errors()[0]->id);
    }
}
