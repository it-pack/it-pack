# Features

This page describes every DeriveIt capability in depth. Each section links to a
runnable [`examples/`](../../examples/README.md) script.

## 1. Flat interface derivation

The core feature. `DeriveIt::derive()` walks a class and returns the rendered
source of a self-contained interface declaring that class's public method
surface.

- Constructors and destructors are dropped (an interface cannot declare them, and
  you generally don't want callers constructing an adaptee through your port).
- Static methods are dropped by default (see [§3](#3-static-methods)).
- Magic `__*` methods are dropped by default.
- No `extends`/trait `use` leaking the adaptee hierarchy (the
  [leakage invariant](../guide/index.md#leakage-invariant)).

```php
use ItPack\DeriveIt\DeriveIt;

$source = DeriveIt::derive(SomeThirdPartyClass::class);
```

If you want the `Nette\PhpGenerator\InterfaceType` object instead of a string,
use `DeriveIt::deriveInterface()`.

> Example: [`examples/01-flat-interface.php`](../../examples/01-flat-interface.php)

## 2. Type fidelity

DeriveIt does not re-implement PHP's type grammar. It delegates to
`Nette\PhpGenerator\Factory::fromMethodReflection()`, the single source of truth
for parameter/return typing. The full grammar is reproduced faithfully:

- **Union types** — `int|string|null`
- **Intersection types** — `Countable&Iterator`
- **Nullable types** — `?string`, `?self`
- **Variadic parameters** — `string ...$parts`
- **By-reference parameters** — `int &$counter`
- **Enums as parameter/return types** — `SimpleBackedEnum $distribution`
- **`self` / `static` / `parent` return types**

Types are stored internally as FQCNs and simplified to short names in the
rendered source (see [ADR-0004](../architecture/decisions/ADR-0004-type-substitution.md)).

> Example: [`examples/03-type-fidelity.php`](../../examples/03-type-fidelity.php)

## 3. Static methods

A port should not force static calls — a static surface is hard to substitute,
mock, or decorate. Therefore **static methods are dropped by default**.

Retention is opt-in and explicit:

```php
// Convenience shortcut on the facade:
$source = DeriveIt::derive(StaticHeavyClass::class, includeStatic: true);
```

This is safe on the PHP ≥ 8.4 target, which permits static interface methods.

> Example: [`examples/02-drop-constructor-static-magic.php`](../../examples/02-drop-constructor-static-magic.php)
> Decision: [ADR-0003](../architecture/decisions/ADR-0003-static-method-policy.md)

## 4. Per-trait interfaces (Interface Segregation)

Instead of one flat interface, derive **one interface per used trait** and
compose the main interface so it `extends` exactly those derived trait
interfaces. This is Interface Segregation applied to the derivation tool itself.

```php
use ItPack\DeriveIt\DeriveIt;

$mainSource = DeriveIt::derivePerTrait(SomeClassUsingTraits::class);

// Or get the individual trait interfaces, keyed by trait short name:
/** @var array<string, \Nette\PhpGenerator\InterfaceType> $traitInterfaces */
$traitInterfaces = DeriveIt::deriveTraitInterfaces(SomeClassUsingTraits::class);
```

**Conflict resolution:** when a method name appears in multiple traits, the
first trait in `getTraitsRecursively()` order owns it; the others drop it. This
guarantees no duplicate declaration across the extended interfaces. Traits with
no public methods are skipped, so the main interface never extends an empty
interface.

The leakage invariant holds: the main interface `extends` only derived trait
interfaces, never the adaptee hierarchy.

> Example: [`examples/04-per-trait-interfaces.php`](../../examples/04-per-trait-interfaces.php)
> Decision: [ADR-0002](../architecture/decisions/ADR-0002-per-trait-composition.md)

## 5. Type substitution & family derivation

When deriving a **family** of related classes together (e.g. `Node` ⇄ `Tree`), a
derived method's return/parameter type may itself be a class being derived.
Leaving the native type in the derived interface would leak a concrete adaptee
type.

`DeriveIt::deriveFamily()` derives the whole family together and rewrites
cross-referencing types to the corresponding derived interface FQCNs. The
substitution map is **auto-detected** from the classes you pass:

```php
use ItPack\DeriveIt\DeriveIt;

$sources = DeriveIt::deriveFamily([
    \App\Node::class,
    \App\Tree::class,
]);

// NodeInterface::getNext(): ?NodeInterface   (was ?Node)
// TreeInterface::getRoot(): NodeInterface    (was Node)
```

For explicit control (or for types outside the family), build a
`DeriveConfig` with a `typeSubstitutionMap` (class FQCN ⇒ derived interface
FQCN) and pass it where the API accepts a config (e.g.
`DeriveIt::deriveAdapter()`).

> Example: [`examples/05-family-substitution.php`](../../examples/05-family-substitution.php)
> Decision: [ADR-0004](../architecture/decisions/ADR-0004-type-substitution.md)

## 6. Adapter generation

Beyond the interface, DeriveIt can emit a **delegating adapter** that implements
the derived interface and wraps a concrete adaptee instance.

```php
use ItPack\DeriveIt\DeriveIt;

$source = DeriveIt::deriveAdapter(SomeThirdPartyClass::class);
```

The returned source defines **both** `SomeThirdPartyClassInterface` and
`SomeThirdPartyClassAdapter` in one file. The adapter holds the adaptee via
constructor injection and forwards every interface method to it.

When a returned value is itself a derived type, register an adapter class to
wrap it so the port stays free of concrete types:

```php
$source = DeriveIt::deriveAdapter(
    \App\Tree::class,
    returnWrapperMap: [
        \App\NodeInterface::class => \App\NodeAdapter::class,
    ],
);
// TreeAdapter::add(NodeInterface $node) {
//     $this->adaptee->add(new NodeAdapter($node));
// }
```

The adapter lives in the adaptee's own namespace so it is self-contained.

> Example: [`examples/06-adapter-generation.php`](../../examples/06-adapter-generation.php)

## 7. Configuration

Every filtering decision is an explicit `DeriveConfig` flag with a sane default,
so behaviour is testable and never an accidental opinion.

| Flag | Default | Meaning |
|------|---------|---------|
| `visibility` | `VISIBILITY_PUBLIC` | `VISIBILITY_PROTECTED` also includes protected methods. |
| `includeStatic` | `false` | Retain static methods. |
| `includeMagic` | `false` | Retain magic `__*` methods. |
| `dropConstruct` | `true` | Drop `__construct`. |
| `dropDestruct` | `true` | Drop `__destruct`. |
| `typeSubstitutionMap` | `[]` | Class FQCN ⇒ derived interface FQCN. |

`DeriveIt::derive()` and `deriveInterface()` expose an `includeStatic` shortcut;
for the full set of flags, pass a `DeriveConfig` to `DeriveIt::deriveAdapter()`
or construct an `InterfaceDeriver` directly.

> Example: [`examples/07-configuration.php`](../../examples/07-configuration.php)

## 8. Graph walking (the foundation)

DeriveIt's generators sit on top of a recursive graph walker. `ClassReflection`
(a `ReflectionClass` subclass) walks the full parent/interface/trait graph of any
class via three swappable resolvers:

- `RecursiveClassResolver` — parent classes
- `RecursiveInterfaceResolver` — implemented interfaces (recursively)
- `RecursiveTraitResolver` — used traits (including nested traits)

```php
use ItPack\DeriveIt\Reflection\ClassReflection;

$reflection = ClassReflection::create(SomeClass::class);

$reflection->getClassesRecursively();     // ReflectionClass[]
$reflection->getInterfacesRecursively();  // ReflectionClass[]
$reflection->getTraitsRecursively();      // ReflectionClass[]
```

Resolvers are swappable via `ClassReflection::setRecursive*Resolver()` if you
need non-default traversal behaviour.

> Example: [`examples/08-graph-walking.php`](../../examples/08-graph-walking.php)

## 9. The leakage invariant (documented guarantee)

Even when the source class implements other interfaces, the derived interface is
flat and self-contained — it extends nothing of the adaptee. This is verified,
not just promised.

> Example: [`examples/09-leakage-invariant.php`](../../examples/09-leakage-invariant.php)

## 10. Real-world use (dogfooding)

The tool is designed for real, complex classes, not just toy fixtures. You can
derive an interface straight from a dependency you don't own — for example
`nette/php-generator`'s own `ClassType` — and the emitted source is valid,
loadable PHP.

> Example: [`examples/10-dogfood-nette.php`](../../examples/10-dogfood-nette.php)
