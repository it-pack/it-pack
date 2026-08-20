<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Contract\ConfigurationGraphInterface;
use ItPack\ConfigureIt\Contract\ConfigurationProcessorInterface;
use ItPack\ConfigureIt\Contract\ProvenanceAwareProcessorInterface;
use ItPack\ConfigureIt\Core\ConfigurationDocument;
use ItPack\ConfigureIt\Core\ConfigurationGraph;
use ItPack\ConfigureIt\Core\ConfigurationPipeline;
use ItPack\ConfigureIt\ValueObject\Format;

class ConfigurationPipelineProvenanceTest extends Unit
{
    public function testPipelineRecordsProcessorTrailOnTouchedNodes(): void
    {
        $processor = new class () implements ConfigurationProcessorInterface, ProvenanceAwareProcessorInterface {
            public function stageName(): string
            {
                return 'UpperProcessor';
            }

            public function process(ConfigurationGraphInterface $graph): ConfigurationGraphInterface
            {
                $next = new ConfigurationGraph();

                foreach ($graph->nodes() as $node) {
                    $value = $node->value();

                    $next->put($node->withValue(\is_string($value) ? \strtoupper($value) : $value));
                }

                return $next;
            }
        };

        $pipeline = new ConfigurationPipeline(processors: [$processor]);

        $graph = $pipeline->run([
            new ConfigurationDocument(['name' => 'config'], Format::JSON),
        ]);

        verify($graph->node('name')->provenance()->processorTrail())->equals(['UpperProcessor']);
        verify($graph->node('name')->value())->equals('CONFIG');
    }

    public function testUntouchedNodesAreNotInTrail(): void
    {
        $processor = new class () implements ConfigurationProcessorInterface, ProvenanceAwareProcessorInterface {
            public function stageName(): string
            {
                return 'NoopProcessor';
            }

            public function process(ConfigurationGraphInterface $graph): ConfigurationGraphInterface
            {
                return $graph;
            }
        };

        $pipeline = new ConfigurationPipeline(processors: [$processor]);

        $graph = $pipeline->run([
            new ConfigurationDocument(['name' => 'config'], Format::JSON),
        ]);

        verify($graph->node('name')->provenance()->processorTrail())->equals([]);
    }

    public function testCustomProcessorWithoutInterfaceFallsBackToClassName(): void
    {
        $processor = new class () implements ConfigurationProcessorInterface {
            public function process(ConfigurationGraphInterface $graph): ConfigurationGraphInterface
            {
                $next = new ConfigurationGraph();

                foreach ($graph->nodes() as $node) {
                    $value = $node->value();

                    $next->put($node->withValue(\is_string($value) ? $value . '!' : $value));
                }

                return $next;
            }
        };

        $pipeline = new ConfigurationPipeline(processors: [$processor]);

        $graph = $pipeline->run([
            new ConfigurationDocument(['name' => 'config'], Format::JSON),
        ]);

        $trail = $graph->node('name')->provenance()->processorTrail();

        verify(\count($trail))->equals(1);
        verify($trail[0])->equals(\get_class($processor));
    }
}
