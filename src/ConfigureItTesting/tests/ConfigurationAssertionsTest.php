<?php

declare(strict_types=1);

namespace ItPack\ConfigureItTesting\Tests;

use ItPack\ConfigureIt\ValueObject\Format;
use ItPack\ConfigureIt\ValueObject\Provenance;
use ItPack\ConfigureItTesting\Assertions\ConfigurationAssertions;
use ItPack\ConfigureItTesting\Fake\InMemoryGraph;
use ItPack\ConfigureItTesting\Node\FakeNode;
use Nette\Schema\Expect;
use PHPUnit\Framework\TestCase;

final class ConfigurationAssertionsTest extends TestCase
{
    use ConfigurationAssertions;

    private function graph(): InMemoryGraph
    {
        $emailProvenance = (new Provenance(
            resourceUri: 'base',
            format: Format::PHP,
            adapter: 'fake',
            priority: 0,
            sourceKind: 'programmatic',
        ))->withProcessor('merge')->withProcessor('freeze');

        $graph = new InMemoryGraph();
        $graph->put(FakeNode::create('app.name', 'it', 'base', 0));
        $graph->put(FakeNode::override('app.debug', false, 'local', 20, 'base.json', 'local'));
        $graph->put(FakeNode::sensitive('db.password', 'secret', 'vault', 0));
        $graph->put(FakeNode::from('cache.ttl', 60, 'redis.php', 5, 'file', 'yaml-loader', Format::YAML));
        $graph->put(FakeNode::withProvenance('email', 'x@y.z', $emailProvenance));

        return $graph;
    }

    public function testKeyPresence(): void
    {
        $graph = $this->graph();

        $this->assertGraphHasKey($graph, 'app.name');
        $this->assertGraphNotHasKey($graph, 'missing.key');
    }

    public function testGraphEqualsExpandedTree(): void
    {
        $graph = $this->graph();

        $this->assertGraphEquals($graph, [
            'app' => ['name' => 'it', 'debug' => false],
            'db' => ['password' => 'secret'],
            'cache' => ['ttl' => 60],
            'email' => 'x@y.z',
        ]);
    }

    public function testKeyValueAndSourceKind(): void
    {
        $graph = $this->graph();

        $this->assertKeyEquals($graph, 'app.name', 'it');
        $this->assertKeyResolvedFrom($graph, 'app.name', 'programmatic');
    }

    public function testOverrideAndSensitivity(): void
    {
        $graph = $this->graph();

        $this->assertKeyOverriddenBy($graph, 'app.debug', 'base.json');
        $this->assertKeyIsSensitive($graph, 'db.password', true);
        $this->assertKeyIsSensitive($graph, 'app.name', false);
    }

    public function testFormatAdapterPriorityAndProcessor(): void
    {
        $graph = $this->graph();

        $this->assertKeyFormat($graph, 'cache.ttl', Format::YAML);
        $this->assertKeyAdapter($graph, 'cache.ttl', 'yaml-loader');
        $this->assertKeyPriority($graph, 'app.debug', 20);
        $this->assertKeyHasProcessor($graph, 'email', 'freeze');
        $this->assertKeyHasProcessor($graph, 'email', 'merge');
    }

    public function testConfigValid(): void
    {
        $expect = Expect::structure([
            'name' => Expect::string(),
            'age' => Expect::int()->min(0),
        ]);

        $this->assertConfigValid($expect, ['name' => 'it', 'age' => 5]);
    }
}
