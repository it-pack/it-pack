# CommitIt — User Guide

> A vendor-neutral API and SDK for everyday version-control operations.
> The `1.0.0` backend is **git**, implemented as an adapter on
> [`czproject/git-php`](https://github.com/czproject/git-php). This guide shows
> how to **use** CommitIt. See [`README.md`](../README.md) for the project
> overview and [`docs/architecture/`](architecture/) for the layer map.

---

## Table of contents

1. [What CommitIt gives you](#1-what-commit-it-gives-you)
2. [Requirements & installation](#2-requirements--installation)
3. [Mental model: ports, factories, value objects](#3-mental-model-ports-factories-value-objects)
4. [Getting started: open, init, clone](#4-getting-started-open-init-clone)
5. [Daily workflow](#5-daily-workflow)
   - [Staging and committing](#51-staging-and-committing)
   - [Reading commits](#52-reading-commits)
   - [Branches](#53-branches)
   - [Tags](#54-tags)
   - [Remotes and sync](#55-remotes-and-sync)
   - [Working trees (git worktrees)](#56-working-trees-git-worktrees)
   - [Configuration management](#57-configuration-management)
6. [Instrumentation with RepositoryObserver](#6-instrumentation-with-repositoryobserver)
7. [Error handling and the exception taxonomy](#7-error-handling-and-the-exception-taxonomy)
8. [The `execute()` escape hatch](#8-the-execute-escape-hatch)
9. [Patterns and best practices](#9-patterns-and-best-practices)
10. [Common pitfalls](#10-common-pitfalls)
11. [Runnable examples](#11-runnable-examples)

---

## 1. What CommitIt gives you

CommitIt models version control as a small, typed operation set:

```text
Repository        — one addressable repo (the core port)
├── WorkingTree   — git worktree management
├── Branch / Tag  — validated reference names
├── Remote        — validated remote names + sync
└── Commit        — immutable captured facts
```

Two properties matter most to consumers:

- **Vendor-neutral vocabulary.** You never speak "git slang" through the public
  API. `Repository`, `WorkingTree`, `BranchName`, `TagName`, `RemoteName`,
  `CommitMessage`, and `Commit` are first-class domain concepts. The git adapter
  is one implementation behind the `Repository` port.
- **Validation first, backend second.** Reference names and commit messages are
  validated as *value objects* at construction time, so bad input fails loudly
  before any subprocess runs.

---

## 2. Requirements & installation

- **PHP** `^8.4`
- **Git** available on the host (the git adapter shells out via `czproject/git-php`)
- The git adapter dependency `czproject/git-php` (`^4.6`)

Install with Composer:

```bash
composer require it-pack/commit-it
```

The package autoloads under the `ItPack\CommitIt\` PSR-4 namespace.

---

## 3. Mental model: ports, factories, value objects

Three moving parts:

| Concept | Role | Where |
|---|---|---|
| `RepositoryFactory` | Backend entry point: opens, initializes, clones. | `Contract\RepositoryFactory` (git impl: `Adapter\Git\GitPhpRepositoryFactory`) |
| `Repository` | The vendor-neutral surface you do work on. | `Contract\Repository` |
| `CommitIt` (SDK) | A thin, stateless facade that wires a factory + optional observer. | `SDK\CommitIt` |

A few rules that explain the whole API:

- **You always supply the backend factory.** `CommitIt::open/init/clone` take a
  `RepositoryFactory` as an argument; CommitIt never hides a global backend. This
  keeps the SDK agnostic and makes testing trivial (swap in a fake factory).
- **Raw strings are rejected at the boundary.** Branch/tag/remote names,
  commit messages, repository paths, and commit ids are *value objects* built
  with named constructors (`BranchName::from('x')`, `CommitMessage::fromParts(...)`,
  `RepositoryPath::from(...)`). Construction validates; afterwards the value is
  guaranteed well-formed.
- **Configuration is immutable builders.** `InitOptions`, `CloneOptions`, and
  `CommitOptions` are immutable: each `withX()` returns a new instance.

```php
use ItPack\CommitIt\Adapter\Git\GitPhpRepositoryFactory;

$factory = new GitPhpRepositoryFactory();
// $factory is reusable and stateless; inject the same instance everywhere.
```

---

## 4. Getting started: open, init, clone

All three entry points live on the `CommitIt` facade and return a `Repository`.

```php
<?php

declare(strict_types=1);

use ItPack\CommitIt\Adapter\Git\GitPhpRepositoryFactory;
use ItPack\CommitIt\Domain\Option\CloneOptions;
use ItPack\CommitIt\Domain\Option\InitOptions;
use ItPack\CommitIt\Domain\Value\RepositoryPath;
use ItPack\CommitIt\SDK\CommitIt;

$factory = new GitPhpRepositoryFactory();

// Open an existing repository (fails fast if the path is not a git repo).
$repo = CommitIt::open(RepositoryPath::from('/path/to/existing'), $factory);

// Initialize a new repository.
$repo = CommitIt::init(
    RepositoryPath::from('/path/to/new'),
    $factory,
    InitOptions::default()->withInitialBranch('main'),
);

// Clone a remote.
$clone = CommitIt::clone(
    'https://example.com/repo.git',
    RepositoryPath::from('/path/to/clone'),
    $factory,
    CloneOptions::default()->withBranch('main'),
);
```

Notes:

- `CommitIt::open()` validates that the path is a *real* git repository
  (`rev-parse --git-dir`); it never silently initializes.
- `CommitIt::init()` with `InitOptions::default()->withBare()` creates a bare
  repository (useful as a shared remote).
- Each method also accepts an optional trailing `?RepositoryObserver` to wrap
  the returned repository in instrumentation (see §6).

---

## 5. Daily workflow

### 5.1 Staging and committing

```php
use ItPack\CommitIt\Domain\Option\CommitOptions;
use ItPack\CommitIt\Domain\Value\CommitMessage;

// Stage everything (or $repo->stage('path/to/file') for a single file).
$repo->stageAll();

// A commit needs a validated CommitMessage; identity is supplied per-commit
// via CommitOptions (git's own config is used when author is omitted).
$id = $repo->commit(
    CommitMessage::fromParts(
        'Add feature',
        'Optional longer body explaining the change.',
    ),
    CommitOptions::default()->withAuthor('Ada Lovelace', 'ada@example.com'),
);

echo $id; // a 40-char SHA-1 string
```

`CommitMessage` also parses a full message with a blank-line-separated body:

```php
$message = CommitMessage::fromString("Subject line\n\nBody paragraph.");
```

`CommitOptions` is immutable and composable:

```php
CommitOptions::default()
    ->withAuthor('Ada', 'ada@example.com')
    ->withAuthorDate(new \DateTimeImmutable('2026-01-01T00:00:00Z'))
    ->withAllowEmpty()   // --allow-empty
    ->withAmend();       // --amend
```

### 5.2 Reading commits

```php
$last = $repo->getLastCommit(); // ?Commit — null when there are no commits

if ($last !== null) {
    echo $last->subject;        // string
    echo $last->body;           // ?string
    echo $last->authorName;     // string
    echo $last->authorEmail;    // string
    echo $last->authorDate->format(\DateTimeInterface::ATOM); // DateTimeImmutable
    echo $last->committerName;
    echo $last->committerEmail;
    echo $last->committerDate->format('c');
    echo $last->id;             // CommitId value object
}

// Fetch a specific commit by id (throws if it does not exist).
$specific = $repo->getCommit($id);
```

`Commit` is a fully immutable snapshot; author/committer metadata is read-only
once captured.

To check for uncommitted work:

```php
if ($repo->hasChanges()) {
    // staged and/or unstaged changes exist
}
```

### 5.3 Branches

```php
use ItPack\CommitIt\Domain\Value\BranchName;

$repo->createBranch(BranchName::from('feature'));
$repo->checkoutBranch(BranchName::from('feature'));
$repo->deleteBranch(BranchName::from('feature'));

/** @var list<BranchName> $branches */
$branches = $repo->listBranches();
foreach ($branches as $branch) {
    echo $branch; // __toString() returns the raw name
}
```

### 5.4 Tags

```php
use ItPack\CommitIt\Domain\Value\TagName;

// Lightweight tag pointing at the current HEAD.
$repo->createTag(TagName::from('v1.0.0'));

// Tag a specific commit (pass a CommitId from getLastCommit()/getCommit()).
$repo->createTag(TagName::from('v1.0.0-rc1'), $id);

$repo->deleteTag(TagName::from('v1.0.0-rc1'));

/** @var list<TagName> $tags */
$tags = $repo->listTags();
```

### 5.5 Remotes and sync

```php
use ItPack\CommitIt\Domain\Value\BranchName;
use ItPack\CommitIt\Domain\Value\RemoteName;

$repo->addRemote(RemoteName::from('origin'), 'https://example.com/repo.git');
$repo->removeRemote(RemoteName::from('origin'));

/** @var array<string,string> $remotes name => url */
$remotes = $repo->listRemotes();

$repo->fetch(RemoteName::from('origin'));
$repo->pull(RemoteName::from('origin'), BranchName::from('main'));
$repo->push(RemoteName::from('origin'), BranchName::from('main'));
```

All remote/sync methods accept `null` to use git's defaults (e.g.
`$repo->push()` pushes the current branch to its upstream).

### 5.6 Working trees (git worktrees)

Worktrees let you check out multiple branches at once, each in its own
directory, sharing one `.git` store.

```php
use ItPack\CommitIt\Domain\Value\BranchName;
use ItPack\CommitIt\Domain\Value\RepositoryPath;

$tree = $repo->getWorkingTree();

// Add a worktree at a path, optionally creating a new branch there.
$tree->add(
    RepositoryPath::from('/path/to/wt'),
    BranchName::from('wt-branch'),
);

/** @var list<WorktreeInfo> $worktrees */
$worktrees = $tree->listWorktrees();
foreach ($worktrees as $wt) {
    echo $wt->path;      // absolute path string
    echo $wt->branch;    // ?string (null on a detached worktree)
    echo $wt->isMain;    // bool — true for the primary checkout
}

$tree->remove(RepositoryPath::from('/path/to/wt'));
```

**Scope safety:** a worktree path must live *inside the repository's parent
directory*. Attempting to place a worktree outside that scope throws
`InvalidArgumentException` before git is invoked. This is a deliberate security
boundary (AGENTS.md: a worktree path must never escape the repository scope).

### 5.7 Configuration management

Git configuration (e.g. `user.name`, `user.email`, `core.autocrlf`) is managed
through the `ConfigPort` interface, which is part of the `Repository` contract.
Every repository handle supports `get()`, `getAll()`, `set()`, and `unset()`,
scoped to any of git's config levels.

```php
use ItPack\CommitIt\Domain\Value\ConfigScope;

// Read a single value (null when not set).
$email = $repo->get('user.email', ConfigScope::Local);

// Read all values for a scope.
/** @var array<string, string> $config */
$config = $repo->getAll(ConfigScope::Local);

// Write a value.
$repo->set('user.name', 'Ada Lovelace', ConfigScope::Local);
$repo->set('user.email', 'ada@example.com', ConfigScope::Local);

// Remove a value.
$repo->unset('user.name', ConfigScope::Local);

// File-scoped config requires an explicit path.
$repo->set('user.name', 'Ada', ConfigScope::File, '/path/to/config');
```

Configuration can also be applied at init or clone time via the options
builders:

```php
use ItPack\CommitIt\Domain\Option\InitOptions;
use ItPack\CommitIt\Domain\Option\CloneOptions;
use ItPack\CommitIt\Domain\Value\ConfigScope;

// Apply config when initializing a repository.
$repo = $factory->init(
    RepositoryPath::from('/path/to/repo'),
    InitOptions::default()->withConfig(
        ['user.name' => 'Ada', 'user.email' => 'ada@example.com'],
        ConfigScope::Local,
    ),
);

// Apply config when cloning.
$repo = $factory->cloneRepository(
    'https://example.com/repo.git',
    RepositoryPath::from('/path/to/repo'),
    CloneOptions::default()->withConfig(
        ['user.name' => 'Ada'],
        ConfigScope::Local,
    ),
);
```

Supported scopes: `system`, `global`, `local` (default), `worktree`, `file`.

---

## 6. Instrumentation with RepositoryObserver

Every `Repository` operation can be observed through a narrow
`RepositoryObserver` port, applied as a non-intrusive decorator
(`ObservedRepository`). A future RunIt adapter can implement this port to emit
RunIt activities/occurrences; CommitIt itself stays free of any RunIt dependency.

```php
use ItPack\CommitIt\Contract\RepositoryObserver;

final class TimelineObserver implements RepositoryObserver
{
    public function onStart(string $operation, array $context): void
    {
        // e.g. record start time; $context carries non-sensitive identifiers
    }

    public function onSuccess(string $operation, array $context): void
    {
        // e.g. emit a success metric
    }

    public function onFailure(string $operation, \Throwable $error, array $context): void
    {
        // e.g. log the failure (the original error is preserved as $error)
    }
}
```

Wire it at the boundary:

```php
use ItPack\CommitIt\SDK\CommitIt;

$repo = CommitIt::open($path, $factory, new TimelineObserver());
// init(...) and clone(...) accept the observer as the final argument too.
```

Or wrap an already-built repository explicitly:

```php
use ItPack\CommitIt\Application\ObservedRepository;

$observed = new ObservedRepository($repo, new TimelineObserver());
```

Semantics (intentional and guaranteed):

- **Observation never changes the result.** On a failed operation the *original*
  `Throwable` is always rethrown, so your control flow is unaffected.
- **Observer failures are isolated.** If your `onStart`/`onSuccess`/`onFailure`
  throws, the exception is swallowed on a separate, non-recursive path — it can
  neither re-trigger observed operations nor mask a real backend failure.
- **The default is zero-overhead.** With no observer (or the provided
  `NullRepositoryObserver`) `CommitIt` returns a plain `Repository`; the
  decorator is not instantiated, so there is no instrumentation cost.
- **Structured context is privacy-aware.** Context carries only operation names
  and non-sensitive identifiers (branch names, commit ids). Commit-message
  bodies and file contents are never passed to observers by default.

---

## 7. Error handling and the exception taxonomy

CommitIt funnels *all* backend failures through one taxonomy so you can catch
at the right granularity. Every CommitIt error implements `CommitItException`
(a marker extending `\Throwable`), so a single catch handles the whole family.

```text
\Throwable
└── CommitItException                      (marker, catch-all for this library)
    ├── VcsOperationException               (marker; backend/git failures)
    │   ├── RepositoryNotFoundException     (RuntimeException)
    │   ├── DirtyWorkingTreeException       (RuntimeException)
    │   └── VcsOperationException (concrete) (RuntimeException, generic git error)
    └── InvalidReferenceNameException       (InvalidArgumentException, validation)
    └── InvalidCommitMessageException       (InvalidArgumentException, validation)
```

Two distinct failure origins:

1. **Validation failures (before git runs).** Constructing an invalid value
   object throws immediately:
   - `InvalidReferenceNameException` (extends `InvalidArgumentException`) from
     `BranchName::from`, `TagName::from`, `RemoteName::from`.
   - `InvalidCommitMessageException` (extends `InvalidArgumentException`) from
     `CommitMessage::fromParts` / `fromString`.
   - `RepositoryPath::from` throws a plain `InvalidArgumentException` for empty
     or control-character paths.
2. **Backend failures (git ran and failed).** These surface as
   `VcsOperationException` or one of its subtypes, mapped from the underlying
   `czproject/git-php` `GitException` by `GitExceptionMap`. **The original
   `GitException` is always preserved as the `previous` throwable**, so you keep
   the real git cause for debugging.

```php
use ItPack\CommitIt\Common\CommitItException;
use ItPack\CommitIt\Domain\Exception\InvalidReferenceNameException;
use ItPack\CommitIt\Domain\Exception\RepositoryNotFoundException;
use ItPack\CommitIt\Domain\Exception\VcsOperationException;
use ItPack\CommitIt\Domain\Value\BranchName;

try {
    $repo->createBranch(BranchName::from('bad name'));
} catch (InvalidReferenceNameException $e) {
    // Caught at validation time (no git call made).
} catch (RepositoryNotFoundException | VcsOperationException $e) {
    // Backend failure. Inspect the real cause:
    $cause = $e->getPrevious(); // the original GitException, if any
} catch (CommitItException $e) {
    // Any other CommitIt error.
}
```

Rule of thumb: catch `InvalidArgumentException` subtypes for *input* problems,
and `VcsOperationException` (or its subtypes) for *operation* problems. Catch
`CommitItException` only when you want to treat every library error the same.

---

## 8. The `execute()` escape hatch

`Repository` exposes a generic passthrough:

```php
$output = $repo->execute('log', ['--oneline', '-5']); // joins lines with "\n"
```

This is **git-specific and NOT portable** — it talks to the git backend
directly and bypasses the vendor-neutral contract. Use it only when no dedicated
method fits (for example, setting a bare remote's `HEAD`, or running a plumbing
command). Prefer the typed methods for any logic you expect to survive a backend
swap.

---

## 9. Patterns and best practices

- **Always pass the factory.** `CommitIt` is intentionally backend-agnostic; the
  `RepositoryFactory` you supply is the backend. Reuse one instance.
- **Prefer typed value objects everywhere.** `BranchName::from('x')` validates
  once; downstream code can trust the value.
- **Compose `CommitOptions` per commit.** Author identity, dates, `--amend`, and
  `--allow-empty` are all expressed immutably and combined without mutation.
- **Instrument at the boundary.** Pass your `RepositoryObserver` to
  `CommitIt::open/init/clone` (or wrap with `ObservedRepository`) rather than
  sprinkling logging through your business code.
- **Keep portable code portable.** Reserve `execute()` for genuinely
  git-only needs; everything else should go through the `Repository` surface.
- **Catch at the right layer.** Validation errors are `InvalidArgumentException`;
  runtime git errors are `VcsOperationException`.

---

## 10. Common pitfalls

- **The factory is required.** `CommitIt::open/init/clone` take a
  `RepositoryFactory` as a mandatory argument — there is no global default
  backend.
- **Use named constructors, not `new`.** `BranchName`, `TagName`, `RemoteName`,
  `CommitId`, `CommitMessage`, `RepositoryPath`, and the `*Options` builders all
  use `::from` / `::fromParts` / `::default()`. Their constructors are private
  by design.
- **`checkoutBranch`, not `checkout`.** The method is `checkoutBranch(BranchName)`,
  and `createBranch` does *not* take a `checkout` flag — call
  `checkoutBranch` explicitly if you want to switch.
- **A bare remote with no commits has no branches.** After
  `InitOptions::default()->withBare()` the repository is empty: `listBranches()`
  returns `[]` until something is pushed. Cloning such a remote yields a
  repository whose `HEAD` is unborn, so `listBranches()` is also empty until you
  push or set the remote `HEAD` (e.g.
  `$repo->execute('symbolic-ref', ['HEAD', 'refs/heads/main'])`). Push a branch
  first to avoid the surprise.
- **`listWorktrees()` returns `WorktreeInfo` objects**, not raw strings; read
  `$wt->path`, `$wt->branch`, `$wt->isMain`.
- **Worktree scope.** A worktree path must sit inside the repository's parent
  directory or the call is rejected before git runs.

---

## 11. Runnable examples

The `examples/` directory contains self-contained, executable demonstrations
that build real temporary repositories under your system temp dir:

| File | Demonstrates |
|---|---|
| `examples/01_vertical_bootstrap_project.php` | init → commit → branch → tag, then read state back |
| `examples/02_vertical_collaborate_release.php` | bare remote → push → clone → branch → push |
| `examples/03_vertical_worktrees.php` | parallel worktrees and reopening one as a `Repository` |
| `examples/04_horizontal_observability.php` | `RepositoryObserver` timeline vs `NullRepositoryObserver` |
| `examples/05_horizontal_defense.php` | validation-first value objects and the exception taxonomy |
| `examples/06_horizontal_vendor_neutral.php` | one `inspect(Repository)` reused for `init` and `clone` |

Run any of them:

```bash
php examples/01_vertical_bootstrap_project.php
```

Each prints a narrated, color-coded log so you can see *what the API does*, not
just read code. They are excluded from the PHPStan/DepTrac/PHP-CS-Fixer gates,
so they stay readable rather than gate-compliant.
