<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Contract\ValueObject\ConfigurationNodeInterface;
use ItPack\ConfigureIt\Core\ConfigurationGraph;
use ItPack\ConfigureIt\Core\ConfigurationNode;
use ItPack\ConfigureIt\Exception\ConfigurationException;

class ConfigurationGraphTest extends Unit
{
    private function node(string $key, mixed $value, string $source, int $priority): ConfigurationNodeInterface
    {
        return new ConfigurationNode($key, $value, $source, $priority);
    }

    public function testHigherPriorityWinsOnConflict(): void
    {
        $graph = new ConfigurationGraph([
            $this->node('db.host', 'base', 'base', 10),
            $this->node('db.host', 'override', 'local', 20),
        ]);

        verify($graph->node('db.host')->value())->equals('override');
        verify($graph->node('db.host')->source())->equals('local');
    }

    public function testLowerPriorityDoesNotOverride(): void
    {
        $graph = new ConfigurationGraph([
            $this->node('db.host', 'base', 'base', 30),
            $this->node('db.host', 'weak', 'local', 5),
        ]);

        verify($graph->node('db.host')->value())->equals('base');
    }

    public function testToArrayRebuildsNestedTree(): void
    {
        $graph = new ConfigurationGraph([
            $this->node('db.host', 'localhost', 'base', 10),
            $this->node('db.port', 5432, 'base', 10),
            $this->node('app.debug', true, 'base', 10),
        ]);

        verify($graph->toArray())->equals([
            'db' => ['host' => 'localhost', 'port' => 5432],
            'app' => ['debug' => true],
        ]);
    }

    public function testHasAndMissingNode(): void
    {
        $graph = new ConfigurationGraph([$this->node('a.b', 1, 'base', 1)]);

        verify($graph->has('a.b'))->true();
        verify($graph->has('a.c'))->false();
    }

    public function testNodeThrowsForMissingKey(): void
    {
        $graph = new ConfigurationGraph();

        $this->expectException(ConfigurationException::class);

        $graph->node('missing');
    }
}
