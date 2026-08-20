<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Adapter\IniLoader;
use ItPack\ConfigureIt\Exception\ParseException;
use ItPack\ConfigureIt\ValueObject\Format;

class IniLoaderTest extends Unit
{
    public function testSupportsIniOnly(): void
    {
        $loader = new IniLoader();

        verify($loader->supports(Format::INI))->true();
        verify($loader->supports(Format::JSON))->false();
    }

    public function testLoadsValidIni(): void
    {
        $document = new IniLoader()->load("[section]\nkey = value\nnum = 5\n");

        verify($document->toArray())->equals([
            'section' => ['key' => 'value', 'num' => 5],
        ]);
        verify($document->format())->equals(Format::INI);
    }

    public function testThrowsOnInvalidIni(): void
    {
        $this->expectException(ParseException::class);

        new IniLoader()->load('key = "unterminated');
    }
}
