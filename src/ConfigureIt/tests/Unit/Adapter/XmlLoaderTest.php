<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Adapter\XmlLoader;
use ItPack\ConfigureIt\Exception\ParseException;
use ItPack\ConfigureIt\ValueObject\Format;

class XmlLoaderTest extends Unit
{
    public function testSupportsXmlOnly(): void
    {
        $loader = new XmlLoader();

        verify($loader->supports(Format::XML))->true();
        verify($loader->supports(Format::JSON))->false();
    }

    public function testLoadsNestedXml(): void
    {
        $document = new XmlLoader()->load(
            '<config><db><host>localhost</host><port>5432</port></db><debug>true</debug></config>',
        );

        verify($document->toArray())->equals([
            'db' => ['host' => 'localhost', 'port' => '5432'],
            'debug' => 'true',
        ]);
        verify($document->format())->equals(Format::XML);
    }

    public function testLoadsAttributesAndRepeatedSiblings(): void
    {
        $document = new XmlLoader()->load(
            '<root><item id="1">a</item><item id="2">b</item></root>',
        );

        verify($document->toArray())->equals([
            'item' => [
                ['@value' => 'a', '@attributes' => ['id' => '1']],
                ['@value' => 'b', '@attributes' => ['id' => '2']],
            ],
        ]);
    }

    public function testThrowsOnInvalidXml(): void
    {
        $this->expectException(ParseException::class);

        new XmlLoader()->load('<a><b></a>');
    }

    public function testLoadsRootAttributesAlongsideChildren(): void
    {
        $document = new XmlLoader()->load('<root id="x"><child>v</child></root>');

        verify($document->toArray())->equals([
            'child' => 'v',
            '@attributes' => ['id' => 'x'],
        ]);
        verify($document->format())->equals(Format::XML);
    }
}
