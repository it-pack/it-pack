# DeriveIt — Project Review

> Review date: 2026-08-20
> Scope: `/home/quill/tinker/php/project/it-pack/src/DeriveIt`
> Method: source reading, dependency analysis, and running the project's own QA
> tooling (`composer test:unit`, `cs:check`, `analyse`, `architecture`, `infection`).
> Supersedes the 2026-08-14 review, which described the pre-Phase-0 scaffolding state.

---

## 1. Summary

DeriveIt (`it-pack/derive-it`) is the **second package of the "It-Pack" family**,
alongside `ConfigureIt`. It derives **clean, self-contained interfaces (and
adapters)** from third-party or legacy PHP classes — walking a class's full
parent/trait/interface graph and flattening it into a port you own, without
leaking the adaptee's own contracts.

**Current reality:** The full derivation pipeline described in the README is
**implemented and all quality gates are green**. Built across Phases 0–7:

- **Graph-walking layer** — three recursive resolvers (`RecursiveClassResolver`,
  `RecursiveInterfaceResolver`, `RecursiveTraitResolver`) plus a `ClassReflection`
  facade.
- **Flat interface derivation** — `DeriveIt::derive($class)` returns a
  self-contained Nette `InterfaceType` / source.
- **Per-trait interface derivation** — `DeriveIt::derivePerTrait()` /
  `deriveTraitInterfaces()` (interface segregation; main interface `extends` only
  the derived trait interfaces).
- **Type substitution** — `TypeSubstitutor` + `DeriveConfig::typeSubstitutionMap`
  with **family derivation** (`DeriveIt::deriveFamily()`).
- **Adapter generation** — `AdapterDeriver` (`DeriveIt::deriveAdapter()`).
- **Configuration** — `DeriveConfig` flags (visibility, static, magic,
  constructor, interface-per-trait, type substitution) with safe defaults.
- **Docs / ADRs / examples** — `docs/guide/*` (user guide), `docs/architecture/
  decisions/*` (4 ADRs), and 10 runnable `examples/*` demos.

All derivers drop constructors, static methods (opt-in), and magic methods by
default, and emit no `extends`/trait `use` leaking the adaptee hierarchy (the
**leakage invariant**).

**Health at a glance (verified 2026-08-20):**

| Check | Command | Result |
|-------|---------|--------|
| Unit tests | `composer test:unit` | ✅ 113 tests, 182 assertions |
| Coding standard | `composer cs:check` | ✅ 0 files to fix |
| Static analysis | `composer analyse` (PHPStan level 5) | ✅ No errors |
| Architecture | `composer architecture` (deptrac) | ✅ 0 violations |
| Mutation testing | `composer infection` (min MSI 80%) | ✅ MSI **85%** |
| Declared PHP version | composer.json | ✅ `php >= 8.4` |

The package is **production-ready through Phase 7** (implementation, QA
hardening, documentation, ADRs, and a tagged release path). Every quality gate is
green. The only remaining items are design-hardening suggestions (§6) and the
documented post-v1 stretch goals (OTel/LLM-assisted discovery).

---

## 2. Repository Structure

```
DeriveIt/
├── composer.json / composer.lock     # php >=8.4; require: nette/php-generator ^4.2
├── phpstan.neon                      # level 5, paths: src + tests/{Functional,Unit}
├── .php-cs-fixer.php                 # PSR-12 + @PHP84Migration (excludes examples/, bootstrap.php)
├── infection.json5                   # codeception framework, @default mutators, min-msi 80
├── codeception.yml                   # suites: Unit, Functional, Acceptance
├── README.md                        # vision + quick start + Documentation section
├── src/
│   ├── DeriveIt.php                  # public facade: derive / deriveInterface /
│   │                                #   derivePerTrait / deriveTraitInterfaces /
│   │                                #   deriveFamily / deriveAdapter
│   ├── Reflection/ClassReflection.php        # facade extends ReflectionClass
│   ├── Resolver/                            # 3 recursive resolvers
│   ├── Contract/Resolver/                    # 4 resolver interfaces
│   ├── Contract/Generator/                   # DeriverInterface, MethodFilterInterface
│   ├── Generator/                           # DeriveConfig, MethodExtractor (+ filters),
│   │                                        #   MethodSignatureFactory, InterfaceDeriver,
│   │                                        #   PerTraitInterfaceDeriver, FamilyDeriver,
│   │                                        #   TypeSubstitutor, AdapterGenerator
│   └── Behavior/Utility/LazyCreatable.php    # trait: create() proxy factory
├── scripts/
│   ├── derive.php                            # demo: prints a derived interface
│   └── regenerate_goldens.php                # dev helper: rewrites tests/_expected/*
├── fixtures/
│   ├── Stubs/                                 # ~21 files used by unit tests
│   └── *.php (60+)                            # type fixtures for golden-file snapshots
├── examples/                          # 10 runnable demos + README (excluded from cs/analyse)
├── tests/
│   ├── Unit/                                  # the primary suite (113 tests)
│   │   ├── Generator/                         # InterfaceDeriver + snapshot tests
│   │   ├── Reflection/  Resolver/  Behavior/  # resolver/facade/lazy tests
│   ├── Functional/DeriveRealPackageTest.php   # 2 tests: derive a real Nette class
│   ├── Acceptance/  (empty — config only)
│   └── _expected/                             # committed golden interface files
├── docs/
│   ├── guide/                                 # index, features, architecture, usage
│   ├── architecture/decisions/                # ADR-0001..0004
│   ├── CHANGELOG.md  IMPLEMENTATION_PLAN.md  REVIEW.md  ROADMAP.md
└── generated/                                # empty — output rendered in-memory / to string
```

---

## 3. What Is Actually Implemented

### 3.1 The resolver layer (graph walking)
Three classes implement `RecursiveResolverInterface::resolve(ReflectionClass): array`,
returning an **associative array keyed by class/interface/trait name** (de-duplicated
via `array_merge` string-key semantics):

- **`RecursiveClassResolver`** — walks `getParentClass()` up the linear chain.
- **`RecursiveInterfaceResolver`** — for the class and each ancestor, collects
  `getInterfaces()` and recurses into interface inheritance.
- **`RecursiveTraitResolver`** — collects traits per class up the chain and recurses
  into each trait's own used traits.

All three use `LazyCreatable::create(): self`.

### 3.2 The facade
**`ClassReflection extends ReflectionClass`** adds `create()`, the three
`get*Recursively()` instance methods (delegating to the resolvers), and optional
resolver constructor injection (see §6.1-1). Resolvers default to a single shared,
immutable set — there is **no mutable static state**.

### 3.3 The generation pipeline
For each public method of the concrete class (PHP resolves trait/parent conflicts),
`MethodExtractor` runs a filter chain (`VisibilityFilter`, `StaticFilter`,
`MagicMethodFilter`, `ConstructorFilter`), then `MethodSignatureFactory::
fromMethod()` rebuilds the signature via Nette's `Factory::fromMethodReflection()`
(the single source of truth for union/intersection/nullable/variadic/by-ref/
readonly typing — ADR-0001). `TypeSubstitutor` rewrites adaptee FQCNs to
user-owned names (ADR-0004). Three derivers consume this:

- **`InterfaceDeriver`** — flat self-contained interface.
- **`PerTraitInterfaceDeriver`** — one interface per trait; main `extends` only the
  derived trait interfaces.
- **`FamilyDeriver`** — derives a family with type substitution (ADR-0004).
- **`AdapterGenerator`** — emits an adapter implementing the derived interface.

### 3.4 Documentation, examples, ADRs
- `docs/guide/` — in-depth user guide (concepts, features, architecture, usage/cookbook).
- `examples/` — 10 copy-pasteable demos mirroring the documented features.
- `docs/architecture/decisions/` — ADR-0001 (Nette Factory typing), ADR-0002
  (per-trait composition), ADR-0003 (static-method policy), ADR-0004
  (type-substitution strategy).
- README links to all of the above via a "Documentation" section.

---

## 4. Status of QA Tooling (verified 2026-08-20)

| Gate | Result | Notes |
|------|--------|-------|
| `composer test:unit` | ✅ 113 tests, 182 assertions | Unit suite green. |
| `composer cs:check` | ✅ 0 files | PSR-12 + `@PHP84Migration`. `examples/` and `bootstrap.php` excluded. |
| `composer analyse` | ✅ No errors | PHPStan level 5 over `src` + `tests/{Functional,Unit}`. Codeception `#[DataProvider]` methods recognized (no deadMethod noise). |
| `composer architecture` | ✅ 0 violations | deptrac: 36 uncovered / 46 allowed / 0 errors. |
| `composer infection` | ✅ MSI 85% (≥ 80%) | Passes under `shuffle: true` (see §5.1 for the pollution fix). |

### 4.1 Infection note (important)
Infection runs Codeception with `settings: shuffle: true`. The **baseline**
therefore executes tests in randomized order. This previously exposed an
**order-dependent test failure** (see §5.1) that aborted Infection *before* any
mutation was scored — i.e. the infection gate was effectively red even though
`composer test:unit` was green in fixed order. After the §5.1 fix, the baseline is
stable under shuffle and MSI is 85%.

---

## 5. Gap Analysis vs. Previous Review & README

### 5.1 Resolved since the 2026-08-14 review
The earlier review documented a scaffolding-stage project with red tooling. All of
those findings are now closed:

| Earlier finding | Status |
|---|---|
| No `php >= 8.4` constraint; Codeception modules in `require`; `nikic/php-parser` in `require` | ✅ Fixed in Phase 0 (`composer.json`: `php >=8.4`; only `nette/php-generator` in require; modules + infection adapter in `require-dev`). |
| `composer analyse` failing (short-ternary ×3, `empty()`, deadMethod) | ✅ Fixed — analyse is green. |
| Generation step did not exist (`scripts/generate_reflection_adapter.php` scratch) | ✅ Retired; replaced by the real `Generator/*` pipeline + `DeriveIt::derive()`. |
| README over-promised (per-trait, substitution, adapters) | ✅ Implemented; README "What it does" now matches delivered code. |
| ~60 root fixtures unused | ✅ Exercised by golden-file snapshot tests over `tests/_expected/*.interface.php`. |
| **Infection baseline aborted under shuffle (test pollution)** | ✅ **Fixed in this review** — see below. |

**The infection test-pollution defect (root cause + fix):**
`tests/Unit/Reflection/ClassReflectionTest.php` replaced the global static
resolver singletons (`ClassReflection::setRecursive*Resolver(...)`) with
`Codeception\Stub::makeEmpty()` stubs and **never reset them**. The trait-resolver
stub was configured to return `RECURSIVE_TRAITS` — an array of **class-name
strings**, not `ReflectionClass` objects. Any later test that called
`getTraitsRecursively()` and then `MethodExtractor::extract()` received a string
and threw `TypeError`. In the default (non-shuffled) Codeception order,
`Generator/` runs before `Reflection/`, so `PerTraitDeriverTest` saw real resolvers
and passed; under Infection's `shuffle: true`, `ClassReflectionTest` could run
first and contaminate the rest of the run.

**Fix:** the initial remedy was a `ClassReflectionTest::tearDown()` that restored the
real resolvers after every test (verified `test:unit` green, `infection` green at the
time). That band-aid was then superseded in **Phase 8** by the proper design fix:
`ClassReflection` now uses **instance-injected resolvers** with an immutable shared
default and no mutable static state (§6.1-1), so the leak cannot recur and no
`tearDown()` reset is needed. Current Infection MSI is **86%**.

### 5.2 README vs. delivered code (current)
The README's capability section now accurately reflects implemented behavior. The
only unimplemented items are explicitly framed as post-v1 (OTel / LLM-assisted
discovery), not as shipped features.

---

## 6. Code-Quality Findings (current)

### 6.1 Real smells / limitations (not regressions, but worth tracking)
1. **Global mutable static resolver state — ✅ RESOLVED (Phase 8, 2026-08-20).**
   Was the direct cause of the §5.1 Infection failure. `ClassReflection` no longer
   holds `static` singletons: the `set*Resolver()` setters and `getRecursive*Resolver()`
   static accessors were removed, and resolvers are now **instance state** injected via
   the constructor / `create()` with an immutable shared default
   (`src/Reflection/ClassReflection.php`). `ClassReflectionTest` passes stub resolvers
   via the constructor and needs no `tearDown()` reset. Cross-test state leakage is
   eliminated; Infection baseline is stable under `shuffle: true` without any band-aid.
2. **`ClassReflection extends ReflectionClass`** (composition-vs-inheritance choice).
   Works and is convenient, but exposes the entire reflection API as "DeriveIt"
   surface, which can confuse consumers. Composition + a private `ReflectionClass`
   would be a cleaner public boundary.
3. **`LazyCreatable::createLazy()` — ✅ RESOLVED (Phase 0).** `LazyCreatable` ships
   only the abstract `create()` method; `createLazy()` does not exist and has zero
   usages. No action outstanding.
4. **Redundant recursion in `RecursiveTraitResolver`** (`resolveClassInheritance()`
   calls the full `resolve()` on each parent, then re-merges). Correct, but
   re-walks shared ancestors; fine at current scale, worth noting before hierarchies
   grow.
5. **Per-trait composed main interface `extends` form — ✅ RESOLVED (Phase 8,
   2026-08-20).** `PerTraitInterfaceDeriver::derive()` now sets `extends` to each trait
   interface's FQCN; Nette/`PhpNamespace` simplifies it to a clean short name when
   rendering (`interface ClassDInterface extends TraitEInterface, TraitAInterface`).
6. **`scripts/create_text_constructs.php` — ✅ RESOLVED (Phase 8, 2026-08-20).**
   The empty no-op stub was deleted; no production/test code referenced it.
7. **`scripts/generate_reflection_adapter.php`** (the old scratch file) is **retired**
   (removed in Phase 1.7); `scripts/derive.php` is the supported demo.
8. **`examples/` and `fixtures/` are excluded from `cs:check` / `analyse`.** `examples/`
   are intentionally demo code; `fixtures/*.php` carry IDE false positives
   (Not-all-paths-return / Undefined `SimpleBackedEnum`) that PHPStan ignores via
   path config. Acceptable, but note that examples are not linted by CI.

### 6.2 Packaging / dependency observations
9. `composer.json` correctly scopes `nette/php-generator` to `require` and all test
   tooling (`codeception/module-asserts`, `infection/codeception-adapter`,
   `just-bases/*`) to `require-dev`. Clean `composer install --no-dev`.
10. The sibling `ConfigureIt` package shares the same QA base (`just-bases/php-qa-base`),
    so gate definitions are consistent across the family.

---

## 7. Recommendations (priority order)

**P0 — none outstanding.** All quality gates are green (verified 2026-08-20, post-Phase-8).

**P1 — design hardening — ✅ COMPLETE.** Resolver instance injection (§6.1-1),
dead `createLazy()` removal (§6.1-3), clean per-trait `extends` (§6.1-5), and the
empty stub deletion (§6.1-6) are all done in Phase 8.

**P2 — polish (optional)**
- Consider composition over `extends ReflectionClass` (§6.1-2) — a cleaner public
  boundary, but not required.
- Redundant recursion in `RecursiveTraitResolver` (§6.1-4) — fine at current scale.

**P3 — post-v1 (optional)**
- OTel / LLM-assisted discovery (documented roadmap, not yet started).
- Expand the `Functional` suite (currently 2 real-package tests) and start asserting
  `generated/` output in CI (Roadmap H5).

---

## 8. Conclusion

DeriveIt is now a **complete, documented, and fully green** package. The headline
capability promised by the README — turning a resolved class graph into a
self-contained interface/adapter — is implemented across a clean resolver →
extractor → signature-factory → deriver pipeline, with per-trait, family, and
adapter modes, configurable via `DeriveIt` + `DeriveConfig`.

Compared with the 2026-08-14 review (which found red static analysis, missing
packaging constraints, a non-existent generator, and an over-promising README),
**every gate is now green**: 113 unit tests, 0 CS fixes, PHPStan clean, deptrac
clean, and Infection at **86% MSI** (≥ 80%). During the final review we identified
and fixed one real defect — **test pollution from global static resolver state** that
broke Infection's shuffled baseline — first via a `tearDown()` reset, then properly
in Phase 8 by **instance-injecting the resolvers** (no mutable static state remains).
The remaining Phase 8 hardening items (clean per-trait `extends`, dead-`createLazy()`
removal, empty-stub deletion) are also complete.

The only non-blocking follow-ups are optional polish (composition over `extends
ReflectionClass`, redundant trait-resolver recursion) and post-v1 work (OTel/LLM
discovery, wider Functional suite). The project is ready for its tagged `v0.1.0`
release.
