<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Adapter\NativeConfigurationSchema;
use ItPack\ConfigureIt\Core\ConfigurationGraph;
use ItPack\ConfigureIt\Core\ConfigurationNode;
use Nette\Schema\Expect;

class NativeConfigurationSchemaTest extends Unit
{
    /**
     * @param array<string, mixed> $data
     */
    private function graphWith(array $data): ConfigurationGraph
    {
        $nodes = [];

        foreach ($data as $key => $value) {
            $nodes[] = new ConfigurationNode($key, $value, 'base', 10);
        }

        return new ConfigurationGraph($nodes);
    }

    public function testValidatesAgainstExpect(): void
    {
        $schema = new NativeConfigurationSchema(Expect::structure([
            'app' => Expect::structure(['debug' => Expect::bool()]),
        ])->castTo('array'));

        $result = $schema->validate($this->graphWith(['app' => ['debug' => true]]));

        verify($result->valid())->true();
        verify($result->errors())->equals([]);
    }

    public function testReportsErrorsForInvalidData(): void
    {
        $schema = new NativeConfigurationSchema(Expect::structure([
            'app' => Expect::structure(['debug' => Expect::bool()]),
        ])->castTo('array'));

        $result = $schema->validate($this->graphWith(['app' => ['debug' => 'not-bool']]));

        verify($result->valid())->false();
        verify($result->errors())->notEmpty();
    }

    /**
     * A failure that carries no path (the whole document is wrong) is attributed
     * to the synthetic "(root)" location rather than an empty string.
     */
    public function testRootLevelFailureIsAttributedToTheRootPath(): void
    {
        $schema = new NativeConfigurationSchema(Expect::listOf(Expect::string()));

        $result = $schema->validate($this->graphWith(['app' => 'value']));

        verify($result->valid())->false();
        verify(count($result->errors()))->equals(1);
        verify($result->errors()[0]['path'])->equals('(root)');
        verify($result->errors()[0]['severity'])->equals('error');
    }

    /**
     * Nette keeps deprecations in a separate channel: they are surfaced as
     * non-blocking warnings and must not flip the result to invalid.
     */
    public function testDeprecationWarningsAreSurfacedWithoutInvalidatingTheResult(): void
    {
        $schema = new NativeConfigurationSchema(Expect::structure([
            'legacy' => Expect::string()->deprecated('legacy is deprecated'),
            'app' => Expect::structure(['debug' => Expect::bool()]),
        ])->castTo('array'));

        $result = $schema->validate($this->graphWith([
            'legacy' => 'still-here',
            'app' => ['debug' => true],
        ]));

        verify($result->valid())->true();
        verify($result->errors())->equals([]);
        verify(count($result->warnings()))->equals(1);
        verify($result->warnings()[0]['path'])->equals('(root)');
        verify($result->warnings()[0]['message'])->equals('legacy is deprecated');
        verify($result->warnings()[0]['severity'])->equals('warning');
    }

    public function testErrorsAndWarningsAreReportedTogetherOnFailure(): void
    {
        $schema = new NativeConfigurationSchema(Expect::structure([
            'legacy' => Expect::string()->deprecated('legacy is deprecated'),
            'port' => Expect::int(),
        ])->castTo('array'));

        $result = $schema->validate($this->graphWith([
            'legacy' => 'still-here',
            'port' => 'not-an-int',
        ]));

        verify($result->valid())->false();
        verify(count($result->errors()))->equals(1);
        verify($result->errors()[0]['path'])->equals('port');
        verify(count($result->warnings()))->equals(1);
        verify(count($result->issues()))->equals(2);
    }

    /**
     * The scalar-type vocabulary is deliberately narrow: only types the freeze-time
     * TypedSnapshot can enforce are declared, unions and open types are omitted.
     */
    public function testScalarTypesMapsTheKnownNetteVocabularyAndOmitsTheRest(): void
    {
        $schema = new NativeConfigurationSchema(Expect::structure([
            'name' => Expect::string(),
            'port' => Expect::int(),
            'ratio' => Expect::float(),
            'debug' => Expect::bool(),
            'contact' => Expect::email(),
            'tags' => Expect::listOf(Expect::string()),
            'labels' => Expect::arrayOf(Expect::string()),
            'nullablePort' => Expect::int()->nullable(),
            'anything' => Expect::mixed(),
            'loose' => Expect::scalar(),
            'either' => Expect::anyOf('a', 'b'),
            'nested' => Expect::structure(['deep' => Expect::string()]),
        ]));

        verify($schema->scalarTypes())->equals([
            'name' => 'string',
            'port' => 'int',
            'ratio' => 'float',
            'debug' => 'bool',
            'contact' => 'string',
            'tags' => 'list',
            'labels' => 'map',
            'nullablePort' => 'int',
            'nested.deep' => 'string',
        ]);
    }

    public function testScalarTypesIsEmptyForARootLevelScalarSchema(): void
    {
        verify(new NativeConfigurationSchema(Expect::string())->scalarTypes())->equals([]);
        verify(new NativeConfigurationSchema(Expect::arrayOf(Expect::string()))->scalarTypes())->equals([]);
    }

    public function testScalarTypesIsEmptyForANonStructureNonTypeSchema(): void
    {
        verify(new NativeConfigurationSchema(Expect::anyOf('a', 'b'))->scalarTypes())->equals([]);
    }

    public function testEmptyStructureDeclaresNoScalarTypes(): void
    {
        verify(new NativeConfigurationSchema(Expect::structure([]))->scalarTypes())->equals([]);
    }
}
