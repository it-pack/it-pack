# DeriveIt — Implementation Roadmap

> Detailed phase plan companion to `REVIEW.md` and `IMPLEMENTATION_PLAN.md`
> Prepared: 2026-08-14 · Last reviewed: 2026-08-23 (Phase 12 residual refactoring implemented) · Status: **Phases 0–11 complete; Phase 12 (REVIEW F-1 … F-10) implemented; release-ready** · Package: `it-pack/derive-it`

This document expands every milestone of the implementation plan into a working
specification: objective, scope, step-by-step approach, dependencies, acceptance
criteria, and execution notes. It is intentionally complementary to the plan —
the plan lists *what* and *when*, this roadmap documents *how* and *why* each
phase is built so a developer can pick it up cold.

## Contents

1. Phase 0 — Foundation & CI Greening (prerequisite)
2. Phase 1 — Core: Flat Interface Derivation (v0.1.0)
3. Phase 2 — Filtering Controls & Method Pipeline (v0.1.x)
4. Phase 3 — Interface-per-Trait Mode (v0.2.0)
5. Phase 4 — Type Substitution Map (v0.3.0)
6. Phase 5 — Adapter Generation (v0.4.0)
7. Phase 6 — Architecture & QA Hardening
8. Phase 7 — Docs, ADRs & Release
 9. Phase 8 — Design Hardening Backlog (post-v1)
10. Phase 9 — Edge-case & Optimization Hardening
11. Phase 10 — Correctness, Leakage & DX Deepening
12. Phase 11 — Refactoring & Modernization (planned)
13. Phase 12 — Residual Refactoring (REVIEW F-1 … F-10, planned)
14. Cross-cutting: Timeline, Risks, Definition of Done

---

## Phase 0 — Foundation & CI Greening

> **Status: ✅ Completed (2026-08-14).** All three gates green; `test:unit` 26/26,
> `cs:check` 0 files, `analyse` no errors. (Suite has since grown to 108 tests with later
> phases.) `composer.json` now declares `php >=8.4`,
> Codeception modules moved to `require-dev`, `nikic/php-parser` dropped as a direct
> dep, `.gitattributes` added.

**Goal:** the project's own tooling is green and packaged correctly. Nothing
behavioral changes — this phase only repairs what `REVIEW.md` §4 and §6 found
broken. It is a hard prerequisite: every later phase assumes `composer analyse`
exits 0 and that the package installs cleanly on a PHP 8.4+ platform.

*Estimated effort: ~0.5 day. Exit criteria: `composer test:unit`, `cs:check`,
`analyse`, and `validate` all pass on a clean checkout.*

### 0.1 Fix PHPStan `src/` errors
- Three short-ternary sites in `src/Reflection/ClassReflection.php` (lines 33, 49, 65) use
  `self::$x ?: self::$x = ...`, forbidden by the active `@PHP84Migration` ruleset.
- Replace each with the null-coalescing assignment: `self::$x ??= new Resolver();`.
- `src/Resolver/RecursiveTraitResolver.php:24` uses `empty($traits)`; replace with a strict
  `$traits === []` (or `count($traits) === 0`) comparison.
- **Acceptance:** `composer analyse` reports zero errors originating in `src/`.

### 0.2 Quiet PHPStan test false-positives
- The 10 failing assertions in `tests/` are `method.unused` / `shipmonk.deadMethod` on
  `provide*Data()` methods consumed at runtime via Codeception `#[DataProvider('...')]`.
- Configure the Codeception-aware PHPStan extension (or add scoped `ignoreErrors` for
  `method.unused` and `shipmonk.deadMethod` limited to the `tests/` path).
- **Do NOT delete the data providers** — they are real test inputs. Suppression, not removal.
- **Acceptance:** `composer analyse` exits 0 end-to-end.

### 0.3 Declare the PHP platform
- `composer.json` `require` is missing a `php` constraint even though the code uses
  PHP 8.4 features (`ReflectionClass::newLazyProxy()` in `LazyCreatable`, `@PHP84Migration` CS set).
- Add `"require": { "php": ">=8.4" }` (or `"^8.4 || ^8.5"` to stay explicit).
- **Acceptance:** PHP-CS-Fixer's "No PHP version requirement" warning disappears;
  `composer validate` is clean.

### 0.4 Fix dependency placement
- `codeception/module-phpbrowser` and `codeception/module-asserts` are in `require`
  (production) but are test-only frameworks — a consuming library must not pull them in.
- Move both to `require-dev`. Drop the direct `nikic/php-parser` entry; it is only a
  transitive dep of `nette/php-generator` and is never used directly in `src/` or `tests/`.
- Keep `nette/php-generator` in `require` (the future generator depends on it).
- **Acceptance:** `composer why codeception/module-phpbrowser` shows dev-only;
  `composer install --no-dev` contains no Codeception.

### 0.5 Distribution hygiene
- Add a `.gitattributes` with `export-ignore` for `tests/`, `fixtures/`, `scripts/`, `var/`,
  `generated/`, and all `*.md` except `README.md` so the published dist is lean.
- **Acceptance:** `composer archive` produces a tarball without dev/scaffolding files.

---

## Phase 1 — Core: Flat Interface Derivation (v0.1.0)

> **Status: ✅ Completed (2026-08-14).** `DeriveIt::derive($class)` derives a self-contained
> interface for any class. Files: `src/Contract/Generator/DeriverInterface.php`,
> `src/Generator/{MethodExtractor,MethodSignatureFactory,InterfaceDeriver}.php`,
> `src/DeriveIt.php`. Golden tests over 42 class fixtures committed under `tests/_expected/`.
> Gates green. (Suite has since grown to 108 tests with later phases.)

**Goal:** given a class, produce a clean Nette `InterfaceType` exposing the effective public
method surface with **zero leakage** from the adaptee's own hierarchy. This is the actual
product and the smallest shippable unit. It directly closes the largest gap in `REVIEW.md` §5
— "the generation step does not exist in `src/`".

### 1.1 Define the generator contract
- New `src/Contract/Generator/DeriverInterface.php` with
  `derive(ReflectionClass|string $class): Nette\PhpGenerator\InterfaceType` and
  `deriveSource(...): string`.
- Keep the existing resolvers as the graph source; the deriver consumes their output, it
  does not re-walk the graph for method collection.
- **Acceptance:** interface compiles, PHPStan green, no behavioral change to resolvers.

### 1.2 Method extraction (flat mode)
- New `src/Generator/MethodExtractor.php`. For flat mode, call
  `getMethods(ReflectionMethod::IS_PUBLIC)` on the **concrete** `ReflectionClass`.
- Rationale: PHP already resolves parent + trait method conflicts (handles the
  `ConflictingMethodTrait` fixture) — we must not hand-walk the graph for the surface.
- Use `ClassReflection::getClassesRecursively()` / `getTraitsRecursively()` only later,
  in per-trait mode (Phase 3).
- **Acceptance:** returns the effective public method set; conflicts resolved (assert via
  the `ConflictingMethodTrait` fixture).

### 1.3 Reflection → signature mapping
- New `src/Generator/MethodSignatureFactory.php` using
  `Nette\PhpGenerator\Factory::fromMethodReflection(ReflectionMethod $m): Method`.
- This yields union / intersection / nullable / variadic / by-ref / readonly-aware
  parameters and return types for free — the single source of truth for typing.
- Then `$method->setAbstract(true)->setBody(null)->setVisibility('public')` to strip
  bodies and force the abstract-public interface shape.
- **Acceptance:** one `Method` per public method, body stripped, all types preserved.

### 1.4 Interface assembly + leakage guard
- New `src/Generator/InterfaceDeriver.php` builds the `InterfaceType`, dropping
  `__construct` / `__destruct`.
- Core invariant (`REVIEW.md` §6.3, principle 3): the assembled type must `extends` /
  reference **nothing** from the adaptee's own parents, traits, or interfaces.
- Verify `getExtends()` is empty and that no FQCN outside the target namespace leaks
  (render via `PhpFile` + `PhpNamespace` so Nette auto-generates short `use` aliases).
- Add a reusable assertion helper `assertInterfaceIsSelfContained()` for tests.

### 1.5 Public entry + static policy
- `DeriveIt::derive($class): string` facade. Default policy **drops static methods**
  ("ports shouldn't force static").
- Provide an `includeStatic` flag — safe because PHP 8.1+ allows static interface
  methods and the target platform is ≥ 8.4.
- **Acceptance:** static methods excluded by default, included when the flag is set.

### 1.6 Snapshot tests over the 60 fixtures
- Leverage the ~60 existing fixtures (`ReadonlyClass`, `UnionTypeClass`,
  `IntersectionTypeClass`, `VariadicAndByRefClass`, `ConsumesEnumParamClass`, ...).
- Generate each interface and compare to a committed golden file
  `tests/_expected/<Fixture>.interface.php` (Codeception `Snapshot` or a simple file diff).
- This is the real regression net for the type grammar — it is what catches Nette
  edge cases (intersection types, enums) immediately.
- **Acceptance:** every fixture produces a deterministic, correct interface; golden files committed.

### 1.7 Fix / retire the scratch script
- Replace `scripts/generate_reflection_adapter.php` with the real deriver; delete dead code
  (the `exit;` and unreachable lines 161–180; the double `return` at line 37).
- Optionally keep a thin `scripts/derive.php` demo that calls the public API.
- **Acceptance:** no unreachable code; any script uses the public `DeriveIt` API.

> **v0.1 Definition of Done:** `test:unit`, `cs:check`, `analyse` green; `derive(SomeClass::class)`
> returns valid PHP interface source for all 60 fixtures; README "What it does" rewritten
> to describe only what Phase 1 delivers.

---

## Phase 2 — Filtering Controls & Method Pipeline (v0.1.x)

> **Status: ✅ Completed (2026-08-14).** `DeriveConfig` (immutable value object) plus a
> composable `MethodFilterInterface` chain (`VisibilityFilter`, `StaticFilter`,
> `MagicMethodFilter`, `ConstructorFilter`) drive `MethodExtractor`. Every filter and
> the config are unit-tested in isolation; type-fidelity covered by golden snapshot +
> `FilteringTest`. Gates green.

**Goal:** make every derivation decision explicit, configurable, and independently testable.
This turns the hard-coded choices of Phase 1 into a policy object and a composable filter
chain, following plan principle 4 (configurable, not opinionated-by-accident).

### 2.1 `DeriveConfig` value object
- `src/Generator/DeriveConfig.php`: `visibility` (public default, optional protected),
  `includeStatic`, `includeMagic` (default false → drop `__*` except meaningful),
  `dropConstruct` / `dropDestruct` (default true).
- Immutable, constructed with named defaults so behavior is explicit and testable.

### 2.2 Composable `MethodFilter` chain
- `src/Contract/Generator/MethodFilterInterface.php` plus `VisibilityFilter`, `StaticFilter`,
  `MagicMethodFilter`, `ConstructorFilter`.
- `MethodExtractor` runs the chain; each filter is unit-tested in isolation, and fixtures
  assert combinations of filters.

### 2.3 Type-fidelity tests
- Assert return/param types render correctly for union (`A|B`), intersection (`A&B`),
  nullable (`?T`), variadic (`...$x`), by-ref (`&$x`), enum param/return, readonly
  properties (not in signatures), and constructor promotion (params only).
- **Acceptance:** golden files updated; all type features covered by tests.

---

## Phase 3 — Interface-per-Trait Mode (v0.2.0)

> **Status: ✅ Completed (2026-08-14).** `PerTraitInterfaceDeriver` derives one
> `InterfaceType` per trait (from the trait's own public methods, conflict-resolved
> so a method shared by multiple traits lives in exactly one trait interface) and
> composes the main interface to `extends` only those derived trait interfaces.
> `DeriveIt::derivePerTrait()` / `deriveTraitInterfaces()` exposed. Fixtures gained
> trait methods + a `ConflictingTraitsClass`. Gates green.

**Goal:** apply Interface Segregation to the tool itself — derive one interface per trait
and compose the main interface from them. This is the README "What it does" per-trait
option, currently only a single flat resolver path.

### 3.1 Per-trait derivation
- For each trait in `ClassReflection::getTraitsRecursively()`, derive one `InterfaceType` from
  that trait's own `getMethods(IS_PUBLIC)` (filtered).
- **Acceptance:** one interface per trait; no external `extends`.

### 3.2 Compose the main interface
- The main derived interface `extends` each per-trait interface (still zero adaptee
  leakage — the per-trait interfaces are ours).
- Conflict handling: when a method appears in multiple traits, it lives in exactly one
  trait interface and is referenced once.
- **Acceptance:** `getExtends()` lists only derived trait interfaces; no duplicate methods.

### 3.3 Tests
- Use `fixtures/Stubs/TraitA..E` + `ClassD` (trait on a deep subclass) + a conflicting-trait
  fixture. Assert the main interface `extends` the correct set.

---

## Phase 4 — Type Substitution Map (v0.3.0)

> **Status: ✅ Completed (2026-08-18).** `TypeSubstitutor` walks `ReflectionNamedType` /
> `ReflectionUnionType` / `ReflectionIntersectionType` and rewrites class types to their
> derived interface FQCN (kept canonical internally; Nette simplifies to short names in
> rendered source). `FamilyDeriver::derive(array $classes)` builds the derive-set map
> automatically and derives every member so cross-references point at derived interfaces.
> Explicit overrides via `DeriveConfig::typeSubstitutionMap` win over auto-detection.
> `DeriveIt::deriveFamily()` renders the family. Covered by `TypeSubstitutionTest`
> (union/param rewrite, builtins untouched, family cross-references, explicit override,
> flat-without-map leaves native type). Gates green: `test:unit` 108/108, `cs:check` 0,
> `analyse` 0.

**Goal:** when a derived method's type is itself something being derived, point at the derived
interface instead of the concrete adaptee type (README roadmap item). This keeps the
leakage invariant intact even across a family of types.

### 4.1 `ReflectionType` walker
- `src/Generator/TypeSubstitutor.php` recursing `ReflectionNamedType` / `ReflectionUnionType` /
  `ReflectionIntersectionType`, rewriting a class FQCN present in the derive-set to its
  derived interface FQCN.
- **Acceptance:** `Foo` → `FooInterface` in signatures.

### 4.2 Batch auto-detection
- Derive a whole family together (e.g. the `Reflection*` family); rewrite by declared-type
  match so cross-referencing types are rewritten automatically.

### 4.3 Explicit overrides
- `DeriveConfig::typeSubstitutionMap` for ambiguous (untyped / `mixed`) returns where
  auto-detection cannot decide; caller-supplied overrides win.

### 4.4 Tests
- Derive two mutually-referencing fixtures; assert return/param types rewritten to derived
  interfaces. No native adaptee type leaks through substitution.

---

## Phase 5 — Adapter Generation (v0.4.0)

> **Status: ✅ Completed (2026-08-18).** `AdapterGenerator` (`src/Generator/AdapterGenerator.php`)
> emits a `final` `ClassType` implementing the derived interface, with a constructor-injected
> `readonly` adaptee and per-method delegation. When a method's (substituted) return type names a
> derived interface present in `returnWrapperMap` (derived-interface FQCN => adapter class FQCN),
> the returned value is wrapped in that adapter (null-safe for nullable returns). `DeriveIt::deriveAdapter()`
> exposes it (emits interface + adapter together, self-contained). Covered by `AdapterGeneratorTest`:
> golden-file match (`tests/_expected/Tree.adapter.php`), end-to-end delegation + return wrapping
> (`Service`/`Collaborator`), void/nullable-return handling, and a `nette/php-generator`
> (`PhpNamespace`) dogfood smoke test. Gates green: `test:unit` 113/113, `cs:check` 0, `analyse` 0.

**Goal:** generate the delegating adapter that implements the derived interface and wraps the
adaptee — this completes "derive adapters" and provides the real `nette/php-generator` dogfood
target from the plan.

### 5.1 `AdapterGenerator`
- New `src/Generator/AdapterGenerator.php` emits a `ClassType` implementing the derived
  interface, holding an adaptee instance (constructor-injected), delegating each method,
  and wrapping return values via a `returnWrapperMap` (derived-interface FQCN => adapter class
  FQCN) when the return type was substituted (Phase 4).
- **Acceptance:** adapter implements the derived interface; delegation is correct; substituted
  return values are wrapped.

### 5.2 Dogfood: `nette/php-generator`
- Derive an interface + adapter for a real `nette/php-generator` class (`PhpNamespace`) and use
  it in a smoke test — proving the round-trip on a real-world, in-repo dependency.

### 5.3 Tests
- Golden-file test for generated adapters plus an end-to-end
  "adaptee → adapter → call → correct result" test (incl. return-value wrapping).

---

## Phase 6 — Architecture & QA Hardening

**Goal:** production-grade quality gates, borrowing the deptrac / infection / coverage
tooling already proven in the sibling `ConfigureIt` package.

### 6.1 `deptrac.yaml` ✅
- Layers: `Contract` (no deps), `Reflection`, `Resolver`, `Generator`, `Behavior`, plus an
  `Entry` layer for the root `DeriveIt.php` facade. `Behavior` is a foundational layer that
  the others depend on (its only dependency is the `LazyCreatable` utility trait).
- **Acceptance:** `composer architecture` (`vendor/bin/deptrac`) → 0 violations.

### 6.2 Mutation testing ✅
- `infection.json5` added (`source: src`, `testFramework: codeception`, threshold 80% MSI);
  `var/` gitignored for logs. Codeception adapter updated so Infection can drive the suite.
- **Acceptance:** `composer infection` reports MSI ≥ 80%.
- **Final-review correction (2026-08-20):** Infection runs Codeception with `settings:
  shuffle: true`, so the baseline executes tests in randomized order. The baseline
  **previously aborted before scoring any mutant** because `ClassReflectionTest` replaced
  the global static resolver singletons with `Stub::makeEmpty()` stubs (the trait-resolver
  stub returned class-name **strings**, not `ReflectionClass` objects) and never reset them.
  When `ClassReflectionTest` ran before `PerTraitDeriverTest` (shuffle-dependent), the
  latter received a string from `getTraitsRecursively()` and threw `TypeError` in
  `MethodExtractor::extract()`. **Fix:** `ClassReflectionTest::tearDown()` now restores the
  real resolvers after every test. After the fix, verified **MSI 85%** (≥ 80%) under
  shuffle. The proper (non-band-aid) fix — instance injection of resolvers — is tracked in
  the Phase 8 hardening backlog (H1); until then, the `tearDown()` reset is mandatory.

### 6.3 Coverage gate ✅
- Existing `composer coverage` (Codeception `--coverage`) reused; CI enforces it.

### 6.4 CI workflow ✅
- `.github/workflows/ci.yml` added: matrix PHP 8.4 / 8.5, steps `test:unit`, `cs:check`,
  `analyse`, `architecture` (deptrac), `infection` — every push gated.

### 6.5 Replace empty suites ✅
- Functional suite converted to a real integration smoke test
  (`tests/Functional/DeriveRealPackageTest.php`: derives `Nette\PhpGenerator\ClassType`,
  asserts the emitted interface exists, is self-contained, and exposes `getName()`).
- `module-phpbrowser` dropped; empty `Acceptance` suite deleted (`phpstan.neon` paths updated).
- **Acceptance:** Functional suite has ≥ 1 passing integration test.

---

## Phase 7 — Docs, ADRs & Release

> **Status: ✅ Completed (2026-08-20).** README "Documentation" section links the user
> guide, examples, and ADRs; `CHANGELOG.md`, `IMPLEMENTATION_PLAN.md`, and the four ADRs
> (0001–0004) are written; `docs/guide/` carries an in-depth user guide (index/features/
> architecture/usage). `REVIEW.md` rewritten to the final, all-gates-green state. Package is
> release-ready for `v0.1.0` (final review 2026-08-20).

**Goal:** honest, navigable docs and a tagged release that matches the shipped code.

### 7.1 Rewrite README
- "What it does" reflects implemented phases; roadmap lists Phase 4/5/6 plus the
  documented-but-out-of-scope OTel/LLM long tail. Closes `REVIEW.md` §5's gap directly.

### 7.2 `CHANGELOG.md`
- Keep a changelog mirroring `ConfigureIt/docs/CHANGELOG.md` style; one entry per release.

### 7.3 ADRs
- `docs/architecture/decisions/`: ADR-0001 use `nette Factory::fromMethodReflection`;
  ADR-0002 interface-per-trait composition; ADR-0003 static-method policy;
  ADR-0004 type-substitution strategy.

### 7.4 Tag releases
- Tag `v0.1.0` (Phase 1), then `v0.2.0` / `v0.3.0` / `v0.4.0` as phases land; verify dist valid.

### 7.5 Stretch (post-v1, document only)
- OTel-assisted shape discovery; LLM-assisted long-tail edge cases — out of scope
  for a production-ready v1, documented as future work only.

---

## Phase 8 — Design Hardening Backlog (post-v1)

> Derived from the final review (`REVIEW.md` §6–§7, 2026-08-20). None of these block the
> `v0.1.0` release — all five quality gates are green — but they should be scheduled before
> any parallel/CI-matrix run or a v2.

**Goal:** remove the latent design smells the review surfaced, so the package scales cleanly.

### 8.1 Instance-inject the resolvers (H1 — highest priority) ✅ COMPLETE (2026-08-20)
- **Done.** `ClassReflection` no longer holds **static mutable singletons** — the
  `set*Resolver()` setters and `getRecursive*Resolver()` static accessors were removed.
  Resolvers are now **instance state**, injected via the constructor / `ClassReflection::
  create()` (optional args), falling back to a single shared, immutable default set
  (`src/Reflection/ClassReflection.php`). This eliminates the entire class of cross-test
  state leakage that broke the Infection baseline under `shuffle: true`.
- `tests/Unit/Reflection/ClassReflectionTest.php` now passes stub resolvers via the
  constructor (named args) and needs no `tearDown()` reset.
- **Verified:** Infection baseline is stable under shuffle with **no** reset band-aid
  (MSI 86%). Acceptance met.

### 8.2 Remove or wire `LazyCreatable::createLazy()` (H2) ✅ COMPLETE (earlier)
- Already resolved in Phase 0: `LazyCreatable` ships only the abstract `create()` method;
  `createLazy()` does not exist and has zero usages. No action outstanding.

### 8.3 Clean per-trait `extends` form (H3 — cosmetic) ✅ COMPLETE (2026-08-20)
- **Done.** The per-trait composed main interface previously rendered
  `extends \TraitXInterface` (global name). `PerTraitInterfaceDeriver::derive()` now sets
  `extends` to each trait interface's **FQCN** (`<namespace>\<Name>`); Nette/`PhpNamespace`
  then simplifies it to a clean short name when rendering. Global-namespace classes keep the
  short name (no namespace to simplify into). `assertPerTraitSelfContained()` was updated to
  compare short names, and the two `getExtends()` expectations in `PerTraitDeriverTest` were
  updated to the FQCN form. Verified: `interface ClassDInterface extends TraitEInterface,
  TraitAInterface`.

### 8.4 Remove the empty stub script (H4) ✅ COMPLETE (2026-08-20)
- **Done.** Deleted `scripts/create_text_constructs.php` (no-op stub, imports only). No
  production/test code referenced it.

### 8.5 Optional: widen the Functional suite (H5) — open
- `tests/Functional/DeriveRealPackageTest.php` has 2 real-package tests. Expand with more
  real third-party targets and start asserting `generated/` output in CI. Still optional;
  all gates are green without it.

> **Phase 8 status (2026-08-20):** H1, H2, H3, H4 complete; only H5 remains as an optional
> follow-up. The package is clean of the review's design smells and fully green.

---

## Phase 9 — Edge-case & Optimization Hardening

> **Status: ✅ Completed (2026-08-20).** Steps 9.1–9.6 delivered: self/static/parent
> substitution (A1) + naming-collision detection (A5); `DerivationResult` (D1) +
> `with*()` config parity (D3) + typed exceptions (D4); default-value constant
> rewrite (A2), union/intersection adapter return wrapping (A3), `failOnEmpty` (A6);
> `includeConstants` / attribute + `@throws` pass-through / `onlyMethods`+`skipMethods`
> (B1–B3); transitive substitution, owned-namespace policy, root auto-discovery,
> combined family source (C1–C4); family + resolver memoization (E1/E2, F4), shared
> `SourceRenderer` (F1), centralized `Naming` (F3). All five gates green:
> `test:unit` 141/141, `cs:check` 0, `analyse` 0, deptrac 0, `infection` MSI ≥ 80%.
> F2 (AdapterGenerator/FamilyDeriver implement `DeriverInterface`) was **deliberately
> not force-fit** — their outputs are a class and a per-member map respectively, so a
> single `InterfaceType`-returning seam would be lossy; the shared `derive`/`deriveSource`
> method names are already present. Every step kept all five gates green (per `AGENTS.md`).

**Sequencing principle:** correctness first (A), then structured result + DX (D),
then surface richness (B), family depth (C), performance (E), maintainability (F).

### Step 9.1 — Substitute `self`/`static`/`parent` (A1, A5)
- `TypeSubstitutor` (and `MethodSignatureFactory`) rewrite `self`/`static`/`parent`
  in return/param types to the **derived interface FQCN** of the enclosing class
  (and its parent for `parent`). Fixes the `withSelf(): <Concrete>` / `static`
  leakage seen in `SelfStaticParentReturnClass.interface.php`.
- Gate behind a config flag (default = current behaviour) to avoid surprising
  callers on a minor release; add golden tests for self/static/parent.
- Also detect naming collisions (A5): if `FooInterface` already exists in the
  target namespace, raise a typed `NamingCollisionException` (see 9.2).

### Step 9.2 — Typed result & exceptions (D1, D3, D4)
- Introduce a `DerivationResult` value object (`interface`, `fqcn`, `shortName`,
  `methods[]`, `substitutionsApplied[]`, `collisions[]`) returned alongside (or
  instead of) the bare string by a new `deriveWithResult()` seam; keep `derive()`
  string API backward-compatible.
- Add `with*()` withers for **all** `DeriveConfig` flags (parity with
  `withIncludeStatic`).
- Replace generic `LogicException` with specific exceptions
  (`NamingCollisionException`, `EmptyDerivationException`, `LeakageDetectedException`).

### Step 9.3 — Default-value & return wrapping (A2, A3, A6)
- Rewrite constant references inside default-value expressions via the
  substitution map (the `SimpleBackedEnum::DEFAULT` leak).
- `AdapterGenerator::wrapperFor()` wraps **each** segment of union/intersection
  returns (A3); non-wrappable segments fall back to raw.
- Add `failOnEmpty` config (A6) so an empty derived surface is signalled, not silent.

### Step 9.4 — Surface richness (B1–B3)
- `includeConstants` flag lifts public constants into the interface (B1).
- Opt-in attribute pass-through (owned attributes only) + `throws` capture (B2).
- `onlyMethods` / `skipMethods` allow/deny lists and a `rename`/alias policy (B3).

### Step 9.5 — Family depth (C1–C4)
- Transitive type substitution (C1) and an owned-namespace substitution policy
  (C2, e.g. `App\*` → `App\Port\*`).
- Auto-discover a family from a root class graph (C3) instead of an explicit list.
- `deriveFamily()` returns a combined source / structured result (C4).

### Step 9.6 — Performance & maintainability (E1–E4, F1–F4)
- Memoize derived interfaces by `FQCN + config hash` (E1); in `deriveFamily` derive
  each base interface once then apply substitution at render (E2); share one Nette
  `Factory` (E3); cache resolver graph per `ReflectionClass` (E4).
- Extract a shared `SourceRenderer` (F1) consolidating the three duplicated
  `PhpNamespace`+`PhpFile` assemblies; make `FamilyDeriver`/`AdapterGenerator`
  implement `DeriverInterface` (F2); centralize naming helpers in a `Naming`
  service (F3); reduce `RecursiveTraitResolver` redundant re-walking (F4).
- Add golden/snapshot tests for enum/trait/abstract targets and an instantiable
  guard (§3.10 of the plan).

---

## Phase 10 — Correctness, Leakage & DX Deepening

> **Status: 🟢 Steps 10.1–10.16 complete (2026-08-22).** Deep leakage auditor (G1),
> per-mode collision/enum parity + per-trait short-name clash (G12), family honors
> caller `DeriveConfig` (G2), injectable instance cache replacing the static (G4),
> adapter static-method delegation (G3), `self`/`static`/`parent` constant rewriting
> (G6), owned-attribute allow-list + builtin skip (G7), opt-in phpdoc preservation
> with FQCN simplification (G8), the `analyze()` / `deriveMany()` / `diff()` DX
> APIs (G9), config presets + validation + uniform method-renames (G10/G11),
> family-of-adapters generation (G14 / EC12), re-expose owned interfaces
> (G16 / A4), shared `Factory` + de-dup reflection (G13 / EC20), and the `BuildMeta`
> value object + shared deriver base + injectable reflection cache (G15 / G18 / E5)
> value object + shared deriver base + injectable reflection cache (G15 / G18 / E5),
> and the `bin/derive` CLI (G17 / DX) are implemented and tested. All five gates
> green. Phase 10 is complete.
> Source of truth: the §4 edge cases (EC1–EC20) and §6 feature backlog (G1–G18) in
> `OPTIMIZATION_PLAN.md`. Every step must keep all five gates green (`test:unit`,
> `cs:check`, `analyse`, `architecture`, `infection` ≥ 80%) per `AGENTS.md`.

**Goal:** deepen the leakage invariant from "no `extends`" to "no unmapped foreign
type anywhere in the signatures", fix the two surprising Phase-9 behaviors (family
ignores caller config; `FamilyDeriver` uses a process-global static cache), and
raise DX for typed codebases (phpdoc/generics preservation, metadata + diff APIs,
config presets, rename policy, family-of-adapters, CLI).

**Sequencing principle:** correctness/leakage first (G1, G12, G2, G3, G4), then
target robustness (G5, G6, G7), then DX (G8, G9, G10, G11, G14, G17), then the
opt-in re-expose feature (G16), then performance/maintainability (G13, G15, G18).

### Step 10.1 — Deep leakage auditor (G1, EC1/EC11/EC17) ✅ COMPLETE
- New `LeakageAuditor` that, after substitution, scans every method's return and
  parameter type strings and flags any FQCN that is non-builtin, outside the target
  namespace, and not in an allowed / substitution list → raise
  `LeakageDetectedException` (new `LeakageDetectedException` variant or reuse).
- Gate behind a new `auditLeakage` config flag (default **off** initially to avoid
  surprising existing users / breaking golden files that currently leak);
  wire into `InterfaceDeriver::build()` and `PerTraitInterfaceDeriver`.
- Fix the leaking golden fixtures it surfaces or populate an owned-namespace
  allow-list so the family/per-trait outputs stay clean.

### Step 10.2 — Per-mode leakage/collision parity (G12, EC5/EC11/EC19) ✅ COMPLETE
- Run `Naming::assertNoCollision()` in per-trait **and** family modes; extend it to
  check `enum_exists` (not just `class_exists`/`trait_exists`).
- Fix the per-trait same-short-name clash: key the trait-interface map by FQCN
  internally, render short names only at output (`PerTraitInterfaceDeriver.php:72`).
- Run the deep auditor (10.1) in family mode so unmapped sibling types are caught.

### Step 10.3 — Family honors caller `DeriveConfig` (G2, EC2) ✅ COMPLETE
- Add a `DeriveConfig $config` parameter to `FamilyDeriver::derive()` /
  `deriveFromRoot()` / `deriveSource()`; thread it into `deriveMember()` (replacing
  the hard-coded `new DeriveConfig()` at `FamilyDeriver.php:115-116`).
- Update `DeriveIt::deriveFamily()` / `deriveFamilyFromRoot()` /
  `deriveFamilySource()` to accept and forward the config.

### Step 10.4 — Injectable cache instead of static (G4, EC3) ✅ COMPLETE
- Remove `private static array $cache` from `FamilyDeriver` (`FamilyDeriver.php:33`);
  accept an optional cache (instance array / PSR-16) via the constructor and add
  `clearCache()`. This also un-masks Infection mutants masked by cross-call caching
  and removes a Phase-8-principle regression.

### Step 10.5 — Adapter static-method support (G3, EC4) ✅ COMPLETE
- Either emit **static** delegating methods that forward to the adaptee class, or
  hard-error when `deriveAdapter()` is called with a config that produces static
  interface methods. Prefer static-delegation; add a golden test.

### Step 10.6 — Full derivation-target support matrix (G5, EC13)
- Add `enum_exists` to `FamilyDeriver::discover()` (`FamilyDeriver.php:210`) and to
  collision detection. Document supported targets (class / trait / enum / abstract /
  readonly). Add golden/snapshot tests for each. Optional `deriveFromNonInstantiable`
  guard for interface/readonly inputs.

### Step 10.7 — Complete default-value constant rewriting (G6, EC6/EC7) ✅ COMPLETE
- Rewrite `self::CONST` references via the derived interface (currently `self::` is
  left verbatim by `TypeSubstitutor::rewriteConstantExpression()`).
- For unmapped foreign constant refs, either drop the default or keep it behind an
  `ownConstantsOnly` leakage policy. Document the scalar-only constant lifting limit.

### Step 10.8 — Attribute allow-list + builtin skip (G7, EC8) ✅ COMPLETE
- Add an `ownedAttributes` allow-list; when `attributePassThrough` is on, keep only
  owned attributes and **always** drop `#[Override]` and other builtins.

### Step 10.9 — Robust phpdoc preservation (G8, EC9/EC10) ✅ COMPLETE
- Opt-in `preserveDocblocks`: re-emit `@param` / `@return` / `@throws` /
  `@template` / `@deprecated` with FQCN simplification + dedupe; fix the brittle
  `@throws` regex (generics in type, native PHP 8.4 `throws`).

### Step 10.10 — `analyze()` / `deriveMany()` / dry-run diff (G9, EC15, D2/D5) ✅ COMPLETE
- `DeriveIt::analyze($class, $config): DerivationResult` (no rendering) for
  tooling/IDE use — `InterfaceDeriver::analyze()` builds the structured result with
  `source: null` so metadata is cheap; `DerivationResult::$source` is now nullable.
- `DeriveIt::deriveMany(iterable, $config): array<class-string, DerivationResult>`
  batch entry point, keyed by FQCN, each result carrying the rendered source.
- `DeriveIt::diff($class, $existingSource, $config): DeriveDiff` for CI drift
  detection — `DeriveDiff::compute()` reports `drift` (exact-string equality) plus a
  self-contained LCS-based unified diff (`DeriveDiff` value object, no dev deps).

### Step 10.11 — Config presets + validation + rename (G10/G11, EC14/EC16, B4) ✅ COMPLETE
- `DeriveConfig::strict()` / `::forAdapter()` / `::forFamily()` presets added;
  the existing immutable withers form the fluent builder. Contradictory
  `onlyMethods`/`skipMethods` overlap and empty `methodRenames` targets are
  rejected in the constructor (EC16).
- `methodRenames` (`array<string,string>`) applied uniformly in flat
  (`InterfaceDeriver`), per-trait (`PerTraitInterfaceDeriver` trait + main
  interfaces), and family (`FamilyDeriver` → `InterfaceDeriver`) modes. A rename
  that would make two methods share a final name throws
  `NamingCollisionException` (G11 / B4 / EC14).

### Step 10.12 — Family-of-adapters generation (G14, EC12) ✅ COMPLETE
- `DeriveIt::deriveFamilyAdapter(classes, wrapperMap, config)` derives the whole
  family of interfaces (via `FamilyDeriver`) and emits a delegating adapter per
  member in a single source (reusing `AdapterGenerator`). Members that return
  another member's derived interface are wired to that member's adapter
  automatically; the optional `wrapperMap` overrides/extends the wiring. Members
  are de-duplicated and grouped by namespace so the single output is valid.

### Step 10.13 — Re-expose owned interfaces (G16, A4, §3.3) ✅ COMPLETE
- `DeriveConfig::reExposeOwnedInterfaces` (opt-in `list<string>` allow-list) adds
  `extends` for owned interfaces the adaptee implements (e.g. `Countable`).
  Third-party interfaces stay out; the core leakage invariant holds by default.
  `InterfaceDeriver::assertSelfContained()` now permits only allow-listed extends;
  interfaces listed but not actually implemented are skipped (the adaptee could not
  satisfy them). Applies to flat + family derivation; the per-trait main interface
  keeps its strict "extends exactly derived trait interfaces" invariant.

### Step 10.14 — Performance: shared `Factory` + de-dup reflection (G13, EC20, E3) ✅ COMPLETE
- `FamilyDeriver` now accepts an optional `?Factory` and threads a single instance
  through every member's `MethodSignatureFactory` (was `new`-ing one per member).
  `InterfaceDeriver` / `PerTraitInterfaceDeriver` also accept an optional `?Factory`
  so a caller can share one instance end-to-end. `Factory` is `final`/stateless, so
  this is a structural object-churn reduction, not a behavior change.
- `deriveFromRoot()` and `deriveSource()` now reuse the `ReflectionClass` instances
  produced by `discover()` (via a new `deriveReflections()` core) instead of
  re-reflecting each class and re-running `class_exists` — eliminating the EC20
  double-reflection path in family scans.

### Step 10.15 — Maintainability: `BuildMeta` + shared base + PSR cache (G15/G18, E5) ✅ COMPLETE
- `InterfaceDeriver::build()` now returns a typed `BuildMeta` value object (interface,
  methods, substitutions, constants) instead of the untyped `(object){...}` shape, so
  the deriver internals and `deriveWithResult()`/`analyze()` consume a documented type.
- `InterfaceDeriver` and `PerTraitInterfaceDeriver` extend a shared `AbstractDeriver`
  base that owns substitutor seeding (`self`/`static`/`parent` + concrete class/parent
  FQCN rewriting — A1), the uniform `shouldSkipMethod()` filter, and reflection
  memoization — removing the duplicated plumbing and keeping the two modes in lockstep.
- An injectable `ReflectionCacheInterface` (`InMemoryReflectionCache` default, instance
  scoped — never static) memoizes `ReflectionClass` instances so whole-repo scans never
  re-reflect a type. A `PsrCacheReflectionCache` PSR-6 adapter wraps any
  `Psr\Cache\CacheItemPoolInterface`, persisting a "reflected" marker across processes;
  `get()` returns the in-process object when present (G18 / E5).

### Step 10.16 — CLI `bin/derive` (G17, DX) ✅ COMPLETE
- A small command to derive a class/file to stdout or a path for ad-hoc ports and
  dogfooding; exercises the public API end-to-end.
- The bin/derive script wraps src/Cli/DeriveCommand.php, a unit-tested command that parses argv, resolves a class or PHP file to an FQCN, and dispatches to DeriveIt in five modes (flat, per-trait, adapter, family, family-adapter). Output goes to stdout, a single --out=FILE, or a --out=DIR/ with one file per member.

---

## Phase 11 — Refactoring & Modernization (planned)

> **Status: 🟢 Implemented (2026-08-22); F12 lazy adapter deferred (optional, post-v1).**
> Implements the findings register in
> `REVIEW.md` (F1–F13). Detailed execution spec: `docs/REFACTORING_PLAN.md`
> (steps R.1–R.13). Every step must keep all five gates green (`test:unit`,
> `cs:check`, `analyse`, `architecture`, `infection` ≥ 80%) per `AGENTS.md`.
> No user-visible derivation-output changes are intended; the only API surface
> changes are *additions* (new interfaces, a factory, two enums) plus internal
> de-duplication.

**Goal:** move the package from "sound" to "exemplary" on SOLID/DRY, hexagonal,
and modern-PHP criteria. Sequencing (quick wins → structural → docs/optional):

```
F7/F8/F9/F10/F11  (no-API-change quick wins)
  └─ F1/F6         (internal DRY + metadata correctness)
       └─ F4/F5    (enums + CLI parser split)
            └─ F2  (deriver interfaces + DeriveItFactory)
                 └─ F3/F12/F13 (docs, optional lazy adapter, CLI trust note)
```

### Step 11.1 — Collapse `DeriveConfig` withers (F1, R.1) 🟡
- Add a private `copyWith(array $overrides): self` to `src/Generator/DeriveConfig.php`
  that reads current state via `get_object_vars($this)` and re-constructs
  (`readonly`-safe, preserves constructor validation). Replace each of the 17
  `with*()` bodies with a one-liner delegating to `copyWith`.
- **Acceptance:** `DeriveConfig.php` ~590 → ~180 lines; all 17 methods + signatures
  unchanged; golden/snapshot tests byte-identical. **No public API change.**

### Step 11.2 — Secondary deriver interfaces + `DeriveItFactory` (F2, R.2) 🟡
- New `src/Contract/Generator/FamilyDeriverInterface.php` (`derive`/`deriveFromRoot`/
  `deriveSource`/`clearCache`) and `src/Contract/Generator/AdapterGeneratorInterface.php`
  (`generate`/`generateSource`). `FamilyDeriver` and `AdapterGenerator` implement them.
- New `src/DeriveItFactory.php` in the **Entry** layer (deptrac-clean: Entry →
  Generator + Contract) that assembles the four derivers, optionally sharing one
  `ReflectionCacheInterface` and one Nette `Factory`. `DeriveIt` may delegate to a
  default factory (BC: keep static methods).
- **Acceptance:** a test builds all derivers via the factory with a shared cache and
  asserts identical output; `architecture` 0 violations. Controlled API *addition*.

### Step 11.3 — Refresh drifted architecture docs (F3, R.3) 🟡
- `docs/guide/architecture.md`: replace `setRecursive*Resolver()` wording with the
  instance-injection design; add the **Cli** layer; correct the "three derivers
  implement `DeriverInterface`" sentence (now four derivers across two interfaces
  after 11.2); note `DeriveIt` is a static facade over injectable derivers.

### Step 11.4 — `DeriveMode` + `Visibility` enums (F4, R.4) 🟡
- New `src/Generator/DeriveMode.php` (FLAT/PER_TRAIT/ADAPTER/FAMILY/FAMILY_ADAPTER)
  and `src/Generator/Visibility.php` (PUBLIC/PROTECTED). `DeriveConfig::$visibility`
  typed to `Visibility` (keep string constants as backward-compat aliases or update
  the 2 call sites in `DeriveCommand::buildConfig`); `DeriveCliInput::$mode` typed to
  `DeriveMode`; CLI token → enum mapping lives in 11.5.
- **Acceptance:** no bare `'flat'`/`'public'` literals remain in `src/` outside enum
  `value` declarations.

### Step 11.5 — Split `DeriveCommand::parse()` + declarative options (F5, R.5) 🟡
- Extract an `ArgParser` value object (Cli layer) holding a declarative option table
  (`value`/`flag`/`map` callbacks) replacing the inline `valueOptions` array + 25-case
  `switch`. `DeriveCommand::parse()` becomes a thin loop; `runDerive()` switches on the
  `DeriveMode` enum so the allowed-mode list lives in exactly one place. Keep exit
  codes/messages identical.
- **Acceptance:** `DeriveCommandTest` (13 tests) still passes; adding a mode = add enum
  case + one option row (OCP).

### Step 11.6 — Family map in `DerivationResult` (F6, R.6) 🟡
- `InterfaceDeriver::build()` (via `deriveWithSubstitutor`) puts the effective
  substitution map (not just `config->typeSubstitutionMap` + self/static/parent) into
  `BuildMeta::$substitutions`, so family-mode `deriveWithResult()` reports the full
  cross-reference map. Flat mode output unchanged.

### Step 11.7 — Delete duplicate `@param` (F7, R.7) ✅ quick
- Remove the duplicated `@param list<string> $reExposeOwnedInterfaces …` line in
  `src/Generator/DeriveConfig.php`.

### Step 11.8 — `0o755` const + drop `@` (F8, R.8) ✅ quick
- `src/Cli/DeriveCommand.php`: add `private const OUTPUT_DIR_MODE = 0o755;` and replace
  the two `!@mkdir($p, 0o777, true) && !is_dir($p)` with `!mkdir($p, self::OUTPUT_DIR_MODE, true) && !is_dir($p)`.

### Step 11.9 — Hoist magic literals to constants (F9, R.9) ✅ quick
- `src/Generator/FamilyDeriver.php`: extract `private const VENDOR_PREFIXES = [...]`
  for `isUserland()`. `src/Reflection/PsrCacheReflectionCache.php`: extract
  `private const CACHE_KEY_PREFIX = 'deriveit.reflection.'`.

### Step 11.10 — Remove last `else`/`elseif` (F10, R.10) ✅ quick
- `src/Cli/DeriveCommand.php`: convert the `elseif` at :143 (fold into the `ArgParser`
  table / guard clause) and the `else` at :396 (early default assignment + guard) so
  `grep` for `else`/`elseif` in `src/` returns zero code matches.

### Step 11.11 — Reuse `SourceRenderer` (F11, R.11) ✅ quick
- Replace `DeriveIt::renderInterface()` (`src/DeriveIt.php:222`) with a call to
  `SourceRenderer::render()`; delete the private method. Rendered output byte-identical.

### Step 11.12 — Optional lazy-object adapter (F12, R.12) 🟡 optional
- Add an opt-in `AdapterGenerator` lazy-adaptee mode via `ReflectionClass::
  newLazyGhost()`/`newLazyProxy()` (gated flag; default output unchanged). Mark
  optional; may slip to post-v1.

### Step 11.13 — Document CLI trust model (F13, R.13) ℹ️
- Add a one-line trust note to `DeriveCommand::writeHelp()` and
  `docs/guide/usage.md` (the CLI autoloads operator-supplied class names).

> **Phase 11 status (implemented, 2026-08-22):** all steps shipped except the
> optional R.12 lazy-object adapter (deferred to post-v1). Every step merged behind
> green gates (`test:unit` 229, `cs:check` 0, `analyse` 0, `architecture` 0,
> `infection` ≥ 80% MSI). Full status in `REFACTORING_PLAN.md` §Implementation
> status / §Definition of Done.

---

## Phase 12 — Residual Refactoring (REVIEW F-1 … F-10, planned)

> **Status: 🟡 Planned (2026-08-22).** Implements the residual findings from the
> post-Phase-11 review (`REVIEW.md` §11, F-1 … F-10). Detailed execution spec:
> `docs/REFACTORING_PLAN.md` (steps RP-1 … RP-10). Every step must keep all five
> gates green (`test:unit`, `cs:check`, `analyse`, `architecture`, `infection ≥ 80%`)
> per `AGENTS.md`. No user-visible derivation-output change is intended; the only
> API surface changes are *additions* (new interfaces/constants) plus internal
> de-duplication. Items marked ◐ are optional and may be skipped with a one-line
> rationale in the commit.

**Goal:** promote the package from "sound" to "exemplary" on control-flow hygiene,
DRY, and (optionally) DIP/defense-in-depth — closing the last ten review findings
so `REVIEW.md` can mark each F-* as addressed/optional.

**Sequencing (quick wins → optional seams):**
```
RP-1, RP-2          (Medium: magic literals → constants)
   └─ RP-5, RP-6   (Low DRY: Naming::shortName, LazyCreatable default)
        └─ RP-8    (Low perf: FamilyDeriver queue/config identity)
             └─ RP-10  (Low consistency: facade → factory)
                  └─ RP-3, RP-4, RP-7, RP-9   (◐ optional seams / defense-in-depth)
```

### Step 12.1 — Named exit-code constants (F-1, RP-1) 🟡 Medium
- `src/Cli/DeriveCommand.php`: add
  `private const EXIT_SUCCESS = 0; EXIT_USAGE = 1; EXIT_DERIVATION = 2; EXIT_OUTPUT = 3;`
  (next to `OUTPUT_DIR_MODE`) and replace every `return 0/1/2/3;` in `execute()`
  with the matching constant. Update the docblock "Exit codes:" line.
- **Acceptance:** no bare `0/1/2/3` returns remain; `DeriveCommandTest` (13 tests)
  green with identical numeric codes; `cs:check`/`analyse` clean.

### Step 12.2 — Named type-separator constants (F-2, RP-2) 🟡 Medium
- `src/Generator/AdapterGenerator.php::bodyFor()`: hoist `'void'`→`TYPE_VOID`,
  `'|'`→`TYPE_SEPARATOR_OR`, `'&'`→`TYPE_SEPARATOR_AND` (use them in the
  `===`/`str_contains`/`preg_split` comparisons) so the re-parsed return-type string
  uses no bare literals. Also add `private const OVERRIDE_ATTRIBUTE = 'Override';` in
  `MethodSignatureFactory::filterAttributes` and use it for the `'Override'` check.
- **Acceptance:** no bare `'void'`/`'|'`/`'&'`/`'Override'` literals remain outside
  the constant declarations; golden `tests/_expected/Tree.adapter.php` byte-identical;
  `AdapterGeneratorTest` green; `infection` ≥ 80%. (Threading the real `ReflectionType`
  is an optional, larger alternative — out of scope to keep output unchanged.)

### Step 12.3 — `Naming::shortName()` DRY helper (F-5, RP-5) 🟡 Low
- `src/Generator/Naming.php`: add `public static function shortName(string $fqcn): string`
  returning the substring after the last `\\` (or the whole string if none).
- Replace the inline `substr($fqcn, (int) strrpos($fqcn, '\\') + 1)` copies in
  `DeriveCommand::fileNameFor` (`src/Cli/DeriveCommand.php:308`),
  `PerTraitInterfaceDeriver::assertPerTraitSelfContained`,
  `FamilyDeriver` constant handling, and `LeakageAuditor::allowedNamespaces` with
  `Naming::shortName($fqcn)`.
- **Acceptance:** no inline FQCN→short-name surgery remains outside `Naming`; all
  gates green; per-trait / leakage / family tests pass.

### Step 12.4 — Default `create()` in `LazyCreatable` (F-6, RP-6) 🟡 Low
- `src/Behavior/Utility/LazyCreatable.php`: replace `abstract public static function create(): self;`
  with a concrete `public static function create(): self { return new self(); }`
  (`new self()` in a trait binds to the using class).
- Delete the duplicated `public static function create(): self { return new self(); }`
  blocks from `RecursiveClassResolver`, `RecursiveInterfaceResolver`,
  `RecursiveTraitResolver`.
- **Acceptance:** no resolver-level `create()` definitions remain; resolver /
  `ClassReflectionTest` tests green.

### Step 12.5 — `FamilyDeriver` perf micro-fixes (F-8, RP-8) 🟡 Low
- `src/Generator/FamilyDeriver.php::discover()`: replace `array_shift($queue)` in the
  `while` loop with an index pointer (`$i = 0; while ($i < count($queue)) { $reflection = $queue[$i++]; … }`)
  to remove the O(n²) dequeue. Output identical.
- `configHash()` re-`serialize()` and `LeakageAuditor::simplifyDocblock()` per-method
  map loop: **measure-first** — change only if a benchmark shows pressure; otherwise
  note "no change needed" in the commit.
- **Acceptance:** family / family-adapter golden + unit tests byte-identical and
  green; `infection` ≥ 80%.

### Step 12.6 — Route `DeriveIt` facade through `DeriveItFactory` (F-10, RP-10) 🟡 Low
- `src/DeriveIt.php`: add a private static `factory(DeriveConfig $config): DeriveItFactory`
  accessor and rewrite each static method to obtain its deriver via the factory
  (`interfaceDeriver()` / `perTraitDeriver()` / `familyDeriver()` /
  `adapterGenerator(...)`), dropping the inline `new InterfaceDeriver` / `new FamilyDeriver`
  / `new AdapterGenerator` / `new PerTraitInterfaceDeriver` constructions. Keep all
  current signatures and the `SourceRenderer` usage.
- **Acceptance:** `grep` for `new (InterfaceDeriver|FamilyDeriver|AdapterGenerator|PerTraitInterfaceDeriver)` in
  `DeriveIt.php` returns no matches; all facade tests + golden files byte-identical;
  all five gates green. (Documentation-only alternative: if routing is unwanted,
  add a one-line note that `DeriveIt` is an intentional thin wrapper and
  `DeriveItFactory` is the injectable composition root — choose one, not both.)

### Step 12.7 — ◐ Optional `MethodSignatureFactory` / `SourceRenderer` ports (F-3, RP-3)
- New `Contract/Generator/MethodSignatureFactoryInterface.php`
  (`fromMethod(ReflectionMethod, ?TypeSubstitutor = null): Method`) and
  `Contract/Generator/SourceRendererInterface.php`
  (`render` / `renderMany`). Have the two concretions `implements` them; type-hint
  the interfaces in `InterfaceDeriver` / `PerTraitInterfaceDeriver` / `FamilyDeriver`
  (defaulting to the current concretions) and in `DeriveItFactory`. No behavior change.
- **Acceptance:** `architecture` 0 violations; golden files byte-identical. May be
  skipped (F-3 is optional) with a one-line rationale.

### Step 12.8 — ◐ Optional `CommandInterface` (F-4, RP-4)
- New `Contract/Cli/CommandInterface.php` with `execute(array $args): int`;
  `DeriveCommand implements CommandInterface`. Optional route for `bin/derive`.
- **Acceptance:** `architecture` 0 violations; `DeriveCommandTest` green.

### Step 12.9 — ◐ Optional `WeakReference` reflection cache (F-7, RP-7)
- New `src/Reflection/WeakReflectionCache.php` implementing `ReflectionCacheInterface`
  (stores `WeakReference::create($reflection)`, returns `$ref->get()`); keep
  `InMemoryReflectionCache` as the default. Add a test for GC-driven `null` return.
- **Acceptance:** new file compiles; default path unchanged; gates green. May be
  skipped (F-7 is optional) if no long-running scan use case exists.

### Step 12.10 — ◐ Optional `--out` path guard (F-9, RP-9)
- `src/Cli/DeriveCommand.php`: add an `assertSafeOutputPath(string $path, ?string $baseDir)`
  that rejects traversal / symlink escapes when an optional `--base-dir=DIR` is
  supplied (threaded through `ArgParser` + `DeriveCliInput`); preserve current
  trusted-operator behavior when `--base-dir` is absent.
- **Acceptance:** existing `DeriveCommandTest` green; new tests assert rejection of
  escapes and allowance of safe paths. May be skipped (F-9 is optional, covered by
  the documented trust model).

> **Phase 12 status (implemented 2026-08-23):** steps 12.1–12.6 (RP-1, RP-2, RP-5,
> RP-6, RP-8, RP-10) shipped, plus optional RP-4 (CommandInterface), RP-7
> (WeakReflectionCache), and RP-9 (`--base-dir` output guard). RP-3
> (MethodSignatureFactory/SourceRenderer ports) was deliberately **skipped** — it
> would require also extracting a `TypeSubstitutorInterface` to avoid a
> `Contract → Generator` coupling, which is larger than the optional finding
> warrants; the composition-root `DeriveItFactory` already exposes the four primary
> derivers behind interfaces. All work keeps the five gates green (`test:unit` 247 /
> `cs:check` 0 / `analyse` 0 / `architecture` 0 / `infection` Covered Code MSI 84%)
> and leaves generated output byte-identical. Detailed steps, acceptance, and
> rollback notes live in `REFACTORING_PLAN.md` (RP-1 … RP-10, with an
> *Implementation status* table).

---

## Cross-cutting: Timeline, Risks, Definition of Done

### Critical path

```
Phase 0
  └─ Phase 1 (v0.1.0: FLAT DERIVE — the shippable core)
       └─ Phase 2 (filters/config)
            └─ Phase 3 (per-trait)         → v0.2.0
                 └─ Phase 4 (type substitution) → v0.3.0
                      └─ Phase 5 (adapters/dogfood)  → v0.4.0
                            └─ Phase 6 (deptrac/mutation/CI)
                                 └─ Phase 7 (docs/ADRs/release)
                                      └─ Phase 8 (design hardening backlog, post-v1)
                                            └─ Phase 9 (edge-case & optimization hardening)
                                                 └─ Phase 10 (correctness, leakage & DX deepening)
                                                   └─ Phase 11 (refactoring & modernization, REVIEW F1–F13)
                                                        └─ Phase 12 (residual refactoring, REVIEW F-1 … F-10)
   ```

### Minimum viable production release
- Phase 0 + Phase 1 + Phase 6 (CI/deptrac) + Phase 7 (README/changelog) already yields a
  defensible **v0.1.0**. Phases 3–5 are feature depth and can be trimmed.
- This directly addresses `REVIEW.md` §8: fastest path to a credible v0.1 is
  (a) make `composer analyse` pass, (b) ship a minimal real generator, (c) align README with reality.

### Top risks & mitigations
- **Nette `Factory::fromMethodReflection` edge cases** (intersection types, enums) → golden-file
  snapshot tests over all 60 fixtures catch regressions immediately.
- **`getMethods()` conflict resolution differs from intended trait precedence** → explicit
  `ConflictingMethodTrait` fixture + assertion.
- **Static mutable resolver singletons** in `ClassReflection` (`REVIEW.md` §6.1-1) → *triggered*
  during the final review: under Infection's `shuffle: true`, the un-reset stub in
  `ClassReflectionTest` contaminated later tests and aborted the Infection baseline. **Resolved
  in Phase 8 (H1):** `ClassReflection` now uses instance-injected resolvers with an immutable
  shared default — no mutable static state remains, so the leak cannot recur.
- **Scope creep** (adapters/OTel/LLM) blocks v1 → trim to Phase 0+1+6+7 for v0.1.0; rest is roadmap.
- **Empty Acceptance suite with `module-phpbrowser`** → replace with integration smoke test;
  drop php-browser (6.5).

### Definition of Done (production-ready v1)
> **Status (final review + Phase 8 hardening, 2026-08-20): all criteria met.** `test:unit`
> 113/113, `cs:check` 0, `analyse` 0, deptrac 0 violations, `infection` MSI 86% (≥ 80%), on
> PHP 8.5 (8.4+ declared); `derive($class)` self-contained for every fixture; README/
> CHANGELOG/4 ADRs written; Functional dogfood test derives a real `nette/php-generator`
> class. Phase 8 hardening (H1–H4) complete. Ready to tag `v0.1.0`.

- [x] `composer test:unit`, `cs:check`, `analyse` all green on PHP 8.4 & 8.5.
- [x] `vendor/bin/deptrac` green; `infection` MSI ≥ threshold (verified 86%).
- [x] `derive($class)` produces a self-contained interface for every fixture; golden files committed.
- [x] Flat derivation (Phase 1) + config filters (Phase 2) shipped; per-trait & substitution
      (Phases 3–4) and adapters (Phase 5) released.
- [x] README, CHANGELOG, and ≥ 1 ADR written (4 ADRs); `v0.1.0` ready to tag.
- [x] CI workflow gates every push; a real `nette/php-generator` adapter derives and runs (dogfood).

---

*Companion documents: `REVIEW.md` (current status & gaps), `REFACTORING_PLAN.md`
(detailed refactoring execution spec, REVIEW findings F1–F13), `OPTIMIZATION_PLAN.md`
(in-depth feature review, edge-case analysis & enhancement backlog), and
`IMPLEMENTATION_PLAN.md` (milestone summary). This roadmap is the detailed execution
spec for the latter.*
