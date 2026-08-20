<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Core\CastingProcessor;
use ItPack\ConfigureIt\Core\ConfigurationGraph;
use ItPack\ConfigureIt\Core\ConfigurationNode;
use ItPack\ConfigureIt\Exception\CastException;

class CastingProcessorTest extends Unit
{
    public function testCastsStringToTypedScalars(): void
    {
        $graph = new ConfigurationGraph([
            new ConfigurationNode('port', '8080', 'ini', 10),
            new ConfigurationNode('ratio', '0.5', 'ini', 10),
            new ConfigurationNode('debug', 'true', 'ini', 10),
            new ConfigurationNode('name', 'api', 'ini', 10),
        ]);

        $next = new CastingProcessor([
            'port' => 'int',
            'ratio' => 'float',
            'debug' => 'bool',
            'name' => 'string',
        ])->process($graph);

        verify($next->node('port')->value())->equals(8080);
        verify($next->node('ratio')->value())->equals(0.5);
        verify($next->node('debug')->value())->equals(true);
        verify($next->node('name')->value())->equals('api');
    }

    public function testPreservesSourcePriorityAndMetadata(): void
    {
        $graph = new ConfigurationGraph([
            new ConfigurationNode('port', '8080', 'ini', 10, ['origin' => 'ini']),
        ]);

        $next = new CastingProcessor(['port' => 'int'])->process($graph);

        $node = $next->node('port');
        verify($node->source())->equals('ini');
        verify($node->priority())->equals(10);
        verify($node->metadata())->equals(['origin' => 'ini']);
    }

    public function testSkipsMissingKeys(): void
    {
        $graph = new ConfigurationGraph([
            new ConfigurationNode('port', '8080', 'ini', 10),
        ]);

        $next = new CastingProcessor(['missing' => 'int'])->process($graph);

        verify($next->has('missing'))->false();
    }

    public function testThrowsOnUncastableValue(): void
    {
        $graph = new ConfigurationGraph([
            new ConfigurationNode('port', 'not-a-number', 'ini', 10),
        ]);

        $this->expectException(CastException::class);

        new CastingProcessor(['port' => 'int'])->process($graph);
    }

    public function testThrowsOnUnknownType(): void
    {
        $graph = new ConfigurationGraph([
            new ConfigurationNode('port', '8080', 'ini', 10),
        ]);

        $this->expectException(CastException::class);

        new CastingProcessor(['port' => 'array'])->process($graph);
    }
}
