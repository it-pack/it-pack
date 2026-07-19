<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Adapter\FileReader;
use ItPack\ConfigureIt\Adapter\JsonLoader;
use ItPack\ConfigureIt\Adapter\PhpLoader;
use ItPack\ConfigureIt\Contract\ConfigurationGraphInterface;
use ItPack\ConfigureIt\Core\ConfigurationBuilder;
use ItPack\ConfigureIt\Core\ConfigurationGraph;
use ItPack\ConfigureIt\Core\ConfigurationNode;

class ConfigurationBuilderTest extends Unit
{
    public function testAccumulatesLoadersAndBuildsResolver(): void
    {
        $builder = new ConfigurationBuilder(new FileReader())
            ->addLoader(new PhpLoader())
            ->addLoader(new JsonLoader())
        ;

        $resolver = $builder->build();

        verify($resolver)->instanceOf(\ItPack\ConfigureIt\Contract\ConfigurationResolverInterface::class);
    }

    public function testProcessorIsAppliedDuringResolution(): void
    {
        $suffixer = new class () implements \ItPack\ConfigureIt\Contract\ConfigurationProcessorInterface {
            public function process(ConfigurationGraphInterface $graph): ConfigurationGraphInterface
            {
                $next = new ConfigurationGraph();
                $nodes = $graph->nodes();

                foreach ($nodes as $node) {
                    if (is_string($node->value())) {
                        $next->put(new ConfigurationNode(
                            $node->key(),
                            $node->value() . '!',
                            $node->source(),
                            $node->priority(),
                            $node->metadata(),
                        ));
                    }
                }

                return $next;
            }
        };

        $builder = new ConfigurationBuilder(new FileReader())
            ->addLoader(new PhpLoader())
            ->addProcessor($suffixer)
        ;

        verify($builder)->instanceOf(ConfigurationBuilder::class);
    }
}
