<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Adapter\FileReader;
use ItPack\ConfigureIt\Adapter\YamlLoader;
use ItPack\ConfigureIt\Core\CastingProcessor;
use ItPack\ConfigureIt\Core\ConfigurationDocumentFactory;
use ItPack\ConfigureIt\Core\ConfigurationGraphRewriter;
use ItPack\ConfigureIt\Core\ConfigurationLoaderFactory;
use ItPack\ConfigureIt\Core\ConfigurationPipeline;
use ItPack\ConfigureIt\Core\DefaultsProcessor;
use ItPack\ConfigureIt\Core\DocumentNormalizer;
use ItPack\ConfigureIt\Core\IncludeProcessor;
use ItPack\ConfigureIt\Core\VariableProcessor;
use ItPack\ConfigureIt\Execution\ExecutionContext;
use ItPack\ConfigureIt\ValueObject\ConfigurationResourceFactory;
use ItPack\ConfigureIt\ValueObject\Format;

class ConfigurationPipelineStagesTest extends Unit
{
    private string $dir;

    public function testStagesComposeIncludeVariableCastDefaults(): void
    {
        $document = new ConfigurationDocumentFactory()->build([
            'db' => ['ref' => '@include db.yaml'],
            'url' => '${db.host}:${db.port}',
            'server' => ['debug' => 'true'],
        ], Format::JSON, 'base');

        $pipeline = new ConfigurationPipeline([
            new IncludeProcessor(new FileReader(), new ConfigurationLoaderFactory([new YamlLoader()]), new DocumentNormalizer(), $this->dir, new ConfigurationResourceFactory(), new ExecutionContext()),
            new VariableProcessor(),
            new CastingProcessor([
                'db.port' => 'int',
                'server.debug' => 'bool',
            ], new ConfigurationGraphRewriter()),
            new DefaultsProcessor(['server.host' => 'default-host']),
        ]);

        $graph = $pipeline->run([$document]);

        verify($graph->toArray())->equals([
            'db' => [
                'port' => 5432,
                'host' => 'localhost',
            ],
            'url' => 'localhost:5432',
            'server' => [
                'debug' => true,
                'host' => 'default-host',
            ],
        ]);
    }

    protected function _before(): void
    {
        $this->dir = sys_get_temp_dir() . '/cfg-stages-' . uniqid();
        mkdir($this->dir, 0o700, true);
        file_put_contents($this->dir . '/db.yaml', "host: localhost\nport: 5432\n");
    }

    protected function _after(): void
    {
        foreach (array_diff(scandir($this->dir), ['.', '..']) as $item) {
            unlink($this->dir . '/' . $item);
        }
        rmdir($this->dir);
    }
}
