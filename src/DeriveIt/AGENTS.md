# AGENTS.md — Guidance for AI agents working in DeriveIt

This file tells an AI agent (and human contributors) how to operate safely in this
repository: what the project is, what commands exist, where code lives, and the
conventions to follow. It is the operational companion to `REVIEW.md` (status &
gaps), `IMPLEMENTATION_PLAN.md` (milestone summary), and `ROADMAP.md` (detailed
phase execution spec).

> Note: the packaging defects catalogued in `REVIEW.md` §6 have been resolved in
> **Phase 0** of `ROADMAP.md` — `composer.json` now declares `php >=8.4`, keeps
> `nette/php-generator` in `require`, and moves Codeception modules to `require-dev`
> (with `nikic/php-parser` dropped as a direct dep, since it is only transitive).
> `composer install --no-dev` is clean and the platform is declared.

---

## 1. What this package is

`it-pack/derive-it` is the second package of the "It-Pack" family (alongside
`ConfigureIt`). Its goal is to **derive clean, self-contained interfaces (and
optionally adapters) from third-party or legacy PHP classes** by walking a class's
full parent/trait/interface graph and flattening it into a port you own.

**Current reality (see `REVIEW.md`):** the **graph-walking layer** (three recursive
resolvers + a thin `ClassReflection` facade) is implemented and tested. On top of
it, **Phase 1 flat interface derivation is implemented in `src/`**: `DeriveIt::derive($class)`
returns a self-contained Nette `InterfaceType` / source for any class, dropping
constructors, static methods (opt-in), and magic methods by default. Adapter
generation and per-trait / type-substitution modes are **not yet built** (Phases 3–5).

---

## 2. Commands (run from this directory)

| Command | Purpose | Must be green? |
|---|---|---|
| `composer test:unit` | Run the Unit suite (Codeception) | ✅ Always |
| `composer coverage` | Unit run with coverage (xml + html) | gate in CI (Phase 6) |
| `composer cs:check` | PHP-CS-Fixer dry-run diff (PSR-12 + `@PHP84Migration`) | ✅ Always |
| `composer cs:fix` | Auto-fix coding standard | use to fix, then re-check |
| `composer analyse` | PHPStan (level 5, paths `src` + `tests/{Functional,Unit}`) | ✅ Green |
| `composer architecture` | deptrac (`vendor/bin/deptrac`) | ✅ Green |
| `composer infection` | Infection mutation testing (MSI ≥ 80%) | ✅ Green |

**Current status (post-Phase 6):** `test:unit` ✅ (113 tests, 182 assertions),
`cs:check` ✅, `analyse` ✅ (no errors), `architecture` ✅ (deptrac 0 violations),
`infection` ✅ (MSI ≥ 80%). Phases 0–6 are complete: Phase 0 (CI/tooling green),
Phase 1 (flat `DeriveIt::derive($class)`), Phase 2 (filter pipeline + `DeriveConfig`),
Phase 3 (per-trait interfaces), Phase 4 (type substitution + family derivation),
Phase 5 (adapter generation), Phase 6 (deptrac/infection/coverage/CI/real Functional
suite). Do not let any of these gates regress.

---

## 3. Repository layout

```
src/
  Reflection/ClassReflection.php        # facade, extends ReflectionClass
  Resolver/                            # RecursiveClass/Interface/TraitResolver
  Contract/Resolver/                    # 4 resolver interfaces (1 base + 3 typed)
  Contract/Generator/                   # DeriverInterface (derive / deriveSource)
                                       #   MethodFilterInterface (filter chain)
  Generator/                           # DeriveConfig, MethodExtractor (+ filters:
                                       #   Visibility/Static/Magic/Constructor),
                                       #   MethodSignatureFactory, InterfaceDeriver,
                                       #   PerTraitInterfaceDeriver (per-trait mode)
  DeriveIt.php                         # public facade: DeriveIt::derive($class),
                                       #   derivePerTrait() / deriveTraitInterfaces()
  Behavior/Utility/LazyCreatable.php    # create() proxy factory (createLazy removed)
scripts/
  derive.php                           # demo: prints a derived interface
  regenerate_goldens.php               # dev helper: rewrites tests/_expected/*
  create_text_constructs.php            # empty stub
fixtures/
  Stubs/                               # ~21 files used by unit tests
  *.php (60+)                          # type fixtures for golden-file snapshot tests
tests/
  Unit/                                # suite with real tests (78 passing)
    Generator/                        # InterfaceDeriver + snapshot tests
  Functional/  Acceptance/             # empty — config + support only
  _expected/                          # committed golden interface files (*.interface.php)
generated/                             # empty — output is rendered in-memory / to string
```

Namespace roots (composer.json): `ItPack\DeriveIt\` → `src/`,
`Tests\` / `_Fixture\` / `_Vendor\` → `tests/` / `fixtures/`.

---

## 4. Conventions to follow

- **Never break CI.** Every change must keep `test:unit`, `cs:check`, and (target)
  `analyse` green. The plan's guiding principle #1 is "never break CI".
- **PHP 8.4+.** Code uses `ReflectionClass::newLazyProxy()` and the `@PHP84Migration`
  ruleset. Use modern PHP 8.4 syntax (e.g. `??=` not short ternary — the latter is
  forbidden by the active CS ruleset and is a current PHPStan failure).
- **PSR-12 + `@PHP84Migration`** is enforced by `php-cs-fixer`. Run `composer cs:fix`
  before committing; never hand-format around it.
- **Leakage invariant (most important design rule).** Anything the generator emits
  must reference **nothing** from the adaptee's own parent classes, traits, or
  interfaces. A derived interface has empty `getExtends()` (flat mode) or only
  derived trait interfaces (per-trait mode), and no `use`/FQCN leaking the adaptee
  hierarchy. Add an `assertInterfaceIsSelfContained()` helper for this.
- **Reuse Nette for the type grammar.** Use
  `Nette\PhpGenerator\Factory::fromMethodReflection()` as the single source of truth
  for parameter/return typing — do **not** hand-build `Parameter` objects (catches
  union/intersection/nullable/variadic/by-ref/readonly/enums correctly). See
  ADR-0001 (planned).
- **Resolvers stay the graph source.** Use `ReflectionClass::getMethods(IS_PUBLIC)`
  on the concrete class for flat extraction (PHP resolves trait/parent conflicts);
  only walk `getTraitsRecursively()`/`getClassesRecursively()` for per-trait mode.
- **Configuration over hidden opinion.** Every filtering decision (visibility,
  static, magic methods, interface-per-trait, type substitution) is a
  `DeriveConfig` flag with a sane default, so behavior is explicit and testable.

---

## 5. Working in this repo — do / don't

**Do**
- Read `REVIEW.md` §3–§6 before changing `src/` — the real defects and smells are
  catalogued there.
- Write tests for any new `src/` behavior; the Unit suite is the only meaningful one.
- Use the existing `fixtures/Stubs/` and the ~60 `fixtures/*.php` type fixtures for
  golden-file snapshot tests.
- Keep resolver return shape documented (associative array keyed by class/interface
  name, de-duplicated via `array_merge` string-key semantics).

**Don't**
- Don't reintroduce the old scratch script `scripts/generate_reflection_adapter.php` —
  it was retired in Phase 1.7 and replaced by `DeriveIt::derive()` plus the
  `scripts/derive.php` demo and `scripts/regenerate_goldens.php` helper.
- Don't leave `composer analyse` red. If a `tests/` data-provider is flagged
  `deadMethod`/`method.unused`, configure the Codeception-aware PHPStan extension or
  scope `ignoreErrors` — **never delete a `#[DataProvider]` method** to silence it.
- Don't introduce global mutable static resolver state beyond what already exists;
  `ClassReflection`'s static singletons are a known smell (REVIEW §6.3) to be
  refactored toward instance injection before Phase 6 parallel CI.

---

## 6. Where to look when asked

- "Why does X fail static analysis?" → `REVIEW.md` §4.3, then `composer analyse`.
- "What should I build next?" → `ROADMAP.md` (detailed) / `IMPLEMENTATION_PLAN.md`
  (summary). Current critical path starts at Phase 0 (green CI) → Phase 1 (flat derive).
- "How is the graph walked?" → `src/Resolver/*` and `src/Reflection/ClassReflection.php`.
- "How should generation work?" → `ROADMAP.md` Phase 1.1–1.7 (contract, extractor,
  signature factory, deriver, snapshot tests).
- "What are the quality gates?" → `ROADMAP.md` Phase 6 (deptrac, infection, coverage, CI).

---

## 7. Definition of Done (production-ready v1)

`composer test:unit`, `cs:check`, `analyse` green on PHP 8.4 & 8.5 · `deptrac` green ·
`infection` MSI ≥ threshold · `derive($class)` self-contained for every fixture ·
README/CHANGELOG/≥1 ADR written · `v0.1.0` tagged · CI gates every push · a real
`nette/php-generator` adapter derives and runs. (Full checklist in `ROADMAP.md`.)
