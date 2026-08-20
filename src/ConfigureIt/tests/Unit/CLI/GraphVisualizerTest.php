<?php

declare(strict_types=1);

namespace Tests\Unit\CLI;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\CLI\GraphVisualizer;
use ItPack\ConfigureIt\Core\ConfigurationGraph;
use ItPack\ConfigureIt\Core\ConfigurationNode;
use ItPack\ConfigureIt\ValueObject\Format;
use ItPack\ConfigureIt\ValueObject\Provenance;

class GraphVisualizerTest extends Unit
{
    private function graph(): ConfigurationGraph
    {
        return new ConfigurationGraph([
            new ConfigurationNode(
                'db.host',
                'local-db',
                'local.json',
                20,
                [],
                new Provenance('local.json', Format::JSON, 'JsonLoader', 20, 'file')->asOverridden('base.json'),
            ),
            new ConfigurationNode('app.debug', true, 'base.json', 10, [], new Provenance('base.json', Format::JSON, 'JsonLoader', 10, 'file')),
        ]);
    }

    public function testToDotWrapsEveryNode(): void
    {
        $dot = new GraphVisualizer()->toDot($this->graph());

        verify(\str_contains($dot, 'digraph configuration'))->true();
        verify(\str_contains($dot, '"db.host"'))->true();
        verify(\str_contains($dot, 'local-db'))->true();
        verify(\str_contains($dot, 'color=red'))->true();
    }

    public function testToAsciiRendersNestedTree(): void
    {
        $ascii = new GraphVisualizer()->toAscii($this->graph());

        verify(\str_contains($ascii, 'db:'))->true();
        verify(\str_contains($ascii, 'host: local-db'))->true();
        verify(\str_contains($ascii, 'app:'))->true();
        verify(\str_contains($ascii, 'debug: true'))->true();
    }
}
