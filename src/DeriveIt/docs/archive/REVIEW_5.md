# DeriveIt — In-Depth Project Review

> Review date: 2026-08-25
> Scope: `/home/quill/tinker/php/project/it-pack/src/DeriveIt` **and** the sibling
> integration package `/home/quill/tinker/php/project/it-pack/src/DeriveItRunit`
> (the `it-pack/derive-it` + `it-pack/derive-it-runit` packages).
> Method: full source reading of `src/` (both packages), a token-based structural
> audit script (declaration order, one-type-per-file, abstract classes, Yoda,
> unimported symbols, line length, else/elseif), targeted greps (vocabulary
> literals, instantiation sites), pcov line-coverage run parsed from
> `--coverage-xml`, a fresh Infection run, and re-execution of every quality
> gate. Supersedes `docs/archive/REVIEW_4.md` (2026-08-24), whose findings were
> closed by `docs/archive/REFACTORING_PLAN_3.md` milestones A–E and
> consolidation phases C1–C4. Finding IDs are scoped to **this** document.

**Assumptions (stated per the review brief; several directives need explicit
interpretation — recorded here so the findings are reproducible):**

- *"No abstract classes"* applies to production code. The single hit
  (`AbstractDeriver`) is a genuine finding (F-4), not an exemption.
- *Constructor promotion* places properties syntactically inside constructors;
  this is treated as satisfying the "properties before constructors" order (the
  two are inseparable in PHP), not as a violation. Verified per file.
- *"One type per file"* applies to hand-written code. Committed golden outputs
  (`tests/_expected/*.adapter.php` intentionally declare interface + adapter)
  are the product's output format and are exempt by design. Inline test
  fixture classes are **not** exempt under the directive as stated (F-5).
- *"Instances only in factories/builders"* is applied to service-like
  collaborators; pure value objects, Nette AST nodes, and thrown exceptions
  are construction-by-nature and exempt (documented per §7).
- *"Coverage ≥ 95%"* is measured as pcov line coverage over `src/` statements
  + methods, parsed from the generated XML.
- Test-side declaration order follows framework lifecycle conventions
  (`setUp()` / `_before()` precede tests); this is noted where it conflicts
  with the literal public-first rule rather than counted file-by-file.

---

## 0. Review Checklist (conceptual)

What this review covers, at a conceptual level:

- **Feature surface & correctness** — five derivation modes, telemetry pipeline, drift detection, RunIt bridge.
- **Architecture & hexagonal fit** — ports/adapters/factories, layering, dependency direction, and the no-abstract-class directive against the current base class.
- **Style-directive audit** — else/elseif, Yoda conditions, magic literals, vocabularies-in-interfaces, imports at top, declaration order, one-type-per-file, 120-column limit.
- **Instantiation discipline** — factories/builders as sole creation points, factories passed via DI.
- **Interface backing & modern PHP** — port coverage per class; readonly classes, enums, traits, weak references, property hooks, lazy objects where they pay.
- **Quality evidence** — coverage ≥ 95% with per-file gaps, cross-test coverage leakage, mutation-testing leverage in both packages.
- **Performance, security, DX, maintenance** — caching hot paths, trust model, operator ergonomics, documentation currency.

---

## 1. Executive Summary

The project remains in strong shape after the refactoring-plan and consolidation
waves: five derivation modes plus telemetry and a RunIt execution bridge, clean
layering enforced by deptrac, all gates green in **both** packages, and a
discipline level that survives a token-level audit remarkably well — zero
executable `else`/`elseif`, zero Yoda conditions, zero fully-qualified
unimported symbols, and production files strictly one-type-per-file.

The new directives from this review's brief surface four real work items:

1. **Line length**: 58 executable lines exceed 120 columns (35 production,
   23 test) plus 3 long docblock lines — the largest single directive debt (F-1).
2. **Coverage sits at exactly 95.00%** with no headroom; `DeriveCommand` is at
   74.3% with its tokenizer-based file-target path (`classFromFile`)
   completely untested (F-2).
3. **Telemetry vocabulary literals are scattered**: activity/measurement/unit
   strings appear inline at ~36 emission sites across four files instead of
   living as constants on interfaces (F-3).
4. **`AbstractDeriver` is the one abstract class** and must go under the
   no-abstract directive; a trait or final-collaborator replacement is
   straightforward (F-4).

Additionally, mutation testing is leveraged heavily in DeriveIt but **not
configured at all in the bridge package** (F-12).

**Gate results (re-run for this review, 2026-08-25):**

| Check | DeriveIt | DeriveItRunit |
|-------|----------|---------------|
| Tests | ✅ 269 tests / 572 assertions | ✅ 4 tests / 21 assertions |
| Coding standard | ✅ 0 of 90 files | ✅ 0 of 4 files |
| PHPStan | ✅ 0 errors (level 5 / level 8) | ✅ 0 errors |
| deptrac | ✅ 0 violations | n/a (single layer pair) |
| Infection | ✅ MSI ≈ 97.3% (min 80) | ❌ not configured (F-12) |
| Line coverage (pcov) | ⚠️ **95.00%** exactly | not measured (F-12) |
| Golden drift | ✅ none | n/a |

---

## 2. Feature Completeness & Correctness

- **Flat / per-trait / adapter / family / family-adapter** derivation — all
  verified working through facade, factory, and CLI paths; leakage invariant
  enforced shallowly (`assertSelfContained`, `assertPerTraitSelfContained`)
  and deeply (opt-in `LeakageAuditor`). No regressions found.
- **Telemetry pipeline (C1–C3)** — emission points behave as documented:
  lifecycle occurrences bracket derivations, activities wrap stages,
  measurements carry counts/bytes, the re-expose misconfiguration surfaces as
  a warning observation, `diff()` emits drift occurrences. Null-sink default
  keeps output byte-identical (golden suite green throughout).
- **CLI `--verbose`** — streams facts to stderr while stdout stays
  byte-identical (explicitly asserted by tests).
- **RunIt bridge (C4)** — end-to-end functional proof stands: a real
  derivation inside a real `SynchronousExecutionEngine` execution records
  occurrences, measurements, and observations onto the execution's publisher;
  drift detection emits `deriveit.drift.detected.v1`.

No correctness issues were found.

## 3. Architecture & Hexagonal Fit

Layering is unchanged and machine-enforced (deptrac 0 violations): Contract →
Behavior/Telemetry → Resolver/Reflection → Generator → Entry/Cli. Ports and
adapters remain complete at every swappable boundary (resolvers ×3, reflection
caches ×3, derivers ×2+family, adapter generator, filters ×4, command,
event sinks ×2 + console). `DeriveItFactory` is the composition root; the
facade routes through it; the RunIt bridge is the single meeting point between
the two packages (verified: `grep -r RunIt src/` in DeriveIt → no hits).

### The abstract-class directive vs `AbstractDeriver`

`abstract readonly class AbstractDeriver` (src/Generator/AbstractDeriver.php:29)
is the **only** abstract class in either package. It carries four shared
concerns for the two concrete derivers: reflection memoization, substitutor
seeding, skip filtering, and the signature pipeline. Under the no-abstract
directive this must be dissolved. Two faithful replacements:

- **Trait (least churn):** convert to `final class` + a `DerivesSurfaces`
  trait providing the same protected methods. Traits cannot declare promoted
  state cleanly here (readonly-class rules), so state would move into each
  deriver's constructor — mechanical but slightly duplicative.
- **Composition (recommended):** extract a `final readonly class
  DerivationCore` (reflect + seed + skip-check + `signatureFor`) injected into
  both derivers through their constructors. This removes inheritance entirely,
  makes the shared behavior independently testable/mutable-testable, and
  matches the directive's stated preference. Estimated effort: half a day
  including test moves.

Either way, both derivers stay `final readonly` and implement their existing
ports; no public API changes.

### Instantiation discipline (factories/builders)

Service-like collaborators are assembled almost exclusively at the composition
root; the residual inline constructions are graded in §7/F-7. Value objects,
Nette AST nodes, and exceptions are construction-by-nature and exempt.

**Assessment:** hexagonal architecture remains satisfied; the abstract-base
removal (F-4) closes the last structural deviation.

## 4. SOLID & DRY

| Principle | Status | Evidence |
|---|---|---|
| SRP | ✅ Strong | Pipeline stages remain single-purpose; telemetry isolated behind emitter; CLI split parse/validate/run/emit. |
| OCP | ✅ Strong | New mode/preset/filter/sink = add case/row/implementation; proven twice recently (Preset enum, event sink). |
| LSP | ✅ Strong | All port implementations honor contracts; bridge sink substitutes any `DeriveEventSinkInterface`. |
| ISP | ✅ Adequate | One-method ports dominate (`MethodFilterInterface`, `CommandInterface`); sink is five cohesive methods. |
| DIP | ✅ At boundaries | Derivers depend on cache/sink/filter/command ports. Internal helpers (`TypeSubstitutor`, `SourceRenderer`, `Naming`, `LeakageAuditor`, `DeriveEventEmitter`) remain concrete — consistent with the standing assumption, revisited in §8. |

**DRY:** prior consolidations hold (single `cloneWithName`, single naming home
in `Naming`, arity-map co-located with parser table, `assemble()` removing the
per-trait double walk). Remaining duplication is the vocabulary literals
(F-3) — fixing F-3 also de-duplicates those.

## 5. Modern PHP Leverage

| Feature | Status | Notes |
|---|---|---|
| Backed enums | ✅ Grown well | Now four: `DeriveMode`, `Visibility`, `Cli\Preset`, `Telemetry\DeriveObservationSeverity`. Adding vocabulary = one case. |
| `readonly` classes | ✅ Extensive | All filters, value objects, derivers, emitter, console sink, presets carrier — including `final readonly` on the new C1–C4 classes. |
| Traits | ✅ Appropriate | `LazyCreatable` default-bodied factory trait. (If F-4 takes the trait route, this stays the only trait — fine.) |
| Weak references | ✅ Provided | `WeakReflectionCache` for long-running scans. |
| Property hooks / virtual properties | ➖ N/A | `copyWith()` reconstruction remains cleaner than hook emulation. |
| Lazy objects | ➖ Deferred | Correctly a generated-adapter concern, tracked post-v1. |
| Fibers | ➖ N/A | Synchronous pipeline; RunIt owns concurrency adapters. |

Nothing obvious is missing; no gratuitous modernism detected.

## 6. Style-Directive Audit (token-based, evidence-backed)

| Directive | Result | Detail |
|---|---|---|
| No `else`/`elseif` | ✅ **0 hits** | Both packages, executable lines only. Dispatch via `match` + guards. |
| No Yoda conditions | ✅ **0 hits** | Token scan for literal-on-LHS comparisons (`null === x`, `'lit' == y`, …). |
| Imports at top of file | ✅ **0 hits** | No fully-qualified `\ItPack\…`/`\Nette\…` usages outside the use-block (heuristic scan; global functions like `\sprintf` legitimately unqualified). |
| One type per file | ✅ Production / ✅ Tests (goldens exempt) | All 44 production files are 1:1 (an initial scanner hit in `InterfaceDeriver.php` was a named-argument artifact, manually disproved). The 5 test files that embedded inline fixtures were extracted to `tests/Support/Fixture/` (+ bridge `tests/Fixture/`) by R4-7; golden `Tree.adapter.php` remains exempt by design. |
| Lines ≤ 120 chars | ❌ **58 code + 3 docblock lines** | 35 production (worst: `DeriveItFactory.php` docblock params 154–162, `DeriveIt.php` @param lines up to 174, `ArgParser.php:103` at 149) + 23 test lines. Mechanical fix — see F-1. |
| Declaration order | ✅ Compliant modulo documented exemptions | Promoted-constructor properties verified per file and treated as satisfied (inseparable). The two genuine violations from the audit — `ArgParser::arities()` (private static before public `parse()`) and `DeriveIt::factory()` (private static above the public static API) — were resolved by R4-8 (both relocated beneath their public API). Test lifecycle methods (`setUp`/`_before`) precede tests by framework convention — documented exemption in AGENTS.md §4. |
| Constants/enums over magic literals | ⚠️ Strong overall, **one systemic gap** | Exit codes, dir mode, type separators, suffixes, prefixes, vendor lists, option arities: all named. Gap: telemetry **vocabularies** (activity names, occurrence types, measurement names, units) appear as bare strings at ~36 emission sites across `InterfaceDeriver` (12), `PerTraitInterfaceDeriver` (18), `FamilyDeriver` (3), `DeriveIt` (3), plus unit strings duplicated in the bridge's mapping. Per the brief these belong on interfaces — see F-3. |

## 7. Instantiation Discipline (detail)

Compliant anchors: derivers/adapters are assembled **only** by
`DeriveItFactory`; resolvers instantiate via the `LazyCreatable` factory trait;
presets are created by `Preset::toConfig()` (a factory method on the type);
the bridge sink/wrapper are built by hosts or `DeriveItExecutions`.

Residual service-like constructions outside factories (all Low):

| Site | Constructs | Note |
|---|---|---|
| `MethodExtractor::resolveFilters()` | default filter chain ×4 | ctor already accepts an explicit chain (DI seam exists); default could come from an injected chain factory. |
| `AbstractDeriver::__construct` | `MethodExtractor`, `SourceRenderer`, `DeriveEventEmitter` (+ `MethodSignatureFactory` when not injected) | dissolves naturally with F-4's composition refactor — build them in `DerivationCore` or inject. |
| `FamilyDeriver::deriveMember()` | member `InterfaceDeriver` + `MethodSignatureFactory` per member | FamilyDeriver acts as composite builder; defensible, but routing through the factory would centralize assembly. |
| `DeriveIt` facade | `SourceRenderer` ×2, `DeriveEventEmitter` (diff), Nette file/namespace nodes (family-adapter grouping) | renderer/emitter could be exposed via factory accessors. |
| `DeriveCommand::execute()` | `ArgParser`, `DeriveConsoleSink` | delivery-layer self-assembly; injecting a sink factory would make the CLI's output path swappable. |
| `ClassReflection` defaults | shared immutable resolvers | already factory-mediated (`Recursive*::create()`). |

Value objects (`DeriveConfig`, results, diffs), Nette AST nodes, exceptions:
exempt by nature (assumption §0).

## 8. Interface Backing of Every Class

Boundary coverage remains complete; new C1–C4 types follow the rule:

| Class | Backing | Verdict |
|---|---|---|
| `InterfaceDeriver`, `PerTraitInterfaceDeriver` | `DeriverInterface` | ✅ (base class removed per F-4 → fully standalone) |
| `FamilyDeriver` / `AdapterGenerator` | family/adapter ports | ✅ |
| Resolvers ×3, caches ×3, filters ×4, `DeriveCommand` | their ports | ✅ |
| `NullDeriveEventSink`, `DeriveConsoleSink` | `DeriveEventSinkInterface` | ✅ |
| `RunItDerivationEventSink` (bridge) | `DeriveEventSinkInterface` | ✅ |
| `DeriveEventEmitter` | — concrete internal pass-through | ◐ acceptable (pure helper; a port would mirror the sink verbatim) |
| `Preset`, `DeriveMode`, `Visibility`, severity enum | enums | ✅ n/a |
| `ParseState` (mutable accumulator), `DeriveCliInput`, `DeriveConfig`, results/diffs/meta, `Naming`, `TypeSubstitutor`, `SourceRenderer`, `MethodSignatureFactory`, `MethodExtractor`, `LeakageAuditor`, `ClassReflection` | internal/value/helper | ✅ per standing assumption |
| `AbstractDeriver` | implements `DeriverInterface` | ❌ abstract — resolved by F-4 |

## 9. Complexity

Objective class-aggregate cyclomatic numbers (pcov metrics): `DeriveCommand`
95, `FamilyDeriver` 45, `MethodSignatureFactory` 41, `ArgParser` 39,
`DeriveConfig` 36, `InterfaceDeriver`/`TypeSubstitutor` 32, `AdapterGenerator`
30. Method-level sizes stay small and guard-style; no `crap` hotspot flagged
by the tooling beyond these aggregates. Split candidates if desired:
`DeriveCommand::classFromFile` → a small `FileTargetResolver` (also eases
testing, F-2), and `AdapterGenerator::bodyFor` remains the branchiest method
but reads sequentially. Verdict: low complexity maintained; aggregates reflect
responsibility breadth, not tangled logic.

## 10. Performance

Reflection memoization (injectable caches, per-node resolver memo, per-config
family memo, index-pointer BFS) unchanged and effective. Telemetry adds one
null-coalescing check per emission site with the default null sink — measured
suite time is unchanged (6.4 s for 269 tests with pcov attached). The bridge
adds cost only when wired. No regressions; no blockers.

## 11. Security

Unchanged posture: tokenizer-only file inspection (never executes targets),
documented trusted-operator model for autoloaded names, lexical `--base-dir`
sandbox with the symlink caveat recorded in help/docs/code, `0o755` directory
mode, no shell/SQL/deserialization surfaces. The bridge adds no new input
paths. ✅

## 12. Maintainability & DX

- Docs are current post-consolidation: guide layers/data-flow include
  telemetry; `docs/integrations/runit.md` carries the CI recipe; AGENTS.md
  layout and routing cover the new layers; stale links fixed.
- Five-gate CI on 8.4/8.5 + coverage artifact; bridge has its own
  test/analyse/cs scripts.
- DX: `--verbose` diagnostics, presets, deterministic output for drift gating.
- Gap: bridge package has no CI workflow file of its own (relies on manual runs)
  — folded into F-12.

## 13. Quality Evidence: Coverage, Leakage, Mutation

**Line coverage (pcov, parsed):** **1443/1519 = 95.00%** — meets ≥95% with
zero headroom. Files below 95%:

| File | Coverage | Gap driver |
|---|---|---|
| `src/Cli/DeriveCommand.php` | 74.29% (130/175) | `classFromFile()` has **zero coverage** (file-target CLI path never exercised); error branches partially hit |
| `src/Generator/ConstructorFilter.php` | 88.89% | `dropDestruct=false` path |
| `src/Generator/LeakageAuditor.php` | 91.07% | deep-audit edge arms |
| `src/Generator/Naming.php` | 93.10% | `Derived` collision-suffix branch |

→ F-2: add a CLI file-target test (kills two birds: covers `classFromFile`
and the `--out` file flow through it), plus three micro-tests. That alone
lifts total coverage to ≈96%.

**Cross-test leakage:** the generated coverage XML contains no per-test
attribution (Codeception does not emit test-scoped coverage here), so leakage
was assessed structurally: (a) suites map to subjects by namespace and name
(verified matrix of test-class → subject), (b) no shared mutable state exists
that could smear coverage (instance-scoped caches only), (c) mutation scoring
is kill-attributed and shows each src file being killed primarily by its
matching suite. Recommendation: enable php-code-coverage test attribution
(`--coverage-phpunit`/test IDs) so future reviews can prove leakage absence
mechanically (folded into F-2 acceptance).

**Mutation testing:** leveraged seriously in DeriveIt — fresh run: **1346
mutants, MSI ≈ 97.3%** (413 killed by tests, 892 timeout-detected, 5 errored,
36 escaped, **0 not covered**), gate min-msi=80 passed at +17.3pp. The 36
escapes are the known profile: string-literal mutants in pre-existing
string-heavy CLI/formatting code plus timing-noise flips; zero escapes and
zero uncovered lines in the C1–C4 telemetry code (verified against the log).
**Gap:** `DeriveItRunit` has no infection configuration at all (F-12).

## 14. Findings & Recommendations

Severity: **High** = blocks release · **Medium** = fix soon · **Low** = debt ·
**Info** = note. IDs scoped to this document.

| ID | Severity | Area | Finding | Recommended action |
|----|----------|------|---------|--------------------|
| F-1 | Medium | 120-col directive | 58 executable + 3 docblock lines exceed 120 cols (list captured in audit; worst offenders: `DeriveIt.php` docblocks 168–174, `DeriveItFactory.php` 145–162, `ArgParser.php:103` 149, `DeriveCommand.php:151` 156). | Mechanical wrap; add `PHP_CodeSniffer`-style line-limit sniffer (or php-cs-fixer `LineLength` fixer) to the ruleset so the limit is enforced, not audited. |
| F-2 | Medium | Coverage floor | Total coverage is exactly 95.00% with no headroom; `DeriveCommand` 74.29%, `ConstructorFilter` 88.89%, `LeakageAuditor` 91.07%, `Naming` 93.10%; `classFromFile()` entirely untested. No numeric coverage gate in CI. | Add: CLI file-target test (`derive some/File.php --out=…`), `dropDestruct=false` case, leakage-audit edge arms, `Derived`-suffix naming case. Add `--fail-under=95` (or 96) coverage threshold to CI. Require per-test attribution for future leakage proofs. |
| F-3 | Medium | Vocabularies in interfaces | Telemetry vocabulary is inline strings: activity names, occurrence types, measurement names at ~36 sites (`InterfaceDeriver` 12, `PerTraitInterfaceDeriver` 18, `FamilyDeriver` 3, `DeriveIt` 3) and unit strings (`count`, `By`, `ms`, `%`) duplicated in derivers + bridge mapping. | Declare constant-only interfaces in `Contract/Telemetry/` (e.g. `DeriveEvents`, `DeriveMeasurements`, `DeriveUnits`) holding the canonical strings; reference them at every site and in tests; bridge maps from the same source. Prevents typo-drift across the decoupling seam. |
| F-4 | Medium | No abstract classes | `AbstractDeriver` is the sole abstract class. | Replace with composition: `final readonly class DerivationCore` (reflect/seed/skip/signatureFor) injected into both derivers (preferred), or a concrete-method trait. Keep both derivers `final readonly` behind `DeriverInterface`. |
| F-5 | Low | One type per file (tests) | 7 test files embed extra types (the original audit counted 5; `NamingTest` and `LeakageAuditorTest` also held inline fixtures, the latter across two namespaces): `ArgParserSample`; `CliSampleTarget/Trait/TraitUser/A/B`; `RecordingDeriveEventSink`; `CountingReflectionCache`/`InMemoryPool`/`CacheItem`; `NamingEndsWithInterface`/`NamingCollideInterface`/`NamingCollide`; `LeakForeignReturn`/`LeakBuiltinReturn`/`LeakOwnedReturn` (+ a `Foreign\Ns\Thing` foreign fixture); `ReportWidget` (bridge). | Extract to PSR-4 fixture/support files (`tests/Support/Fixture/…`, autoload-dev already mapped; the foreign fixture uses a new `Foreign\` dev PSR-4 root). Goldens exempt by design. **Resolved by R4-7 (Milestone C, 2026-08-28): every fixture moved out; hand-written tree is `MULTI = 0` (goldens excluded), one-type-per-file restored.** |
| F-6 | Low | Declaration order | Genuine violations: `ArgParser::arities()` private-static before public `parse()`; `DeriveIt::factory()` private-static above the public static API. Test lifecycle methods (`setUp`/`_before`) conflict with public-first by framework convention; constructor promotion interleaves properties with constructors by language design. | Move `arities()` below the public methods; relocate `factory()` beneath the public block (or document an explicit exemption in AGENTS.md alongside the promotion/lifecycle exemptions). **Resolved by R4-8 (Milestone C, 2026-08-28): `arities()` moved below `parse()`; `factory()` relocated beneath the public static block; AGENTS.md §4 documents the public-first rule plus the promotion/lifecycle exemptions.** |
| F-7 | Low | Instantiation discipline | Service-ish collaborators constructed outside factories: filter chain inside `MethodExtractor`; extractor/renderer/emitter(+signature factory) inside `AbstractDeriver`; per-member deriver inside `FamilyDeriver`; renderer/emitter in facade; parser/console-sink in `DeriveCommand`. | Fold most into F-4's `DerivationCore`; expose `DeriveItFactory::sourceRenderer()/eventEmitter()` for the facade; inject a sink factory (or sink) into `DeriveCommand`. Value objects/AST/exceptions stay exempt per assumption. |
| F-8 | Low | Complexity outliers | Class-aggregate CC: `DeriveCommand` 95, `FamilyDeriver` 45, `MethodSignatureFactory` 41. Method sizes remain small; no crap-hotspots. | Optional splits when next touched: `FileTargetResolver` (synergy with F-2), family member-assembly helper. Not required now. **Resolved by R4-9 (Milestone C, 2026-08-28): the tokenizer scan was extracted into `src/Cli/FileTargetResolver.php` and injected into `DeriveCommand` as an optional DI seam; `DeriveCommand` aggregate CC reduced.** |
| F-9 | Info | Mutation noise | 36 escaped mutants are string-literal/timing-noise flips in pre-existing formatting code; 0 escapes and 0 not-covered in telemetry code. | Acceptable; revisit only if escapes grow. |
| F-10 | Info | Leakage evidence | Per-test coverage attribution unavailable under current Codeception config; leakage argued structurally (naming-aligned suites, no shared state, kill attribution). | Enable test-scoped coverage in CI artifacts for mechanical proofs next review. |
| F-11 | Info | Interface backing | Boundary classes fully ported; internal concretes (`DeriveEventEmitter`, `ParseState`, helpers) acceptable per standing assumption. `DeriveEventEmitter` is the only new unported service-shaped helper. | Leave unless finer DI seams are wanted. |
| F-12 | Medium | Mutation leverage (bridge) | `DeriveItRunit` ships without infection (or coverage) configuration; its 4 tests are behavioral but unmutated. | Add `infection.json5` + composer script + CI step; assert MSI ≥ 80 and record baseline. Also add a workflow file for the package. |

**Priority order:** F-3 + F-4 (architecture/directive substance) → F-1 + F-2
(enforcement + headroom) → F-12 → F-5…F-8 polish → info items as tracked.

## 15. Out of Scope / Notes

- Sibling monorepo packages other than `DeriveItRunit` were not examined.
- Infection timeout-share caveat from REVIEW_4 §12 still applies to
  interpreting killed-vs-timeout ratios.
- The token-audit script used for the structural directives is throwaway
  tooling (`/tmp`), not committed; its raw outputs are quoted in §6 and can be
  regenerated per the described patterns if needed.
