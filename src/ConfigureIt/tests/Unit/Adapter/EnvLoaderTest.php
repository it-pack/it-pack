<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Adapter\EnvLoader;
use ItPack\ConfigureIt\Exception\ParseException;
use ItPack\ConfigureIt\ValueObject\Format;

/**
 * @covers \ItPack\ConfigureIt\Adapter\EnvLoader
 */
class EnvLoaderTest extends Unit
{
    public function testSupportsEnvOnly(): void
    {
        $loader = new EnvLoader();

        verify($loader->supports(Format::ENV))->true();
        verify($loader->supports(Format::JSON))->false();
    }

    public function testParsesKeyValues(): void
    {
        $document = new EnvLoader()->load("FOO=bar\nBAZ=qux\n");

        verify($document->toArray())->equals(['FOO' => 'bar', 'BAZ' => 'qux']);
        verify($document->format())->equals(Format::ENV);
    }

    public function testSkipsCommentsAndBlankLines(): void
    {
        $document = new EnvLoader()->load("# comment\n\nFOO=bar\n   \n# another\n");

        verify($document->toArray())->equals(['FOO' => 'bar']);
    }

    public function testThrowsOnMissingSeparator(): void
    {
        $this->expectException(ParseException::class);

        new EnvLoader()->load('FOObar');
    }

    public function testThrowsOnEmptyKey(): void
    {
        $this->expectException(ParseException::class);

        new EnvLoader()->load('=value');
    }
}
