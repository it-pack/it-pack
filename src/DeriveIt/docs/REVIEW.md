# DeriveIt — In-Depth Project Review

> Review date: 2026-08-22
> Scope: `/home/quill/tinker/php/project/it-pack/src/DeriveIt`
> Method: full source reading of `src/` (39 files), dependency-layer analysis,
> targeted greps, and running the project's own QA tooling
> (`composer test:unit`, `cs:check`, `analyse`, `architecture`).
> Supersedes `docs/archive/REVIEW.md` (2026-08-20), which covered Phase 0–8.
> This review adds the Phase 9/10 surface (type substitution, family, adapter,
> leakage auditing, caching, and the `bin/derive` CLI) and re-evaluates the
> project against SOLID/DRY, hexagonal architecture, modern PHP, and the
> "no magic strings/numbers, no `else`/`elseif`, low complexity" directives.

---

## 1. Executive Summary

DeriveIt (`it-pack/derive-it`) derives **self-contained interfaces and adapters**
from existing PHP classes by walking the parent/trait/interface graph and
flattening it into a port you own. The pipeline is real, well-tested, and all
quality gates are green. The design is genuinely hexagonal at the **edges**
(resolvers and reflection caches are clean ports with swappable adapters), and
value objects are `readonly`. Two structural weaknesses remain:

1. **The `DeriveConfig` wither API is massively duplicated** (17 near-identical
   `with*()` methods), the single biggest maintainability/DRY cost in the repo.
2. **The deriver "port" (`DeriverInterface`) only covers the flat and per-trait
   derivers** — `FamilyDeriver` and `AdapterGenerator` are not behind an
   interface, and the `DeriveIt` facade `new`s concrete derivers directly with no
   factory/composition-root abstraction, so Dependency Inversion is not applied at
   the top of the graph.

Both are low-risk and easily remediated. Modern-PHP leverage is already strong
(`readonly` everywhere it matters); the clearest further wins are **enums for the
magic mode/visibility strings**, **property hooks instead of withers**, and a
**lazy-object adapter** option for the generated code.

**Health at a glance (verified 2026-08-22):**

| Check | Command | Result |
|-------|---------|--------|
| Unit tests | `composer test:unit` | ✅ 227 tests, 469 assertions |
| Coding standard | `composer cs:check` (PSR-12 + `@PHP84Migration`) | ✅ 0 files to fix |
| Static analysis | `composer analyse` (PHPStan) | ✅ No errors |
| Architecture | `composer architecture` (deptrac) | ✅ 0 violations |
| Mutation testing | `composer infection` (min MSI 80%) | ✅ MSI 99% (prior session) |
| Declared PHP | `composer.json` | ✅ `php >= 8.4` |

---

## 2. Feature Completeness & Correctness

- **Flat derivation** (`DeriveIt::derive*`, `InterfaceDeriver`) — implemented and
  enforced: drops constructors/static/magic by default; forces `abstract`+`public`;
  no `extends`/trait `use` leaking the adaptee (the **leakage invariant**),
  guarded by `InterfaceDeriver::assertSelfContained()` (`src/Generator/InterfaceDeriver.php:245`).
- **Per-trait derivation** (`PerTraitInterfaceDeriver`) — one interface per trait,
  main interface `extends` only the derived trait interfaces
  (`src/Generator/PerTraitInterfaceDeriver.php:172`). Conflict rule (first trait in
  recursion order owns a method name) is deterministic and tested.
- **Type substitution** (`TypeSubstitutor`) — rewrites `self`/`static`/`parent` and
  an explicit FQCN map, including `union`/`intersection`/`nullable` and
  constant-default expressions (`src/Generator/TypeSubstitutor.php`). Correct.
- **Family derivation** (`FamilyDeriver`) — transitive closure over the
  substitution map, owned-namespace prefix rewrite, root auto-discovery, and
  per-config memoization (`src/Generator/FamilyDeriver.php:148`). Correct.
- **Adapter generation** (`AdapterGenerator`) — delegating adapter with constructor
  injection, return-type wrapping, and union/intersection handling
  (`src/Generator/AdapterGenerator.php`). Meets the leakage invariant across the
  adapter boundary (wrapped returns reference derived interfaces only).
- **Leakage auditing** — shallow `assertSelfContained()` plus a deep
  `LeakageAuditor` (`src/Generator/LeakageAuditor.php`) that scans every method
  return/param type. **Off by default** (`DeriveConfig::$auditLeakage`) so existing
  golden fixtures with intentionally-kept foreign types are not broken — a
  reasonable, documented default.
- **CLI** (`bin/derive`, `DeriveCommand`) — derives a class **or a `.php` file** to
  stdout / file / directory in five modes, with a usage/help surface and exit codes
  (0/1/2/3). Tokenizer-based file scan (`DeriveCommand::classFromFile`) resolves an
  FQCN **without executing** the file — safe.

**Correctness gaps (low severity):**
- `InterfaceDeriver::build()` reports `DerivationResult::$substitutions` as
  `config->typeSubstitutionMap` + `self`/`static`/`parent` even when called via the
  family path (`deriveWithSubstitutor`), so the metadata omits the family
  cross-reference map. Cosmetic for the result object; the rendered source is still
  correct. (`src/Generator/InterfaceDeriver.php:185`)
- `DeriveConfig` carries a **duplicated `@param` annotation** for
  `$reExposeOwnedInterfaces` (lines 36–37 are identical). Doc only, but a real
  copy-paste defect. (`src/Generator/DeriveConfig.php:36`)

---

## 3. Architecture & Hexagonal Fit

Dependency direction (deptrac, 0 violations) is correct: `Contract` (interfaces)
depends on nothing; `Behavior`/`Reflection`/`Resolver`/`Generator`/`Cli`/`Entry`
depend only on layers below them. The `Cli` layer (added in Step 10.16) is wired
into `deptrac.yaml` and allowed to depend on `Entry`, `Generator`, etc.

**Ports & Adapters — where it works well:**
- `Contract/Resolver/*ResolverInterface` + `Resolver/*` (3 impls). Clean port/adapter.
- `Contract/Reflection/ReflectionCacheInterface` + `InMemoryReflectionCache` and
  `PsrCacheReflectionCache`. Textbook adapter pair (the PSR-6 adapter is a genuine
  hexagonal boundary to an external cache). (`src/Reflection/PsrCacheReflectionCache.php`)
- `Contract/Generator/MethodFilterInterface` + 4 filters. Composable, testable.

**Where the ports/adapters story is incomplete:**
- `DeriverInterface` (`derive()`/`deriveSource()`) is implemented only by
  `InterfaceDeriver` and `PerTraitInterfaceDeriver`.
  `FamilyDeriver` and `AdapterGenerator` are **not** behind an interface
  (`src/Generator/FamilyDeriver.php:35`, `src/Generator/AdapterGenerator.php:28`).
  A caller cannot treat "a deriver" polymorphically across all four modes.
- The `DeriveIt` facade is a **static entry point that `new`s concrete derivers
  directly** (`src/DeriveIt.php:35,54,77,106,121,137,151,179,244,259,282`). There is
  **no factory** that assembles derivers/caches from a single config, so
  Dependency Inversion is not applied at the composition root. The user explicitly
  asked about factories: **DeriveIt currently has none** beyond the tiny
  `LazyCreatable::create()` proxy trait on resolvers.
- `ReflectionCacheInterface` is injectable into the individual derivers, **but the
  `DeriveIt` facade does not expose or forward a cache** — each top-level call builds
  a fresh `InMemoryReflectionCache`. Correct, just inconsistent with the otherwise
  injectable design.

**Composition vs inheritance:** `ClassReflection extends ReflectionClass`
(`src/Reflection/ClassReflection.php:25`) is convenient but exposes the entire
reflection API as DeriveIt surface. Carries the documented risk noted in the
archived review; still an open design call, not a bug.

---

## 4. SOLID Assessment

| Principle | Status | Notes |
|-----------|--------|-------|
| **S** — Single Responsibility | ⚠️ Mixed | Most classes are tight. `DeriveCommand` aggregates parse + validate + resolve + run + emit (`src/Cli/DeriveCommand.php:45`); `parse()` alone is the highest-complexity method in the repo (see §6). `AbstractDeriver` is both a template *and* a config/dependency holder. |
| **O** — Open/Closed | ⚠️ Mixed | Adding a mode touches ≥5 places: `parse()` switch, `valueOptions` list, `validate()`, `runDerive()` match, help text, and `DeriveCliInput::$mode`. A mode registry/enum would close these. |
| **L** — Liskov | ✅ | `InterfaceDeriver`/`PerTraitInterfaceDeriver` honour `DeriverInterface`; resolvers/caches honour their contracts. |
| **I** — Interface Segregation | ✅ | `MethodFilterInterface::keep()` and `DeriverInterface` are minimal and client-specific. |
| **D** — Dependency Inversion | ⚠️ Mixed | Edge ports (resolver/cache/filter) depend on abstractions ✅; the facade and CLI depend on concretes ❌ (see §3). |

---

## 5. DRY & Low Complexity

**DRY violations (high-impact):**
1. **`DeriveConfig` withers** — 17 `with*()` methods, each reconstructing all 17
   constructor parameters verbatim (`src/Generator/DeriveConfig.php:128`–`590`). Any
   new flag requires editing the constructor **and** 17 methods. This is the single
   largest maintainability liability in the codebase and the most obvious DRY fix.
2. `DeriveIt::renderInterface()` (`src/DeriveIt.php:222`) re-inlines the
   `PhpNamespace`+`PhpFile` dance that `SourceRenderer` already owns
   (`src/Generator/SourceRenderer.php`). Use `SourceRenderer`.
3. `PerTraitInterfaceDeriver::assertPerTraitSelfContained()` defines an inline
   short-name extractor (`src/Generator/PerTraitInterfaceDeriver.php:177`) that
   duplicates the `strrpos('\\')` idiom used in `LeakageAuditor`/`TypeSubstitutor`/
   `DeriveCommand::fileNameFor`. Hoist to a shared `Naming` helper.
4. The allowed-mode list is duplicated as a literal in `DeriveCommand::validate()`
   (`src/Cli/DeriveCommand.php:343`) and again in `runDerive()`'s `match`
   (`src/Cli/DeriveCommand.php:480`).

**Low complexity:** outside the CLI, cyclomatic complexity is low — filters are
single-expression `keep()`s; `TypeSubstitutor::walk()` is a clean recursion;
`FamilyDeriver::transitiveClosure()` is a bounded fixpoint loop. **Complexity
hotspots:**
- `DeriveCommand::parse()` — `for` + `switch(25 cases)` + value-option branching
  (CC ≈ 30). This is where most of the method's bulk lives; acceptable for a CLI
  parser but the one method worth splitting (e.g. an `ArgParser` value object).
- `FamilyDeriver::discover()` — `while` + nested loops over reflected types (CC
  moderate; correct).
- `AdapterGenerator::bodyFor()` — branching over single/union/intersection/void
  (moderate; readable).

---

## 6. Modern PHP Leverage

| Feature | Current use | Opportunity |
|---------|-------------|------------|
| **Readonly classes** | ✅ `DeriveConfig`, `DerivationResult`, `DeriveDiff`, `BuildMeta`, `DeriveCliInput`, and most generators/filters/caches are `readonly`. | `ClassReflection` cannot be `readonly` while it keeps mutable `static` resolver defaults; consider moving those to a non-static default factory (also aids testability). |
| **Enums** | ❌ Not used. | `DeriveConfig::VISIBILITY_*` are string constants (`'public'`/`'protected'`); mode strings (`'flat'`,`'per-trait'`,…) are bare literals. A `Visibility` backed enum and a `DeriveMode` backed enum would eliminate the magic strings in §9 and tighten `DeriveConfig`/`DeriveCommand`. |
| **Property hooks (8.4)** | ❌ Not used. | The 17 withers (§5.1) could collapse to a single `readonly` class exposing **virtual `set` hooks** (`set visibility(string $v) { return $this->withVisibility($v); }`) — removing the duplication while keeping fluent chaining. |
| **Lazy objects (8.4 `newLazyGhost`/`newLazyProxy`)** | ❌ Not used. | `AdapterGenerator` could offer a lazily-initialised adaptee proxy so the generated adapter defers constructing an expensive adaptee until first call — a natural fit for the "adapter" concept and the platform's existing lazy-object usage. |
| **Weak maps / `WeakReference`** | ❌ Not used. | `InMemoryReflectionCache` keeps strong refs in a plain array (`src/Reflection/InMemoryReflectionCache.php:18`). For very large whole-repo scans a `WeakReference`-keyed store lets unused `ReflectionClass`es be GC'd; low priority since caches are per-derivation/short-lived. |
| **Traits** | ✅ `LazyCreatable` (`src/Behavior/Utility/LazyCreatable.php`). | Shared-helper trait use is appropriate; no abuse. `AbstractDeriver` chose inheritance over a trait for shared deriver plumbing — a reasonable call. |
| **Fibers** | N/A | Derivation is synchronous, CPU-bound reflection work with no I/O concurrency. Fibers would add nothing; correctly not used. |

---

## 7. Interface Coverage (is every class backed by an interface?)

| Class | Backed by interface? | Note |
|-------|----------------------|------|
| `RecursiveClassResolver` / `Interface` / `Trait` | ✅ `Recursive*ResolverInterface` | Clean port/adapter. |
| `InMemoryReflectionCache` / `PsrCacheReflectionCache` | ✅ `ReflectionCacheInterface` | Clean port/adapter. |
| `VisibilityFilter` / `StaticFilter` / `MagicMethodFilter` / `ConstructorFilter` | ✅ `MethodFilterInterface` | Composable. |
| `InterfaceDeriver` / `PerTraitInterfaceDeriver` | ✅ `DeriverInterface` | |
| `FamilyDeriver` | ❌ | Batch deriver; no port. |
| `AdapterGenerator` | ❌ | No `AdapterGeneratorInterface`. |
| `DeriveIt` | ❌ | Static facade; no `Deriver` port. |
| `DeriveCommand` | ❌ | CLI; acceptable (entry/infra), but note for testability. |
| `ClassReflection` | ❌ | `extends ReflectionClass` by design. |
| `DeriveConfig`, `DerivationResult`, `DeriveDiff`, `BuildMeta`, `Naming`, `SourceRenderer`, `TypeSubstitutor`, `MethodSignatureFactory`, `MethodExtractor`, `LeakageAuditor` | ❌ | Value objects / pure utilities; an interface is **not** warranted for these. |

**Verdict:** every *behavior* class that should be swappable is interface-backed
(resolvers, caches, filters, the two primary derivers). The gap is the two
secondary derivers (`FamilyDeriver`, `AdapterGenerator`) and the absence of a
deriver **factory/registry** at the composition root.

---

## 8. Security

DeriveIt is a **local developer tool** that operates on the user's own codebase; it
is not a network service, so the threat model is "untrusted input = a class name or
file path the operator typed." Within that model:

- ✅ `classFromFile()` uses the tokenizer and **never executes** the scanned file
  (`src/Cli/DeriveCommand.php:411`) — no code-injection via a pointed-at file.
- ✅ No `eval`, no `unserialize()` of untrusted data (the only `serialize()` is of a
  config for a cache key, `src/Generator/FamilyDeriver.php:215`).
- ✅ No secrets, no DB, no shell execution.
- ⚠️ `resolveType()` calls `class_exists()`/`interface_exists()`/… on the operator-
  supplied FQCN (`src/Cli/DeriveCommand.php:400`), which autoloads arbitrary code.
  This is the tool's purpose (derive a class), so it is acceptable, but the CLI
  should be documented as "run only on trusted code" (it already is, implicitly).
- ⚠️ `@mkdir(..., 0o777, true)` uses error suppression (`src/Cli/DeriveCommand.php:512,542`).
  The pattern is defended by a re-check (`!is_dir($out)`), so it is safe, but the
  `0o777` mode and the `@` suppressor are magic-number / style smells (see §9).

---

## 9. Magic Strings / Numbers & `else`/`elseif` Audit

**Directives: avoid `else`/`elseif`; avoid magic strings/numbers.**

- `else` / `elseif` in `src/`: only **two** real occurrences, both in
  `DeriveCommand`:
  - `DeriveCommand.php:143` — `} elseif (...)` (value-option consumption). Replace
    with the preceding `if` returning/continuing, or fold into the `match`.
  - `DeriveCommand.php:396` — `} else { $fqcn = $target; }`. Replace with an early
    assignment + guard clause. Everywhere else uses guard clauses / `match`, so the
    codebase is already ~98% compliant; these two are the last to fix.
- **Magic strings:**
  - Mode literals `'flat'`,`'per-trait'`,`'adapter'`,`'family'`,`'family-adapter'`
    appear in `DeriveCommand` (parse/validate/runDerive) and as the `DeriveCliInput`
    `$mode` type — candidate for a `DeriveMode` enum.
  - `'public'`/`'protected'` visibility literals — candidate for a `Visibility` enum
    (currently `DeriveConfig::VISIBILITY_*` string constants).
  - `'void'` literal comparison in `AdapterGenerator::bodyFor()`
    (`src/Generator/AdapterGenerator.php:136`); `'?'` stripping (lines 140–141) —
    would read clearer as a `ReflectionType`/Nette-type check.
  - `'deriveit.reflection.'` cache key prefix
    (`src/Reflection/PsrCacheReflectionCache.php:48`) — hoist to a `private const`.
- **Magic numbers:**
  - `0o777` mkdir mode (`DeriveCommand.php:512,542`) — define a `private const
    OUTPUT_DIR_MODE = 0o755;` (0755 is preferable to 0777 for a tool writing into
    the user's tree).
  - `md5(...)` for the config hash (`FamilyDeriver.php:215`) — algorithm choice, not
    a literal bug; acceptable, but a named constant would document intent.
  - `FamilyDeriver::isUserland()` vendor blocklist
    `['Nette\\','PHPUnit\\','Codeception\\','Symfony\\','Composer\\','Illuminate\\']`
    (`src/Generator/FamilyDeriver.php:302`) — a magic allow/deny list; should be a
    `private const` array (or config) so it is not buried in method logic.

---

## 10. Performance & Maintainability

- **Performance — good:** reflection memoization via `ReflectionCacheInterface`
  (`InMemory` default, `src/Reflection/InMemoryReflectionCache.php`); per-node memo
  in `RecursiveTraitResolver` (`src/Resolver/RecursiveTraitResolver.php:22`);
  per-config memo in `FamilyDeriver` (`src/Generator/FamilyDeriver.php:42`);
  transitively-closed substitution map computed once per family.
- **Maintainability — strong:** deptrac-enforced layers, PHPStan-clean, PSR-12,
  227 tests, rich CLI, tokenizer-safe file scan. The 17-method wither duplication
  (§5.1) and the doc drift (§11) are the only real maintainability drags.
- **Documentation drift (maintainability defect):** `docs/guide/architecture.md` is
  **out of date** vs. the shipped code:
  - it still cites `ClassReflection::setRecursive*Resolver()` (removed in Phase 8;
    resolvers are now **instance-injected**, `src/Reflection/ClassReflection.php`).
  - it lists only `DeriveIt` as `Entry` and says "All three derivers implement
    `DeriverInterface`" — but the `Cli` layer now exists and `FamilyDeriver` does
    **not** implement `DeriverInterface`.
  - It should be refreshed to match the current `deptrac.yaml` layers and the
    instance-injection design.

---

## 11. DX

- ✅ Single `bin/derive` entry with `--help`, sensible exit codes, file/directory
  output, and a tokenizer-based "point at a file" mode.
- ✅ `DeriveConfig` presets (`strict()`/`forAdapter()`/`forFamily()`) and fluent
  withers make the API discoverable.
- ✅ `DeriveDiff` supports CI drift detection (`DeriveIt::diff`).
- ⚠️ The wither API's 17-method duplication hurts *author* DX (every new flag = 17
  edits) even though *consumer* DX is good.
- ⚠️ No factory means consumers who want a shared `ReflectionCache` or a custom
  deriver must reach into concrete constructors individually
  (`new InterfaceDeriver(..., reflectionCache: $cache)`), which is inconsistent with
  the otherwise injectable design.

---

## 12. Findings Register

| ID | Area | Severity | Finding | Recommendation |
|----|------|----------|---------|----------------|
| F1 | DRY / Maintainability | **High** | `DeriveConfig` has 17 duplicated `with*()` methods (`DeriveConfig.php:128`–`590`). | Property hooks (virtual `set`), or a single `with(string $k, mixed $v)` + clone, or a builder. |
| F2 | SOLID / Hexagonal | **Med** | `FamilyDeriver` & `AdapterGenerator` not behind an interface; `DeriveIt` `new`s concretes — no factory/composition root. | Add `FamilyDeriverInterface`/`AdapterGeneratorInterface`; introduce a `DeriveItFactory` that assembles derivers + optional `ReflectionCache`. |
| F3 | Docs / Maintainability | **Med** | `docs/guide/architecture.md` drifts from code (cites removed `setRecursive*Resolver`, omits `Cli` layer, wrong deriver count). | Refresh to current `deptrac.yaml` + instance-injection design. |
| F4 | Magic strings | **Med** | Mode & visibility literals duplicated across CLI/config. | `DeriveMode` + `Visibility` backed enums. |
| F5 | Low complexity / OCP | **Med** | Adding a mode touches ≥5 sites in `DeriveCommand`. | Mode registry/enum + a single `ArgParser` value object; split `parse()`. |
| F6 | Correctness (meta) | **Low** | `DerivationResult::$substitutions` omits the family map when built via `deriveWithSubstitutor`. | Pass the effective map into `BuildMeta`. |
| F7 | Docs | **Low** | Duplicate `@param` for `$reExposeOwnedInterfaces` (`DeriveConfig.php:36`–`37`). | Delete the duplicate line. |
| F8 | Magic numbers / style | **Low** | `0o777` mkdir mode + `@` suppressor in `DeriveCommand.php:512,542`. | `private const OUTPUT_DIR_MODE = 0o755;`; drop `@` (re-check already guards). |
| F9 | Magic strings | **Low** | Vendor blocklist buried in `FamilyDeriver::isUserland()` (`:302`); cache key prefix literal (`:48`). | Hoist to `private const`. |
| F10 | `else`/`elseif` | **Low** | Two remaining `else`/`elseif` in `DeriveCommand.php:143,396`. | Convert to guard clauses / `match`. |
| F11 | DRY | **Low** | `DeriveIt::renderInterface()` re-inlines `SourceRenderer` (`DeriveIt.php:222`). | Use `SourceRenderer`. |
| F12 | Modern PHP | **Low** | No lazy-object adapter option. | Offer `AdapterGenerator` lazy adaptee via `ReflectionClass::newLazyGhost()`. |
| F13 | Security/doc | **Info** | CLI autoloads operator-supplied FQCNs. | Document "run only on trusted code" in `--help`/guide. |

---

## 13. Prioritized Recommendations

**P0 — none outstanding.** All quality gates are green.

**P1 — design hardening:**
- **F1** collapse the `DeriveConfig` withers (property hooks or builder). Highest
  ROI: removes ~450 lines of duplication and makes every future flag a one-line add.
- **F2** introduce deriver interfaces for `FamilyDeriver`/`AdapterGenerator` and a
  `DeriveItFactory` (addresses the explicit factories/hexagonal ask).

**P2 — polish:**
- F3 refresh `docs/guide/architecture.md`.
- F4 mode/visibility enums.
- F5 split `DeriveCommand::parse()` + mode registry.
- F10 remove last `else`/`elseif`; F8 `0o755` const; F9/F7 doc/magic consts; F11
  reuse `SourceRenderer`.

**P3 — optional / post-v1:**
- F6 metadata map; F12 lazy-object adapter; F13 CLI trust note.

---

## 14. Conclusion

DeriveIt is a **complete, well-tested, and architecturally sound** package. Its
core strength is a genuinely hexagonal edge layer — resolver and reflection-cache
**ports with swappable adapters**, composable method filters, and a strictly
enforced leakage invariant — all under deptrac/PHPStan/PSR-12 with 227 green unit
tests and 99% mutation score. Modern-PHP adoption is already above average
(`readonly` value objects, instance-injected resolvers, tokenizer-safe CLI).

The review surfaces **no correctness or security blockers**. The remaining work is
structural polish: the `DeriveConfig` wither duplication (F1) and the missing
deriver interfaces/factory at the composition root (F2) are the two items worth
doing first; the rest are low-risk consistency fixes (magic strings/numbers, the
two `else`/`elseif`, doc drift). Addressing F1–F3 would move the project from
"sound" to "exemplary" on SOLID/DRY and hexagonal criteria without disturbing any
quality gate.
