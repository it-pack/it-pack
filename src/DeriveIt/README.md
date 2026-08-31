# derive-it

> Part of the **It-Pack** family.

Derives interfaces (and adapters) from third-party or legacy PHP classes — without
hand-writing reflection code, and without leaking the adaptee's own contracts into
your codebase.

## The problem

You want to depend on a port (an interface you own), not on someone else's concrete
class or someone else's interface. But hand-deriving a correct interface from a
real-world class — with its full inheritance chain, its traits, its
already-implemented interfaces, its constructor promotion, its union/intersection
types, its enums — is tedious and error-prone to write by hand, and brittle to
maintain as the adaptee changes.

`derive-it` automates the derivation. Point it at a class (or a package, or a
related family of classes), and it walks the full class/trait/interface graph and
produces a clean, self-contained interface — one that never `extends` or references
anything from the adaptee's own type hierarchy.

## What it does

- Walks the full inheritance chain (parent classes), trait usage (including nested
  traits), and already-implemented interfaces of a target class.
- Flattens the public method surface into a self-contained interface — no leaking
  of the adaptee's own interfaces or class hierarchy into the derived contract.
- Filters by visibility (public surface only, or public + protected), and handles
  static vs. instance methods deliberately (static dropped by default, opt-in on
  PHP ≥ 8.4 which permits static interface methods).
- Correctly drops constructors and destructors (an interface can't declare a
  constructor, and you generally don't want callers constructing an adaptee
  through your port anyway).
- Preserves the real type grammar via `nette/php-generator`'s reflection-to-source
  round-trip: union/intersection types, nullable types, variadics, by-ref params,
  readonly, constructor promotion, and enums as parameter/return types.
- Optionally derives **one interface per trait boundary** instead of one flat
  interface — Interface Segregation applied to the derivation tool itself.
- Optionally rewrites cross-referencing types across a **family** of derived
  classes (e.g. `Node` ⇄ `Tree`) so the derived interfaces reference each other
  rather than the native types.
- Optionally generates a **delegating adapter** that implements the derived
  interface and wraps a concrete adaptee instance, keeping the port free of
  concrete types.

The leakage invariant is enforced everywhere: a derived interface has an empty
`getExtends()` in flat mode (or only derived trait interfaces in per-trait mode),
and emits no `use`/FQCN leaking the adaptee hierarchy.

## Installation

```bash
composer require it-pack/derive-it
```

## Usage

### Flat interface derivation

```php
use ItPack\DeriveIt\DeriveIt;

$source = DeriveIt::derive(SomeThirdPartyClass::class);

// $source is a complete, renderable PHP interface in the adaptee's namespace,
// declaring only the public, non-static, non-magic, non-constructor methods.
```

If you want the `Nette\PhpGenerator\InterfaceType` object instead of rendered
source, use `deriveInterface()`:

```php
/** @var \Nette\PhpGenerator\InterfaceType $interface */
$interface = DeriveIt::deriveInterface(SomeThirdPartyClass::class);

$interface->hasMethod('doSomething'); // true
```

### Configuration

Every filtering decision is an explicit `DeriveConfig` flag with a sane default, so
behaviour is testable and never an accidental opinion.

```php
use ItPack\DeriveIt\DeriveIt;
use ItPack\DeriveIt\Generator\DeriveConfig;

$config = new DeriveConfig(
    visibility: DeriveConfig::VISIBILITY_PUBLIC, // or VISIBILITY_PROTECTED
    includeStatic: false,  // retain static methods (PHP >= 8.4)
    includeMagic: false,   // retain __* magic methods
    dropConstruct: true,   // drop __construct
    dropDestruct: true,   // drop __destruct
);

$source = DeriveIt::derive(SomeThirdPartyClass::class, includeStatic: true);
```

`DeriveIt::derive()` and `deriveInterface()` accept an `$includeStatic` shortcut;
for the full set of flags, build a `DeriveConfig` and pass it to the adapter
deriver below, or construct a `InterfaceDeriver` directly.

### Per-trait interfaces

Interface Segregation: one interface per used trait, with the main interface
extending exactly those derived trait interfaces.

```php
use ItPack\DeriveIt\DeriveIt;

$mainSource = DeriveIt::derivePerTrait(SomeClassUsingTraits::class);

// Or get the individual trait interfaces as objects, keyed by trait short name:
/** @var array<string, \Nette\PhpGenerator\InterfaceType> $traitInterfaces */
$traitInterfaces = DeriveIt::deriveTraitInterfaces(SomeClassUsingTraits::class);

$traitInterfaces['TraitA']->hasMethod('traitAMethod'); // true
```

When a method name appears in multiple traits, the first trait in the resolved
order owns it; the others drop it — so no duplicate declaration can occur across
the extended interfaces. Traits with no public methods are skipped.

### Family derivation (type substitution)

Derive a set of related classes together so that cross-referencing return/parameter
types are rewritten to the corresponding derived interface.

```php
use ItPack\DeriveIt\DeriveIt;
use ItPack\DeriveIt\Generator\DeriveConfig;

$map = [
    \App\Node::class => \App\NodeInterface::class,
    \App\Tree::class => \App\TreeInterface::class,
];

$config = new DeriveConfig(typeSubstitutionMap: $map);

/** @var array<class-string, string> $sources */
$sources = DeriveIt::deriveFamily([\App\Node::class, \App\Tree::class]);

// NodeInterface::getNext(): ?NodeInterface  (was ?Node)
// TreeInterface::add(NodeInterface $node): void  (was add(Node $node))
```

Internally, types are stored as FQCNs (the canonical form) and Nette simplifies
them to clean short names in the rendered source.

### Adapter generation

Derive the self-contained interface and a delegating adapter that implements it and
wraps a concrete adaptee instance. The adapter lives in the adaptee's own namespace
so it is self-contained.

```php
use ItPack\DeriveIt\DeriveIt;

$source = DeriveIt::deriveAdapter(SomeThirdPartyClass::class);

// $source defines both `SomeThirdPartyClassInterface` and
// `SomeThirdPartyClassAdapter` in one file. The adapter holds the adaptee via
// constructor injection and forwards every interface method to it.
```

When a returned value is itself a derived type, register an adapter class to wrap
it so the port stays free of concrete types:

```php
$source = DeriveIt::deriveAdapter(
    \App\Tree::class,
    returnWrapperMap: [
        \App\NodeInterface::class => \App\NodeAdapter::class,
    ],
);
// TreeAdapter::add(NodeInterface $node) { $this->adaptee->add(new NodeAdapter($node)); }
```

## How it works

- **Graph walking** (`src/Reflection`, `src/Resolver`) — native PHP `Reflection`
  walks the parent/interface/trait graph recursively. Resolvers are swappable via
  `RecursiveClassResolverInterface`, `RecursiveInterfaceResolverInterface`, and
  `RecursiveTraitResolverInterface`.
- **Generation** (`src/Generator`) — `nette/php-generator` (specifically
  `Factory::fromMethodReflection()`) is the single source of truth for
  parameter/return typing. `derive-it` adds the *derivation* logic on top: deciding
  what belongs in the interface, what doesn't, and how it should be shaped.

## Documentation

- **User Guide** (`docs/guide/`) — [overview & concepts](docs/guide/index.md),
  [tutorial](docs/guide/tutorial.md), [cookbook](docs/guide/cookbook.md),
  [features](docs/guide/features.md), [architecture](docs/guide/architecture.md),
  and [usage / API reference](docs/guide/usage.md).
- **Runtime architecture diagram** — see [`docs/diagram/derive-it-architecture.html`](docs/diagram/derive-it-architecture.html)
  (spec: [`docs/diagram/derive-it-architecture.json`](docs/diagram/derive-it-architecture.json)).
  One glance: the eleven core runtime components, the primary derivation path,
  external dependencies, and the ownership / leakage-trust boundary.
- **Runnable examples** (`examples/`) — one script per feature; see
  [`examples/README.md`](examples/README.md).
- **Design decisions** — ADRs under `docs/architecture/decisions/`.

## Status

Production-ready through **Phase 7** (docs, ADRs, release). All quality gates are green:

| Gate | Command | Result |
|------|---------|--------|
| Unit tests | `composer test:unit` | 113 tests, 182 assertions |
| Coding standard | `composer cs:check` | 0 files |
| Static analysis | `composer analyse` | no errors |
| Architecture | `composer architecture` (deptrac) | 0 violations |
| Mutation testing | `composer infection` | MSI ≥ 80% |

The dogfooding target — deriving an adapter for `nette/php-generator` itself — is
exercised by the Functional integration test suite.

## Roadmap

- **ADRs** — design decisions documented under `docs/architecture/decisions/`.
- **Post-v1 (documented only):** OTel-assisted shape discovery; LLM-assisted long-tail
  edge cases. Out of scope for a production-ready v1.
