# AGENTS.md

## Purpose

This file guides AI coding agents working on **CommitIt** (`it-pack/commit-it`).

CommitIt provides a vendor-neutral API and SDK for common version-control operations,
shipping first with a **git** implementation built on `czproject/git-php`.

Use these instructions when implementing, reviewing, refactoring, testing, or
documenting the project. Keep changes small, explicit, and consistent with the
it-pack execution model and the agreed architecture in `docs/IMPLEMENTATION_PLAN.md`.

---

## Instruction Priority

Follow instructions in this order:

1. The current user or task request.
2. Repository-local instructions in the nearest `AGENTS.md`.
3. This file.
4. Existing architecture decisions and public contracts.
5. Existing code conventions.
6. General language and framework conventions.

If instructions conflict, preserve the higher-priority intent and make the smallest necessary adjustment.

Do not silently resolve significant ambiguity. State the issue and request clarification when it affects public APIs, domain semantics, security, or backward compatibility.

---

## Project Mission

CommitIt models **version control as a vendor-neutral operation set**:

```text
VCS
├── Repository
│   ├── WorkingTree
│   ├── Branch
│   ├── Tag
│   ├── Remote
│   └── Commit
└── Observer (operation lifecycle)
```

The governing principles are:

> Every operation happens on a repository.
> Repositories are addressed by validated paths.
> References (branches, tags) have validated names.
> Commits are immutable facts.
> Operations are observable but not coupled to any observer.
> The git implementation is one adapter, not the definition of the domain.

External technologies implement or transport parts of this model:

- `czproject/git-php` is the git adapter.
- RunIt is a *future* observer adapter (via `RepositoryObserver`).
- PSR-3 is a possible logging observer adapter.

Never redefine CommitIt domain semantics in terms of an external standard.

---

## Sources of Truth

Before making architectural changes, read the smallest relevant set of:

1. `docs/INITIAL_BRIEFING.md`
2. `docs/EXECUTION.md` (shared it-pack execution model)
3. `docs/IMPLEMENTATION_PLAN.md` (current architecture reasoning)
4. `README.md`
5. Relevant files under `docs/architecture/`
6. Relevant Architecture Decision Records under `docs/adr/`
7. Public contracts under `src/Contract/`
8. Tests covering the affected behavior
9. `composer.json`
10. `deptrac.yaml`
11. `phpstan.neon`

If a listed file does not exist, do not invent its contents. Use the available sources and mention the missing artifact when it blocks the task.

Public contracts, accepted ADRs, and tested behavior take precedence over incidental implementation details.

---

## Context-Efficient Workflow

Agents with any context-window size should work incrementally.

### Before editing

1. Read this file.
2. Inspect the requested area and its immediate dependencies.
3. Find existing contracts, implementations, tests, and documentation.
4. Identify whether the change affects:
   - Domain semantics.
   - Public API.
   - Serialization.
   - Adapter mapping.
   - Security.
   - Performance.
   - Backward compatibility.
5. Form a short implementation plan.

Do not load the entire repository unless the task genuinely requires it.

### While editing

- Make one coherent change at a time.
- Prefer modifying existing focused components over creating parallel abstractions.
- Keep patches small enough to review.
- Re-check relevant tests after each meaningful step.
- Preserve unrelated formatting and behavior.
- Do not refactor unrelated code opportunistically.

### Before finishing

1. Review the diff.
2. Remove accidental or speculative changes.
3. Run the narrowest relevant checks.
4. Run broader quality checks when available and proportionate.
5. Update tests and documentation.
6. Report what changed, what was validated, and any unresolved risk.

If context is limited, prioritize correctness in this order:

1. Domain invariants.
2. Public contracts.
3. Tests.
4. Security.
5. Architecture boundaries.
6. Documentation.
7. Internal cleanup.

---

## Architectural Boundaries

CommitIt uses hexagonal ports-and-adapters architecture, enforced by `deptrac.yaml`.

```text
SDK / Consumer Adapters
          │
          ▼
Application
          │
          ▼
Domain

Infrastructure Adapters ──► Ports (Contracts) ◄── Application
```

Expected dependency direction:

```text
Common       → (nothing internal)
Contract     → Common, Domain
Domain       → Common, Contract
Application  → Common, Contract, Domain
SDK          → Common, Contract, Domain, Application
Adapter      → Common, Contract, Domain, czproject/git-php
```

### Domain must not depend on

- Application services.
- SDK conveniences.
- Infrastructure adapters.
- `czproject/git-php`.
- RunIt or the it-pack execution runtime.
- PSR interfaces.
- Frameworks.
- Filesystems or process runners (validation is pure; path resolution may use the
  filesystem but must stay side-effect free beyond resolution).
- Network clients.

### Application must not depend on

- SDK facades.
- Concrete external adapters (`czproject/git-php`).
- Framework service providers.
- Vendor-specific VCS implementations.

### Adapter rules

Adapters may translate VCS concepts but must not change their meaning.

- Keep all `czproject/git-php` usage inside `src/Adapter/Git/`.
- Map `GitException` to domain exceptions and preserve the original as `previous`.
- Do not add adapter-specific fields to core domain objects solely to satisfy git.
- Prefer composition over subclassing `czproject` classes (e.g. worktrees are
  invoked through the repository's `execute()` passthrough, not by extending
  `GitRepository`).

DepTrac rules are architectural requirements, not optional style guidance.

---

## Domain Invariants

Preserve these invariants unless an accepted ADR explicitly changes them.

### Repository

- A repository is addressed by exactly one validated `RepositoryPath`.
- A repository belongs to exactly one VCS backend (git in 1.0.0).
- Opening a path without a valid repository fails fast, never silently initializing.
- Terminal/atomic operations (commit, push) either succeed completely or roll back
  observable state.

### Commit

- A commit is an immutable fact.
- A commit id is a validated, opaque string (git: 40-hex SHA-1).
- A commit's author/committer metadata is read-only once captured.

### Reference names (Branch / Tag / Remote)

- Reference names are validated before use (`RefNameValidator`).
- Invalid names must be rejected with `InvalidReferenceNameException`, not passed
  to the backend.
- Names are extensible validated strings, not closed enums.

### WorkingTree

- Working-tree operations target paths resolved relative to the repository root.
- A worktree path must never escape the repository's parent scope without explicit,
  validated intent.
- Adding/removing a worktree must not corrupt the primary checkout.

### Observer

- The `RepositoryObserver` is a non-intrusive hook applied via a decorator.
- The core repository and adapter must not depend on any concrete observer.
- A failed operation notifies `onFailure` and preserves the original `Throwable`.

---

## PHP Standards

Target PHP `>= 8.4`.

Every PHP file must begin with:

```php
<?php

declare(strict_types=1);
```

### Classes and contracts

- Every concrete class must be `final`.
- Use `readonly` whenever controlled mutation is unnecessary.
- Every concrete class must implement an appropriate interface.
- Required exceptions must implement the relevant CommitIt exception marker interface.
- **Interface-backing carve-out (intentional):** value objects (`Domain\Value\*`),
  option builders (`Domain\Option\*`), and `@internal` adapters helpers such as
  `GitExceptionMap`/`GitExceptionRunner`/`LocalePinningRunner` are deliberately
  **not** interface-backed. An interface there would be YAGNI and contradict the
  "do not create interfaces solely to duplicate" rule. Ports, decorators,
  factories, and public exceptions remain interface-backed (which they already
  are). See `docs/REVIEW.md` §5 for the audit.
- Prefer composition over inheritance.
- Keep interfaces small and focused.
- Do not create interfaces solely to duplicate another interface without semantic value.
- Traits must harmonize with interfaces and remain focused.
- Avoid stateful traits and hidden dependencies.

### Types

- Use native parameter, property, and return types.
- Avoid implicit `mixed`.
- Add PHPDoc generics where native PHP cannot express a relationship.
- Do not use PHPDoc to contradict native types.
- Use dedicated value objects for identifiers, names, paths, and validated concepts.
- Use `null` for optional values.
- Use property hooks for value objects and back them with respective contracts.
- Prefer extensible validated strings over closed enums for user-defined names and kinds.
- Use enums for genuinely closed internal state sets.
- Ensure interfaces only depend on interfaces/traits and no concrete classes.
- Use `never` for exceptions that should never be thrown.
- Use `object` for untyped objects.
- Use `array` for untyped arrays.
- Use `string` for untyped strings.
- Use `int` for untyped integers.
- Use `float` for untyped floats.
- Use `bool` for untyped booleans.
- Use `resource` for untyped resources.
- Use `callable` for untyped callables.
- Use `Stringable` for untyped stringable objects.
- Use `iterable` for untyped iterable objects.
- Use `Traversable` for untyped traversable objects.
- Use `Generator` for untyped generator objects.
- Use `Iterator` for untyped iterator objects.
- Use `IteratorAggregate` for untyped iterator aggregates.
- Use `Countable` for untyped countable objects.
- Use `ArrayAccess` for untyped array-like objects.
- Use `JsonSerializable` for untyped JSON serializable objects.
- Use `__serialize` and `__deserialize` over the deprecated `Serializable` for untyped serializable objects.

### PHP 8.4 features

Use modern features only when they improve clarity:

- Property hooks for clear validation or computed properties.
- Virtual properties for intentional projections.
- Lazy objects for expensive optional infrastructure.
- `WeakMap` for object-keyed, non-owning runtime associations.
- `WeakReference` only where correctness does not depend on object lifetime.

Do not use a language feature merely because it is available.

---

## API Design

CommitIt is a library and SDK for both ItPack and third-party consumers.

Public APIs must be:

- Vendor-neutral.
- Framework-neutral.
- Explicitly typed.
- Composable.
- Testable.
- Difficult to misuse.
- Stable enough for independent adapters.

### Prefer

- Interfaces at architectural boundaries (`Repository`, `WorkingTree`, `RepositoryFactory`, `RepositoryObserver`).
- Immutable definitions and artifacts (`Commit`, value objects).
- Named constructors (`CommitMessage::fromParts()`, `RepositoryPath::from()`).
- Factories for infrastructure assembly (`GitPhpRepositoryFactory`).
- Immutable builders for ergonomic configuration (`InitOptions`, `CloneOptions`, `CommitOptions`).
- Decorators for non-intrusive instrumentation (`ObservedRepository`).
- Explicit scope arguments.
- Narrow observer interfaces.
- Ports for process execution, path resolution, and observation.

### Avoid

- Global facades.
- Service locators.
- Hidden container access.
- Mandatory inheritance.
- Reflection on hot paths.
- Generic "manager" or "helper" classes.
- Large configuration arrays without typed builders.
- Boolean parameters with unclear meaning.
- Public APIs coupled to one adapter.
- Proprietary promises or schedulers.

Do not add a public extension point until there is a concrete consumer or adapter use case.

---

## Naming

Use domain terminology consistently:

| Use | Avoid as a synonym |
|---|---|
| Repository | Repo wrapper, git handle |
| WorkingTree | Worktree (git slang), checkout |
| Branch | Ref (when ambiguous) |
| Commit | Revision, changeset |
| CommitId | Hash, sha |
| Reference name | Ref string |

Names should communicate one responsibility:

```text
GitPhpRepository
GitPhpRepositoryFactory
GitPhpWorkingTree
RepositoryOpener
ObservedRepository
RefNameValidator
CommitMessageValidator
```

Avoid vague names such as:

```text
GitManager
VcsHelper
DataProcessor
CommonService
Utility
```

---

## External Adapter Rules

### Git (`czproject/git-php`)

- Keep `czproject/git-php` usage inside `src/Adapter/Git/`.
- Wrap the `Git`/`GitRepository` runner; prefer injecting an `IRunner` so tests can
  use `MemoryRunner`.
- Map `GitException` to a domain exception and preserve it as `previous`.
- Do not subclass `czproject` classes to add features; compose and use the public
  `execute()`/`run()` passthrough (e.g. for worktrees).
- Apply path and reference validation before invoking the backend.

### RunIt (future, optional)

- CommitIt must not depend on RunIt.
- A future `it-pack/commit-it-runit` package implements `RepositoryObserver` and
  maps operations to RunIt activities/occurrences.
- Keep the observer vocabulary (operation name + structured context) mappable to
  RunIt's Activity/Observation/Occurrence triad without coupling.

### PSR-3 (optional logging observer)

- A logging observer may implement `RepositoryObserver` and map lifecycle events to
  PSR-3 log calls.
- Never log full commit-message bodies or file contents by default.

---

## Security Requirements

Treat repository paths, reference names, commit messages, and file contents as
potential injection or data-exfiltration vectors.

Do not:

- Pass unvalidated user input to git subprocesses unchecked.
- Allow absolute paths to escape the repository scope for stage/remove.
- Log secrets embedded in commit messages, paths, or ref names by default.
- Silently overwrite an existing non-empty directory on clone/init.
- Expose or corrupt `.git`.
- Create unbounded buffers of git output in core services.

When adding fields or mappings, consider:

1. Classification.
2. Injection surface.
3. Maximum size.
4. Path scope / traversal.
5. Serialization safety.
6. Tenant isolation (shared workers cloning untrusted URLs).

Security-sensitive behavior requires negative tests (injection-shaped inputs,
malicious paths, escape attempts).

---

## Performance Requirements

Assume repository operations may be called repeatedly in loops or workers.

Avoid:

- Filesystem or process operations in core domain validation beyond path resolution.
- Reflection during normal execution.
- Eager serialization of commit metadata.
- Eager worktree listing.
- Unbounded recursion, nesting, or buffering.
- Per-operation initialization of expensive adapters.

Prefer:

- Injecting a shared `IRunner` rather than constructing one per call.
- Lazy objects for expensive optional infrastructure (e.g. observer wiring).
- Reusable immutable validators and mappers.
- Deferred metadata loading (load commit details on demand, not on list).
- Bounded parsing of command output.

Performance changes require benchmarks when they affect:

- Repository open/init/clone.
- Commit or stage operations.
- Worktree listing.
- Path resolution.
- Bulk ref enumeration.

Do not claim a performance improvement without measurements.

---

## Self-Instrumentation

CommitIt should support observation of its own operations via `RepositoryObserver`,
but it must not recursively observe the observer.

Preserve these rules:

- Apply observation through the `ObservedRepository` decorator, not inside the core.
- Send observer failures to a separate, non-recursive path (the observer itself
  must not trigger further observed operations).
- Keep the bootstrap / factory path intentionally uninstrumented.
- Avoid observation that materially changes hot-path behavior.

---

## Testing Requirements

Every behavior change requires tests at the narrowest appropriate level.

### Test categories

- Domain invariant tests.
- Value-object validation tests (`RefNameValidator`, `CommitMessageValidator`, `CommitId`).
- Port contract tests (reused by the git adapter conformance suite).
- Adapter unit tests (via `czproject` `MemoryRunner`).
- Adapter integration tests (real temp repositories in the Functional suite).
- Serialization / value-object round-trip tests.
- Failure-injection tests (`GitExceptionMap`, observer `onFailure`).
- Observer/decorator tests.
- Property-based tests where useful (ref-name fuzzing).
- Concurrency isolation tests (repeated operations in persistent workers).
- Mutation tests for critical branching.

### Test-framework neutrality

Public adapter conformance tooling must not require consumers to inherit from a specific test framework base class.

Prefer:

- Fixture interfaces.
- Verifier objects.
- Behavior harnesses.
- Violation/result collections.
- Factories supplied by adapter authors.

### Critical cases

Always test relevant combinations of:

- Success.
- Original operation failure.
- Adapter failure (`GitException`).
- Invalid reference name.
- Missing repository path.
- Dirty working tree blocking an operation.
- Worktree add/remove/list.
- Clone into existing non-empty directory.
- Observer `onStart` / `onSuccess` / `onFailure` dispatch.
- `NullRepositoryObserver` no-op behavior.
- Concurrency isolation.

A test should verify observable behavior, not private implementation details.

---

## Quality Gates

Inspect `composer.json` before running commands. Use repository-defined scripts when available.

Expected tools include:

- PHPStan (configured at `level: max`).
- DepTrac.
- PHP-CS-Fixer (`@PHP84Migration`).
- Infection.
- Composer validation and audit.
- Unit and integration test runners.

Typical checks may include:

```bash
composer validate
composer test
composer analyse
composer architecture
composer coding-style
composer mutation
composer audit
composer quality
```

Do not assume these scripts exist. Verify them first. Wire the missing scripts into
`composer.json` before implementation begins.

### PHPStan

- Target the highest configured level (`max`).
- Do not add first-party errors to a baseline.
- Do not suppress an error without a specific documented reason.
- Prefer correcting types and design over adding broad ignores.

### DepTrac

- Do not add skips to hide an architectural violation.
- Correct the dependency direction.
- If a boundary must change, update the architecture and ADR first.

### Mutation testing

Prioritize mutation coverage for:

- Reference-name validation.
- Commit-message validation.
- `GitExceptionMap` error mapping.
- Observer dispatch branching.
- Path resolution/scope checks.

---

## Documentation Requirements

Update documentation when changing:

- Public APIs.
- Domain semantics.
- Reference-name or commit-message rules.
- Adapter mappings.
- Observer contract.
- Security defaults.
- Configuration.
- Installation requirements.

Examples must:

- Use current public APIs.
- Include `declare(strict_types=1)`.
- Be minimal but executable where practical.
- Avoid imaginary framework setup.
- Demonstrate CommitIt terminology rather than adapter terminology.

Create or update an ADR when a change affects:

- Aggregate boundaries.
- Dependency direction.
- Public extension points.
- Observer / RunIt integration semantics.
- Security defaults.
- Serialization compatibility.

---

## Backward Compatibility

Before `1.0.0`, breaking changes may be necessary but must be deliberate and documented.

After `1.0.0`, treat these as compatibility-sensitive:

- Public interfaces and signatures.
- Public classes and enums.
- Value-object normalization fields.
- Adapter extension contracts.
- Exception types exposed to consumers.
- The `RepositoryObserver` port.

Do not casually add methods to public interfaces. Adding an interface method breaks third-party implementations.

Prefer introducing a new focused interface and composing it into new APIs.

---

## Change Discipline

### Do

- Solve the requested problem directly.
- Reuse established domain language.
- Add tests before or with behavior changes.
- Preserve original exceptions.
- Keep adapters optional.
- Use ports for external effects.
- Explain non-obvious decisions.
- Update affected documentation.

### Do not

- Rewrite unrelated code.
- Add dependencies without justification.
- Introduce a framework requirement.
- Introduce global mutable state.
- Expose adapter types from domain contracts.
- Add speculative abstractions.
- Hide errors with broad static-analysis ignores.
- Disable architecture rules.
- Regenerate baselines to make CI pass.
- Change serialized formats accidentally.
- Commit generated artifacts unless the repository requires them.
- Claim checks passed when they were not run.

---

## When to Stop and Ask

Request clarification before proceeding when:

- `docs/IMPLEMENTATION_PLAN.md`, an ADR, and public code disagree materially.
- A change would break a stable public contract.
- Required security or path-scope behavior is unknown.
- Delivery guarantees are unclear.
- A new dependency or package split is required.
- The task requires choosing between incompatible VCS semantics.
- Existing tests encode contradictory behavior.
- The requested change would couple the domain to an external adapter.
- Required project files or specifications are missing.

If clarification is unavailable, present:

1. The unresolved question.
2. The smallest safe assumption.
3. The consequences of that assumption.
4. The reversible implementation option.

---

## Completion Report

When returning a completed implementation task, report concisely:

- What changed.
- Why it changed.
- Tests and quality checks run.
- Checks not run and why.
- Public API or compatibility impact.
- Remaining risks or follow-up work.

Do not include large code dumps when the changes already exist in the working tree.

---

## Final Review Checklist

Before considering a change complete, verify:

- [ ] The change follows the CommitIt VCS vocabulary.
- [ ] Domain semantics remain vendor-neutral.
- [ ] Dependency direction is correct (per DepTrac).
- [ ] Concrete classes are `final`.
- [ ] Eligible classes are `readonly`.
- [ ] Concrete classes implement appropriate interfaces.
- [ ] Value objects and commits remain immutable.
- [ ] Original backend exceptions are preserved as `previous`.
- [ ] External effects use ports.
- [ ] Sensitive data (paths, messages) is classified and not logged by default.
- [ ] Buffers, payloads, and path scope are bounded.
- [ ] Relevant tests were added or updated.
- [ ] Architecture and static-analysis checks pass where available.
- [ ] Documentation reflects public behavior.
- [ ] No unrelated changes were introduced.
- [ ] Any unverified assumption is explicitly reported.
