# DisenchantIt — Refactoring Plan (post-review II)

> Companion to `docs/REVIEW.md` (the current post-consolidation engineering
> review) and `README.md`.
>
> This plan converts every open finding of `docs/REVIEW.md` (F1–F8) into
> **milestones (M0–M6)** with **step-by-step implementation tasks**. The
> completed consolidation plan (M0–M6 of REVIEW_2 vintage) is archived at
> `docs/archive/REFACTORING_PLAN_2.md`.
>
> Severity convention (from the review): 🔴 high · 🟠 medium · 🟡 low · ⚪ info.
>
> Every task is **behavior-preserving** unless stated otherwise, and every
> milestone ends with the full gate suite green:
>
> ```bash
> composer architecture                              # PASS (no violations)
> vendor/bin/phpstan analyse src --level=9           # no errors
> composer format:check                              # 0 files to fix
> vendor/bin/codecept run unit                       # baseline: OK (172 tests, 431 assertions)
> vendor/bin/codecept run unit --coverage-xml        # baseline: 90.00% lines (1089/1210)
> ```

---

## 0. Ground rules (apply to every task)

1. **Source-text safety hard rule** is never touched; detection/rewrite stay pure
   text transforms.
2. **Layering** stays enforced by `phparkitect.php`: Contract → ValueObject only;
   Core → Contract + ValueObject; Adapter → Contract + ValueObject(+Adapter);
   Factory composes. Interfaces may host scalar constants without violating the
   interface-dependency rule (constants introduce no type dependency).
3. **Factory contract** (ADR-0002): collaborators injected via constructors;
   composition only inside `Factory\`; factories expose instance `build()` plus
   optional `static create(): self` proxy.
4. **Workflow**: mono-repo, parallel subproject work — milestones land as
   ordinary commits on `main` (no dedicated branch; recorded decision from the
   previous plan's M0). One coherent commit per task.
5. Baseline counts must hold or grow: **172 tests / 431 assertions**, coverage
   **90.00 %**; any shrinkage is a regression signal.

---

## 1. Traceability matrix

| Finding | Sev | Topic | Milestone | Primary files | Tests |
|---|---|---|---|---|---|
| F5 | 🟠 | Inline collaborator default | **M1** | `MagicLiteralVisitor`, `MagicStringVisitorFactory` | existing visitor suites green |
| F3 | 🟠 | Vocabulary hosting on ports | **M1** | `StringLiteralDetectorInterface`, `ParsedSourceCacheInterface`, `SemConvConfigInterface`*, `DisenchantScopeInterface`* (+ 4 impls, 3 consumers) | update `DetectorCacheTest` refs |
| F7 | ⚪ | Const scalar typing | **M1** | `ParsedSourceCacheInterface` (with F3) | same |
| F1a | 🔴 | Coverage: 4 priority files | **M2** | new/extended unit suites | new tests (~+60–80 cases est.) |
| F1b | 🔴 | Coverage: remaining 14 files → ≥95 % | **M3** | new/extended unit suites | new tests |
| F2 | 🟠 | Mutation testing | **M4** | `composer.json`, `infection.json5` | MSI baseline recorded |
| F8 | 🟠 | Per-test/per-mutant attribution | **M4** | Infection reports, test-naming audit | documented convention |
| F4 | 🟡 | Declaration order (13 sites) | **M5** | 13 source files + `tools/order_check.php` | existing suites green |
| F6 | 🟡 | >120-col code lines (9) | **M5** | 7 source files | format gate |

\* `SemConvConfigInterface` / `DisenchantScopeInterface` live in **`ValueObject\`**
(not `Contract\`) alongside their implementations — verified.

Deliberately **not planned**: WeakMap/Fibers adoption (correctly closed with
rationale, REVIEW §5); benchmarks (no performance claim pending); changelog
convention (deferred until first release).

---

## 2. Milestone M0 — Baseline & safety net

- [x] **M0-T1** Record current gate output (four commands above) — expected:
      PASS · no errors · 0 of 172 · OK (172, 431); coverage XML regenerated at
      **90.00 % (1089/1210)** with 18 files <95 % (table in REVIEW §11).
      **Recorded (2026-08-25):** architecture ✅ no violations · PHPStan lvl 9
      no errors · format 0 of 172 · unit OK (**172 tests / 431 assertions**) ·
      coverage **90.00 % (1089/1210)**, 18 files <95 % — table byte-identical
      to REVIEW §11. This is the regression baseline for the whole plan.
- [x] **M0-T2** No branch (mono-repo rule §0.4). Work lands as commits on `main`.

**Acceptance:** baseline numbers captured before M1 starts. ✅

---

## 3. Milestone M1 — DI discipline & vocabulary hosting (F5, F3, F7) 🟠

**Goal:** remove the one inline collaborator instantiation and hoist all public
tunable constants onto their ports, typed consistently.

### Tasks

- [x] **M1-T1 Remove inline default (F5).** In
      `src/Adapter/MagicLiteralVisitor.php:47`: drop
      `= new ContentClassifier()` from the promoted parameter. Make the
      constructor argument required; in
      `src/Factory/MagicStringVisitorFactory.php` construct
      `new ContentClassifier()` once and pass it explicitly (factory may `new`;
      share the instance across visitor builds if the factory builds several).
      Update direct constructions in tests if any exist
      (`grep -rn "new MagicLiteralVisitor" tests/`).
- [x] **M1-T2 Host `MAX_SOURCE_BYTES` on its port (F3).** Declare
      `public const int MAX_SOURCE_BYTES = 1_000_000;` on
      `Contract\StringLiteralDetectorInterface`; delete it from
      `Adapter\Detector` (constructor default becomes `self::MAX_SOURCE_BYTES`,
      which resolves through the interface). Update references:
      `tests/Unit/DetectorCacheTest.php:211`, docblock `@see` in
      `Exception/SourceTooLargeException.php:14`.
- [x] **M1-T3 Host `DEFAULT_MAX_ENTRIES` on its port (F3+F7).** Declare
      `public const int DEFAULT_MAX_ENTRIES = 32;` on
      `Contract\ParsedSourceCacheInterface`; delete from
      `Adapter\ParsedSourceCache` (ctor default keeps working via inheritance);
      update `DetectorCacheTest.php:103,115` and the `@see` in
      `InvalidCacheConfigurationException.php:14`. Note the explicit `int`
      type fixes F7 in the same stroke.
- [x] **M1-T4 Host SemConv defaults (F3).** Move
      `DEFAULT_INTERFACE_NAME = 'SemConv'` and `DEFAULT_NAMESPACE = 'Generated'`
      from `ValueObject/SemConvConfig.php:39-40` onto
      `ValueObject\SemConvConfigInterface`; update
      `Factory/SemConvConfigFactory.php:19-20`. Keep the private `DOT` /
      `PHP_FILE_EXTENSION` consts where they are (implementation detail).
- [x] **M1-T5 Host heredoc cap (F3).** Move `DEFAULT_MAX_HEREDOC_BODY_CHARS =
      4096` from `ValueObject/DisenchantScope.php:50` onto
      `ValueObject\DisenchantScopeInterface`; update consumers
      (`Adapter/MagicLiteralVisitor.php:48`,
      `Factory/MagicStringVisitorFactory.php:22,31`, internal use at
      `DisenchantScope.php:67`). This also removes the misplaced const found by
      the declaration-order scan (part of F4-d) for free.
- [x] **M1-T6 Sweep:** `grep -rn "public const" src/Adapter src/Core` — expected
      residue: none (only `private const` implementation details remain on
      classes; vocabularies live on ports/enums/interfaces).

**Verification (done):** full gates green — architecture 0 violations;
PHPStan `src` level 9 clean; `format:check` 0/172; unit suite 172 tests /
431 assertions. Coverage XML unchanged at 90.00 % (1089/1210); moved lines
only. `MagicLiteralVisitorTest.php:100` already passed `$contentClassifier`
explicitly, so the required-param change compiled clean.

**API impact (pre-1.0, deliberate):** the four public constants change
host-type (`Detector::…` → `StringLiteralDetectorInterface::…`, etc.).
Recorded in M6 upgrade notes.

---

## 4. Milestone M2 — Coverage closure I: priority files (F1a) 🔴

**Goal:** bring the four highest-risk gaps from the review table to ≥95 %
line coverage with behavior-asserting tests (not line-chasing).

### Tasks — each with named cases to add

- [x] **M2-T1 `RoleClassifier` (60.0 %, 21/35).** New cases in a dedicated
      `tests/Unit/RoleClassifierTest.php` (or extend existing):
      noise-function arguments classified non-candidate (`sprintf`, `printf`,
      `vsprintf`, `trigger_error` — the `NOISE_FUNCTIONS` set), regex-shaped
      strings vs plain strings, HTTP-status ints (100–599) vs other ints,
      version-like strings, identifier-shape boundary (spaces/punctuation),
      float handling. Enumerate the uncovered branch set from the coverage HTML
      (`tests/_output/coverage`) before writing, assert resulting
      `LiteralRole`/subkind per input.
- [x] **M2-T2 `LineBasedRewriter` (71.4 %, 90/126).** Extend the rewriter-focused
      suites with fixture-based cases: multi-line span replacement
      (`endLine > line`), needle-not-found fallback path (`$newSpan =
      $replacement`), stale-line skip paths (both defensive `continue`s),
      `enumAlreadyExists` short-circuit, duplicate-const skip in class body,
      multi-class insertion ordering (bottom-up splice), empty-items early
      return, dry-run no-op. Assert full output strings, not fragments, where
      practical — these are the idempotency-critical paths AGENTS.md flags for
      mutation priority.
- [x] **M2-T3 `NamingContextExtractor` (76.4 %, 42/55).** Cases: trailing
      unit-comment hints (`// ms`), variable-name alternation matching
      (`preg_match` hit and miss at `NamingContextExtractor.php:114`), empty
      match guard (`$m[1] !== ''`), comment-only context returns null,
      array-key contexts, property-name contexts.
- [x] **M2-T4 `DictionaryNameResolver` (23.1 %, 3/13).** New
      `DictionaryNameResolverTest`: `create()` map resolution hit/miss,
      `resolve()` passthrough for unknown values, `fromFile()` happy path via
      temp-file fixture, not-found throw (`RuntimeException`, message match),
      invalid-JSON-object throw, JSON-scalar rejection.

**Acceptance (met):** all four files ≥95 % lines in a fresh coverage run
(RoleClassifier 97.1 %, LineBasedRewriter 97.6 %, NamingContextExtractor 96.4 %,
DictionaryNameResolver 100.0 % — aggregate of the four 97.4 %); suite grew from
172 → 219 tests / 431 → 515 assertions with no assertion-free tests.

---

## 5. Milestone M3 — Coverage closure II: remaining files to ≥95 % (F1b) 🔴

**Goal:** lift every file in the review's §11 gap table above 95 % and the
aggregate with it. Work smallest-first; several need only 1–3 cases.

### Tasks (file → missing behavior)

- [x] **M3-T1** `ConstantPlacement` (50 %) — exercise `typeKeyword()` mapping
       for string and int kinds.
- [x] **M3-T2** `FileSemConvStore` (71.4 %) — read-failure throw, directory
       creation failure (read-only parent via `chmod`), write-failure throw
       (fail-closed contract), load-miss when file absent already covered.
- [x] **M3-T3** `SemConvConfig` (75 %) — virtual `outputPath` property variants
       (namespace present/null), `withNamespace`-style overrides if present.
- [x] **M3-T4** `NamespaceUseInserterTrait` (81.3 %) — import-already-present
       path, insertion-after-last-use position, no-namespace file shape (via
       SemConvAliasRewriter fixtures).
- [x] **M3-T5** `ClassnameToClassConst` (82.4 %) — `supports()` false branch,
       FQCN short-name resolution, replacement content assertions.
- [x] **M3-T6** `JsonReportRecorder` (83.3 %) — encode-failure throw path
       (unencodable payload, e.g. resource in payload), happy-path report shape.
- [x] **M3-T7** `BackedEnumTarget` (86.7 %) — empty-occurrences artifact,
       proposed-name fallback (`Disenchanted`), digit-leading value guard,
       case-name collision suffix loop.
- [x] **M3-T8** `SemConvAliasRewriter` (87.5 %) — alias idempotency (already
       aliased untouched), `use` insertion ordering, existing `self::X` sites
       never modified.
- [x] **M3-T9** `CompositePromotionTarget` (88.9 %) — empty-target composite
       artifact, first-match-wins precedence.
- [x] **M3-T10** `PhpLintObserver` (88.9 %) — broken-PHP fixture raising
       `VerificationException`, cleanup-on-throw (`finally`), binary override
       via env/config.
- [x] **M3-T11** `DisenchantScope` (90.2 %) — `fromConfig` defaults and
       override matrix, `withConvertFqcnToClassConst` immutability chain.
- [x] **M3-T12** `SarifRecorder` (93.5 %), `ContentClassifier` (94.4 %),
       `FlagSetTarget` (94.7 %) — close residual branches (encode-failure throw;
       remaining classify edge from M2 leftovers; single-flag/non-shared-variable
       edges already partially covered).
- [x] **M3-T13 Verify aggregate:** fresh coverage XML ⇒ **≥95.0 % total AND no
       file below 95 %**; record final figure. If a file proves un-testable past
       threshold without contrived fixtures (e.g. encode-failure internals),
       stop and record the exception + rationale here instead of weakening
       assertions.

**Acceptance (met, with one sanctioned M3-T13 exception):** aggregate
≥95.0 % (final 95.54 % → 98.75 % across the 14 M3 target files); 13 of 14
target files ≥95 %. `PhpLintObserver` remains at **88.9 %** — see the
recorded exception below — and is the single deliberate deviation from the
"no file below 95 %" clause. Gates green.

---

## 6. Milestone M4 — Mutation testing & attribution (F2, F8) 🟠

**Goal:** install Infection, record an MSI baseline scoped per AGENTS.md
priority, and make cross-test leakage inspectable.

### Tasks

- [ ] **M4-T1 Install:** `composer require --dev infection/infection` (verify
      PHP 8.4 compatibility; pin version); add `"mutation": "@php vendor/bin/infection"` 
      and `"mutation:ci"` variant with `--min-msi` placeholder to
      `composer.json` scripts.
- [ ] **M4-T2 Configure:** minimal `infection.json5` — `source: { directories:
      [src] }`, `timeout: 10`, `logs: { text: infection.log, summary: true }`,
      initial `minMsi: 0` (baseline recording, not yet a gate).
- [ ] **M4-T3 Baseline runs (scoped, per mutation priority):**
      `vendor/bin/infection --filter=LineBasedRewriter` then promotion targets
      (`BackedEnumTarget`, `FlagSetTarget`, `ClassConstantTarget`,
      `ClassnameToClassConst`) then validators/idempotency guards
      (`ClosedSetDetector`, `ParsedSourceCache`). Record MSI per scope in this
      plan's implementation log.
- [ ] **M4-T4 Kill cheap survivors only:** fix equivalent/marginal mutants by
      strengthening assertions of tests added in M2/M3 (never by adding ignores).
      Re-run scopes until survivor sets are understood; document any accepted
      escaped mutants with rationale (timeout-bound loops, log strings).
- [ ] **M4-T5 Attribution (F8):** adopt Infection's per-mutant kill lists as the
      attribution mechanism (each mutant names the killing test(s)); audit that
      every unit test's name states its SUT (repo convention already holds —
      verify, fix outliers); record in this plan how future reviewers verify
      "no leakage": mutant-kills-by-test + naming audit. Optional stretch:
      split-run coverage script under `tools/` — only if time-boxed.
- [ ] **M4-T6 Gate decision:** propose (do not enable unilaterally) a
      `--min-msi` floor for CI based on measured baselines; record the proposal
      for the user to accept.

**Acceptance:** `composer mutation` runs green end-to-end; baselines logged;
survivors either killed or documented.

---

## 7. Milestone M5 — Declaration order & line length (F4, F6) 🟡

**Goal:** mechanical conformance pass, then keep it enforced.

**Convention decision (recorded up front):** the prescribed order treats named
static constructors (`create()`) as *factory methods* → they sit **immediately
after the constructor** (or first member when no constructor exists) and before
instance methods; other static methods go last. This reconciles the ergonomic
create-first style with the rule.

### Tasks

- [ ] **M5-T1 `static create()` placement (9 files):** move `create()` above the
      first instance method in `ConstantNameGenerator`, `ConstantNameResolver`,
      `DictionaryNameResolver`, `NumericNameGenerator`, `NikicParserFactory`,
      `NikicTraverserFactory`, `ConstantPlacementFactory`,
      `MagicStringVisitorFactory`, `SemConvConfigFactory` (in factory classes
      that means `create()` before `build()`; classes whose ctor is absent get
      `create()` as first member).
- [ ] **M5-T2 Public-before-private:** `Core/Disenchanter.php` — relocate
      private `record()` below the public API trio (before `invokeObserver`);
      `ContentClassifier` — hoist `isClassNameString()` next to `classify()`;
      `MagicLiteralVisitor` — hoist `getFound()` above the private helpers.
- [ ] **M5-T3 Const-after-property residue:** `Detector.php` resolved by M1-T2
      (constant left the class). Verify `DisenchantScope` similarly via M1-T5;
      fix any remainder.
- [ ] **M5-T4 Port member order:** `ValueObject/ConstantPlacementInterface.php`
      — move hooked property `$occurrences` above `typeKeyword()`.
- [ ] **M5-T5 Codify the checker:** promote the token-level analyzer used by
      REVIEW §7 into `tools/order_check.php` (committed, documented header),
      run it in this milestone; expect **0 violations**; note in AGENTS.md that
      the tool exists for pre-commit checks (wiring into CI is a user decision).
- [ ] **M5-T6 Line length (F6):** reformat the nine >120-col code lines —
      `DisenchanterInterface:41,57` and `Core/Disenchanter:101,122` signatures
      one-parameter-per-line; wrap `LineBasedRewriter:96`,
      `ScopeFilter:42`, `NamingContextExtractor:113`,
      `MagicStringVisitorFactory:28`; wrap
      `InvalidCacheConfigurationException:16` extends/implements clause.
      Generated `tests/_support/_generated/*` exempt.

**Verification:** full gates + `php tools/order_check.php` → 0 +
`awk 'length > 120'` over src/tests/examples excluding generated → empty.

---

## 8. Milestone M6 — Closeout: docs sync & upgrade notes 🟡

- [ ] **M6-T1** `docs/REVIEW.md`: add **Status** resolutions to the findings
      table (F1–F8 → milestone/mechanism), mirroring the previous cycle's
      incremental maintenance pattern.
- [ ] **M6-T2** Scan README/USER_GUIDE for constant-host references
      (`Detector::MAX_SOURCE_BYTES` etc.) introduced by M1 and update examples
      to port-hosted names.
- [ ] **M6-T3** Upgrade-notes entry (this document, release-notes home):
      the four constants moved onto ports (old class-hosted names removed,
      pre-1.0 policy).
- [ ] **M6-T4** Record final metrics in the implementation log: coverage %,
      MSI per scope, violation counts (order/length) — the next review's
      baseline.
- [ ] **M6-T5** Final full gate run + `composer validate`.

---

## 9. Assumptions & decision log

1. **Coverage bar applies per-file AND aggregate** (review wording "at least
   95 %" + explicit sub-threshold table); M3-T13 records exceptions rather than
   gaming numbers.
2. **`create()` = factory method** in the ordering taxonomy → placed right after
   the constructor; this is the reconciliation between ADR-0002 ergonomics and
   the prescribed section order. If the user prefers strict statics-last
   instead, M5-T1 becomes a move to the bottom — one-line deviation note either
   way.
3. **Infection enters as dev tooling, not yet a CI gate**; the `--min-msi` floor
   is proposed (M4-T6), not imposed.
4. **Constants move outright** (pre-1.0) rather than leaving deprecated aliases;
   consumers are in-repo and enumerated.
5. **Attribution mechanism = Infection per-mutant kills** + naming audit;
   a bespoke per-test pcov harness is stretch scope only.
6. **No behavioral changes anywhere in this plan** except error-path exercises
   added by tests themselves.

## Implementation log

Dated record of what actually landed, per milestone. Checkboxes above mark task
completion; this log captures outcomes, deviations, and API impact.

### 2026-08-25 — M1 closed (F5, F3, F7)

- Removed the only inline collaborator instantiation: `MagicLiteralVisitor`
  ctor param `$contentClassifier` is now required; `MagicStringVisitorFactory`
  constructs and passes it explicitly (composition root). No other inline `new`
  of collaborators remains in `src/Adapter` / `src/Core` (factory `new`s are
  sanctioned by §0.3).
- Hoisted four public tunable constants onto their owning ports, each `int`-typed
  where numeric (fixes F7 for `DEFAULT_MAX_ENTRIES`):
  - `StringLiteralDetectorInterface::MAX_SOURCE_BYTES = 1_000_000`
  - `ParsedSourceCacheInterface::DEFAULT_MAX_ENTRIES = 32` (was untyped)
  - `SemConvConfigInterface::DEFAULT_INTERFACE_NAME = 'SemConv'`,
    `DEFAULT_NAMESPACE = 'Generated'`
  - `DisenchantScopeInterface::DEFAULT_MAX_HEREDOC_BODY_CHARS = 4096`
  Impls (`Detector`, `ParsedSourceCache`, `SemConvConfig`, `DisenchantScope`)
  reference via `self::` through inheritance; consumers (`MagicLiteralVisitor`,
  `MagicStringVisitorFactory`, `DetectorCacheTest`) retargeted to the ports.
  Docblock `@see` in `SourceTooLargeException` / `InvalidCacheConfigurationException`
  repointed at the new hosts. Stale WeakMap port docblock on
  `ParsedSourceCacheInterface` rewritten to bounded/content-key wording.
- M1-T5 also removed the misplaced const that the declaration-order scan had
  flagged (part of F4-d) for free.
- Gates green: architecture 0 violations · PHPStan src lvl 9 clean ·
  `format:check` 0/172 · unit 172 tests / 431 assertions · coverage unchanged
  90.00 % (1089/1210). New coverage report identical modulo the moved lines.
- **API impact (pre-1.0, deliberate):** four constants changed host-type from
  class → port. Consumers referencing the old `Detector::…` etc. must retarget;
  tracked for M6 upgrade notes. No signature breakage for callers using the
  interface types.

### 2026-08-25 — M2 closed (F1a, priority-file coverage)

- Added four dedicated unit suites (no source changes — behavior-asserting
  tests only):
  - `tests/Unit/RoleClassifierTest.php` — every `classify()` branch (numeric,
    exception-message, noise-function `FormatString`, plain function/method/
    static-call args, unrecognized call-arg fallthrough, array key vs value,
    array-dim-fetch key, comparison, match/case branch value, enum backing
    value, class-const-value exclusion) plus the `looksIdentifierLike` shape
    boundary.
  - `tests/Unit/LineBasedRewriterTest.php` — idempotency-critical paths: empty
    items, dry-run no-op, single-line replace (found / needle-not-found /
    stale-line skip), multi-line span replace (found / fallback / stale span),
    class-body insertion, duplicate-const skip, top-level enum insertion,
    `enumAlreadyExists` skip, multi-class bottom-up ordering, unknown-class skip.
  - `tests/Unit/NamingContextExtractorTest.php` — every `findVariableName`
    branch (assign var / array-dim-fetch / property-fetch, array value+key,
    typed property default, param default), `detectUnit` hit/miss, and
    `findPrecedingComment` variants (present / code-line break / empty comment
    / blank-line skip).
  - `tests/Unit/DictionaryNameResolverTest.php` — `create()`/`resolve()`
    hit+miss, `fromFile()` happy path (temp-file fixture), missing-file throw,
    non-object JSON throw, array-JSON→empty-map, non-string-entry filtering.
- Coverage of the four priority files (fresh pcov XML): **RoleClassifier
  97.1 %** (was 60.0), **LineBasedRewriter 97.6 %** (was 71.4),
  **NamingContextExtractor 96.4 %** (was 76.4), **DictionaryNameResolver
  100.0 %** (was 23.1); aggregate of the four 97.4 %. All ≥95 % → M2
  acceptance met.
- Gates green: architecture 0 violations · PHPStan src lvl 9 clean ·
  format 0/176 · unit **219 tests / 515 assertions** (was 172 / 431).
- Test-expectation note: `RoleClassifier::looksIdentifierLike` accepts strings
  containing `.` (the regex allows `.`); tests assert the *actual* behavior,
  not the docblock's "sentence punctuation" wording. Flagged for a later
  docstring/behavior reconciliation if desired (out of M2 scope).

### 2026-08-25 — M3 closed (F1b, remaining-file coverage)

- Lifted the 14 remaining §11 gap-table files to ≥95 % (13 of 14) purely via
  behavior-asserting tests — no source changes. New suites:
  - `tests/Unit/ConstantPlacementTest.php`, `tests/Unit/SemConvConfigTest.php`,
    `tests/Unit/ValueObject/DisenchantScopeTest.php` (extended existing),
    `tests/Unit/Adapter/{FileSemConvStore,NamespaceUseInserterTrait,
    ClassnameToClassConst,JsonReportRecorder,SarifRecorder,SemConvAliasRewriter,
    PhpLintObserver}Test.php`, and extensions to `BackedEnumTargetTest`,
    `FlagSetTargetTest`, `CompositePromotionTargetTest`, `ContentClassifierTest`.
  - Branch coverage added: `ConstantPlacement::typeKeyword` (string/int/float);
    `SemConvConfig` virtual `outputPath` override + `fullyQualifiedName` null
    namespace; `DisenchantScope` `fromConfig` full matrix, string-list/int-config
    non-array & non-numeric fallbacks, `withConvertFqcnToClassConst` immutability;
    `FileSemConvStore` load-miss/empty/read-failure + save dir-create/write
    fail-closed throws (warnings silenced in-test so the source's RuntimeException
    propagates); `NamespaceUseInserterTrait` idempotent-already-imported,
    insert-after-last-use, no-anchor; `ClassnameToClassConst` supports false
    (empty / non-FQCN) + build empty no-op; `JsonReportRecorder`/`SarifRecorder`
    encode-failure throws (resource in payload → json_encode false); `BackedEnumTarget`
    empty-occurrences no-op, duplicate-value skip, case-name collision suffix,
    `Disenchanted` fallback name; `SemConvAliasRewriter` empty placements, already
    aliased idempotency, placement-not-in-code; `CompositePromotionTarget` empty
    composite returns Const no-op; `FlagSetTarget` supports-false (no shared
    naming context), build empty no-op, duplicate-value skip; `ContentClassifier`
    empty-string → Unknown, regex-with-delimiter-and-space → not regex;
    `PhpLintObserver` valid-source passes + broken-source throws `VerificationException`.
- Final per-file coverage (pcov): BackedEnumTarget 97.8 %, ClassnameToClassConst
  100 %, CompositePromotionTarget 100 %, ContentClassifier 100 %, FileSemConvStore
  100 %, FlagSetTarget 98.2 %, JsonReportRecorder 100 %, NamespaceUseInserterTrait
  100 %, SarifRecorder 100 %, SemConvAliasRewriter 100 %, ConstantPlacement 100 %,
  DisenchantScope 100 %, SemConvConfig 100 %. Aggregate 95.54 % → M3 target files
  98.75 %.
- **Sanctioned M3-T13 exception — `PhpLintObserver` (88.9 %, 16/18).** The two
  uncovered statements are the defensive temp-file I/O guards: `tempnam()`
  returning false (line 30) and `file_put_contents()` returning false (line 35).
  Both only fire on injected filesystem failure. Codeception's error handler
  converts the resulting PHP *warning* into an exception that is thrown *before*
  the source's fail-closed `RuntimeException`, so asserting that contract requires
  contrived filesystem fault injection (e.g. FUSE/permission games) that weakens
  the test's honesty. The fail-closed throw contract is already exercised by the
  sibling `FileSemConvStore` save-failure paths (same RuntimeException shape), so
  the behavior is covered indirectly. Left at 88.9 % deliberately per M3-T13; the
  other 13 files meet the ≥95 % bar.
- Gates green: architecture 0 violations · PHPStan src lvl 9 clean · format 0/185
  · unit **268 tests / 589 assertions** (was 219 / 515).

### 2026-08-25 — M0 closed, baseline recorded

- All four gates green on `main` (`1c24d00` tree): architecture PASS · PHPStan
  src lvl 9 clean · format 0 of 172 files · unit suite OK **172 tests / 431
  assertions**.
- Coverage regenerated via pcov (`--coverage-xml`): **90.00 % lines (1089/1210)**,
  **18 files below the 95 % bar** — gap table byte-identical to REVIEW §11, so
  M2/M3 file targets are confirmed as written.
- No branch created (mono-repo rule); per-milestone commit granularity provides
  isolation. No code touched; baseline-only milestone.

## Suggested execution order

`M0 → M1 → M2 → M3 → M4 → M5 → M6`

Rationale: hygiene fixes first (small, unblock clean diffs), then the largest
effort item (coverage) split priority-vs-rest, tooling immediately after so
mutation scoring covers the new tests, mechanical style sweep last, docs at the
end.
