<?php

declare(strict_types=1);

namespace Tests\Conformance;

use Codeception\Test\Unit;
use ItPack\RunIt\Conformance\Fixture\RunItPortFixture;
use ItPack\RunIt\Conformance\Verifier\SerializerVerifier;
use ItPack\RunIt\Contract\Port\SerializerInterface;
use RuntimeException;
use stdClass;
use Tests\Support\Fixture\Conformance\ConformanceViolations;

/**
 * Feeds the {@see SerializerVerifier} broken serializers and asserts each
 * contract breach is reported with the expected violation identity.
 *
 * @see https://w3id.org/itpack/runit#Snapshot
 */
final class SerializerVerifierNegativeTest extends Unit
{
    private RunItPortFixture $fixture;

    protected function setUp(): void
    {
        $this->fixture = new RunItPortFixture();
    }

    public function testThrowingContentTypeIsReportedAndStopsTheCheck(): void
    {
        $serializer = new class () implements SerializerInterface {
            public function normalize(mixed $data): mixed
            {
                throw new RuntimeException('must never be reached');
            }

            public function contentType(): string
            {
                throw new RuntimeException('content type unavailable');
            }
        };

        $result = new SerializerVerifier()->verify($serializer, $this->fixture);

        self::assertFalse($result->isConformant());
        self::assertSame(['contentType.throws'], ConformanceViolations::ids($result));
        self::assertSame(1, count($result));
    }

    public function testEmptyContentTypeIsReported(): void
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

        $result = new SerializerVerifier()->verify($serializer, $this->fixture);

        self::assertFalse($result->isConformant());
        self::assertSame(['contentType.empty'], ConformanceViolations::ids($result));
    }

    public function testThrowingNormalizeIsReportedPerSample(): void
    {
        $serializer = new class () implements SerializerInterface {
            public function normalize(mixed $data): mixed
            {
                throw new RuntimeException('normalization unavailable');
            }

            public function contentType(): string
            {
                return 'application/json';
            }
        };

        $result = new SerializerVerifier()->verify($serializer, $this->fixture);

        self::assertFalse($result->isConformant());
        self::assertSame(
            [
                'normalize.scalar.throws',
                'normalize.nested.throws',
                'normalize.payload.throws',
            ],
            ConformanceViolations::ids($result),
        );
    }

    public function testNonPrimitiveOutputIsReportedPerSample(): void
    {
        $serializer = new class () implements SerializerInterface {
            public function normalize(mixed $data): mixed
            {
                return ['wrapped' => new stdClass()];
            }

            public function contentType(): string
            {
                return 'application/json';
            }
        };

        $result = new SerializerVerifier()->verify($serializer, $this->fixture);

        self::assertFalse($result->isConformant());
        self::assertSame(
            [
                'normalize.scalar.not-primitive',
                'normalize.nested.not-primitive',
                'normalize.payload.not-primitive',
            ],
            ConformanceViolations::ids($result),
        );
    }

    public function testNonDeterministicOutputIsReportedPerSample(): void
    {
        $serializer = new class () implements SerializerInterface {
            private bool $flip = false;

            public function normalize(mixed $data): mixed
            {
                $this->flip = !$this->flip;

                return $this->flip ? ['a' => 1, 'b' => 2] : ['b' => 2, 'a' => 1];
            }

            public function contentType(): string
            {
                return 'application/json';
            }
        };

        $result = new SerializerVerifier()->verify($serializer, $this->fixture);

        self::assertFalse($result->isConformant());
        self::assertSame(
            [
                'normalize.scalar.not-deterministic',
                'normalize.nested.not-deterministic',
                'normalize.payload.not-deterministic',
            ],
            ConformanceViolations::ids($result),
        );
    }
}
