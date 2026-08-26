<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Core\CastingProcessor;
use ItPack\ConfigureIt\Core\ConfigurationDocumentFactory;
use ItPack\ConfigureIt\Core\ConfigurationGraphRewriter;
use ItPack\ConfigureIt\Core\ConfigurationPipeline;
use ItPack\ConfigureIt\Core\DefaultsProcessor;
use ItPack\ConfigureIt\ValueObject\Format;

class ConfigurationPipelineProcessorsTest extends Unit
{
    public function testDefaultsAndCastingComposeInPipeline(): void
    {
        $factory = new ConfigurationDocumentFactory();

        $documents = [
            $factory->build(['server' => ['port' => '8080', 'debug' => 'true']], Format::INI),
        ];

        $pipeline = new ConfigurationPipeline([
            new DefaultsProcessor(['server.host' => 'localhost', 'server.debug' => false]),
            new CastingProcessor([
                'server.port' => 'int',
                'server.debug' => 'bool',
                'server.host' => 'string',
            ], new ConfigurationGraphRewriter()),
        ]);

        $graph = $pipeline->run($documents);

        verify($graph->toArray())->equals([
            'server' => [
                'port' => 8080,
                'debug' => true,
                'host' => 'localhost',
            ],
        ]);
    }
}
