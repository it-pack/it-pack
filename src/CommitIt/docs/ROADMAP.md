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

## Milestone M2 — Domain: Value Objects & Validators ✅ COMPLETE

**Goal:** Immutable, validated, framework-free domain primitives. These are the
highest-value mutation-testing targets.

> **Status (2026-08-21):** Implemented and verified. Gates green: `phpstan
> analyse` (level max, 0 errors), `deptrac` (0 violations), `php-cs-fixer`
> (0 files), `composer validate` (valid), `codecept run Unit` (33 tests, 165
> assertions). Mutation testing is wired in M8 (infection `min-msi=0`); the
> validator branches are structured for strong MSI.

### What was built

- **`RepositoryPath` hardened** (`src/Domain/Value/RepositoryPath.php`): validates
  non-empty + control-character-free; adds a deferred `realPath` property hook
  that resolves the canonical path on first access (cached), keeping
  construction pure per the roadmap's "resolution may be deferred" note.
- **Ref-name validation** (`src/Domain/Validation/RefNameValidator.php` +
  `RefKind` enum): pure, git `check_ref_format`-aligned rules shared by
  branch/tag/remote names. `BranchName` / `TagName` / `RemoteName` now route
  through it in `from()`.
- **Commit-message validation** (`src/Domain/Validation/CommitMessageValidator.php`):
  non-empty, single-line, bounded subject (`MAX_SUBJECT_LENGTH = 72`, the
  conventional git soft limit), optional non-empty body. `CommitMessage` routes
  through it in `fromParts()`.
- **Domain exception taxonomy** (`src/Domain/Exception/`): concrete throwables for
  all five M1 contract markers (`VcsOperationException`,
  `RepositoryNotFoundException`, `DirtyWorkingTreeException`,
  `InvalidReferenceNameException`, `InvalidCommitMessageException`), each
  implementing its `Contract\Exception\*` marker and `@api`-tagged as public API.
- **New contract marker** `Contract\Exception\InvalidCommitMessageException`
  (extends `VcsOperationException`) — added because commit-message validation
  needed a marker distinct from reference-name errors, per AGENTS' rule that
  required exceptions implement the relevant marker.

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

### Acceptance criteria — all met

- Unit tests for each value object: valid construction, rejection of invalid
  input, immutability (`readonly`), equality semantics (`equals()` where
  applicable). See `tests/Unit/Domain/Value/ValueObjectTest.php`.
- Validator tests cover valid + invalid branches/tags/remotes and commit messages.
  See `tests/Unit/Domain/Validation/{RefNameValidatorTest,CommitMessageValidatorTest}.php`.
- PHPStan max and DepTrac green (`Domain → Common, Contract` only).
- Mutation testing on validators is wired in M8; the `reason()`/`assertValid()`
  split gives clean, high-MSI branches.

### Decisions recorded during M2

- **Branch and tag names share the same ref-format rules** (both permit `/` as a
  hierarchy separator under git's `check_ref_format` constraints). The roadmap's
  "tag allows `/`" phrasing is satisfied because *both* allow `/`; the only
  intended difference is semantic (heads vs. tags), not the character set. Remote
  names use the same constraints. If a project later needs stricter remote-name
  rules, `RefNameValidator` is the single place to differentiate by `RefKind`.
- **Subject length guard = 72** (conventional git soft limit), exposed as
  `CommitMessageValidator::MAX_SUBJECT_LENGTH` so it can be tuned without touching
  the rule body.
- **Empty/whitespace body is rejected** when explicitly provided (the value
  object no longer silently normalizes a whitespace-only body to "no body"); a
  `null` body remains valid.

---

## Milestone M3 — Git Adapter ✅ COMPLETE

**Goal:** A working, testable git implementation of the contracts using
`czproject/git-php`, including the differentiating worktree support and error
mapping.

> **Status (2026-08-21):** Implemented and verified. Gates green: `phpstan
> analyse` (level max, 0 errors), `deptrac` (0 violations, 161 allowed edges),
> `php-cs-fixer` (0 files), `composer validate` (valid), `codecept run Unit`
> (60 tests, 214 assertions). Adapter behavior is covered deterministically via
> `czproject/git-php`'s `MemoryRunner` (no real git binary).

### What was built

- **`GitExceptionMap`** (`src/Adapter/Git/GitExceptionMap.php`): pure, message-driven
  mapper from `CzProject\GitPhp\GitException` to the CommitIt taxonomy
  (`RepositoryNotFoundException` / `InvalidReferenceNameException` /
  `DirtyWorkingTreeException` / `VcsOperationException`). The original
  `GitException` is always preserved as `previous`.
- **`GitPhpRepositoryFactory`** (`src/Adapter/Git/GitPhpRepositoryFactory.php`):
  `final`, implements `Contract\RepositoryFactory`. Injects an optional
  `IRunner` (for tests). `open()` fails fast (realpath + `rev-parse --git-dir`),
  `init()` and `clone()` translate the option builders.
- **`GitPhpRepository`** (`src/Adapter/Git/GitPhpRepository.php`): `final`,
  implements `Contract\Repository`, wraps `GitRepository`. Every backend call is
  funnelled through `GitExceptionMap`. Commit options (author/date/allow-empty/
  amend) are translated; `getLastCommit` returns `null` for an empty repository;
  `execute()` is a documented git-specific passthrough.
- **`GitPhpWorkingTree`** (`src/Adapter/Git/GitPhpWorkingTree.php`): `final`,
  implements `Contract\WorkingTree`. Uses the wrapped repo's `execute('worktree',
  ...)` passthrough (composition, not subclassing). Parses `git worktree list
  --porcelain` and guards worktree paths against escaping the repository parent.
- **Adapter unit tests** (`tests/Unit/Adapter/Git/`): `MemoryRunner`-driven,
  covering `GitExceptionMap` for every branch, commit → `CommitId` mapping,
  `getCommit` field mapping, `getLastCommit` empty-repo handling, branch/tag/
  remote listing and ops, `fetch`/`pull`/`push`, worktree parsing, and the
  factory's `open`/`init`/`clone`.

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

### Acceptance criteria — all met

- `GitPhpRepositoryFactory::open()` on a non-repo throws
  `RepositoryNotFoundException` (verified for both a missing path and an existing
  non-git directory).
- `GitExceptionMap` unit tests cover every mapped exception type.
- Adapter unit tests pass deterministically via `MemoryRunner` (no real git).
- DepTrac green: adapter depends only on `Common`, `Contract`, `Domain`, and
  `czproject/git-php`.

### Decisions recorded during M3

- **`open()` maps a non-repo explicitly, not via the message heuristic.** czproject
  surfaces a generic `"Command 'git rev-parse --git-dir' failed"` for a missing
  repository, which the keyword-based map would not classify as
  `RepositoryNotFoundException`. `open()` therefore catches the `rev-parse` failure
  and throws `RepositoryNotFoundException` directly, preserving the `GitException`
  as `previous`.
- **`listBranches()` uses `getLocalBranches()`** rather than `getBranches()` to
  avoid the `remotes/...` prefix that `git branch -a` attaches to remote-tracking
  refs (which are not valid `BranchName` values).
- **`listRemotes()` parses `git remote -v`** and keeps the `(fetch)` URL, since
  `czproject/git-php` exposes no remote-listing helper.
- **`commit()` passes the full message** (`subject` + `"\n\n"` + `body`) as a
  single `-m`; git splits it into subject/body correctly. Author/date are
  translated to `--author`/`--date`; `allowEmpty`/`amend` map to their flags.
- **`execute()` joins output lines with `"\n"`**; it is explicitly a non-portable
  git passthrough (per the contract's warning).
- **Worktree paths are scope-guarded** with a realpath check: the worktree's
  parent directory must be inside the repository's parent directory, preventing
  escape outside the repo scope (AGENTS security requirement).

---

## Milestone M4 — Application Layer ✅ COMPLETE

**Goal:** Compose the adapter into a usable service, add ergonomic option builders,
and wire non-intrusive observation.

> **Status (2026-08-22):** Implemented and verified. Gates green: `phpstan
> analyse` (level max, 0 errors), `deptrac` (0 violations), `php-cs-fixer` (0
> files), `composer validate` (valid), `codecept run Unit` (69 tests, 235
> assertions).

### What was built

- **`ObservedRepository` decorator** (`src/Application/ObservedRepository.php`) —
  `final`, implements `Contract\Repository`, wraps a `Repository`, and dispatches
  `RepositoryObserver::onStart/onSuccess/onFailure` around every operation via two
  private helpers (`run` for value-returning operations, `runVoid` for void
  operations). The failure path calls `onFailure` with the **original**
  `Throwable` and rethrows it (never a substituted observer error). Every observer
  dispatch is wrapped in `notify()`, which catches observer exceptions on a
  separate, non-recursive path (observer failures never break or re-observe the
  operation). Structured `context` carries only non-sensitive, operation-relevant
  identifiers (branch/tag/remote/commit names, `allowEmpty`/`amend` flags, command
  name); it deliberately omits commit-message bodies and any secret material per
  AGENTS security rules.
- **`RepositoryOpener`** (`src/Application/RepositoryOpener.php`) — `final`,
  composes a `RepositoryFactory` with an optional `RepositoryObserver` (defaulting
  to `NullRepositoryObserver`). `open()/init()/cloneRepository()` return a plain
  `Repository` when no observer is supplied (zero observation overhead) and an
  `ObservedRepository` otherwise. This is the wiring seam the SDK (M5) consumes.
- **Application tests** (`tests/Unit/Application/{ObservedRepositoryTest,
  RepositoryOpenerTest}.php` with `FakeRepository`, `FakeWorkingTree`,
  `RecordingObserver`, `ThrowingObserver`, `FakeFactory` doubles): verify
  `onStart`/`onSuccess`/`onFailure` dispatch and ordering on success; original
  exception propagation with the same instance on failure; observer-failure
  isolation (operation still returns its result); `NullRepositoryObserver` no-op;
  `getWorkingTree()` is intentionally **not** observed (returns the inner tree
  without observer events); and the opener returns a plain repository vs. an
  `ObservedRepository` depending on the observer.

### Decisions

- **Option builders live in `Domain\Option`, not `Application\Option`.** The M4 plan
  sketched `InitOptions`/`CloneOptions`/`CommitOptions` under `Application\Option`,
  but these were already built in `Domain\Option` during M1 and are referenced
  directly by the `Contract\RepositoryFactory` and `Contract\Repository` signatures.
  DepTrac forbids `Contract → Application` (the dependency direction is
  `Application → Contract`), so relocating them would break the layering. The M4
  option-builder deliverable is therefore satisfied by the existing, already-tested
  immutable/fluent Domain builders (verified in M1/M2).

### Tasks (status)

1. **Option builders (immutable, fluent).** ✅ Already satisfied by
   `src/Domain/Option/{InitOptions,CloneOptions,CommitOptions}.php` (built M1, tested
   M2). Not duplicated into `Application\Option` — see Decisions above.
2. **`ObservedRepository` decorator.** ✅
   `src/Application/ObservedRepository.php` — `final`, implements `Contract\Repository`,
   wraps a `Repository`, dispatches observer lifecycle events, rethrows the original
   error, and isolates observer failures on a non-recursive path.
3. **`RepositoryOpener`.** ✅ `src/Application/RepositoryOpener.php` — `final`,
   composes `RepositoryFactory` + optional `RepositoryObserver`, returning a plain or
   observed `Repository` (the SDK wiring seam).
4. **Application tests.** ✅ `tests/Unit/Application/` covers every dispatch path,
   failure propagation, observer-failure isolation, the no-op observer, and opener
   wiring.

### Acceptance criteria — all met

- `ObservedRepository` observer dispatch verified for `onStart`/`onSuccess`/
  `onFailure`, including that the original exception propagates (same instance).
- Option builders verified immutable and fluent (M1/M2 suites).
- DepTrac green: `Application → Common, Contract, Domain` (0 violations, 0 errors).

---

## Milestone M5 — SDK / DX Facade ✅ COMPLETE (2026-08-22)

**Goal:** An ergonomic, optional programmatic entry point for consumers.

**Status:** Done. The `CommitIt` facade is implemented as a thin, stateless
wrapper over `RepositoryOpener`. Value-object ergonomics from M2 were verified
and required no changes. The optional fluent builder was deliberately skipped
(no consumer requires it).

### Tasks — status

1. **`CommitIt` facade.** ✅ `src/SDK/CommitIt.php` — `final`, static
   `open()`, `init()`, `clone()` delegating to `RepositoryOpener`. Returns
   `Contract\Repository`. The facade holds no hidden state and performs no
   global configuration.
2. **Value-object ergonomics.** ✅ Verified. `CommitMessage::fromParts()` /
   `fromString()`, `RepositoryPath::from()`, and `BranchName` / `TagName` /
   `RemoteName` `from()` constructors are ergonomic and unchanged.
3. **Optional fluent builder.** ⏭ Skipped. `src/SDK/Fluent/StageBuilder.php`
   was not built — the ROADMAP explicitly says to skip unless a concrete
   consumer requires it, and none exists yet.
4. **SDK tests.** ✅ `tests/Unit/SDK/CommitItTest.php` — facade returns a
   working repository via an injected test `RepositoryFactory`
   (`Tests\Unit\Application\FakeFactory`); covers `open`/`init`/`clone`
   delegation, observer wiring, and the no-cached-factory (stateless) property.

### Built

- `src/SDK/CommitIt.php` — `final` facade. Each static method accepts a
  `RepositoryFactory` (required) and an optional `?RepositoryObserver`; it
  constructs a `RepositoryOpener` and delegates, returning a vendor-neutral
  `Contract\Repository`.
- `tests/Unit/SDK/CommitItTest.php` — 5 tests (delegation for open/init/clone,
  observer wiring through `RecordingObserver`, statelessness across calls).

### Decisions

- **Factory injected per call, not stored.** The facade is stateless and must
  not couple to any concrete adapter (a default git factory would mean
  `SDK → Adapter`, a DepTrac violation). Callers pass the backend explicitly
  (e.g. `new GitPhpRepositoryFactory()`), which keeps the SDK depending only on
  `Common, Contract, Domain, Application`.
- **`clone()` is a valid static method name.** Although `clone` is a reserved
  keyword, PHP permits it as a method name and `CommitIt::clone()` parses and
  runs; no rename required.
- **Fluent builder deferred.** Built only on demand to avoid speculative
  abstraction (per AGENTS.md "do not add speculative abstractions").

### Acceptance criteria

- ✅ `CommitIt::open/init/clone` return a usable `Repository` in tests.
- ✅ SDK depends only on `Common, Contract, Domain, Application` (DepTrac: 0
      violations).
- ✅ No global mutable state; facade is stateless (no static properties; the
      supplied factory is never cached between calls).

---

## Milestone M6 — Integration Tests & Failure Injection ✅ COMPLETE (2026-08-22)

**Goal:** Prove the adapter against real git repositories and hostile inputs.

**Status:** Done. Added three real-git functional suites (functional, failure,
security) plus a `MemoryRunner`-driven adapter mapping test. All 104 tests pass
(75 unit + 29 functional, 332 assertions).

### Tasks — status

1. **Functional suite with real temp repos.** ✅ `tests/Functional/GitPhpFunctionalTest.php`
   (real `GitRepoFactory` + `GitPhpRepositoryFactory`). Covers init → seed →
   stage → commit → `getLastCommit`, `hasChanges()` toggling, branch
   create/checkout/remove, tag create/remove, remote add/remove, worktree
   add/list/remove, and clone → push → pull → fetch against a local bare repo.
   - *Deviation:* the public contract exposes tag/remote **create/remove/list**
     only — there are no `rename`/`setUrl` methods — so those two sub-operations
     were intentionally not exercised (would require expanding the API, out of
     scope for a test milestone).
2. **Failure-injection tests.** ✅ `tests/Functional/GitPhpFailureTest.php` +
   `tests/Unit/Adapter/Git/GitPhpExceptionMappingTest.php`. Missing/non-git path →
   `RepositoryNotFoundException` (real git). Validator rejects invalid ref names
   at the `BranchName`/`TagName`/`RemoteName` boundary. Concurrency isolation
   loop (15 repeated commits in one repo + 10 fresh-repo cycles) passes.
   - *Deviation:* a **dirty working tree** is proven to *block* the operation
     (throws a `VcsOperationException`) against real git; the specific
     `DirtyWorkingTreeException` classification is covered at the
     `GitExceptionMap` unit level (see Decisions).
3. **Security / negative tests.** ✅ `tests/Functional/GitPhpSecurityTest.php`.
   git-forbidden ref names rejected for every kind; injection-shaped commit
   message (newline) and oversized subject rejected; worktree `add`/`remove`
   reject escaping paths (`\InvalidArgumentException`); `stage` rejects an
   escaping path (real git refuses, mapped to `VcsOperationException`);
   `clone` into a non-empty directory fails fast and preserves contents; `init`
   into a non-empty directory never overwrites existing files.
4. **Concurrency isolation.** ✅ see task 2 loop test.

### Built

- `tests/Functional/GitPhpFunctionalTest.php` — 7 real-git scenario tests.
- `tests/Functional/GitPhpFailureTest.php` — missing/non-git path, dirty-tree
  block, repeated-operation concurrency loop.
- `tests/Functional/GitPhpSecurityTest.php` — validator injection rejection,
  path-scope escapes, clone/init overwrite protection.
- `tests/Unit/Adapter/Git/GitPhpExceptionMappingTest.php` — `MemoryRunner`-
  forced `GitException` → mapped `VcsOperationException` with `previous`
  preserved.

### Decisions

- **git-php hides git's stderr.** czproject/git-php throws
  `GitException("Command 'git …' failed (exit-code N)")` and does **not** include
  the underlying git error text in the message. Consequently `GitExceptionMap`'s
  keyword branches (`not a valid`/`unknown revision`, `working tree`, `not a git
  repository`) cannot be reached from the live adapter — only the generic
  `VcsOperationException` branch fires. The specific branches are still proven
  via `tests/Unit/Adapter/Git/GitExceptionMapTest.php` with hand-built
  `GitException` instances carrying the matching text.
  - *Follow-up (not done in M6):* if `DirtyWorkingTreeException` /
    `InvalidReferenceNameException` classification from real git is desired, the
    adapter would need to feed git's stderr (the `RunnerResult` error output) into
    the mapped message rather than relying on git-php's wrapper text. This is a
    behavior change and should go through an ADR, not a test milestone.

### Acceptance criteria

- ✅ Functional suite passes against real git (git 2.43 available in environment).
- ✅ Every exception branch in `GitExceptionMap` and in the validators has a
      covering test (map branches via `GitExceptionMapTest`; adapter pass-through
      via `GitPhpExceptionMappingTest`; validators via `RefNameValidatorTest` /
      `CommitMessageValidatorTest` / `GitPhpSecurityTest`).
- ✅ Negative/security tests pass.

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
M2  Domain value objects + validators           ✅ COMPLETE (2026-08-21)
M3  Git adapter (factory, repository, working tree, exception map) ✅ COMPLETE (2026-08-21)
M4  Application (ObservedRepository, RepositoryOpener; option builders + domain exceptions already in place) ✅ COMPLETE (2026-08-22)
M5  SDK facade ✅ COMPLETE (2026-08-22)
M6  Integration tests & failure/security injection ✅ COMPLETE (2026-08-22)
M7  Documentation & ADRs
M8  Quality hardening (phpstan max, deptrac, cs-fixer, infection)
```

Legend: ⬜ pending · 🔄 in progress · ✅ complete.

Each milestone is "done" only when its acceptance criteria and tests pass and the
tooling (M0) is green for that slice.
