# AGENTS.md

## Purpose

This file guides AI coding agents working on **RunIt** (`it-pack/run-it`).

RunIt provides the vendor-neutral execution model and runtime backbone for the ItPack ecosystem.

Use these instructions when implementing, reviewing, refactoring, testing, or documenting the project. Keep changes small, explicit, and consistent with the execution model.

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

RunIt models **execution itself**:

```text
Execution
├── Context
├── Activity
├── Measurement
├── Observation
├── Occurrence
└── Snapshot
```

The governing principles are:

> Everything happens within an execution.  
> Execution carries context.  
> Activities perform work.  
> Measurements quantify work.  
> Observations describe work.  
> Occurrences record work.  
> Snapshots preserve work.

External technologies implement or transport parts of this model:

- OpenTelemetry is an adapter.
- CloudEvents is an adapter.
- PSR-3 is an adapter.
- PSR-14 is an adapter.
- Swoole and OpenSwoole are runtime adapters.

Never redefine RunIt domain semantics in terms of an external standard.

---

## Sources of Truth

Before making architectural changes, read the smallest relevant set of:

1. `EXECUTION.md`
2. `README.md`
3. Relevant files under `docs/architecture/`
4. Relevant Architecture Decision Records under `docs/adr/`
5. Public contracts under `src/Contract/`
6. Tests covering the affected behavior
7. `composer.json`
8. `deptrac.yaml`
9. `phpstan.neon.dist`

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

RunIt uses hexagonal ports-and-adapters architecture.

```text
SDK / Consumer Adapters
          │
          ▼
Application
          │
          ▼
Domain

Infrastructure Adapters ──► Ports ◄── Application
```

Expected dependency direction:

```text
Domain       → Contracts
Application  → Contracts, Domain, Ports
SDK          → Contracts, Domain, Application, Ports
Adapters     → Contracts, Domain, Ports, external APIs
```

### Domain must not depend on

- Application services.
- SDK conveniences.
- Infrastructure adapters.
- OpenTelemetry.
- CloudEvents.
- PSR interfaces.
- Frameworks.
- Swoole or OpenSwoole.
- Containers.
- Filesystems.
- Network clients.

### Application must not depend on

- SDK facades.
- Concrete external adapters.
- Framework service providers.
- Vendor-specific telemetry implementations.

### Adapter rules

Adapters may translate RunIt concepts but must not change their meaning.

Keep mapping logic inside the relevant adapter. Do not add vendor-specific fields to core domain objects solely to satisfy an adapter.

DepTrac rules are architectural requirements, not optional style guidance.

---

## Domain Invariants

Preserve these invariants unless an accepted ADR explicitly changes them.

### Execution

- An execution represents one logical runtime operation.
- Every artifact belongs to exactly one execution.
- An execution has at most one terminal outcome.
- Terminal executions cannot transition again.
- Nested executions use explicit parent or link identities.
- Ownership is represented by identity and relationships, not mandatory in-memory collections.

### Context

- Execution context is immutable.
- Changes produce a new context.
- Context values have explicit classification and propagation policy.
- Secret values are never exported.
- Ambient context is convenience infrastructure, not the canonical domain API.
- Global mutable execution state is prohibited.

### Activity

- Activities are bounded units of work.
- An activity belongs to one execution.
- A nested activity references its parent activity explicitly.
- A completed activity cannot be reopened.
- Open activities must be resolved according to an explicit completion policy.

### Measurement

- Measurements are quantitative.
- Each measurement has a name, numeric value, and unit.
- High-cardinality values must not become metric dimensions by default.
- Duration uses monotonic time.

### Observation

- Observations are qualitative.
- Prefer stable message templates and structured attributes.
- Throwable data must be safely normalized.
- Observations must not become occurrences merely because they are important.

### Occurrence

- Occurrences are immutable facts.
- Occurrence types are stable, lowercase, and versioned.
- Occurrence types are independent of PHP class names.
- CloudEvents is a representation of occurrences, not their domain definition.

### Snapshot

- Snapshots are immutable.
- Snapshot serialization is explicit and deterministic.
- Arbitrary PHP object serialization is prohibited.
- Snapshot persistence is delegated to a port.

---

## Execution Lifecycle

Preserve the execution state machine:

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

When an operation throws:

1. Record failure safely.
2. Resolve active activities according to policy.
3. Complete the execution as failed.
4. Restore prior context in `finally`.
5. Rethrow the original `Throwable`.

A telemetry or adapter exception must not replace the original business exception.

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
- Exceptions must implement the relevant RunIt exception marker interface.
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
- Use dedicated value objects for identifiers, names, units, and validated concepts.
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
- Use Stringable for untyped stringable objects.
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
- Fibers through runtime adapters.
- `WeakMap` for object-keyed, non-owning runtime associations.
- `WeakReference` only where correctness does not depend on object lifetime.

Do not use a language feature merely because it is available.

---

## API Design

RunIt is a library and SDK for both ItPack and third-party consumers.

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
- Immutable builders for ergonomic configuration.
- Decorators for non-intrusive instrumentation.
- Explicit scope arguments.
- Narrow recorder interfaces.
- Ports for time, identity, serialization, publication, and storage.

### Avoid

- Global facades.
- Service locators.
- Hidden container access.
- Mandatory inheritance.
- Reflection on hot paths.
- Generic “manager” or “helper” classes.
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
| Execution | Trace, transaction, request |
| Activity | Span, operation step |
| Measurement | Metric value |
| Observation | Log |
| Occurrence | Event |
| Snapshot | Serialized result |
| Context | Baggage, metadata bag |

Adapter documentation may explain mappings, but core APIs must retain RunIt vocabulary.

Names should communicate one responsibility:

```text
ExecutionRunner
ActivityRunner
ArtifactPublisher
ContextStorage
SamplingPolicy
Redactor
SnapshotStore
```

Avoid vague names such as:

```text
ExecutionManager
TelemetryHelper
DataProcessor
CommonService
Utility
```

## Vocabulary Alignment

`vocab/run-it.ttl` is the canonical OWL vocabulary for RunIt domain concepts.
`vocab/run-it-taxonomy.ttl` is the companion SKOS taxonomy for extensible kinds,
context keys, occurrence types, and other recommended controlled values.

When adding or materially changing a public domain contract, value object,
serialized field, standard name, kind, context key, occurrence type, or its
documentation, reference the corresponding vocabulary resource:

- In implementation code, use a PHPDoc `@see` reference to the canonical term
  IRI where a corresponding term exists.
- In public documentation, link to the relevant local vocabulary file and,
  when useful, its canonical IRI or taxonomy scheme.
- Keep the reference adjacent to the defining contract or concept; do not add
  RDF concerns to the runtime model or make the vocabulary a runtime dependency.

If no suitable term exists, add or revise the vocabulary and its documentation
in the same change before using a new public term. Taxonomy entries are
recommended and extensible unless the ontology explicitly defines them as a
closed set.

---

## External Adapter Rules

### OpenTelemetry

- Keep OpenTelemetry dependencies optional.
- Library code must not configure global providers.
- Do not select an exporter from core code.
- Map executions and activities without equating them to traces and spans.
- Apply redaction and cardinality controls before export.
- Test mapping with in-memory providers or exporters.
- Preserve original execution identities where possible.

### CloudEvents

- Map occurrences through a dedicated mapper.
- Preserve unknown extension attributes when reading events.
- Validate required attributes.
- Keep event data serialization explicit.
- Do not export unrestricted execution context.
- Provide bidirectional mapping tests.

### PSR-3

- Map observations to logger messages and context.
- Keep message templates stable.
- Put the throwable under the expected exception context key where appropriate.
- Do not turn every log into an occurrence.

### PSR-14

- Account for synchronous dispatch and listener exceptions.
- Apply the configured publication failure policy.
- Do not allow PSR event mutability to mutate immutable RunIt occurrences.

### Swoole, OpenSwoole, and Fibers

- Store context per Fiber or coroutine.
- Never store request context in process-global mutable state.
- Restore or clear context in `finally`.
- Test repeated executions in persistent workers.
- Keep runtime-specific APIs out of domain and application contracts.

---

## Publication and Failure Policy

Artifact publication follows this conceptual pipeline:

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

Never catch and silently discard a publication failure without applying the configured policy or recording it through the recursion-safe health path.

---

## Security Requirements

Treat context, observations, occurrences, and snapshots as potential data-exfiltration paths.

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

Assume execution and activity APIs may run on hot paths.

Avoid:

- Network or filesystem operations in core services.
- Reflection during normal execution.
- Eager serialization.
- Eager stack-trace generation.
- Retaining all artifacts by default.
- Unbounded recursion, nesting, or buffering.
- Per-artifact initialization of expensive adapters.

Prefer:

- Monotonic clocks for durations.
- Deferred normalization.
- Streaming publication.
- Reusable immutable mappers and policies.
- Bounded buffering.
- Sampling and filtering before expensive work.

Performance changes require benchmarks when they affect:

- Execution start or completion.
- Activity start or completion.
- Context storage.
- Artifact creation.
- Publication.
- Serialization.
- Fiber or coroutine switching.

Do not claim a performance improvement without measurements.

---

## Self-Instrumentation

RunIt should use its own model for operational activities, but it must not recursively observe publication.

Preserve these rules:

- Use a publication recursion guard.
- Send nested publication failures to a separate health reporter.
- Do not route health reporting back through the failing publisher.
- Instrument exporter batches or flushes rather than every internal method.
- Keep the bootstrap path intentionally uninstrumented.
- Avoid self-instrumentation that materially changes hot-path behavior.

---

## Testing Requirements

Every behavior change requires tests at the narrowest appropriate level.

### Test categories

- Domain invariant tests.
- Lifecycle tests.
- Application service tests.
- Port contract tests.
- Adapter integration tests.
- Serialization round-trip tests.
- Failure-injection tests.
- Property-based tests where useful.
- Fiber or coroutine isolation tests.
- Persistent-worker leak tests.
- Mutation tests for critical branching.
- Benchmarks for hot-path changes.

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
- Adapter failure.
- Nested execution.
- Nested activities.
- Context restoration.
- Repeated completion.
- Cancellation or timeout.
- Redaction.
- Buffer overflow.
- Recursive publication.
- Concurrency isolation.

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

Typical checks may include:

```bash
composer validate
composer test
composer analyse
composer architecture
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

### DepTrac

- Do not add skips to hide an architectural violation.
- Correct the dependency direction.
- If a boundary must change, update the architecture and ADR first.

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
- Demonstrate RunIt terminology rather than adapter terminology.

Create or update an ADR when a change affects:

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
- introduce a framework requirement.
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

- `EXECUTION.md`, an ADR, and public code disagree materially.
- A change would break a stable public contract.
- Required security or data-classification behavior is unknown.
- Delivery guarantees are unclear.
- A new dependency or package split is required.
- The task requires choosing between incompatible execution semantics.
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

- [ ] The change follows the RunIt execution vocabulary.
- [ ] Domain semantics remain vendor-neutral.
- [ ] Dependency direction is correct.
- [ ] Concrete classes are `final`.
- [ ] Eligible classes are `readonly`.
- [ ] Concrete classes implement appropriate interfaces.
- [ ] Context and artifacts remain immutable.
- [ ] Original business exceptions are preserved.
- [ ] External effects use ports.
- [ ] Sensitive data is classified and redacted.
- [ ] Buffers, payloads, and cardinality are bounded.
- [ ] Relevant tests were added or updated.
- [ ] Architecture and static-analysis checks pass where available.
- [ ] Documentation reflects public behavior.
- [ ] No unrelated changes were introduced.
- [ ] Any unverified assumption is explicitly reported.
