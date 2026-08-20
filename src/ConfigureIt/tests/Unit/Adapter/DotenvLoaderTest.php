<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Adapter\DotenvLoader;
use ItPack\ConfigureIt\Exception\ParseException;
use ItPack\ConfigureIt\ValueObject\Format;

class DotenvLoaderTest extends Unit
{
    public function testSupportsDotenvOnly(): void
    {
        $loader = new DotenvLoader();

        verify($loader->supports(Format::DOTENV))->true();
        verify($loader->supports(Format::ENV))->false();
        verify($loader->supports(Format::JSON))->false();
    }

    public function testLoadsFlatKeyValuePairs(): void
    {
        $document = new DotenvLoader()->load("APP_NAME=configure-it\nAPP_DEBUG=true\n");

        verify($document->toArray())->equals(['APP_NAME' => 'configure-it', 'APP_DEBUG' => 'true']);
        verify($document->format())->equals(Format::DOTENV);
    }

    public function testStripsQuotesAndResolvesNestedVariables(): void
    {
        $document = new DotenvLoader()->load("BASE=base\nVALUE=\"quoted\"\nEXPANDED=\${BASE}-x\n");

        verify($document->toArray())->equals([
            'BASE' => 'base',
            'VALUE' => 'quoted',
            'EXPANDED' => 'base-x',
        ]);
    }

    public function testThrowsOnInvalidFile(): void
    {
        $this->expectException(ParseException::class);

        new DotenvLoader()->load("=value\n");
    }
}
