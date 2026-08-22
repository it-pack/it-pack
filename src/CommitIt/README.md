# CommitIt

## A multipurpose VCS API and SDK for different VCS systems (starting with git).

Executes common VCS tasks with a vendor-agnostic API and SDK.

---

## Overview

CommitIt is the newest member of the **it-pack** family. It provides a
**vendor-neutral API and SDK** for everyday version-control operations. The first
and only backend in `1.0.0` is **git**, implemented as an adapter on top of
[`czproject/git-php`](https://github.com/czproject/git-php).

Design goals:

- **Vendor-neutral core.** Public concepts (`Repository`, `WorkingTree`, `Branch`,
  `Tag`, `Remote`, `Commit`) are not git slang. The git implementation is one
  adapter among (possible) others.
- **Hexagonal architecture.** Ports (`src/Contract`) are implemented by the git
  adapter (`src/Adapter/Git`); domain, application, and SDK layers never depend on
  the external library.
- **Execution-model ready.** Operations are observable through a narrow
  `RepositoryObserver` port, so a future RunIt adapter can map them to the shared
  it-pack execution model without coupling CommitIt to RunIt.
- **Modern PHP 8.4.** `readonly`, property hooks, immutable builders, and strict
  typing throughout.

> Status: implemented (1.0.0 scope). The architecture below is the one shipped in
> this repository; see [`docs/USER_GUIDE.md`](docs/USER_GUIDE.md) for how to use
> it, [`docs/IMPLEMENTATION_PLAN.md`](docs/IMPLEMENTATION_PLAN.md) for the full
> reasoning, [`docs/architecture/`](docs/architecture/) for the layer map, and
> [`docs/adr/`](docs/adr/) for the accepted decisions. Runnable demos live in
> [`examples/`](examples/).

---

## Architecture

```text
SDK  ──►  Application  ──►  Domain  ◄── Contract (ports)  ◄── Adapter/Git
```

| Layer | Responsibility |
|---|---|
| `Common` | Base marker exception and shared primitives. |
| `Contract` | Interfaces: `Repository`, `WorkingTree`, `RepositoryFactory`, `RepositoryObserver`. |
| `Domain` | Immutable value objects (`CommitId`, `Commit`, `BranchName`, `TagName`, `RemoteName`, `CommitMessage`, `RepositoryPath`) and validators. |
| `Application` | Option builders, the `ObservedRepository` decorator, and factory wiring. |
| `SDK` | Ergonomic facade (`CommitIt`) for programmatic use. |
| `Adapter/Git` | The only place `czproject/git-php` is used: translation, worktree support, and error mapping. |

Dependency direction is enforced by `deptrac.yaml`: adapters depend on contracts,
never the reverse.

---

## Usage

> For the full, in-depth walkthrough (workflows, observation, error handling,
> pitfalls, and runnable demos) see [`docs/USER_GUIDE.md`](docs/USER_GUIDE.md).
> Executable end-to-end scripts live in [`examples/`](examples/).

CommitIt is backend-agnostic: every entry point takes a `RepositoryFactory`,
and the git backend is `GitPhpRepositoryFactory`. Reference names and commit
messages are validated value objects built with named constructors.

### Opening, staging, and committing

```php
<?php

declare(strict_types=1);

use ItPack\CommitIt\Adapter\Git\GitPhpRepositoryFactory;
use ItPack\CommitIt\Domain\Option\CommitOptions;
use ItPack\CommitIt\Domain\Value\CommitMessage;
use ItPack\CommitIt\Domain\Value\RepositoryPath;
use ItPack\CommitIt\SDK\CommitIt;

$factory = new GitPhpRepositoryFactory();
$repo = CommitIt::open(RepositoryPath::from('/path/to/repo'), $factory);

$repo->stageAll();
$id = $repo->commit(
    CommitMessage::fromParts('Add feature', 'Optional longer body explaining the change.'),
    CommitOptions::default()->withAuthor('Ada Lovelace', 'ada@example.com'),
);
```

### Initializing and cloning

```php
<?php

declare(strict_types=1);

use ItPack\CommitIt\Adapter\Git\GitPhpRepositoryFactory;
use ItPack\CommitIt\Domain\Option\CloneOptions;
use ItPack\CommitIt\Domain\Option\InitOptions;
use ItPack\CommitIt\Domain\Value\RepositoryPath;
use ItPack\CommitIt\SDK\CommitIt;

$factory = new GitPhpRepositoryFactory();

$repo = CommitIt::init(
    RepositoryPath::from('/path/to/new'),
    $factory,
    InitOptions::default()->withInitialBranch('main'),
);

$clone = CommitIt::clone(
    'https://example.com/repo.git',
    RepositoryPath::from('/path/to/clone'),
    $factory,
    CloneOptions::default()->withBranch('main'),
);
```

### Branches, tags, remotes, and working trees

```php
<?php

declare(strict_types=1);

use ItPack\CommitIt\Domain\Value\BranchName;
use ItPack\CommitIt\Domain\Value\RemoteName;
use ItPack\CommitIt\Domain\Value\RepositoryPath;
use ItPack\CommitIt\Domain\Value\TagName;

$repo->createBranch(BranchName::from('feature'));
$repo->checkoutBranch(BranchName::from('feature'));

$repo->createTag(TagName::from('v1.0.0'));

$repo->addRemote(RemoteName::from('upstream'), 'https://example.com/upstream.git');
$repo->push(RemoteName::from('upstream'), BranchName::from('main'));

$worktree = $repo->getWorkingTree();
$worktree->add(RepositoryPath::from('/path/to/wt'), BranchName::from('wt-branch'));
foreach ($worktree->listWorktrees() as $wt) {
    // $wt->path, $wt->branch (?string), $wt->isMain (bool)
}
$worktree->remove(RepositoryPath::from('/path/to/wt'));
```

### Observation (RunIt-ready)

Every `Repository` operation can be observed via `RepositoryObserver`, applied
through the `ObservedRepository` decorator. A future `it-pack/commit-it-runit`
adapter implements this port to emit RunIt activities/occurrences; CommitIt
itself stays free of any RunIt dependency. Observation never changes an
operation's result, and observer failures are isolated on a separate,
non-recursive path.

```php
<?php

declare(strict_types=1);

use ItPack\CommitIt\Contract\RepositoryObserver;
use ItPack\CommitIt\SDK\CommitIt;

final class MyObserver implements RepositoryObserver
{
    public function onStart(string $operation, array $context): void {}
    public function onSuccess(string $operation, array $context): void {}
    public function onFailure(string $operation, \Throwable $error, array $context): void {}
}

$repo = CommitIt::open($path, $factory, new MyObserver());
```

---

## Security and Safety

- User-supplied paths, reference names, and commit messages are validated before
  reaching the backend.
- Backend exceptions (`GitException`) are mapped to domain exceptions and the
  original cause is preserved as `previous`.
- The `execute()` escape hatch is documented as git-specific and non-portable;
  command arguments rely on the runner's argument escaping.
- Commit-message bodies and file contents are never logged by default.

---

## Testing

- **Domain**: value-object and validator unit tests.
- **Adapter unit**: `czproject/git-php` `MemoryRunner` for fast, deterministic
  parsing/mapping tests without real git.
- **Adapter integration**: real temporary repositories in the Codeception
  `Functional` suite.
- **Quality gates**: PHPStan (`level: max`), DepTrac, PHP-CS-Fixer
  (`@PHP84Migration`), Infection.

---

## Roadmap to 1.0.0

1. Contracts and exceptions (`Common`, `Contract`).
2. Domain value objects and validators.
3. Git adapter (`GitPhpRepository`, `GitPhpRepositoryFactory`, `GitPhpWorkingTree`, `GitExceptionMap`).
4. Application layer (option builders, `ObservedRepository`, `RepositoryOpener`).
5. SDK facade (`CommitIt`).
6. Integration tests, documentation, and ADRs.

See [`docs/IMPLEMENTATION_PLAN.md`](docs/IMPLEMENTATION_PLAN.md) for the full
reasoning, open questions, and suggested ADRs.
