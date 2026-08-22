<?php

declare(strict_types=1);

namespace ItPack\ConfigureItTesting\Tests;

use ItPack\ConfigureIt\Core\ConfigurationGraph;
use ItPack\ConfigureItTesting\Fake\InMemoryGraph;
use ItPack\ConfigureItTesting\Node\FakeNode;
use PHPUnit\Framework\TestCase;

final class InMemoryGraphTest extends TestCase
{
    public function testPutAndHasAndNode(): void
    {
        $graph = new InMemoryGraph();
        $graph->put(FakeNode::create('app.name', 'it'));
        $graph->put(FakeNode::create('app.debug', true));

        self::assertTrue($graph->has('app.name'));
        self::assertFalse($graph->has('missing'));
        self::assertSame('it', $graph->node('app.name')->value());
    }

    public function testToArrayExpandsDottedKeys(): void
    {
        $graph = new InMemoryGraph([
            FakeNode::create('app.name', 'it'),
            FakeNode::create('app.debug', true),
            FakeNode::create('list.0', 'a'),
        ]);

        self::assertSame([
            'app' => ['name' => 'it', 'debug' => true],
            'list' => ['a'],
        ], $graph->toArray());
    }

    public function testBuildReturnsImmutableCoreGraph(): void
    {
        $graph = new InMemoryGraph([FakeNode::create('app.name', 'it')]);

        $built = $graph->build();

        self::assertInstanceOf(ConfigurationGraph::class, $built);
        self::assertSame('it', $built->node('app.name')->value());
    }
}
