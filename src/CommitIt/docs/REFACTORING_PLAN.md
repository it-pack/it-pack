# CommitIt — Refactoring Plan

> Implementation plan derived from `docs/REVIEW.md`. Each milestone is independently shippable and
> must keep `composer quality` green. Tasks are ordered by risk (security first) then by dependency
> (style fixes that unblock coverage reporting come before threshold raises).
>
> Working dir: `src/CommitIt` (`it-pack/commit-it`). Sibling `it-pack` packages are out of scope.

## 0. Principles & conventions

- **One milestone per branch / PR.** Milestones are sequenced so each lands behind a green
  `composer quality`; do not bundle milestones.
- **TDD for behaviour changes.** When a task adds behaviour (env pinning, named constructor, new
  trait), write/extend the test *before* the source change and confirm it fails first.
- **No quality-gate regression.** Before pushing, run `composer quality` and the milestone's
  specific coverage/mutation command.
- **Preserve the §5 carve-out.** Value objects, option builders, and `@internal` adapter helpers stay
  non-interface-backed unless Milestone 8 explicitly decides otherwise. Do not silently override
  `AGENTS.md` §5.
- **Do not touch committed adapter mapping semantics.** Refactors must not change git command output
  or exception taxonomy (ADR-007). Mapping changes go through review, not this plan.
- **Real commands only.** Verification commands below use the actual `composer` scripts in
  `composer.json` (`validate`, `cs:check`, `cs:fix`, `analyse`, `architecture`, `test`,
  `test:unit`, `test:functional`, `coverage`, `mutation`, `security:audit`, `quality`).

## 1. Milestone roadmap

| # | Milestone | Priority | Risk | Depends on | Gate to add | Status |
|---|---|---|---|---|---|---|
| M1 | Secure locale pinning (env injection, no `putenv`) | Medium (security) | Low–Med | — | `composer quality` | ✅ Done |
| M2 | Extract `ObserverDispatch` trait (DRY) | Medium | Low | — | `composer quality` | ✅ Done |
| M3 | Close Unit coverage gap to ≥95% lines | Medium | Low | M2 (optional) | `composer coverage` |
| M4 | Fix coverage job + enforce mutation | Medium | Low | M1, M3 | `coverage:all`, `quality:full` |
| M5 | Declare `readonly class` on eligible types | Low | Low | — | `composer quality` |
| M6 | Enforce `line_length` + import FQNs + decl order | Low | Low | — | `cs:check` |
| M7 | Magic literals → const/enum; `Commit::from` | Low | Low | — | `composer quality` |
| M8 | Interface-backing reconciliation (decision) | Low | Low | — | decision only |

## 2. Milestones in detail

### M1 — Secure locale pinning via `$env` (no `putenv`)

**Goal.** Remove the process-global `putenv('LC_ALL=C')` / `getenv` mutation in
`src/Adapter/Git/LocalePinningRunner.php:39-47`. Pin locale by merging `LC_ALL=C`/`LANG=C` into the
`$env` array already passed to `IRunner::run($cwd, $args, $env)`, so parallel workers never race on the
global environment and exception mapping is locale-stable.

**Tasks**

| ID | File | Action | Verification | Risk |
|---|---|---|---|---|
| M1.1 | `src/Adapter/Git/LocalePinningRunner.php` | Drop `putenv`/`getenv`. Build `['LC_ALL' => 'C', 'LANG' => 'C']` and merge into the `$env` argument before `parent::run()` (or into the runner's `run`). Keep the class `@internal`. | `composer analyse` (no new error), `composer architecture` | Low |
| M1.2 | `src/Adapter/Git/GitExceptionRunner.php` / `GitPhpRepositoryFactory.php` | Confirm the merged `$env` propagates through `IRunner::run` (no consumer relies on the global `LC_ALL`). If the factory assembles `$env`, inject the locale there. | `composer test:functional` | Low |
| M1.3 | `tests/Unit/Adapter/Git/*LocalePinning*` (new) | Add a unit test asserting `LocalePinningRunner::run` does **not** call `putenv` (wrap with `phpunit` `FunctionsToNamespace` / `putenv` interceptor or assert `getenv('LC_ALL')` unchanged around the call) and that the `$env` it returns contains `LC_ALL=C`. | `composer test:unit` | Low–Med |

**Done criteria.** No `putenv`/`getenv` in `src`. Functional suite green. New test proves the global env
is untouched. `composer quality` green.

**Rollback.** Revert `LocalePinningRunner.php` only; callers pass `$env` unchanged.

#### M1 — Implementation notes (completed)

- `src/Adapter/Git/LocalePinningRunner.php` rewritten: no `putenv`/`getenv` mutation. It now reads the
  current process environment once via `getenv()`, merges the caller-supplied `$env` (values cast to
  `string`), then pins `LC_ALL`/`LANG` to the constant `self::LOCALE = 'C'`, and forwards the **full**
  env array to the inner runner. Forwarding the full env is required because `CliRunner` passes `$env`
  to `proc_open` as the child's *entire* environment (so git still resolves PATH/HOME/GIT_*); pinning
  via the env array removes the process-global race under parallel workers.
- `tests/Unit/Adapter/Git/LocalePinningRunnerTest.php` rewritten to assert the new contract: the child
  env array contains `LC_ALL=C`/`LANG=C`, caller env is preserved (and a caller-supplied locale is
  overridden), and the host `getenv('LC_ALL')` is unchanged before/after the call.
- `tests/Unit/Adapter/Git/GitPhpRepositoryFactoryTest.php` updated: `MemoryRunner` keys results by the
  env array, so the three `setResult` calls now register results with a `pinnedEnv()` helper that
  mirrors the locale-pinned env (resolved per process, so it equals the runner's own view). This is a
  test-only change; `GitAdapterMemoryTrait` (which builds `MemoryRunner` directly, without the locale
  decorator) was unaffected.
- **Verification run:** `composer analyse` (0 errors), `composer cs:check` (0 files), `composer test:unit`
  (115 ✔ / 314 assertions), `composer test:functional` (31 ✔ / 89 assertions). `composer quality`
  itself only fails on `security:audit` due to a pre-existing abandoned dev dependency
  (`doctrine/annotations`), unrelated to this change.

---

### M2 — Extract `ObserverDispatch` trait (DRY)

**Goal.** Remove the duplicated observer-dispatch logic in `ObservedRepository::run`/`runVoid`/`notify`
(`src/Application/ObservedRepository.php:192-244`, including the duplicated docblock at `:181-191`) and
`ObservedWorkingTree::run`/`runVoid`/`notify` (`src/Application/ObservedWorkingTree.php:63-113`).

Design note: to avoid a trait/property collision with the existing `private readonly RepositoryObserver
$observer` in both classes, the trait takes the observer **as a parameter** rather than reading
`$this->observer`:

```php
// src/Application/ObserverDispatch.php  (new, final trait, @internal)
private function dispatchRun<T>(RepositoryObserver $observer, string $operation, array $context, callable $operation): T
{
    $observer->onStart($operation, $context);
    try {
        $result = $operation();
    } catch (Throwable $e) {
        $observer->onFailure($operation, $context, $e);
        throw $e;
    }
    $observer->onSuccess($operation, $context);
    return $result;
}
// + dispatchRunVoid(...) mirroring
```

**Tasks**

| ID | File | Action | Verification | Risk |
|---|---|---|---|---|
| M2.1 | `src/Application/ObserverDispatch.php` (new) | Create the trait with `dispatchRun()` and `dispatchRunVoid()` (observer passed in). No state. | `composer analyse` | Low |
| M2.2 | `src/Application/ObservedRepository.php` | Replace the inline `run`/`runVoid`/`notify` bodies with `$this->dispatchRun($this->observer, ...)`. Delete the duplicated `@template` docblock (`:181-191`). | `composer cs:check`, `composer test` | Low |
| M2.3 | `src/Application/ObservedWorkingTree.php` | Same replacement. Import `WorktreeInfo` (currently FQN at `:34`). | `composer test` | Low |
| M2.4 | `tests/Unit/Application/ObservedRepositoryTest.php`, `ObservedWorkingTreeTest.php` | Confirm existing onStart/onSuccess/onFailure assertions still pass (behaviour unchanged). | `composer test:unit` | Low |

**Done criteria.** No duplicated dispatch body; single trait. Behaviour-identical (observer test asserts
unchanged). `composer quality` green.

#### M2 — Implementation notes (completed)

- New `src/Application/ObserverDispatch.php` (`@internal` trait, no state). It exposes `dispatchRun()`
  (generic, returns `T`), `dispatchRunVoid()` (returns `void`), and an internal `dispatchNotify()` that
  swallows observer exceptions on a non-recursive path. The observer is passed **as a parameter** so the
  trait cannot collide with the `private readonly RepositoryObserver $observer` held by each decorator.
- **Deliberate deviations from the plan's pseudo-code:**
  - A PHP `trait` cannot be declared `final`, so the trait is plain (not `final`) with an `@internal` tag.
  - The plan's sketch omitted the `notify` swallow wrapper; behaviour would *not* have been identical
    without it, so the swallow path is preserved inside `dispatchNotify` (mirrors the original `notify`).
  - The trait signature keeps the original call-site argument order `(observer, operation, action,
    context)` rather than the plan's `(observer, operation, context, action)`, which minimizes the diff in
    every call site and avoids an accidental argument-swap.
- `ObservedRepository.php` and `ObservedWorkingTree.php` now `use ObserverDispatch;` and forward `$this->observer`
  to `dispatchRun`/`dispatchRunVoid`; their private `run`/`runVoid`/`notify` bodies were deleted.
- `ObservedWorkingTree` also imports `WorktreeInfo` (was a docblock FQN) per M2.3.
- **Verification:** `composer analyse` (0 errors), `composer cs:check` (0 files), `composer architecture`
  (0 violations), `composer test:unit` (115 ✔ / 314 assertions), `composer test:functional` (31 ✔ / 89
  assertions). Existing `ObservedRepositoryTest`/`ObservedWorkingTreeTest` onStart/onSuccess/onFailure
  assertions pass unchanged, confirming behaviour parity.

---

### M3 — Close Unit coverage gap to ≥95% lines

**Goal.** Bring Unit line coverage from 86.35% to ≥95% (current gaps: value-object `equals()`, missing
`with*` builder tests, `ObservedRepository` at 26.9% methods). This is mostly additive tests — no source
change except where a test reveals a real bug (then fix in the same milestone).

**Tasks**

| ID | File | Action | Verification | Risk |
|---|---|---|---|---|
| M3.1 | `tests/Unit/Domain/Value/*` (extend `ValueObjectTest.php`) | Add `equals()` cases for `CommitId`, `TagName`, `RemoteName` (and any other VO with `equals`); cover same-value, different-value, case sensitivity. | `composer coverage` then inspect `var/coverage/index.html` for `CommitId`/`TagName`/`RemoteName` | Low |
| M3.2 | `tests/Unit/Domain/Option/CloneOptionsTest.php` | Add the missing `with*` test (4/5 → 5/5). | `composer coverage` | Low |
| M3.3 | `tests/Unit/Domain/Option/CommitOptionsTest.php` | Add the missing `with*` test (5/6 → 6/6). | `composer coverage` | Low |
| M3.4 | `tests/Unit/Application/ObservedRepositoryTest.php` | Add a data-driven test that drives every `ObservedRepository` method (`getPath`, `getLastCommit`, `getCommit`, `branch`/`tag`/`remote` ops, `fetch`, `pull`, `push`, `stage`, `execute`) over a `FakeRepository` (test double implementing `CommitPort`+`BranchPort`+`TagPort`+`RemotePort`+`WorkingTreeProvider` that records calls). Assert `onStart`/`onSuccess`/`onFailure` per operation. | `composer coverage` (`ObservedRepository` methods ≥95%) | Low |
| M3.5 | `tests/Support/*` (new `FakeRepository` / `RecordingRepository`) | Provide the test double used by M3.4 (kept in `tests/`, not shipped). | `composer test:unit` | Low |
| M3.6 | `tests/Unit/Adapter/Git/GitAdapterMemoryTrait.php` | Replace the single `else` with early-return/guard (style + test hygiene). | `composer cs:check` | Low |

**Done criteria.** `composer coverage` reports Unit lines ≥95%, methods ≥95%, classes ≥95%. No `@covers`
leakage introduced. `composer quality` green.

> If any test in M3.4/M3.1 reveals a genuine bug, fix it in this milestone and note it in the PR
> (does not change the refactor scope but is required for correctness).

---

### M4 — Fix coverage job + enforce mutation testing

**Goal.** The merged Unit+Functional coverage cannot currently be reported because `codecept run` (all
suites) errors in the **Acceptance** suite on `codecoverage.serialized`. Also `mutation` is configured
but not in `composer quality` and uses only Unit with low thresholds (70/75).

**Tasks**

| ID | File | Action | Verification | Risk |
|---|---|---|---|---|
| M4.1 | `composer.json` | Add script `coverage:all`: `vendor/bin/codecept run Unit Functional --coverage --coverage-xml --coverage-html`. Keep `coverage` = Unit only (fast default). | `composer coverage:all` succeeds (no Acceptance serialization error) | Low |
| M4.2 | `composer.json` | Add `quality:full` = `validate` → `cs:check` → `analyse` → `architecture` → `coverage:all` → `mutation` → `security:audit`. Keep `quality` fast (no mutation). | `composer quality:full` green | Low |
| M4.3 | `infection.json5` | Raise `minMsi` → 90 and `minCoveredMsi` → 95 (align with the 95% bar). Keep `@default` mutators; `threads=2`. | `composer mutation` passes thresholds | Low |
| M4.4 | `infection.json5` (optional) | Consider `--test-framework-extra-args="Unit Functional"` so mutation also exercises adapter mapping covered only by Functional. If Codeception/Infection cannot accept two suites, leave Unit (document why). | `composer mutation` | Low |
| M4.5 | CI config (if present) | Run `composer quality:full` in CI (or `quality` + `coverage:all` + `mutation`). | CI green | Low |

**Done criteria.** `composer coverage:all` reports a **merged** Unit+Functional percentage; `composer
quality:full` green; mutation thresholds ≥90/95. `composer quality` (fast) still green.

---

### M5 — Declare `readonly class` on eligible types

**Goal.** Apply `readonly class` to every type whose properties are all `readonly` (behaviour-neutral
hardening). Per REVIEW §4, `GitPhpRepository` is **excluded** (its `$workingTree` memo at
`src/Adapter/Git/GitPhpRepository.php:34` is mutable by design).

**Tasks**

| ID | File | Action | Verification | Risk |
|---|---|---|---|---|
| M5.1 | Value objects `src/Domain/Value/*` | Add `readonly` to class declarations (`RepositoryPath`, `Commit`, `CommitId`, `BranchName`, `TagName`, `RemoteName`, `CommitMessage`, `WorktreeInfo`, …). | `composer analyse` | Low |
| M5.2 | Option builders `src/Domain/Option/*` | Add `readonly` (`InitOptions`, `CloneOptions`, `CommitOptions`). | `composer analyse` | Low |
| M5.3 | `src/Application/ObservedRepository.php`, `ObservedWorkingTree.php`, `RepositoryOpener.php` | Add `readonly`. | `composer analyse` | Low |
| M5.4 | `src/Adapter/Git/GitPhpRepositoryFactory.php`, `GitPhpWorkingTree.php`, `GitExceptionMap.php`, `GitExceptionRunner.php`, `LocalePinningRunner.php` | Add `readonly`. | `composer analyse` | Low |
| M5.5 | `src/Contract/NullRepositoryObserver.php` and any other fully-readonly concrete | Add `readonly`. | `composer analyse` | Low |
| M5.6 | `src/Adapter/Git/GitPhpRepository.php` | **Leave as-is** (working-tree memo). Add a one-line comment noting the deliberate exclusion. | `composer analyse` | Low |

**Done criteria.** `phpstan` reports no new error; `composer quality` green. `GitPhpRepository` remains
non-`readonly` with an explanatory comment.

---

### M6 — Enforce `line_length` + import FQNs + declaration order

**Goal.** Close the style partials: add the missing `line_length` rule, import FQN `\InvalidArgumentException`
usages, fix declaration order in the two adapters.

**Tasks**

| ID | File | Action | Verification | Risk |
|---|---|---|---|---|
| M6.1 | `.php-cs-fixer.php` | Add `'line_length' => ['length' => 120]` to the rules array. | `composer cs:check` now flags >120 lines | Low |
| M6.2 | All `src` files with FQN `\InvalidArgumentException` | Import `InvalidArgumentException` at top; replace FQN usages. Affected: `GitPhpRepository.php:268,290`, `GitPhpWorkingTree.php:132,144`, `RepositoryPath.php:29,33`, `CommitId.php:27`, `ObservedWorkingTree.php` (`WorktreeInfo` docblock `:34`). | `composer cs:fix` then `composer analyse` | Low |
| M6.3 | `src/Adapter/Git/GitPhpRepository.php` | Reorder: move `private readonly ?WorkingTree $workingTree = null;` *above* the constructor (`:34` → before `:30`); move private `assertWithinWorkingTree` (`:262`) and `canonicalizePath` (`:298`) *after* the public `execute` (`:326`). | `composer cs:check`, `composer analyse` | Low |
| M6.4 | `src/Adapter/Git/GitPhpWorkingTree.php` | Move private non-static `assertWithinRepositoryScope` (`:124`) *before* private static `parseWorktreeList` (`:72`); keep static-after-non-static ordering. | `composer cs:check` | Low |
| M6.5 | `src/SDK/CommitIt.php` (`:29` 131, `:34` 161, `:44` 176), `ObservedRepository.php:95` (122), `InvalidReferenceNameException.php:16` (121), `InvalidCommitMessageException.php:15` (121) | Wrap signatures/messages to ≤120 chars (multi-line the `SDK\CommitIt` constructor/method signatures). | `composer cs:check` reports 0 >120 | Low |

**Done criteria.** `composer cs:check` shows 0 files to fix and 0 lines >120 in `src`. `composer quality`
green.

---

### M7 — Magic literals → const/enum + `Commit::from`

**Goal.** Replace observer operation-name magic strings and adapter literals with a typed vocabulary; add
a `Commit` named constructor for mapper consistency.

**Tasks**

| ID | File | Action | Verification | Risk |
|---|---|---|---|---|
| M7.1 | `src/Contract/RepositoryOperation.php` (new `enum`) | Define `enum RepositoryOperation: string` with cases `GetPath`, `Stage`, `Unstage`, `Commit`, `GetLastCommit`, `GetCommit`, `Branch`, `Tag`, `Remote`, `Fetch`, `Pull`, `Push`, `GetWorkingTree`, `Execute` (mirror the strings used in `ObservedRepository`/`ObservedWorkingTree`). | `composer architecture` (Contract → Common/Domain only) | Low |
| M7.2 | `src/Application/ObservedRepository.php`, `ObservedWorkingTree.php` | Replace magic operation strings with `RepositoryOperation::*` cases; map to the string passed to the observer via `->value`. | `composer test` (observer tests still pass) | Low |
| M7.3 | `src/Adapter/Git/LocalePinningRunner.php` | Add `private const LOCALE = 'C';` and use it for `LC_ALL`/`LANG` (replaces the `'C'` literal). | `composer analyse` | Low |
| M7.4 | `src/Adapter/Git/GitPhpRepository.php` | Add `private const FETCH_REF = '(fetch)';` (`:195`). | `composer analyse` | Low |
| M7.5 | `src/Adapter/Git/GitPhpWorkingTree.php` | Add `private const WORKTREE_PREFIX = 'worktree ';` and `private const BRANCH_PREFIX = 'branch ';`; replace `strlen('worktree ')` (`:95`) and `strlen('branch ')` (`:104`). | `composer analyse`, `composer test:functional` | Low |
| M7.6 | `src/Domain/Value/Commit.php` | Add `public static function from(...): self` named constructor (mirror the other VOs' `private __construct` + named factory). | `composer analyse` | Low |
| M7.7 | `src/Adapter/Git/GitPhpRepository.php` | Use `Commit::from(...)` in `mapCommit` (`:343`) instead of `new Commit(...)`. | `composer test:functional` | Low |

**Done criteria.** No bare observer-name / locale / `'(fetch)'` / `worktree `/`branch ` literals remain
(verify with grep). `Commit` mapper uses the named constructor. `composer quality` green.

---

### M8 — Interface-backing reconciliation (decision, not code)

**Goal.** Resolve the open question from REVIEW §12: does the stricter "every class backed by interface"
rule override the `AGENTS.md` §5 carve-out? This milestone is a **documentation/ADR decision only**; it
may produce zero source changes or a small set of new interfaces.

**Tasks**

| ID | File | Action | Verification | Risk |
|---|---|---|---|---|
| M8.1 | `docs/adr/` (new ADR) | Record the decision: either (a) affirm §5 carve-out and document `SDK\CommitIt` + `RepositoryOpener` + validators as intentional non-backed facades/seams, or (b) add `CommitIt`/`RepositoryOpener` interfaces. | ADR reviewed | Low |
| M8.2 | `AGENTS.md` §5 | If (a): add one sentence enumerating the intentional exceptions. If (b): add the interfaces and back the facades. | `composer architecture` (no new violation) | Low |
| M8.3 | `docs/REVIEW.md` §5 / §12 | Mark the open question resolved. | — | Low |

**Done criteria.** The §12 open question is closed and reflected in `AGENTS.md`/ADR. Either no source
change or a reviewed, minimal interface addition.

## 3. Execution order & dependency graph

```
M1 (security)  ─┐
M2 (DRY)       ─┤
M5 (readonly)  ─┤  (independent, can run in parallel branches)
M6 (style)     ─┤
M7 (literals)  ─┘
        │
        ├──► M3 (coverage)  depends on M2 ideally (trait in place before testing dispatch)
        │        │
        │        └──► M4 (coverage job + mutation) depends on M3 (thresholds meaningful only at ≥95%)
        │
        └──► M8 (decision) independent; schedule anytime
```

Recommended merge sequence for a single linear reviewer: **M1 → M6 → M7 → M5 → M2 → M3 → M4 → M8**.
(M1 first because it is security; M6/M7/M5 are cheap style/hardening that unblock clean diffs; M2 then
M3/M4 build on the trait; M8 last as a doc decision.)

## 4. Verification command reference

| Need | Command |
|---|---|
| Full fast gate | `composer quality` |
| Full strict gate (CI) | `composer quality:full` (added in M4) |
| Unit coverage % | `composer coverage` → open `var/coverage/index.html` |
| Merged Unit+Functional coverage % | `composer coverage:all` (added in M4) |
| Mutation | `composer mutation` |
| Style check (dry-run) | `composer cs:check` |
| Style auto-fix | `composer cs:fix` |
| Static analysis | `composer analyse` |
| Architecture (DepTrac) | `composer architecture` |
| Unit tests | `composer test:unit` |
| Functional tests | `composer test:functional` |
| Security audit | `composer security:audit` |

## 5. Per-milestone acceptance checklist

Every milestone PR must satisfy **all** of:

- [ ] `composer quality` is green (M4 adds: `coverage:all` + `mutation` also green for M4).
- [ ] No new PHPStan error, no new DepTrac violation, no new CS diff.
- [ ] Tests added/changed follow TDD (failing test first for behaviour changes).
- [ ] `@covers` annotations preserved (no coverage leakage).
- [ ] Public API / exception taxonomy / adapter mapping unchanged unless the PR is M7/M8 and explicitly notes it.
- [ ] `docs/REVIEW.md` §11/§12 updated if a recommendation is resolved (cross-link the milestone).

## 6. Safety & rollback notes

- **No semantic changes** in M2/M5/M6 — these are pure refactors; if `composer test` stays green, the
  behaviour is preserved. Rollback = revert the single file/branch.
- **M1** changes how locale reaches git. Risk is low because the value (`C`) is unchanged; only the
  delivery channel (env vs global) changes. Verify `composer test:functional` (real repos) passes.
- **M3/M4** are test/config only — zero production-behaviour risk; rollback is safe.
- **M7** introduces a new `enum` in `Contract`; DepTrac must still show `Contract → Common, Domain`.
  Confirm with `composer architecture` before merge.
- **M8** may add interfaces; if it does, ensure no third-party implementer is broken (pre-1.0.0, BC is
  documented, not guaranteed — see `AGENTS.md` Backward Compatibility).
