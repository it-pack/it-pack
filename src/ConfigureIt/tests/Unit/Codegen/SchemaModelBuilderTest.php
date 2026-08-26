<?php

declare(strict_types=1);

namespace Tests\Unit\Codegen;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Codegen\Model\Property;
use ItPack\ConfigureIt\Codegen\SchemaModelBuilder;
use Nette\Schema\Expect;

/**
 * Exercises the full schema-to-model translation matrix: scalars, collections
 * (list/map of scalar and of structure), nested structures and the fallback
 * branches for unknown element types and non-structure/non-type schemas.
 *
 * @covers \ItPack\ConfigureIt\Codegen\SchemaModelBuilder
 */
final class SchemaModelBuilderTest extends Unit
{
    public function testNestedStructureProducesNestedDto(): void
    {
        $models = new SchemaModelBuilder()->build(
            Expect::structure([
                'db' => Expect::structure([
                    'host' => Expect::string(),
                ]),
            ]),
            'AppConfig',
        );

        verify($models[0]->className)->equals('AppConfig');
        verify($models[0]->root)->true();

        $db = $models[1];
        verify($db->className)->equals('AppConfigDb');
        verify($db->root)->false();
        verify($db->properties[0])->equals(new Property('host', 'scalar', 'string', 'string'));
    }

    public function testScalarRootIsWrapped(): void
    {
        $models = new SchemaModelBuilder()->build(Expect::int(), 'Count');

        verify(count($models))->equals(1);
        verify($models[0]->className)->equals('Count');
        verify($models[0]->properties[0])->equals(new Property('value', 'scalar', 'int', 'int'));
    }

    public function testUnknownScalarTypeFallsBackToMixed(): void
    {
        $models = new SchemaModelBuilder()->build(Expect::type('resource'), 'Weird');

        verify($models[0]->properties[0])->equals(new Property('value', 'scalar', 'mixed', 'get'));
    }

    public function testNonTypeNonStructureSchemaFallsBackToMixed(): void
    {
        $models = new SchemaModelBuilder()->build(
            Expect::anyOf(Expect::int(), Expect::string()),
            'Either',
        );

        verify($models[0]->className)->equals('Either');
        verify($models[0]->properties[0])->equals(new Property('value', 'scalar', 'mixed', 'get'));
    }

    public function testListOfStructureProducesItemDto(): void
    {
        $models = new SchemaModelBuilder()->build(
            Expect::structure([
                'servers' => Expect::listOf(Expect::structure([
                    'ip' => Expect::string(),
                ])),
            ]),
            'AppConfig',
        );

        $servers = $models[0]->properties[0];
        verify($servers->kind)->equals('list');
        verify($servers->phpType)->equals('array<int, AppConfigServersItem>');
        verify($servers->nestedClass)->equals('AppConfigServersItem');

        $item = $models[1];
        verify($item->className)->equals('AppConfigServersItem');
        verify($item->properties[0]->name)->equals('ip');
    }

    public function testMapOfStructureProducesItemDto(): void
    {
        $models = new SchemaModelBuilder()->build(
            Expect::structure([
                'peers' => Expect::arrayOf(Expect::structure([
                    'host' => Expect::string(),
                ])),
            ]),
            'AppConfig',
        );

        $peers = $models[0]->properties[0];
        verify($peers->kind)->equals('map');
        verify($peers->phpType)->equals('array<string, AppConfigPeersItem>');
    }

    public function testListOfScalarProducesTypedArray(): void
    {
        $models = new SchemaModelBuilder()->build(
            Expect::structure([
                'tags' => Expect::listOf(Expect::string()),
            ]),
            'AppConfig',
        );

        $tags = $models[0]->properties[0];
        verify($tags->kind)->equals('list');
        verify($tags->phpType)->equals('array<int, string>');
        verify($tags->elementType)->equals('string');
        verify($tags->elementRead)->equals('string');
        verify($tags->nestedClass)->null();
    }

    public function testMapOfScalarProducesTypedArray(): void
    {
        $models = new SchemaModelBuilder()->build(
            Expect::structure([
                'meta' => Expect::arrayOf(Expect::int()),
            ]),
            'AppConfig',
        );

        $meta = $models[0]->properties[0];
        verify($meta->kind)->equals('map');
        verify($meta->phpType)->equals('array<string, int>');
        verify($meta->elementRead)->equals('int');
    }

    public function testListRootIsWrappedWithListKind(): void
    {
        $models = new SchemaModelBuilder()->build(Expect::listOf(Expect::int()), 'Items');

        verify($models[0]->className)->equals('Items');
        verify($models[0]->properties[0]->kind)->equals('list');
        verify($models[0]->properties[0]->phpType)->equals('array<int, mixed>');
    }

    public function testMapRootIsWrappedWithMapKind(): void
    {
        $models = new SchemaModelBuilder()->build(Expect::arrayOf(Expect::string()), 'Map');

        verify($models[0]->properties[0]->kind)->equals('map');
        verify($models[0]->properties[0]->phpType)->equals('array<string, mixed>');
    }

    public function testScalarMatrixMapsToReadMethods(): void
    {
        $schema = Expect::structure([
            'a' => Expect::int(),
            'b' => Expect::float(),
            'c' => Expect::bool(),
            'd' => Expect::string(),
            'e' => Expect::email(),
            'f' => Expect::unicode(),
        ]);

        $models = new SchemaModelBuilder()->build($schema, 'ScalarMatrix');

        $reads = array_map(static fn (Property $p): string => $p->readMethod, $models[0]->properties);
        verify($reads)->equals(['int', 'float', 'bool', 'string', 'string', 'string']);

        $types = array_map(static fn (Property $p): string => $p->phpType, $models[0]->properties);
        verify($types)->equals(['int', 'float', 'bool', 'string', 'string', 'string']);
    }

    public function testStructureChildWithAnyOfFallsBackToMixed(): void
    {
        $models = new SchemaModelBuilder()->build(
            Expect::structure([
                'weird' => Expect::anyOf(Expect::int(), Expect::string()),
            ]),
            'AppConfig',
        );

        $weird = $models[0]->properties[0];
        verify($weird->kind)->equals('scalar');
        verify($weird->phpType)->equals('mixed');
        verify($weird->readMethod)->equals('get');
    }
}
