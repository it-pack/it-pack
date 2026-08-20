# AGENTS.md

## Purpose

This file guides AI coding agents working on **CommandIt** (`it-pack/command-it`).

CommandIt provides command creation and execution for the ItPack ecosystem: commands are defined once
and executed either programmatically (a CQRS-style command bus) or through CLI adapters (Symfony
Console and others).

Use these instructions when implementing, reviewing, refactoring, testing, or documenting the project.
Keep changes small, explicit, and consistent with the execution model and the architectural boundaries
below.

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

Do not silently resolve significant ambiguity. State the issue and request clarification when it affects
public APIs, domain semantics, security, or backward compatibility.

---

## Project Mission

CommandIt models **command execution itself** as a specialization of the It_Pack execution model:

```text
Command
├── CommandExecution (an Execution)
│   ├── CommandContext
│   ├── Activity (handler work, optionally nested)
│   ├── Measurement (duration, counts)
│   ├── Observation (logs, notices)
│   ├── Occurrence (CommandReceived, CommandHandled, CommandFailed)
│   └── CommandResult (the Snapshot)
```

The governing principles (inherited from `docs/EXECUTION.md`):

> Everything happens within an execution.
> Execution carries context.
> Activities perform work.
> Measurements quantify work.
> Observations describe work.
> Occurrences record work.
> Snapshots preserve work.

External technologies implement or transport parts of this model:

- Symfony Console is an adapter (CLI projection).
- PSR-3 is an adapter (observations → logs).
- PSR-14 is an adapter (occurrences → events).
- RunIt is a **future** adapter/integration target, not a current dependency.

Never redefine CommandIt domain semantics in terms of an external standard.

---

## Sources of Truth

Before making architectural changes, read the smallest relevant set of:

1. `docs/EXECUTION.md`
2. `docs/IMPLEMENTATION_PLAN.md`
3. `README.md`
4. Relevant files under `docs/architecture/`
5. Public contracts under `src/Contract/`
6. Tests covering the affected behavior
7. `composer.json`
8. `deptrac.yaml`
9. `phpstan.neon.dist`

If a listed file does not exist, do not invent its contents. Use the available sources and mention the
missing artifact when it blocks the task.

Public contracts, accepted plans/ADRs, and tested behavior take precedence over incidental
implementation details.

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

CommandIt uses hexagonal ports-and-adapters architecture.

```text
SDK / Consumer Adapters
          │
          ▼
Application (Core)
          │
          ▼
Domain

Infrastructure Adapters ──► Ports ◄── Application
```

Expected dependency direction:

```text
Domain       → Contracts
Application  → Contracts, Domain, Behavior, ValueObject
SDK          → Contracts, Domain, Application, ValueObject
Adapters     → Contracts, Domain, Ports, external APIs
```

### Domain must not depend on

- Application services.
- SDK conveniences.
- Infrastructure adapters.
- Symfony Console.
- RunIt (current or future) — integration is via ports only.
- PSR interfaces (they are adapters).
- Frameworks.
- Fibers or coroutine runtimes (use the `RuntimeContextDetectorInterface` port).
- Containers or service locators.
- Filesystems or network clients.

### Application must not depend on

- SDK facades.
- Concrete external adapters.
- Framework service providers.
- Vendor-specific CLI or telemetry implementations.

### Adapter rules

Adapters may translate CommandIt concepts but must not change their meaning.

Keep mapping logic inside the relevant adapter. Do not add vendor-specific fields to core domain
objects solely to satisfy an adapter.

DepTrac rules are architectural requirements, not optional style guidance.

---

## Domain Invariants

Preserve these invariants unless an accepted plan/ADR explicitly changes them.

### Command

- A command is an immutable intent carrying validated input.
- A command maps to exactly one `CommandExecution`.
- A command never retains a mutable execution session.
- A command's name is a validated, extensible string (not a closed enum).

### CommandExecution

- A command execution represents one logical command run (an Execution).
- Every artifact belongs to exactly one execution.
- An execution has at most one terminal outcome (`SUCCEEDED` | `FAILED` | `CANCELLED`).
- Terminal executions cannot transition again.
- Nested activities use explicit parent references.
- Ownership is represented by identity and relationships, not mandatory in-memory collections.

### CommandContext

- Execution context is immutable.
- Changes produce a new context.
- Context values have explicit classification and propagation policy.
- Secret values are never exported.
- Ambient context is convenience infrastructure, not the canonical domain API.
- Global mutable execution state is prohibited (use fiber-bound or request-scoped storage).

### Activity

- Activities are bounded units of work (the handler, and its sub-steps).
- An activity belongs to one execution.
- A nested activity references its parent activity explicitly.
- A completed activity cannot be reopened.

### Measurement

- Measurements are quantitative.
- Each measurement has a name, numeric value, and unit.
- High-cardinality values must not become dimensions by default.
- Duration uses a monotonic clock.

### Observation

- Observations are qualitative.
- Prefer stable message templates and structured attributes.
- Throwable data must be safely normalized.
- Observations must not become occurrences merely because they are important.

### Occurrence

- Occurrences are immutable facts (`CommandReceived`, `CommandHandled`, `CommandFailed`).
- Occurrence types are stable, lowercase, and versioned.
- Occurrence types are independent of PHP class names.
- CloudEvents / PSR-14 are representations of occurrences, not their domain definition.

### CommandResult (Snapshot)

- Results are immutable.
- Result serialization is explicit and deterministic.
- Arbitrary PHP object serialization is prohibited.
- Result persistence is delegated to a port.

---

## Command Lifecycle

Preserve the command state machine:

```text
DEFINED
   │  (validated)
   ▼
RECEIVED          ──► occurrence: CommandReceived
   │
   ▼
EXECUTING         ──► activity begins, measurement starts
   ├──► SUCCEEDED ──► occurrence: CommandHandled, snapshot: CommandResult
   ├──► FAILED    ──► occurrence: CommandFailed, original Throwable preserved
   └──► CANCELLED ──► (optional, via Cancellation port)
```

Lifecycle behavior must be:

- Explicit.
- Validated.
- Idempotent where documented.
- Safe during exceptions.
- Testable without external infrastructure.

When a handler throws:

1. Record failure safely (occurrence: `CommandFailed`).
2. Resolve active activities according to policy.
3. Complete the execution as failed.
4. Restore prior context in `finally`.
5. Rethrow the **original** `Throwable`.

A reporter or adapter exception must not replace the original business exception.

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
- Exceptions must implement the relevant CommandIt exception marker interface.
- Prefer composition over inheritance.
- Keep interfaces small and focused.
- Do not create interfaces solely to duplicate another interface without semantic value.
- Traits must harmonize with interfaces and remain focused.
- Avoid stateful traits and hidden dependencies.

### Types

- Use native parameter, property, and return types.
- Avoid implicit `mixed` where a narrower type fits; use PHPDoc generics where native PHP cannot
  express a relationship.
- Do not use PHPDoc to contradict native types.
- Use dedicated value objects for identifiers, names, and validated concepts.
- Use `null` for optional values.
- Use property hooks for value objects and computed projections, backed by respective contracts.
- Prefer extensible validated strings over closed enums for user-defined names and kinds.
- Use enums for genuinely closed internal state sets (`CommandStatus`, `CommandResultStatus`, `InputKind`).
- Ensure interfaces only depend on interfaces/traits and no concrete classes.
- Use `never` for methods that should never be reached.
- Use `object` for untyped objects, `array` for untyped arrays, etc., per the standard untyped vocabulary.

### PHP 8.4+ features

Use modern features only when they improve clarity, isolation, or performance:

- **Property hooks** for clear validation or computed properties (e.g. `CommandDefinition::signature`
  computed from name/args/options; value-object normalization via `set` hooks).
- **Lazy objects** (`newLazyGhost`/`newLazyProxy`) for expensive optional handler infrastructure, behind
  `HandlerResolverInterface`.
- **WeakMap / WeakReference** for object-keyed, non-owning runtime associations (per-Fiber context,
  command→execution metadata). Never rely on object lifetime for correctness where a strong reference is
  required.
- **Fibers** through the `RuntimeContextDetectorInterface` port for context isolation in coroutine
  runtimes. Do not store request context in process-global mutable state.
- **Traits** (`Behavior/Domain/*`) for stateless cross-cutting domain rules.

Do not use a language feature merely because it is available.

---

## API Design

CommandIt is a library and SDK for both ItPack and third-party consumers.

Public APIs must be:

- Vendor-neutral.
- Framework-neutral.
- Explicitly typed.
- Composable.
- Testable.
- Difficult to misuse.
- Stable enough for independent adapters.

### Prefer

- Interfaces at architectural boundaries.
- Immutable definitions and artifacts.
- Named constructors.
- Factories for infrastructure assembly.
- Immutable builders for ergonomic command/definition configuration (`SDK/CommandDefinitionBuilder`).
- Decorators for non-intrusive instrumentation (middleware).
- Explicit scope arguments.
- Narrow handler/recorder interfaces.
- Ports for time, identity, serialization, publication, and context storage.

### Avoid

- Global facades.
- Service locators.
- Hidden container access.
- Mandatory inheritance.
- Reflection on hot paths.
- Generic "manager" or "helper" classes.
- Large configuration arrays without typed builders.
- Boolean parameters with unclear meaning.
- Public APIs coupled to one adapter (e.g. Symfony Console).
- Proprietary promises or schedulers.

Do not add a public extension point until there is a concrete consumer or adapter use case.

---

## Naming

Use domain terminology consistently:

| Use | Avoid as a synonym |
|---|---|
| Command | Message, request, action |
| CommandHandler | Listener, subscriber, controller |
| CommandBus | Dispatcher, mediator |
| CommandExecution | Trace, transaction |
| CommandResult | Response, return value |
| Argument | Parameter, input flag |
| Option | Flag, switch |
| CommandContext | Baggage, metadata bag |

Adapter documentation may explain mappings, but core APIs must retain CommandIt vocabulary.

Names should communicate one responsibility:

```text
CommandExecutor
HandlerResolver
CommandRegistry
CommandInputParser
ExecutionReporter
ContextStorage
LazyHandlerProxy
CommandDefinitionBuilder
```

Avoid vague names such as:

```text
CommandManager
CliHelper
DataProcessor
CommonService
Utility
```

## Vocabulary Alignment

The shared It_Pack vocabulary in `docs/EXECUTION.md` is the canonical reference for execution concepts.
`vocab/run-it.ttl` (when present in the wider ecosystem) is the canonical OWL vocabulary for RunIt domain
concepts; `vocab/run-it-taxonomy.ttl` is the companion SKOS taxonomy.

When adding or materially changing a public domain contract, value object, serialized field, standard
name, command name, argument/option name, occurrence type, or its documentation, reference the
corresponding vocabulary resource:

- In implementation code, use a PHPDoc `@see` reference to the canonical term IRI where a corresponding
  term exists.
- In public documentation, link to the relevant vocabulary file and, when useful, its canonical IRI.
- Keep the reference adjacent to the defining contract or concept; do not add RDF concerns to the
  runtime model or make the vocabulary a runtime dependency.

If no suitable term exists, add or revise the vocabulary and its documentation in the same change before
using a new public term. Taxonomy entries are recommended and extensible unless explicitly closed.

---

## External Adapter Rules

### Symfony Console (CLI)

- Keep `symfony/console` an optional `suggest` dependency; core never imports it.
- Map `CommandDefinition` → Symfony `Command`; arguments/options → input definitions.
- `execute()` dispatches through the `CommandBus` and writes the `CommandResult` to output.
- Do not leak execution context across CLI invocations in persistent workers.

### PSR-3

- Map observations to logger messages and context.
- Keep message templates stable.
- Put the throwable under the expected exception context key where appropriate.
- Do not turn every log into an occurrence.

### PSR-14

- Account for synchronous dispatch and listener exceptions.
- Apply the configured publication failure policy.
- Do not allow PSR event mutability to mutate immutable CommandIt occurrences.

### RunIt (future integration)

- Integration is via `Contract/Port` (reporter, context provider, storage, runtime detector).
- A future `it-pack/command-it-run-it` package implements these ports to feed RunIt's pipeline.
- CommandIt must not import RunIt types; the `CommandExecution` shape is intentionally RunIt-like but
  self-contained.
- Do not change serialized formats to chase a guessed RunIt API; absorb mismatches in the adapter.

### Fibers, Swoole, OpenSwoole, and Coroutines

- Store context per Fiber or coroutine via `WeakMap` (`FiberBoundContextStorage`).
- Never store request context in process-global mutable state.
- Restore or clear context in `finally`.
- Test repeated executions in persistent workers.
- Keep runtime-specific APIs out of domain and application contracts.

---

## Publication and Failure Policy

Command artifacts follow this conceptual pipeline:

```text
Validation
→ Enrichment
→ Classification
→ Redaction
→ Sampling
→ Routing
→ Adapter publication
```

Requirements:

- Redact before external publication.
- Bound buffers and queues.
- Define overflow behavior.
- Prevent recursive publication.
- Preserve the business result.
- Preserve the original business exception.
- Make fail-closed behavior explicit.

Default behavior is expected to be fail-open with fallback reporting.

Never catch and silently discard a publication failure without applying the configured policy or
recording it through the recursion-safe health path.

---

## Security Requirements

Treat context, observations, occurrences, and results as potential data-exfiltration paths.

Do not:

- Export secrets.
- Capture all environment variables.
- Serialize arbitrary objects.
- Export unlimited stack traces.
- Create unbounded attributes or payloads.
- Use user-controlled values as metric names.
- Use high-cardinality values as metric dimensions by default.
- Retain execution context between persistent-worker requests.

When adding fields or mappings, consider:

1. Classification.
2. Redaction.
3. Maximum size.
4. Cardinality.
5. Serialization safety.
6. Tenant isolation.
7. Propagation boundaries.

Security-sensitive behavior requires negative tests.

---

## Performance Requirements

Assume command dispatch and handler APIs may run on hot paths.

Avoid:

- Network or filesystem operations in core services.
- Reflection during normal execution.
- Eager serialization.
- Eager handler instantiation.
- Retaining all artifacts by default.
- Unbounded recursion, nesting, or buffering.
- Per-command initialization of expensive adapters.

Prefer:

- Monotonic clocks for durations.
- Deferred normalization.
- Lazy handler resolution (`LazyHandlerProxy`).
- Reusable immutable mappers and policies.
- Bounded buffering.
- Sampling and filtering before expensive work.

Performance changes require benchmarks when they affect:

- Command dispatch.
- Handler resolution.
- Context storage/lookup.
- Publication.
- Serialization.
- Fiber or coroutine switching.

Do not claim a performance improvement without measurements.

---

## Self-Instrumentation

CommandIt should use its own model for operational activities, but it must not recursively observe
publication.

Preserve these rules:

- Use a publication recursion guard.
- Send nested publication failures to a separate health reporter.
- Do not route health reporting back through the failing publisher.
- Instrument executor batches or flushes rather than every internal method.
- Keep the bootstrap path intentionally uninstrumented.
- Avoid self-instrumentation that materially changes hot-path behavior.

---

## Testing Requirements

Every behavior change requires tests at the narrowest appropriate level.

### Test categories

- Domain invariant tests.
- Lifecycle tests.
- Application service (executor) tests.
- Port contract tests.
- Adapter integration tests (Symfony Console, CLI argv).
- Serialization round-trip tests.
- Failure-injection tests.
- Property-based tests where useful.
- Fiber or coroutine isolation tests.
- Persistent-worker leak tests.
- Mutation tests for critical branching.
- Benchmarks for hot-path changes.

### Test-framework neutrality

Public adapter conformance tooling must not require consumers to inherit from a specific test framework
base class.

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
- Adapter failure.
- Nested execution/activities.
- Context restoration.
- Repeated completion.
- Cancellation or timeout.
- Redaction.
- Buffer overflow.
- Recursive publication.
- Concurrency / Fiber isolation.

A test should verify observable behavior, not private implementation details.

---

## Quality Gates

Inspect `composer.json` before running commands. Use repository-defined scripts when available.

Expected tools include:

- PHPStan.
- DepTrac.
- PHP-CS-Fixer.
- Infection.
- Composer validation and audit.
- Backward compatibility checks.
- Unit and integration test runners.

Typical checks mirror the family (see RunIt/ConfigureIt):

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

Do not assume these scripts exist. Verify them first.

### PHPStan

- Target the highest configured level.
- Do not add first-party errors to a baseline.
- Do not suppress an error without a specific documented reason.
- Prefer correcting types and design over adding broad ignores.

### DepTrac

- Do not add skips to hide an architectural violation.
- Correct the dependency direction.
- If a boundary must change, update the architecture and plan/ADR first.

### Mutation testing

Prioritize mutation coverage for:

- State transitions.
- Failure policies.
- Context restoration.
- Redaction.
- Sampling.
- Validators.
- Adapter mappings.

---

## Documentation Requirements

Update documentation when changing:

- Public APIs.
- Domain semantics.
- Lifecycle behavior.
- Context fields.
- Serialization.
- Occurrence naming.
- Adapter mappings.
- Failure policy.
- Security defaults.
- Configuration.
- Installation requirements.

Examples must:

- Use current public APIs.
- Include `declare(strict_types=1)`.
- Be minimal but executable where practical.
- Avoid imaginary framework setup.
- Demonstrate CommandIt terminology rather than adapter terminology.

Create or update a plan/ADR when a change affects:

- Aggregate boundaries.
- Dependency direction.
- Public extension points.
- Lifecycle semantics.
- Failure guarantees.
- Context propagation.
- Package splitting.
- Security defaults.
- Serialization compatibility.

---

## Backward Compatibility

Before `1.0.0`, breaking changes may be necessary but must be deliberate and documented.

After `1.0.0`, treat these as compatibility-sensitive:

- Public interfaces and signatures.
- Public classes and enums.
- Artifact normalization fields.
- Serialized schemas marked stable.
- Occurrence type conventions.
- Adapter extension contracts.
- Exception types exposed to consumers.

Do not casually add methods to public interfaces. Adding an interface method breaks third-party
implementations.

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
- Depend on RunIt before it is stable.

---

## When to Stop and Ask

Request clarification before proceeding when:

- `docs/EXECUTION.md`, the plan, and public code disagree materially.
- A change would break a stable public contract.
- Required security or data-classification behavior is unknown.
- Delivery guarantees are unclear.
- A new dependency or package split is required.
- The task requires choosing between incompatible execution semantics.
- Existing tests encode contradictory behavior.
- The requested change would couple the domain to an external adapter (incl. RunIt prematurely).
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

- [ ] The change follows the CommandIt execution vocabulary.
- [ ] Domain semantics remain vendor-neutral.
- [ ] Dependency direction is correct (no domain → adapter, no RunIt coupling).
- [ ] Concrete classes are `final`.
- [ ] Eligible classes are `readonly`.
- [ ] Concrete classes implement appropriate interfaces.
- [ ] Commands, results, and input remain immutable.
- [ ] Original business exceptions are preserved.
- [ ] External effects use ports.
- [ ] Sensitive data is classified and redacted.
- [ ] Buffers, payloads, and cardinality are bounded.
- [ ] Relevant tests were added or updated.
- [ ] Architecture and static-analysis checks pass where available.
- [ ] Documentation reflects public behavior.
- [ ] No unrelated changes were introduced.
- [ ] Any unverified assumption is explicitly reported.
