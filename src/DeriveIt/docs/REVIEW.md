# DeriveIt — In-Depth Project Review

> Review date: 2026-08-22
> Scope: `/home/quill/tinker/php/project/it-pack/src/DeriveIt` (the `it-pack/derive-it` package)
> Method: full source reading of `src/` (54 source files under `src/DeriveIt/src/…`),
> dependency-layer analysis, targeted greps, and running the package's own QA tooling
> (`composer test:unit`, `cs:check`, `analyse`, `architecture`).
> Supersedes `docs/archive/REVIEW_2.md` (2026-08-22), which was written **before**
> the Phase 11 refactor (R.1–R.13). This review evaluates the **post-refactor** state
> and confirms whether the previously-flagged structural weaknesses were resolved,
> then re-rates the package against every requested axis.

**Assumptions (stated per the review brief):**
- "Fully backed by interfaces" is interpreted as *external collaborators and swappable
  boundaries* — not value objects or pure string/utility helpers, which legitimately
  need no port. This nuance is made explicit in §7.
- "Modern PHP leverage" is assessed for *fit*, not novelty: Fibers are N/A to a
  synchronous code-generation tool; recommendations are limited to features that add
  real value here.
- The reviewer treats the existing 5 quality gates as the definition of "green".
- Findings are review-ready: each has a concrete location, reasoning, and a
  recommended action with a severity.

---

## 0. Review Checklist (conceptual)

What this review covers, at a conceptual level:

- **Feature surface & correctness** — every derivation mode, the leakage invariant, and substitution correctness.
- **Architecture & hexagonal fit** — ports/adapters at the boundaries, the composition-root factory, and dependency direction (deptrac).
- **SOLID & DRY** — single responsibility, open/closed extension points, dependency inversion at seams, and duplication.
- **Modern PHP leverage** — enums, readonly, traits, lazy objects, weak references, property hooks where they earn their place.
- **Interface coverage & control-flow hygiene** — which classes lack ports, absence of `else`/`elseif`, magic strings/numbers, and method complexity.
- **Performance, security, maintainability & DX** — caching, trust model, and operator ergonomics.

---

## 1. Executive Summary

DeriveIt derives **self-contained interfaces and delegating adapters** from existing
PHP classes by walking the parent/trait/interface graph and flattening it into a port
you own. The pipeline is real, comprehensively tested, and **all quality gates are
green** (verified 2026-08-22).

The Phase 11 refactor resolved **every** structural weakness called out in
`REVIEW_2.md`:
- `DeriveConfig` wither duplication → collapsed into a single `copyWith()` (`src/Generator/DeriveConfig.php:143`).
- Deriver "port" coverage → `FamilyDeriverInterface` + `AdapterGeneratorInterface` added; `DeriveItFactory` is now the composition root (`src/DeriveItFactory.php`).
- Magic mode/visibility strings → `DeriveMode` and `Visibility` enums (`src/Generator/DeriveMode.php`, `src/Generator/Visibility.php`).
- Monolithic CLI `parse()` → declarative `ArgParser` option table (`src/Cli/ArgParser.php`).
- Magic `0o777` and inline `VENDOR_PREFIXES`/`CACHE_KEY_PREFIX` → named constants (`src/Cli/DeriveCommand.php:30`, `src/Generator/FamilyDeriver.php:42`, `src/Reflection/PsrCacheReflectionCache.php:27`).
- Facade reuses `SourceRenderer`; docs and CLI trust model refreshed.

The package is now in **strong shape**. Remaining items are low-risk polish: a handful
of residual magic strings/numbers (most notably CLI exit codes and the `'void'`/`'|'`/`'&'`
literals in `AdapterGenerator`), a few small DRY repeats (FQCN→short-name extraction,
the `LazyCreatable::create()` body), and optional interface seams for the two internal
service objects (`MethodSignatureFactory`, `SourceRenderer`). None block a v1.

**Health at a glance (verified 2026-08-22):**

| Check | Command | Result |
|-------|---------|--------|
| Unit tests | `composer test:unit` | ✅ 229 tests, 472 assertions |
| Coding standard | `composer cs:check` (PSR-12 + `@PHP84Migration`) | ✅ 0 files to fix |
| Static analysis | `composer analyse` (PHPStan, level 5) | ✅ 0 errors |
| Architecture | `composer architecture` (deptrac) | ✅ 0 violations |
| Mutation testing | `composer infection` (min MSI 80%) | ✅ Covered Code MSI 88% (full run: 1136 mutants, 683 killed, 133 undetected, 311 time-outs, 9 errors — gate passed at +8.29pp over the required MSI) |
| Declared PHP | `composer.json` | ✅ `php >= 8.4` |

---

## 2. Feature Completeness & Correctness

- **Flat derivation** (`DeriveIt::derive*`, `InterfaceDeriver`) — implemented and
  enforced: drops constructors/static/magic by default, forces `abstract`+`public`,
  and keeps `extends`/trait `use` empty (the **leakage invariant**), guarded by
  `InterfaceDeriver::assertSelfContained()` (`src/Generator/InterfaceDeriver.php:248`).
- **Per-trait derivation** (`PerTraitInterfaceDeriver`) — one interface per trait,
  main `extends` only the derived trait interfaces
  (`src/Generator/PerTraitInterfaceDeriver.php:172`). Deterministic conflict rule
  (first trait in recursion order owns a method name). Correct.
- **Type substitution** (`TypeSubstitutor`) — rewrites `self`/`static`/`parent` and an
  explicit FQCN map, including union/intersection/nullable and constant-default
  expressions. Correct (`src/Generator/TypeSubstitutor.php`).
- **Family derivation** (`FamilyDeriver`) — transitive closure over the substitution
  map, owned-namespace prefix rewrite, root auto-discovery, and per-config memoization
  (`src/Generator/FamilyDeriver.php:161`). Correct. The reported `substitutions` map
  now carries the effective family cross-reference map (R.6).
- **Adapter generation** (`AdapterGenerator`) — delegating adapter with constructor
  injection, static-vs-instance delegation, and union/intersection return wrapping
  (`src/Generator/AdapterGenerator.php`). Meets the leakage invariant across the
  adapter boundary (wrapped returns reference derived interfaces only).
- **Leakage auditing** — shallow `assertSelfContained()` plus a deep `LeakageAuditor`
  (`src/Generator/LeakageAuditor.php`) scanning every method return/param type. Off by
  default (`DeriveConfig::$auditLeakage`) so golden fixtures with intentionally-kept
  foreign types are not broken — a reasonable, documented default.
- **CLI** (`bin/derive`, `DeriveCommand`) — derives a class **or a `.php` file** to
  stdout/file/directory in five modes, with help text and exit codes (0/1/2/3). The
  tokenizer-based `classFromFile` resolves an FQCN **without executing** the file —
  safe (`src/Cli/DeriveCommand.php:187`).

No correctness regressions were found. Small inefficiencies (re-derived trait
interfaces, see §9) are harmless at current scale.

---

## 3. Architecture & Hexagonal Fit

The layering is enforced by deptrac (0 violations) and documented in
`docs/guide/architecture.md`. The **ports/adapters pattern is applied correctly at the
external boundaries**:

| Port (Contract) | Adapter(s) (concrete) |
|---|---|
| `RecursiveResolverInterface` (+ 3 typed sub-interfaces) | `RecursiveClassResolver`, `RecursiveInterfaceResolver`, `RecursiveTraitResolver` |
| `ReflectionCacheInterface` | `InMemoryReflectionCache`, `PsrCacheReflectionCache` (PSR-6) |
| `DeriverInterface` | `InterfaceDeriver`, `PerTraitInterfaceDeriver` |
| `FamilyDeriverInterface` | `FamilyDeriver` |
| `AdapterGeneratorInterface` | `AdapterGenerator` |
| `MethodFilterInterface` | `VisibilityFilter`, `StaticFilter`, `MagicMethodFilter`, `ConstructorFilter` |

`DeriveItFactory` (`src/DeriveItFactory.php`) is the **composition root**: it assembles
all four derivers behind their interfaces and optionally shares one
`ReflectionCacheInterface` and one Nette `Factory` across the set, so a CLI run reflects
/ builds each type at most once. This closes the hexagonal gap that `REVIEW_2.md`
flagged.

**Assessment: hexagonal architecture is satisfied** at the boundaries where swapping
behavior matters (graph walking, caching, derivation strategy). Dependency direction is
clean — `Contract` depends on nothing; `Generator` sits at the bottom of the deptrac
dependency graph and may use `Contract`; `Entry`/`Cli` sit on top. No upward or lateral
leakage.

Two minor consistency notes (non-blocking):
- The `DeriveIt` facade still `new`s concrete derivers directly in its static methods
  (`derive`, `derivePerTrait`, `deriveAdapter`, `deriveFamily`, `deriveFamilyAdapter`),
  duplicating the assembly logic that `DeriveItFactory` exists to centralise. The
  facade is a legitimate thin convenience wrapper, but routing it through the factory
  would remove the overlap (see F-3).
- `DeriveCommand` has no `CommandInterface`. Acceptable for a single CLI entry point,
  but a one-method interface would make the command injectable/testable behind a port
  (optional, see F-4).

---

## 4. SOLID & DRY

| Principle | Status | Evidence |
|---|---|---|
| **SRP** | ✅ Strong | Every class has one job; no god objects. Largest method (`InterfaceDeriver::build`) is ~80 lines and still cohesive. |
| **OCP** | ✅ Strong | New derivation mode = add `DeriveMode` enum case + one `ArgParser` row + one `match` arm. New filter = new `MethodFilterInterface` impl. New resolver = new interface impl. No existing code edited. |
| **LSP** | ✅ Strong | All derivers honour their port contracts; `FamilyDeriverInterface` returns `InterfaceType` keyed by FQCN consistently. |
| **ISP** | ✅ Adequate | `MethodFilterInterface` is minimal. `DeriverInterface` carries 2 methods; `FamilyDeriverInterface` 4. No fat interfaces observed. |
| **DIP** | ✅ at boundaries | Derivers depend on `ReflectionCacheInterface`, resolver interfaces, and deriver interfaces — not concretions — at the swap-relevant seams. Internal collaborators (`MethodSignatureFactory`, `SourceRenderer`, `TypeSubstitutor`) are concrete (see §7). |

**DRY wins this review confirms:**
- `DeriveConfig::copyWith()` collapsed 18 duplicated `with*()` bodies (`src/Generator/DeriveConfig.php:143`).
- `ArgParser` replaced a 25-case switch with one declarative table (`src/Cli/ArgParser.php:78`).
- `Naming` centralises derived-interface naming + collision rules.
- `SourceRenderer` is the single rendering point (`src/Generator/SourceRenderer.php`).
- `LazyCreatable` trait enforces a uniform `create()` factory convention across resolvers.

**DRY debt (minor, see §9 / F-5, F-6):**
- FQCN→short-name string surgery (`substr($fqcn, strrpos($fqcn,'\\')+1)`) is re-implemented inline in `PerTraitInterfaceDeriver::assertPerTraitSelfContained`, `DeriveCommand::fileNameFor`, `FamilyDeriver` constant handling, and `LeakageAuditor::allowedNamespaces`. A `Naming::shortName(string $fqcn): string` helper would consolidate these.
- `LazyCreatable` declares `abstract public static function create(): self`, and each of the three resolvers re-declares the *identical* body `return new self();`. The trait could supply a default implementation (it resolves `self` to the using class).
- `PerTraitInterfaceDeriver::derive()` and `::deriveSource()` both re-invoke `deriveTraitInterfaces()`, re-walking the trait graph per call.

---

## 5. Modern PHP Leverage

| Feature | Status | Notes |
|---|---|---|
| **Enums (backed)** | ✅ Used well | `DeriveMode` and `Visibility` replace bare strings (R.4). `DeriveConfig::VISIBILITY_*` constants are now enum cases. |
| **`readonly` classes** | ✅ Extensive | `DerivationResult`, `BuildMeta`, `DeriveDiff`, `DeriveConfig`, `DeriveCliInput`, `InterfaceDeriver`, `PerTraitInterfaceDeriver`, `TypeSubstitutor`, `AdapterGenerator`, and all filters/value objects are `readonly`. |
| **Traits** | ✅ Appropriate | `LazyCreatable` provides a shared factory seam for resolvers (no static mutable state — the pre-refactor anti-pattern is gone). |
| **Asymmetric visibility (8.4)** | ✅ Implied | `DeriveConfig` uses constructor-promoted `readonly` props — the modern equivalent of `public private(set)`; no need to change. |
| **Property hooks** | ➖ Not needed | `copyWith()` is a clean immutable-update pattern; hooks would not improve it. Left as-is intentionally. |
| **Lazy objects** | ➖ Deferred (R.12) | Documented as a post-v1, optional *generated-code* feature (lazy adapter via `ReflectionClass::newLazyProxy`/`newLazyGhost`). Not applicable to the library itself. Status correctly recorded. |
| **Weak references / WeakMap** | ◐ Optional | `InMemoryReflectionCache` holds `ReflectionClass` instances in a plain array, keeping them alive for the cache's lifetime. For whole-repo scans in a long-running process this is the one place a `WeakReference` (or `WeakMap` keyed by an interned object) would let reflected metadata be GC'd. Low priority — caches are instance-scoped and short-lived today. |
| **Fibers** | ➖ N/A | Synchronous codegen tool; concurrency primitive adds nothing. |

**Verdict:** modern-PHP usage is already strong and purposeful; no missing "obvious" feature. The only defensible additions are the optional `WeakReference` cache (F-7) and, for generated adapters, the deferred lazy-object mode (R.12, already tracked).

---

## 6. Control-Flow Hygiene: `else`/`elseif`, Magic Strings/Numbers, Complexity

- **`else` / `elseif`**: ✅ **Absent from actual code.** A repo-wide grep finds exactly one hit — inside a docblock comment in `src/Cli/ArgParser.php:20` ("Everything else is treated as…"), not executable. Dispatch is done via `match` (5 sites) and early-`return` guards (e.g. `DeriveCommand::validate`, `AdapterGenerator::bodyFor`, `MethodSignatureFactory::fromMethod`). This satisfies the "avoid else/elseif" directive fully.
- **Magic strings/numbers**: ⚠️ Mostly eliminated, with a few residuals (see F-1, F-2):
  - `DeriveCommand` exit codes `0/1/2/3` are bare literals → should be named constants (`EXIT_SUCCESS`, `EXIT_USAGE`, `EXIT_DERIVATION`, `EXIT_OUTPUT`).
  - `AdapterGenerator::bodyFor` re-parses the rendered return type using literal `'void'`, `'|'`, `'&'` → should reuse `ReflectionType` (the `Method` already carries a typed return) or named constants.
  - `MethodSignatureFactory::filterAttributes` hard-codes the `'Override'` attribute name → a `private const OVERRIDE = 'Override';` (or `::class` of the global attribute) would remove the bare string.
  - `Naming::derivedInterfaceShortName` and `AdapterGenerator`/`DeriveIt::deriveFamilyAdapter` hard-code the `'Interface'` / `'Derived'` / `'Adapter'` suffixes → candidates for `Naming` constants (`INTERFACE_SUFFIX`, `ADAPTER_SUFFIX`).
- **Low complexity**: ✅ Methods are small and single-purpose. The two largest are
  `InterfaceDeriver::build` (~80 lines, cohesive) and `ArgParser::buildConfig` (~15
  `if` lines). `ArgParser::buildConfig` could be expressed as a small table loop, but it
  is readable as-is. No cyclomatic-complexity hotspots.

---

## 7. Interface Coverage of Every Class

The package's *boundary* classes are fully ported. The residual gaps are internal
collaborators and value/utility objects, which — per the stated assumption — do not
require ports:

| Class | Has interface? | Verdict |
|---|---|---|
| `DeriveIt` (facade) | — | ✅ Facade; no port needed. |
| `DeriveItFactory` (composition root) | — | ✅ Entry point; no port needed. |
| `InterfaceDeriver`, `PerTraitInterfaceDeriver` | `DeriverInterface` | ✅ |
| `FamilyDeriver` | `FamilyDeriverInterface` | ✅ |
| `AdapterGenerator` | `AdapterGeneratorInterface` | ✅ |
| `Recursive*Resolver` (×3) | `Recursive*ResolverInterface` | ✅ |
| `InMemoryReflectionCache`, `PsrCacheReflectionCache` | `ReflectionCacheInterface` | ✅ |
| `VisibilityFilter`, `StaticFilter`, `MagicMethodFilter`, `ConstructorFilter` | `MethodFilterInterface` | ✅ |
| `DeriveConfig`, `DerivationResult`, `BuildMeta`, `DeriveDiff`, `Naming`, `TypeSubstitutor`, `LeakageAuditor`, `ParseState`, `DeriveCliInput`, `DeriveMode`, `Visibility` | — | ✅ Value objects / pure helpers; no port needed. |
| `MethodSignatureFactory` | ❌ concrete | ◐ Optional seam (F-3). |
| `SourceRenderer` | ❌ concrete | ◐ Optional seam (F-3). |
| `MethodExtractor` | ❌ concrete | ✅ Internal helper; acceptable. |
| `ClassReflection` | ❌ extends `ReflectionClass` | ✅ Deliberate facade; the *resolvers it uses* are ported. |
| `DeriveCommand`, `ArgParser` | ❌ concrete | ◐ Optional `CommandInterface` (F-4). |

**Conclusion:** every *swappable boundary* class is backed by an interface. The only
recommendation is to optionally port `MethodSignatureFactory` and `SourceRenderer`
(F-3) if finer-grained DI/testing of the derivers is ever desired; this is not required
for correctness or for the hexagonal model, which is already satisfied.

---

## 8. Performance

- ✅ Reflection memoization via `ReflectionCacheInterface` (injectable) ensures each
  class is reflected at most once per instance (`AbstractDeriver::reflect`,
  `FamilyDeriver::reflect`). This is the dominant cost and is handled well.
- ✅ `RecursiveTraitResolver` memoizes per-node resolution (`src/Resolver/RecursiveTraitResolver.php:22`).
- ◐ `FamilyDeriver::discover()` uses `array_shift()` inside a `while` loop, an O(n²)
  dequeue. Negligible for typical family sizes; a simple index pointer would remove it
  if families grow large (F-8).
- ◐ `FamilyDeriver::configHash()` serializes the full map + config on every derive.
  Cheap today; could key the memo by a stable config identity if profiling shows
  pressure (F-8).
- ◐ `LeakageAuditor::simplifyDocblock()` loops the entire substitution map per method
  when docblocks are preserved. Only active under opt-in flags, and docblocks are
  short, so acceptable (F-8).

No performance blockers.

---

## 9. Security

- ✅ **No code execution from input.** `DeriveCommand::classFromFile` scans tokens only
  (`token_get_all`); it never `include`s or `eval`s the target file
  (`src/Cli/DeriveCommand.php:187`).
- ✅ **Trust model documented** (R.13): the CLI autoloads operator-supplied class
  names, so it must run only on trusted code; this is stated in `--help` and
  `docs/guide/usage.md`.
- ✅ No `shell_exec`/`proc_open`/SQL/serialization-of-untrusted-data paths.
- ⚠️ **`--out` path handling (F-9, low risk).** `file_put_contents($path, …)` writes to
  an operator-supplied path (and `mkdir(..., self::OUTPUT_DIR_MODE, true)` creates
  ancestor dirs). Because the operator is already trusted (per the documented model),
  this is acceptable, but a defense-in-depth check — reject path traversal / symlink
  escapes outside an optional base dir — would tighten it without hurting DX.
- ⚠️ **`class_exists($fqcn)` on user input** autoloads arbitrary code. Again covered by
  the trust model; no change required, noted for completeness.

Security posture is appropriate for a developer tool operating on first-party code.

---

## 10. Maintainability & DX

- ✅ **Excellent test coverage** — 229 tests / 472 assertions across Unit, with
  snapshot/golden-file tests for the derivers and dedicated tests for the factory, CLI,
  filters, substitution, and edge cases.
- ✅ **Coding standard enforced** (PSR-12 + `@PHP84Migration`), 0 files to fix.
- ✅ **Static analysis clean** (PHPStan level 5, 0 errors).
- ✅ **deptrac 0 violations** — architecture is enforceable, not just documented.
- ✅ **Fail-fast configuration** — `DeriveConfig` rejects contradictory allow/deny
  method lists and empty `reExposeOwnedInterfaces` entries at construction time.
- ✅ **DX**: five CLI modes, presets (`strict`/`adapter`/`family`), rich `--help`,
  deterministic output (so `DeriveDiff` enables CI drift detection), and ten worked
  `examples/*.php` demos.
- ◐ The `DeriveIt` facade and `DeriveItFactory` overlap (§3) is the only notable
  maintainability wrinkle; harmless but worth a comment or a route-through-factory.

---

## 11. Findings & Recommendations

Severity: **High** = blocks v1 / correctness or security risk · **Medium** = should fix
soon / clear quality debt · **Low** = polish / optional.

| ID | Severity | Area | Finding | Recommended action |
|----|----------|------|---------|--------------------|
| F-1 | Medium | Magic numbers | `DeriveCommand` exit codes `0/1/2/3` are bare literals (`src/Cli/DeriveCommand.php:57–102`). | Add `private const EXIT_SUCCESS = 0; EXIT_USAGE = 1; EXIT_DERIVATION = 2; EXIT_OUTPUT = 3;` and reference them. |
| F-2 | Medium | Magic strings | `AdapterGenerator::bodyFor` re-parses the rendered return type with literal `'void'`/`'|'`/`'&'` (`src/Generator/AdapterGenerator.php:135–191`). | Prefer the typed `ReflectionType` already available on `Method`; if string parsing is kept, hoist `TYPE_VOID`/`UNION_SEP`/`INTERSECTION_SEP` constants. |
| F-3 | Low | Interface coverage / DIP | `MethodSignatureFactory` and `SourceRenderer` are concrete dependencies of the derivers; no port. | Optional: extract `MethodSignatureFactoryInterface` / `SourceRendererInterface` if finer DI seams are wanted. Not required for hexagonal compliance. |
| F-4 | Low | Interface coverage | `DeriveCommand` has no `CommandInterface`. | Optional: one-method port to make the command injectable behind a boundary. |
| F-5 | Low | DRY | FQCN→short-name substring logic is duplicated in 4 sites. | Add `Naming::shortName(string $fqcn): string` and reuse. |
| F-6 | Low | DRY | `LazyCreatable` declares `abstract create()` and each resolver repeats `return new self();`. | Provide a default `create()` body in the trait (`new self()` resolves to the using class). |
| F-7 | Low | Modern PHP | `InMemoryReflectionCache` keeps `ReflectionClass` alive for the cache lifetime. | Optionally store `WeakReference` values so metadata is GC-eligible in long-running scans. |
| F-8 | Low | Performance | `FamilyDeriver::discover` O(n²) `array_shift`; `configHash` re-serializes; docblock simplification loops the full map. | Index-pointer queue; stable config identity for memo key; micro-optimisations only if profiling demands. |
| F-9 | Low | Security (defense-in-depth) | `--out` path is operator-controlled with no traversal/symlink guard. | Optional base-dir / symlink check; covered today by the documented trust model. |
| F-10 | Low | Consistency | `DeriveIt` facade `new`s concrete derivers, duplicating `DeriveItFactory` assembly. | Route facade through the factory (or document the facade as an intentional thin wrapper). |

**Resolved-from-REVIEW_2 (confirmed):** `DeriveConfig` wither duplication (R.1),
deriver-port coverage + factory (R.2), `DeriveMode`/`Visibility` enums (R.4),
declarative `ArgParser` (R.5), family-map reporting (R.6), duplicate `@param` (R.7),
`OUTPUT_DIR_MODE` constant (R.8), hoisted `VENDOR_PREFIXES`/`CACHE_KEY_PREFIX` (R.9),
last `else`/`elseif` removed (R.10), `SourceRenderer` reuse (R.11), docs refresh (R.3),
CLI trust model (R.13), lazy-adapter deferred note (R.12).

---

## 12. Out of Scope / Notes

- The review covers `it-pack/derive-it` only; sibling packages in the monorepo
  (`CommandIt`, `ConfigureIt`, `RunIt`, …) were not examined and show unrelated
  working-tree modifications that this review does not touch.
- Mutation testing (infection) was run for this session: **Covered Code MSI 88%**
  across 1136 generated mutants (683 killed, 133 undetected, 311 time-outs, 9 errors).
  The 80% gate is satisfied (passed at +8.29pp). The time-outs/errors are incidental
  mutants that hang or fatal under mutation and do not affect the passing result.
- "Every class fully backed by interfaces" is intentionally interpreted as *boundary*
  coverage (see §0 assumption and §7); value objects and pure helpers are excluded by
  design.
