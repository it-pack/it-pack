<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Contract\ValueObject\ConfigurationDocumentInterface;
use ItPack\ConfigureIt\Core\ConfigurationDocument;
use ItPack\ConfigureIt\Core\ConfigurationPipeline;
use ItPack\ConfigureIt\Core\DefaultsProcessor;
use ItPack\ConfigureIt\ValueObject\Format;

class ConfigurationPipelineTest extends Unit
{
    /**
     * @return iterable<ConfigurationDocumentInterface>
     */
    private function docs(): iterable
    {
        yield new ConfigurationDocument(['a' => ['x' => 1]], Format::PHP, 'base');
        yield new ConfigurationDocument(['a' => ['y' => 2]], Format::PHP, 'local');
    }

    public function testDocumentsFlattenAndMergeInOrder(): void
    {
        $pipeline = new ConfigurationPipeline();

        $graph = $pipeline->run($this->docs());

        verify($graph->has('a.x'))->true();
        verify($graph->has('a.y'))->true();
        verify($graph->node('a.x')->source())->equals('base');
        verify($graph->node('a.y')->source())->equals('local');
    }

    public function testProcessorsTransformGraphDeterministically(): void
    {
        $pipeline = new ConfigurationPipeline([new DefaultsProcessor(['a.z' => 99])]);

        $graph = $pipeline->run($this->docs());

        verify($graph->has('a.z'))->true();
        verify($graph->node('a.z')->value())->equals(99);
        verify($graph->node('a.z')->source())->equals('defaults');
    }

    public function testRunsThroughInterfaceType(): void
    {
        $pipeline = new ConfigurationPipeline();

        $graph = $pipeline->run($this->docs());

        verify($graph->has('a.x'))->true();
    }
}
