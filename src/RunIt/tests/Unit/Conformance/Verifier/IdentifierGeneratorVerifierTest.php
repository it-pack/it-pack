<?php

declare(strict_types=1);

namespace Tests\Unit\Conformance\Verifier;

use Codeception\Test\Unit;
use ItPack\RunIt\Conformance\Fixture\RunItPortFixture;
use ItPack\RunIt\Conformance\Verifier\IdentifierGeneratorVerifier;
use ItPack\RunIt\Contract\Port\IdentifierGeneratorInterface;
use RuntimeException;
use Tests\Support\Fixture\SequenceIdentifierGenerator;

/**
 * @group conformance
 */
final class IdentifierGeneratorVerifierTest extends Unit
{
    public function testPortReportsIdentifierGeneratorInterface(): void
    {
        self::assertSame(IdentifierGeneratorInterface::class, new IdentifierGeneratorVerifier()->port());
    }

    public function testConformantGeneratorPasses(): void
    {
        $result = new IdentifierGeneratorVerifier()->verify(new SequenceIdentifierGenerator(), new RunItPortFixture());

        self::assertTrue($result->isConformant());
        self::assertCount(0, $result);
    }

    public function testGenerateThrowIsRecorded(): void
    {
        $generator = new class () implements IdentifierGeneratorInterface {
            public function generate(): string
            {
                throw new RuntimeException('generate broken');
            }
        };

        $result = new IdentifierGeneratorVerifier()->verify($generator, new RunItPortFixture());

        self::assertFalse($result->isConformant());
        self::assertCount(1, $result->errors());
        self::assertSame('generate.throws', $result->errors()[0]->id);
    }

    public function testEmptyValueIsRecorded(): void
    {
        $generator = new class () implements IdentifierGeneratorInterface {
            public function generate(): string
            {
                return '';
            }
        };

        $result = new IdentifierGeneratorVerifier()->verify($generator, new RunItPortFixture());

        self::assertFalse($result->isConformant());
        self::assertSame('generate.empty', $result->errors()[0]->id);
    }

    public function testInvalidFormatIsRecorded(): void
    {
        $generator = new class () implements IdentifierGeneratorInterface {
            public function generate(): string
            {
                return 'bad value';
            }
        };

        $result = new IdentifierGeneratorVerifier()->verify($generator, new RunItPortFixture());

        self::assertFalse($result->isConformant());
        self::assertSame('generate.invalid-format', $result->errors()[0]->id);
    }

    public function testDuplicateValueIsRecorded(): void
    {
        $generator = new class () implements IdentifierGeneratorInterface {
            public function generate(): string
            {
                return 'always-the-same';
            }
        };

        $result = new IdentifierGeneratorVerifier()->verify($generator, new RunItPortFixture());

        self::assertFalse($result->isConformant());
        self::assertCount(199, $result->errors());
        self::assertSame('generate.not-unique', $result->errors()[0]->id);
    }
}
