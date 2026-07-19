<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Adapter\JsonLoader;
use ItPack\ConfigureIt\Adapter\PhpLoader;
use ItPack\ConfigureIt\Exception\ParseException;
use ItPack\ConfigureIt\ValueObject\Format;

class JsonLoaderTest extends Unit
{
    public function testSupportsJsonOnly(): void
    {
        $loader = new JsonLoader();

        verify($loader->supports(Format::JSON))->true();
        verify($loader->supports(Format::PHP))->false();
    }

    public function testLoadsValidJson(): void
    {
        $document = new JsonLoader()->load('{"a":{"b":1}}');

        verify($document->toArray())->equals(['a' => ['b' => 1]]);
        verify($document->format())->equals(Format::JSON);
    }

    public function testThrowsOnInvalidJson(): void
    {
        $this->expectException(ParseException::class);

        new JsonLoader()->load('{not json');
    }
}

class PhpLoaderTest extends Unit
{
    public function testSupportsPhpOnly(): void
    {
        $loader = new PhpLoader();

        verify($loader->supports(Format::PHP))->true();
        verify($loader->supports(Format::JSON))->false();
    }

    public function testLoadsReturnedArray(): void
    {
        $document = new PhpLoader()->load('<?php return ["x" => 42];');

        verify($document->toArray())->equals(['x' => 42]);
        verify($document->format())->equals(Format::PHP);
    }

    public function testThrowsWhenNotAnArray(): void
    {
        $this->expectException(ParseException::class);

        new PhpLoader()->load('<?php return 5;');
    }
}
