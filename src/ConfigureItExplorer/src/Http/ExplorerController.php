<?php

declare(strict_types=1);

namespace ItPack\ConfigureItExplorer\Http;

use ItPack\ConfigureIt\CLI\ConfigurationInspector;
use ItPack\ConfigureIt\CLI\GraphVisualizer;
use ItPack\ConfigureIt\Contract\ConfigurationGraphInterface;
use ItPack\ConfigureIt\Core\ConfigurationDiffer;
use ItPack\ConfigureIt\Core\RedactingRenderer;
use ItPack\ConfigureItExplorer\Graph\GraphProviderInterface;
use ItPack\ConfigureItExplorer\Query\GraphQuery;
use ItPack\ConfigureItExplorer\Query\QueryPredicate;
use ItPack\ConfigureItExplorer\Server\Request;
use ItPack\ConfigureItExplorer\Server\Response;
use ItPack\ConfigureItExplorer\View\HtmlRenderer;
use Throwable;

/**
 * The read-only web controller (Epic E9.2 — T9.2.1 / T9.2.2).
 *
 * Each action is a thin adapter over Core diagnostics services:
 *   - `explain`  → ConfigurationInspector (provenance table / per-key detail)
 *   - `diff`     → ConfigurationDiffer (semantic diff with provenance)
 *   - `graph`    → GraphVisualizer (DOT / ASCII)
 *   - `query`    → GraphQuery (safe provenance query API over the graph)
 *
 * Every action supports `?format=json` (or `Accept: application/json`) for a
 * machine-readable response, so the explorer doubles as an HTTP query API.
 */
final class ExplorerController
{
    public function __construct(
        private GraphProviderInterface $graphs,
        private ConfigurationInspector $inspector = new ConfigurationInspector(),
        private GraphVisualizer $visualizer = new GraphVisualizer(),
        private ConfigurationDiffer $differ = new ConfigurationDiffer(new RedactingRenderer()),
        private GraphQuery $query = new GraphQuery(),
        private HtmlRenderer $html = new HtmlRenderer(),
        private RedactingRenderer $renderer = new RedactingRenderer(),
    ) {
    }

    public function dashboard(Request $request): Response
    {
        $content = $this->html->form(
            '/explain',
            ['file' => 'Config file path', 'key' => 'Key (optional)'],
            'Explain',
        )
        .$this->html->form('/diff', ['base' => 'Base file', 'other' => 'Other file'], 'Diff')
        .$this->html->form('/graph', ['file' => 'Config file path', 'format' => 'dot | ascii'], 'Graph')
        .$this->html->form('/query', ['file' => 'Config file path', 'q' => 'e.g. overridden-by:local.json'], 'Query')
        .$this->cheatSheet();

        return Response::html($this->html->page('Dashboard', $content, $this->nav()));
    }

    public function explain(Request $request): Response
    {
        $file = $request->get('file');

        if (null === $file) {
            return Response::html($this->html->page(
                'Explain',
                $this->html->form('/explain', ['file' => 'Config file path', 'key' => 'Key (optional)'], 'Explain'),
                $this->nav(),
            ));
        }

        $graph = $this->load($file);

        if ($graph instanceof Response) {
            return $graph;
        }

        $key = $request->get('key');

        if ($request->wantsJson()) {
            $nodes = [];

            foreach ($graph->nodes() as $nodeKey => $node) {
                $provenance = $node->provenance();
                $nodes[$nodeKey] = [
                    'value' => $this->renderer->render($node->value(), $provenance->isSensitive()),
                    'sensitive' => $provenance->isSensitive(),
                    'provenance' => $provenance->toArray(),
                ];
            }

            return Response::json(['file' => $file, 'key' => $key, 'nodes' => $nodes]);
        }

        $text = $this->inspector->explain($graph, $key);
        $content = $this->html->pre(\explode("\n", \rtrim($text, "\n")));

        return Response::html($this->html->page(null !== $key ? 'Explain '.$key : 'Explain', $content, $this->nav()));
    }

    public function diff(Request $request): Response
    {
        $base = $request->get('base');
        $other = $request->get('other');

        if (null === $base || null === $other) {
            return Response::html($this->html->page(
                'Diff',
                $this->html->form('/diff', ['base' => 'Base file', 'other' => 'Other file'], 'Diff'),
                $this->nav(),
            ));
        }

        $graphBase = $this->load($base);

        if ($graphBase instanceof Response) {
            return $graphBase;
        }

        $graphOther = $this->load($other);

        if ($graphOther instanceof Response) {
            return $graphOther;
        }

        $diff = $this->differ->diff($graphBase, $graphOther);

        if ($request->wantsJson()) {
            return Response::json(['base' => $base, 'other' => $other, 'diff' => $diff->toArray()]);
        }

        $content = $this->html->pre(\explode("\n", $diff->summary()));

        return Response::html($this->html->page('Diff', $content, $this->nav()));
    }

    public function graph(Request $request): Response
    {
        $file = $request->get('file');

        if (null === $file) {
            return Response::html($this->html->page(
                'Graph',
                $this->html->form('/graph', ['file' => 'Config file path', 'format' => 'dot | ascii'], 'Graph'),
                $this->nav(),
            ));
        }

        $graph = $this->load($file);

        if ($graph instanceof Response) {
            return $graph;
        }

        $format = $request->get('format', 'dot');
        $isAscii = 'ascii' === $format;
        $text = $isAscii ? $this->visualizer->toAscii($graph) : $this->visualizer->toDot($graph);

        if ($request->wantsJson()) {
            return Response::json([
                'file' => $file,
                'format' => $isAscii ? 'ascii' : 'dot',
                'graph' => $text,
            ]);
        }

        $hint = $isAscii ? '' : '<p class="hint">Render elsewhere with <code>dot -Tsvg file.dot &gt; graph.svg</code></p>';
        $content = $hint.$this->html->pre(\explode("\n", \rtrim($text, "\n")));

        return Response::html($this->html->page('Graph', $content, $this->nav()));
    }

    public function query(Request $request): Response
    {
        $expression = $request->get('q');
        $file = $request->get('file');
        $base = $request->get('base');
        $other = $request->get('other');

        if (null === $expression) {
            return Response::html($this->html->page(
                'Query',
                $this->html->form('/query', ['file' => 'Config file path', 'q' => 'e.g. overridden-by:local.json'], 'Query')
                .$this->cheatSheet(),
                $this->nav(),
            ));
        }

        $json = $request->wantsJson();

        if ('changed' === $expression) {
            if (null === $base || null === $other) {
                return Response::html(
                    $this->html->page('Query', $this->html->error(400, 'The "changed" query requires both base and other files.'), $this->nav()),
                    400,
                );
            }

            $graphBase = $this->load($base);

            if ($graphBase instanceof Response) {
                return $graphBase;
            }

            $graphOther = $this->load($other);

            if ($graphOther instanceof Response) {
                return $graphOther;
            }

            return $this->queryResponse($expression, $this->query->changes($graphBase, $graphOther), $json, ['base' => $base, 'other' => $other]);
        }

        if (null === $file) {
            return Response::html(
                $this->html->page('Query', $this->html->error(400, 'A graph query needs a file parameter.'), $this->nav()),
                400,
            );
        }

        $graph = $this->load($file);

        if ($graph instanceof Response) {
            return $graph;
        }

        return $this->queryResponse($expression, $this->query->run($graph, $expression), $json, ['file' => $file]);
    }

    /**
     * @param list<string>          $keys
     * @param array<string, string> $context
     */
    private function queryResponse(string $expression, array $keys, bool $json, array $context): Response
    {
        if ($json) {
            return Response::json([
                'query' => $expression,
                'count' => \count($keys),
                'keys' => $keys,
                'context' => $context,
            ]);
        }

        /** @var list<list<string>> $rows */
        $rows = [];

        foreach ($keys as $key) {
            $rows[] = [$key];
        }

        $body = $this->html->table(['KEY'], $rows);

        return Response::html($this->html->page('Query: '.$expression, $body, $this->nav()));
    }

    private function load(string $path): ConfigurationGraphInterface|Response
    {
        try {
            return $this->graphs->build($path);
        } catch (Throwable $e) {
            return Response::html($this->html->error(400, $e->getMessage()), 400);
        }
    }

    /**
     * @return list<array{label: string, href: string}>
     */
    private function nav(): array
    {
        return [
            ['label' => 'Home', 'href' => '/'],
            ['label' => 'Explain', 'href' => '/explain'],
            ['label' => 'Diff', 'href' => '/diff'],
            ['label' => 'Graph', 'href' => '/graph'],
            ['label' => 'Query', 'href' => '/query'],
        ];
    }

    private function cheatSheet(): string
    {
        $rows = [];

        foreach (QueryPredicate::cases() as $predicate) {
            $rows[] = [
                $predicate->value.($predicate->hasValue() ? ':VALUE' : ''),
                $predicate->description(),
            ];
        }

        return '<div class="card"><h2>Query grammar</h2>'
            .$this->html->table(['QUERY', 'MEANING'], $rows)
            .'<p class="hint">Also: <code>changed</code> (needs <code>base</code> + <code>other</code>) lists keys that differ between two files. Append <code>&amp;format=json</code> for machine output.</p></div>';
    }
}
