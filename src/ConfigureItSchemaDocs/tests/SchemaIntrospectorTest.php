<?php

declare(strict_types=1);

namespace ItPack\ConfigureItSchemaDocs\Tests;

use ItPack\ConfigureItSchemaDocs\Model\SchemaNode;
use ItPack\ConfigureItSchemaDocs\SchemaIntrospector;
use Nette\Schema\Schema;
use PHPUnit\Framework\TestCase;

final class SchemaIntrospectorTest extends TestCase
{
    private function definition(): Schema
    {
        /** @var Schema $def */
        $def = require __DIR__.'/Fixtures/sample-schema.php';

        return $def;
    }

    private function root(): SchemaNode
    {
        return (new SchemaIntrospector())->introspect($this->definition());
    }

    public function testRootIsObjectContainingApp(): void
    {
        $root = $this->root();

        self::assertSame(SchemaNode::KIND_OBJECT, $root->kind);
        self::assertArrayHasKey('app', $root->properties);
    }

    public function testScalarFieldsCarryTypeDefaultAndRequired(): void
    {
        $app = $this->root()->properties['app'];

        self::assertSame('string', $app->properties['name']->type);
        self::assertSame('configure-it', $app->properties['name']->default);
        self::assertFalse($app->properties['name']->required);

        self::assertSame('boolean', $app->properties['debug']->type);
        self::assertFalse($app->properties['debug']->default);

        self::assertSame('integer', $app->properties['port']->type);
        self::assertEquals([1, 65535], $app->properties['port']->range);
        self::assertSame(8080, $app->properties['port']->default);
    }

    public function testNullableFlagIsCaptured(): void
    {
        $secret = $this->root()->properties['app']->properties['secret'];

        self::assertSame('string', $secret->type);
        self::assertTrue($secret->nullable);
    }

    public function testEnumFromScalarAnyOf(): void
    {
        $env = $this->root()->properties['app']->properties['env'];

        self::assertSame(SchemaNode::KIND_ENUM, $env->kind);
        self::assertSame(['dev', 'staging', 'prod'], $env->enumValues);
        self::assertSame('dev', $env->default);
        self::assertTrue($env->hasDefault);
    }

    public function testListAndMapArrays(): void
    {
        $app = $this->root()->properties['app'];

        $tags = $app->properties['tags'];
        self::assertSame(SchemaNode::KIND_ARRAY, $tags->kind);
        self::assertNotNull($tags->items);
        self::assertSame('string', $tags->items->type);

        $features = $app->properties['features'];
        self::assertSame(SchemaNode::KIND_ARRAY, $features->kind);
        self::assertNotNull($features->additionalProperties);
        self::assertSame('boolean', $features->additionalProperties->type);
    }

    public function testUnionFromSchemaAnyOf(): void
    {
        $source = $this->root()->properties['app']->properties['source'];

        self::assertSame(SchemaNode::KIND_UNION, $source->kind);
        self::assertCount(2, $source->anyOf);
        self::assertSame('string', $source->anyOf[0]->type);
        self::assertSame(SchemaNode::KIND_OBJECT, $source->anyOf[1]->kind);
        self::assertArrayHasKey('url', $source->anyOf[1]->properties);
    }

    public function testDeprecationIsRecordedOnFieldAndStructure(): void
    {
        $app = $this->root()->properties['app'];

        self::assertTrue($app->deprecated);
        self::assertTrue($app->properties['deprecatedFlag']->deprecated);
    }

    public function testPatternIsCaptured(): void
    {
        $email = $this->root()->properties['app']->properties['email'];

        self::assertSame('/^.+@.+$/', $email->pattern);
    }
}
