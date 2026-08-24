# Usage Guide

Task-oriented recipes for DeriveIt, followed by the full public API reference.

## Installation

```bash
composer require it-pack/derive-it
```

Requires PHP ≥ 8.4 and `nette/php-generator` ^4.2 (a transitive dependency).

## Choosing a mode

| You want… | Use |
|-----------|-----|
| One interface for a class | `DeriveIt::derive()` |
| The `InterfaceType` object, not source | `DeriveIt::deriveInterface()` |
| One interface per trait (segregation) | `DeriveIt::derivePerTrait()` / `deriveTraitInterfaces()` |
| A family with cross references rewritten | `DeriveIt::deriveFamily()` |
| An interface **and** a delegating adapter | `DeriveIt::deriveAdapter()` |

## Recipe: derive a flat interface

```php
use ItPack\DeriveIt\DeriveIt;

$source = DeriveIt::derive(\Some\ThirdParty\Widget::class);

// $source is a complete, renderable PHP interface in the adaptee's namespace,
// declaring only the public, non-static, non-magic, non-constructor methods.
```

## Recipe: get the InterfaceType object

```php
/** @var \Nette\PhpGenerator\InterfaceType $interface */
$interface = DeriveIt::deriveInterface(\Some\ThirdParty\Widget::class);

$interface->hasMethod('doSomething'); // true
```

## Recipe: keep static methods

```php
$source = DeriveIt::derive(\Some\ThirdParty\Widget::class, includeStatic: true);
```

(Static interface methods require PHP ≥ 8.4 — DeriveIt's minimum target.)

## Recipe: per-trait interfaces

```php
use ItPack\DeriveIt\DeriveIt;

$mainSource = DeriveIt::derivePerTrait(\App\UsesTraits::class);

/** @var array<string, \Nette\PhpGenerator\InterfaceType> $traits */
$traits = DeriveIt::deriveTraitInterfaces(\App\UsesTraits::class);
// $traits['TraitA']->hasMethod('traitAMethod');
```

When a method name exists in multiple traits, the first trait in
`getTraitsRecursively()` order owns it; the others drop it.

## Recipe: family derivation with type substitution

Auto-detected cross references:

```php
use ItPack\DeriveIt\DeriveIt;

$sources = DeriveIt::deriveFamily([
    \App\Node::class,
    \App\Tree::class,
]);

// NodeInterface::getNext(): ?NodeInterface
// TreeInterface::getRoot(): NodeInterface
```

Explicit substitution for types outside the family:

```php
use ItPack\DeriveIt\DeriveIt;
use ItPack\DeriveIt\Generator\DeriveConfig;

$config = new DeriveConfig(typeSubstitutionMap: [
    \App\Node::class => \App\NodeInterface::class,
]);

$source = DeriveIt::deriveAdapter(\App\Tree::class, config: $config);
```

## Recipe: generate an adapter

```php
use ItPack\DeriveIt\DeriveIt;

$source = DeriveIt::deriveAdapter(\App\Tree::class);
// Defines both App\TreeInterface and App\TreeAdapter in one self-contained file.
```

Wrap returned derived types so the port stays concrete-free:

```php
$source = DeriveIt::deriveAdapter(
    \App\Tree::class,
    returnWrapperMap: [
        \App\NodeInterface::class => \App\NodeAdapter::class,
    ],
);
```

## Recipe: full configuration

```php
use ItPack\DeriveIt\DeriveIt;
use ItPack\DeriveIt\Generator\DeriveConfig;
use ItPack\DeriveIt\Generator\InterfaceDeriver;

$config = new DeriveConfig(
    visibility: DeriveConfig::VISIBILITY_PROTECTED, // public + protected
    includeStatic: true,
    includeMagic: true,
    dropConstruct: true,
    dropDestruct: true,
    typeSubstitutionMap: [
        \App\Node::class => \App\NodeInterface::class,
    ],
);

$source = (new InterfaceDeriver(config: $config))
    ->deriveSource(\App\Widget::class);
```

`DeriveConfig` is immutable; `withIncludeStatic(bool)` returns a copy with that
flag toggled.

## Recipe: custom graph traversal

Replace a resolver if you need non-default walking behaviour. Resolvers are
instance-injected — there is no mutable static setter:

```php
use App\MyCustomClassResolver;
use ItPack\DeriveIt\Reflection\ClassReflection;

$reflection = ClassReflection::create(
    \App\Widget::class,
    classResolver: new MyCustomClassResolver(),
);
$reflection->getClassesRecursively();
```

## Command-line interface

`DeriveIt` ships an ad-hoc CLI driver (`ItPack\DeriveIt\Cli\DeriveCommand`) that
derives a class or file to stdout or to a path / directory in any of the
supported modes (flat, per-trait, adapter, family, family-adapter). Run it with
no arguments or `--help` for the full option list:

```bash
derive --mode=family 'App\Payments\Gateway' --out=ports/
```

> **Trust model (F13 / R.13):** the CLI *autoloads the class names you pass* (and
> token-scans any file targets). Only run it against code you trust — do not point
> it at untrusted class names or files.
>
> `--base-dir` is a lexical guard: it does not resolve symlinks in the target
> path. Point `--out` only at paths you control.

Pass `--verbose` to stream pipeline diagnostics (activities, measurements,
occurrences, warnings) to **stderr** as `derive: …` lines; stdout stays
byte-identical to a non-verbose run.

To attach derivations to a [RunIt](../EXECUTION.md) execution instead of
reading them as text, see
[integrations/runit.md](../integrations/runit.md).

## API reference — `DeriveIt` (facade)

All methods accept `ReflectionClass|class-string` for the target class unless
noted.

### `derive($class, bool $includeStatic = false): string`
Renders the flat derived interface source. `$includeStatic` retains static
methods.

### `deriveInterface($class, bool $includeStatic = false): InterfaceType`
Same as `derive()` but returns the `Nette\PhpGenerator\InterfaceType` object.

### `derivePerTrait($class): string`
Renders the composed main interface (which `extends` the derived trait
interfaces).

### `deriveTraitInterfaces($class): array<string, InterfaceType>`
Returns the individual trait interfaces, keyed by trait short name.

### `deriveFamily(array $classes): array<class-string, string>`
Derives a family together; returns rendered interface source keyed by class FQCN.
Cross-referencing types are auto-rewritten to derived interface FQCNs.

### `deriveAdapter($class, array $returnWrapperMap = [], DeriveConfig $config = new DeriveConfig()): string`
Renders the derived interface **and** a delegating adapter in one self-contained
file. `$returnWrapperMap` maps derived-interface FQCN ⇒ adapter class FQCN.

## API reference — `DeriveConfig`

Immutable value object. Constructor parameters (all optional, with sane
defaults):

| Parameter | Type | Default |
|-----------|------|---------|
| `visibility` | `string` (`VISIBILITY_PUBLIC` \| `VISIBILITY_PROTECTED`) | `VISIBILITY_PUBLIC` |
| `includeStatic` | `bool` | `false` |
| `includeMagic` | `bool` | `false` |
| `dropConstruct` | `bool` | `true` |
| `dropDestruct` | `bool` | `true` |
| `typeSubstitutionMap` | `array<string, string>` (class FQCN ⇒ interface FQCN) | `[]` |

Methods: `visibilityMask(): int` (bitmask for extraction),
`withIncludeStatic(bool): self` (immutable copy).

## FAQ / gotchas

- **Why is my interface empty?** The class may only have a constructor (or only
  static/magic methods that were dropped). Constructors can't live in an
  interface; opt into static/magic with `DeriveConfig`.
- **Why do `self`/`static`/`parent` render as the literal class name?** Nette
  resolves them to the concrete class. For a derived family, provide an explicit
  `typeSubstitutionMap` entry.
- **Why does the per-trait main interface reference `\TraitXInterface`?** The
  derived trait interfaces carry no namespace, so the composed interface renders
  them by global name. Functionally valid; a future revision may namespace them.
- **Why FQCNs internally?** So in-memory objects and rendered source agree, and
  Nette simplifies FQCNs to short names on render. See
  [ADR-0004](../architecture/decisions/ADR-0004-type-substitution.md).

## Where to go next

- Run the [`examples/`](../../examples/README.md) — one script per feature.
- Read the [Architecture](architecture.md) page for how it fits together.
- Browse the [design decisions](../architecture/decisions/).
