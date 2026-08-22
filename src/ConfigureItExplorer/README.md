# configure-it-explorer

A small, **read-only** web explorer + graph query API for [ConfigureIt](https://github.com/it-pack/configure-it).

It is a thin, framework-free adapter over the Core diagnostics services
(`ConfigurationInspector`, `GraphVisualizer`, `ConfigurationDiffer`) and the
graph query API (`GraphQuery`), so it behaves exactly like `bin/configure-it`
— but in a browser, with a JSON API.

> This tool renders configuration read-only. Secret values are masked to `****`
> by the Core redacting renderer before they reach the view. The built-in PHP
> server has no authentication — run it on `127.0.0.1` and only as long as
> needed.

---

## Install

```bash
composer require --dev it-pack/configure-it-explorer
```

The package pulls in `it-pack/configure-it` as its only runtime dependency.

## Run

```bash
./bin/configure-it-explorer
# ConfigureIt Explorer running at http://127.0.0.1:8080
```

Override the bind address with `CONFIGURE_IT_EXPLORER_HOST`:

```bash
CONFIGURE_IT_EXPLORER_HOST=127.0.0.1:9000 ./bin/configure-it-explorer
```

Open the printed URL. Every action takes a `file` (path to a supported config
file: `json`, `yaml`, `toml`, `neon`, `ini`, `xml`, `env`, `php`, `md`/`markdown`).

## Routes

| Route | Purpose |
|-------|---------|
| `/` | Dashboard with forms + the query cheat-sheet. |
| `/explain?file=<path>&key=<optional>` | Per-key provenance table, or a single key's detail. |
| `/diff?base=<path>&other=<path>` | Semantic diff with provenance for every changed key. |
| `/graph?file=<path>&format=dot\|ascii` | Graphviz DOT or a plain-text ASCII tree. |
| `/query?file=<path>&q=<expression>` | Safe provenance query (see below). |

Every route accepts `?format=json` (or `Accept: application/json`) for a
machine-readable response, turning the explorer into an HTTP query API.

## Graph query API

The query language is tiny and safe (enumerate-driven — it can never execute
code). Each predicate narrows the resolved graph to the matching nodes:

| Query | Meaning |
|-------|---------|
| `source:VALUE` | keys whose source matches `VALUE` (sourceKind or resource basename) |
| `overridden-by:VALUE` | keys that overrode a node coming from `VALUE` |
| `sensitive` | keys flagged as sensitive / secrets |
| `adapter:VALUE` | keys loaded by the adapter whose short class matches `VALUE` |
| `layer:VALUE` | keys originating from layer `VALUE` |
| `format:VALUE` | keys of format `VALUE` (e.g. `json`, `yaml`, `toml`) |
| `trail:VALUE` | keys whose processor trail contains `VALUE` |
| `changed` | keys that differ between `base` and `other` (needs both params) |

A bare `VALUE` with no predicate is treated as `source:VALUE`:

```
overridden-by:local.json     → keys that overrode a node from local.json
source:vault                → keys sourced from a vault
changed (base + other)      → keys that differ between two files
```

### Examples

```bash
# All keys overridden by local.json
curl 'http://127.0.0.1:8080/query?file=config/local.json&q=overridden-by:local.json&format=json'

# Same question, as a human table in the browser:
#   /query?file=config/local.json&q=overridden-by:local.json
```

## Programmatic use

The HTTP layer is fully decoupled from the server, so it is unit-testable and
reusable:

```php
use ItPack\ConfigureItExplorer\ExplorerApplication;
use ItPack\ConfigureItExplorer\Server\Request;

$app = ExplorerApplication::bootstrap();           // reads files via GraphFromFile
$response = $app->handle(new Request('GET', '/query', [
    'file' => 'config/local.json',
    'q'    => 'source:local.json',
]));

echo $response->status;   // 200
echo $response->body;     // HTML or JSON
```

The graph query service works standalone on any `ConfigurationGraphInterface`:

```php
use ItPack\ConfigureItExplorer\Query\GraphQuery;

$keys = (new GraphQuery())->run($graph, 'overridden-by:base.json');
$changed = (new GraphQuery())->changes($baseGraph, $otherGraph);
```

## Architecture

```
Server\Request  ──►  ExplorerApplication  ──►  Server\Router  ──►  Http\ExplorerController
                                                                   │
        Graph\FromFileGraphProvider (GraphFromFile) ───────────────┤
        Query\GraphQuery (QueryParser → QueryCriterion) ───────────┤
        CLI\ConfigurationInspector / GraphVisualizer / ConfigurationDiffer
                                                                   │
                                                              View\HtmlRenderer
```

- **No framework dependency** — the only runtime cost is `it-pack/configure-it`.
- **Read-only** — it never writes or mutates configuration.
- **Masked secrets** — sensitive values are `****` everywhere, by construction.

## Development

```bash
composer install
vendor/bin/phpstan analyse     # level 9
vendor/bin/php-cs-fixer fix
vendor/bin/phpunit
```

## License

MIT
