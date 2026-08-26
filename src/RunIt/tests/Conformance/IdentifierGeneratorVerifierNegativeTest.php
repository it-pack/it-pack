<?php

declare(strict_types=1);

namespace Tests\Conformance;

use Codeception\Test\Unit;
use ItPack\RunIt\Conformance\Fixture\RunItPortFixture;
use ItPack\RunIt\Conformance\Verifier\IdentifierGeneratorVerifier;
use ItPack\RunIt\Contract\Port\IdentifierGeneratorInterface;
use RuntimeException;
use Tests\Support\Fixture\Conformance\ConformanceViolations;

/**
 * Feeds the {@see IdentifierGeneratorVerifier} broken generators and asserts
 * each contract breach is reported with the expected violation identity.
 *
 * @see https://w3id.org/itpack/runit#executionId
 */
final class IdentifierGeneratorVerifierNegativeTest extends Unit
{
    private RunItPortFixture $fixture;

    protected function setUp(): void
    {
        $this->fixture = new RunItPortFixture();
    }

    public function testThrowingGenerateIsReportedAndStopsTheCheck(): void
    {
        $generator = new class () implements IdentifierGeneratorInterface {
            public function generate(): string
            {
                throw new RuntimeException('entropy exhausted');
            }
        };

        $result = new IdentifierGeneratorVerifier()->verify($generator, $this->fixture);

        self::assertFalse($result->isConformant());
        self::assertSame(['generate.throws'], ConformanceViolations::ids($result));
        self::assertSame(1, count($result));
    }

    public function testEmptyValuesAreReportedForEverySample(): void
    {
        $generator = new class () implements IdentifierGeneratorInterface {
            public function generate(): string
            {
                return '';
            }
        };

        $result = new IdentifierGeneratorVerifier()->verify($generator, $this->fixture);

        self::assertFalse($result->isConformant());
        self::assertSame(array_fill(0, 200, 'generate.empty'), ConformanceViolations::ids($result));
    }

    public function testDuplicateValuesAreReported(): void
    {
        $generator = new class () implements IdentifierGeneratorInterface {
            public function generate(): string
            {
                return 'fixed-identifier';
            }
        };

        $result = new IdentifierGeneratorVerifier()->verify($generator, $this->fixture);

        self::assertFalse($result->isConformant());
        self::assertSame(array_fill(0, 199, 'generate.not-unique'), ConformanceViolations::ids($result));
    }

    public function testInvalidFormatIsReported(): void
    {
        $generator = new class () implements IdentifierGeneratorInterface {
            private int $counter = 0;

            public function generate(): string
            {
                return 'invalid ' . ++$this->counter;
            }
        };

        $result = new IdentifierGeneratorVerifier()->verify($generator, $this->fixture);

        self::assertFalse($result->isConformant());
        self::assertSame(array_fill(0, 200, 'generate.invalid-format'), ConformanceViolations::ids($result));
    }
}
