# configure-it-testing

Testing utilities for **ConfigureIt** consumers: fakes and a
`ConfigurationAssertions` trait for PHPUnit.

Everything depends only on `it-pack/configure-it` (and `nette/schema`, used by
`assertConfigValid`). It is meant to be installed as a `require-dev` companion.

---

## Install

```bash
composer require --dev it-pack/configure-it-testing
```

## Fakes

### `FakeConfigurationSource` — a `ConfigurationSourceInterface`

```php
use ItPack\ConfigureItTesting\Fake\FakeConfigurationSource;

$source = FakeConfigurationSource::fromArray([
    'config/app.json' => 'json',
    'config/local.yaml' => 'yaml',
]);

foreach ($source->discover() as $resource) {
    echo $resource->uri();        // config/app.json
    echo $resource->format()->value; // json
}
```

### `FakeLoader` — a configurable `ConfigurationLoaderInterface`

```php
use ItPack\ConfigureItTesting\Fake\FakeLoader;
use ItPack\ConfigureIt\ValueObject\Format;

$loader = new FakeLoader();                 // parses JSON by default
$loader->supports(Format::JSON);            // true
$document = $loader->load('{"name":"it"}'); // ConfigurationDocument

// custom parser + restricted formats:
$custom = new FakeLoader(
    static fn (string $raw): array => ['raw' => $raw],
    Format::ENV,
);
```

### `SpyProcessor` — a `ConfigurationProcessorInterface` that records calls

```php
use ItPack\ConfigureItTesting\Fake\SpyProcessor;

$spy = new SpyProcessor();
$spy->process($graph);

$spy->timesCalled();   // 1
$spy->lastGraph();     // the graph it saw
```

### `InMemoryGraph` — a mutable `ConfigurationGraphInterface` builder

```php
use ItPack\ConfigureItTesting\Fake\InMemoryGraph;
use ItPack\ConfigureItTesting\Node\FakeNode;

$graph = new InMemoryGraph();
$graph->put(FakeNode::create('app.name', 'it'));
$graph->put(FakeNode::sensitive('db.password', 'secret'));

$graph->has('app.name');        // true
$graph->toArray();              // ['app' => ['name' => 'it', 'password' => 'secret']]
$immutable = $graph->build();   // ItPack\ConfigureIt\Core\ConfigurationGraph
```

### `FakeNode` — build `ConfigurationNode`s with provenance

```php
use ItPack\ConfigureItTesting\Node\FakeNode;
use ItPack\ConfigureIt\ValueObject\Format;

FakeNode::create('app.name', 'it', 'base', 0);
FakeNode::sensitive('db.password', 'secret');                 // isSensitive() === true
FakeNode::override('app.debug', false, 'local', 20, 'base.json'); // overridden by base.json
FakeNode::from('cache.ttl', 60, 'redis.php', 5, 'file', 'yaml-loader', Format::YAML);
```

## `ConfigurationAssertions` trait

`use` it in a PHPUnit test case to assert on a resolved graph's provenance and
values:

```php
use ItPack\ConfigureItTesting\Assertions\ConfigurationAssertions;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    use ConfigurationAssertions;

    public function testResolved(): void
    {
        $graph = /* ...InMemoryGraph... */;

        $this->assertGraphHasKey($graph, 'app.name');
        $this->assertGraphNotHasKey($graph, 'missing');
        $this->assertGraphEquals($graph, ['app' => ['name' => 'it']]);
        $this->assertKeyEquals($graph, 'app.name', 'it');
        $this->assertKeyResolvedFrom($graph, 'app.name', 'programmatic');
        $this->assertKeyOverriddenBy($graph, 'app.debug', 'base.json');
        $this->assertKeyIsSensitive($graph, 'db.password', true);
        $this->assertKeyFormat($graph, 'cache.ttl', Format::YAML);
        $this->assertKeyAdapter($graph, 'cache.ttl', 'yaml-loader');
        $this->assertKeyPriority($graph, 'app.debug', 20);
        $this->assertKeyHasProcessor($graph, 'email', 'freeze');

        // Validate sample input against an Expect definition:
        $this->assertConfigValid(
            Expect::structure(['name' => Expect::string()]),
            ['name' => 'it'],
        );
    }
}
```

### Available assertions

| Assertion | Checks |
|-----------|--------|
| `assertGraphHasKey($graph, $key)` | key present |
| `assertGraphNotHasKey($graph, $key)` | key absent |
| `assertGraphEquals($graph, $expected)` | whole tree equals |
| `assertKeyEquals($graph, $key, $expected)` | resolved value |
| `assertKeyResolvedFrom($graph, $key, $sourceKind)` | provenance source kind |
| `assertKeyOverriddenBy($graph, $key, $overriddenSource)` | override + shadowed source |
| `assertKeyIsSensitive($graph, $key, $expected = true)` | `isSensitive()` |
| `assertKeyFormat($graph, $key, Format)` | provenance format |
| `assertKeyAdapter($graph, $key, $adapter)` | provenance adapter |
| `assertKeyPriority($graph, $key, $priority)` | provenance priority |
| `assertKeyHasProcessor($graph, $key, $stage)` | processor in trail |
| `assertConfigValid($schema, $data)` | Nette schema accepts `$data` |

## Development

```bash
composer install
vendor/bin/phpstan analyse     # level 9
vendor/bin/php-cs-fixer fix
vendor/bin/phpunit
```

## License

MIT
