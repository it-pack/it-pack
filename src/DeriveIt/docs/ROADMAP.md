# DeriveIt — Implementation Roadmap

> Detailed phase plan companion to `REVIEW.md` and `IMPLEMENTATION_PLAN.md`
> Prepared: 2026-08-14 · Last reviewed: 2026-08-20 (final review) · Status: **Phases 0–7 complete; release-ready** · Package: `it-pack/derive-it`

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
10. Cross-cutting: Timeline, Risks, Definition of Done

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

*Companion documents: `REVIEW.md` (current status & gaps) and `IMPLEMENTATION_PLAN.md`
(milestone summary). This roadmap is the detailed execution spec for the latter.*
