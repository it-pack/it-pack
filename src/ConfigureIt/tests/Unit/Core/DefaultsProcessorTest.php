<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Core\ConfigurationGraph;
use ItPack\ConfigureIt\Core\ConfigurationNode;
use ItPack\ConfigureIt\Core\DefaultsProcessor;

class DefaultsProcessorTest extends Unit
{
    public function testFillsMissingKeysOnly(): void
    {
        $graph = new ConfigurationGraph([
            new ConfigurationNode('app.name', 'demo', 'base', 10),
        ]);

        $next = new DefaultsProcessor(['app.name' => 'default', 'app.debug' => false])->process($graph);

        verify($next->node('app.name')->value())->equals('demo');
        verify($next->node('app.debug')->value())->equals(false);
    }
}
