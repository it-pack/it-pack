# DeriveIt — In-Depth Project Review

> Review date: 2026-08-24
> Scope: `/home/quill/tinker/php/project/it-pack/src/DeriveIt` (the `it-pack/derive-it` package)
> Method: full source reading of `src/` (all 48 production classes), `bin/`, `scripts/`,
> `examples/`, `docs/` (guide + ADRs + roadmap), dependency/packaging inspection
> (`composer why`, `composer show --no-dev`), targeted greps (control flow, magic
> literals, interface backing), and a fresh run of every quality gate.
> Supersedes `docs/archive/REVIEW_3.md` (2026-08-22), whose findings F-1…F-10 were
> verified closed during this review. Finding IDs below are scoped to **this**
> document.

**Assumptions (stated per the review brief):**

- "Every class fully backed by interfaces" is interpreted as *swappable boundaries
  and external collaborators* — not value objects, enums, pure helpers, or the two
  Entry-layer entry points, which legitimately need no port. The per-class verdict
  table is in §7.
- "Modern PHP leverage" is assessed for *fit*, not novelty; features that add
  nothing to a synchronous code-generation tool (Fibers) are marked N/A rather
  than recommended.
- "Green" means the five repository gates (`test:unit`, `cs:check`, `analyse`,
  `architecture`, `infection`) pass on PHP 8.4+.
- This review documents findings only — no source files were modified while
  producing it.

---

## 0. Review Checklist (conceptual)

What this review covers, at a conceptual level:

- **Feature surface & correctness** — all five derivation modes, substitution fidelity, and the leakage invariant.
- **Architecture & hexagonal fit** — ports/adapters at every swappable boundary, the composition-root factory, and enforced dependency direction.
- **SOLID & DRY** — responsibility placement, open/closed extension points, and remaining duplication.
- **Control-flow hygiene & complexity** — absence of `else`/`elseif`, named constants over magic strings/numbers, method size.
- **Interface backing & modern PHP** — port coverage per class; readonly classes, enums, traits, weak references, lazy objects where they earn their place.
- **Performance & security** — reflection caching, memoization, trust model, output-path safety.
- **Maintenance & DX** — packaging correctness, documentation currency, CI, operator ergonomics.

---

## 1. Executive Summary

DeriveIt derives self-contained interfaces (and delegating adapters) from existing
PHP classes across five modes — flat, per-trait, adapter, family, family-adapter —
plus a CLI driver, dry-run drift diffing, structured derivation results, presets,
and a deep leakage auditor. All five quality gates were re-run for this review and
are green on PHP 8.4:

| Check | Command | Result (2026-08-24) |
|-------|---------|---------------------|
| Unit tests | `composer test:unit` | ✅ 247 tests, 509 assertions |
| Coding standard | `composer cs:check` (PSR-12 + `@PHP84Migration`) | ✅ 0 of 80 files to fix |
| Static analysis | `composer analyse` (PHPStan level 5) | ✅ 0 errors |
| Architecture | `composer architecture` (deptrac) | ✅ 0 violations |
| Mutation testing | `composer infection` (min MSI 80%) | ✅ MSI ≈ 99.7% (+19.7pp over gate); 1190 mutants, only 4 escaped |

The Phase 9–12 hardening waves hold up under re-inspection: REVIEW_3's findings are
genuinely closed (exit-code constants, type-literal constants, `CommandInterface`,
`Naming::shortName()`, default-bodied `LazyCreatable`, `WeakReflectionCache`,
index-pointer BFS queue, `--base-dir` sandbox, facade-routed factory).

Two issues worth fixing before tagging a release, neither behavioral:

1. **Undeclared runtime dependency** — `PsrCacheReflectionCache` imports
   `Psr\Cache\CacheItemPoolInterface`, but `psr/cache` is not declared in
   `composer.json`. A production install (`composer show --no-dev`) resolves only
   `nette/php-generator` + `nette/utils`; using the advertised PSR-6 cache fatals
   there today (it currently works only because a dev-chain package,
   `doctrine/annotations`, pulls `psr/cache` transitively). See F-1.
2. **Stale agent/contributor docs** — `AGENTS.md` still describes the Phase-1-era
   tree ("78 passing tests", phases 3–5 "not yet built", pointers to root-level
   `REVIEW.md`/`ROADMAP.md` that now live in `docs/`). See F-2.

Everything else found is low-severity polish (naming-rule duplication, one dead
validation branch, one residual magic string, optional `readonly class`
modernization). No correctness, security, or architectural regressions were found.

---

## 2. Feature Completeness & Correctness

- **Flat derivation** (`DeriveIt::derive*` → `InterfaceDeriver`) — drops
  constructors/destructors/static/magic by default, forces abstract-public
  signatures via Nette's `Factory::fromMethodReflection()` (the single typing
  authority), enforces the leakage invariant through both the shallow guard
  (`assertSelfContained()`, `src/Generator/InterfaceDeriver.php:248`) and the opt-in
  deep `LeakageAuditor`. Correct.
- **Per-trait mode** (`PerTraitInterfaceDeriver`) — one interface per used trait;
  main interface extends exactly the derived trait interfaces; deterministic
  first-trait-wins conflict rule; short-name collision between same-named traits in
  different namespaces fails loudly (EC19). Correct.
- **Type substitution** (`TypeSubstitutor`) — union/intersection walk, nullable
  preservation, `self`/`static`/`parent` keyword rewriting, constant-default
  expression rewriting (A2), owned-namespace prefix rewriting (C2), docblock FQCN
  simplification (G8). Correct.
- **Family derivation** (`FamilyDeriver`) — transitive closure over the
  substitution map (C1), BFS auto-discovery from a root class with an index-pointer
  queue (O(n) dequeue, F-8 of REVIEW_3), per-config memoization keyed by
  `md5(serialize(...))`, namespace-grouped single-file rendering (C4). Correct.
- **Adapter generation** (`AdapterGenerator`) — constructor-injected final adapter,
  instance + static delegation, nullable-aware wrapping, union return dispatch via
  generated `match`, intersection degenerate-to-delegate. Meets the leakage
  invariant across the adapter boundary.
- **Structured results & DX surface** — `DerivationResult` / `BuildMeta` /
  `DeriveDiff` (unified-style drift diff), `analyze()` metadata-only path,
  `deriveMany()` batch, three config presets, fail-fast `DeriveConfig` validation
  (contradictory allow/deny lists, empty renames/re-expose entries, invalid
  visibility strings normalized to the enum).
- **CLI** (`bin/derive` → `DeriveCommand` + declarative `ArgParser`) — five modes,
  file-or-class targets resolved by a tokenizer scan that never executes the target
  file, stdout/file/directory output, named exit codes, rich help.

No correctness regressions were found.

---

## 3. Architecture & Hexagonal Fit

The layering is machine-enforced (deptrac, 0 violations) and matches
`docs/guide/architecture.md`: `Contract` depends on nothing; `Behavior` is the
dependency-free foundation; `Resolver` → `Reflection` → `Generator` stack upward;
`Entry` (`DeriveIt` + `DeriveItFactory`) and `Cli` sit on top with downward-only
edges. Ports/adapters coverage at the swappable boundaries is complete:

| Port (`src/Contract/…`) | Adapter(s) |
|---|---|
| `RecursiveResolverInterface` (+ 3 typed sub-interfaces) | `RecursiveClassResolver`, `RecursiveInterfaceResolver`, `RecursiveTraitResolver` |
| `ReflectionCacheInterface` | `InMemoryReflectionCache` (default), `WeakReflectionCache` (GC-friendly), `PsrCacheReflectionCache` (PSR-6) |
| `DeriverInterface` | `InterfaceDeriver` (flat), `PerTraitInterfaceDeriver` (per-trait) |
| `FamilyDeriverInterface` | `FamilyDeriver` |
| `AdapterGeneratorInterface` | `AdapterGenerator` |
| `MethodFilterInterface` | `VisibilityFilter`, `StaticFilter`, `MagicMethodFilter`, `ConstructorFilter` |
| `CommandInterface` | `DeriveCommand` (output/error sinks injected as closures — hexagonal at the process boundary) |

`DeriveItFactory` is the composition root (Entry): it assembles all four derivers
behind their ports and shares one `ReflectionCacheInterface` + one Nette `Factory`
across a run, so a CLI batch reflects each type once. The static facade
`DeriveIt::factory()` routes every public method through it (REVIEW_3 F-10
resolved). This is a textbook hexagonal arrangement: the domain (derivation rules)
has zero knowledge of the delivery mechanisms (CLI, stdout, PSR-6 cache).

Residual notes (non-blocking):

- `FamilyDeriver::deriveMember()` builds a throwaway `InterfaceDeriver` per member
  without forwarding its own `$reflectionCache`/Nette `$factory` — harmless today
  because the already-built `ReflectionClass` is handed in directly (no
  re-reflection occurs), but it quietly diverges from the "one shared set"
  composition story (F-8).
- The `Cli` layer may depend on `Entry` (deptrac allows it) and does — acceptable:
  the CLI is a delivery adapter consuming the public facade.

**Assessment: hexagonal architecture is satisfied**, with factories (`DeriveItFactory`,
`LazyCreatable::create()` on resolvers, `MethodExtractor`'s default filter-chain
construction, `NamingCollisionException::*` named constructors) consistently placed
at composition edges rather than scattered through domain logic.

---

## 4. SOLID & DRY

| Principle | Status | Evidence |
|---|---|---|
| **SRP** | ✅ Strong | One job per class; the largest method (`InterfaceDeriver::build`, ~85 lines) is cohesive assembly, guarded stepwise; CLI parse/validate/run/emit split cleanly. |
| **OCP** | ✅ Strong | New mode = `DeriveMode` case + one `ArgParser` row + one `match` arm. New filter/resolver/cache = new port implementation. No existing case edited. |
| **LSP** | ✅ Strong | All derivers honor their ports' contracts and result shapes (`array<class-string, InterfaceType>` consistent across `FamilyDeriver`). |
| **ISP** | ✅ Adequate | `MethodFilterInterface` is one method; `CommandInterface` one method; `DeriverInterface` two; nothing fat. |
| **DIP** | ✅ At boundaries | Derivers depend on `ReflectionCacheInterface` and deriver/filter/command ports. Internal value helpers (`TypeSubstitutor`, `SourceRenderer`, `Naming`, `LeakageAuditor`) remain concrete — acceptable under the stated assumption (see §7). |

**DRY wins confirmed this review:** `AbstractDeriver` extracts substitutor seeding /
skip filtering / reflection memoization so flat and per-trait modes stay in
lockstep; `copyWith()` keeps 18 `DeriveConfig` withers one-line; `ArgParser` is a
declarative option table; `SourceRenderer` and `Naming` centralize rendering and
naming.

**DRY debt remaining (all low):**

- Signature-collection loop (`extract → skip-check → rename → signature`) appears
  three times: `InterfaceDeriver::build` (`src/Generator/InterfaceDeriver.php:146–157`),
  `PerTraitInterfaceDeriver::deriveTraitInterfaces` (`:72–83`) and `::derive`
  (`:131–141`). A `protected function collectSignatures(...)` on `AbstractDeriver`
  would collapse all three (F-5).
- Naming rules leak outside `Naming`: `'Adapter'` suffix inline in
  `AdapterGenerator::generate()` (`:51`) **and** `DeriveIt::deriveFamilyAdapter`
  (`src/DeriveIt.php:206`); the `'Interface'` suffix bypasses
  `Naming::derivedInterfaceShortName()` in `PerTraitInterfaceDeriver` (`:59`);
  the `($ns === '' ? '' : $ns . '\\') . X` join is repeated in `Naming`,
  `AdapterGenerator::interfaceFqcn()` and twice inside
  `DeriveIt::deriveFamilyAdapter()` (F-4).
- `InterfaceDeriver::build()` recomputes `$selfFqcn`/`$parentFqcn` locally although
  `AbstractDeriver::createTypeSubstitutor()` derives exactly the same values — they
  could be exposed from `TypeSubstitutor` instead (F-9).
- `FamilyDeriver::deriveReflections()` carries a stacked, dead duplicate docblock
  (`src/Generator/FamilyDeriver.php:147–150`) left behind by a refactor (F-6).

---

## 5. Modern PHP Leverage

| Feature | Status | Notes |
|---|---|---|
| Backed enums | ✅ Used well | `DeriveMode` (5 modes), `Visibility`; typed constants (`VISIBILITY_PUBLIC`) reference enum cases; adding a mode is OCP-clean. |
| `readonly` classes | ✅ Extensive | All filters, value objects, both concrete derivers, `TypeSubstitutor`, `AdapterGenerator` — and notably `abstract readonly class AbstractDeriver`, a correct 8.4 use of the modifier on an abstract base. `DeriveConfig` has all-readonly props but is *not* marked `readonly class`; it could be (F-3). |
| Traits | ✅ Appropriate | `LazyCreatable` supplies a default-bodied `create()` (REVIEW_3 F-6 resolved) — the trait binds `self` to the using class; no static mutable state anywhere. |
| Weak references | ✅ Provided | `WeakReflectionCache` (`WeakReference<ReflectionClass>`) ships as an injectable alternative for long-running scans. Minor wart: redundant `?? null` in `get()` (`src/Reflection/WeakReflectionCache.php:27`, F-11). A `WeakMap` adds nothing here — keys would have to be objects the caller retains. |
| Lazy objects (8.4) | ➖ N/A for the library | `newLazyProxy`/`newLazyGhost` matter for *generated* adapters, not for a codegen tool itself; correctly deferred/tracked rather than forced. |
| Property hooks / virtual properties | ➖ Not needed | `DeriveConfig::copyWith()` (immutable update via `get_object_vars` + reconstruct) is cleaner than any hook-based emulation; asymmetric visibility would be redundant with promoted `readonly`. |
| Fibers | ➖ N/A | Synchronous generation pipeline; no I/O concurrency to schedule. |

One caution flag: `DeriveConfig::copyWith()` keys withers by property-name strings
(`['includeStatic' => …]`). Renaming a constructor parameter breaks every wither
only at runtime — a PHPStan/IDE blind spot inherent to the pattern (F-10).

**Verdict:** modern-PHP usage is strong and purposeful; nothing obvious is missing.

---

## 6. Control-Flow Hygiene: `else`/`elseif`, Magic Strings/Numbers, Complexity

- **`else` / `elseif`:** ✅ **Zero executable occurrences in `src/`.** The only grep
  hit is the English word "else" inside a docblock (`src/Cli/ArgParser.php:20`).
  Branching is expressed with early-return guards, guard clauses, and five `match`
  expressions (`DeriveCommand`, `ArgParser`, `MethodSignatureFactory`,
  `TypeSubstitutor` ×2, `AdapterGenerator`). Directive fully satisfied.
- **Magic strings/numbers:** ⚠️ Nearly eliminated; residuals:
  - `'Family'` literal as the output key for family-adapter mode
    (`src/Cli/DeriveCommand.php:274`) — should be a named constant alongside the
    exit codes (F-7).
  - Preset names `'strict'|'adapter'|'family'` are bare strings duplicated between
    the `match`, the error message, and the help text in `ArgParser` — candidate
    for a small backed enum like `DeriveMode` (F-7).
  - Suffix literals covered under §4/F-4.
  Everything else flagged in prior reviews is now a constant: exit codes
  (`EXIT_SUCCESS…OUTPUT`), `OUTPUT_DIR_MODE = 0o755`, `TYPE_VOID`/separators,
  `OVERRIDE_ATTRIBUTE`, `VENDOR_PREFIXES`, `CACHE_KEY_PREFIX`.
- **Low complexity:** ✅ Methods are short and single-purpose. `bodyFor()` is the
  branchiest (~35 lines) but reads as sequential guards with early returns;
  `validate()` contains one *unreachable* defensive check — after `--root`
  promotes the input to `FAMILY` mode (`src/Cli/DeriveCommand.php:126–132`), the
  subsequent `root !== null` test inside the FLAT/PER_TRAIT/ADAPTER arm (`:135`)
  can never fire (F-13). No cyclomatic hotspots; `ArgParser::buildConfig`'s fifteen
  sequential `if ($s->flag)` lines are repetitive but table-convertible if ever
  extended (not required).

---

## 7. Interface Coverage of Every Class

Boundary classes are fully ported. Residual concretes are internal collaborators
and value/utility types, which need no port under the stated assumption:

| Class | Interface/backing | Verdict |
|---|---|---|
| `InterfaceDeriver`, `PerTraitInterfaceDeriver` | `DeriverInterface` (+ shared `AbstractDeriver`) | ✅ |
| `FamilyDeriver` | `FamilyDeriverInterface` | ✅ |
| `AdapterGenerator` | `AdapterGeneratorInterface` | ✅ |
| `RecursiveClassResolver`, `RecursiveInterfaceResolver`, `RecursiveTraitResolver` | typed resolver interfaces | ✅ (but see F-12: two of three are not `final`) |
| `InMemoryReflectionCache`, `PsrCacheReflectionCache`, `WeakReflectionCache` | `ReflectionCacheInterface` | ✅ (F-1 packaging caveat on the PSR-6 one) |
| Four method filters | `MethodFilterInterface` | ✅ |
| `DeriveCommand` | `CommandInterface` | ✅ |
| `DeriveIt`, `DeriveItFactory` | — | ✅ Facade / composition root; no port meaningful. |
| `DeriveConfig`, `DerivationResult`, `BuildMeta`, `DeriveDiff`, `DeriveCliInput`, `ParseState`, `Visibility`, `DeriveMode` | — | ✅ Value objects / enums. |
| `Naming`, `LeakageAuditor`, `SourceRenderer`, `TypeSubstitutor`, `MethodSignatureFactory`, `MethodExtractor`, `BuildMeta` helpers | — | ✅ Pure/internal helpers; ports optional (prior F-3 explicitly skipped as low-value). |
| `ClassReflection` | extends `ReflectionClass` | ✅ Deliberate facade; the resolvers behind it are ported. |
| Exceptions ×3 | extend `LogicException` | ✅ Named constructors on `NamingCollisionException`. |

**Conclusion:** every swappable boundary is interface-backed; nothing new needs a
port.

---

## 8. Performance

- ✅ Reflection memoization is layered well: injectable `ReflectionCacheInterface`
  (instance-scoped, never static — deliberately mutation-test friendly),
  per-node memo in `RecursiveTraitResolver`, per-config interface memo in
  `FamilyDeriver` (`$this->cache[$key] ??= ...`), index-pointer BFS queue
  (O(n) dequeue), discovery reusing already-built reflections.
- ◐ `configHash()` runs `md5(serialize(map) . serialize(prefixMap) . serialize(config))`
  per derive call — fine at current scale; switch to stable identity only if
  profiling ever demands it.
- ◐ `TypeSubstitutor::simplifyDocblock()` loops the whole map per docblock — opt-in
  (`preserveDocblocks`) and docblocks are small; acceptable.
- ◐ `FamilyDeriver::deriveMember()` constructs a fresh `InterfaceDeriver` (with its
  own default cache) per member instead of sharing the family's — no measurable
  cost since reflections are passed in, but it defeats the factory's sharing story
  (F-8).

No performance blockers.

---

## 9. Security

- ✅ **No input-executed code paths.** `classFromFile` uses `token_get_all` only —
  never `include`/`eval`. The CLI autoloads operator-supplied class names, and the
  trust model ("run only on code you trust") is stated in `--help` and the docs.
- ✅ **Output-path sandbox:** `--base-dir` rejects lexical escapes via realpath +
  canonicalized comparison; created directories use `0o755`, not world-write.
  Note: canonicalization does not resolve symlinks in the *target* path, so a
  symlink planted inside the base dir can still point out — documented as a purely
  lexical check, consistent with the trusted-operator model (F-14, informational).
- ✅ No shell execution, SQL, deserialization of untrusted data, or secret handling
  anywhere in `src/`.

Posture appropriate for a developer tool operating on first-party code.

---

## 10. Maintenance & DX

- ✅ **CI gates everything**: `.github/workflows/ci.yml` runs validate/cs/analyse/
  architecture/infection/coverage on a PHP 8.4 + 8.5 matrix, on PRs and pushes.
- ✅ **Test depth:** 25 unit test classes incl. golden-file snapshot suites against
  48 committed expected sources, dedicated phase-hardening suites (Phase 9–15
  named), mutation-coverage tests, and a real-package Functional test.
- ✅ **Docs suite:** user guide (index/features/usage/architecture), four ADRs,
  detailed ROADMAP with per-phase status, ten runnable `examples/`, golden
  regeneration script (`scripts/regenerate_goldens.php`).
- ✅ **DX niceties:** deterministic output enabling CI drift detection
  (`DeriveIt::diff`), presets, rich `--help`, named exit codes, sandboxed `--out`.
- ❌ **Stale `AGENTS.md` (F-2):** claims Phases 0–6 complete / 113 tests /
  "adapter generation not built"; points readers to root-level `REVIEW.md`,
  `IMPLEMENTATION_PLAN.md`, `ROADMAP.md` that have moved to `docs/archive/` and
  `docs/`; references retired scratch paths. Any contributor or AI agent following
  it will mis-navigate the repo. Update to post-Phase-12 reality (or reduce it to a
  pointer into `docs/guide/` + `docs/ROADMAP.md`).
- ◐ **Packaging warnings (F-1, F-15):** undeclared `psr/cache` (§1) and missing
  `license` field in `composer.json` (`composer validate` warns).

---

## 11. Findings & Recommendations

Severity: **High** = blocks release / correctness or security risk · **Medium** =
fix before tag · **Low** = quality debt · **Info** = note only. IDs are scoped to
this document (successors of `docs/archive/REVIEW_3.md` F-1…F-10, all verified
closed).

| ID | Severity | Area | Finding | Recommended action |
|----|----------|------|---------|--------------------|
| F-1 | **Medium** | Packaging / DIP | `PsrCacheReflectionCache` imports `Psr\Cache\CacheItemPoolInterface` but `psr/cache` is absent from `composer.json`; `composer show --no-dev` proves it is missing from production installs (currently shadowed by dev-only `doctrine/annotations`). | Add `"psr/cache": "^1.0 || ^2.0 || ^3.0"` to `require` (interface-only, negligible weight), or move the adapter to `suggest` with a classmap-guarded factory. First option is simplest and honest. |
| F-2 | **Medium** | Maintenance / DX | `AGENTS.md` is stale: wrong phase status (says Phases 3–5 unbuilt, 113 tests), broken pointers to removed root docs (`REVIEW.md`, `IMPLEMENTATION_PLAN.md`), outdated layout description. | Rewrite to current state (Phases 0–12, 247 tests, docs/ layout) or slim it to a router into `docs/guide/` and `docs/ROADMAP.md`. |
| F-3 | Low | Modern PHP | `DeriveConfig` is `final class` with exclusively `readonly` properties — eligible for `final readonly class`. | Mark it `readonly class` for consistency with the rest of the codebase (no behavior change). |
| F-4 | Low | DRY / naming | Derived-name rules live outside `Naming`: `'Adapter'` suffix duplicated (`AdapterGenerator.php:51`, `DeriveIt.php:206`); `'Interface'` suffix hand-built (`PerTraitInterfaceDeriver.php:59`); namespace-join expression repeated ×4. | Add `Naming::adapterShortName()`, reuse `derivedInterfaceShortName()` for traits, and a `Naming::join(string $namespace, string $short): string` helper; adopt at all sites. |
| F-5 | Low | DRY | Signature-collection loop triplicated across flat/per-trait derivers. | Hoist `collectSignatures(iterable $methods, TypeSubstitutor $s): list<Method>` into `AbstractDeriver`. |
| F-6 | Low | Maintainability | Dead duplicate docblock stacked above `FamilyDeriver::deriveReflections()` (`FamilyDeriver.php:147–150`). | Delete the orphaned block. |
| F-7 | Low | Magic strings | `'Family'` output-key literal (`DeriveCommand.php:274`); preset names `'strict'/'adapter'/'family'` as bare strings in `ArgParser` (match + message + help). | Constant for the key; introduce a backed `Preset` enum mirroring `DeriveMode`. Also consider deriving `VALUE_OPTIONS` from the handler table so arity lives in one place. |
| F-8 | Low | Consistency / perf | `FamilyDeriver::deriveMember()` creates per-member `InterfaceDeriver`s without forwarding its own `$reflectionCache`/`$factory`. | Pass them through the constructor call (one line) so family runs genuinely share the assembled set. |
| F-9 | Low | DRY | `InterfaceDeriver::build()` recomputes self/parent derived FQCNs already computed inside `createTypeSubstitutor()`. | Expose `selfFqcn()/parentFqcn()` accessors on `TypeSubstitutor`; report from those. |
| F-10 | Low | Robustness | `DeriveConfig::copyWith()` addresses properties by string keys — renames break withers only at runtime. | Acceptable trade-off; alternatively generate withers or assert key validity in `copyWith()` via a `property_exists` loop. |
| F-11 | Trivial | Code clarity | `WeakReflectionCache::get()` wraps an already-nullable value: `$ref->get() ?? null`. | Simplify to `return $ref?->get();`. |
| F-12 | Low | Consistency | `RecursiveClassResolver` and `RecursiveInterfaceResolver` are non-`final`; `RecursiveTraitResolver` is. | Make all three `final` (nothing extends them). |
| F-13 | Low | Complexity | Unreachable defensive branch in `DeriveCommand::validate()` (`:135`): after `--root` promotion the mode can never be FLAT/PER_TRAIT/ADAPTER with `root !== null`. | Remove the dead inner check or convert it to an assertion comment; current form implies behavior that cannot occur. |
| F-14 | Info | Security | `--base-dir` sandbox is lexical; symlinked targets inside the base dir can escape. | Documented and accepted under the trusted-operator model; optionally `realpath()` the final parent directory when it exists. |
| F-15 | Info | Packaging | `composer validate` warns: no `license` field. | Declare a license (or `"proprietary"`) before Packagist publication. |

**Priority order:** F-1 and F-2 before tagging v0.1.0; F-3…F-13 are a single
afternoon of polish; F-14/F-15 informational.

**Resolution (2026-08-24):** all findings closed via `docs/REFACTORING_PLAN.md`
Milestones A–E.

| ID | Resolution | Where |
|----|------------|-------|
| F-1 | ✅ Addressed | `psr/cache: ^1.0 \|\| ^2.0 \|\| ^3.0` declared; pool documented as optional consumer infrastructure (R3-1). |
| F-2 | ✅ Addressed | `AGENTS.md` rewritten to post-Phase-12 reality with live gate baseline and `docs/` pointers + maintenance rule (R3-2). |
| F-3 | ✅ Addressed | `DeriveConfig` is now `final readonly class` (R3-11). |
| F-4 | ✅ Addressed | `Naming::INTERFACE_SUFFIX` / `ADAPTER_SUFFIX` / `interfaceShortName()` (unconditional per-trait append preserved) / `adapterShortName()` / `join()`; all call sites migrated (R3-4). |
| F-5 | ✅ Addressed | `AbstractDeriver::signatureFor()`; the three collection loops rewired, single `cloneWithName` site (R3-5). |
| F-6 | ✅ Addressed | Dead stacked docblock deleted from `FamilyDeriver` (R3-6). |
| F-7 | ✅ Addressed | `Cli\Preset` backed enum drives parse/message/help; `VALUE_OPTIONS` → arity map; `DeriveCommand::FAMILY_ADAPTER_SOURCE_KEY` constant (R3-8). |
| F-8 | ✅ Addressed | `FamilyDeriver::deriveMember()` forwards its cache + Nette factory to member derivers (R3-12). |
| F-9 | ✅ Addressed | `TypeSubstitutor::selfFqcn()/parentFqcn()` accessors; `InterfaceDeriver::build()` reports from the substitutor without recomputing (R3-7). |
| F-10 | ✅ Addressed | `copyWith()` rejects unknown override keys (`LogicException`) + test (R3-13). |
| F-11 | ✅ Addressed | `WeakReflectionCache::get()` collapsed to a nullsafe expression (R3-14). |
| F-12 | ✅ Addressed | Both resolvers marked `final`; all three consistent (R3-10). |
| F-13 | ✅ Addressed | Unreachable root check removed from `DeriveCommand::validate()`; promotion intent documented; promotion test added (R3-9). |
| F-14 | ✅ Addressed (doc-only) | Lexical-sandbox caveat recorded in `assertSafeOutputPath()` docblock, `--help`, and `docs/guide/usage.md`; hardening path intentionally not taken under the trusted-operator model (R3-15). |
| F-15 | ✅ Addressed | `"license": "MIT"` declared + MIT `LICENSE` file; `composer validate` warning-free (R3-3). |

Post-plan gates (2026-08-24): `test:unit` 252 tests / 525 assertions · `cs:check` 0 ·
`analyse` 0 · `architecture` 0 violations · `infection` MSI ≈ 99.8% (1200 mutants,
2 escaped) · golden files byte-stable throughout.

---

## 12. Out of Scope / Notes

- Only `it-pack/derive-it` was examined; sibling monorepo packages were not touched.
- Mutation-testing nuance, stated transparently: of 1190 mutants, 966 were scored
  via timeout and 214 killed by tests (6 errored, 4 escaped, 0 not covered). The
  timeout share reflects mutants in generated-code strings that hang the suite;
  the headline MSI (≈99.7%) therefore overstates direct test kill evidence, but an
  escape count of 4 across the full mutant set remains strong. Raising `timeout`
  or scoping heavy-string mutators are optional follow-ups.
- Gate results quoted in §1 were produced by this review's own runs, not copied
  from prior documentation.
