<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Common;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(RandomIdentifierGenerator::class)]
final class RandomIdentifierGeneratorTest extends Unit
{
    private const string FORMAT ='%s-%s-%s-%s-%s';
    private const string REGEX ='/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

    public function testGenerateReturnsUuidV4String(): void
    {
        $id = new RandomIdentifierGenerator()->generate();

        self::assertStringMatchesFormat(self::FORMAT, $id);
        self::assertSame(36, strlen($id));
        self::assertSame('4', $id[14]);
        self::assertMatchesRegularExpression(self::REGEX, $id);
    }

    public function testGenerateReturnsUniqueValues(): void
    {
        $generator = new RandomIdentifierGenerator();

        $id1 = $generator->generate();
        $id2 = $generator->generate();

        self::assertNotSame($id1, $id2);
    }
}
