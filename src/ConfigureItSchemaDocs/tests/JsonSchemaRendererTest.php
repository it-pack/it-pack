<?php

declare(strict_types=1);

namespace ItPack\ConfigureItSchemaDocs\Tests;

use ItPack\ConfigureItSchemaDocs\Renderer\JsonSchemaRenderer;
use ItPack\ConfigureItSchemaDocs\SchemaIntrospector;
use Nette\Schema\Schema;
use PHPUnit\Framework\TestCase;

final class JsonSchemaRendererTest extends TestCase
{
    private function definition(): Schema
    {
        /** @var Schema $def */
        $def = require __DIR__.'/Fixtures/sample-schema.php';

        return $def;
    }

    /**
     * @return array<mixed, mixed>
     */
    private function decoded(): array
    {
        $root = (new SchemaIntrospector())->introspect($this->definition());
        $json = (new JsonSchemaRenderer())->render($root, 'Sample');

        /** @var array<mixed, mixed> $decoded */
        $decoded = json_decode((string) json_encode($json), true);

        return $decoded;
    }

    public function testDocumentMetadata(): void
    {
        $schema = $this->decoded();

        self::assertSame('https://json-schema.org/draft/2020-12/schema', $schema['$schema']);
        self::assertSame('Sample', $schema['title']);
        self::assertSame('object', $schema['type']);
        self::assertArrayHasKey('app', (array) $schema['properties']);
    }

    public function testStructureEmitsPropertiesAndRequired(): void
    {
        $schema = $this->decoded();
        $properties = (array) $schema['properties'];
        $app = (array) $properties['app'];

        self::assertSame('object', $app['type']);
        self::assertContains('email', (array) $app['required']);
        self::assertArrayHasKey('name', (array) $app['properties']);

        $appProps = (array) $app['properties'];
        $name = (array) $appProps['name'];
        self::assertSame('string', $name['type']);
        self::assertSame('configure-it', $name['default']);
    }

    public function testRangeAndDefaultOnInteger(): void
    {
        $schema = $this->decoded();
        $properties = (array) $schema['properties'];
        $app = (array) $properties['app'];
        $appProps = (array) $app['properties'];
        $port = (array) $appProps['port'];

        self::assertSame('integer', $port['type']);
        self::assertEquals(1, $port['minimum']);
        self::assertEquals(65535, $port['maximum']);
        self::assertSame(8080, $port['default']);
    }

    public function testNullableProducesUnionType(): void
    {
        $schema = $this->decoded();
        $properties = (array) $schema['properties'];
        $app = (array) $properties['app'];
        $appProps = (array) $app['properties'];
        $secret = (array) $appProps['secret'];

        self::assertSame(['string', 'null'], $secret['type']);
    }

    public function testEnumAndListAndMap(): void
    {
        $schema = $this->decoded();
        $properties = (array) $schema['properties'];
        $app = (array) $properties['app'];
        $appProps = (array) $app['properties'];

        $env = (array) $appProps['env'];
        self::assertSame(['dev', 'staging', 'prod'], $env['enum']);
        self::assertSame('dev', $env['default']);

        $tags = (array) $appProps['tags'];
        self::assertSame('array', $tags['type']);
        self::assertSame(['type' => 'string'], $tags['items']);

        $features = (array) $appProps['features'];
        self::assertSame('array', $features['type']);
        self::assertSame(['type' => 'boolean'], $features['additionalProperties']);
    }

    public function testUnionAndPattern(): void
    {
        $schema = $this->decoded();
        $properties = (array) $schema['properties'];
        $app = (array) $properties['app'];
        $appProps = (array) $app['properties'];

        $source = (array) $appProps['source'];
        self::assertArrayHasKey('anyOf', $source);
        self::assertCount(2, (array) $source['anyOf']);

        $email = (array) $appProps['email'];
        self::assertSame('/^.+@.+$/', $email['pattern']);
    }

    public function testDeprecationFlag(): void
    {
        $schema = $this->decoded();
        $properties = (array) $schema['properties'];
        $app = (array) $properties['app'];

        self::assertTrue($app['deprecated']);

        $appProps = (array) $app['properties'];
        $flag = (array) $appProps['deprecatedFlag'];
        self::assertTrue($flag['deprecated']);
    }
}
