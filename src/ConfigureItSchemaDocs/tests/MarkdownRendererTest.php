<?php

declare(strict_types=1);

namespace ItPack\ConfigureItSchemaDocs\Tests;

use ItPack\ConfigureItSchemaDocs\Renderer\MarkdownRenderer;
use Nette\Schema\Schema;
use PHPUnit\Framework\TestCase;

final class MarkdownRendererTest extends TestCase
{
    private function definition(): Schema
    {
        /** @var Schema $def */
        $def = require __DIR__.'/Fixtures/sample-schema.php';

        return $def;
    }

    private function markdown(): string
    {
        $root = (new \ItPack\ConfigureItSchemaDocs\SchemaIntrospector())->introspect($this->definition());

        return (new MarkdownRenderer())->render($root, 'Sample');
    }

    public function testTitleAndStructureHeading(): void
    {
        $md = $this->markdown();

        self::assertStringContainsString('# Sample', $md);
        self::assertStringContainsString('**app**', $md);
        self::assertStringContainsString('**name** (`string`)', $md);
    }

    public function testDefaultAndFlagsRendered(): void
    {
        $md = $this->markdown();

        self::assertStringContainsString("default: `'configure-it'`", $md);
        self::assertStringContainsString('nullable', $md);
        self::assertStringContainsString('required', $md);
        self::assertStringContainsString('deprecated', $md);
    }

    public function testEnumListAndMapAndUnionLabels(): void
    {
        $md = $this->markdown();

        self::assertStringContainsString('**env** (`enum`)', $md);
        self::assertStringContainsString("'dev'", $md);
        self::assertStringContainsString("'staging'", $md);
        self::assertStringContainsString("'prod'", $md);
        self::assertStringContainsString('**tags** (`array<string>`)', $md);
        self::assertStringContainsString('**features** (`map<string, boolean>`)', $md);
        self::assertStringContainsString('**source** (`union`)', $md);
    }

    public function testRangeAndPatternRendered(): void
    {
        $md = $this->markdown();

        self::assertStringContainsString('range: 1..65535', $md);
        self::assertStringContainsString('pattern: `/^.+@.+$/`', $md);
    }
}
