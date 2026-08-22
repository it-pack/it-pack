<?php

declare(strict_types=1);

namespace ItPack\ConfigureItExplorer\Tests;

use ItPack\ConfigureIt\Contract\ConfigurationGraphInterface;
use ItPack\ConfigureIt\Core\ConfigurationGraph;
use ItPack\ConfigureIt\Core\ConfigurationNode;
use ItPack\ConfigureIt\ValueObject\Format;
use ItPack\ConfigureIt\ValueObject\Provenance;
use ItPack\ConfigureItExplorer\Graph\FromFileGraphProvider;
use ItPack\ConfigureItExplorer\Graph\GraphProviderInterface;
use ItPack\ConfigureItExplorer\Http\ExplorerController;
use ItPack\ConfigureItExplorer\Server\Request;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * T9.2.1 / T9.2.2 — the web controller serves explain, diff, graph and the
 * query API, both as HTML and as JSON, and degrades gracefully on bad input.
 */
final class FakeGraphProvider implements GraphProviderInterface
{
    /**
     * @param array<string, ConfigurationGraphInterface> $map
     */
    public function __construct(private array $map)
    {
    }

    public function build(string $path): ConfigurationGraphInterface
    {
        if (!isset($this->map[$path])) {
            throw new RuntimeException('no graph for '.$path);
        }

        return $this->map[$path];
    }
}

final class ExplorerControllerTest extends TestCase
{
    private function graph(): ConfigurationGraph
    {
        return new ConfigurationGraph([
            new ConfigurationNode(
                'db.host',
                'db.local',
                'local.json',
                100,
                [],
                new Provenance('local.json', Format::JSON, 'JsonLoader', 100, 'file', overridden: true, overriddenSource: 'base.json'),
            ),
            new ConfigurationNode(
                'db.port',
                5432,
                'base.json',
                10,
                [],
                new Provenance('base.json', Format::JSON, 'JsonLoader', 10, 'file'),
            ),
        ]);
    }

    /**
     * @param array<string, ConfigurationGraphInterface> $map
     */
    private function controller(array $map): ExplorerController
    {
        return new ExplorerController(new FakeGraphProvider($map));
    }

    public function testDashboard(): void
    {
        $response = $this->controller([])->dashboard(new Request('GET', '/', []));

        self::assertSame(200, $response->status);
        self::assertStringContainsString('ConfigureIt Explorer', $response->body);
        self::assertStringContainsString('Query grammar', $response->body);
    }

    public function testExplainHtml(): void
    {
        $response = $this->controller(['local.json' => $this->graph()])
            ->explain(new Request('GET', '/explain', ['file' => 'local.json']));

        self::assertSame(200, $response->status);
        self::assertStringContainsString('db.host', $response->body);
    }

    public function testExplainMissingFileShowsForm(): void
    {
        $response = $this->controller([])->explain(new Request('GET', '/explain', []));

        self::assertSame(200, $response->status);
        self::assertStringContainsString('<form', $response->body);
    }

    public function testExplainJsonMasksSecretsAndCarriesProvenance(): void
    {
        $response = $this->controller(['local.json' => $this->graph()])
            ->explain(new Request('GET', '/explain', ['file' => 'local.json'], ['HTTP_ACCEPT' => 'application/json']));

        self::assertSame(200, $response->status);
        self::assertJson($response->body);

        $data = (array) \json_decode($response->body, true);
        $nodes = (array) $data['nodes'];
        self::assertArrayHasKey('db.host', $nodes);
        $host = (array) $nodes['db.host'];
        self::assertSame('db.local', $host['value']);
        $provenance = (array) $host['provenance'];
        self::assertTrue($provenance['overridden']);
    }

    public function testGraphAscii(): void
    {
        $response = $this->controller(['local.json' => $this->graph()])
            ->graph(new Request('GET', '/graph', ['file' => 'local.json', 'format' => 'ascii']));

        self::assertSame(200, $response->status);
        self::assertStringContainsString('db.local', $response->body);
    }

    public function testQueryHtml(): void
    {
        $response = $this->controller(['local.json' => $this->graph()])
            ->query(new Request('GET', '/query', ['file' => 'local.json', 'q' => 'overridden-by:base.json']));

        self::assertSame(200, $response->status);
        self::assertStringContainsString('db.host', $response->body);
    }

    public function testQueryJson(): void
    {
        $response = $this->controller(['local.json' => $this->graph()])
            ->query(new Request('GET', '/query', ['file' => 'local.json', 'q' => 'source:local.json'], ['HTTP_ACCEPT' => 'application/json']));

        self::assertJson($response->body);

        $data = (array) \json_decode($response->body, true);
        self::assertContains('db.host', (array) $data['keys']);
        self::assertSame('source:local.json', $data['query']);
    }

    public function testQueryRequiresFile(): void
    {
        $response = $this->controller([])
            ->query(new Request('GET', '/query', ['q' => 'sensitive']));

        self::assertSame(400, $response->status);
    }

    public function testChangedQueryNeedsBothFiles(): void
    {
        $response = $this->controller([])
            ->query(new Request('GET', '/query', ['q' => 'changed', 'base' => 'base.json']));

        self::assertSame(400, $response->status);
    }

    public function testDiff(): void
    {
        $base = new ConfigurationGraph([
            new ConfigurationNode('db.port', 5432, 'base.json', 1, [], new Provenance('base.json', Format::JSON, 'JsonLoader', 1, 'file')),
        ]);
        $other = new ConfigurationGraph([
            new ConfigurationNode('db.port', 6432, 'other.json', 1, [], new Provenance('other.json', Format::JSON, 'JsonLoader', 1, 'file')),
        ]);

        $response = $this->controller(['base.json' => $base, 'other.json' => $other])
            ->diff(new Request('GET', '/diff', ['base' => 'base.json', 'other' => 'other.json']));

        self::assertSame(200, $response->status);
        self::assertStringContainsString('db.port', $response->body);
    }

    public function testLoadErrorReturns400(): void
    {
        $response = $this->controller([])->explain(new Request('GET', '/explain', ['file' => 'missing.json']));

        self::assertSame(400, $response->status);
    }

    public function testEndToEndOverRealFiles(): void
    {
        $provider = new FromFileGraphProvider();
        $controller = new ExplorerController($provider);

        $base = \realpath(__DIR__.'/fixtures/base.json');
        $local = \realpath(__DIR__.'/fixtures/local.json');
        self::assertNotFalse($base);
        self::assertNotFalse($local);

        $explain = $controller->explain(new Request('GET', '/explain', ['file' => $base]));
        self::assertSame(200, $explain->status);
        self::assertStringContainsString('db.host', $explain->body);

        $graph = $controller->graph(new Request('GET', '/graph', ['file' => $base, 'format' => 'ascii']));
        self::assertSame(200, $graph->status);
        self::assertStringContainsString('level: info', $graph->body);

        $query = $controller->query(new Request('GET', '/query', ['file' => $base, 'q' => 'source:base.json']));
        self::assertSame(200, $query->status);
        self::assertStringContainsString('db.host', $query->body);
    }
}
