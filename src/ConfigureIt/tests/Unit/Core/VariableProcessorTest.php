<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Core\ConfigurationGraph;
use ItPack\ConfigureIt\Core\ConfigurationNode;
use ItPack\ConfigureIt\Core\VariableProcessor;

class VariableProcessorTest extends Unit
{
    public function testInterpolatesPlaceholdersFromGraph(): void
    {
        $graph = new ConfigurationGraph([
            new ConfigurationNode('db.host', 'localhost', 'base', 10),
            new ConfigurationNode('db.port', '5432', 'base', 10),
            new ConfigurationNode('url', '${db.host}:${db.port}', 'base', 10),
        ]);

        $next = new VariableProcessor()->process($graph);

        verify($next->node('url')->value())->equals('localhost:5432');
    }

    public function testLeavesUnknownReferencesVerbatim(): void
    {
        $graph = new ConfigurationGraph([
            new ConfigurationNode('url', '${missing.host}', 'base', 10),
        ]);

        $next = new VariableProcessor()->process($graph);

        verify($next->node('url')->value())->equals('${missing.host}');
    }

    public function testIgnoresNonStringValues(): void
    {
        $graph = new ConfigurationGraph([
            new ConfigurationNode('count', 5, 'base', 10),
        ]);

        $next = new VariableProcessor()->process($graph);

        verify($next->node('count')->value())->equals(5);
    }
}
