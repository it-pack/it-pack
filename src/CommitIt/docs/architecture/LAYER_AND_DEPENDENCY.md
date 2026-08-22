# CommitIt — Layered Architecture & Dependency Map

This document summarizes the architectural reasoning in
[`IMPLEMENTATION_PLAN.md`](../IMPLEMENTATION_PLAN.md) (Sections 3–4) and the
boundary rules enforced by `deptrac.yaml`. It is the canonical short reference
for where a given concept belongs.

## Dependency direction

```text
SDK (DX facade, fluent builders)
        │
        ▼
Application (factory wiring, observer decorator, option builders)
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

The dependency arrow always points **inward toward the domain and contracts**.
Adapters depend on contracts; contracts never depend on adapters. `czproject/git-php`
may be referenced *only* inside `src/Adapter/Git/`.

## Layers

| Layer | Responsibility | May depend on |
|---|---|---|
| `Common` | Marker exception (`CommitItException`) and shared primitives. | nothing internal |
| `Contract` | Interfaces: `Repository`, `WorkingTree`, `RepositoryFactory`, `RepositoryObserver` + the domain exception markers. | `Common`, `Domain` (value objects spoken by the ports, e.g. `Commit`, `BranchName`) |
| `Domain` | Immutable value objects (`CommitId`, `Commit`, `BranchName`, `TagName`, `RemoteName`, `CommitMessage`, `RepositoryPath`), pure validators (`RefNameValidator`, `CommitMessageValidator`), domain exceptions. | `Common`, `Contract` |
| `Application` | Option builders (`InitOptions`, `CloneOptions`, `CommitOptions`), the `ObservedRepository` decorator, `RepositoryOpener`. | `Common`, `Contract`, `Domain` |
| `SDK` | Ergonomic facade (`CommitIt`) for programmatic use. | `Common`, `Contract`, `Domain`, `Application` |
| `Adapter/Git` | The only place `czproject/git-php` is used: translation, worktree support, error mapping (`GitExceptionMap`). | `Common`, `Contract`, `Domain`, `czproject/git-php` |

## Boundary invariants

- **Adapters translate, they do not redefine.** The git adapter maps
  `czproject/git-php` to CommitIt contracts; it introduces no new domain semantics.
- **Value objects are immutable and validated.** Identifiers, names, paths, and
  messages are dedicated value objects (never bare strings) and are validated
  before reaching the backend.
- **Failures map to domain exceptions and preserve the original cause.** A
  `GitException` becomes a `VcsOperationException` (or narrower subtype) with the
  original attached as `previous`.
- **Instrumentation is a decorator.** Observation lives behind the
  `RepositoryObserver` port applied via `ObservedRepository`; the core repository
  and adapter stay clean and RunIt-free.
- **No global mutable state, no service locator, no hidden container.**

## Enforcement

- `deptrac.yaml` encodes the layer rules and is run as a quality gate; violations
  are fixed by correcting the dependency direction, never by adding skips.
- `PHPStan` runs at `level: max`; `PHP-CS-Fixer` applies `@PHP84Migration`
  (`declare_strict_types`, etc.).

See the ADRs under [`../adr/`](../adr/) for the accepted decisions behind these
boundaries.
