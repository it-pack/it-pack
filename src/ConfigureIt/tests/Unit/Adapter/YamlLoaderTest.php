<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Adapter\YamlLoader;
use ItPack\ConfigureIt\Exception\ParseException;
use ItPack\ConfigureIt\ValueObject\Format;

class YamlLoaderTest extends Unit
{
    public function testSupportsYamlOnly(): void
    {
        $loader = new YamlLoader();

        verify($loader->supports(Format::YAML))->true();
        verify($loader->supports(Format::JSON))->false();
    }

    public function testLoadsValidYaml(): void
    {
        $document = new YamlLoader()->load("a:\n  b: 1\nlist:\n  - x\n  - y\n");

        verify($document->toArray())->equals(['a' => ['b' => 1], 'list' => ['x', 'y']]);
        verify($document->format())->equals(Format::YAML);
    }

    public function testThrowsOnInvalidYaml(): void
    {
        $this->expectException(ParseException::class);

        new YamlLoader()->load("a: [unclosed\n");
    }
}
