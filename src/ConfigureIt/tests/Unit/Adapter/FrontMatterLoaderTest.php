<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Adapter\FrontMatterLoader;
use ItPack\ConfigureIt\Exception\ParseException;
use ItPack\ConfigureIt\ValueObject\Format;

class FrontMatterLoaderTest extends Unit
{
    public function testSupportsFrontMatterOnly(): void
    {
        $loader = new FrontMatterLoader();

        verify($loader->supports(Format::FRONTMATTER))->true();
        verify($loader->supports(Format::JSON))->false();
    }

    public function testLoadsMetadataAndContent(): void
    {
        $document = new FrontMatterLoader()->load(
            "---\ntitle: Hello\nactive: true\n---\nBody content\n",
        );

        verify($document->toArray())->equals([
            'title' => 'Hello',
            'active' => true,
            'content' => "Body content\n",
        ]);
        verify($document->format())->equals(Format::FRONTMATTER);
    }

    public function testLoadsMetadataWithoutContent(): void
    {
        $document = new FrontMatterLoader()->load("---\nonly: meta\n---\n");

        verify($document->toArray())->equals(['only' => 'meta']);
    }

    public function testThrowsOnInvalidFrontMatter(): void
    {
        $this->expectException(ParseException::class);

        new FrontMatterLoader()->load("---\ntitle: [unclosed\n---\nbody\n");
    }
}
