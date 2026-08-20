# CommitIt — Implementation Plan

> Status: Planning / brainstorming. No source code has been written yet (`src/` is empty).
> Audience: AI coding agents and maintainers implementing `it-pack/commit-it`.

---

## 1. Overview and Goals

CommitIt is the VCS member of the it-pack family. It provides a **vendor-neutral
API and SDK** for common version-control operations, shipping first with a **git**
implementation built on `czproject/git-php`.

Design tensions to resolve up front:

- **Vendor-neutral core vs. git-first reality.** The public surface must not leak
  git-specific vocabulary where a VCS-agnostic concept exists, but the 1.0.0 scope
  is git only. We resolve this by keeping git-specific mechanics *inside the
  adapter layer* and expressing the public contract in VCS-neutral terms
  (`Repository`, `Branch`, `Tag`, `Remote`, `Commit`, `WorkingTree`).
- **Powerful enough to wrap git, but open to other VCS later.** No other VCS is
  targeted today, so we must avoid speculative abstraction while still drawing a
  clean port boundary so a future adapter (e.g. hg, svn, fossil) can be slotted in
  without touching the domain or SDK.
- **Execution-model ready, not execution-model dependent.** CommitIt must not
  depend on RunIt. It must, however, be *open to* RunIt integration: operations
  should be observable through a narrow port so a future `it-pack/commit-it-runit`
  adapter can map them to RunIt activities/occurrences without changes to CommitIt
  itself.

---

## 2. Design Principles

1. **Hexagonal ports & adapters** (already enforced by `deptrac.yaml`):
   `Common → Contract → Domain → Application → SDK → Adapter`, with adapters
   depending on contracts but never the reverse.
2. **Adapters translate, they do not redefine.** The git adapter maps
   `czproject/git-php` calls to CommitIt contracts; it does not invent new domain
   semantics.
3. **Immutable value objects for validated concepts.** Identifiers, names, paths,
   and messages are dedicated value objects, not bare strings.
4. **Failures map to domain exceptions and preserve the original cause.**
   A `GitException` becomes a `CommitIt\Domain\Exception\VcsOperationException`
   (or narrower subtype) with the original attached as `previous`. Never swallow
   or replace the underlying error.
5. **Instrumentation is a decorator, not a cross-cutting hack.** Observation lives
   behind a `RepositoryObserver` port applied via a non-intrusive decorator, so the
   core repository and adapter stay clean and RunIt can later subscribe.
6. **No global mutable state, no service locator, no hidden container.**
7. **Modern PHP 8.4 features only when they improve clarity** (property hooks for
   validated value objects, readonly where mutation is unnecessary, lazy objects
   for expensive optional infrastructure, `WeakMap` for object-keyed associations).

---

## 3. Layered Architecture and Dependency Map

```text
SDK (DX facade, fluent builders)
        │
        ▼
Application (factory wiring, observer decorator, orchestration)
        │
        ▼
Domain (value objects, validators, domain exceptions)
        ▲
        │
Contract (Repository, WorkingTree, RepositoryFactory, RepositoryObserver ports)
        ▲
        │
Adapter/Git (czproject/git-php wrapper, worktree support, error mapping)
```

Dependency rules (from `deptrac.yaml`):

- **Common** — base marker exception, shared primitives. Depends on nothing
  internal.
- **Contract** — interfaces only. Depends on Common (+ Domain value objects where
  a port needs to speak them, e.g. `Commit`, `BranchName`).
- **Domain** — value objects, validators, domain exceptions. Depends on Common,
  Contract. No external libs, no `czproject`, no filesystem/process logic.
- **Application** — factory composition, the observer decorator, option builders.
  Depends on Common, Contract, Domain.
- **SDK** — ergonomic facade. Depends on all of the above. This is the layer most
  consumers touch.
- **Adapter** — the only layer allowed to depend on `czproject/git-php`. Depends on
  Common, Contract, Domain.

> Note: `Common` and `Contract` are currently empty directories in `deptrac`; the
> plan populates them deliberately so architectural boundaries are real, not
> nominal.

---

## 4. Proposed Package Structure

```text
src/
  Common/
    CommitItException.php            # marker interface / base for all CommitIt errors
  Contract/
    Repository.php                   # primary VCS port
    WorkingTree.php                  # working-tree / worktree port
    RepositoryFactory.php            # opens/inits/clones repositories
    RepositoryObserver.php           # operation lifecycle hook (RunIt-ready)
    Exception/
      VcsOperationException.php      # domain exception marker
      RepositoryNotFoundException.php
      InvalidReferenceNameException.php
      DirtyWorkingTreeException.php
  Domain/
    Value/
      RepositoryPath.php             # validated, realpath-resolved path
      CommitId.php                   # opaque validated id (git: 40 hex)
      Commit.php                     # immutable commit snapshot
      BranchName.php                 # validated ref name (extensible string)
      TagName.php                    # validated ref name
      RemoteName.php                 # validated remote name
      CommitMessage.php              # subject + body, validated
    Validation/
      RefNameValidator.php
      CommitMessageValidator.php
    Exception/
      ...domain-specific throwables implementing Contract exceptions
  Application/
    RepositoryOpener.php             # wires factory + observer decorator
    ObservedRepository.php           # decorator: dispatches to RepositoryObserver
    Option/
      InitOptions.php                # immutable builder for `init`
      CloneOptions.php               # immutable builder for `clone`
      CommitOptions.php              # immutable builder for `commit`
  Adapter/
    Git/
      GitPhpRepositoryFactory.php    # builds GitPhpRepository (uses czproject Git)
      GitPhpRepository.php           # implements Repository via czproject
      GitPhpWorkingTree.php          # implements WorkingTree (worktree cmds)
      GitExceptionMap.php            # czproject GitException -> domain exception
  SDK/
    CommitIt.php                     # static facade: open()/init()/clone()
    Fluent/
      StageBuilder.php               # optional ergonomic staging/commit chaining
```

This is intentionally lean. Interfaces exist because they are *ports*, not because
we anticipate multiple implementations today. No interface duplicates another.

---

## 5. Domain Model and Value Objects

Value objects are the backbone of "difficult to misuse" APIs.

- **`RepositoryPath`** — string path; validates existence-or-creatable and
  resolves to a real path. Property-hook backed.
- **`CommitId`** — opaque string; the git adapter enforces the 40-hex SHA-1
  format, but the domain type stays format-agnostic so a future VCS can relax it.
- **`Commit`** (`readonly`) — `id`, `subject`, `body`, `authorName`,
  `authorEmail`, `authorDate`, `committerName`, `committerEmail`,
  `committerDate`. Mirrors the data `czproject` already returns in its `Commit`
  class, but as our own immutable, testable object.
- **`BranchName` / `TagName` / `RemoteName`** — extensible validated strings
  (AGENTS guidance: prefer validated strings over closed enums for user-defined
  names). Validation delegates to `RefNameValidator`.
- **`CommitMessage`** (`readonly`) — `subject` + optional `body`. Validation:
  non-empty subject, trimmed, no trailing newline/whitespace, length guard for the
  subject line. Built via named constructor `CommitMessage::fromParts()` or
  `::fromString()`.

Validators (`RefNameValidator`, `CommitMessageValidator`) are pure, framework-free,
and unit-tested directly — good mutation-testing targets.

---

## 6. Contracts (Ports)

### `Repository` (primary port)
Covers the operations every VCS repository should support at minimum:

- `getPath(): RepositoryPath`
- `hasChanges(): bool`
- `stage(string|iterable $files): void` and `stageAll(): void`
- `commit(CommitMessage $message, ?CommitOptions $options = null): CommitId`
- `getLastCommit(): Commit`
- `getCommit(CommitId $id): Commit`
- `getBranches(): array`, `getCurrentBranch(): BranchName`,
  `createBranch(BranchName $name, bool $checkout = false): void`,
  `removeBranch(BranchName $name): void`, `checkout(BranchName|TagName $ref): void`
- `getTags(): array`, `createTag(TagName $name, ?array $options = null): void`,
  `removeTag(TagName $name): void`, `renameTag(TagName $old, TagName $new): void`
- `getRemotes(): array`, `addRemote(RemoteName $name, string $url): void`,
  `removeRemote(RemoteName $name): void`, `renameRemote(...)`, `setRemoteUrl(...)`
- `pull(...)`, `push(...)`, `fetch(...)`
- `getWorkingTree(): WorkingTree`
- `execute(string $command, ...$args): array` — narrow escape hatch for
  git-specific commands *not* yet in the contract, clearly documented as
  adapter-specific and not portable.

### `WorkingTree` (port)
Models git working trees / worktrees (a CommitIt-differentiating feature per the
briefing):

- `list(): array`
- `add(RepositoryPath $path, ?BranchName $branch = null): void`
- `remove(RepositoryPath $path): void`

### `RepositoryFactory` (port)
- `open(RepositoryPath $path): Repository`
- `init(RepositoryPath $path, ?InitOptions $options = null): Repository`
- `clone(string $url, RepositoryPath $path, ?CloneOptions $options = null): Repository`

### `RepositoryObserver` (port — RunIt-ready)
A minimal lifecycle hook:

```php
interface RepositoryObserver
{
    public function onStart(string $operation, array $context): void;
    public function onSuccess(string $operation, array $context): void;
    public function onFailure(string $operation, \Throwable $error, array $context): void;
}
```

Default `NullRepositoryObserver` (no-op) ships in `Common`. A future
`it-pack/commit-it-runit` package implements this interface to emit RunIt
activities/occurrences. CommitIt itself stays free of any RunIt dependency.

---

## 7. Git Adapter Design

`czproject/git-php` already provides `Git`, `GitRepository`, `Commit`,
`CommitId`, runners (`CliRunner`, `MemoryRunner`), and a clean `run(...$args)` /
`execute(...$cmd)` passthrough. The adapter's job is translation, not reinvention.

- **`GitPhpRepositoryFactory`** builds a `czproject\GitPhp\Git` (optionally with a
  custom `IRunner` for testing) and returns a `GitPhpRepository`. It opens existing
  repos, inits new ones, and clones.
- **`GitPhpRepository implements Repository`** wraps a `GitRepository` instance.
  Each method translates:
  - `CommitMessage` → string(s) passed to `commit()`.
  - returned `CommitId`/`Commit` → our domain `CommitId`/`Commit`.
  - collections (`getBranches`, `getTags`, `getRemotes`) → arrays of our value
    objects.
- **Worktree support.** `czproject` has no worktree API, confirmed in the lab
  `worktree.php` (which subclassed `Git`/`GitRepository`). We avoid subclassing and
  instead compose: `GitPhpWorkingTree` calls the wrapped repository's public
  `execute('worktree', 'list'|'add'|'remove', ...)` passthrough and parses the
  output. This keeps the adapter free of `czproject` internals hacking.
- **Error mapping.** `GitExceptionMap` converts `CzProject\GitPhp\GitException`
  into the appropriate `Contract\Exception\*` type, preserving the original as
  `previous`. Common mappings: missing `.git` → `RepositoryNotFoundException`;
  invalid ref → `InvalidReferenceNameException`; uncommitted changes on a blocked
  op → `DirtyWorkingTreeException`; everything else → `VcsOperationException`.
- **Testing the adapter cheaply.** `czproject` ships `MemoryRunner`, which lets us
  script command outputs for fast, deterministic unit tests of parsing/mapping
  without spawning real git processes. Real temp repositories are reserved for the
  functional/integration suites.

---

## 8. SDK / DX Layer

The SDK is the ergonomic, programmatic face of CommitIt. It is a thin facade over
the application layer and must remain optional and decoupled from the domain.

```php
use ItPack\CommitIt\SDK\CommitIt;
use ItPack\CommitIt\Domain\Value\CommitMessage;

$repo = CommitIt::open('/path/to/repo');
$repo->stageAll();
$repo->commit(CommitMessage::fromParts('Add feature', 'Details here.'));

$repo = CommitIt::init('/path/to/new');
$worktree = $repo->getWorkingTree();
$worktree->add(RepositoryPath::from('/path/to/wt'), branch: new BranchName('wt'));
```

Option builders (`InitOptions`, `CloneOptions`, `CommitOptions`) are immutable and
fluent, avoiding large untyped config arrays (per AGENTS API guidance).

A light `Fluent\StageBuilder` may chain `stage()->commit()` for DX, but only if a
concrete consumer needs it — not speculatively.

---

## 9. Execution-Model Readiness (RunIt Integration)

CommitIt does **not** depend on RunIt. To stay "open to integration":

1. Every mutating/long operation on `Repository` is observable via
   `RepositoryObserver` (Section 6). The application layer applies this through the
   `ObservedRepository` decorator, so the core and adapter never reference it.
2. A future, separate package `it-pack/commit-it-runit` implements
   `RepositoryObserver` and translates `onStart/onSuccess/onFailure` into RunIt
   activities and occurrences. No change to CommitIt is required for that.
3. `EXECUTION.md` remains the shared it-pack vocabulary reference; CommitIt's
   observer vocabulary (operation name + structured context) is chosen to map
   cleanly onto RunIt's Activity/Observation/Occurrence triad without forcing it.

This satisfies the briefing's requirement without premature coupling.

---

## 10. Security Considerations

- **Command injection.** `Repository::execute()` and file/path arguments flow to
  git subprocesses. Rely on `czproject`'s runner argument escaping, but restrict
  the `execute()` escape hatch in documentation and consider validating the command
  token against an allow-list in the git adapter if we expose it publicly.
- **Path traversal / arbitrary write.** `RepositoryPath` must resolve and, where
  relevant, constrain operations to within the repository root. Never accept
  absolute paths that escape the repo for stage/remove.
- **Secrets.** Commit messages, file contents, and ref names are user-controlled
  and could contain secrets. CommitIt must never log full payloads by default; the
  observer context should carry operation names and identifiers, not message
  bodies, unless explicitly configured.
- **`.git` integrity.** Operations must not corrupt or expose `.git`. Cloning into
  an existing non-empty directory should fail fast, not silently overwrite.
- **Denial of service.** Clone/fetch from untrusted URLs is out of scope for 1.0.0
  but should be documented; no unbounded buffering of git output in core.

Negative tests should cover injection-shaped inputs and malicious paths.

---

## 11. Testing Strategy

Aligned with `AGENTS.md` test categories, mapped to CommitIt:

- **Domain invariant tests** — value-object validation (`RefNameValidator`,
  `CommitMessageValidator`, `CommitId` format), immutability of `Commit`.
- **Port contract tests** — define behavioral contracts for `Repository`,
  `WorkingTree`, `RepositoryFactory`. The git adapter's conformance suite
  implements them; future adapters reuse the same harness (fixture interfaces /
  verifier objects, framework-neutral).
- **Adapter unit tests** — use `czproject\GitPhp\MemoryRunner` to script outputs
  and assert parsing/mapping without real git.
- **Adapter integration tests** — real temp repositories in the Codeception
  `Functional` suite (`init`, `commit`, `branch`, `tag`, `remote`, `worktree`,
  `clone`, `pull`/`push` against a local bare repo).
- **Failure-injection tests** — `GitExceptionMap` mapping for each exception type;
  observer `onFailure` dispatch.
- **Observer/decorator tests** — `ObservedRepository` invokes the observer on every
  path; `NullRepositoryObserver` is a true no-op.
- **Mutation testing** — prioritize validators, error mapping, and ref-name rules
  (these are the critical branching points).
- **Benchmarks** — only if/when `execute()` or cloning becomes a hot path; git
  subprocess cost dominates, so micro-optimizing PHP is low value initially.

QA tooling already present: PHPStan (`level: max`), DepTrac, PHP-CS-Fixer
(`@PHP84Migration`), Infection. Scripts should be wired in `composer.json`
(`analyse`, `architecture`, `coding-style`, `mutation`, `test`) before
implementation begins.

---

## 12. Phasing / Roadmap to 1.0.0

1. **Scaffold & contracts** — populate `Common`/`Contract`, define interfaces and
   exceptions, wire `composer` scripts.
2. **Domain value objects + validators** — with unit tests.
3. **Git adapter** — `GitPhpRepository`, `GitPhpRepositoryFactory`,
   `GitPhpWorkingTree`, `GitExceptionMap`; MemoryRunner-based unit tests.
4. **Application layer** — option builders, `ObservedRepository` decorator,
   `RepositoryOpener`.
5. **SDK facade** — `CommitIt`, `CommitMessage` ergonomics, optional fluent
   builder.
6. **Integration tests** — real-repo functional suite + failure injection.
7. **Docs & ADRs** — README, usage examples, ADRs for the port boundary and the
   observer/RunIt integration strategy.
8. **Polish** — PHPStan max, DepTrac green, Infection threshold, CS-Fixer clean.

---

## 13. Open Questions / Decisions Needed

- **`execute()` exposure.** Should the generic `execute(string $command, ...$args)`
  escape hatch be part of the public `Repository` contract (portable but
  git-specific) or live only on the git adapter under a `GitRepository` extension
  interface? Recommendation: keep it on the contract but documented as
  adapter-specific and non-portable.
- **`Commit` richness.** Do we expose full author/committer metadata in 1.0.0 or a
  minimal `Commit` (id + subject) with metadata added later? Recommendation:
  ship the full immutable `Commit` since `czproject` already provides it cheaply.
- **Scope of working trees.** Confirm worktree (linked) vs. bare secondary checkouts
  — plan targets linked worktrees via `git worktree`.
- **RunIt dependency decision.** Confirm CommitIt ships *zero* RunIt code and relies
  solely on the `RepositoryObserver` port. Recommendation: yes (keeps the package
  independently installable).

---

## 14. Suggested ADR Topics

If/when accepted, record as ADRs under `docs/adr/`:

- ADR-001: Hexagonal layering and port boundaries for CommitIt.
- ADR-002: Vendor-neutral vocabulary vs. git-first implementation.
- ADR-003: `RepositoryObserver` as the RunIt-integration boundary.
- ADR-004: Worktree support strategy (composition over `czproject` subclassing).
- ADR-005: Value-object validation rules for refs and commit messages.

---

## 15. Summary

CommitIt should be a thin, rigorously-typed, hexagonal VCS library: a vendor-neutral
`Repository`/`WorkingTree`/`RepositoryFactory` contract, a git adapter wrapping
`czproject/git-php` (including the differentiating worktree support), immutable
domain value objects with validators, an optional ergonomic SDK, and a single
narrow `RepositoryObserver` port that makes future RunIt integration a separate,
dependency-free adapter. The design favors small, explicit, testable pieces over
speculative abstraction, and reuses it-pack family standards already encoded in
tooling and `AGENTS.md`.
