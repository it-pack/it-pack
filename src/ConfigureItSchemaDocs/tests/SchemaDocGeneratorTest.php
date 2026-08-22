<?php

declare(strict_types=1);

namespace ItPack\ConfigureItSchemaDocs\Tests;

use ItPack\ConfigureItSchemaDocs\SchemaDocGenerator;
use Nette\Schema\Schema;
use PHPUnit\Framework\TestCase;

final class SchemaDocGeneratorTest extends TestCase
{
    private function definition(): Schema
    {
        /** @var Schema $def */
        $def = require __DIR__.'/Fixtures/sample-schema.php';

        return $def;
    }

    public function testMarkdownOutput(): void
    {
        $markdown = SchemaDocGenerator::fromExpect($this->definition(), 'Sample')->toMarkdown();

        self::assertStringContainsString('# Sample', $markdown);
        self::assertStringContainsString('**app**', $markdown);
    }

    public function testJsonSchemaOutputIsValidJson(): void
    {
        $generator = SchemaDocGenerator::fromExpect($this->definition(), 'Sample');

        $json = $generator->toJsonSchema();
        self::assertSame('object', $json['type']);

        $text = $generator->toJsonSchemaJson();
        self::assertJson($text);

        /** @var array<mixed, mixed> $decoded */
        $decoded = json_decode($text, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('https://json-schema.org/draft/2020-12/schema', $decoded['$schema']);
    }

    public function testWithTitleReturnsIndependentInstance(): void
    {
        $generator = SchemaDocGenerator::fromExpect($this->definition(), 'First');
        $renamed = $generator->withTitle('Second');

        self::assertStringContainsString('# Second', $renamed->toMarkdown());
        self::assertStringContainsString('# First', $generator->toMarkdown());
    }
}
