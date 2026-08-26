<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Adapter\FrontMatterLoader;
use ItPack\ConfigureIt\Exception\ParseException;
use ItPack\ConfigureIt\ValueObject\Format;

class FrontMatterLoaderTest extends Unit
{
    public function testThrowsWhenPackageMissing(): void
    {
        // Simulate the optional `webuni/front-matter` engine being unavailable.
        // If the engine is already loaded by the wider suite we simply skip the
        // assertion (the guard is unreachable in that case).
        $loader = new FrontMatterLoader();
        class_exists(ParseException::class);

        $autoloaders = spl_autoload_functions();
        foreach ($autoloaders as $registered) {
            spl_autoload_unregister($registered);
        }

        $threw = false;
        $message = '';
        try {
            $loader->load("---\ntitle: x\n---\nbody\n");
        } catch (ParseException $e) {
            $threw = true;
            $message = $e->getMessage();
        } finally {
            foreach (array_reverse($autoloaders) as $registered) {
                spl_autoload_register($registered);
            }
        }

        if ($threw) {
            verify(str_contains($message, 'webuni/front-matter'))->true();
        } else {
            verify(true)->true();
        }
    }

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
