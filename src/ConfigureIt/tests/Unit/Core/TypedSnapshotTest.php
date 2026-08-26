<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Core\ConfigurationGraph;
use ItPack\ConfigureIt\Core\ConfigurationNode;
use ItPack\ConfigureIt\Core\ConfigurationSnapshot;
use ItPack\ConfigureIt\Core\TypedSnapshot;
use ItPack\ConfigureIt\Exception\TypeException;
use ItPack\ConfigureIt\ValueObject\Format;
use ItPack\ConfigureIt\ValueObject\Provenance;
use ItPack\ConfigureIt\ValueObject\SourceKind;
use stdClass;

class TypedSnapshotTest extends Unit
{
    public function testConformingTreeValidatesAtFreeze(): void
    {
        $snapshot = new TypedSnapshot($this->inner(), [
            'app.port' => 'int',
            'app.debug' => 'bool',
            'app.tags' => 'list',
            'app.config' => 'map',
        ]);

        verify($snapshot->int('app.port'))->equals(8080);
        verify($snapshot->bool('app.debug'))->true();
        verify($snapshot->stringList('app.tags'))->equals(['a', 'b']);
        verify($snapshot->get('app.config'))->equals(['host' => 'localhost']);
        verify($snapshot->all())->equals($this->inner()->all());
    }

    public function testDelegatesProvenanceAndPresence(): void
    {
        $snapshot = new TypedSnapshot($this->inner(), ['app.port' => 'int']);

        verify($snapshot->has('app.port'))->true();
        verify($snapshot->exists('app.port'))->true();
        verify($snapshot->provenance('app.port'))->notNull();
    }

    public function testFreezeThrowsOnMismatch(): void
    {
        $this->expectException(TypeException::class);
        $this->expectExceptionMessage('Configuration key "app.port" expected bool, got int.');

        new TypedSnapshot($this->inner(), ['app.port' => 'bool']);
    }

    public function testFreezeIgnoresAbsentDeclaredKeys(): void
    {
        $snapshot = new TypedSnapshot($this->inner(), ['app.port' => 'int', 'app.missing' => 'string']);

        verify($snapshot->int('app.port'))->equals(8080);
    }

    public function testExposesTheDeclaredTypeMap(): void
    {
        $types = ['app.port' => 'int', 'app.debug' => 'bool'];

        verify(new TypedSnapshot($this->inner(), $types)->scalarTypes())->equals($types);
        verify(new TypedSnapshot($this->inner(), [])->scalarTypes())->equals([]);
    }

    public function testExposesTheWrappedSnapshotForArtifactSerialization(): void
    {
        $inner = $this->inner();
        $snapshot = new TypedSnapshot($inner, ['app.port' => 'int']);

        self::assertSame($inner, $snapshot->innerSnapshot());
        verify($snapshot->innerSnapshot()->all())->equals($inner->all());
    }

    public function testDelegatesEveryTypedAccessorToTheInnerSnapshot(): void
    {
        $graph = new ConfigurationGraph([
            new ConfigurationNode('app.port', 8080, 'base', 10),
            new ConfigurationNode('app.ratio', 0.5, 'base', 10),
            new ConfigurationNode('app.debug', true, 'base', 10),
            new ConfigurationNode('app.name', 'api', 'base', 10),
            new ConfigurationNode('app.tags', ['a', 'b'], 'base', 10),
        ]);

        $snapshot = new TypedSnapshot(ConfigurationSnapshot::fromGraph($graph), [
            'app.port' => 'int',
            'app.ratio' => 'float',
            'app.debug' => 'bool',
            'app.name' => 'string',
            'app.tags' => 'list',
        ]);

        verify($snapshot->int('app.port'))->equals(8080);
        verify($snapshot->float('app.ratio'))->equals(0.5);
        verify($snapshot->bool('app.debug'))->true();
        verify($snapshot->string('app.name'))->equals('api');
        verify($snapshot->list('app.tags'))->equals(['a', 'b']);
        verify($snapshot->stringList('app.tags'))->equals(['a', 'b']);
        verify($snapshot->get('app.name'))->equals('api');
        verify($snapshot->get('app.absent', 'fallback'))->equals('fallback');
        verify($snapshot->has('app.absent'))->false();
        verify($snapshot->exists('app.absent'))->false();
        verify($snapshot->provenance('app.absent'))->null();
    }

    public function testDelegatesDefaultsForAbsentPaths(): void
    {
        $snapshot = new TypedSnapshot($this->inner(), []);

        verify($snapshot->int('nope', 1))->equals(1);
        verify($snapshot->float('nope', 1.5))->equals(1.5);
        verify($snapshot->bool('nope', true))->true();
        verify($snapshot->string('nope', 'x'))->equals('x');
        verify($snapshot->list('nope', [1]))->equals([1]);
        verify($snapshot->stringList('nope', ['a']))->equals(['a']);
    }

    public function testDelegatesSensitivity(): void
    {
        $graph = new ConfigurationGraph([
            new ConfigurationNode(
                'db.password',
                's3cr3t',
                'vault',
                10,
                [],
                new Provenance('vault://app', Format::JSON, 'VaultLoader', 10, SourceKind::Vault, sensitive: true),
            ),
            new ConfigurationNode(
                'app.name',
                'api',
                'base',
                10,
                [],
                new Provenance('base.json', Format::JSON, 'JsonLoader', 10, SourceKind::File),
            ),
        ]);

        $snapshot = new TypedSnapshot(ConfigurationSnapshot::fromGraph($graph), ['db.password' => 'string']);

        verify($snapshot->isSensitive('db.password'))->true();
        verify($snapshot->isSensitive('app.name'))->false();
        verify($snapshot->provenance('db.password')?->isSensitive())->true();
    }

    /**
     * @dataProvider conformingDeclarations
     */
    public function testAcceptsEveryDeclaredType(string $type, mixed $value): void
    {
        $graph = new ConfigurationGraph([new ConfigurationNode('k', $value, 'base', 10)]);

        $snapshot = new TypedSnapshot(ConfigurationSnapshot::fromGraph($graph), ['k' => $type]);

        verify($snapshot->get('k'))->equals($value);
    }

    /**
     * @return array<string, array{string, mixed}>
     */
    public function conformingDeclarations(): array
    {
        return [
            'int'                 => ['int', 8080],
            'int from string'     => ['int', '8080'],
            'float'               => ['float', 0.5],
            'float from int'      => ['float', 2],
            'bool'                => ['bool', true],
            'bool from string'    => ['bool', 'yes'],
            'string'              => ['string', 'api'],
            'string from int'     => ['string', 5],
            'list'                => ['list', ['a', 'b']],
            'empty list'          => ['list', []],
            'map'                 => ['map', ['host' => 'localhost']],
        ];
    }

    /**
     * @dataProvider violatingDeclarations
     */
    public function testRejectsEveryViolatedDeclarationAtFreezeTime(string $type, mixed $value, string $expectedMessage): void
    {
        $graph = new ConfigurationGraph([new ConfigurationNode('k', $value, 'base', 10)]);
        $inner = ConfigurationSnapshot::fromGraph($graph);

        try {
            new TypedSnapshot($inner, ['k' => $type]);
            self::fail(sprintf('Expected the "%s" declaration to be rejected.', $type));
        } catch (TypeException $e) {
            verify($e->getMessage())->equals($expectedMessage);
        }
    }

    /**
     * @return array<string, array{string, mixed, string}>
     */
    public function violatingDeclarations(): array
    {
        return [
            'int vs word'      => ['int', 'abc', 'Configuration key "k" expected int, got string.'],
            'int vs bool'      => ['int', true, 'Configuration key "k" expected int, got bool.'],
            'float vs word'    => ['float', 'abc', 'Configuration key "k" expected float, got string.'],
            'bool vs int two'  => ['bool', 2, 'Configuration key "k" expected bool, got int.'],
            'string vs list'   => ['string', ['a'], 'Configuration key "k" expected string, got array.'],
            'list vs map'      => ['list', ['a' => 1], 'Configuration key "k" expected a list, got array.'],
            'map vs list'      => ['map', ['a', 'b'], 'Configuration key "k" expected map, got array.'],
            // An unsupported declaration is a configuration mistake in its own right.
            'unsupported type' => ['datetime', 'now', 'Unsupported declared type "datetime" for key "k".'],
            'empty type'       => ['', 'x', 'Unsupported declared type "" for key "k".'],
        ];
    }

    public function testAnEmptyTypeMapValidatesNothing(): void
    {
        $graph = new ConfigurationGraph([new ConfigurationNode('k', new stdClass(), 'base', 10)]);

        $snapshot = new TypedSnapshot(ConfigurationSnapshot::fromGraph($graph), []);

        verify($snapshot->scalarTypes())->equals([]);
        verify($snapshot->has('k'))->true();
    }
    private function inner(): ConfigurationSnapshot
    {
        $graph = new ConfigurationGraph([
            new ConfigurationNode('app.port', 8080, 'base', 10),
            new ConfigurationNode('app.debug', true, 'base', 10),
            new ConfigurationNode('app.tags', ['a', 'b'], 'base', 10),
            new ConfigurationNode('app.config', ['host' => 'localhost'], 'base', 10),
        ]);

        return ConfigurationSnapshot::fromGraph($graph);
    }
}
