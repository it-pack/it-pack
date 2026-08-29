# CommitIt — In-Depth Review

> Review of the `it-pack/commit-it` package (working dir `src/CommitIt`).
> Prior review docs moved to `docs/archive/`. This document supersedes them.
> Quality gates at time of review: PHPStan `level: max` (0 errors), DepTrac (0 violations / 0 skips),
> PHP-CS-Fixer `@PHP84Migration` (0 files to fix), tests 145 ✔ (396 assertions).

## Review checklist

The review evaluates the package across the following conceptual dimensions:

- **Architecture & boundaries** — hexagonal layering, ports/adapters, factories, dependency direction, and whether the domain stays vendor-neutral.
- **Design principles** — SOLID, DRY, absence of abstract classes, trait/composition preference, and interface-backing of concrete types.
- **Modern PHP leverage** — where `readonly`, property hooks/virtual properties, traits, enums, weak maps/refs, lazy objects, or fibers would raise safety or clarity.
- **Code-style contract** — declaration order, no `else`/`elseif`, no Yoda conditions, top-level imports, ≤120-char lines, one type per file, constants/enums over magic literals, and vocabularies living in interfaces.
- **Cross-cutting quality** — security (injection, path scope, secret handling), performance (memoization, subprocess cost), and DX (API clarity, documentation).
- **Test assurance** — line/method/class coverage against the ≥95% target, coverage-leakage discipline, and mutation testing in the gate.

## 1. Scope, method & assumptions

- **Scope.** This review covers the `CommitIt` package only. Sibling `it-pack` packages
  (`CommandIt`, `ConfigureIt`, `DeriveIt`, `RunIt`, …) are out of scope; their working-tree
  changes were visible in `git status` but not examined.
- **Method.** Every `src/**` PHP file was read in full. Style checks were enforced with grep/awk
  over `src` and `tests`. Coverage was produced with `pcov` via Codeception.
- **Assumptions.**
  - The committed tree is internally consistent (the port-split and `@internal` adapter helpers
    previously created in the working tree are now committed).
  - "Every class backed by an interface" is read together with the project's own documented
    carve-out in `AGENTS.md` §5 / `docs/REVIEW.md` §5 (value objects, option builders, and
    `@internal` adapter helpers are deliberately *not* interface-backed). Where the two conflict,
    this is called out explicitly rather than silently overridden.
  - Mutation testing was reviewed at the configuration level; a full `infection` run was not
    executed in this pass (long runtime, `threads=2`).

## 2. Architecture — hexagonal, ports, adapters, factories

**Strengths (Pass).**

- Layer separation is enforced by `deptrac.yaml` and verified clean: `Common → (nothing)`,
  `Contract → Common, Domain`, `Domain → Common, Contract`, `Application → Common, Contract,
  Domain`, `SDK → Common, Contract, Domain, Application`, `Adapter → Common, Contract, Domain`.
  No skips, no violations.
- The `Repository` surface is intentionally split into focused ports — `CommitPort`, `BranchPort`,
  `TagPort`, `RemotePort` (`src/Contract/Repository.php:30`) — so new capability arrives as a new
  port composed into the union, not as edits to a god-interface. This is a clean ISP/OCP result
  (ADR-006).
- Adapters are isolated under `src/Adapter/Git/`; `czproject/git-php` never leaks into Domain or
  Application. `GitExceptionMap` preserves the original `GitException` as `previous` everywhere
  (security/debugging requirement satisfied).
- Factories are injected: `GitPhpRepositoryFactory` accepts `?IRunner` (`src/Adapter/Git/GitPhpRepositoryFactory.php:32`),
  `RepositoryOpener` accepts a `RepositoryFactory`, and `SDK\CommitIt` receives the factory as an
  explicit argument (no hidden global wiring).
- The `ObservedRepository` / `ObservedWorkingTree` decorators keep observation fully optional and
  decoupled; the core never depends on a concrete observer (F2).

**Findings (low severity).**

- `ObservedRepository::getWorkingTree()` constructs `new ObservedWorkingTree(...)` inline
  (`src/Application/ObservedRepository.php:170`). This is the decorator wrapping its collaborator,
  but it technically violates the "new instances only in factories/builders" rule. Acceptable as a
  decorator idiom, but if the rule is to be strict, the wrapping belongs in `RepositoryOpener`
  (which already owns the observer).
- Value objects (`Commit`, `RepositoryPath`, `BranchName`, …) are instantiated with `new` inside
  adapter mappers (`src/Adapter/Git/GitPhpRepository.php:343` `mapCommit`, `:139` `BranchName::from`
  is used, but `new Commit(...)` at `:343` bypasses a named constructor). For consistency with the
  other value objects (all use `private __construct` + named factory), `Commit` should expose a
  `Commit::from(...)` named constructor and mappers should use it. Low priority.

## 3. SOLID & DRY

**S — Single Responsibility.** `Repository` is split (see §2). `GitExceptionMap` has one job
(message→exception taxonomy). `LocalePinningRunner` has one job (locale pinning). Good.

**O — Open/Closed.** New operations = new port (ADR-006). New exception kinds = new marker interface
(ADR-007). Good.

**L — Liskov.** All implementers satisfy their contracts; `NullRepositoryObserver` is a safe
no-op subtype of `RepositoryObserver`. Good.

**I — Interface Segregation.** The four ports are narrow. Good.

**D — Dependency Inversion.** Application/SDK depend on `Contract` interfaces, never on
`czproject/git-php`. Good.

**DRY — one real duplication.** `ObservedRepository` and `ObservedWorkingTree` contain near-identical
`run()` / `runVoid()` / `notify()` observer-dispatch logic
(`src/Application/ObservedRepository.php:192-244`, `src/Application/ObservedWorkingTree.php:63-113`).
This is the strongest DRY finding and a direct fit for the project's "prefer traits" guidance.
Extract a focused `ObserverDispatch` trait (private `run`/`runVoid`/`notify`, no state) and have both
decorators use it. This also eliminates the duplicated PHPDoc block on `ObservedRepository::run`
(see §6).

## 4. Modern PHP feature leverage

| Feature | Status | Note |
|---|---|---|
| `readonly class` | **Partial** | Value objects, option builders, `ObservedRepository`, `ObservedWorkingTree`, `RepositoryOpener`, `GitPhpRepositoryFactory`, `NullRepositoryObserver`, `GitExceptionMap`, `GitExceptionRunner`, `LocalePinningRunner`, `GitPhpWorkingTree` are all eligible (every property is `readonly`) but not declared `readonly class`. `GitPhpRepository` is the only blocker — its `$workingTree` memoization cache is mutable (see F12). Adding `readonly class` to the eligible set is a safe, behaviour-neutral hardening. |
| Property hooks / virtual props | **Good** | `RepositoryPath::$realPath` (`src/Domain/Value/RepositoryPath.php:53`) is a lazy, cached virtual property — a good use. `CommitMessage::toString()` etc. remain methods; no change needed. |
| Traits | **Opportunity** | `ObserverDispatch` trait (see §3 DRY). Traits otherwise avoided appropriately. |
| Enums | **Good** | `RefKind` enum (`src/Domain/Validation/RefKind.php`) models the closed reference-kind set. Exception taxonomy uses marker interfaces (correct for an open exception hierarchy). |
| WeakMap / WeakReference | **N/A** | No cross-object caching requirement; the working-tree memo is a direct property, not a registry. |
| Lazy objects | **N/A** | `getWorkingTree()` memoization is a one-line property cache; a Lazy object would add indirection without benefit. |
| Fibers | **N/A** | No concurrency/async requirement in a VCS client. |

## 5. Class backing, abstract classes, traits

- **No abstract classes.** Verified — zero `abstract class` in `src`. Pass.
- **Interface backing.** Consistent with `AGENTS.md` §5 carve-out: value objects, option builders,
  and `@internal` adapter helpers (`GitExceptionMap`, `GitExceptionRunner`, `LocalePinningRunner`) are
  intentionally *not* interface-backed. All public ports/decorators/factories/exceptions are
  interface-backed.
- **Deviations not covered by the carve-out** (low priority, mostly YAGNI):
  - `SDK\CommitIt` — static facade, not interface-backed. Defensible as a thin entry point; if the
    "every class" rule is taken literally, back it with a `CommitIt` interface or steer consumers to
    `RepositoryOpener`.
  - `Application\RepositoryOpener` — wiring seam, not interface-backed. AGENTS says factories are
    interface-backed; `RepositoryOpener` is factory-like. Optional interface if consumers must swap
    it.
  - `Domain\Validation\CommitMessageValidator`, `RefNameValidator` — pure validators, not
    interface-backed. Not in the carve-out; an interface would be YAGNI today.

## 6. Code-style compliance matrix

| Rule (from brief) | Status | Evidence / action |
|---|---|---|
| One type per file (PSR-4) | **Pass** | Each `src`/`tests` file declares exactly one class/interface/trait/enum. |
| Declaration order (trait → const → props → ctor → factory → non-static → static; public before private) | **Partial** | `GitPhpRepository` declares `$workingTree` *after* the constructor (`src/Adapter/Git/GitPhpRepository.php:34` vs `:30`) and interleaves private `assertWithinWorkingTree`/`canonicalizePath` (`:262`,`:298`) *before* the public `execute` (`:326`). `GitPhpWorkingTree` places private static `parseWorktreeList` (`:72`) before private non-static `assertWithinRepositoryScope` (`:124`) — static before non-static. Reorder. |
| No `else` / `elseif` | **Pass (src)** | None in `src`. Only `tests/Unit/Adapter/Git/GitAdapterMemoryTrait.php` uses `else`; flag for tests. |
| No Yoda conditions | **Pass** | No `false === $x` / `null === $x` patterns found. |
| All referenced types imported at top | **Partial** | `\InvalidArgumentException` used as FQN instead of imported in `GitPhpRepository` (`:268`,`:290`), `GitPhpWorkingTree` (`:132`,`:144`), `RepositoryPath` (`:29`,`:33`), `CommitId` (`:27`); `ObservedWorkingTree` references `WorktreeInfo` only as an FQN docblock (`:34`) without importing it. Import these. `\Throwable` in `catch` is idiomatic FQN — low priority. |
| No line > 120 chars | **Partial** | `src`: 6 lines exceed 120 — `ObservedRepository.php:95` (122), `InvalidReferenceNameException.php:16` (121), `InvalidCommitMessageException.php:15` (121), `CommitIt.php:29` (131), `:34` (161), `:44` (176). `tests` (excl. `_generated`): 16 lines. `.php-cs-fixer.php` does **not** enable `line_length`, so this is not enforced. Recommend adding `'line_length' => ['length' => 120]`. Wrap the `src` lines (multi-line the `SDK\CommitIt` signatures). |
| Constants/enums over magic literals; vocabularies in interfaces | **Partial** | `CommitMessageValidator::MAX_SUBJECT_LENGTH` is a constant (good). But observer operation names (`'getPath'`, `'stage'`, `'commit'`, …) are magic strings in `ObservedRepository`/`ObservedWorkingTree`; git CLI flags/args are inline literals in the adapter; `'(fetch)'` (`GitPhpRepository.php:195`) and the `'C'` locale (`LocalePinningRunner`) are bare literals. Recommend: an `enum RepositoryOperation` in `Contract` for the observer vocabulary; `private const` for `'C'`, `'(fetch)'`, and the `strlen('worktree ')` / `strlen('branch ')` literals (`GitPhpWorkingTree.php:95`,`:104`). |
| Duplicated PHPDoc | **Fail (minor)** | `ObservedRepository::run` has two stacked `@template` docblocks (`src/Application/ObservedRepository.php:181-191`). Collapse to one (also removed by the §3 trait extraction). |

## 7. Security

**Strengths (Pass).**

- Path-scope invariants are enforced by CommitIt, not delegated to git: `assertWithinWorkingTree`
  (`GitPhpRepository.php:262`) rejects absolute paths and `..` traversals; `assertWithinRepositoryScope`
  (`GitPhpWorkingTree.php:124`) keeps worktrees inside the repository parent. Both canonicalize
  without touching the filesystem when the target does not exist yet.
- Observer context deliberately omits commit-message bodies and secrets (verified by
  `ObservedRepositoryTest::testSuccessContextOmitsMessageBody`).
- `GitExceptionMap` never logs; mapping is message-driven but preserves the original as `previous`.

**Findings (medium).**

- **`putenv` locale pinning is process-global and not thread-safe** (`src/Adapter/Git/LocalePinningRunner.php:39-47`).
  Under shared/parallel workers (the AGENTS security section explicitly mentions "shared workers
  cloning untrusted URLs"; `infection.json5` runs `threads=2`), two concurrent git invocations can
  interleave `putenv('LC_ALL=C')` and the restore, briefly unpinning the locale for a sibling
  process and making exception mapping locale-fragile. The `IRunner::run($cwd, $args, $env)` signature
  already receives an `$env` array; pin the locale by *merging* `LC_ALL=C`/`LANG=C` into `$env` and
  passing it down, leaving the global environment untouched. This removes the race and the global
  mutation entirely.
- `CommitPort::execute()` is a generic passthrough documented as NON-portable; it accepts raw
  command + args. This is an accepted escape hatch (documented), but consumers can run arbitrary git
  through a vendor-neutral handle. Keep the `@api` warning and consider a `@deprecated`-style note in
  consumer docs.

## 8. Performance

- `getWorkingTree()` is memoized (`GitPhpRepository.php:34`,`:253`) — good (F12). Note this is the sole
  source of mutability blocking `readonly class` (§4).
- `RepositoryPath::$realPath` is lazily cached via a property hook — good.
- `getLastCommit()` spawns two git subprocesses (`rev-parse --verify HEAD` then `getLastCommit`)
  (`GitPhpRepository.php:91-108`). Correctness-first; acceptable, but could be folded into one call if
  profiling shows it hot.
- `LocalePinningRunner` mutates/restores env on every call; the env-injection fix (§7) is also
  marginally cheaper.

## 9. Maintainability & DX

- API is clear: named constructors (`RepositoryPath::from`, `CommitMessage::fromParts`,
  `CommitId::from`), immutable builders (`InitOptions`/`CloneOptions`/`CommitOptions`), and a thin
  `SDK\CommitIt` facade. Documentation exists (`README.md`, `USER_GUIDE.md`, ADRs).
- The duplicate/stacked docblock (§6) and the unenforced `line_length` rule are the only maintainability
  nits.

## 10. Test coverage & mutation

**Coverage (pcov).**

| Suite | Lines | Methods | Classes |
|---|---|---|---|
| Unit (`composer coverage` target) | 86.35% | 72.66% | 50.00% |
| Functional (own run) | 67.47% | 42.45% | 16.67% |

- The project's `coverage` script measures **Unit only**, so adapter code exercised by the Functional
  suite (real git repos) is not counted toward the headline number. A combined Unit+Functional run
  could not be produced: `codecept run` (all suites) fails because the **Acceptance** suite errors on
  coverage serialization (`codecoverage.serialized does not exist`). Fix the coverage job to run
  `Unit Functional` (exclude Acceptance) so the true merged number is reported.
- **Target ≥95% is not met by Unit alone (86.35%).** Concrete Unit gaps to close:
  - Value-object `equals()` only tested for `BranchName` (`ValueObjectTest.php:78`); `CommitId`,
    `TagName`, `RemoteName` `equals()` are uncovered (CommitId 50%, TagName/RemoteName 75% lines).
  - Option builders each miss one `with*` test: `CloneOptions` (4/5), `CommitOptions` (5/6).
  - `ObservedRepository` Unit coverage is 26.9% methods / 46% lines — only `commit`, `hasChanges`,
    `stageAll`, `getWorkingTree` are exercised. The other ~19 methods (branch/tag/remote ops, `fetch`,
    `pull`, `push`, `getPath`, `getLastCommit`, `getCommit`, `stage`, `execute`) are not invoked in
    Unit. A data-driven test over `FakeRepository` would cover them cheaply (the Functional suite
    already covers them against real git, but Unit should too for the ≥95% gate).
- **Coverage leakage.** All 18 Unit test files carry `@covers` annotations (e.g.
  `ValueObjectTest.php:18-27`, `ObservedRepositoryTest.php:18`), so coverage is not mis-attributed to
  untested code. Good — this satisfies "tests do not leak coverage."

**Mutation testing.**

- `infection.json5` is configured (`@default` mutators, `minMsi: 70`, `minCoveredMsi: 75`) and wired to
  `composer mutation`. **But `mutation` is NOT part of `composer quality`** (which runs
  validate → cs:check → analyse → architecture → test → security:audit). So mutation is never enforced
  in the gate.
- Thresholds (70/75) are well below the 95% line-coverage target. Recommend: add `@mutation` to
  `composer quality` (or a CI stage) and raise `minMsi`/`minCoveredMsi` to ≥90/95 to match the coverage
  bar. Priority mutation targets per AGENTS: reference-name validation, commit-message validation,
  `GitExceptionMap` mapping, observer dispatch branching, path-scope checks.

## 11. Prioritized recommendations

1. **(Medium, security)** Replace `putenv`/`getenv` in `LocalePinningRunner` with `$env`-array injection
   of `LC_ALL=C`/`LANG=C`; drop the global mutation. Removes a concurrency race under parallel workers.
   **→ Implemented (M1).** `LocalePinningRunner` now forwards the full process env with `LC_ALL`/`LANG`
   pinned to `C` via the per-process `$env` array; no global `putenv`. Verified by `LocalePinningRunnerTest`
   (global env unchanged) and a green Functional suite.
 2. **(Medium, DRY/style)** Extract an `ObserverDispatch` trait (private `run`/`runVoid`/`notify`) used by
    both `ObservedRepository` and `ObservedWorkingTree`; delete the duplicated docblock.
    **→ Implemented (M2).** `src/Application/ObserverDispatch.php` (`@internal` trait, observer passed in)
    now holds `dispatchRun`/`dispatchRunVoid`/`dispatchNotify`; both decorators `use` it and forward
    `$this->observer`. The stacked docblock is gone. Behaviour parity confirmed by the existing
    `ObservedRepositoryTest`/`ObservedWorkingTreeTest` onStart/onSuccess/onFailure assertions.
 3. **(Medium, coverage)** Close the Unit gaps: value-object `equals()` for all types, the missing
    `with*` builders, and a data-driven `ObservedRepository` test over `FakeRepository`. Re-measure;
    target ≥95% lines.
    **→ Implemented (M3).** Unit coverage: lines 98.54%, methods 97.78%, classes 96.00% (all ≥95%).
    `ObservedRepositoryTest` is data-driven over `FakeRepository` (`observedOperationProvider`) covering every
    operation's `onStart`/`onSuccess`/`onFailure` path; `OptionBuilderTest` covers the missing `with*` builders;
    `ValueObjectTest` covers `equals()` same/different/case-sensitivity. Adapter Unit gaps were also closed via
    `MemoryRunner`-backed cases (factory + working tree at 100% methods/lines; repository at 130/137 lines).
4. **(Medium, coverage gate)** Make the coverage job run `Unit Functional` (skip Acceptance) and add
   `@mutation` to `composer quality`; raise infection `minMsi`/`minCoveredMsi` to ≥90/95.
   **→ Implemented (M4).** New `composer coverage:all` runs `codecept run Unit,Functional` (merged
   Unit+Functional report, avoids the old `Acceptance` `codecoverage.serialized` error). New
   `composer quality:full` chains `validate → cs:check → analyse → architecture → coverage:all → mutation`
   (the fast `quality` stays mutation-free). `infection.json5` thresholds set to `minMsi` = 90 /
   `minCoveredMsi` = 90; the plan's aspirational **95 covered** is unreachable because the residual escaped
   mutants are equivalent (see REFACTORING_PLAN.md M4 notes: `GitExceptionMap` `VcsOperationException`
   fall-through, `RefNameValidator` `\s` subsumption, `(string)`/`?? []` no-ops). Hardening raised the
   covered mutation score from **77.6% to 91.7%** (overall MSI 90.2%).
5. **(Low, modern PHP)** Declare `readonly class` on the eligible types (value objects, option builders,
   decorators, `RepositoryOpener`, `GitPhpRepositoryFactory`, the `@internal` helpers, `GitPhpWorkingTree`,
   `NullRepositoryObserver`). `GitPhpRepository` stays mutable by its working-tree memo (intentional).
   **→ Implemented (M5).** `readonly class` added to 21 concrete types in `Domain\Value`, `Domain\Option`,
   `Application`, `Adapter\Git`, and `Contract`. Three adapter classes (`GitPhpRepositoryFactory::$git`,
   `GitPhpWorkingTree::$repository`, `LocalePinningRunner::$inner`) first had their write-once constructor
   property promoted to `readonly` so the class could become `readonly`. Deliberate exclusions with comments:
   `GitPhpRepository` (lazy `$workingTree` memo) and `RepositoryPath` (lazy `realPath` get-hook cache).
   `ObserverDispatch` is a stateless trait so `readonly` does not apply. `phpstan` clean, `composer
   quality:full` green (228 tests, Mutation covered MSI 91%). See REFACTORING_PLAN.md M5 notes.
6. **(Low, style)** Add `'line_length' => ['length' => 120]` to `.php-cs-fixer.php`; wrap the 6 `src`
   lines >120 (notably the `SDK\CommitIt` signatures). Import `\InvalidArgumentException` where used as
   FQN; import `WorktreeInfo` in `ObservedWorkingTree`.
7. **(Low, declaration order)** Reorder `GitPhpRepository` (property before ctor; private methods after
   all public) and `GitPhpWorkingTree` (non-static private before static private).
8. **(Low, magic literals)** Introduce `enum RepositoryOperation` in `Contract` for observer operation
   names; add `private const` for the `'C'` locale, `'(fetch)'`, and the `worktree `/`branch ` prefixes.
9. **(Low, consistency)** Add `Commit::from(...)` named constructor; use it in `GitPhpRepository::mapCommit`.
10. **(Low, rule reconciliation)** Confirm whether `SDK\CommitIt` and `RepositoryOpener` should be
    interface-backed, or document them as intentional facades/seams outside the §5 carve-out.

## 12. Assumptions & open questions

- **Open question (rule vs. carve-out).** The brief requires "every class fully backed by an interface,"
  which conflicts with the project's documented §5 carve-out (value objects, option builders, `@internal`
  helpers are intentionally not interface-backed). This review treats the carve-out as the project
  standard and flags only the *uncarved* deviations (facade, seam, validators). Confirm whether the
  stricter "every class" rule should override §5.
- **Open question (coverage job).** The Acceptance suite blocks a merged coverage run. Is excluding
  Acceptance from coverage acceptable, or should the Acceptance bootstrap be fixed so `Unit Functional`
  reports the true merged percentage?
- No source files were modified during this review; all findings above are documentation/recommendation
  only.
