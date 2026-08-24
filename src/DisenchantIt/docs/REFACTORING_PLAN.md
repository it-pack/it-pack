# DisenchantIt — Refactoring Plan (post-review consolidation)

> Companion to `docs/REVIEW.md` (the current engineering review) and `README.md`.
>
> This plan converts every open finding of `docs/REVIEW.md` (F1–F8) into
> **milestones (M0–M6)** with **step-by-step implementation tasks**. The previous
> plan (Phases 0–6) is archived at `docs/archive/REFACTORING_PLAN.md`; all of its
> items are done or closed there.
>
> Severity convention (carried over from the review): 🔴 strong candidate ·
> 🟠 worthwhile · 🟡 minor/hygiene.
>
> Every task below is **behavior-preserving** unless explicitly marked otherwise,
> and every milestone ends with the full gate suite green:
>
> ```bash
> composer architecture
> vendor/bin/phpstan analyse src --level=9
> composer format:check
> vendor/bin/codecept run unit        # baseline: OK (168 tests, 388 assertions)
> ```

---

## 0. Ground rules (apply to every task)

1. **Source-text safety hard rule** is never touched: no task adds execution or
   autoloading of analysed files.
2. **Layering** stays enforced by `phparkitect.php`: Contract → ValueObject only;
   Core → Contract + ValueObject; Adapter → Contract + ValueObject; Factory is
   the composition root. Run `composer architecture` after every structural move.
3. **Factory contract**: collaborators are wired in factories; no inline `new`
   inside domain code. Factories expose instance `build()` plus an optional
   `static create(): self` proxy (ADR-0002).
4. **Concrete classes stay `final`** and **backed by an interface** (ADR-0004).
   New helpers introduced here follow the same rule.
5. One coherent commit per task; one milestone per PR-sized change set. Never mix
   a refactor with an unrelated cleanup.
6. Idempotency guarantees (no self-rewrap, no duplicate const, SemConv merge
   safety) are regression-tested by the existing suite — any red test during a
   milestone means the refactor drifted; stop and reconcile before continuing.

---

## 1. Traceability matrix

| Finding | Sev | Topic | Milestone | Primary files | Tests |
|---|---|---|---|---|---|
| F1 | Medium | Duplicated name-slugify | **M1** | `BackedEnumTarget`, `FlagSetTarget`, new `NameSlugifier(+Interface)`, `PromotionTargetFactory` | new `NameSlugifierTest`; existing target suites must stay green |
| F4 | Low–Med | Unbounded parse cache | **M2** | `ParsedSourceCache`, `DetectorFactory` (opt. exposure) | extend `DetectorCacheTest` |
| F2 | Low | Duplicated const regex | **M3** | `LineBasedRewriter` | existing rewrite suites (no new cases required) |
| F3 | Low | Duplicated class-brace scan | **M3** | `LineBasedRewriter` | existing rewrite suites |
| F7 | Low | Legacy placement shim | **M4** | `LineBasedRewriter`, `Contract/RewriterInterface` | full suite; UPGRADE note |
| F8 | Info | Duplicate docblock | **M4** | `Core/Disenchanter` | none (comment-only) |
| F5 | Low | Trivial `else` sites | **M5** | `LineBasedRewriter`, `ClassConstantTarget` | existing suites |
| F6 | Low | Magic `'private'`/`'public'` strings | **M5** | new `ValueObject/Visibility`, `ConstantDeclaration`, `ClassConstantTarget`, `FlagSetTarget`, `SemConvCollector` | existing suites + small `Visibility` coverage |

Deliberately **not planned** (recorded so they are not re-raised):

- `FlagSetTarget.php:139` `elseif` accumulator (first-vs-differing variable) —
  readable as-is; converting would obscure the invariant. **Won't fix.**
- WeakMap/Fibers/lazy-object leverage opportunities (REVIEW §7) — correctly
  unused today; revisit only when a multi-file driver lands.

---

## 2. Milestone M0 — Baseline & safety net 🟡

**Goal:** prove the starting state and isolate the work.

- [x] **M0-T1** Record baseline gate output (all four commands above) in the
      working notes; expected: PASS / no errors / 0 files / OK (164, 378).
      **Recorded (2026-08-24, post-M1):** architecture PASS · PHPStan lvl 9 no
      errors · format 0 of 170 files to fix · unit suite OK (**168 tests, 388
      assertions** — grew by `NameSlugifierTest`; pre-M1 figure was 164/378).
      This is the regression baseline for M2 onward.
- [x] **M0-T2** ~~Create branch `refactor/post-review-consolidation` off
      `main`.~~ **Adapted:** this repository is a mono-repo with several
      subprojects worked on simultaneously; milestones land as ordinary commits
      on `main` instead of a dedicated branch (user decision). Isolation is
      provided per-milestone commit granularity (`e685933` = M1) rather than a
      long-lived branch.

**Acceptance:** baseline green before each milestone starts; M1 landed green on
`main`.

---

## 3. Milestone M1 — Shared name slugifier (F1, Medium) 🟠

**Goal:** remove the parallel-maintenance risk between
`BackedEnumTarget::pascalCase` (`src/Adapter/BackedEnumTarget.php:102`) and
`FlagSetTarget::pascalCase` (`src/Adapter/FlagSetTarget.php:151`). The two
implementations are identical except for the empty-result fallback
(`'Value'` vs `'Flag'`).

**Design decisions**

- The slugifier is consumed **only by two adapters**, so it stays
  **adapter-internal**: `src/Adapter/NameSlugifierInterface.php` +
  `src/Adapter/NameSlugifier.php`. It does **not** go into `Contract\` — that
  would publish an extension point with no external consumer (AGENTS.md API
  discipline). phparkitect's "final classes are backed by interfaces" rule is
  satisfied within the Adapter layer.
- Fallback becomes a parameter so one implementation serves both callers
  byte-compatibly.
- Wiring happens in `PromotionTargetFactory::build()` — the composition root —
  with **one shared instance** injected into both targets (DI via factories;
  the factory may `new` collaborators).

### Tasks

- [x] **M1-T1** Add the port:

  ```php
  namespace ItPack\DisenchantIt\Adapter;

  interface NameSlugifierInterface
  {
      /**
       * PascalCases a raw literal / variable name into a valid PHP identifier
       * fragment. Keeps only alphanumerics, title-cases each word, guards a
       * leading digit with 'V', and returns $fallback when nothing survives.
       */
      public function pascalCase(string $value, string $fallback = 'Value'): string;
  }
  ```

- [x] **M1-T2** Add `final readonly class NameSlugifier implements
      NameSlugifierInterface`. Move the body verbatim from
      `BackedEnumTarget::pascalCase` (`[^A-Za-z0-9]+` collapse, `ucfirst` join,
      `'V' .` leading-digit guard), returning `$fallback` instead of the
      hard-coded `'Value'`.

- [x] **M1-T3** Inject `NameSlugifierInterface $nameSlugifier` into the
      constructors of `BackedEnumTarget` and `FlagSetTarget`; delete both
      private `pascalCase()` methods; route calls through the port:
      - `BackedEnumTarget::caseNameMap` → `$this->nameSlugifier->pascalCase($lit->value, 'Value')`
      - `BackedEnumTarget::enumName` → `$this->nameSlugifier->pascalCase($proposed, 'Value')`
      - `FlagSetTarget::build` (line 77) → `$this->nameSlugifier->pascalCase($variable, 'Flag')`
      Update the stale docblock at `FlagSetTarget.php:147-149` ("kept private to
      avoid a shared dependency") to reference the shared service.

- [x] **M1-T4** Wire in `PromotionTargetFactory::build()`:
      `$slugifier = new NameSlugifier();` passed to both targets. Verify no
      other construction sites exist (`grep -rn "new BackedEnumTarget\\|new FlagSetTarget"`),
      including `tests/Unit/Adapter/` fixtures — update those to receive the
      collaborator.

- [x] **M1-T5** Add `tests/Unit/Adapter/NameSlugifierTest.php`:
      - splits on any non-alphanumeric run and title-cases words
        (`'cache-ttl_seconds'` → `'CacheTtlSeconds'`);
      - guards a leading digit (`'4xx_mode'` → `'V4XxMode'`-shape);
      - returns the **default** fallback `'Value'` on empty result;
      - honors a **custom** fallback (`'Flag'`) — the exact behavioral contract
        both former copies encoded.

**Verification:** full gates; `tests/Unit/Adapter/FlagSetTargetTest.php`,
`BackedEnumTarget`-related suites, and `PromotionTargetFactory` coverage pass
**unchanged** — that is the byte-compatibility proof.

**Rollback:** single-commit revert; no serialized formats touched.

---

## 4. Milestone M2 — Bound the parsed-source cache (F4, Low–Med) 🟠

**Goal:** close the unbounded-memory finding at
`src/Adapter/ParsedSourceCache.php:28` while keeping the content-hash identity
design (which is correct — see REVIEW §7).

**Design decisions**

- `ParsedSourceCacheInterface` stays **unchanged** (adding members breaks
  third-party implementers).
- The bound lives on the concrete class as an optional constructor argument with
  a default, so `new ParsedSourceCache()` call sites
  (`src/Factory/DetectorFactory.php:67`, `tests/Unit/DetectorCacheTest.php`,
  `examples/14_performance_cache.php:93,111`) keep working untouched.
- Eviction is **deterministic FIFO** (oldest insertion evicted first) rather than
  LRU: simpler, deterministic under the repo's testing rules, and the working
  set per run is tiny (default cap 32 covers any realistic reused-Detector
  session; single-`Disenchanter` runs need exactly 1 entry).

### Tasks

- [ ] **M2-T1** In `ParsedSourceCache`:
      - add `public const DEFAULT_MAX_ENTRIES = 32;`
      - constructor: `public function __construct(private readonly int $maxEntries = self::DEFAULT_MAX_ENTRIES)`
      - throw `InvalidCacheConfigurationException` (new, extends
        `\InvalidArgumentException`, implements
        `DisenchantItExceptionInterface`) when `$maxEntries < 1`;
      - in `put()`, before inserting, evict from `array_key_first($this->store)`
        while `count($this->store) >= $this->maxEntries`;
      - rewrite the class docblock: entries are **bounded**; the lifetime note
        ("persist for the lifetime of the owning Detector") remains, now with an
        explicit memory ceiling.
- [ ] **M2-T2** *(Optional exposure)* Read `src/Factory/DetectorFactory.php`
      (~lines 40–80) and, only if its `create()` already takes options, thread a
      `?int $cacheMaxEntries = null` through to the cache. Do **not** grow the
      factory signature just for this — the default suffices until a consumer
      asks.
- [ ] **M2-T3** Extend `tests/Unit/DetectorCacheTest.php`:
      - inserting `DEFAULT_MAX_ENTRIES + N` distinct sources evicts the oldest
        (`has()` false for earliest keys, true for the newest cap);
      - re-`put()` of an existing key does not grow the store;
      - `maxEntries = 0` throws `InvalidCacheConfigurationException`;
      - existing hit-across-detect/rewrite tests remain green (bound never trips
        at realistic sizes).

**Verification:** full gates; run `php examples/14_performance_cache.php` and
confirm identical output (cache semantics unchanged at these sizes).

**Rollback:** revert commit; interface untouched so no downstream coordination
needed.

---

## 5. Milestone M3 — `LineBasedRewriter` DRY consolidation (F2, F3, Low) 🟡

**Goal:** single sources of truth inside the rewriter for (a) the const-name
regex and (b) the classlike-declaration scan.

### Tasks

- [ ] **M3-T1 Const-name helper (F2).** Extract

      ```php
      private function constantNameFromLine(string $line): ?string
      ```

      wrapping the pattern currently duplicated at
      `LineBasedRewriter.php:205` (duplicate-guard in `planInsertions`) and
      `:288` (collection in `findExistingConstantNames`). Replace both call
      sites. Acceptance: `grep -n 'const\\\\s+' src/Adapter/LineBasedRewriter.php`
      shows the pattern exactly once.

- [ ] **M3-T2 Declaration-pattern helper (part of F3).** The
      `(?:class|enum|interface|trait)` alternation is built twice
      (`:309-312` and `:340-343`). Extract

      ```php
      private function classlikeDeclarationPattern(string $className): string
      ```

      returning the composed `sprintf('/\\b(?:class|enum|interface|trait)\\s+%s\\b/', preg_quote($className, '/'))`.

- [ ] **M3-T3 Unified brace scanner (F3).** Merge
      `findClassOpeningBraceLine` (`:338`) and `findClassClosingBraceLine`
      (`:307`) into one scanner:

      ```php
      /** @return array{opening: int, closing: int}|null */
      private function findClassBodyBraces(array $lines, string $className): ?array
      ```

      locating the declaration line (via the shared pattern helper), then walking
      once to return the opening `{` line index and the depth-balanced closing
      `}` index. Keep both original method names as thin wrappers (or inline the
      two call sites in `planInsertions`) so the insertion logic reads as before.
      Note: this reintroduces one small branch inside the walker — accepted in
      exchange for removing the near-duplicate loops (REVIEW §5.3).

**Verification:** full gates. No new tests strictly required — the existing
rewrite suites (`DisenchanterTest`, `FlagSetTargetTest`,
`Phase5SyntacticPositionsTest`, `TypedNumericRewriteTest`, idempotency
second-pass assertions) exercise every touched path; add a fixture only if a gap
surfaces.

---

## 6. Milestone M4 — Remove the legacy placement shim (F7) + docblock fix (F8) 🟡

**Goal:** finish the Phase-2 transition by deleting
`artifactFromPlacement()` and narrowing the `RewriterInterface` port.

**Precondition (verified 2026-08-24):** no caller passes bare
`ConstantPlacementInterface` items into `rewrite()` — all production and test
paths emit `PromotionArtifactInterface` (grep over `src/` and `tests/Unit/`
confirmed; `ClassConstantTarget` embeds placements *inside* the artifact).
`ConstantPlacementInterface` itself remains in use as artifact payload and via
`ConstantPlacementFactory` — only the rewriter shim goes.

⚠️ **Backward-compatibility note (pre-1.0, deliberate):** narrowing the
`@param` docblock of `RewriterInterface::rewrite()` is documentation-level, but
third-party implementations/callers relying on legacy placements lose support.
Record this in the release notes / upgrade section produced in M6.

### Tasks

- [ ] **M4-T1** In `src/Contract/RewriterInterface.php`: drop the
      `ConstantPlacementInterface` import; change the docblock type to
      `list<PromotionArtifactInterface>`; delete the "during the Phase-2
      transition" sentence; refresh the port docblock ("apply promotion
      artifacts to one file's source text").
- [ ] **M4-T2** In `src/Adapter/LineBasedRewriter.php`: delete
      `artifactFromPlacement()` (`:130-156`), simplify the item loop
      (`:43-50`) to accept artifacts directly, remove the now-unused
      `ConstantPlacementInterface` import, and strip the transition paragraphs
      from the class docblock (`:22-25`).
- [ ] **M4-T3** Sweep for stragglers: `grep -rn "artifactFromPlacement\\|
      ConstantPlacementInterface" src/ examples/` — expected remaining hits are
      the legitimate payload usages only (`PromotionArtifact`,
      `ClassConstantTarget`, factories).
- [ ] **M4-T4** Fix the duplicate docblock at `Core/Disenchanter.php:59-66`
      (merge the recorder-port description and the `@param` block into one
      docblock on `record()`).

**Verification:** full gates (PHPStan will flag any missed import/type drift);
suite stays at 164+ passing.

---

## 7. Milestone M5 — Style & magic-value sweep (F5, F6, Low) 🟡

**Goal:** align the remaining trivial `else` sites with the repo's guard-clause
style and remove the literal visibility strings.

Ordering note: run this **after** M4 so the shim's `'private'`
(`LineBasedRewriter.php:145`) is already gone and isn't migrated twice.

### Tasks

- [ ] **M5-T1 Guard conversion in `planInsertions` (F5).**
      `LineBasedRewriter.php:176-220` currently wraps the top-level branch in
      `else`. Convert to early-continue:

      ```php
      if ($classInsertions[0]->topLevel) {
          // ... anchor lookup, idempotency guard, merge ...
          $insertionPoints[$anchorIndex] = array_merge(...);
          continue;
      }

      // class-body branch, unnested
      $braceLineIndex = $this->findClassBodyBraces(...)['opening'] ?? null; // post-M3 shape
      ```

      Behavior identical; nesting depth drops by one.

- [ ] **M5-T2 Expression selects in `ClassConstantTarget` (F5).**
      Replace the `if ($isMultiline) … else …` pairs at
      `ClassConstantTarget.php:68-79` and `:91-97` with expression form, keeping
      the side-effecting statement separate:

      ```php
      $valueExpression = $isMultiline
          ? var_export($classOccurrences[0]->value, true)
          : $classOccurrences[0]->raw;
      if (!$isMultiline) {
          $placements[] = $this->placements->build(...);
      }
      // ...
      [$needle, $endLine] = $isMultiline
          ? [$lit->raw, $lit->endLine]
          : [$lit->needle(), null];
      ```

- [ ] **M5-T3 `Visibility` value object (F6).** Introduce a backed enum:

      ```php
      // src/ValueObject/Visibility.php
      enum Visibility: string
      {
          case Private = 'private';
          case Protected = 'protected';
          case Public = 'public';
      }
      ```

      Then:
      - `ConstantDeclaration::$visibility` becomes
        `Visibility $visibility = Visibility::Private`; `toCodeLine()` renders
        `$this->visibility->value`;
      - call sites updated: `ClassConstantTarget.php:85` →
        `Visibility::Private`, `FlagSetTarget.php:103` and
        `SemConvCollector.php:196` → `Visibility::Public`;
      - acceptance: `grep -rn "'private'\\|'public'" src/` returns no
        visibility-literal hits outside the enum itself.

      *Fallback option (only if the ripple proves larger than expected):* keep
      the `string` property and add
      `ConstantDeclarationInterface::VISIBILITY_PRIVATE/PUBLIC` class constants.
      Record whichever option landed in the M6 status table.

**Verification:** full gates; rendered `const` lines are byte-identical
(covered by every insertion assertion in the suite).

**API impact:** `ConstantDeclaration` constructor parameter type widens from
`string` to the enum — pre-1.0 deliberate change; listed in M6 release notes.

---

## 8. Milestone M6 — Documentation sync, status closeout, final audit 🟡

- [ ] **M6-T1** Update `docs/REVIEW.md`: add a **Status** column to the §11
      findings table marking F1–F8 resolved, with the landed mechanism
      (e.g. "F1 → `Adapter\\NameSlugifier`").
- [ ] **M6-T2** Scan `README.md` / `USER_GUIDE.md` for drift against touched
      surfaces: `RewriterInterface` contract wording, `ConstantDeclaration`
      construction examples (now `Visibility::…`), and any cache-lifetime prose;
      fix what changed.
- [ ] **M6-T3** Add a short **Upgrade notes** block (release notes location per
      repo convention) covering the two pre-1.0 deliberate changes:
      `RewriterInterface` no longer accepts legacy placements; `ConstantDeclaration`
      takes a `Visibility` enum.
- [ ] **M6-T4** Optional quality extras, only where repository scripts exist
      (verify `composer.json` first): targeted Infection run scoped to
      `LineBasedRewriter` + promotion targets (mutation priority per AGENTS.md:
      placement decisions, idempotency guards), `composer validate`.
- [ ] **M6-T5** Final full gate run; merge the branch; tag-free (no release
      implied).

---

## 9. Assumptions & decision log

1. **Slugifier placement (Adapter, not Contract)** — internal collaborator, no
   external consumer; avoids publishing an extension point prematurely.
2. **FIFO eviction, cap 32, interface unchanged (M2)** — deterministic, minimal
   surface; revisitable when a batch driver needs LRU/tunables.
3. **Shim removal is safe (M4)** — verified by grep that no `rewrite()` caller
   passes legacy placements; the port narrowing is docblock-level, runtime
   signature (`array $items`) unchanged.
4. **`Visibility` enum preferred over class constants (M5)** — closed value set,
   matches ADR-0003 guidance; constants kept as documented fallback.
5. **No new ADR required** — none of these tasks changes aggregate boundaries,
   dependency direction, lifecycle semantics, or serialization. The two API
   notes land as upgrade-notes instead (pre-1.0 policy, AGENTS.md).
6. **Baseline counts** (recorded in §2 M0-T1; currently 168 tests / 388
   assertions) are expected to hold or grow;
   any shrinkage during a milestone is a regression signal, not acceptable
   collateral.

## Implementation log

Dated record of what actually landed, per milestone. The checkboxes above mark
task completion; this log captures outcomes, deviations, and API impact.

### 2026-08-24 — M1 landed (`e685933`), M0 closed on `main`

**M1 — Shared name slugifier (REVIEW F1).**

- Added `Adapter\NameSlugifierInterface` (port, `pascalCase(string $value,
  string $fallback = 'Value'): string`) and `Adapter\NameSlugifier`
  (`final readonly`, stateless). Body moved verbatim from the two deleted
  private `pascalCase()` copies; only difference: empty-result fallback is now
  a parameter.
- `BackedEnumTarget` / `FlagSetTarget` take `NameSlugifierInterface` via
  constructor injection and pass explicit fallbacks (`'Value'` for enum
  cases/names, `'Flag'` for flag-set base names). Stale "kept private to avoid
  a shared dependency" docblock removed.
- **API impact (pre-1.0, deliberate):** `BackedEnumTarget::*construct*` /
  `FlagSetTarget::__construct()` changed from no-arg to one required
  `NameSlugifierInterface` argument. Direct constructors are internal wiring;
  consumers use `PromotionTargetFactory::create()->build()`, which is unchanged
  and now wires a single shared slugifier into both targets.
- Construction sites updated: `PromotionTargetFactory`,
  `BackedEnumTargetTest`, `FlagSetTargetTest`,
  `CompositePromotionTargetTest`, `examples/05_placement_strategies.php`.
- Incidental fix in example 05 (same file, discovered by static analysis): its
  import pointed at non-existent `Factory\ConstantNameResolver`; corrected to
  `Adapter\ConstantNameResolver` (would have fataled on run).
- New `tests/Unit/Adapter/NameSlugifierTest.php` (6 cases): word splitting /
  title-casing, passthrough, leading-digit guard, default fallback `'Value'`,
  custom fallback `'Flag'`. Note: `ucfirst('4xx')` leaves digit-leading words
  untouched → `'V4xxMode'`, byte-compatible with the removed copies.
- Verification: architecture PASS · PHPStan src lvl 9 clean · format clean ·
  unit suite OK 168 tests / 388 assertions · example 05 smoke-runs.

**M0 — baseline recorded, branch step adapted.**

- All four gates re-run green post-M1 (numbers in §2 M0-T1). Baseline for
  M2+ is **168 tests / 388 assertions**.
- Deviation: no dedicated branch — mono-repo with parallel subproject work;
  milestones land as ordinary commits on `main` (M1 = `e685933`). Isolation is
  per-milestone commit granularity instead.

## Suggested execution order

`M0 → M1 → M2 → M3 → M4 → M5 → M6`

Rationale: highest-value/risk-first consolidations (M1, M2), then mechanical
rewriter cleanup (M3) *before* the shim deletion (M4) so the scanner refactor is
validated on live paths, style sweep last (M5) to avoid migrating code that M4
deletes, docs closeout at the end.
