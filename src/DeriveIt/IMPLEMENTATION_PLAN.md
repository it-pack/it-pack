# DeriveIt — Implementation Plan (to production-ready)

> Created: 2026-08-14
> Companion doc: `REVIEW.md` (current status & gaps)
> Target: a library that *derives clean, self-contained interfaces (and optionally adapters) from third-party/legacy PHP classes*, with green CI, honest docs, and a real dogfood target (`nette/php-generator`, and later the Dagger PHP SDK).

---

## 0. Guiding principles

1. **Never break CI.** Every phase ends with `test:unit`, `cs:check`, and `analyse` green. The repo has Codeception + PHP-CS-Fixer + PHPStan (level 5) wired via composer scripts; we keep them green and *add* coverage/mutation/architecture gates.
2. **Reuse, don't re-invent the type grammar.** The README is right that hand-rolling union/intersection/nullable/variadic/by-ref/readonly/enum types is the hard part — but `nette/php-generator`'s `Factory::fromMethodReflection()` already does it correctly. The *derivation* logic (what belongs in the interface, what doesn't, how it's shaped) is our job; the *introspection→source* round-trip is Nette's.
3. **No leaking of the adaptee's hierarchy.** A derived interface must `extends` / reference *nothing* from the adaptee's own parent classes, traits, or interfaces. This is the core invariant and the primary thing tests must assert.
4. **Configurable, not opinionated-by-accident.** Every filtering decision (visibility, static, magic methods, interface-per-trait, type substitution) is a `DeriveConfig` flag with a sane default, so behavior is explicit and testable.
5. **Smallest shippable product first.** Ship *flat interface derivation* (Phase 1) as v0.1, then layer features. Don't block v0.1 on per-trait / substitution / adapters.

---

## 1. Current baseline (from `REVIEW.md`)

- ✅ Graph-walking resolvers (`src/Resolver/*`, `src/Reflection/ClassReflection.php`) — tested, correct.
- ✅ Unit tests green; CS green.
- ❌ PHPStan red (5 `src/` issues, 10 test false-positives).
- ❌ No `require.php` constraint; Codeception in `require`; unused `nikic/php-parser` direct dep.
- ❌ **No generator** — the interface/adapter emission doesn't exist in `src/`; only a broken scratch script (`scripts/generate_reflection_adapter.php`).
- ❌ ~60 unused fixtures; empty `generated/`; empty Functional/Acceptance suites.

---

## 2. Phased milestones

### Phase 0 — Foundation & CI greening (prerequisite, ~0.5 day)
*Goal: the project's own tooling is green and packaged correctly. Nothing behavioral changes.*

| # | Task | Detail / reference | Acceptance |
|---|------|--------------------|------------|
| 0.1 | Fix PHPStan `src/` errors | `src/Reflection/ClassReflection.php:33,49,65` short ternary → `self::$x ??= …`; `src/Resolver/RecursiveTraitResolver.php:24` `empty()` → strict `=== []`. | `composer analyse` no longer reports `src/` errors. |
| 0.2 | Quiet PHPStan test false-positives | Configure the Codeception-aware PHPStan extension (or `ignoreErrors` with `method.unused`/`shipmonk.deadMethod` scoped to `tests/`) so `#[DataProvider]` methods stop being flagged. **Do not delete the data providers.** | `composer analyse` exits 0. |
| 0.3 | Declare PHP platform | Add `"php": ">=8.4"` to `composer.json` `require` (code uses `ReflectionClass::newLazyProxy()` — `src/Behavior/Utility/LazyCreatable.php:15`). | `composer validate` clean; PHP-CS-Fixer warning gone. |
| 0.4 | Fix dependency placement | Move `codeception/module-phpbrowser`, `codeception/module-asserts` to `require-dev`; drop direct `nikic/php-parser` (transitive via `nette/php-generator`); keep `nette/php-generator` in `require`. | `composer why` shows test deps only in dev; `composer install --no-dev` has no Codeception. |
| 0.5 | Dist hygiene | Add `.gitattributes` (`export-ignore` for `tests/`, `fixtures/`, `scripts/`, `var/`, `generated/`, `*.md` except README). | Dist archive excludes dev files. |

### Phase 1 — Core: flat interface derivation (the actual product) — v0.1.0
*Goal: given a class, produce a clean `InterfaceType` with the effective public method surface and zero adaptee leakage.*

| # | Task | Detail | Acceptance |
|---|------|-------|------------|
| 1.1 | Define generator contract | New `src/Contract/Generator/DeriverInterface.php`: `derive(ReflectionClass\|string $class): Nette\PhpGenerator\InterfaceType` and `deriveSource(…): string`. Keep resolvers as the graph source. | Interface compiles; PHPStan green. |
| 1.2 | Method extraction | New `src/Generator/MethodExtractor.php`. **Flat mode:** call `getMethods(ReflectionMethod::IS_PUBLIC)` on the *concrete* `ReflectionClass` — PHP already resolves parent+trait conflicts (handles the `ConflictingMethodTrait` fixture). Reuse `ClassReflection::getClassesRecursively()`/`getTraitsRecursively()` only for per-trait mode (Phase 3). | Returns effective public method set; conflicts resolved (assert via `ConflictingMethodTrait`). |
| 1.3 | Reflection→signature mapping | New `src/Generator/MethodSignatureFactory.php` using `Nette\PhpGenerator\Factory::fromMethodReflection(ReflectionMethod $m): Method`. This yields correct union/intersection/nullable/variadic/by-ref/readonly-aware parameters and return types for free. Then `$method->setAbstract(true)->setBody(null)->setVisibility('public')`. | One `Method` per public method, body stripped, types preserved. |
| 1.4 | Interface assembly + leakage guard | New `src/Generator/InterfaceDeriver.php`. Build `InterfaceType`, drop `__construct`/`__destruct`, assert the assembled type `extends`/references nothing external (verify `getExtends()` empty and no FQCN outside the target namespace leaks). Render via `PhpFile` + `PhpNamespace` (Nette auto-generates `use` statements). | Output contains only the derived interface; no `extends`/external refs. |
| 1.5 | Public entry + static policy | `DeriveIt::derive($class): string` facade; default **drops static methods** (interfaces-as-ports shouldn't force static). Add `includeStatic` flag (PHP 8.1+ allows static interface methods; safe since target ≥8.4). | Static methods excluded by default; included when flag set. |
| 1.6 | Snapshot tests over fixtures | Use the ~60 existing fixtures (`fixtures/ReadonlyClass.php`, `UnionTypeClass.php`, `IntersectionTypeClass.php`, `VariadicAndByRefClass.php`, `ConsumesEnumParamClass.php`, …). Generate each interface, compare to committed `tests/_expected/<Fixture>.interface.php` golden files (Codeception `Snapshot` or simple file diff). This is the real test of the type grammar. | Every fixture produces a deterministic, correct interface; golden files committed. |
| 1.7 | Fix/retire scratch script | Replace `scripts/generate_reflection_adapter.php` usage with the real deriver; delete dead code (`exit;` + unreachable lines, double `return`). Keep a thin `scripts/derive.php` demo if useful. | No unreachable code; script uses the public API. |

**v0.1 Definition of Done:** `composer test:unit`, `cs:check`, `analyse` green; `derive(SomeClass::class)` returns a valid PHP interface source for all 60 fixtures; README "What it does" rewritten to describe *only* what Phase 1 delivers.

### Phase 2 — Filtering controls & method pipeline — v0.1.x
*Goal: make derivation decisions explicit and configurable.*

| # | Task | Detail | Acceptance |
|---|------|-------|------------|
| 2.1 | `DeriveConfig` value object | `src/Generator/DeriveConfig.php`: `visibility` (public default; optional protected), `includeStatic`, `includeMagic` (default false → drop `__*` except where meaningful), `dropConstruct`/`dropDestruct` (default true). Immutable, constructor with named defaults. | Config drives behavior; unit-tested. |
| 2.2 | Composable `MethodFilter` chain | `src/Contract/Generator/MethodFilterInterface.php` + filters: `VisibilityFilter`, `StaticFilter`, `MagicMethodFilter`, `ConstructorFilter`. `MethodExtractor` runs the chain. Each filter is unit-tested in isolation. | Each rule independently verifiable; fixtures assert combos. |
| 2.3 | Type-fidelity tests | Assert return/param types render correctly for union (`A\|B`), intersection (`A&B`), nullable (`?T`), variadic (`...$x`), by-ref (`&$x`), enum param/return, readonly properties (not in signatures), constructor promotion (params only). | Golden files updated; all type features covered. |

### Phase 3 — Interface-per-trait mode — v0.2.0
*Goal: Interface Segregation applied to the tool itself (README "What it does" item).*

| # | Task | Detail | Acceptance |
|---|------|-------|------------|
| 3.1 | Per-trait derivation | For each trait in `ClassReflection::getTraitsRecursively()`, derive one `InterfaceType` from that trait's own `getMethods(IS_PUBLIC)` (filtered). | One interface per trait; no external `extends`. |
| 3.2 | Compose main interface | The main derived interface `extends` each per-trait interface (still zero adaptee leakage — the per-trait interfaces are ours). Conflict handling: when a method appears in multiple traits, it lives in exactly one trait interface and is referenced once. | `getExtends()` lists only derived trait interfaces; no dup methods. |
| 3.3 | Tests | Use `fixtures/Stubs/TraitA..E` + `ClassD` (trait on a deep subclass) + a conflicting-trait fixture. Assert the main interface `extends` the right set. | Composition correct; no leakage. |

### Phase 4 — Type substitution map — v0.3.0
*Goal: when a derived method's type is itself something being derived, point at the derived interface (README roadmap).*

| # | Task | Detail | Acceptance |
|---|------|-------|------------|
| 4.1 | `ReflectionType` walker | `src/Generator/TypeSubstitutor.php` recursing `ReflectionNamedType` / `ReflectionUnionType` / `ReflectionIntersectionType`, rewriting a class FQCN present in the derive-set to its derived interface FQCN. | `Foo` → `FooInterface` in signatures. |
| 4.2 | Batch auto-detection | Derive a whole family together (e.g. `Reflection*` family); rewrite by declared-type match. | Cross-referencing types rewritten automatically. |
| 4.3 | Explicit overrides | `DeriveConfig::typeSubstitutionMap` for ambiguous (untyped/`mixed`) returns. | Caller-supplied overrides win. |
| 4.4 | Tests | Derive two mutually-referencing fixtures; assert return/param types rewritten to derived interfaces. | No native adaptee type leaks through substitution. |

### Phase 5 — Adapter generation (completes the "derive adapters" promise) — v0.4.0
*Goal: generate the delegating adapter that implements the derived interface and wraps the adaptee (the Dagger SDK dogfood).*

| # | Task | Detail | Acceptance |
|---|------|-------|------------|
| 5.1 | `AdapterGenerator` | New `src/Generator/AdapterGenerator.php`: emits a `ClassType` implementing the derived interface, holding an adaptee instance (constructor-injected), delegating each method, and **wrapping return values** via `TypeSubstitutor`/adapter factory when the return type was substituted (Phase 4). | Adapter implements derived interface; delegates correctly. |
| 5.2 | Dogfood: `nette/php-generator` | Derive an interface + adapter for a real `nette/php-generator` class; use it in a smoke test. | Real-world round-trip works. |
| 5.3 | Tests | Golden-file tests for generated adapters; at least one end-to-end "adaptee → adapter → call → correct result" test. | Adapter behavior verified. |

### Phase 6 — Architecture & QA hardening
*Goal: production-grade quality gates (borrow from sibling `ConfigureIt`).*

| # | Task | Detail | Acceptance |
|---|------|-------|------------|
| 6.1 | `deptrac.yaml` | Layers: `Contract` (no deps), `Reflection`, `Resolver`, `Generator`, `Behavior`. Enforce no upward/lateral leakage. `ConfigureIt` already ships one as a template. | `vendor/bin/deptrac` green. |
| 6.2 | Mutation testing | Add `infection` config (already in `vendor/bin`); run on `src/`. Set a minimal MSI threshold (e.g. ≥80%) in CI. | Mutations caught by the Phase 1–5 tests. |
| 6.3 | Coverage gate | Use existing `composer coverage` (Codeception `--coverage`); enforce threshold in CI. | Coverage reported; gate passes. |
| 6.4 | CI workflow | Add `.github/workflows/ci.yml`: matrix PHP 8.4 / 8.5, steps `test:unit`, `cs:check`, `analyse`, `arch` (deptrac), `infection`. | Every push is gated. |
| 6.5 | Replace empty suites | Convert the empty Functional suite into a **real integration smoke test** (derive a real package, assert output) instead of the browser-based Acceptance suite. **Drop `module-phpbrowser`** (wrong for a library). | Functional suite has ≥1 passing integration test. |

### Phase 7 — Docs, ADRs & release
*Goal: honest, navigable docs and a tagged release.*

| # | Task | Detail | Acceptance |
|---|------|-------|------------|
| 7.1 | Rewrite README | "What it does" reflects implemented phases; roadmap lists Phase 4/5/6 + out-of-scope (OTel/LLM long-tail from README). | README matches code. |
| 7.2 | `CHANGELOG.md` | Keep a changelog (mirror `ConfigureIt/docs/CHANGELOG.md` style). | Entries per release. |
| 7.3 | ADRs | `docs/architecture/decisions/`: ADR-0001 use `nette/php-generator Factory::fromMethodReflection`; ADR-0002 interface-per-trait composition; ADR-0003 static-method policy; ADR-0004 type-substitution strategy. | Decisions recorded. |
| 7.4 | Tag releases | `v0.1.0` (Phase 1), then `v0.2.0`, `v0.3.0`, `v0.4.0` as phases land. | Tags pushed; dist valid. |
| 7.5 | Stretch (post-v1, document only) | OTel-assisted shape discovery; LLM-assisted long-tail edge cases (README roadmap). **Out of scope for production-ready v1.** | Documented as future work. |

---

## 3. Suggested execution order (critical path)

```
Phase 0 ─┐
         ├─► Phase 1 (v0.1.0: FLAT DERIVE — the shippable core)
         ├─► Phase 2 (filters/config)
         ├─► Phase 3 (per-trait)        ─► v0.2.0
         ├─► Phase 4 (type substitution) ─► v0.3.0
         ├─► Phase 5 (adapters/dogfood)  ─► v0.4.0
         ├─► Phase 6 (deptrac/mutation/CI)
         └─► Phase 7 (docs/ADRs/release)
```

Phases 2–5 can be trimmed for a *minimum* production release: **Phase 0 + Phase 1 + Phase 6 (CI/deptrac) + Phase 7 (README/changelog)** already yields a defensible v0.1.0. Phases 3–5 are feature depth.

---

## 4. Key technical decisions to lock early

- **Extraction source:** use `ReflectionClass::getMethods(ReflectionMethod::IS_PUBLIC)` on the concrete class for flat mode (conflict-resolved by PHP); use per-trait `getMethods()` only in Phase 3. Avoid re-walking the graph manually for method collection.
- **Signature fidelity:** `Nette\PhpGenerator\Factory::fromMethodReflection()` is the single source of truth for parameter/return typing. Do not hand-build `Parameter` objects.
- **Static methods:** excluded by default (ports shouldn't enforce static); opt-in via `includeStatic`.
- **Leakage invariant:** every generated `InterfaceType` must have empty `getExtends()` (flat mode) or only derived trait interfaces (per-trait mode), and no `use`/FQCN referencing the adaptee's own hierarchy. Add a dedicated assertion helper `assertInterfaceIsSelfContained()`.
- **Output rendering:** always wrap in `PhpNamespace` so Nette emits short `use` aliases; the deriver returns a string, the adapter generator returns a string, both independently testable.

---

## 5. Risks & mitigations

| Risk | Impact | Mitigation |
|------|--------|------------|
| Nette `Factory::fromMethodReflection` edge cases (e.g. intersection types, enums) | Wrong signatures | Golden-file snapshot tests over all 60 fixtures (1.6) catch regressions immediately. |
| `getMethods()` conflict resolution differs from intended trait precedence | Wrong effective surface | Explicit `ConflictingMethodTrait` fixture + assertion (1.2). |
| Static mutable resolver singletons (`ClassReflection` statics, `REVIEW.md` §6.3) | Cross-test contamination as suites grow | Set resolvers per-test or refactor to instance injection before Phase 6 parallel CI. |
| Scope creep (adapters/OTel/LLM) blocks v1 | Never ships | Trim to Phase 0+1+6+7 for v0.1.0; keep rest as roadmap. |
| Empty Acceptance suite with `module-phpbrowser` | Misleading CI | Replace with integration smoke test; drop php-browser (6.5). |

---

## 6. Definition of Done (production-ready v1)

- [ ] `composer test:unit`, `cs:check`, `analyse` all green on PHP 8.4 & 8.5.
- [ ] `vendor/bin/deptrac` green; `infection` MSI ≥ threshold.
- [ ] `derive($class)` produces a self-contained interface for every fixture; golden files committed.
- [ ] Flat derivation (Phase 1) + config filters (Phase 2) shipped; per-trait & substitution at least one released minor.
- [ ] README, CHANGELOG, and ≥1 ADR written; `v0.1.0` tagged.
- [ ] CI workflow gates every push.
- [ ] Dogfood: a real `nette/php-generator` adapter derives and runs.
