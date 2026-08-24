# AGENTS.md — Guidance for AI agents working in DeriveIt

This file tells an AI agent (and human contributors) how to operate safely in this
repository: what the project is, what commands exist, where code lives, and the
conventions to follow. It is the operational companion to `docs/REVIEW.md`
(current audit), `docs/REFACTORING_PLAN.md` (active refactoring steps), and
`docs/ROADMAP.md` (detailed phase execution spec).

> **Maintenance rule:** when files move, phases land, or gate baselines change,
> update this file in the same change set. Stale pointers here misdirect every
> future contributor and agent (this happened once already — see R3-2 in
> `docs/REFACTORING_PLAN.md`).

---

## 1. What this package is

`it-pack/derive-it` is part of the "It-Pack" family. It **derives clean,
self-contained interfaces (and delegating adapters) from third-party or legacy PHP
classes** by walking a class's full parent/trait/interface graph and flattening it
into a port you own.

**Current reality:** Phases 0–12 of `docs/ROADMAP.md` are **complete;
the package is release-ready**. All five derivation modes are implemented behind
the public facade `DeriveIt` and assembled by the composition root
`DeriveItFactory`: flat (`derive*`), per-trait (`derivePerTrait`,
`deriveTraitInterfaces`), adapter (`deriveAdapter`), family
(`deriveFamily*`, auto-discovery from a root class), and family-of-adapters
(`deriveFamilyAdapter`). Supporting surface: `DeriveConfig` presets and withers,
structured results (`DerivationResult`, `BuildMeta`), CI drift diffing
(`DeriveDiff` via `DeriveIt::diff()`), an opt-in deep leakage auditor
(`LeakageAuditor`), three swappable reflection caches, and an ad-hoc CLI
(`bin/derive`, five modes).

---

## 2. Commands (run from this directory)

| Command | Purpose | Must be green? |
|---|---|---|
| `composer test:unit` | Run the Unit suite (Codeception) | ✅ Always |
| `composer coverage` | Unit run with coverage (xml + html) | gate in CI |
| `composer cs:check` | PHP-CS-Fixer dry-run diff (PSR-12 + `@PHP84Migration`) | ✅ Always |
| `composer cs:fix` | Auto-fix coding standard | use to fix, then re-check |
| `composer analyse` | PHPStan (level 5, paths `src` + `tests/{Functional,Unit}`) | ✅ Always |
| `composer architecture` | deptrac (`vendor/bin/deptrac`) | ✅ Always |
| `composer infection` | Infection mutation testing (`--min-msi=80`) | ✅ Always |

**Current baseline (2026-08-24):** `test:unit` ✅ (247 tests, 509 assertions),
`cs:check` ✅ (0 files), `analyse` ✅ (0 errors), `architecture` ✅ (0 violations),
`infection` ✅ (MSI ≈ 99.7%, min-msi 80). CI (`.github/workflows/ci.yml`) runs the
full battery on a PHP 8.4 + 8.5 matrix on every push/PR. Do not let any gate
regress.

---

## 3. Repository layout

```
bin/
  derive                                # executable CLI entry (DeriveCommand)
docs/
  ROADMAP.md                            # phase execution spec (Phases 0–12, complete)
  REVIEW.md                             # current audit (F-1…F-15) — start here for defects
  REFACTORING_PLAN.md                   # active plan closing REVIEW.md findings (R3-*)
  guide/                                # index / features / usage / architecture
  architecture/decisions/               # ADR-0001 … ADR-0004
  archive/                              # prior reviews & plans (superseded)
examples/                               # 10 runnable demos + bootstrap.php
scripts/
  derive.php                            # demo: prints a derived interface
  regenerate_goldens.php                # dev helper: rewrites tests/_expected/*
src/
  Cli/                                  # DeriveCommand, ArgParser, DeriveCliInput, ParseState,
                                        #   Preset, DeriveConsoleSink (--verbose diagnostics)
  Contract/
    Cli/CommandInterface.php
    Generator/                          # DeriverInterface, FamilyDeriverInterface,
                                        #   AdapterGeneratorInterface, MethodFilterInterface
    Reflection/ReflectionCacheInterface.php
    Resolver/                           # RecursiveResolverInterface + 3 typed sub-interfaces
    Telemetry/                          # DeriveEventSinkInterface (execution-model emission port,
                                        #   see docs/CONSOLIDATION_PLAN.md)
  DeriveIt.php                          # static facade (Entry) — every public API method
  DeriveItFactory.php                   # composition root (Entry): wires derivers behind ports,
                                        #   shares one ReflectionCacheInterface + Nette Factory
  Exception/                            # EmptyDerivation / LeakageDetected / NamingCollision
  Generator/                            # InterfaceDeriver, PerTraitInterfaceDeriver,
                                        #   FamilyDeriver, AdapterGenerator, MethodExtractor
                                        #   (+ Visibility/Static/Magic/Constructor filters),
                                        #   MethodSignatureFactory, TypeSubstitutor,
                                        #   SourceRenderer, LeakageAuditor, Naming,
                                        #   DeriveConfig (+ strict()/forAdapter()/forFamily()),
                                        #   DeriveMode, Visibility, BuildMeta,
                                        #   DerivationResult, DeriveDiff, AbstractDeriver
  Reflection/                           # ClassReflection facade + InMemory / PsrCache /
                                        #   Weak reflection caches
  Resolver/                             # RecursiveClass / Interface / TraitResolver
  Telemetry/                            # NullDeriveEventSink (default no-op sink)
  Behavior/Utility/LazyCreatable.php    # shared default-bodied create() factory trait
fixtures/
  Stubs/                                # stubs used by unit tests
  *.php (~60)                           # type fixtures for golden-file snapshot tests
tests/
  Unit/                                 # 25 test classes incl. snapshot/golden suites
  Functional/DeriveRealPackageTest.php  # end-to-end against a real external package
  _expected/                            # 48 committed golden sources (*.interface.php,
                                        #   *.adapter.php) — must stay byte-stable
generated/                              # empty — output is rendered in-memory / to string
```

Namespace roots (composer.json): `ItPack\DeriveIt\` → `src/`,
`Tests\` / `_Fixture\` / `_Vendor\` → `tests/` / `fixtures/`.

---

## 4. Conventions to follow

- **Never break CI.** Every change must keep all five gates green. Guiding
  principle #1 of every plan in this repo.
- **PHP 8.4+.** Modern syntax is used throughout and enforced by CS
  (`@PHP84Migration`): readonly classes, backed enums, constructor promotion,
  first-class callables, `match`, `??=` (short ternary is forbidden by the ruleset).
- **PSR-12 + `@PHP84Migration`** is enforced by `php-cs-fixer`. Run `composer cs:fix`
  before committing; never hand-format around it.
- **Leakage invariant (most important design rule).** Anything the generator emits
  must reference **nothing** from the adaptee's own parent classes, traits, or
  interfaces unless explicitly allow-listed (`reExposeOwnedInterfaces`). Enforced
  by `InterfaceDeriver::assertSelfContained()` (flat),
  `PerTraitInterfaceDeriver::assertPerTraitSelfContained()` (per-trait), and the
  opt-in deep `LeakageAuditor` (`DeriveConfig::$auditLeakage`).
- **Reuse Nette for the type grammar.** Use
  `Nette\PhpGenerator\Factory::fromMethodReflection()` as the single source of truth
  for parameter/return typing — do **not** hand-build `Parameter` objects (catches
  union/intersection/nullable/variadic/by-ref/enums correctly). See
  `docs/architecture/decisions/ADR-0001`.
- **Resolvers stay the graph source.** Use `ReflectionClass::getMethods(IS_PUBLIC)`
  on the concrete class for flat extraction (PHP resolves trait/parent conflicts);
  only walk `getTraitsRecursively()`/`getClassesRecursively()` for per-trait mode.
- **Configuration over hidden opinion.** Every filtering decision (visibility,
  static, magic methods, constants, attributes, docblocks, type substitution) is an
  explicit `DeriveConfig` flag with a sane default, so behavior is testable.
- **No mutable global/static state.** Caches and resolvers are instance-scoped and
  injected (`DeriveItFactory`, `ClassReflection::create()`); static singletons are
  forbidden (they leak across tests and mask Infection mutants).
- **Telemetry is facts-only and opt-in.** The pipeline emits activities,
  measurements, observations, and occurrences through the injectable
  `DeriveEventSinkInterface` (default `NullDeriveEventSink`): scalar attributes,
  no timing/identity (host concerns), failures still throw — see
  `docs/CONSOLIDATION_PLAN.md` before adding emission sites.
- **Golden files are contracts.** Never edit `tests/_expected/*` by hand; if a
  deliberate output change is intended, regenerate via `scripts/regenerate_goldens.php`
  and review the full diff.

---

## 5. Working in this repo — do / don't

**Do**
- Read `docs/REVIEW.md` before changing `src/` — open defects are catalogued there
  (F-ids), and `docs/REFACTORING_PLAN.md` maps them to concrete steps (R3-ids).
- Write tests for any new `src/` behavior; Unit is the meaningful suite
  (`tests/Functional` covers one real-package smoke case).
- Use the existing `fixtures/Stubs/` and `fixtures/*.php` type fixtures for
  golden-file snapshot tests.
- Keep resolver return shape documented (associative array keyed by class/interface
  name, de-duplicated via `array_merge` string-key semantics).

**Don't**
- Don't reintroduce the old scratch script `scripts/generate_reflection_adapter.php` —
  it was retired in Phase 1.7 and replaced by the `DeriveIt` facade plus
  `scripts/derive.php` and `scripts/regenerate_goldens.php`.
- Don't leave `composer analyse` red. If a `tests/` data-provider is flagged
  `deadMethod`/`method.unused`, configure the Codeception-aware PHPStan extension or
  scope `ignoreErrors` — **never delete a `#[DataProvider]` method** to silence it.
- Don't introduce global mutable static resolver or cache state (see §4).
- Don't add dependencies without declaring them in `composer.json` — even
  interface-only packages like `psr/cache` must be declared (undeclared transitive
  reliance broke production installs once; see REVIEW.md F-1).

---

## 6. Where to look when asked

- "Why does X fail static analysis?" → run `composer analyse`; background in
  `docs/REVIEW.md`.
- "What should I work on next?" → `docs/REFACTORING_PLAN.md` (active findings work)
  → `docs/ROADMAP.md` (phase history & post-v1 backlog).
- "How is the graph walked?" → `src/Resolver/*` and `src/Reflection/ClassReflection.php`.
- "How does derivation/generation work?" → `docs/guide/architecture.md`, then
  `src/Generator/` (`AbstractDeriver` → derivers → `MethodSignatureFactory` /
  `TypeSubstitutor`).
- "What are the quality gates?" → §2 above; enforcement in `.github/workflows/ci.yml`
  and `infection.json5`.
- "Where are the design decisions?" → `docs/architecture/decisions/ADR-0001 … 0004`.
- "How does telemetry / the execution model work?" → `Contract/Telemetry/`,
  `Generator/DeriveEventEmitter`, `docs/integrations/runit.md`; the bridge lives
  in the sibling package `src/DeriveItRunit/` (see `docs/CONSOLIDATION_PLAN.md`).

---

## 7. Definition of Done (release v0.1.0)

All five gates green on PHP 8.4 & 8.5 · deptrac 0 violations · infection MSI ≥ 80% ·
derived output self-contained for every fixture (golden suite green) ·
README/user-guide/ADRs written · packaging clean (`psr/cache` declared, license
set, `composer validate` warning-free) · CI gating every push · real-package
Functional test passing. Track remaining pre-tag items in `docs/REFACTORING_PLAN.md`
Milestone A.
