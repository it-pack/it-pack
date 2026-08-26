<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Core\ConfigurationGraph;
use ItPack\ConfigureIt\Core\ConfigurationNode;
use ItPack\ConfigureIt\Core\ConfigurationSnapshot;

class ConfigurationSnapshotTest extends Unit
{
    public function testGetReturnsNestedValueByDotPath(): void
    {
        $snapshot = $this->snapshot();

        verify($snapshot->get('db.host'))->equals('localhost');
        verify($snapshot->get('app.debug'))->true();
    }

    public function testGetReturnsDefaultForMissing(): void
    {
        $snapshot = $this->snapshot();

        verify($snapshot->get('db.missing', 'fallback'))->equals('fallback');
        verify($snapshot->get('db.missing'))->null();
    }

    public function testHasReflectsPresence(): void
    {
        $snapshot = $this->snapshot();

        verify($snapshot->has('db.host'))->true();
        verify($snapshot->has('nope.nope'))->false();
    }

    public function testAllReturnsFullTree(): void
    {
        $snapshot = $this->snapshot();

        verify($snapshot->all())->equals([
            'db' => ['host' => 'localhost'],
            'app' => ['debug' => true],
        ]);
    }
    private function snapshot(): ConfigurationSnapshot
    {
        $graph = new ConfigurationGraph([
            new ConfigurationNode('db.host', 'localhost', 'base', 10),
            new ConfigurationNode('app.debug', true, 'base', 10),
        ]);

        return ConfigurationSnapshot::fromGraph($graph);
    }
}
