# AGENTS.md

## Purpose

This file guides AI coding agents working on **AsyncIt** (`it-pack/async-it`).

AsyncIt provides the portable, capability-oriented **scheduling and progression engine** for the ItPack ecosystem. It defines *how* work progresses within an execution, strictly decoupled from operating-system multiplexers, polling engines, vendor runtimes, and telemetry frameworks.

AsyncIt is the **Scheduling/Progress tier** of the ItPack execution stack and is designed to be the canonical implementation of RunIt's `ExecutionEngineInterface`. It consumes RunIt's execution model (identity, immutable context, artifacts) and never redefines it.

Use these instructions when implementing, reviewing, refactoring, testing, or documenting the project. Keep changes small, explicit, and consistent with the execution model and the architectural decisions recorded under `docs/background/`.

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

AsyncIt models **the progression of work**:

```text
Execution (RunIt owns identity & semantics)
└── Scheduling (AsyncIt owns progression)
    ├── Task
    ├── Scheduler
    ├── Awaiting
    ├── Suspension / Resumption
    ├── Synchronization
    └── Cancellation
```

The governing principles are:

> Everything happens within an execution (RunIt).  
> AsyncIt moves work forward, it does not define what an execution is.  
> Polling is not scheduling; scheduling is not execution semantics.  
> Capabilities are negotiated, never assumed from vendor names.  
> Cancellation is an observed state change, not a preemptive kill.  
> Context is immutable and must never leak across concurrent tasks.

AsyncIt enforces a strict mechanical split that keeps higher tiers (Spindle, AExO, RunIt) durable across engine evolution:

```text
Semantics (RunIt)  ≠  Scheduling (AsyncIt)  ≠  Polling (Engine / Io\Poll)
```

External technologies implement or transport parts of this model:

- PHP 8.1+ Fibers are the unit of suspended work.
- PHP 8.6 `Io\Poll` is a poll provider (an adapter slot), not a scheduler.
- Revolt, Swoole, and Swow are runtime adapters.
- RunIt is the execution-semantics backbone (consumed, not reimplemented).
- OpenTelemetry / CloudEvents / PSR-3 / PSR-14 are telemetry adapters in the broader stack.

Never redefine RunIt domain semantics in terms of an external runtime or async engine.

---

## Sources of Truth

Before making architectural changes, read the smallest relevant set of:

1. `docs/EXECUTION.md` (shared ItPack execution model).
2. `README.md`.
3. `docs/background/` — briefing docs, capability spec, and ADRs (`adr-AI-001`, `adr-itp-002`).
4. Public contracts under `src/Contract/` and ports under `src/Contract/Port/`.
5. The conformance harness under `src/Conformance/` (`AsyncRuntimeVerifier`).
6. Tests covering the affected behavior.
7. `codeception.yml`
8. `composer.json`.
9. `phparkitect.php`.
10. `phpstan.neon` / `phpstan-bleeding-edge.neon`.
11. `.php-cs-fixer.php`
12. `rector.php`

Agents may run `composer update` in AsyncIt to resolve current dependencies
within the package's declared constraints and obtain an up-to-date quality
picture. The package's `composer.lock` is ignored; do not commit it. Preserve
pre-existing changes, do not change constraints merely to make an update pass,
and report dependency-resolution or subsequent test failures.

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
    - Scheduling semantics.
    - Public API.
    - Capability negotiation.
    - Serialization.
    - Adapter mapping.
    - Security (context isolation / handle leaks).
    - Performance (scheduler hot path).
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

1. Scheduling invariants (lifecycle, cancellation, context isolation).
2. Public contracts.
3. Tests (especially conformance assertions).
4. Security (no request leakage, no handle leaks).
5. Architecture boundaries (DepTrac).
6. Documentation.
7. Internal cleanup.

---

## Architectural Boundaries

AsyncIt uses hexagonal ports-and-adapters architecture. AsyncIt is itself an **infrastructure port** under RunIt's semantic backbone: it occupies the scheduling slot that RunIt's `ExecutionEngineInterface` reserves.

```text
SDK / Consumer Adapters (Revolt, Swoole, Swow, Native Poller)
          │
          ▼
Application (Scheduler, Runtime Discovery)
          │
          ▼
Domain (Task, Timer, Channel, Cancellation, Await)

Infrastructure Adapters ──► Ports ◄── Application
        (pollers, runtime bindings)
```

Expected dependency direction:

```text
Domain       → Contracts
Application  → Contracts, Domain, Ports
SDK          → Contracts, Domain, Application, Ports
Adapters     → Contracts, Domain, Ports, external runtimes
Conformance  → Contracts, Domain, Application, SDK, Adapters
```

### Domain must not depend on

- Application services.
- SDK conveniences.
- Infrastructure adapters (Revolt, Swoole, Swow, poller backends).
- RunIt semantics implementation (it consumes RunIt contracts/ports only).
- OpenTelemetry, CloudEvents, PSR interfaces.
- Frameworks.
- Swoole/Swow/Revolt process models.
- Containers or global state.
- Filesystems or network clients (beyond poll-provider contracts).

### Application must not depend on

- SDK facades.
- Concrete external runtime adapters.
- Framework service providers.
- Vendor-specific scheduling implementations.

### Adapter rules

Adapters may translate runtime concepts (coroutine suspension, poller readiness) but must not change AsyncIt scheduling semantics.

Keep mapping logic inside the relevant adapter. Do not add vendor-specific fields to core domain objects solely to satisfy a runtime.

Phparkitect rules are architectural requirements, not optional style guidance.

---

## Domain Invariants

Preserve these invariants unless an accepted ADR explicitly changes them.

### Polling ≠ Scheduling ≠ Semantics

- The poll provider (`Io\Poll`, `stream_select` fallback, etc.) only signals resource readiness.
- AsyncIt owns task queues, timers, Fiber suspension/resumption, and cancellation mechanics.
- RunIt owns execution identity, context, and artifacts.
- Adapters must slot cleanly: changing the poll backend never changes scheduling behavior or semantics.

### Task

- A task is the micro-unit of scheduled work within an execution.
- A task is distinct from the engine's internal Fiber; a task wraps a Fiber.
- A task belongs to exactly one execution.
- An execution may contain many tasks coordinated via the scheduler.
- A task has at most one terminal outcome.
- Terminal tasks cannot transition again.
- Nested/structured tasks use explicit parent or link identities.

### Scheduling

- Schedulers coordinate task queues, priority, and Fiber suspension/resumption.
- Suspension detaches the active RunIt context from the Fiber scope.
- Resumption re-attaches the matching context before continuing.
- Schedulers never forcibly kill a running Fiber.

### Awaiting

- Awaiting yields control while waiting for a resource, without blocking the reactor.
- Await boundaries (e.g., awaitReadable, delay) are the cooperation points where cancellation is observed.
- Await resolution must release any OS-level poll handles it registered.

### Suspension & Resumption (Fiber-Safe Context)

- Context is immutable (RunIt owns it; AsyncIt consumes it via `ContextStorageInterface`).
- On suspension, the context is detached and locked away from the ambient Fiber scope.
- While suspended, the task's context is not ambient and cannot be read by interleaved tasks.
- On resumption, the exact context is re-attached via a `finally` guard.
- Global mutable scheduling state is prohibited.

### Synchronization

- Channels and other coordination primitives may be native or emulated, negotiated by capability.
- Synchronization must remain bounded and must not introduce hidden global state.

### Observed Cooperative Cancellation

- Cancellation and timeout are observed state changes, not preemptive interruptions.
- A running task checks its cancellation status at cooperation points and unwinds gracefully.
- On cancellation, a task must explicitly release OS-level file watchers (`StreamPollHandle`) back to the poll context.
- Conflicting terminal transitions (e.g., succeeding an already `timed_out` task) raise a `ConflictingCompletionException`.
- In structured concurrency, cancellation cascades from parent to children unless explicitly detached.

### Time

- Durations and timers use monotonic clocks (`hrtime()`), never wall-clock time.
- Telemetry derived from scheduling (durations) must be free of wall-clock skew.

---

## Task Lifecycle

Preserve the task state machine:

```text
CREATED
   │
   ▼
RUNNING
   ├──► SUCCEEDED
   ├──► FAILED
   ├──► CANCELLED
   └──► TIMED_OUT
```

Lifecycle behavior must be:

- Explicit.
- Validated.
- Idempotent where documented.
- Safe during exceptions.
- Testable without external infrastructure.

When a task throws:

1. Record failure safely (publish a RunIt Occurrence/Observation via the publication port).
2. Release poll handles and timers.
3. Complete the task as failed.
4. Restore prior context in `finally`.
5. Rethrow the original `Throwable`.

A runtime or adapter exception must not replace the original business exception.

---

## PHP Standards

Target PHP `>= 8.5`.

Every PHP file must begin with:

```php
<?php

declare(strict_types=1);
```

### Classes and contracts

- Every concrete class must be `final`.
- Every final class ust be fully backed by interfaces.
- Use `readonly` whenever controlled mutation is unnecessary.
- Every concrete class must implement an appropriate interface.
- Exceptions must implement the relevant AsyncIt exception marker interface.
- Prefer composition over inheritance.
- Keep interfaces small and focused.
- Do not create interfaces solely to duplicate another interface without semantic value.
- Traits must harmonize with interfaces and remain focused.
- Traits must be used for code deduplication and composition.
- Avoid hidden dependencies.

#### Service instantiation ("new only in factories")

Service construction (`new <Service...>`) is restricted to factories/builders under
`src/Application/Factory/` and `src/SDK/Factory/` (composition root). The following are
**intentional exemptions** and may be instantiated directly where they occur:

- `throw new <Exception...>()` — standard PHP exception instantiation in a `throw`
  statement, not service construction.
- Value objects (e.g. `new ContextToken($id)`) and result/violation objects (e.g.
  `new ConformanceResult(...)`, `new ConformanceViolation(...)`) — short-lived data
  carriers with no collaborators.
- PHP internal classes, including extension-provided internal classes (e.g.
  `new WeakMap()`). Userland project and third-party classes are not exempt
  merely because they are vendor-provided or use a global namespace.

Do not introduce exception factories, value-object factories, or speculative abstractions to
work around this rule. If a new class holds collaborators or coordinates behavior, construct it
through a factory/builder instead.

### Types

- Use native parameter, property, and return types.
- Avoid implicit `mixed`.
- Add PHPDoc generics where native PHP cannot express a relationship.
- Do not use PHPDoc to contradict native types.
- Use dedicated value objects for identifiers, names, units, capability keys, and validated concepts.
- Use `null` for optional values.
- Use property hooks for value objects and back them with respective contracts.
- Prefer extensible validated strings in interfaces over closed enums for user-defined names and kinds.
- Use enums for genuinely closed internal state sets (e.g., task lifecycle states, capability statuses).
- Ensure interfaces only depend on interfaces/enums and no concrete classes.
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
- Lazy objects for expensive optional runtime adapters.
- Fibers through runtime adapters for task suspension.
- `WeakMap` for object-keyed, non-owning runtime associations (e.g., per-task metadata).
- `WeakReference` only where correctness does not depend on object lifetime.

Do not use a language feature merely because it is available.

---

## API Design

AsyncIt is a library and SDK for both ItPack and third-party consumers.

Public APIs must be:

- Vendor-neutral.
- Framework-neutral.
- Explicitly typed.
- Composable.
- Testable.
- Difficult to misuse.
- Stable enough for independent adapters.

### Prefer

- Interfaces at architectural boundaries (Task, Scheduler, Poller, RuntimeAdapter).
- Immutable definitions and artifacts.
- Named constructors (e.g., capability descriptors, discovery results).
- Factories for runtime/adapter assembly (`RuntimeDiscovery`).
- Immutable builders for ergonomic scheduling configuration.
- Decorators for non-intrusive instrumentation.
- Explicit scope arguments.
- Narrow recorder/publisher interfaces.
- Ports for time (monotonic clock), identity, context storage, publication, and polling.

### Avoid

- Global facades.
- Service locators.
- Hidden container access.
- Mandatory inheritance.
- Reflection on hot paths.
- Generic "manager" or "helper" classes.
- Large configuration arrays without typed builders.
- Boolean parameters with unclear meaning.
- Public APIs coupled to one runtime adapter.
- Proprietary promises or schedulers that bypass the contract.

---

## Naming

Use domain terminology consistently:

| Use               | Avoid as a synonym          |
|-------------------|-----------------------------|
| Task              | Span, coroutine, job        |
| Scheduler         | Event loop, reactor         |
| Awaiting          | Wait, sleep                 |
| Poller            | Event loop, multiplexer     |
| Cancellation      | Kill, abort                 |
| Suspension        | Pause, stop                 |
| Execution (RunIt) | Trace, transaction, request |
| Context (RunIt)   | Baggage, metadata bag       |

Adapter documentation may explain runtime mappings, but core APIs must retain AsyncIt vocabulary.

Names should communicate one responsibility:

```text
RuntimeDiscovery
CapabilityDescriptor
TaskScheduler
PollerPort
StreamPollHandle
CancellationContext
TaskChannel
AsyncRuntimeVerifier
```

Avoid vague names such as:

```text
AsyncManager
SchedulerHelper
RuntimeProcessor
CommonService
Utility
```

---

## Vocabulary Alignment

AsyncIt maintains its own RDF vocabulary, distinct from the execution-semantics vocabulary it consumes:

- `vocab/async-it.ttl` — the **AsyncIt Scheduling Ontology** (OWL). The canonical source for AsyncIt-owned scheduling concepts: `Task`, `Scheduler`, `Awaitable`, `CancellationScope`, `Timer`, `TaskChannel`, `Poller`, `StreamPollHandle`, `RuntimeAdapter`, `CapabilityDescriptor`/`Capability`/`CapabilityStatus`, `ConformanceResult`/`ConformanceViolation`/`ViolationSeverity`, and their properties. Closed sets are modelled as controlled individuals (`TaskStatus`, `CapabilityStatus`, `ViolationSeverity`, `CapabilityKind`).
- `vocab/async-it-taxonomy.ttl` — the **AsyncIt Scheduling Taxonomy** (SKOS). Extensible concept schemes for kinds and keys: `TaskKinds`, `CapabilityKeys`, `OccurrenceTypes`, `RuntimeAdapterKinds`, `PollerKinds`.
- `../Runit/vocab/run-it.ttl` (and `run-it-taxonomy.ttl`) — the **RunIt Execution Ontology/Taxonomy** that AsyncIt *consumes*. AsyncIt does not own terms like `Execution`, `Context`, `Occurrence`, `Measurement`, `Observation`, `Snapshot`; it maps scheduler events into them.

During implementation, every public scheduling contract, enum, value object, serialized field, standard name, kind, capability key, or occurrence type **must** carry a PHPDoc `@see` to the matching vocabulary resource:

- **Closed sets** (enums/interfaces such as `TaskStatus`, `CapabilityStatus`, `ViolationSeverity`, `CapabilityKind`) map to ontology controlled individuals — reference the individual IRI in `async-it.ttl` (e.g. `@see https://w3id.org/itpack/asyncit#TaskStatus`).
- **Extensible kinds/keys/types** (task kinds, capability keys, scheduler occurrence types, adapter/poller kinds) map to taxonomy `skos:Concept` notations — reference the taxonomy concept (e.g. `@see https://w3id.org/itpack/asyncit/taxonomy#occ-task-spawned`).
- **Consumed RunIt terms** carry `@see run-it#*` / `run-it-taxonomy#*` where AsyncIt maps onto them (e.g. `belongsToExecution` → `runit:Execution`).

Rules:

- In public documentation, link to the relevant local vocabulary file and, when useful, its canonical IRI or taxonomy scheme.
- Keep the reference adjacent to the defining contract or concept; do not add RDF concerns to the runtime model or make the vocabulary a runtime dependency.
- If no suitable term exists, add the term to `vocab/async-it.ttl` (closed set) or `vocab/async-it-taxonomy.ttl` (extensible) in the same change before using a new public term, and reference it. Taxonomy entries are recommended and extensible unless the ontology explicitly defines them as a closed set.

---

## External Adapter Rules

### Runtime Adapters (Revolt, Swoole, Swow, Native 8.6, Select fallback)

- Negotiate capabilities at the process boundary; never branch on `extension_loaded(...)`.
- Map suspension/resumption to the runtime's coroutine or Fiber model without changing AsyncIt semantics.
- Store context per Fiber/coroutine; never store request context in process-global mutable state.
- Restore or clear context in `finally`.
- On terminal transition, release every registered poll handle and timer.
- Keep runtime-specific APIs out of domain and application contracts.

### Polling (Io\Poll and SelectPoller)

- `Io\Poll` is a readiness notifier only; it does not manage tasks, timers, or Fiber lifecycles.
- `SelectPoller` is a reference/development fallback honoring the same behavioral contract within the legacy `stream_select` descriptor ceiling.
- The fallback must never pretend to be the native Poll API; it is an AsyncIt poller implementation with weaker characteristics.
- Adapter slots must allow the poll backend to change without touching scheduling or semantics.

### Capability Negotiation

- Query functional questions: *"Can you run Fibers natively? Do you support edge-triggered polling? Do you have native channels?"*
- Categorize each capability as `Supported`, `Emulated`, `Adapter-Only`, or `Unavailable`.
- The resulting `CapabilityDescriptor` is the single source of truth for the active environment.
- Never guess performance characteristics; select scheduling policy from the negotiated descriptor.

### RunIt Integration

- Publish scheduler events (task spawn, resume, complete) as RunIt Occurrences.
- Record task durations (monotonic) as RunIt Measurements.
- Record anomalies as RunIt Observations.
- Consume RunIt's `ContextStorageInterface` for fiber-safe context; never invent a parallel context system.

---

## Publication and Failure Policy

Scheduler artifacts are published into the RunIt artifact stream through a port. Publication follows this conceptual pipeline:

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

- Redact before external publication (context classification is owned by RunIt).
- Bound buffers and queues.
- Define overflow behavior.
- Prevent recursive publication.
- Preserve the business result.
- Preserve the original business exception.
- Make fail-closed behavior explicit.

Default behavior is expected to be fail-open with fallback reporting.

Never catch and silently discard a publication failure without applying the configured policy or recording it through the recursion-safe health path.

---

## Security Requirements

Treat context propagation, poll handles, and scheduler state as potential data-exfiltration and resource-exhaustion paths.

Do not:

- Export secrets (classification is owned by RunIt; AsyncIt must respect deny-by-default).
- Retain execution context between persistent-worker requests.
- Leak `StreamPollHandle` or timer descriptors across task boundaries.
- Use global or static state for request-specific scheduling metadata.
- Serialize arbitrary objects as scheduling state.
- Create unbounded queues, channels, or payloads.
- Use user-controlled values as metric names.

When adding fields or mappings, consider:

1. Classification (delegated to RunIt context model).
2. Redaction.
3. Maximum size.
4. Cardinality.
5. Serialization safety.
6. Tenant isolation (persistent-worker request leakage).
7. Propagation boundaries.

Security-sensitive behavior requires negative tests (e.g., context-bleed, handle-leak).

---

## Performance Requirements

Assume scheduler and await boundaries may run on hot paths.

Avoid:

- Network or filesystem operations in core scheduling services.
- Reflection during normal execution.
- Eager serialization.
- Eager stack-trace generation.
- Retaining all task artifacts by default.
- Unbounded recursion, nesting, or buffering.
- Per-task initialization of expensive adapters.

Prefer:

- Monotonic clocks for durations (`hrtime()`).
- Deferred normalization.
- Bounded buffering.
- Reusable immutable mappers and policies.
- Sampling and filtering before expensive work.
- Negotiated capability selection over runtime probing.

Performance changes require benchmarks when they affect:

- Task spawn or completion.
- Fiber suspension or resumption.
- Scheduler queue throughput.
- Poll-handle registration/teardown.
- Publication.
- Serialization.

Do not claim a performance improvement without measurements.

---

## Self-Instrumentation

AsyncIt should publish its operational activity into RunIt artifacts, but it must not recursively observe publication.

Preserve these rules:

- Use a publication recursion guard.
- Send nested publication failures to a separate health reporter.
- Do not route health reporting back through the failing publisher.
- Instrument batch/flush boundaries rather than every internal scheduler method.
- Keep the bootstrap and discovery path intentionally uninstrumented.
- Avoid self-instrumentation that materially changes hot-path behavior.

---

## Testing Requirements

Every behavior change requires tests at the narrowest appropriate level. Conformance is proven by **behavior**, not by interface shape alone.

### Test categories

- Domain invariant tests.
- Task lifecycle tests.
- Application service tests (scheduler, discovery).
- Port contract tests (clock, context storage, poller, publication).
- Adapter integration tests (Revolt, Swoole, Swow, Native, Select).
- Serialization round-trip tests (capability descriptors, task state).
- Failure-injection tests.
- Property-based tests where useful.
- Fiber or coroutine isolation tests.
- Persistent-worker leak tests.
- Mutation tests for critical branching.
- Benchmarks for hot-path changes.

### Conformance (AsyncRuntimeVerifier)

Adapters are valid only when they pass the `AsyncRuntimeVerifier` suite. Mandatory assertions:

- **Assertion A — Context Isolation:** Interleave concurrent tasks and prove zero context bleed across suspension/resumption cycles (detach at yield, re-attach on resume).
- **Assertion B — Temporal Precision:** Verify monotonic-clock integration; durations must be free of wall-clock skew.
- **Assertion C — Deterministic Cleanup:** Prove no dangling watchers; every `StreamPollHandle` and timer is released immediately after timeout or cancellation.

A single leaked descriptor or one context-bleed is a conformance failure.

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
- Adapter/poller failure.
- Nested/structured tasks.
- Context restoration (detach/re-attach).
- Repeated completion (idempotent terminal transition).
- Cancellation and timeout (observed, cooperative).
- Conflicting completion (`ConflictingCompletionException`).
- Handle/poll-descriptor overflow.
- Recursive publication.
- Concurrency isolation (persistent-worker leakage).

A test should verify observable behavior, not private implementation details.

---

## Quality Gates

Inspect `composer.json` before running commands. Use repository-defined scripts when available.

Expected tools include:

- PHPStan.
- Phparkitect.
- PHP-CS-Fixer.
- Rector.
- PHPStan.
- PHPStan-Strict-Rules.
- Infection.
- Composer validation and audit.
- Backward compatibility checks.
- Unit and integration test runners.
- Scripts in tools/.

Typical checks may include:

```bash
composer validate
composer test
composer analyse
composer architecture
composer refactor
composer coding-style
composer mutation
composer benchmark
composer audit
composer quality
```

Do not assume these scripts exist. Verify them first.

### PHPStan

- Target the highest configured level.
- Do not add first-party errors to a baseline.
- Do not suppress an error without a specific documented reason.
- Prefer correcting types and design over adding broad ignores.

### Phparkitect

- Do not add skips to hide an architectural violation.
- Correct the dependency direction.
- If a boundary must change, update the architecture and ADR first.

### Mutation testing

Prioritize mutation coverage for:

- State transitions.
- Cancellation and timeout handling.
- Context restoration.
- Handle cleanup.
- Capability selection.
- Validators.
- Adapter mappings.

---

## Documentation Requirements

Update documentation when changing:

- Public APIs.
- Scheduling semantics.
- Task lifecycle behavior.
- Context propagation mechanics.
- Serialization.
- Capability naming.
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
- Demonstrate AsyncIt terminology rather than runtime/adapter terminology.

Create or update an ADR (under `docs/background/`) when a change affects:

- Aggregate boundaries (Task, Scheduler).
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

- Public interfaces and signatures (Task, Scheduler, PollerPort, RuntimeAdapter).
- Public classes and enums (task states, capability statuses).
- Artifact normalization fields.
- Serialized schemas marked stable (capability descriptors).
- Capability type conventions.
- Adapter extension contracts.
- Exception types exposed to consumers.

Do not casually add methods to public interfaces. Adding an interface method breaks third-party implementations.

Prefer introducing a new focused interface and composing it into new APIs.

---

## Change Discipline

### Do

- Solve the requested problem directly.
- Reuse established domain language.
- Add tests (including conformance assertions) before or with behavior changes.
- Preserve original exceptions.
- Keep adapters optional and capability-negotiated.
- Use ports for external effects (clock, context storage, polling, publication).
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

- `docs/EXECUTION.md`, an ADR, and public code disagree materially.
- A change would break a stable public contract.
- Required security or data-classification behavior is unknown.
- Delivery guarantees (at-most-once, ordering) are unclear.
- A new dependency or package split is required.
- The task requires choosing between incompatible scheduling semantics.
- Existing tests encode contradictory behavior.
- The requested change would couple the domain to an external runtime adapter.
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

- [ ] The change follows the AsyncIt scheduling vocabulary (Task, Scheduler, Poller, Cancellation).
- [ ] Polling, scheduling, and execution semantics remain cleanly separated.
- [ ] Capabilities are negotiated, not assumed from vendor names.
- [ ] Dependency direction is correct (DepTrac).
- [ ] Concrete classes are `final`.
- [ ] Eligible classes are `readonly`.
- [ ] Concrete classes implement appropriate interfaces.
- [ ] Context remains immutable and is detached/re-attached around suspension.
- [ ] Cancellation is observed, never preemptive.
- [ ] Poll handles and timers are released on terminal transition.
- [ ] Original business exceptions are preserved.
- [ ] External effects use ports.
- [ ] Sensitive data is classified and redacted (via RunIt context model).
- [ ] Buffers, payloads, and cardinality are bounded.
- [ ] Relevant conformance assertions (A/B/C) pass or were extended.
- [ ] Architecture and static-analysis checks pass where available.
- [ ] Documentation reflects public behavior.
- [ ] No unrelated changes were introduced.
- [ ] Any unverified assumption is explicitly reported.
