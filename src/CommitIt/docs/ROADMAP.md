# CommitIt — Implementation Roadmap

> Companion to [`docs/IMPLEMENTATION_PLAN.md`](docs/IMPLEMENTATION_PLAN.md) and
> [`AGENTS.md`](AGENTS.md).
> This document breaks the planned architecture into **ordered milestones and
> concrete tasks** with acceptance criteria and test expectations. It is the
> authoritative "what to build next" checklist for `it-pack/commit-it`.

---

## How to use this roadmap

- Milestones are **strictly ordered** by dependency: a later milestone may not
  start until the acceptance criteria of the earlier one pass (green checks).
- Within a milestone, tasks may be parallelized, but each task ends with a
  runnable/verifiable unit.
- Every code task must obey the rules in `AGENTS.md`:
  `final` classes, `readonly` where possible, strict types, hexagonal dependency
  direction (enforced by `deptrac.yaml`), PHPStan `level: max`, PHP-CS-Fixer
  `@PHP84Migration`.
- Mark a task done only when its listed tests exist and pass.

---

## Milestone M0 — Foundation & QA Wiring ✅ COMPLETE

**Goal:** A buildable package with green tooling scripts so every later milestone
can be verified immediately.

> **Status (2026-08-21):** Implemented and verified. All gates green:
> `composer validate --strict` (valid), `phpstan analyse` (no errors, level max),
> `deptrac` (0 violations), `php-cs-fixer fix --dry-run` (0 files),
> `codecept run Unit` (2 tests, 5 assertions), `composer audit` (no advisories).

### Tasks

1. **Verify and wire `composer.json` scripts.** ✅
   - Added scripts: `validate`, `test`, `test:unit`, `test:functional`,
     `coverage`, `cs:check`, `cs:fix`, `analyse`, `architecture`, `mutation`,
     `security:audit`, `quality`.
   - Added `php: ^8.4`, `license: MIT`, `autoload-dev` (`Tests\` → `tests/`),
     `prefer-stable: true`. `composer.lock` refreshed via `update --lock` (no
     dependency change).
   - Confirmed `czproject/git-php:^4.6` is in `require`.
   - File: `composer.json`.

2. **Confirm static-analysis baseline is clean.** ✅
   - **Defect fixed:** `phpstan.neon` still `include`d RunIt's
     `tools/phpstan/runit-rules/extension.neon` (a copy artifact). That path does
     not exist here and would couple CommitIt to RunIt (forbidden by `AGENTS.md`).
     Removed the include; `phpstan` now loads and reports no errors at `level: max`.
   - `deptrac.yaml` already present and green (0 violations); paths cover
     `src/Common`, `src/Contract`, `src/Domain`, `src/Application`, `src/SDK`,
     `src/Adapter`.

3. **Establish test directory layout for Codeception.** ✅
   - Suites already scaffolded (`Unit`, `Functional`, `Acceptance`).
   - Created `tests/Support/Fixture/` (with `.gitkeep`) and `tests/Support/Helper/`.
   - `phpstan.neon` paths include `./tests/Unit` and `./tests/Functional`.

4. **Create a temp-repository test helper.** ✅
   - `tests/Support/Helper/GitRepoFactory.php` — `final` helper, `proc_open` with
     an argument vector (no shell, no `sudo`): `initBareRepo`, `initWorkingRepo`,
     `seedFile`, `cloneRepo`, `tearDown`, `runGit`, `createTempDir`.
   - Smoke test `tests/Unit/Support/GitRepoFactoryTest.php` proves create/seed/
     tear-down and bare-repo clone.

### Acceptance criteria — all met

- `composer validate` passes (valid, strict).
- `composer analyse`, `composer architecture`, `composer coding-style --dry-run`
  run without errors.
- `composer test` discovers the suites without failure (Unit passes; Functional
  discovers 0 tests, as expected pre-M3).
- A `GitRepoFactory` helper exists and can create/tear down a temp repo in a unit
  test.

---

## Milestone M1 — Common & Contract (Ports) ✅ COMPLETE

**Goal:** Define the vendor-neutral surface and the exception taxonomy. No
behavioral code — interfaces, value-object skeletons (required by the port
signatures), and the exception taxonomy.

> **Status (2026-08-21):** Implemented and verified. Gates green: `phpstan
> analyse` (level max, 0 errors), `deptrac` (0 violations), `php-cs-fixer` (0
> files), `codecept run Unit` (14 tests, 39 assertions).

### Tasks

1. **`Common` base exception.** ✅
   - `src/Common/CommitItException.php` — marker interface extending `\Throwable`.

2. **Contract exception taxonomy (markers).** ✅
   - `src/Contract/Exception/VcsOperationException.php` (base marker).
   - `src/Contract/Exception/RepositoryNotFoundException.php`.
   - `src/Contract/Exception/InvalidReferenceNameException.php`.
   - `src/Contract/Exception/DirtyWorkingTreeException.php`.
   - Each extends `CommitItException`; concrete throwables land in `Domain` (M2)
     implementing these markers.

3. **Primary port: `Repository`.** ✅
   - `src/Contract/Repository.php` — `getPath()`, `hasChanges()`, `stage()`,
     `stageAll()`, `commit()`, `getLastCommit()`, `getCommit()`, branch/tag/remote
     ops, `fetch/pull/push`, `getWorkingTree()`, `execute()`.

4. **`WorkingTree` port.** ✅
   - `src/Contract/WorkingTree.php` — `listWorktrees()`, `add()`, `remove()`.
   - Method renamed from `list()` (PHP reserved word).

5. **`RepositoryFactory` port.** ✅
   - `src/Contract/RepositoryFactory.php` — `open()`, `init()`,
     `cloneRepository()`.
   - Method renamed from `clone()` (PHP reserved word).

6. **`RepositoryObserver` port (RunIt-ready).** ✅
   - `src/Contract/RepositoryObserver.php` — `onStart()`, `onSuccess()`,
     `onFailure()`, with structured `context` arrays so the vocabulary maps to
     RunIt's Activity/Observation/Occurrence triad without coupling.
   - `src/Contract/NullRepositoryObserver.php` — no-op default.
     **Placed in `Contract` (not `Common`):** `Common` depends on nothing
     (`Common: ~` in deptrac), so a `Common` class cannot implement a `Contract`
     interface. The no-op observer lives beside the port it satisfies.

### Prerequisite value-object & option skeletons (built here so ports compile)

The plan's sequencing note anticipated this: M1 ports reference domain value
objects, so the skeletons were created now (full validation + dedicated validators
are Milestone M2).

- `src/Domain/Value/`: `RepositoryPath`, `CommitId`, `BranchName`, `TagName`,
  `RemoteName`, `CommitMessage`, `Commit`, `WorktreeInfo` — `final readonly`
  holders with named constructors and basic non-empty guards.
- `src/Domain/Option/`: `InitOptions`, `CloneOptions`, `CommitOptions` — `final
  readonly` immutable fluent builders (`with*()` returns a new instance).

### Architecture decision recorded during M1

- **deptrac alignment:** the original `deptrac.yaml` only allowed
  `Contract → Common`, which forbade referencing Domain value objects in the
  ports. The approved plan/AGENTS layering is `Contract → Common, Domain` (with
  concrete exceptions in `Domain` implementing `Contract` markers, hence the
  intended `Contract ↔ Domain` cycle). `deptrac.yaml` was updated to match the
  documented architecture (allowed edges: Common=0, Contract=Common+Domain,
  Domain=Common+Contract, Application/SDK/Adapter as planned).
- **Dead-code detector:** `shipmonk/dead-code-detector` (auto-loaded by
  `phpstan/extension-installer`) flags any symbol unreferenced in the analyzed
  paths. Since M1 is a forward-declared public API not yet consumed, the intended
  public surface is marked `@api` (the detector's documented escape hatch for
  intentional public API) rather than suppressed. Value objects/ports are thus
  annotated `@api`; consumers in M3/M4 will reference them directly.

### Acceptance criteria — all met

- DepTrac passes: `Contract` depends on `Common` + `Domain`; `Domain` depends on
  `Common` + `Contract` (per the plan's approved layering).
- PHPStan `level: max` passes across `src/` and `tests/`.
- Every public interface has a PHPDoc describing its responsibility and the
  non-portable `execute()` caveat.
- Unit tests cover value-object construction/equality/round-trip, option-builder
  immutability, and the no-op observer.

---

## Milestone M2 — Domain: Value Objects & Validators

**Goal:** Immutable, validated, framework-free domain primitives. These are the
highest-value mutation-testing targets.

### Tasks

> **Note:** The value-object *classes* (`RepositoryPath`, `CommitId`, `BranchName`,
> `TagName`, `RemoteName`, `CommitMessage`, `Commit`, `WorktreeInfo`) were already
> created as `final readonly` skeletons in **M1** so the port signatures could
> compile. M2 hardens them: add full validation (ref-name rules, commit-message
> rules, commit-id format, path safety) and the dedicated validators below. The
> `@api` annotations added in M1 can stay (these remain public API).

1. **`RepositoryPath`.**
   - `src/Domain/Value/RepositoryPath.php` — `final readonly`, named constructor
     `from(string $path)`, property hook for resolved realpath, validation
     (non-empty, path-safe). Must not perform I/O beyond resolution; resolution
     may be deferred/optional to keep the object pure.

2. **`CommitId`.**
   - `src/Domain/Value/CommitId.php` — opaque validated string. Git format
     (40-hex) enforced by the adapter; domain keeps it format-agnostic but
     validates non-empty. Provide `equals()` and `__toString()`.

3. **`BranchName` / `TagName` / `RemoteName`.**
   - `src/Domain/Value/{BranchName,TagName,RemoteName}.php` — extensible validated
     strings via a shared `RefName` concept or per-type validation. Reject empty,
     whitespace, and `..` sequences; branch/tag rules differ slightly (tag allows
     `/`). `final readonly`.

4. **`CommitMessage`.**
   - `src/Domain/Value/CommitMessage.php` — `subject` + optional `body`.
     Named constructors `fromParts(string $subject, ?string $body = null)` and
     `fromString(string $message)`. Validation via `CommitMessageValidator`.

5. **`Commit`.**
   - `src/Domain/Value/Commit.php` — `final readonly` immutable snapshot:
     `id: CommitId`, `subject`, `body`, `authorName`, `authorEmail`,
     `authorDate:\DateTimeImmutable`, `committerName`, `committerEmail`,
     `committerDate:\DateTimeImmutable`.

6. **Validators.**
   - `src/Domain/Validation/RefNameValidator.php` — pure, tests branch/tag/remote
     naming; throws/returns violation set. Prefer throwing
     `InvalidReferenceNameException` on invalid input at the boundary, or return a
     result object for testability.
   - `src/Domain/Validation/CommitMessageValidator.php` — non-empty subject,
     trimmed, no trailing newline/whitespace, subject length guard.

7. **Domain exceptions (concrete throwables).**
   - Implement the contract exceptions from M1 in `src/Domain/Exception/` where
     they carry domain meaning (e.g. `InvalidReferenceNameException` thrown by the
     validator). They must implement the `Contract\Exception\*` markers.

### Acceptance criteria

- Unit tests for each value object: valid construction, rejection of invalid
  input, immutability (no setters / `readonly`), equality semantics.
- Validator tests cover valid + invalid branches/tags/remotes and commit messages
  (property-based fuzzing for ref names is encouraged).
- PHPStan max and DepTrac green (`Domain → Common, Contract` only).
- Mutation testing on validators reaches the project threshold (see M8).

---

## Milestone M3 — Git Adapter

**Goal:** A working, testable git implementation of the contracts using
`czproject/git-php`, including the differentiating worktree support and error
mapping.

### Tasks

1. **`GitExceptionMap`.**
   - `src/Adapter/Git/GitExceptionMap.php` — maps
     `CzProject\GitPhp\GitException` → the appropriate
     `Contract\Exception\*` type, preserving the original as `previous`.
   - Mappings: missing `.git` → `RepositoryNotFoundException`; invalid ref →
     `InvalidReferenceNameException`; uncommitted changes blocking an op →
     `DirtyWorkingTreeException`; else `VcsOperationException`.

2. **`GitPhpRepositoryFactory`.**
   - `src/Adapter/Git/GitPhpRepositoryFactory.php` — `final`, implements
     `Contract\RepositoryFactory`. Builds a `czproject\GitPhp\Git` (optionally with
     an injected `IRunner` for tests), then returns `GitPhpRepository`. Implements
     `open()` (fail-fast if not a repo), `init()`, `clone()`.

3. **`GitPhpRepository`.**
   - `src/Adapter/Git/GitPhpRepository.php` — `final`, implements
     `Contract\Repository`. Wraps a `GitRepository`. Translates every contract
     method:
     - `stage/stageAll` → `addFile`/`addAllChanges`.
     - `commit(CommitMessage, ?CommitOptions)` → `commit()`; result mapped to
       `CommitId`.
     - `getLastCommit/getCommit` → map `czproject\GitPhp\Commit` to domain
       `Commit`.
     - branch/tag/remote ops → corresponding `GitRepository` methods, wrapping
       names in value objects.
     - `execute()` → delegates to the wrapped repo's `execute()` passthrough
       (documented as git-specific).
   - All calls wrapped so `GitException` is routed through `GitExceptionMap`.

4. **`GitPhpWorkingTree`.**
   - `src/Adapter/Git/GitPhpWorkingTree.php` — `final`, implements
     `Contract\WorkingTree`. Uses the wrapped repo's public `execute('worktree',
     ...)` passthrough (composition, **not** subclassing `GitRepository`):
     - `list()` → parse `git worktree list` output into value objects/arrays.
     - `add(RepositoryPath, ?BranchName)` → `git worktree add [-b branch] path`.
     - `remove(RepositoryPath)` → `git worktree remove path`.

5. **Adapter unit tests with `MemoryRunner`.**
   - `tests/Unit/Adapter/Git/` — use `czproject\GitPhp\MemoryRunner` to script
     command outputs and assert parsing/mapping without spawning real git. Cover:
     commit message → id mapping, `getCommit` mapping, worktree list parsing,
     and `GitExceptionMap` for each exception branch.

### Acceptance criteria

- `GitPhpRepositoryFactory::open()` on a non-repo throws
  `RepositoryNotFoundException`.
- `GitExceptionMap` unit tests cover every mapped exception type.
- Adapter unit tests pass deterministically via `MemoryRunner` (no real git).
- DepTrac green: adapter depends only on `Common`, `Contract`, `Domain`, and
  `czproject/git-php`.

---

## Milestone M4 — Application Layer

**Goal:** Compose the adapter into a usable service, add ergonomic option builders,
and wire non-intrusive observation.

### Tasks

1. **Option builders (immutable, fluent).**
   - `src/Application/Option/InitOptions.php`
   - `src/Application/Option/CloneOptions.php`
   - `src/Application/Option/CommitOptions.php`
   - Each `final readonly` with named constructors and fluent `with*()` methods
     returning new instances. No large untyped config arrays.

2. **`ObservedRepository` decorator.**
   - `src/Application/ObservedRepository.php` — `final`, implements
     `Contract\Repository`, wraps a `Repository`, and dispatches
     `RepositoryObserver::onStart/onSuccess/onFailure` around each operation.
   - Failure path must call `onFailure` and rethrow the original `Throwable`
     (never a substituted observer error). Observer failures are caught and routed
     to a separate, non-recursive path (do not re-observe).

3. **`RepositoryOpener`.**
   - `src/Application/RepositoryOpener.php` — `final`, composes the
     `RepositoryFactory` and an optional `RepositoryObserver`, returning either a
     plain `Repository` or an `ObservedRepository`. This is the wiring seam the SDK
     uses.

4. **Application tests.**
   - `tests/Unit/Application/` — `ObservedRepository` invokes the observer on
     every path (success + failure); `NullRepositoryObserver` is a true no-op;
     option builders are immutable (`with*()` returns a different instance).

### Acceptance criteria

- `ObservedRepository` observer dispatch verified for `onStart`/`onSuccess`/
  `onFailure`, including that the original exception propagates.
- Option builders verified immutable and fluent.
- DepTrac green: `Application → Common, Contract, Domain`.

---

## Milestone M5 — SDK / DX Facade

**Goal:** An ergonomic, optional programmatic entry point for consumers.

### Tasks

1. **`CommitIt` facade.**
   - `src/SDK/CommitIt.php` — `final`, static `open()`, `init()`, `clone()`
     delegating to `RepositoryOpener`. Returns `Contract\Repository`.
   - Keep the SDK thin: no hidden state, no global configuration.

2. **Value-object ergonomics (if needed).**
   - Ensure `CommitMessage::fromParts()`, `RepositoryPath::from()`,
     `BranchName`/`TagName`/`RemoteName` constructors are convenient for SDK users
     (already built in M2; verify DX).

3. **Optional fluent builder (only if a concrete consumer needs it).**
   - `src/SDK/Fluent/StageBuilder.php` — chain `stage()->commit()`; do **not**
     build speculatively. Skip if no consumer requires it.

4. **SDK tests.**
   - `tests/Unit/SDK/` — facade returns a working repository backed by the
     `MemoryRunner` factory in tests (inject a test factory).

### Acceptance criteria

- `CommitIt::open/init/clone` return a usable `Repository` in tests.
- SDK depends only on `Common, Contract, Domain, Application` (DepTrac).
- No global mutable state; facade is stateless.

---

## Milestone M6 — Integration Tests & Failure Injection

**Goal:** Prove the adapter against real git repositories and hostile inputs.

### Tasks

1. **Functional suite with real temp repos.**
    - `tests/Functional/` using the M0 `GitRepoFactory` helper. Cover:
     init → seed → stage → commit → getLastCommit; branch create/checkout/remove;
     tag create/remove/rename; remote add/remove/rename/setUrl; pull/push against
     a local bare repo; `hasChanges()` toggling; worktree add/list/remove.

2. **Failure-injection tests.**
   - Missing repository path → `RepositoryNotFoundException`.
   - Invalid ref name → `InvalidReferenceNameException` (validator boundary).
   - Dirty working tree blocking an operation → `DirtyWorkingTreeException`.
   - Forced `GitException` via `MemoryRunner` error scripting → mapped domain
     exception with `previous` preserved.

3. **Security / negative tests.**
   - Injection-shaped ref names and commit messages are rejected by validators.
   - Path-scope: stage/remove rejects paths escaping the repository root.
   - `clone`/`init` into an existing non-empty directory fails fast (no silent
     overwrite).

4. **Concurrency isolation.**
   - Repeat a sequence of operations in a loop / persistent-worker simulation to
     catch shared-state leaks.

### Acceptance criteria

- Functional suite passes against real git (requires git in CI/environment).
- Every exception branch in `GitExceptionMap` and the validators has a covering
  test.
- Negative/security tests pass.

---

## Milestone M7 — Documentation & ADRs

**Goal:** Record decisions and finalize public-facing docs.

### Tasks

1. **Architecture docs.**
   - `docs/architecture/` — a short layer-and-dependency writeup (can summarize
     `IMPLEMENTATION_PLAN.md` Section 3/4).

2. **ADRs** under `docs/adr/` for the topics identified in the plan:
   - `ADR-001` Hexagonal layering and port boundaries.
   - `ADR-002` Vendor-neutral vocabulary vs. git-first.
   - `ADR-003` `RepositoryObserver` as the RunIt-integration boundary.
   - `ADR-004` Worktree strategy (composition over `czproject` subclassing).
   - `ADR-005` Value-object validation rules.

3. **Update `README.md`** to mark implemented features as available (transition
   the "planned" sections to "usage" as code lands). Keep examples executable and
   `declare(strict_types=1)`-clean.

4. **Update `AGENTS.md` "Sources of Truth"** if new `docs/adr/` or
   `docs/architecture/` paths are introduced.

### Acceptance criteria

- ADRs exist for all five topics and are consistent with the implemented code.
- `README.md` examples reflect the actual public API at this milestone.
- `docs/IMPLEMENTATION_PLAN.md` open questions (Section 13) are resolved or logged
  as deferred with rationale.

---

## Milestone M8 — Quality Hardening

**Goal:** Reach the it-pack quality bar before tagging `1.0.0`.

### Tasks

1. **PHPStan `level: max` clean** across `src/` and tests (no baseline errors
   added; documented suppressions only with justification).

2. **DepTrac green** with no skips; correct any boundary violation by refactoring,
   not by adding skips.

3. **PHP-CS-Fixer clean** (`@PHP84Migration`, `declare_strict_types`, etc.). Run
   `composer coding-style` (not dry-run) and commit formatted code.

4. **Infection mutation testing.**
   - Set/confirm a mutation score threshold in `infection.json5`.
   - Prioritize mutants in validators, `GitExceptionMap`, and observer dispatch.
   - Iterate until threshold met.

5. **Composer audit** clean for shipped dependencies.

6. **Benchmarks (only if a hot path emerges).** Add `phpbench` benchmarks for
   `execute()` or cloning only if profiling shows a real cost; never claim an
   improvement without measurements.

### Acceptance criteria

- `composer quality` (or the aggregate of analyse/architecture/coding-style/
   mutation/audit/test) passes in CI.
- Mutation score meets the configured threshold.
- No open architectural or static-analysis violations.

---

## Cross-Cutting Concerns (apply every milestone)

- **Naming:** use VCS domain terms (`Repository`, `WorkingTree`, `Branch`,
  `Commit`, `CommitId`); avoid `Manager`/`Helper`/`Utility` (see `AGENTS.md`).
- **Immutability & finality:** value objects and most services `final` + `readonly`.
- **Exception preservation:** adapters wrap backend errors and keep `previous`.
- **No global state / service locators.**
- **Test-first:** add/adjust tests with each behavioral change; prefer narrow
  tests (unit → adapter-unit via `MemoryRunner` → functional).
- **Documentation sync:** update `README.md`/`AGENTS.md` when public behavior
  changes.

---

## Suggested Execution Order (summary)

```text
M0  Foundation & QA wiring                       ✅ COMPLETE (2026-08-21)
M1  Common & Contract ports                     ✅ COMPLETE (2026-08-21)
M2  Domain value objects + validators   (skeletons built in M1; M2 adds validation + validators + tests)
M3  Git adapter (factory, repository, working tree, exception map)
M4  Application (ObservedRepository, RepositoryOpener; option builders already in Domain/Option)
M5  SDK facade
M6  Integration tests & failure/security injection
M7  Documentation & ADRs
M8  Quality hardening (phpstan max, deptrac, cs-fixer, infection)
```

Legend: ⬜ pending · 🔄 in progress · ✅ complete.

Each milestone is "done" only when its acceptance criteria and tests pass and the
tooling (M0) is green for that slice.
