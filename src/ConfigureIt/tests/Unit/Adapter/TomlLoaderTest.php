<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Adapter\TomlLoader;
use ItPack\ConfigureIt\Exception\ParseException;
use ItPack\ConfigureIt\ValueObject\Format;

class TomlLoaderTest extends Unit
{
    public function testSupportsTomlOnly(): void
    {
        $loader = new TomlLoader();

        verify($loader->supports(Format::TOML))->true();
        verify($loader->supports(Format::JSON))->false();
    }

    public function testLoadsValidToml(): void
    {
        $document = new TomlLoader()->load(
            "title = \"hi\"\n[server]\nhost = \"localhost\"\nport = 8080\n",
        );

        verify($document->toArray())->equals([
            'title' => 'hi',
            'server' => ['host' => 'localhost', 'port' => 8080],
        ]);
        verify($document->format())->equals(Format::TOML);
    }

    public function testThrowsOnInvalidToml(): void
    {
        $this->expectException(ParseException::class);

        new TomlLoader()->load('a = ');
    }
}
