# configure-it-schema-docs

Emit **Markdown reference docs** and a **JSON-Schema** document from a
`Nette\Schema\Expect` definition.

It is a thin, framework-free adapter over ConfigureIt's schema layer: it
reflects the `Expect` graph (via `ReflectionProperty`, the same boundary the
Core already crosses in `NativeConfigurationSchema::scalarTypes()`) and turns it
into a normalized `SchemaNode` tree, which the renderers then serialize. No
Nette types ever leak into the output.

> Only `it-pack/configure-it` and `nette/schema` are runtime dependencies.

---

## Install

```bash
composer require --dev it-pack/configure-it-schema-docs
```

## CLI

Point it at a PHP file that **returns** an `Expect` definition:

```bash
# Markdown (default)
php bin/configure-it-schema-docs \
  --file=config/schema.php \
  --title="App Config" > docs/config.md

# JSON-Schema (draft 2020-12)
php bin/configure-it-schema-docs \
  --file=config/schema.php \
  --format=json-schema > config.schema.json
```

`config/schema.php`:

```php
<?php
use Nette\Schema\Expect;
return Expect::structure([
    'app' => Expect::structure([
        'name' => Expect::string()->default('configure-it'),
        'port' => Expect::int()->min(1)->max(65535)->default(8080),
        'env'  => Expect::anyOf('dev', 'staging', 'prod')->default('dev'),
    ]),
]);
```

## Programmatic use

```php
use ItPack\ConfigureItSchemaDocs\SchemaDocGenerator;
use Nette\Schema\Expect;

$docs = SchemaDocGenerator::fromExpect(
    Expect::structure([/* ... */]),
    'App Config',
);

echo $docs->toMarkdown();              // human-readable reference
echo $docs->toJsonSchemaJson();        // JSON-Schema (draft 2020-12)
```

The introspector also works standalone:

```php
use ItPack\ConfigureItSchemaDocs\SchemaIntrospector;
use ItPack\ConfigureItSchemaDocs\Model\SchemaNode;

/** @var SchemaNode $tree */
$tree = (new SchemaIntrospector())->introspect($expectDefinition);
```

## What it captures

| `Expect` construct | Markdown | JSON-Schema |
|--------------------|----------|-------------|
| `structure([...])` | nested fields | `object` + `properties` + `required` |
| `string`/`int`/`float`/`bool` | typed field | `type` |
| `arrayOf(T)` | `map<string, T>` | `additionalProperties` |
| `listOf(T)` | `array<T>` | `items` |
| `anyOf('a','b')` | `enum` | `enum` |
| `anyOf(T1, T2)` | `union` | `anyOf` |
| `->nullable()` | `_(nullable)_` | `type: [<t>, 'null']` |
| `->default(v)` | `default: …` | `default` |
| `->required()` | `_(required)_` | `required` list |
| `->deprecated()` | `_(deprecated)_` | `deprecated: true` |
| `->min()`/`->max()` | `range: …` | `minimum`/`maximum` (or `minLength`/`maxItems`) |
| `->pattern()` | `pattern: …` | `pattern` |

Recursive definitions are guarded against infinite descent (a cycle renders as
an opaque `mixed` node).

## Development

```bash
composer install
vendor/bin/phpstan analyse     # level 9
vendor/bin/php-cs-fixer fix
vendor/bin/phpunit
```

## License

MIT
