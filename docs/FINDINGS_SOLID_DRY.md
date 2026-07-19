# Findings — SOLID & DRY

Anchored to `src/`. Severity: 🔴 defect · 🟠 smell · 🟡 minor.

---

## S — Single Responsibility

### 🟠 `ClassReflection` is two things at once (DeriveIt)
`src/DeriveIt/src/Reflection/ClassReflection.php`
- Lines 15–24: a `ReflectionClass` decorator that adds recursive graph queries.
- Lines 17–19, 31–72: a **static service-locator / registry** holding mutable resolver singletons (`$classResolver`, `$interfaceResolver`, `$traitResolver`) with `get*Resolver()`/`set*Resolver()` accessors.

Owning global mutable state and being a value-style reflection wrapper are two responsibilities. The static caches also make the class non-deterministic across tests/threads and force test setup through `set*Resolver()` (see `ClassReflectionTest.php` lines 40–93, which must stub each resolver).

**Suggestion:** inject the three resolvers via constructor (`__construct(ReflectionClass $rc, RecursiveClassResolverInterface $cr, …)`) and drop the static `set*Resolver()` mutators. A thin factory/`ClassReflection::create()` can keep the ergonomic call site.

### 🟠 `RecursiveTraitResolver::resolve` mixes three traversal concerns (DeriveIt)
`src/DeriveIt/src/Resolver/RecursiveTraitResolver.php` lines 20–54.
`resolve()` orchestrates; `resolveClassInheritance()` calls back into the *public* `resolve()` (line 41) to handle trait-of-trait recursion, while `resolveTraitInheritance()` separately maps `resolve()`. The recursion crosses method boundaries in a tangled way (the class-inheritance walker delegates to the top-level `resolve`, which re-enters the trait walk). Works, but the responsibility split is unclear and easy to break.

**Suggestion:** keep one clear recursion: `collectTraits(ReflectionClass): list<string>` that iterates the class chain and, per class, merges `getTraits()` plus recursion over each trait's own `getTraits()`. Avoid calling the public `resolve()` from a private helper.

---

## O — Open / Closed

### 🟡 Empty marker interfaces are not extension points (DeriveIt)
`src/DeriveIt/src/Contract/Resolver/Recursive{Class,Interface,Trait}ResolverInterface.php`
All three are `extends RecursiveResolverInterface` with **zero** added members. They don't add behavior, so they aren't meaningful OCP extension points — they're nominal DI tags.

**Suggestion:** either (a) give them a distinguishing method/constant (e.g. a `public const TYPE = 'class'`), or (b) collapse to a single `RecursiveResolverInterface` parameterised by a `ResolverKind` enum, or (c) accept the tag interfaces but document *why* they exist (adapter selection). As-is they're dead weight.

### 🟡 `HashFinalizer` pretends to be configurable (HashIt) — see Defects
`src/HashIt/HashFinalizer.php` line 11 accepts `bool $binary` but ignores it. The class is *not* open for the "binary vs text" variation it appears to support; that variation lives only in `BinaryHashFinalizer`. This is an OCP/LSP trap (see below).

---

## L — Liskov Substitution

### 🔴 `HashFinalizer` violates the contract its constructor implies (HashIt)
`src/HashIt/HashFinalizer.php`
```php
public function __construct(bool $binary = false){}   // $binary never read
public function finalize(HashContext $context): string {
    return hash_final($context, false);                // always text
}
```
A caller who constructs `new HashFinalizer(true)` reasonably expects binary output; they get text. The two `HashFinalizerInterface` implementations are **not substitutable by their constructor argument**. `BinaryHashFinalizer` (correctly) always returns binary and is `final`.

**Suggestion:** delete the misleading constructor parameter; rename the class to `TextHashFinalizer` (it always returns text). Keep `BinaryHashFinalizer` for the `true` case. Or make a single finalizer constructed with the `bool $binary` it actually uses.

---

## I — Interface Segregation

### 🟡 `HashInterface` exposes getters nobody can populate (HashIt)
`src/HashIt/HashInterface.php` + `src/HashIt/Hash.php`
The interface declares `algorithm`, `length`, `binary`, `value` as read-only getters. The implementing `Hash` can never satisfy them correctly (its getters recurse infinitely — see Defects). The interface itself is fine in isolation, but it is **segregated from any constructor/setter that could fulfill it**, so the contract is unfulfillable. Not an ISP violation per se, but an interface with no viable implementer.

**Suggestion:** redesign `Hash` as an immutable value object with a real constructor (`__construct(string $value, string $algorithm, bool $binary)`) and backing properties; only then keep the interface.

---

## D — Dependency Inversion

### 🟠 `Hash` depends on nothing it can use; ports are orphaned (HashIt)
`src/HashIt/Hash.php` is a dead/orphan class (no `new Hash` anywhere in the package, no wires to `ContextFactory`/`HashUpdater`/`HashFinalizer`). The ports exist but there is **no composition root** tying `ContextFactory → HashUpdater → HashFinalizer → Hash` together. DIP is "declared" (interfaces present) but never "applied" (no high-level module consumes the abstraction to produce a hash).

**Suggestion:** add a `Hasher` / `HashService` that depends on `ContextFactoryInterface`, `HashUpdaterInterface`, `HashFinalizerInterface` and returns a `Hash` value object. That is the missing high-level module that makes DIP real.

### 🟠 `ClassReflection` depends on concrete resolvers by default (DeriveIt)
`src/DeriveIt/src/Reflection/ClassReflection.php` lines 34, 50, 66 hard-wire `Recursive{Class,Interface,Trait}Resolver::create()` as the default. The dependency direction is fine (domain depends on the port), but the *default binding* is baked into the domain object instead of a composition root.

**Suggestion:** move default wiring to `ClassReflection::create()` (a static factory / composition root), leaving the instance itself dependency-free.

### 🟡 `HashIt` has no `composer.json` (packaging / DIP at the repo level)
`src/HashIt/` contains source but **no `composer.json`**, so it has no PSR-4 autoload, no declared dependencies, and cannot be installed or tested in isolation. `DeriveIt` and `FileIt` and `ExtendIt` each have one; `HashIt` is the outlier.

**Suggestion:** add `src/HashIt/composer.json` mirroring the sibling packages (`ItPack\HashIt\` → `src/`), plus a phpstan/cs config and at least a smoke test.

---

## DRY — Duplication

### 🔴 The algorithm catalogue is encoded **three times** (HashIt)
1. `src/HashIt/HashAlgorithm.php` — hand-written `enum` with `#[HmacSafe]` attributes + methods.
2. `src/HashIt/HashAlgorithmEnum.php` — *auto-generated* `enum` with the **same cases**, no methods.
3. `src/HashIt/HashAlgorithms.php` — `interface` of `final public const string` + a `HMAC_SAFE` array.

`HashAlgorithm::hmacSafeAlgorithms()` (lines 213–217) returns `HashAlgorithms::HMAC_SAFE`, and the `HMAC_SAFE` array duplicates the `#[HmacSafe]` marks in `HashAlgorithm`. Three sources of truth for one list → guaranteed drift (the auto-gen header even warns "Do not change manually").

**Suggestion:** keep **one** authoritative definition. Recommend: a single `HashAlgorithm` backed enum, and derive `HMAC_SAFE` from the attribute at runtime (`array_filter` over `cases()` checking the attribute), or generate the const-array from the enum. Delete `HashAlgorithmEnum` (it adds nothing over `HashAlgorithm`) and drop the hand-maintained `HMAC_SAFE` list in `HashAlgorithms`.

### 🟡 Repeated `create()` boilerplate across resolvers (DeriveIt)
`RecursiveClassResolver.php` (15–18), `RecursiveInterfaceResolver.php` (15–18), `RecursiveTraitResolver.php` (15–18) each repeat the identical `public static function create(): self { return new self(); }`, made necessary by the `LazyCreatable` trait's `abstract public static function create(): self`.

**Suggestion:** move the trivial `create()` into `LazyCreatable` itself (provide a default `new static()` implementation that subclasses can override), eliminating the triplicate.

### 🟡 `SPLFileInfo` mis-cased type (HashIt)
`src/HashIt/FilepathFileinfo.php` line 7: `use SPLFileInfo;` (should be `SplFileInfo`). PHP class names are case-insensitive so it runs, but it's a copy-paste smell and breaks tooling that normalises FQCNs.

### 🟠 Per-package in-tree `vendor/` + duplicated tooling (monorepo DRY)
Each package ships its own `vendor/` tree (e.g. `src/ExtendIt/vendor/` contains symfony, roave/better-reflection, webonyx/graphql, dagger…). The same dev tooling (`just-bases/php-qa-base`, phpstan, cs-fixer) is re-declared per package. Git-ignored, so not a commit problem, but it defeats the point of a monorepo.

**Suggestion:** adopt a single root `composer.json` (or `phpactor/phpractor` / `symplify/monorepo-builder`) so dependencies and QA config live once. At minimum, share a root `phpstan.neon` / `.php-cs-fixer.php` imported by each package.

---

## DRY — Anemic / dead structures

### 🟡 `FileInfo` is a mutable anemic struct (FileIt)
`src/FileIt/ValueObject/FileInfo.php` — 9 `public` properties, no constructor, no validation, no behavior. Callers can construct it empty (`$mimeType`, `$size` uninitialised → `null` where `int|false` is declared).

**Suggestion:** make it a `readonly` value object with a constructor that requires the essential fields and defaults/derives the rest; validate types at construction.

### 🟠 `ClassInspector::inspect()` does nothing (ExtendIt)
`src/ExtendIt/src/Inspection/ClassInspector.php` lines 16–19:
```php
public function inspect(ReflectionClass $class): void {
    $methods = $class->getMethods();   // computed, discarded
}
```
Dead code — the result is never used, returned, or stored. This is a placeholder masquerading as an implementation.

**Suggestion:** either implement (collect/return a meaningful structure) or remove the class until the behavior exists. Don't ship no-op public methods.

### 🟡 `HashValue` and `Hash` overlap (HashIt)
`src/HashIt/HashValue.php` (wraps a `string $value`) and `src/HashIt/Hash.php` (`$value` getter) both represent "a hash value" with no relationship. Pick one representation once `Hash` is repaired.
