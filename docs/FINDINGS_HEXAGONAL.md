# Findings — Hexagonal Architecture (Ports & Adapters)

Anchored to `src/`. Severity: 🔴 defect · 🟠 smell · 🟡 minor.

Hexagonal ("ports & adapters") asks: **the domain/core depends only on ports (interfaces it owns);
adapters (concrete implementations, frameworks, stdlib) live at the edges and are wired in a
composition root.** Dependencies point inward.

---

## What is already good

- **`DeriveIt` has a real port/adapter split.** `src/DeriveIt/src/Contract/Resolver/*ResolverInterface.php`
  are **ports** (interfaces the package owns), and `src/DeriveIt/src/Resolver/*Resolver.php` are
  **adapters** (concrete traversal strategies). The domain (`ClassReflection`) depends on the
  interfaces, not the concretes. This is the right shape and matches the README's "depend on a port
  you own" philosophy.
- **`HashIt` *declares* ports** (`ContextFactoryInterface`, `HashUpdaterInterface`,
  `HashFinalizerInterface`, `FilepathInterface`) — the intent is there.

The problems are in **edge hygiene**, **wiring**, and **dependency direction** detailed below.

---

## 🟠 Ports that leak concrete framework/stdlib types (HashIt)

A port must hide the adapter's technology. Here the ports expose PHP's own `HashContext` type,
so the "port" is coupled to the stdlib hashing implementation — swapping to a different hashing
backend (e.g. an external KMS, a queue, a mock) is impossible without touching the port.

| File | Leak |
|------|------|
| `src/HashIt/src/HashFinalizerInterface.php:11` | `finalize(HashContext $context): string` |
| `src/HashIt/src/HashFinalizer.php:13` | `hash_final($context, …)` — concrete call in adapter |
| `src/HashIt/src/HashUpdater.php:18` | `hash_update($this->context, $data)` — concrete call |
| `src/HashIt/src/HashUpdaterInterface.php:9` | port mentions only `update(string)` ✅ but the adapter's `HashContext` is supplied by the port-side `finalize()` signature |
| `src/HashIt/src/ContextFactoryInterface.php:11` | `build(): HashContext` — port returns the concrete stdlib type |
| `src/HashIt/src/ContextFactory.php:17` | `hash_init($this->algo)` — concrete call |

**Suggestion:** the port boundary should speak **only in domain/primitive types**.
`ContextFactoryInterface::build()` should return an abstraction the package owns (e.g.
a `HashContextPort` interface or simply feed the context into the updater/finalizer via the
composition root rather than threading `HashContext` through the public port signatures).
Concretely: have `HashUpdaterInterface`/`HashFinalizerInterface` accept the package-owned
context object, and let the *adapter* hold the `HashContext` internally. That restores
"the core never sees `HashContext`."

---

## 🔴 No composition root — ports are orphaned (HashIt)

There is **no high-level module** that wires `ContextFactory → HashUpdater → HashFinalizer → Hash`.
- `src/HashIt/src/Hash.php` (the obvious "result" type) is dead/orphaned — nothing constructs or
  returns it, and it is non-functional (see Defects).
- Nothing in `src/HashIt` composes the three adapters into a usable "hash this string/file" use-case.

Without a composition root, the ports/adapters exist as a diagram, not as running code. DIP is
*declared* but never *applied*.

**Suggestion:** add a core service, e.g. `Hasher` (in `src/HashIt/`):
```php
final class Hasher {
    public function __construct(
        private ContextFactoryInterface  $factory,
        private HashUpdaterInterface     $updater,
        private HashFinalizerInterface   $finalizer,
    ) {}

    public function hash(string $algo, string $data): HashInterface {
        $ctx = $this->factory->build($algo);   // note: algo passed in
        $this->updater->update($ctx, $data);
        return new Hash($this->finalizer->finalize($ctx), $algo, /*binary*/ false);
    }
}
```
This is the missing inward-facing module and the single place adapters get bound.

---

## 🟠 The composition root leaks into the domain object (DeriveIt)

`src/DeriveIt/src/Reflection/ClassReflection.php` lines 31–72 bake default adapter bindings into
the domain class:
```php
public static function getRecursiveClassResolver(): RecursiveClassResolverInterface {
    return self::$classResolver ?: self::$classResolver = RecursiveClassResolver::create();
}
```
This is the **registry / service-locator anti-pattern** at the edge of the core: the domain object
knows the concrete adapter class names and owns global mutable resolver state. It also makes the
defaults non-overridable except via the static `set*Resolver()` mutators (which are themselves a
global-state smell and force test setup, see `ClassReflectionTest.php`).

**Suggestion:** keep `ClassReflection` as a pure domain object that *receives* its resolvers
(via constructor or a factory). Do the `Recursive*Resolver::create()` default binding in
`ClassReflection::create()` (the package's composition-root entry point) or in the application
that consumes DeriveIt — not inside the core class.

---

## 🟡 `FilepathInterface` is a port with a single trivial adapter (HashIt)

`src/HashIt/src/FilepathInterface.php` + `src/HashIt/src/FilepathFileinfo.php`. The port (`__toString(): string`)
and the one adapter (`SplFileInfo` → string) are fine, but:
- The port is **never consumed** anywhere in the package (no file-hashing path exists yet).
- `FilepathFileinfo` is `final readonly` while `HashFinalizer` is *not* `final` — inconsistent
  sealing of adapters (see Defects for the related `HashFinalizer` issue).

**Suggestion:** either wire `FilepathInterface` into a real `hashFile(FilepathInterface)` use-case
(adapter supplies the path to the core `Hasher`), or defer adding it until that use-case exists.
Standardise `final` on all concrete adapters.

---

## 🟡 Adapter-side coupling is otherwise clean in DeriveIt

The three resolvers depend only on `ReflectionClass` (stdlib, used *inside* the adapter — acceptable
since the adapter is the edge) and on the `LazyCreatable` trait. They do **not** reach back into the
core. Dependency direction is correct; only the *default wiring location* (above) needs moving.

---

## 🟠 Monorepo layout fights the "one core, many edges" idea

Each package carries its own `vendor/` tree and re-declares dev tooling. This isn't a ports/adapters
violation per se, but it obscures the boundary between "core" and "edge": vendored frameworks
(dagger, symfony, roave/better-reflection, webonyx/graphql in `src/ExtendIt/vendor/`) sit **inside**
a package that is supposed to be producing adapters — with no clear adapter module separating them from
the package's own code.

**Suggestion:** make the adapter boundary explicit — e.g. `src/ExtendIt/src/Adapter/Dagger/*` for
dagger-derived adapters, keeping the package's own `src/` as the core. And collapse the duplicated
tooling via a root composer/QA config (see DRY doc).

---

## Checklist for `HashIt` to become genuinely hexagonal

1. ✅ Ports exist → 🟠 make them speak only domain/primitive types (stop leaking `HashContext`).
2. 🔴 Add the missing core service (`Hasher`) + a real `Hash` value object.
3. 🟠 Add a composition root (factory / DI config) binding the three adapters.
4. 🟡 Consume `FilepathInterface` in a file-hashing use-case or drop it for now.
5. 🟡 Add `composer.json` + tests so the wiring is exercised.
