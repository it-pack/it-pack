<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Core\ConfigurationGraph;
use ItPack\ConfigureIt\Core\ConfigurationNode;
use ItPack\ConfigureIt\Core\ConfigurationSnapshot;
use ItPack\ConfigureIt\Core\TypedSnapshot;
use ItPack\ConfigureIt\Exception\TypeException;

class TypedSnapshotTest extends Unit
{
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
}
