# derive-it

> Part of the **It-Pack** family.

Derives interfaces and adapters from third-party or legacy PHP classes — without hand-writing reflection code, and without leaking the adaptee's own contracts into your codebase.

## The problem

You want to depend on a port (an interface you own), not on someone else's concrete class or someone else's interface. But hand-deriving a correct interface from a real-world class — with its full inheritance chain, its traits, its already-implemented interfaces, its constructor promotion, its union/intersection types, its enums — is tedious and error-prone to write by hand, and brittle to maintain as the adaptee changes.

`derive-it` automates the derivation. Point it at a class (or a package, or a related family of classes), and it walks the full class/trait/interface graph and produces a clean, self-contained interface — one that never `extends` or references anything from the adaptee's own type hierarchy.

## What it does

- Walks the full inheritance chain (parent classes), trait usage (including nested traits), and already-implemented interfaces of a target class.
- Flattens everything into method signatures — no leaking of the adaptee's own interfaces or class hierarchy into the derived contract.
- Filters by visibility (public surface only) and handles static vs. instance methods deliberately.
- Correctly drops constructors (an interface can't declare one, and you generally don't want callers constructing an adaptee through your port anyway).
- Optionally derives one interface per trait boundary, instead of one flat interface — Interface Segregation applied to the derivation tool itself.
- Built on `nette/php-generator` for rendering, and native PHP `Reflection` for graph-walking — each used for what it's actually good at.

## Usage sketch

```php
use ItPack\DeriveIt\Reflection\ClassReflection;

$reflection = ClassReflection::create(SomeThirdPartyClass::class);

$classes    = $reflection->getClassesRecursively();
$interfaces = $reflection->getInterfacesRecursively();
$traits     = $reflection->getTraitsRecursively();

// → feed the resolved graph into the interface/adapter generation step
```

Resolvers are swappable — `ClassReflection` accepts custom implementations of
`RecursiveClassResolverInterface`, `RecursiveInterfaceResolverInterface`, and
`RecursiveTraitResolverInterface` if you need non-default traversal behavior.

## Why not just use Reflection directly?

You can — and `derive-it` does, internally, for the graph-walking. But hand-rolling the full derivation on top of raw Reflection means re-solving PHP's entire type grammar yourself (union types, intersection types, nullable types, variadics, by-ref params, readonly, constructor promotion, enums as parameter types...). `nette/php-generator` already solves the introspection-to-source round-trip correctly; `derive-it` adds the *derivation* logic — deciding what belongs in the interface, what doesn't, and how it should be shaped — on top.

## Status

Early, active. First real target: deriving adapters for the Dagger PHP SDK's generated classes, as part of AExOps. Dogfooding target: deriving an adapter for `nette/php-generator` itself.

## Roadmap

- **Interface-per-trait as a configurable option**, not just one flat interface per class.
- **Type substitution map** — when a derived method's return/param type is itself something being derived (e.g. deriving the whole `Reflection*` family together), rewrite the signature to point at the corresponding derived interface instead of the native type, and wrap the actual return value at the adapter implementation layer. Batch-scoped auto-detection by declared-type match as the default; explicit caller-supplied overrides for anything ambiguous (untyped/mixed returns).
- **OTel-assisted shape discovery** — reusing OpenTelemetry extension instrumentation (not for the generated adapters themselves, but for the generation/analysis step) to observe actual runtime argument/return shapes where static signatures are too loose to trust.
- **LLM-assisted long tail** — deterministic tooling handles the ~90-95% of classes/methods with clean, honest signatures; a narrowly-scoped LLM call handles the specific remaining edge cases a user doesn't want to hand-write themselves.