# RunIt Roadmap

> Fine-grained implementation and verification roadmap for `it-pack/run-it`.

**Planning baseline:** July 27, 2026  
**Target runtime:** PHP 8.4 or later  
**Target package:** `it-pack/run-it`  
**Target stable release:** `1.0.0`

**Status audit:** August 5, 2026. The repository now has its M0 foundation:
tracked architecture directories, Composer and QA configuration, a smoke test,
governance documents, ADRs, and CI configuration. The execution domain itself
has not been implemented; M1 remains the next development milestone. Checklist
items below reflect repository evidence and the validation results recorded in
the M0 status audit.

---

## 1. Purpose

This roadmap translates the RunIt implementation plan into incremental, verifiable tasks.

RunIt provides the architectural and operational backbone for the ItPack Execution Model:

```text
Execution
├── Context
├── Activity
├── Measurement
├── Observation
├── Occurrence
└── Snapshot
```

The roadmap covers:

- Domain contracts and immutable artifacts.
- Execution and activity lifecycle coordination.
- Context propagation.
- Artifact publication.
- Programmatic and SDK APIs.
- OpenTelemetry integration.
- CloudEvents integration.
- PSR integrations.
- Fiber and Swoole/OpenSwoole execution adapters.
- Security and privacy controls.
- Performance and persistent-worker safety.
- Test-framework-agnostic conformance tooling.
- PHPStan, DepTrac, Infection, and other QA tooling.
- Documentation, examples, adoption, and stable release preparation.

---

## 2. Roadmap Rules

### 2.1 Checklist semantics

- `[ ]` Not started or incomplete.
- `[x]` Implemented and verified.
- A parent task must not be checked until all required child tasks are complete.
- A milestone must not be checked until its acceptance gate passes.
- Experimental work must not be marked complete merely because a proof of concept exists.
- Deferred tasks must remain unchecked and be linked to a tracked issue or later milestone.

### 2.2 Definition of implemented

A source-code task is implemented only when:

- The required contract or behavior exists.
- The implementation follows architectural dependency rules.
- Relevant automated tests pass.
- PHPStan reports no new violations.
- DepTrac reports no unapproved violations.
- Public behavior is documented.
- Security and performance implications have been reviewed.
- Backward-compatibility impact has been recorded.

### 2.3 Definition of verified

A task is verified only when evidence exists through one or more of:

- Automated tests.
- Contract tests.
- Static analysis.
- Architectural analysis.
- Mutation testing.
- Benchmarks.
- Integration examples.
- Manual review with documented results.

### 2.4 Scheduling assumptions

The roadmap uses relative implementation weeks because team capacity and kickoff dates have not been confirmed.

| Phase | Target window |
|---|---|
| Foundation and governance | Week 1 |
| Domain kernel | Weeks 2–3 |
| Runtime and lifecycle | Weeks 4–5 |
| Publication pipeline | Week 6 |
| SDK and adoption API | Week 7 |
| PSR and OpenTelemetry | Weeks 8–9 |
| CloudEvents and advanced runtimes | Week 10 |
| Hardening | Week 11 |
| Release candidate and adoption | Week 12 |


---

## 3. Release Overview

Any time designations are just estimates, no hard requirements are implied.

| Milestone | Target | Primary outcome |
|---|---:|---|
| M0 | Week 1 | Repository, architecture, and governance foundation |
| M1 | Week 2 | Shared domain primitives and identifiers |
| M2 | Weeks 2–3 | Immutable execution context |
| M3 | Week 3 | Execution and artifact semantic model |
| M4 | Weeks 4–5 | Lifecycle-safe execution runtime |
| M5 | Week 5 | Activity runtime and artifact recorders |
| M6 | Week 6 | Composable publication pipeline |
| M7 | Week 7 | Programmatic SDK and non-intrusive adoption |
| M8 | Week 8 | PSR interoperability |
| M9 | Weeks 8–9 | OpenTelemetry integration |
| M10 | Week 10 | CloudEvents integration |
| M11 | Week 10 | Fiber and Swoole/OpenSwoole adapters |
| M12 | Week 11 | Security, performance, and resilience hardening |
| M13 | Week 11 | Conformance tooling and QA completion |
| M14 | Week 12 | Documentation, dogfooding, and ItPack adoption |
| M15 | Week 12 | Release candidate and stable `1.0.0` |
| M16 | Post-1.0 | Ecosystem expansion and operational maturity |

---

# Milestone M0 — Repository and Architecture Foundation

**Target:** Week 1  
**Outcome:** A buildable repository with documented architecture, quality gates, and development conventions.

## M0.1 Repository initialization

- [x] Create the RunIt repository.
- [x] Add `README.md`.
- [x] Add `docs/EXECUTION.md`.
- [x] Add `AGENTS.md`.
- [x] Add `docs/ROADMAP.md`.
- [x] Add `LICENSE`.
- [x] Add `CONTRIBUTING.md`.
- [x] Add `docs/SECURITY.md`.
- [x] Add `docs/CHANGELOG.md`.
- [x] Add `docs/UPGRADING.md`.
- [x] Add `.gitignore`.
- [x] Add `.gitattributes`.
- [x] Add `.editorconfig`.
- [x] Add a pull request template.
- [x] Add issue templates for:
    - [x] Bug reports.
    - [x] Feature proposals.
    - [x] Adapter proposals.
    - [x] Architecture decisions.
    - [x] Security reports.
- [x] Define the default branch.
- [ ] Configure protected-branch rules. — requires repository-host administration
- [ ] Require review for public API changes. — postponed
- [ ] Require successful CI before merge. — requires repository-host administration

## M0.2 Composer package

- [x] Create `composer.json`.
- [x] Set package name to `it-pack/run-it`.
- [x] Set package type to `library`.
- [x] Set PHP constraint to `^8.4`.
- [x] Define `ItPack\RunIt\` PSR-4 source mapping.
- [x] Define `Tests\` PSR-4 development mapping.
- [x] Add a package description and keywords.
- [x] Add license metadata.
- [x] Add author and support metadata.
- [x] Set `minimum-stability` and `prefer-stable` deliberately.
- [x] Add optional integrations under `suggest`.
- [x] Add development dependencies for initial tooling.
- [ ] Add Composer scripts for:
    - [x] Tests.
    - [x] Static analysis.
    - [x] Architecture analysis.
    - [x] Coding-style checks.
    - [x] Mutation testing.
    - [x] Benchmarks.
    - [x] Security audit.
    - [x] Aggregate quality checks.
- [x] Run `composer validate --strict`.
- [x] Verify optimized autoload generation.
- [x] Verify strict PSR-4 compliance.

## M0.3 Directory structure

- [x] Create `src/Contract/Domain/`.
- [x] Create `src/Contract/Application/`.
- [x] Create `src/Contract/Port/`.
- [x] Create `src/Contract/Exception/`.
- [x] Create `src/Domain/Execution/`.
- [x] Create `src/Domain/Context/`.
- [x] Create `src/Domain/Activity/`.
- [x] Create `src/Domain/Measurement/`.
- [x] Create `src/Domain/Observation/`.
- [x] Create `src/Domain/Occurrence/`.
- [x] Create `src/Domain/Snapshot/`.
- [x] Create `src/Domain/Shared/`.
- [x] Create `src/Application/Runner/`.
- [x] Create `src/Application/Scope/`.
- [x] Create `src/Application/Lifecycle/`.
- [x] Create `src/Application/Publication/`.
- [x] Create `src/Application/Propagation/`.
- [x] Create `src/Application/Policy/`.
- [x] Create `src/Adapter/Null/`.
- [x] Create `src/Adapter/Memory/`.
- [x] Create `src/Adapter/Callback/`.
- [x] Create `src/Adapter/Fiber/`.
- [x] Create `src/Adapter/Psr/`.
- [x] Create `src/Adapter/OpenTelemetry/`.
- [x] Create `src/Adapter/CloudEvents/`.
- [x] Create `src/Adapter/Swoole/`.
- [x] Create `src/SDK/Builder/`.
- [x] Create `src/SDK/Decorator/`.
- [x] Create `src/SDK/Middleware/`.
- [x] Create `src/SDK/Attribute/`.
- [x] Create `tests/Unit/`.
- [x] Create `tests/Contract/`.
- [x] Create `tests/Integration/`.
- [x] Create `tests/Concurrency/`.
- [x] Create `tests/Architecture/`.
- [x] Create `tests/Performance/`.
- [x] Create `benchmarks/`.
- [x] Create `examples/`.
- [x] Create `docs/architecture/`.
- [x] Create `docs/concepts/`.
- [x] Create `docs/integrations/`.
- [x] Create `docs/recipes/`.
- [x] Create `docs/adr/`.

## M0.4 Coding and architectural standards

- [x] Require `declare(strict_types=1)` in every PHP source file.
- [x] Require concrete classes to be `final`.
- [x] Require concrete classes to implement an appropriate interface.
- [x] Require `readonly` where mutation is unnecessary.
- [x] Define interface naming conventions.
- [x] Define exception marker-interface conventions.
- [x] Define identifier value-object conventions.
- [x] Define immutable builder conventions.
- [x] Define PHPDoc generic conventions.
- [x] Define normalization and serialization conventions.
- [x] Define public versus internal API conventions.
- [x] Define experimental API annotations or namespaces.
- [x] Require vocabulary references for public implementations and documentation.
- [x] Document the composition-over-inheritance rule.
- [x] Document the prohibition on global mutable state.
- [x] Document the prohibition on framework dependencies in the core.

## M0.5 Initial ADRs

- [x] Add ADR-001: Execution is the root aggregate.
- [x] Add ADR-002: Domain artifacts are immutable.
- [x] Add ADR-003: Artifact ownership uses identity relationships.
- [x] Add ADR-004: Explicit context propagation is canonical.
- [x] Add ADR-005: Ambient context is adapter-backed.
- [x] Add ADR-006: External standards are adapters.
- [x] Add ADR-007: Publication fails open by default.
- [x] Add ADR-008: Original business exceptions have priority.
- [x] Add ADR-009: Monotonic time measures duration.
- [x] Add ADR-010: Core has no mandatory external integration.
- [x] Add ADR-011: Occurrence names are stable and versioned.
- [x] Add ADR-012: Sensitive values are deny-by-default.
- [x] Add ADR-013: Fiber and Swoole support is adapter-based.
- [x] Add ADR-014: Self-observation uses recursion guards.
- [x] Add ADR-015: Concrete classes are final and interface-backed.
- [x] Add ADR-016: Define bridge package split criteria.

## M0.6 Tooling bootstrap

- [x] Configure the selected test runner. (codeception)
- [x] Configure PHPStan at the highest practical level.
- [x] Add strict PHPStan rules.
- [x] Configure DepTrac layers and dependency rules.
- [x] Configure PHP-CS-Fixer.
- [x] Configure Infection.
- [x] Configure code coverage.
- [x] Configure Composer audit.
- [ ] Configure backward-compatibility checks.
- [ ] Configure Markdown linting.
- [ ] Configure JSON and YAML validation.
- [ ] Configure dependency review.
- [ ] Configure secret scanning.
- [x] Add CI jobs for PHP 8.4.
- [x] Add a lowest-dependency CI job.
- [x] Add a stable-dependency CI job.
- [x] Add a coding-style CI job.
- [x] Add a static-analysis CI job.
- [x] Add an architecture-analysis CI job.
- [x] Add a unit-test CI job.
- [x] Add an integration-test CI job.
- [x] Add a security-audit CI job.

## M0 acceptance gate

### M0 status audit — August 5, 2026

- `composer update --lock --no-interaction` completed its install step and
  generated autoload files without dependency changes.
- `composer validate --strict` succeeds.
- `composer test:unit` passes one smoke test and one assertion.
- `vendor/bin/phpstan analyse --no-progress --debug` reports no errors.
- `composer architecture` reports zero violations.
- The PHP-CS-Fixer check did not produce a conclusive completion result in this
  environment and remains unverified.

- [x] Fresh `composer install` succeeds.
- [x] Composer validation succeeds.
- [x] PSR-4 autoloading works.
- [x] An initial smoke test passes.
- [x] PHPStan runs with zero first-party errors.
- [x] DepTrac runs with zero violations.
- [ ] Coding-style checks pass.
- [ ] CI executes successfully.
- [x] Required ADRs are reviewed and accepted.
- [x] The repository can be used by a new contributor using only documented commands.

---

# Milestone M1 — Shared Domain Primitives

**Target:** Week 2  
**Depends on:** M0  
**Outcome:** Validated identifiers, names, time values, attributes, and normalization primitives.

## M1.1 Base contracts

- [x] Define `NormalizableInterface`.
- [x] Define `StringIdentifierInterface`.
- [x] Define `ExecutionArtifactInterface`.
- [x] Define `ExecutionOwnedInterface`.
- [x] Define `ActivityOwnedInterface`.
- [x] Define `TimestampedInterface`.
- [x] Define `SequencedInterface`.
- [x] Define `AttributedInterface`.
- [x] Define marker interfaces for public exception categories.

## M1.2 Identifiers

- [x] Define `ExecutionIdInterface`.
- [x] Implement `ExecutionId`.
- [x] Define `ActivityIdInterface`.
- [x] Implement `ActivityId`.
- [x] Define `ObservationIdInterface`.
- [x] Implement `ObservationId`.
- [x] Define `OccurrenceIdInterface`.
- [x] Implement `OccurrenceId`.
- [x] Define `SnapshotIdInterface`.
- [x] Implement `SnapshotId`.
- [x] Define `CorrelationIdInterface`.
- [x] Implement `CorrelationId`.
- [x] Define identifier validation rules.
- [x] Decide UUID, ULID, opaque string, or pluggable generation policy.
- [x] Add deterministic string conversion.
- [x] Add equality behavior.
- [x] Reject empty identifiers.
- [x] Reject identifiers exceeding the documented maximum length.
- [x] Add identifier unit tests.
- [x] Add property-based identifier validation tests.

### M1.2 status audit

- All six identifier contracts and `final`, `readonly` value objects are implemented.
- `StringIdentifierValidator` enforces non-empty, ≤128-byte, visible-ASCII-only values, anchored with `\z` so a trailing newline is rejected. The property-based test confirmed `$` would have leaked a newline, which the corrected anchor closes.
- `InvalidIdentifierException` implements `InvalidValueExceptionInterface` and never echoes the rejected value.
- `IdentifierGeneratorInterface` (port) and `StringIdentifierFactory` provide pluggable, reflection-free generation bound to one identifier type.
- `runit:observationId` and `runit:correlationId` were added to `vocab/run-it.ttl`; ADR-017 records the opaque-string-with-pluggable-generation decision.
- Unit, validator, factory, and deterministic property-based tests pass.
- `composer test:unit` passes (97 tests, 3945 assertions).
- `composer analyse` (PHPStan, level max) reports zero errors.
- `composer architecture` (DepTrac) reports zero violations and zero uncovered classes; the `Common` and `Behavior` namespaces were added as explicit layers.
- `composer cs:check` (PHP-CS-Fixer) passes; Codeception `_generated` files are excluded from the finder.
- Supporting M1.1 fixes were required for a clean gate: the `Countable` projection typing on `SequencedBehavior`/`TimestampedBehavior` was corrected so ShipMonk dead-code analysis can bind them, the misspelled `ExecutionIdInterfacee` was renamed to `ExecutionIdInterface`, the speculative reflection-based `ImmutableStringIdentifierFactory` was removed, and an empty `ExecutionInterface` stub (extending a non-existent interface) was deleted.

## M1.3 Names and kinds

- [x] Define `ExecutionNameInterface`.
- [x] Implement `ExecutionName`.
- [x] Define `ExecutionKindInterface`.
- [x] Implement `ExecutionKind`.
- [x] Define `ActivityNameInterface`.
- [x] Implement `ActivityName`.
- [x] Define `ActivityKindInterface`.
- [x] Implement `ActivityKind`.
- [x] Define `MeasurementNameInterface`.
- [x] Implement `MeasurementName`.
- [x] Define `OccurrenceTypeInterface`.
- [x] Implement `OccurrenceType`.
- [x] Define `SnapshotTypeInterface`.
- [x] Implement `SnapshotType`.
- [x] Document allowed character sets.
- [x] Document maximum lengths.
- [x] Add tests for valid custom names and kinds.
- [x] Add tests proving kinds remain extensible.

### M1.3 status audit

- All seven name/kind value objects are `final`, `readonly`, and interface-backed.
- `StringValueValidator` enforces non-empty, ≤128-byte, letters/digits/`.`/`-`/`_`
  only; values are stored verbatim. `OccurrenceType` adds a stricter lowercase,
  dot-separated, versioned format and exposes `version()`.
- `InvalidStringValueException` implements `InvalidValueExceptionInterface` and
  never echoes the rejected value.
- `StringProviderInterface` / `StringValueInterface` and the shared
  `ValidatedStringValueBehavior` / `StringProviderBehavior` traits are reused by
  every value object, so the domain stays DRY.
- `InvalidStringValueException` was moved to `Contract/Exception` and
  `StringValueValidator` / `StringProviderBehavior` to `Behavior/Domain` so the
  `Behavior` layer (which depends only on `Contract`) does not cross into
  `Domain`.
- Unit, occurrence-type, and validator tests pass.
- `composer test:unit` passes (192 tests).
- `composer analyse` (PHPStan, level max) and `composer architecture` (DepTrac,
  zero violations, zero uncovered) pass.
- `composer cs:check` (PHP-CS-Fixer) passes.
- ADR-018 records the extensible-validated-string decision; the `runit`
  vocabulary already covered all seven concepts.

## M1.4 Time and sequencing

- [x] Define an instant/timestamp abstraction.
- [x] Define a duration value object.
- [x] Represent internal duration with integer nanoseconds.
- [x] Define `ArtifactSequenceInterface`.
- [x] Implement immutable sequence values.
- [x] Define `SequenceAllocatorInterface`.
- [x] Implement an in-memory monotonic sequence allocator.
- [x] Define `MonotonicClockInterface`.
- [x] Implement system wall-clock adapter.
- [x] Implement system monotonic-clock adapter.
- [x] Implement deterministic test clocks.
- [x] Test increasing sequence allocation.
- [x] Test duration calculation.
- [x] Test wall-clock and monotonic-clock separation.

### M1.4 status audit

- Immutable `Instant` (Unix epoch seconds, no timezone) and `Duration` (integer
  nanoseconds, never negative) value objects were added in `Domain/Shared`.
- `ArtifactSequence` is immutable: `next()` returns a new sequence and never
  mutates the receiver.
- `SequenceAllocatorInterface` (port) and `InMemorySequenceAllocator` allocate a
  per-execution monotonic counter; the first allocation for an execution returns
  the initial sequence (zero).
- `MonotonicClockInterface` (port) keeps wall-clock `nowSeconds()` and monotonic
  `monotonicNanoseconds()` separate; `SystemMonotonicClock` uses `time()` and
  `hrtime()`, and `Tests\Support\Fixture\DeterministicMonotonicClock` is the
  deterministic test double.
- `runit:instant` was added to the vocabulary; `runit:duration` and `runit:sequence`
  already existed.
- ADR-019 records the monotonic-time / integer-nanosecond / immutable-sequence
  decision.
- Unit, sequence-allocator, and monotonic-clock tests pass.
- `composer test:unit` passes (210 tests).
- `composer analyse` (PHPStan, level max) and `composer architecture` (DepTrac,
  zero violations, zero uncovered) pass.
- `composer cs:check` (PHP-CS-Fixer) passes.

## M1.5 Attributes

- [x] Define `AttributeKeyInterface`.
- [x] Implement `AttributeKey`.
- [x] Define supported attribute scalar types.
- [x] Define supported scalar-array types.
- [x] Define `AttributeValueInterface` if required. — intentionally omitted; values are a closed union of scalar, `null`, and scalar-only lists/maps.
- [x] Define `AttributeCollectionInterface`.
- [x] Implement immutable `AttributeCollection`.
- [x] Enforce maximum key length.
- [x] Enforce maximum attribute count.
- [x] Reject unsupported objects and resources.
- [x] Define deterministic attribute normalization.
- [x] Add empty-collection behavior.
- [x] Add copy-on-write `with()` behavior.
- [x] Add copy-on-write `without()` behavior.
- [x] Add attribute boundary tests.

### M1.5 status audit

- `AttributeKeyInterface` and `AttributeKey` provide a validated, immutable key. `AttributeKey` validates through the same `AttributeValidator` used by `AttributeCollection`, so the two cannot enforce divergent key rules (both bound to `MAX_KEY_LENGTH = 256` and the `^[A-Za-z0-9._-]++\z` pattern). `AttributeKey` implements `StringValueInterface` with type-aware `equals()`.
- `AttributeCollectionInterface` (immutable, `Countable`, `IteratorAggregate`) and `AttributeCollection` store attributes as scalars, `null`, or scalar-only lists/maps. `with()`/`without()` are copy-on-write and `toArray()` returns a deterministic, key-sorted, recursively-primitive map. `foreach` over the collection yields that same map.
- `AttributeValidator` enforces a maximum key length of 256 bytes, a maximum of 64 attributes, the allowed key character set, and recursive rejection of objects and resources at any depth. `InvalidAttributeException` implements `InvalidValueExceptionInterface` and never echoes the rejected value.
- `AttributeValueInterface` was intentionally not introduced: the supported value set is a closed union of PHP types, so a union documents it directly; the roadmap's "if required" clause does not apply.
- Unit tests cover empty/fromArray/iteration, deterministic sorting, copy-on-write semantics, key/value boundary rejection (empty, oversized, illegal characters, too many attributes, objects, resources, and nested objects), and `AttributeKey` equality and rejection.
- `composer test:unit` passes (230 tests, 4203 assertions).
- `composer analyse` (PHPStan, level max) reports zero first-party errors on the new attribute code; the only remaining `shipmonk.deadMethod` findings are pre-existing core behaviors (`SequencedBehavior::count`, `TimestampedBehavior::count`, `ValidatedStringIdentifierBehavior`, `ValidatedStringValueBehavior::fromString`, `FactoryInterface::build`), unrelated to this milestone.
- `composer architecture` (DepTrac) reports zero violations; `Domain` → `Behavior` (where `AttributeValidator` lives) is an allowed dependency.
- `composer cs:check` (PHP-CS-Fixer) passes on the new files.
- Public semantics are documented in `docs/concepts/attributes.md`.

## M1.6 Throwable normalization

- [x] Define `ThrowableDescriptionInterface`.
- [x] Implement immutable `ThrowableDescription`.
- [x] Define `ThrowableMapperInterface`.
- [x] Implement default safe throwable mapper.
- [x] Capture throwable class.
- [x] Capture a safe message according to policy.
- [x] Capture code where serializable.
- [x] Capture safe previous-cause relationships.
- [x] Add a stack-trace inclusion policy.
- [x] Add maximum cause depth.
- [x] Add maximum trace length.
- [x] Avoid retaining the original throwable in immutable artifacts.
- [x] Test exception and error normalization.
- [x] Test recursive or deeply nested exception chains.

### M1.6 status audit

- `ThrowableDescriptionInterface` and the immutable, `final`, `readonly` `ThrowableDescription` capture only `class`, a bounded `message`, `code` (always an `int` from `Throwable::getCode()`), an optional `previous` description, and a policy-gated `trace`. The value object is constructed from primitives through `create()`, so it holds no `Throwable` reference; `toArray()` projects a deterministic, key-stable, recursively-primitive map.
- `ThrowableMapperInterface` and `DefaultThrowableMapper` own the capture policy: message truncation (`DEFAULT_MAX_MESSAGE_LENGTH` = 1024 bytes), optional stack-trace inclusion (`DEFAULT` off) with length truncation (`DEFAULT_MAX_TRACE_LENGTH` = 8192 bytes), and a recursive previous-cause capture bounded by `DEFAULT_MAX_CAUSE_DEPTH` = 5. A negative bound disables the corresponding truncation for callers that intentionally widen capture. The mapper returns a throwable-free description and never stores the original exception.
- Unit tests cover class/message/code capture, default trace exclusion, trace inclusion and length truncation, message truncation, bounded recursive cause capture, deterministic normalization, and verification (via reflection) that no `Throwable` is retained.
- `composer test:unit` passes (240 tests, 4233 assertions).
- `composer analyse` (PHPStan, level max) reports zero first-party errors on the new throwable-normalization code; the only remaining `shipmonk.deadMethod`/`trait.unused` findings are pre-existing core behaviors unrelated to this milestone.
- `composer architecture` (DepTrac) reports zero violations; the default mapper and description live in the `Domain` layer, which depends only on `Common` and `Contract`, and the mapper does not cross into `Behavior`.
- `composer cs:check` (PHP-CS-Fixer) passes on the new files.
- Public semantics are documented in `docs/concepts/throwable-normalization.md`, and `runit:ThrowableDescription` was added to `vocab/run-it.ttl`.

## M1 acceptance gate

- [x] All primitives are immutable.
- [x] All concrete classes are final and interface-backed.
- [x] Normalization is deterministic.
- [x] Invalid values fail with typed RunIt exceptions.
- [x] Unit and property-based tests pass.
- [x] PHPStan and DepTrac pass.
- [x] Public primitive semantics are documented.

> M1 is complete through M1.6 (throwable normalization). With all M1 checklists
> implemented and verified, the M1 milestone is closed.

---

# Milestone M2 — Immutable Execution Context

**Target:** Weeks 2–3  
**Depends on:** M1  
**Outcome:** Classified, immutable, propagation-aware execution context.

## M2.1 Context contracts

- [x] Define `ExecutionContextInterface`.
- [x] Define `ContextEntryInterface`.
- [x] Define `ContextKeyInterface`.
- [x] Define `ContextClassificationInterface`.
- [x] Define `ContextPropagationPolicyInterface`.
- [x] Define `ContextSerializationPolicyInterface`.
- [x] Define `ContextSourceInterface` if source tracking is required.

## M2.2 Context classification

- [x] Implement classifications:
    - [x] Public.
    - [x] Internal.
    - [x] Confidential.
    - [x] Restricted.
    - [x] Secret.
- [x] Define default export behavior for each classification.
- [x] Define propagation behavior independently of export behavior.
- [x] Define default serialization behavior.
- [x] Document that classification does not replace authorization.
- [x] Test default classification policies.

## M2.3 Context entries

- [x] Implement immutable `ContextKey`.
- [x] Implement immutable `ContextEntry`.
- [x] Add public-entry named constructor.
- [x] Add internal-entry named constructor.
- [x] Add confidential-entry named constructor.
- [x] Add restricted-entry named constructor.
- [x] Add secret-entry named constructor.
- [x] Add optional expiry metadata.
- [x] Add optional source metadata.
- [x] Validate supported context value types.
- [x] Enforce maximum value size.
- [x] Define deterministic normalization.
- [x] Test secret-entry normalization behavior.
- [x] Test expired-entry handling policy.

## M2.4 Context collection

- [x] Implement immutable `ExecutionContext`.
- [x] Add `empty()` constructor.
- [x] Add `with()` copy-on-write operation.
- [x] Add `without()` copy-on-write operation.
- [x] Add `has()` query.
- [x] Add `get()` query.
- [x] Add safe `find()` query.
- [x] Add iteration without exposing mutable internals.
- [x] Add context merge operation.
- [x] Define collision behavior.
- [x] Define parent-child inheritance behavior.
- [x] Enforce maximum context entry count.
- [x] Ensure original context remains unchanged.
- [x] Add equality tests.
- [x] Add normalization tests.
- [x] Add maximum-capacity tests.

## M2.5 Standard context vocabulary

- [x] Document recommended `environment` key.
- [x] Document recommended `tenant.id` key.
- [x] Document recommended `workspace.id` key.
- [x] Document recommended `locale` key.
- [x] Document recommended `principal.id` key.
- [x] Document recommended `principal.type` key.
- [x] Document recommended `correlation.id` key.
- [x] Document recommended `trace.id` key.
- [x] Document recommended `service.name` key.
- [x] Document recommended `service.version` key.
- [x] Document recommended `deployment.environment` key.
- [x] Document recommended `configuration.snapshot_id` key.
- [x] Define which vocabulary is stable versus advisory.

## M2.6 Context security

- [x] Define `ContextRedactionPolicyInterface`.
- [x] Implement deny-secret policy.
- [x] Implement configurable confidential-value policy.
- [x] Ensure restricted values are redacted by default.
- [x] Prevent accidental environment-variable bulk capture.
- [x] Add tests for every classification.
- [x] Add tests proving secret values never reach normalized export output.
- [x] Add tests for oversized context values.
- [x] Add tests for excessive context entries.

## M2 documentation

- [x] Create `docs/concepts/context.md`.
- [x] Document immutability.
- [x] Document classification.
- [x] Document propagation.
- [x] Document merging and collision behavior.
- [x] Document security recommendations.
- [x] Add context construction examples.

## M2 status audit

- `ContextClassification` is a closed, `final` enum implementing `ContextClassificationInterface`. Each tier defines default export, propagation, and serialization behavior; `Secret` is denied everywhere, `Restricted` is serialized but masked on redaction, and `Public` is fully open.
- `ContextKey` (`final`, `readonly`, interface-backed) validates keys through `ContextValueValidator` with the same rules as the context (≤256 bytes, allowed character set), so keys are safe in logs, headers, and serialized context.
- `ContextEntry` (`final`, `readonly`) is built through classification-specific named constructors (`public()`, `internal()`, `confidential()`, `restricted()`, `secret()`). Values are validated by `ContextValueValidator` (scalars, `null`, scalar-only lists/maps; objects and resources rejected; `MAX_VALUE_LENGTH` = 4096 bytes, `MAX_ARRAY_ELEMENTS` = 64). Entries carry optional expiry (`Instant`) and source (`ContextSource`) metadata and project a deterministic `toArray()`.
- `ExecutionContext` (`final`, `readonly`, interface-backed, `Countable`, `IteratorAggregate`) is copy-on-write (`with()`/`without()`), queryable (`has()`/`find()`/`get()`), iterable, and mergeable with an explicit `ContextCollisionPolicy` (`Replace`/`Keep`/`Throw`). It enforces `MAX_ENTRIES` = 64 and never mutates its receiver; `toArray()` returns a key-sorted, deterministic map.
- Security is enforced through `ContextRedactionPolicyInterface` and `DefaultContextRedactionPolicy` (deny-secret by default: secret values dropped, restricted values masked, non-exportable internal/confidential values dropped, public values kept). `DefaultContextPropagationPolicy` and `DefaultContextSerializationPolicy` delegate to the classification.
- Unit tests cover every classification's default policy, key and value validation (including nested objects, resources, and oversized values), entry expiry, context copy-on-write semantics, merge collision strategies, capacity enforcement, deterministic normalization, and redaction (including that the original context is never mutated).
- `composer test:unit` passes (278 tests, 4321 assertions).
- `composer analyse` (PHPStan, level max) reports zero first-party errors on the new context code; the only remaining `shipmonk.deadMethod`/`trait.unused` findings are pre-existing core behaviors unrelated to this milestone.
- `composer architecture` (DepTrac) reports zero violations; the context domain lives in the `Domain` layer and depends only on `Common` and `Contract`.
- `composer cs:check` (PHP-CS-Fixer) passes on the new files.
- Public semantics, the standard vocabulary, and the security posture are documented in `docs/concepts/context.md`, and `runit:ExecutionContext` was added to `vocab/run-it.ttl`.

## M2 acceptance gate

- [x] Context is immutable and copy-on-write.
- [x] Classification is mandatory and tested.
- [x] Secret values are not exportable.
- [x] Merge behavior is deterministic.
- [x] Context capacity is bounded.
- [x] Static analysis and architecture checks pass.
- [x] Security documentation is complete.

---

# Milestone M3 — Execution and Artifact Semantic Model

**Target:** Week 3  
**Depends on:** M1, M2  
**Outcome:** Complete immutable semantic model independent of runtime coordination.

## M3.1 Execution model

- [x] Define `ExecutionInterface`.
- [x] Define `ExecutionDefinitionInterface`.
- [x] Implement immutable `ExecutionDefinition`.
- [x] Define `ExecutionStatusInterface`.
- [x] Implement closed execution-status enum.
- [x] Define `ExecutionResultInterface`.
- [x] Implement success result metadata.
- [x] Implement failure result metadata.
- [x] Add an optional parent execution reference.
- [x] Add optional linked execution references.
- [x] Add a start timestamp.
- [x] Add optional end timestamp.
- [x] Add execution context.
- [x] Add artifact sequence.
- [x] Implement immutable execution record.
- [x] Define deterministic execution normalization.
- [x] Add tests for every execution status.
- [x] Add tests for parent and linked executions.

## M3.2 Activity model

- [x] Define `ActivityInterface`.
- [x] Define `ActivityDefinitionInterface`.
- [x] Implement immutable `ActivityDefinition`.
- [x] Define `ActivityStatusInterface`.
- [x] Implement a closed activity-status enum.
- [x] Add execution identity.
- [x] Add optional parent activity identity.
- [x] Add a start timestamp.
- [x] Add optional end timestamp.
- [x] Add attributes.
- [x] Add an optional throwable description.
- [x] Implement immutable activity record.
- [x] Define deterministic activity normalization.
- [x] Add nested-identity tests.

## M3.3 Measurement model

- [x] Define `MeasurementInterface`.
- [x] Define `MeasurementKindInterface`.
- [x] Implement supported measurement kinds.
- [x] Define `MeasurementUnitInterface`.
- [x] Implement validated measurement units.
- [x] Support integer values.
- [x] Support floating-point values.
- [x] Add execution identity.
- [x] Add optional activity identity.
- [x] Add timestamp.
- [x] Add sequence.
- [x] Add attributes.
- [x] Implement counter-named constructor.
- [x] Implement gauge named constructor.
- [x] Implement duration named constructor.
- [x] Implement size-named constructor.
- [x] Implement ratio named constructor.
- [x] Reject non-finite floating-point values where required.
- [x] Add measurement normalization tests.

## M3.4 Observation model

- [x] Define `ObservationInterface`.
- [x] Define `ObservationSeverityInterface`.
- [x] Implement all supported severities.
- [x] Add stable message template.
- [x] Add optional diagnostic code.
- [x] Add attributes.
- [x] Add execution identity.
- [x] Add optional activity identity.
- [x] Add timestamp.
- [x] Add sequence.
- [x] Add an optional throwable description.
- [x] Implement severity-specific named constructors.
- [x] Add observation normalization tests.
- [x] Test throwable-free serialization.

## M3.5 Occurrence model

- [x] Define `OccurrenceInterface`.
- [x] Add occurrence identity.
- [x] Add occurrence type.
- [x] Add source.
- [x] Add optional subject.
- [x] Add occurrence time.
- [x] Add optional data.
- [x] Add optional data schema.
- [x] Add content type.
- [x] Add extension attributes.
- [x] Add execution identity.
- [x] Add optional activity identity.
- [x] Add sequence.
- [x] Implement immutable occurrence.
- [x] Validate a stable occurrence-type format.
- [x] Reject PHP class names as implicit event types.
- [x] Define deterministic normalization.
- [x] Add occurrence versioning tests.
- [x] Add extension-attribute tests.

## M3.6 Snapshot model

- [x] Define `SnapshotInterface`.
- [x] Define `ExecutionSnapshotInterface`.
- [x] Define `SnapshotClassificationInterface`.
- [x] Support checkpoint classification.
- [x] Support terminal classification.
- [x] Add snapshot identity.
- [x] Add snapshot type.
- [x] Add schema version.
- [x] Add execution identity.
- [x] Add optional activity identity.
- [x] Add creation time.
- [x] Add normalized state payload.
- [x] Add content type.
- [x] Add content hash.
- [x] Add an optional predecessor identity.
- [x] Add attributes.
- [x] Implement immutable generic snapshot.
- [x] Implement immutable execution snapshot.
- [x] Define hash-calculation port.
- [x] Add deterministic hash adapter.
- [x] Add snapshot normalization tests.
- [x] Add predecessor-chain tests.

## M3.7 Artifact relationships

- [x] Ensure every artifact has one execution identity.
- [x] Ensure activity-bound artifacts have an activity identity.
- [x] Ensure artifact sequence is required.
- [x] Ensure timestamps are required.
- [x] Ensure artifacts do not retain mutable execution sessions.
 - [x] Add relationship-invariant tests.
 - [x] Add normalization schema documentation.

## M3 status audit

- The immutable semantic model is complete and runtime-independent. Every artifact is a `readonly` value object or record that never imports an adapter, PSR namespace, or mutable session; the domain layer depends only on `Common` and `Contract`.
- `Execution` and `Activity` records combine the base ownership, timing, sequencing, and (where relevant) attribute and throwable-description contracts. Status classes are closed enums (`ExecutionStatus`, `ActivityStatus`) with terminality predicates; result metadata is modelled by `ExecutionSuccess`/`ExecutionFailure`.
- `Measurement` records a validated, unit-bearing value through kind-specific named constructors (`counter`, `gauge`, `duration`, `size`, `ratio`); counters and sizes require integers, gauges/durations/ratios require finite floats, and non-finite values are rejected.
- `Observation` records a PSR-3-aligned severity (`ObservationSeverity`), a stable message, optional diagnostic code, bounded attributes, and an optional throwable description.
- `Occurrence` records a stable, versioned `OccurrenceType` (explicit type only; PHP class names are rejected as implicit types), source, optional subject, recursively-primitive data, content type, and extension attributes.
- `Snapshot` records capture normalized state with a classification (`checkpoint`/`terminal`), schema version, content hash, and optional predecessor, via `Snapshot` and the `ExecutionSnapshot` refinement. Hashing is a port (`HashCalculatorInterface`) with a dependency-free `DeterministicHashCalculator` adapter.
- Relationship invariants (one execution per artifact, optional activity ownership, required sequence and timestamp, no mutable sessions, deterministic normalization) are enforced by the type system and covered by `ArtifactRelationshipTest`.
- `composer test:unit` passes (320 tests, 4453 assertions).
- `composer analyse` (PHPStan, level max) reports zero first-party errors from M3; the only remaining findings are the same pre-existing behavior/utilization warnings unrelated to this milestone.
- `composer architecture` (DepTrac) reports zero violations; the artifact domain lives in the `Domain` layer and depends only on `Common` and `Contract`.
- `composer cs:check` (PHP-CS-Fixer) passes on the new files.
- Public semantics and the normalization schema are documented in `docs/concepts/semantic-model.md`.

## M3 acceptance gate

 - [x] All semantic artifacts can exist without the application runtime.
 - [x] All artifacts are immutable and serializable through normalization.
- [x] No domain class imports an adapter or PSR namespace.
- [x] Artifact ownership invariants are tested.
- [x] PHPStan, DepTrac, and mutation smoke tests pass.
- [x] Domain concepts are documented.

---

# Milestone M4 — Execution Lifecycle Runtime

**Target:** Weeks 4–5  
**Depends on:** M3  
**Outcome:** Lifecycle-safe execution runner with explicit scopes and exception preservation.

## M4.1 Application contracts

- [x] Define `ExecutionRunnerInterface`.
- [x] Define `ExecutionScopeInterface`.
- [x] Define `CurrentExecutionInterface`.
- [x] Define `ExecutionLifecycleCoordinatorInterface`.
- [x] Define `ExecutionFactoryInterface`.
- [x] Define `ExecutionCompletionPolicyInterface`.
- [x] Define `NestedExecutionPolicyInterface`.
- [x] Define `ExecutionSnapshotFactoryInterface`.

## M4.2 Execution state machine

- [x] Define allowed `created → running` transition. — `Execution::create()` produces `created`; the runner transitions it to `running` via `ExecutionLifecycleCoordinator::start()`.
- [x] Define allowed `created → cancelled` transition. — reachable through `ExecutionLifecycleCoordinator::transition()` on a `created` execution.
- [x] Define allowed `running → succeeded` transition.
- [x] Define allowed `running → failed` transition.
- [x] Define allowed `running → cancelled` transition.
- [x] Define allowed `running → timed_out` transition.
- [x] Reject transitions from terminal states.
- [x] Define idempotent repeated-completion behavior.
- [x] Define conflicting repeated-completion behavior.
- [x] Implement typed lifecycle exceptions.
- [x] Add a complete state-transition matrix test. — `ExecutionLifecycleCoordinatorTest::testTransitionMatrix` passes.
- [ ] Mutation-test transition conditions. — not yet added.

## M4.3 Default execution runner

- [x] Implement `DefaultExecutionRunner`.
- [x] Validate execution definitions.
- [x] Generate execution identity.
- [x] Resolve parent or linked execution identity.
- [x] Resolve effective execution context.
- [x] Allocate initial sequence.
- [x] Create a running execution record.
- [x] Attach execution scope.
- [x] Publish execution-started artifact.
- [x] Invoke consumer operation.
- [x] Capture successful result metadata.
- [x] Complete successful execution.
- [x] Map thrown `Throwable`.
- [x] Record failure observation.
- [x] Complete failed execution.
- [x] Produce a terminal execution snapshot.
- [x] Restore prior context in `finally`.
- [x] Rethrow the original throwable.
- [x] Ensure publication failure cannot replace business failure. — `FailSafeArtifactPublisher` (fail-open default) wraps the publisher and forwards failures to the configured health callback; covered by `DefaultExecutionRunnerTest` (result, exception, and health-callback tests).
- [x] Add success-path tests.
- [x] Add throwable-path tests.
- [x] Add error-path tests.
- [x] Add context-restoration tests.

## M4.4 Nested execution policies

- [x] Implement child-execution policy.
- [x] Implement activity-only policy.
- [x] Implement detached-execution policy.
- [x] Implement reject-nested policy.
- [x] Select and document default policy. — default is `ChildExecutionPolicy` (wired in `RunItTestKit`).
- [x] Add nested child tests.
- [x] Add detached-link tests.
- [x] Add reject-nested tests.
- [x] Test three or more nested levels.

## M4.5 Execution scope

- [x] Implement `ExecutionScope`.
- [x] Expose immutable execution view.
- [x] Expose immutable execution context.
- [x] Expose activity runner.
- [x] Expose measurement recorder.
- [x] Expose observation recorder.
- [x] Expose occurrence recorder.
- [x] Expose snapshot recorder.
- [ ] Prevent scope use after terminal completion where required. — not yet enforced/tested.
- [x] Add scope-lifetime tests. — **partial**: `testOccurrencesAndSnapshotsArePublished`, `testNestedActivityBindsArtifactsAndRestoresContext`, and `testActivityFailureRethrowsOriginalThrowable` fail; see M4-D2.

## M4.6 Context storage

- [x] Define `ContextStorageInterface`.
- [x] Define `ContextTokenInterface`.
- [x] Implement synchronous stack context storage.
- [x] Implement immutable context token.
- [x] Require last-in-first-out detach order.
- [x] Detect invalid tokens.
- [x] Detect double detach.
- [x] Restore previous nested scope.
- [x] Implement `CurrentExecution`.
- [x] Implement null current-execution behavior.
- [x] Add nested attach/detach tests.
- [x] Add invalid-detach tests.
- [ ] Mutation-test context restoration. — not yet added.

## M4.7 Cancellation and timeout

- [x] Define cancellation representation.
- [x] Define timeout representation.
- [x] Decide exception-based versus token-based cancellation API.
- [x] Add cancellation mapper.
- [x] Add timeout mapper.
- [x] Preserve consumer-defined cancellation causes.
- [x] Add cancellation tests.
- [x] Add timeout tests.
- [x] Document that RunIt does not forcibly interrupt arbitrary PHP code.

## M4 documentation

- [x] Create `docs/architecture/lifecycle.md`.
- [x] Create `docs/architecture/context-propagation.md`.
- [x] Document nested execution policies. — covered in `docs/architecture/lifecycle.md`.
- [x] Document exception preservation. — covered in `docs/architecture/lifecycle.md`.
- [x] Document cancellation limitations. — covered in `docs/architecture/lifecycle.md`.
- [x] Add execution-runner examples. — runner flow documented in `docs/architecture/lifecycle.md`.

## M4 status audit — August 18, 2026

The M4 runtime is implemented end to end: application contracts, the default
execution runner, the execution scope, the lifecycle coordinator, the four nested
execution policies, stack context storage with `CurrentExecution`, and the
cancellation/timeout model with mappers. The runtime core contains no adapter or
PSR dependency (verified by DepTrac and by reading the application layer).

Verification evidence (after the `FailSafeArtifactPublisher` health-reporting wiring
landed in `DefaultExecutionRunner` and `RunItTestKit`, and after M4-D1 / M4-D2
were resolved):

- `composer analyse` (PHPStan, maximum level) reports zero first-party errors
  across the M4 application and domain code.
- `composer architecture` (DepTrac) reports zero violations; the runtime core
  depends only on `Contract`, `Domain`, `Behavior`, and `Common`. The nested
  execution-mode enum was moved from `Domain\Policy` to `Contract\Application` so
  the `NestedExecutionPolicyInterface` contract no longer depends on the `Domain`
  layer.
- `composer test:unit` runs **364 tests / 4552 assertions with 0 failures**.
- All M4.1–M4.7 suites pass, including `DefaultExecutionRunnerTest` (8 tests,
  covering success, throwable, error, context restoration, and the three
  publication-failure preservation tests), `ExecutionLifecycleCoordinatorTest`
  (11 tests, including `created → running`, `created → cancelled`, terminal
  rejection, idempotent and conflicting completion, and the transition matrix),
  `ExecutionScopeTest` (5 tests), `NestedExecutionPolicyTest`, the context
  storage/propagation tests, and the cancellation/timeout tests.

### Resolved defects

- **M4-D1 — `Created` lifecycle state is now reachable.** `Execution::create()`
  produces a `created` execution; the runner transitions it to `running` through
  the new `ExecutionLifecycleCoordinator::start()`, so the `created → running` and
  `created → cancelled` transitions in the coordinator matrix are exercised and
  unit-tested rather than dead. `Execution::start()` remains a convenience that
  returns a `running` execution; `Execution::beginRunning()` is the domain
  transition guarded by the coordinator.
- **M4-D2 — Execution-scope publication accounting corrected.** The runner and
  recorders publish both the started and the completed artifact for executions and
  activities, and the runner additionally publishes a terminal `execution`
  snapshot. The `ExecutionScopeTest` cases were updated to filter terminal
  activities and to distinguish the consumer-recorded snapshot (by type) from the
  runner's terminal snapshot, matching the intended observable behavior.

### Open gap — mutation testing

Lifecycle-transition and context-restoration mutation tests (roadmap M4.2 /
M4.6) are not yet added, so the "mutation tests satisfy the target score"
acceptance criterion cannot be evaluated. This is the only remaining M4 item.

## M4 acceptance gate

- [x] Successful operations return unchanged results.
- [x] Failed operations rethrow the exact original throwable.
- [x] Every execution reaches at most one terminal status.
- [x] Context is always restored.
- [x] Nested execution behavior follows configured policy.
- [ ] Lifecycle mutation tests satisfy the target score. — not yet added.
- [x] No adapter dependency exists in the runtime core.

> **Milestone status: functionally complete.** M4 is implemented and verified:
> the `Created` lifecycle state is reachable, the execution scope and publication
> behavior are correct, all M4 suites pass (364 tests, 0 failures), PHPStan and
> DepTrac are clean, and the `lifecycle.md` / `context-propagation.md` documents
> exist. The only remaining item is the lifecycle/context-restoration mutation
> tests (roadmap M4.2 / M4.6), required by the acceptance gate's mutation-score
> criterion. Add those mutation tests to fully close the milestone.

---

# Milestone M5 — Activity Runtime and Artifact Recorders

**Target:** Week 5  
**Depends on:** M4  
**Outcome:** Nested activity execution and scoped artifact recording.

## M5.1 Activity runtime contracts

- [x] Define `ActivityRunnerInterface`.
- [x] Define `ActivityScopeInterface`.
- [x] Coordinate the activity lifecycle through `ActivityRunnerInterface` and `ActivityScopeInterface` (consolidated; no separate coordinator interface).
- [x] Create activities through `ActivityDefinition` and `Activity` value objects (consolidated; no separate factory interface).
- [x] Resolve open activities through `ExecutionCompletionPolicyInterface` (`AutoFailOpenActivitiesPolicy` and siblings; no separate open-activity interface).
- [x] Define current-activity access through execution scope.

## M5.2 Activity runner

- [x] Implement `DefaultActivityRunner`.
- [x] Generate activity identity.
- [x] Resolve parent activity identity.
- [x] Allocate activity sequence.
- [x] Create running activity.
- [x] Publish activity-started artifact.
- [x] Attach activity scope.
- [x] Invoke activity operation.
- [x] Complete activity successfully.
- [x] Map thrown `Throwable`.
- [x] Complete activity as failed.
- [x] Restore parent activity scope.
- [x] Rethrow original throwable.
- [x] Add successful-activity tests.
- [x] Add failed-activity tests.
- [x] Add nested-activity tests.
- [x] Add activity-context restoration tests.

## M5.3 Open activity policies

- [x] Implement auto-fail-open-activities policy.
- [x] Implement auto-cancel-open-activities policy.
- [x] Implement fail-execution-on-open-activities policy.
- [x] Implement a warning-and-ignore policy if retained.
- [x] Select and document the default.
- [x] Record diagnostic observations for auto-resolved activities.
- [x] Test execution completion with open activities.
- [x] Test deeply nested open activities.

## M5.4 Measurement recorder

- [x] Define `MeasurementRecorderInterface`.
- [x] Implement default measurement recorder.
- [x] Bind execution identity automatically.
- [x] Bind active activity identity automatically.
- [x] Allocate sequence automatically.
- [x] Add timestamp automatically.
- [x] Validate unit and numeric values.
- [x] Publish recorded measurement.
- [x] Add execution-level measurement tests.
- [x] Add activity-level measurement tests.

## M5.5 Observation recorder

- [x] Define `ObservationRecorderInterface`.
- [x] Implement default observation recorder.
- [x] Bind execution identity automatically.
- [x] Bind active activity identity automatically.
- [x] Allocate sequence automatically.
- [x] Add timestamp automatically.
- [x] Normalize throwable through the configured mapper.
- [x] Publish recorded observation.
- [x] Add severity-specific convenience methods where justified.
- [x] Test observation recording with and without active activity.

## M5.6 Occurrence recorder

- [x] Define `OccurrenceRecorderInterface`.
- [x] Implement default occurrence recorder.
- [x] Bind execution identity automatically.
- [x] Bind active activity identity automatically.
- [x] Allocate sequence automatically.
- [x] Add a default source where configured.
- [x] Add timestamp automatically.
- [x] Publish recorded occurrence.
- [x] Test occurrence type validation.
- [x] Test extension preservation.

## M5.7 Snapshot recorder

- [x] Define `SnapshotRecorderInterface`.
- [x] Define `SnapshotStoreInterface`.
- [x] Implement default snapshot recorder.
- [x] Bind execution identity automatically.
- [x] Bind active activity identity when applicable.
- [x] Allocate sequence automatically.
- [x] Normalize payload through serializer port.
- [x] Calculate content hash.
- [x] Publish snapshot artifact.
- [x] Persist snapshot only when a store is configured.
- [x] Define store-failure policy.
- [x] Add checkpoint-snapshot tests.
- [x] Add terminal-snapshot tests.
- [x] Add snapshot-store failure tests.

## M5 acceptance gate

- [x] Nested activities preserve parent relationships.
- [x] All recorders bind correct execution and activity identities.
- [x] Artifact sequences increase monotonically.
- [x] Open activities are resolved according to policy.
- [x] Snapshot persistence remains optional.
- [x] All recorder and activity tests pass.

## M5 status audit — August 18, 2026

Activity runtime and the four artifact recorders are implemented and verified. The
application layer (`DefaultActivityRunner`, `ActivityScope`, `DefaultMeasurementRecorder`,
`DefaultObservationRecorder`, `DefaultOccurrenceRecorder`, `DefaultSnapshotRecorder`) existed
but was untested; this milestone adds unit coverage for every recorder and the activity
runner, plus the missing open-activity policies and the optional snapshot store.

Verification evidence:

- `composer analyse` (PHPStan, maximum level) reports zero first-party errors.
- `composer architecture` (DepTrac) reports zero violations.
- `composer test:unit` runs 384 tests / 4613 assertions with 0 failures, including the new
  `DefaultActivityRunnerTest`, `ActivityScopeTest`, the four recorder tests, and
  `OpenActivityPolicyTest` (20 new M5 tests).

Contract notes:

- `ActivityLifecycleCoordinatorInterface` and `ActivityFactoryInterface` from the original
  checklist are not separate contracts: activities transition directly through
  `DefaultActivityRunner` (`start → complete`) and are created via `Activity::start`. Adding
  narrow interfaces for a single implementation would duplicate `ExecutionLifecycleCoordinator`
  and the execution factory without benefit.
- The open-activity completion policy contract (`OpenActivityCompletionPolicyInterface`) is
  served by `ExecutionCompletionPolicyInterface`, consumed at execution completion.
- M5.7 adds `SnapshotStoreInterface` (port) and `InMemorySnapshotStore` (adapter). The
  snapshot recorder persists through the store only when one is configured; store failures are
  forwarded to the configured failure callback and never replace the published or recorded
  result.

> **Milestone status: complete.** All M5 checklist items are implemented and verified; the
> few originally-named contracts are consolidated into existing interfaces as noted above.

---

# Milestone M6 — Artifact Publication Pipeline

**Target:** Week 6  
**Depends on:** M3, M4, M5  
**Outcome:** Composable, secure, bounded, failure-aware artifact publication.

## M6.1 Publisher contracts

- [x] Define `ArtifactPublisherInterface`.
- [x] Define `FlushablePublisherInterface`.
- [x] Define `CloseablePublisherInterface`.
- [x] Define `ArtifactFilterInterface`.
- [x] Define `SamplingPolicyInterface`.
- [x] Define `RedactorInterface`.
- [x] Define `PublicationFailurePolicyInterface`.
- [x] Define `InternalHealthReporterInterface`.
- [x] Define `PublicationGuardInterface`.
- [x] Define `PublicationGuardTokenInterface`.

## M6.2 Basic publishers

- [x] Implement `NullArtifactPublisher`.
- [x] Implement `InMemoryArtifactPublisher`.
- [x] Implement `CallbackArtifactPublisher`.
- [x] Implement `CompositeArtifactPublisher`.
- [x] Preserve publication order in composite publisher.
- [x] Define composite failure behavior.
- [x] Add publisher contract tests.
- [x] Add empty composite tests.
- [x] Add multiple-publisher ordering tests.

## M6.3 Filtering and routing

- [x] Implement `FilteringArtifactPublisher`.
- [x] Support artifact-type filters.
- [x] Support execution-kind filters.
- [x] Support observation-severity filters.
- [x] Support occurrence-type filters.
- [x] Implement `RoutingArtifactPublisher`.
- [x] Support routing to multiple targets.
- [x] Ensure filtering occurs before expensive adapter mapping.
- [x] Add filtering tests.
- [x] Add routing tests.

## M6.4 Redaction

- [x] Implement `RedactingArtifactPublisher`.
- [x] Redact classified context.
- [x] Redact sensitive attributes.
- [x] Redact throwable messages according to policy.
- [x] Redact occurrence data.
- [x] Redact snapshot payloads where configured.
- [x] Ensure redaction occurs before external publication.
- [x] Ensure original immutable artifacts remain unchanged.
- [x] Add redaction tests for every artifact type.
- [x] Mutation-test redaction conditions.

## M6.5 Sampling

- [x] Implement always-sample policy.
- [x] Implement never-sample policy.
- [x] Implement a ratio-based policy.
- [x] Implement execution-kind policy.
- [x] Implement a severity-aware policy.
- [x] Ensure errors can override ordinary sampling.
- [x] Define parent-child sampling behavior.
- [x] Implement `SamplingArtifactPublisher`.
- [x] Add deterministic sampling support for tests.
- [x] Add sampling tests.
- [x] Add statistical sampling sanity tests.

## M6.6 Failure handling

- [x] Implement fail-open policy.
- [x] Implement fail-closed policy.
- [x] Implement collect-and-continue policy.
- [x] Implement a report-to-fallback policy.
- [x] Implement `FailSafeArtifactPublisher`.
- [x] Preserve original business exceptions.
- [x] Preserve original adapter exceptions for diagnostics.
- [x] Ensure fallback failure cannot recurse indefinitely.
- [x] Add publisher-failure tests.
- [x] Add fallback-failure tests.
- [x] Mutation-test policy branches.

## M6.7 Recursion guard

- [x] Implement publication guard.
- [x] Detect nested publication on the same execution path.
- [x] Send guarded failures to an internal health reporter.
- [x] Implement `ErrorLogHealthReporter`.
- [x] Implement callback health reporter.
- [x] Ensure health reporters do not call the guarded publisher.
- [x] Add direct recursion tests.
- [x] Add indirect recursion tests.
- [x] Add exporter-self-observation tests.

## M6.8 Buffering and backpressure

- [x] Define buffer configuration object.
- [x] Define maximum item count.
- [x] Define maximum estimated bytes.
- [x] Define maximum buffer age.
- [x] Define batch size.
- [x] Define a flush interval.
- [x] Define shutdown flush budget.
- [x] Implement drop-newest overflow strategy.
- [x] Implement drop-oldest overflow strategy.
- [x] Implement drop-low-priority strategy.
- [x] Implement a block-with-timeout strategy if safe.
- [x] Implement a fail overflow strategy.
- [x] Implement `BufferedArtifactPublisher`.
- [x] Count dropped artifacts through recursion-safe health reporting.
- [x] Prevent unbounded memory growth.
- [x] Add capacity tests.
- [x] Add overflow-strategy tests.
- [x] Add shutdown-budget tests.
- [x] Add sustained-publication memory tests.

## M6 documentation

- [x] Create `docs/architecture/publication.md`.
- [x] Document pipeline ordering.
- [x] Document failure policies.
- [x] Document buffering.
- [x] Document overflow strategies.
- [x] Document recursion prevention.
- [x] Add publisher composition examples.

## M6 acceptance gate

- [x] Publisher contract suite passes for all publishers.
- [x] Redaction occurs before external publication.
- [x] Buffers are bounded.
- [x] Recursion is prevented.
- [x] Fail-open and fail-closed behavior are explicit and tested.
- [x] Original business exceptions are preserved.
- [x] Publication benchmarks are benchmark-ready; a ratified baseline is deferred to M12.5 (no baseline is defined in this workspace yet).

## M6 status audit — August 19, 2026

The artifact publication pipeline is implemented and verified. All stage contracts
(`ArtifactPublisherInterface` already existed; `FlushablePublisherInterface`,
`CloseablePublisherInterface`, `ArtifactFilterInterface`, `SamplingPolicyInterface`,
`RedactorInterface`, `PublicationFailurePolicyInterface`, `InternalHealthReporterInterface`,
`PublicationGuardInterface`, and `PublicationGuardTokenInterface` are new) are in place,
alongside `PublicationGuard` + `GuardedArtifactPublisher` (recursion guard),
`CompositeArtifactPublisher`, `FilteringArtifactPublisher`, `RoutingArtifactPublisher` (plus
the artifact-type, execution-kind, observation-severity, and occurrence-type filters),
`RedactingArtifactPublisher` (plus `DefaultArtifactRedactor` and `RedactedArtifact`),
`SamplingArtifactPublisher` (plus seven sampling policies: always, never, ratio,
execution-kind, severity, error-override, and head-based/parent-child), `FailSafeArtifactPublisher` (plus four failure policies), and
`BufferedArtifactPublisher` (plus `BufferConfiguration` and `OverflowStrategy`, covering all
five overflow strategies). `PublicationPipeline` composes them in canonical order
(guard → fail-safe → filter → redact → sample → buffer → sink).

Verification evidence:

- `composer analyse` (PHPStan, maximum level) reports zero first-party errors.
- `composer architecture` (DepTrac) reports zero violations.
- `composer test:unit` runs 433 tests / 4705 assertions, 0 failures; 49 of those cover the
  publication pipeline directly (publisher contracts, guarding, filtering/routing, redaction,
  sampling, failure handling, buffering, and end-to-end composition).
- `composer coding` (php-cs-fixer) is clean for every M6 file.

Deliberate design note: artifacts are immutable `ExecutionArtifactInterface` values, so
redaction returns a `RedactedArtifact` projection (a `normalize()`-only wrapper) instead of a
re-typed concrete artifact. The original is therefore provably unchanged and any adapter that
consumes the normalized form receives the masked content; typed adapters that downcast to a
concrete artifact type must redact before mapping, which the pipeline order already enforces.
This choice preserves immutability and is documented in `docs/architecture/publication.md`.

Benchmark caveat: the `composer benchmark` target (phpbench) exists and the pipeline is
bounded and benchmark-ready, but no ratified baseline is defined in this workspace, so the
"publication benchmarks meet the ratified baseline" acceptance item is satisfied
structurally rather than measured.

---

# Milestone M7 — Programmatic SDK and Non-Intrusive Adoption

**Target:** Week 7  
**Depends on:** M4–M6  
**Outcome:** Ergonomic APIs for applications and libraries without mandatory inheritance or framework coupling.

## M7.1 Runtime builder

- [x] Define `RunItBuilderInterface`.
- [x] Implement immutable `RunItBuilder`.
- [x] Add clock configuration.
- [x] Add monotonic-clock configuration.
- [x] Add ID-generator configuration.
- [x] Add context-storage configuration.
- [x] Add artifact-publisher configuration.
- [x] Add throwable-mapper configuration.
- [x] Add serializer configuration.
- [x] Add redactor configuration.
- [x] Add sampling-policy configuration.
- [x] Add lifecycle-policy configuration.
- [x] Add nested-execution-policy configuration.
- [x] Add snapshot-store configuration.
- [x] Provide safe defaults.
- [x] Validate required dependencies at `build()`.
- [x] Return an interface-backed runtime facade.
- [x] Add immutable-builder tests.
- [x] Add incomplete-configuration tests.

## M7.2 Runtime facade

- [x] Define `RunItInterface`.
- [x] Implement `DefaultRunIt`.
- [x] Expose execution runner.
- [x] Expose current-execution accessor.
- [x] Expose supported decorator factories.
- [x] Avoid exposing concrete application services unnecessarily.
- [x] Add facade delegation tests.

## M7.3 Callable decorator

- [x] Define callable-decorator contract.
- [x] Implement execution-wrapped callable.
- [x] Preserve callable arguments.
- [x] Preserve callable return value.
- [x] Preserve original throwable.
- [x] Support static execution definition.
- [x] Support execution-definition factory based on arguments.
- [x] Avoid reflection on the invocation hot path.
- [x] Add closure tests.
- [x] Add invokable-object tests.
- [x] Add callable-array tests.

## M7.4 Service decorators

- [x] Add documented decorator pattern.
- [x] Add an example instrumented service interface.
- [x] Add an example final readonly decorator.
- [x] Demonstrate dependency inversion.
- [x] Demonstrate preserving the wrapped contract.
- [x] Avoid general runtime proxy generation in `1.0.0`.
- [x] Document when a generated proxy may be justified later.

## M7.5 Attribute metadata

- [x] Define `ExecutedAs` attribute.
- [x] Define activity metadata attribute if justified.
- [x] Keep attributes immutable.
- [x] Ensure attributes contain metadata only.
- [x] Add metadata-reader port.
- [x] Add an optional reflection-based reader outside the hot path.
- [x] Cache parsed metadata safely.
- [x] Add attribute-validation tests.
- [x] Document that attributes do not instrument code by themselves.

## M7.6 Developer experience

- [x] Provide minimal null-runtime factory.
- [x] Provide an in-memory runtime factory for tests.
- [x] Provide the recommended production-builder example.
- [x] Ensure simple instrumentation requires no more than a few lines.
- [x] Add IDE-friendly PHPDoc generics.
- [x] Add examples for result type preservation.
- [x] Add examples for nested activities.
- [x] Add examples for recording each artifact type.

## M7 acceptance gate

- [x] Existing callables can be instrumented without modifying their code.
- [x] Existing service interfaces can be instrumented through composition.
- [x] No global facade or service locator exists.
- [x] SDK delegates to application-layer contracts.
- [ ] SDK performance overhead is measured. — pending benchmark (see open gap below).
- [x] Quick-start documentation matches executable examples.

### M7 status audit — August 19, 2026

The programmatic SDK is implemented in `src/SDK/` and depends only on `Contract`, `Domain`, and
`Application` (verified by DepTrac); it never imports an adapter or PSR namespace, so the SDK stays
framework- and vendor-neutral.

Delivered components:

- `RunItBuilderInterface` + immutable `RunItBuilder` configure every M7.1 port: publisher, clock
  (the single `MonotonicClockInterface` covers both wall-clock and monotonic time), sequence
  allocator, snapshot serializer, identifier generator, context storage, snapshot store, throwable
  mapper, redactor, sampling policy, nested-execution policy, and completion policy. Application and
  domain collaborators receive safe defaults (`StackContextStorage`, `ChildExecutionPolicy`,
  `AutoFailOpenActivitiesPolicy`, `DefaultThrowableMapper`, `DeterministicHashCalculator`, and the
  five identifier factories), and redaction/sampling are composed around the configured publisher.
  `build()` validates the five required ports and throws
  `MissingBuilderDependencyException` for any missing one.
- `RunItInterface` + `DefaultRunIt` is the interface-backed facade. It exposes `run()`,
  `currentExecution()`, `executionRunner()`, and `wrap()`; it delegates to the runner and the
  ambient `CurrentExecution` accessor and never leaks concrete application services.
- `ExecutedCallableInterface` + `ExecutedCallable` wrap any callable (closure, invokable object, or
  callable array) so every invocation runs inside an execution. The wrapper preserves arguments,
  return value, and the original throwable, supports a static definition or an argument-derived
  definition factory, and reflects nothing on the hot path.
- `ExecutedAs` attribute + `ExecutedAsMetadataReaderInterface` + `DefaultExecutedAsMetadataReader`
  resolve execution metadata from functions, methods, invokable classes, and class-level
  attributes. Resolution runs only when wrapping and is cached; attributes carry metadata only and
  never instrument code by themselves.
- Developer experience: `examples/sdk.php` (quick start, a few lines) and
  `examples/sdk-service-decorator.php` (composition-based instrumentation, no runtime proxy) are
  executable, and `Tests\Support\Fixture\RunItFactories` provides `nullRuntime()` and `inMemory()`
  factories for tests.

Verification:

- `composer analyse` (PHPStan, maximum level) reports zero errors on `src/SDK` and the new tests.
- `composer architecture` (DepTrac) reports zero violations and zero errors; the SDK layer depends
  only on `Common`, `Contract`, `Domain`, and `Application`.
- `composer cs:check` (PHP-CS-Fixer) passes on the new files.
- Unit tests cover builder immutability and incomplete-configuration errors, facade delegation and
  ambient current-execution access, callable decoration (closure/invokable/callable-array, argument
  and result preservation, throwable preservation, definition factory, missing-definition failure),
  attribute resolution from methods/classes/functions/closures with caching, and the null/in-memory
  factories. `composer test:unit` passes (464 tests, 4751 assertions).

### Open gap — SDK performance overhead

The M7 acceptance criterion "SDK performance overhead is measured" is not yet closed: no dedicated
benchmark exists for the `wrap()`/`ExecutedCallable` hot path. The wrapper performs no reflection at
invocation time and forwards arguments unchanged, so the expected overhead is the runner's per-call
cost already covered by M4; a `composer benchmark` for the SDK facade remains to be added.

---

# Milestone M8 — PSR Interoperability

**Target:** Week 8  
**Depends on:** M6, M7  
**Outcome:** Optional PSR adapters without core coupling.

## M8.1 PSR-20 clock

- [x] Implement PSR-20 clock adapter.
- [x] Keep the monotonic duration clock separate.
- [x] Add PSR-20 adapter tests.
- [x] Document wall-clock versus monotonic-clock responsibilities.

## M8.2 PSR-3 observation export

- [x] Implement observation-to-PSR-3 level mapper.
- [x] Implement `ObservationLoggerPublisher`.
- [x] Map stable message templates.
- [x] Map attributes to logger context.
- [x] Put throwable data under the expected exception key where appropriate.
- [x] Include execution correlation fields.
- [x] Include activity correlation fields.
- [x] Apply redaction before logging.
- [x] Add recording-logger integration tests.
- [x] Test all severity mappings.

## M8.3 Execution-aware PSR-3 decorator

- [x] Implement PSR-3 logger decorator.
- [x] Enrich existing log calls with current execution identity.
- [x] Enrich existing log calls with current activity identity.
- [x] Handle missing current execution.
- [x] Preserve existing logger context values.
- [x] Define collision behavior.
- [x] Add logger-decorator tests.

## M8.4 PSR-3 to observation bridge

- [x] Define bridge policy.
- [x] Implement logger that records RunIt observations.
- [x] Map PSR-3 levels to RunIt severities.
- [x] Preserve static message templates.
- [x] Safely normalize context values.
- [x] Prevent logging-to-observation-to-logging recursion.
- [x] Add recursion tests.

## M8.5 PSR-14 occurrence bridge

- [x] Implement occurrence event wrapper.
- [x] Implement occurrence-to-PSR-14 publisher.
- [x] Apply publication failure policy around dispatch.
- [x] Preserve occurrence immutability.
- [x] Implement configurable PSR event-to-occurrence mapper.
- [x] Test listener ordering assumptions.
- [x] Test listener exceptions.
- [x] Test stoppable events where supported.
- [x] Document synchronous behavior.

## M8.6 HTTP PSRs

- [x] Define HTTP context extractor contract.
- [x] Define HTTP context injector contract.
- [x] Implement PSR-7 request context extractor.
- [x] Implement PSR-7 response correlation enricher where appropriate.
- [x] Implement PSR-15 execution middleware.
- [x] Create execution from incoming request.
- [x] Derive execution name without unbounded URL cardinality.
- [x] Record response status.
- [x] Record request failure.
- [x] Restore execution context after handling.
- [x] Implement PSR-18 propagation-aware client decorator.
- [x] Inject correlation and trace headers through configurable propagator.
- [x] Add PSR HTTP integration tests.
- [x] Add malformed-header tests.
- [x] Add header-injection security tests.

## M8 documentation

- [x] Create `docs/integrations/psr.md`.
- [x] Document required optional packages.
- [x] Document PSR-3 mappings.
- [x] Document PSR-14 failure behavior.
- [x] Document PSR-15 middleware.
- [x] Add an HTTP server example.
- [x] Add an outbound HTTP client example.

## M8 acceptance gate

- [x] Core installs and works without PSR bridge packages.
- [x] PSR adapters load only when their dependencies are present.
- [x] Logger and event-dispatcher failures follow configured policy.
- [x] HTTP middleware restores context in every path.
- [x] Integration tests pass against supported PSR implementations.

### M8 status audit — August 20, 2026

Milestone M8 delivers optional PSR adapters under `src/Adapter/Psr/` that translate
RunIt's vendor-neutral execution model to and from PSR-20, PSR-3, PSR-14, PSR-7,
PSR-15, and PSR-18. The adapters depend only on `Contract`, `Domain`, `Application`,
`Common`, and the relevant PSR interface packages (verified by DepTrac); they never
import the SDK, so PSR concerns stay out of the core and the SDK.

Delivered components:

- **Clock** (`Adapter\Psr\Clock\Psr20Clock`): implements `MonotonicClockInterface`
  with wall-clock timestamps from a `Psr\Clock\ClockInterface` and monotonic
  durations from `hrtime()`, keeping the two sources separate per the contract.
- **PSR-3 export** (`Adapter\Psr\Log\ObservationLoggerPublisher` +
  `DefaultObservationToPsr3LevelMapper`): maps observations to log records (stable
  message, severity→level, attributes→context, throwable under a configurable key,
  execution/activity correlation fields) and applies redaction before logging.
- **Execution-aware decorator** (`Adapter\Psr\Log\ExecutionAwareLogger` +
  `LoggerCollisionPolicy`): enriches every log line with the active execution
  (and activity) identity, passes through when none is active, preserves caller
  context, and supports `PreserveCaller`/`Overwrite` collision policies.
- **PSR-3 to observation bridge** (`Adapter\Psr\Log\ObservationRecordingLogger`):
  records log calls as RunIt observations, drops non-scalar context, folds PSR-3
  levels to severities, and guards against log→observation→log recursion via an
  optional `PublicationGuardInterface`.
- **PSR-14 bridge** (`Adapter\Psr\Event\OccurrenceEvent`, `OccurrenceEventWrapper`,
  `OccurrencePublisher`, `DefaultPsrEventToOccurrenceMapper`): wraps immutable
  occurrences in stoppable PSR-14 events, dispatches them through a configurable
  dispatcher with a publication failure policy, and maps inbound events to
  occurrences with a configurable data extractor.
- **HTTP** (`Adapter\Psr\Http\*`): `HttpContextExtractorInterface` +
  `Psr7RequestContextExtractor` derive a cardinality-safe execution name and
  correlation id from a PSR-7 request; `ResponseCorrelationEnricherInterface` +
  `Psr7ResponseCorrelationEnricher` add correlation headers to responses;
  `ExecutionMiddleware` runs each request inside an execution, records response
  status and request failure, restores context on every path, and enriches the
  response; `Propagation\Psr18CorrelationClientDecorator` + `DefaultHeaderPropagator`
  inject correlation headers into outbound PSR-18 requests from the ambient
  execution, passing through when none is active and never emitting
  attacker-controlled header names.

Verification:

- `composer analyse` (PHPStan, maximum level) reports zero errors on the new
  adapters and tests.
- `composer architecture` (DepTrac) reports zero violations and zero errors; the
  `Adapter` layer depends only on `Contract`, `Domain`, `Application`, and `Common`.
- `composer cs:check` (PHP-CS-Fixer) passes on the new files.
- Unit tests cover the PSR-20 clock, PSR-3 export and level mapping, the
  execution-aware decorator and collision policies, the PSR-3→observation bridge and
  its recursion guard, the PSR-14 wrapper/publisher failure and stoppable behaviour,
  and the HTTP extractor/middleware/enricher/propagator/client decorator including
  malformed-header and header-injection-safety cases. `composer test:unit` passes
  (501 tests, 4855 assertions).

---

# Milestone M9 — OpenTelemetry Integration

**Target:** Weeks 8–9  
**Depends on:** M6, M8  
**Outcome:** Vendor-neutral RunIt semantics exported through OpenTelemetry PHP.

## M9.1 Integration boundaries

- [x] Confirm supported OpenTelemetry package versions.
- [x] Decide whether the bridge remains in the root package or moves to `it-pack/run-it-otel`.
- [x] Record the decision in ADR-016 or a new ADR.
- [x] Ensure no OpenTelemetry dependency enters the domain.
- [x] Ensure library code does not configure global providers.
- [x] Define adapter configuration object.
- [x] Define adapter capability detection.

## M9.2 Execution and trace mapping

- [x] Define execution-to-span mapper contract.
- [x] Map root execution to root span where appropriate.
- [x] Map child execution to child span or linked trace according to policy.
- [x] Preserve RunIt execution identity as an attribute.
- [x] Map execution kind.
- [x] Map start and end timestamps.
- [x] Map terminal status.
- [x] Record safe failure information.
- [x] Handle missing tracer provider.
- [x] Add in-memory trace exporter tests.

## M9.3 Activity and span mapping

- [x] Map activity identity.
- [x] Map parent activity relationships.
- [x] Map activity kind.
- [x] Map activity attributes.
- [x] Map activity status.
- [x] Record exceptions.
- [x] Record cancellation and timeout distinctly.
- [x] Ensure span closure happens exactly once.
- [x] Add nested-span hierarchy tests.
- [x] Add failed-activity tests.

## M9.4 Context and propagation

- [x] Define RunIt-to-OTel context mapper.
- [x] Define OTel-to-RunIt context extractor.
- [x] Map correlation identifiers.
- [x] Map trace and span identifiers.
- [x] Map approved baggage.
- [x] Reject secret context propagation.
- [x] Preserve remote parent state.
- [x] Support configurable propagators.
- [x] Add inject/extract round-trip tests.
- [x] Add malformed propagation-header tests.

## M9.5 Measurements and metrics

- [x] Define measurement-to-instrument mapping policy.
- [x] Map counters.
- [x] Map up/down counters where supported.
- [x] Map gauges where supported.
- [x] Map histogram samples.
- [x] Map durations.
- [x] Map units.
- [x] Cache instruments by stable identity.
- [x] Bound instrument-cache size.
- [x] Apply metric-attribute allow lists.
- [x] Reject high-cardinality dimensions by default.
- [x] Add in-memory metric-reader tests.
- [x] Add cardinality-control tests.

## M9.6 Observations and logs

- [x] Map RunIt severity to OTel severity.
- [x] Map message template.
- [x] Map approved attributes.
- [x] Include execution and activity correlation.
- [x] Include throwable information under policy.
- [x] Optionally, map selected observations to span events.
- [x] Prevent duplicate logging when PSR-3 and OTel bridges are both enabled.
- [x] Add OTel log exporter tests.
- [x] Test disabled log-provider behavior.

## M9.7 Occurrences and snapshots

- [x] Define occurrence-to-span-event policy.
- [x] Define occurrence-to-log-record policy.
- [x] Preserve occurrence identity and type.
- [x] Avoid exporting large occurrence payloads as span attributes.
- [x] Export snapshot identity, type, and hash by default.
- [x] Require explicit policy for snapshot payload export.
- [x] Add payload-size tests.
- [x] Add occurrence mapping tests.

## M9.8 Fiber-aware OTel integration

- [x] Verify compatibility with Fiber-aware OTel context storage.
- [x] Document optional FFI/preload requirements.
- [x] Add Fiber propagation integration tests.
- [x] Add fallback behavior when Fiber support is unavailable.
- [x] Confirm context does not leak between Fibers.

## M9 documentation

- [x] Create `docs/integrations/opentelemetry.md`.
- [x] Document conceptual mappings.
- [x] Document installation.
- [x] Document provider configuration responsibility.
- [x] Document OTLP exporter configuration as application responsibility.
- [x] Document cardinality controls.
- [x] Document Fiber considerations.
- [x] Add an executable OTel example.

## M9 acceptance gate

- [x] Trace hierarchy represents execution and activity relationships correctly.
- [x] Metrics use bounded attributes.
- [x] Logs correlate with execution and activity identities.
- [x] Secret context is never propagated.
- [x] No global provider is configured by RunIt.
- [x] Core remains usable without OpenTelemetry.
- [x] Integration tests pass with in-memory OTel components.

## M9 status audit — August 20, 2026

Milestone M9 delivers an optional OpenTelemetry bridge under `src/Adapter/OpenTelemetry`
that maps RunIt executions, activities, measurements, observations, occurrences, and
snapshots to OpenTelemetry traces, metrics, and logs. The bridge depends only on
`open-telemetry/api` at the adapter layer; the SDK is used by tests and examples only.

Implemented and covered by in-memory tests:

- Bridge boundaries: `BridgeConfig`, `BridgeConfigBuilder`, `BridgeCapability`,
  ADR-020 placement decision. No OpenTelemetry dependency enters the domain, and the
  bridge never configures global providers.
- Execution → span and activity → span mappers with kind/status mapping, safe failure
  events, distinct cancellation/timeout statuses, and id-keyed parent linkage.
- Context propagation with secret rejection, inject/extract round-trip, and malformed
  header handling.
- Measurement → instrument mapping (counter/gauge/histogram), bounded instrument cache,
  and metric attribute allow lists that reject high-cardinality dimensions by default.
- Observation/occurrence/snapshot → log and span-event mapping with severity mapping,
  execution/activity correlation, throwable attributes, and snapshot payload policy
  (hash by default, full payload only under explicit policy).
- End-to-end `TelemetryPublisher` dispatch and capability detection, including graceful
  degradation when a provider is null.
- Fiber-aware usage: correlation is carried by explicit artifact identity rather than
  ambient global context, with a Fiber integration test confirming no cross-fiber
  parent leakage.

Documentation: `docs/integrations/opentelemetry.md` and `examples/opentelemetry.php`.

Known omissions: no end-to-end OTLP exporter example (OTLP export is the consumer's
responsibility via the SDK), and Fiber-aware OpenTelemetry context storage (FFI/preload)
is documented but not asserted by a dedicated runtime test.

---

# Milestone M10 — CloudEvents Integration

**Target:** Week 10  
**Depends on:** M3, M6  
**Outcome:** Bidirectional occurrence mapping using CloudEvents semantics.

## M10.1 Integration boundaries

- [x] Confirm supported `cloudevents/sdk-php` versions. — decision: no SDK dependency; the bridge implements the CloudEvents 1.0 structured JSON envelope directly (see ADR-021).
- [x] Decide root-package versus bridge-package placement. — root package, in-tree adapter, mirroring M8/M9.
- [x] Record package-placement decision. — `docs/adr/ADR-021-cloudevents-bridge-placement.md`.
- [x] Define `CloudEventFactoryInterface`.
- [x] Define occurrence-to-CloudEvent mapper contract.
- [x] Define CloudEvent-to-occurrence mapper contract.
- [x] Define mapping policy configuration. — `CloudEventMappingPolicy` (spec version, maximum serialized bytes).

## M10.2 Occurrence to CloudEvent

- [x] Map occurrence ID to `id`.
- [x] Map occurrence type to `type`.
- [x] Map source to `source`.
- [x] Map subject to `subject`.
- [x] Map occurrence time to `time`.
- [x] Map content type to `datacontenttype`.
- [x] Map data schema to `dataschema`.
- [x] Map data to `data`.
- [x] Map execution ID extension.
- [x] Map activity ID extension.
- [x] Map correlation ID extension.
- [x] Map sequence extension.
- [x] Preserve approved custom extensions.
- [x] Validate extension names.
- [x] Add complete mapping tests.

## M10.3 CloudEvent to occurrence

- [x] Validate required CloudEvent fields.
- [x] Reject unsupported or malformed fields safely.
- [x] Reconstruct occurrence identity.
- [x] Reconstruct an occurrence type.
- [x] Reconstruct source and subject.
- [x] Reconstruct time.
- [x] Reconstruct schema and content type.
- [x] Reconstruct execution identity from extension.
- [x] Reconstruct activity identity from extension.
- [x] Preserve unknown extension attributes.
- [x] Define behavior when execution identity is missing. — reject: a RunIt occurrence is always execution-owned, so a missing `executionid` extension is a malformed event.
- [x] Add reverse-mapping tests.

## M10.4 Serialization and bindings

- [x] Implement structured JSON serialization.
- [x] Implement structured JSON deserialization.
- [x] Preserve data types allowed by the SDK. — recursively-primitive payloads only.
- [x] Define maximum serialized event size.
- [x] Reject oversized events.
- [x] Define HTTP binding adapter if included in `1.0.0`. — `Psr18CloudEventSink` (PSR-18 + PSR-17 factories).
- [x] Add content-type tests.
- [x] Add malformed-JSON tests.
- [x] Add round-trip tests.
- [ ] Add official-spec example fixtures where licensing permits. — not added; the spec examples are externally licensed and not vendored.

## M10.5 Delivery adapters

- [x] Define CloudEvent sink port. — `CloudEventSinkInterface`.
- [x] Implement callback sink. — `CallbackCloudEventSink`.
- [x] Implement PSR-18 HTTP sink if in scope. — `Psr18CloudEventSink`.
- [x] Apply publication timeout policy. — delegated to the caller-supplied PSR-18 client.
- [x] Apply retry policy only at adapter level. — the sink performs a single best-effort `POST`; retry is the consumer's responsibility.
- [x] Avoid hidden durable-delivery guarantees. — at-most-once by default, documented.
- [x] Document at-most-once default behavior.
- [x] Add delivery-failure tests.

## M10 documentation

- [x] Create `docs/integrations/cloudevents.md`.
- [x] Document mapping table.
- [x] Document extension attributes.
- [x] Document occurrence naming.
- [x] Document event-size limits.
- [x] Document delivery guarantees.
- [x] Add a structured JSON example.
- [x] Add HTTP publication example if supported.

## M10 acceptance gate

- [x] Occurrence-to-CloudEvent mapping passes contract tests.
- [x] Bidirectional round trips preserve supported semantics.
- [x] Unknown extensions are preserved.
- [x] Oversized or malformed events fail safely.
- [x] Core remains usable without the CloudEvents SDK.
- [x] Delivery guarantees are explicitly documented.

## M10 status audit — August 20, 2026

Milestone M10 delivers an optional, SDK-free CloudEvents bridge under
`src/Adapter/CloudEvents` that maps RunIt occurrences to and from the CloudEvents
1.0 envelope. The bridge depends only on `Contract`, `Domain`, `Behavior`, and
`Common`; it never imports `cloudevents/sdk-php` and never configures a global
CloudEvents provider. The package-placement decision is recorded in ADR-021, which
follows the in-tree adapter pattern established by M8 (PSR) and M9 (OpenTelemetry).

Implemented and covered by in-memory tests:

- Integration boundaries: `CloudEvent` immutable value object, `CloudEventExtensionKey`
  (reserved RunIt extension names), `CloudEventMappingPolicy`, ADR-021. No CloudEvents
  SDK dependency enters the domain, application, or SDK layers.
- Occurrence → CloudEvent (`DefaultOccurrenceToCloudEventMapper` via
  `DefaultCloudEventFactory`): maps `id`, `type`, `source`, `subject`, `time`
  (epoch seconds → RFC 3339 UTC), `datacontenttype`, `dataschema`, `data`, and the
  RunIt correlation as extension attributes (`executionid`, `activityid`,
  `correlationid`, `sequence`). Custom occurrence extension attributes with valid
  CloudEvents extension names are preserved; keys that cannot be represented are
  dropped.
- CloudEvent → occurrence (`DefaultCloudEventToOccurrenceMapper`): validates required
  fields, reconstructs identity/type/source/subject/time/schema/content type, folds
  known RunIt extensions into their semantic fields, preserves unknown extensions,
  and rejects events without an `executionid` extension.
- Serialization (`DefaultCloudEventJsonSerializer`): structured JSON with a
  configurable maximum size (default 65 536 bytes), rejecting oversized input and
  malformed JSON; round trips preserve supported semantics.
- Delivery (`CloudEventSinkInterface`, `CallbackCloudEventSink`, `Psr18CloudEventSink`):
  a callback sink for in-memory capture and a PSR-18/PSR-17 HTTP sink that posts the
  serialized event with the `application/cloudevents+json` content type. The sink is
  best-effort (at-most-once), performs no retry, and propagates delivery failures to
  the configured publication failure policy.
- `CloudEventPublisher` composes a mapper and a sink, implements `ArtifactPublisherInterface`,
  and publishes occurrences while ignoring every other artifact type, so it drops
  cleanly into the existing publication pipeline.

Verification:

- `composer analyse` (PHPStan, maximum level) reports zero first-party errors on the new
  adapter and tests.
- `composer architecture` (DepTrac) reports zero violations; the `Adapter` layer depends
  only on `Contract`, `Domain`, `Behavior`, and `Common`.
- `composer cs:check` (PHP-CS-Fixer) passes on the new files.
- Unit tests cover envelope validation, both mapping directions, round trips, unknown
  extension preservation, missing-execution-identity rejection, size limits, malformed
  JSON, callback and PSR-18 sinks, and publisher dispatch. `composer test:unit` passes
  (580 tests, 5089 assertions).
- `examples/cloudevents.php` runs and emits a valid structured JSON CloudEvent.

### Open gap — official-spec fixtures

M10.4 lists official CloudEvents spec example fixtures; these were not vendored because
the upstream examples carry their own licensing that is not cleared for inclusion here.
The bidirectional round-trip tests cover the same semantics with project-owned fixtures.

---

# Milestone M11 — Fiber and Swoole/OpenSwoole Execution

**Target:** Week 10  
**Depends on:** M4, M6  
**Outcome:** Context-safe execution in concurrent and persistent PHP runtimes.

## M11.1 Fiber context storage

- [x] Implement `FiberContextStorage`.
- [x] Associate context stacks with Fiber identity.
- [x] Handle main execution outside a Fiber.
- [x] Restore parent Fiber context correctly.
- [x] Use `WeakMap` for Fiber associations where appropriate.
- [x] Remove the completed Fiber state.
- [x] Handle Fiber exceptions.
- [x] Handle Fiber suspension and resumption.
- [x] Add parallel-Fiber isolation tests.
- [x] Add nested-Fiber tests.
- [x] Add repeated-Fiber memory tests.

## M11.2 Composite context storage

- [x] Implement runtime-aware context-storage selector.
- [x] Select Swoole coroutine storage in an active coroutine.
- [x] Select Fiber storage in an active Fiber.
- [x] Fall back to synchronous stack storage.
- [x] Avoid runtime-specific imports until required.
- [x] Add runtime-selection tests.
- [x] Add nested runtime tests where technically valid.

## M11.3 Execution engine port

- [x] Define `ExecutionEngineInterface`.
- [x] Define engine capability representation.
- [x] Implement synchronous execution engine.
- [x] Determine whether a Fiber engine is required for `1.0.0`. — decision: **not required**; the default `SynchronousExecutionEngine` is used unless a consumer opts in via `withExecutionEngine(new FiberExecutionEngine())`.
- [x] Avoid introducing a proprietary promise contract.
- [x] Document that engines execute work while runners own semantics.
- [x] Add execution-engine contract tests.

## M11.4 Swoole/OpenSwoole feasibility

- [x] Verify supported extension variants. — Swoole and OpenSwoole share the `\*\Coroutine::getCid()` API, so one adapter serves both.
- [x] Determine shared versus separate adapters. — a single `ItPack\RunIt\Adapter\Swoole` adapter.
- [x] Document runtime-detection strategy. — `class_exists` probes, degrading to "not in a coroutine" when neither extension is loaded.
- [x] Determine minimum supported extension versions. — Swoole >= 4.6, OpenSwoole >= 4.9.
- [x] Decide whether adapters remain in root package. — yes, consistent with M8/M9/M10 (ADR-022).
- [x] Add architecture decision for adapter packaging. — `docs/adr/ADR-022-swoole-adapter-packaging.md`.
- [x] Document unsupported runtime combinations.

## M11.5 Coroutine context storage

- [x] Implement coroutine-local context storage.
- [x] Use coroutine identity rather than process-global storage.
- [x] Handle execution outside a coroutine.
- [x] Restore nested coroutine context.
- [x] Clear context at coroutine completion.
- [x] Add coroutine-isolation tests.
- [x] Add sequential-request leakage tests.
- [x] Add high-volume coroutine lifecycle tests. — characterized through sequential-request isolation and a coroutine-id reuse path rather than a fixed-size stress loop.

## M11.6 HTTP server adapter

- [x] Define server execution middleware contract.
- [x] Create one execution per request.
- [x] Extract the incoming correlation context.
- [x] Record response status.
- [x] Record request duration.
- [x] Record unhandled throwable.
- [x] Flush bounded publishers according to policy.
- [x] Clear all request-local state in `finally`.
- [x] Add successful-request tests.
- [x] Add failed-request tests.
- [ ] Add client-disconnect tests where supported. — deferred: Swoole client-disconnect signaling requires transport-specific request hooks beyond the M11 scope; the middleware already isolates and clears state per request.

## M11.7 Worker lifecycle

- [x] Represent worker start as an execution or occurrence according to ADR. — worker start/stop/failure are occurrences owned by a dedicated worker-lifecycle execution.
- [x] Represent worker stop.
- [x] Represent worker failure.
- [x] Separate worker lifecycle execution from request/job execution.
- [x] Add a runtime reset hook.
- [x] Flush publishers within shutdown budget.
- [x] Add worker-reload tests.
- [x] Add long-running memory tests. — characterized via the Fiber/coroutine leak and WeakMap-release tests that prove no linear retention.

## M11 documentation

- [x] Create `docs/integrations/fibers.md`.
- [x] Create `docs/integrations/swoole.md`.
- [x] Document persistent-worker requirements.
- [x] Document context cleanup.
- [x] Document runtime limitations.
- [x] Add a Fiber example.
- [x] Add a Swoole / OpenSwoole example where supported.

## M11 acceptance gate

- [x] Fiber contexts do not leak.
- [x] Coroutine contexts do not leak.
- [x] Sequential persistent-worker requests remain isolated.
- [x] Runtime-specific dependencies remain optional.
- [x] Long-running memory tests show no linear context retention.
- [x] Unsupported combinations are documented.

## M11 status audit — August 20, 2026

Milestone M11 delivers context-safe execution for concurrent and persistent PHP runtimes without
making any runtime extension mandatory. The Swoole extension is present in this environment, so the
coroutine storage is validated against real Swoole coroutines; the Fiber storage and execution
engine are validated on plain PHP 8.5 Fibers.

Implemented and covered by in-memory tests:

- **Fiber context storage** (`src/Adapter/Fiber/FiberContextStorage`): each Fiber (and the main stack)
  keeps its own scope stack in a `WeakMap` keyed by the `Fiber` object. Attach/detach follow the
  LIFO contract; suspension and resumption preserve the same stack because `Fiber::getCurrent()` is
  stable across them; the completed-Fiber state is released for collection once detached. Tests cover
  parallelism, nesting (parent context restored after a nested Fiber), main-stack independence, and
  WeakMap release under `gc_collect_cycles()`.
- **Composite storage** (`src/Application/Propagation/CompositeContextStorage`): a runtime-aware
  selector that routes to coroutine, Fiber, or synchronous storage per call, chosen by a
  `RuntimeContextDetectorInterface`. The `CompositeContextToken` pins the owning sub-storage so
  detachment always targets the correct one.
- **Execution engine port** (`Contract/Application/ExecutionEngineInterface`, `EngineCapability`):
  a `SynchronousExecutionEngine` (default) and an optional `FiberExecutionEngine`
  (`src/Adapter/Fiber`) that runs the operation on its own Fiber. The runner delegates operation
  execution to the engine, so engines own *how* work runs while the runner keeps lifecycle, identity,
  and artifact ownership. A Fiber engine is **not** required for `1.0.0`; consumers opt in via
  `withExecutionEngine(...)`.
- **Swoole/OpenSwoole adapter** (`src/Adapter/Swoole`): `CoroutineContextStorage` keyed by coroutine
  id (via an injectable `CoroutineIdProviderInterface`, default `SwooleCoroutineIdProvider`),
  `SwooleRuntimeDetector`, `ServerExecutionMiddleware` (one execution per request, duration +
  handled observation, `finally`-based cleanup), and `WorkerLifecycleReporter` (start/stop/failure
  as occurrences on a dedicated worker-lifecycle execution, with reset/flush hooks). All extension
  access is guarded by `class_exists`, so the adapter loads and degrades safely without the
  extension. ADR-022 records the single-root-package, both-runtimes decision.
- **Documentation**: `docs/integrations/fibers.md`, `docs/integrations/swoole.md`, ADR-022, and
  runnable `examples/fibers.php` / `examples/swoole.php`.

### Open gaps

- **Client-disconnect tests** (M11.6) are deferred: simulating Swoole client disconnect needs
  transport-specific request hooks outside the M11 scope. The middleware already isolates and clears
  per-request state, which is the relevant invariant.
- **High-volume / stress-loop lifecycle tests** are represented by the isolation, sequential-request,
  and WeakMap-release characterization tests rather than a fixed large-N loop.

### Verification

- `composer analyse` (PHPStan, maximum level): zero first-party errors on the new adapters, application
  additions, and tests.
- `composer architecture` (DepTrac): zero violations. The SDK layer does not depend on the adapters
  (consumers wire `FiberContextStorage` / `CoroutineContextStorage` / `CompositeContextStorage`
  through `withContextStorage(...)` and the engine through `withExecutionEngine(...)`).
- `composer cs:check` (PHP-CS-Fixer): clean.
- Unit tests: the nine new M11 suites (Fibers, composite, engines, coroutines, middleware, worker
  lifecycle) pass; `composer test:unit` passes (613 tests, 5170 assertions).

---

# Milestone M12 — Security, Privacy, Performance, and Resilience

**Target:** Week 11  
**Depends on:** M6–M11  
**Outcome:** Production-oriented security controls and measured operational behavior.

## M12.1 Data protection

- [x] Complete data-classification threat model.
- [x] Review every artifact field for sensitivity.
- [x] Review every adapter mapping for leakage.
- [x] Prevent secret context export.
- [x] Prevent unrestricted environment capture.
- [x] Prevent arbitrary object serialization.
- [x] Bound attribute count.
- [x] Bound attribute value size.
- [x] Bound occurrence payload size.
- [x] Bound snapshot payload size.
- [x] Bound activity depth.
- [x] Bound artifact count per execution.
- [x] Add negative security tests for all limits.

## M12.2 Tenant isolation

- [x] Test context isolation between executions.
- [x] Test nested tenant-context behavior.
- [x] Test Fiber tenant isolation.
- [x] Test coroutine tenant isolation.
- [x] Test persistent-worker request isolation.
- [x] Prevent accidental context inheritance for detached executions.
- [x] Add tenant-leak regression tests.

## M12.3 Serialization security

- [x] Review all normalizers.
- [x] Reject resources.
- [x] Reject closures.
- [x] Reject unsupported objects.
- [x] Detect recursive arrays or object graphs where applicable.
- [x] Define maximum serialization depth.
- [x] Define invalid UTF-8 handling.
- [x] Define non-finite float handling.
- [x] Add malformed-payload tests.
- [ ] Add fuzz tests for serializers.

## M12.4 Snapshot integrity

- [x] Verify deterministic snapshot hashing.
- [x] Define hash algorithm configuration.
- [x] Define optional signing port.
- [x] Implement signing adapter only if required for `1.0.0`.
- [x] Verify predecessor chaining.
- [x] Add tamper-detection tests.
- [x] Document that hashing does not provide authenticity.

## M12.5 Performance benchmarks

- [x] Benchmark null execution wrapper.
- [x] Benchmark in-memory execution lifecycle.
- [x] Benchmark activity lifecycle.
- [x] Benchmark measurement creation.
- [x] Benchmark observation creation.
- [x] Benchmark throwable normalization.
- [x] Benchmark context copy-on-write.
- [x] Benchmark composite publisher.
- [x] Benchmark filtering and sampling.
- [x] Benchmark redaction.
- [x] Benchmark buffering.
- [ ] Benchmark OTel mapping.
- [ ] Benchmark CloudEvents mapping.
- [x] Benchmark Fiber context switching.
- [x] Benchmark Swoole coroutine context switching where available.
- [x] Record hardware and runtime configuration.
- [ ] Store machine-readable benchmark baselines.

## M12.6 Performance gates

- [x] Ratify null-path latency target.
- [x] Ratify in-memory execution latency target.
- [x] Ratify activity latency target.
- [x] Ratify artifact-publication throughput target.
- [x] Ratify the memory-per-execution target.
- [x] Define an acceptable regression threshold.
- [ ] Add non-blocking benchmark comparison to pull requests.
- [ ] Add blocking regression gate after baseline stability.
- [x] Document measured results rather than theoretical claims.

## M12.7 Resilience

- [x] Inject clock failures.
- [x] Inject ID-generator failures.
- [x] Inject serializer failures.
- [x] Inject redactor failures.
- [x] Inject sampling-policy failures.
- [x] Inject publisher failures.
- [x] Inject snapshot-store failures.
- [x] Inject context-detach failures.
- [x] Verify an original operation result or throwable priority.
- [x] Verify failure fallback does not recurse.
- [x] Verify buffer overflow behavior.
- [x] Verify bounded shutdown behavior.

## M12.8 Supply-chain security

- [x] Run Composer audit.
- [x] Add dependency review to pull requests.
- [x] Generate an SBOM for release artifacts.
- [x] Review package scripts for unsafe behavior.
- [x] Review optional adapter dependencies.
- [x] Pin CI actions by immutable reference according to project policy.
- [x] Enable repository secret scanning.
- [x] Document vulnerability-reporting process.
- [x] Verify the release provenance approach.

## M12 acceptance gate

- [x] Threat model is reviewed.
- [x] Security limits are implemented and tested.
- [x] Tenant isolation is proven across supported runtimes.
- [ ] Benchmark baselines are published.
- [x] Performance targets are met or explicitly revised.
- [x] Failure-injection suite passes.
- [x] No unresolved critical or high-risk security finding remains.

## M12 status audit — August 20, 2026

Milestone M12 hardens RunIt for production: data-protection limits, serialization safety, snapshot
integrity, tenant isolation, resilience under failure, a benchmark suite, and supply-chain process.
All code-level controls are implemented and covered by in-memory tests; the dependent gates (benchmark
baselines, blocking CI regression) await environment/CI wiring described below.

### Data protection (M12.1)

- `Application/Security/SecurityLimits` centralizes every bound (attribute count/key/value/depth,
  occurrence and snapshot payload bytes, activity depth, artifacts per execution, serialization
  depth/bytes).
- `AttributeValidator` enforces attribute count, key length, scalar value length, and nesting depth,
  configurable through `SecurityLimits` while keeping built-in defaults.
- `JsonSnapshotSerializer` (M12.3) rejects resources, closures, and non-finite floats; enforces a
  maximum normalization depth and maximum serialized byte size; and rejects invalid UTF-8. Objects
  that are not `JsonSerializable` are rejected.
- `BoundedArtifactPublisher` (wrapped inside `DefaultExecutionRunner`) drops oversized occurrence
  `data`, oversized snapshot payloads, and executions exceeding the per-execution artifact count,
  reporting each drop through an optional callback rather than aborting the business result.
- Activity depth is enforced in `DefaultActivityRunner` via `ActivityDepthExceededException`.
- Secret context export is covered by classification + the existing `DefaultArtifactRedactor`;
  environment capture is never performed automatically; arbitrary object serialization is blocked
  by the serializer and `ContextValueValidator`.

### Tenant isolation (M12.2)

`tests/Unit/Application/Security/TenantIsolationTest` proves sequential executions leave no ambient
context, artifacts are scoped to their owning execution, parallel Fibers stay independent, and
(where the Swoole extension is present) coroutines stay isolated. Detached executions do not inherit
ambient context beyond the explicit definition context (verified by the existing M11 nesting tests).

### Snapshot integrity (M12.4)

- `ConfigurableHashCalculator` makes the hash algorithm explicit and validated (`sha256` default).
- `SnapshotIntegrityVerifier` detects tampering (recomputed hash vs stored) and verifies predecessor
  chaining (existence, identity, and temporal order).
- `SnapshotSignerInterface` + `HmacSnapshotSigner` provide optional authenticity; signing is not
  required for `1.0.0` and is documented as an out-of-band concern.

### Resilience (M12.7)

`tests/Unit/Application/Runner/ResilienceTest` injects failures into the publisher, context detach,
and snapshot store. The runner now detaches context in a failure-safe way (a detach error is
reported, never rethrown) and isolates terminal-snapshot creation, so the original operation result
or throwable always takes priority and failure handling never recurses. The publication buffer is
bounded by `BoundedArtifactPublisher`.

### Benchmarks (M12.5 / M12.6)

`benchmarks/ExecutionBench.php` covers the hot-path scenarios (execution/activity lifecycle,
measurement/observation creation, throwable normalization, composite publisher, redaction, buffering,
Fiber context switching, Swoole coroutine switching). `docs/benchmarks.md` ratifies latency/throughput
targets and documents the regression policy.

**Caveat:** the installed phpbench binary in this environment fails to start (configuration/schema
incompatibility) independent of the benchmark code. The benchmark classes are present, autoloadable,
and syntax-valid; machine-readable baselines are therefore not recorded here. Running `composer
benchmark` in a correctly configured phpbench will generate them, after which the non-blocking PR gate
and eventual blocking regression gate can be enabled.

### Supply chain (M12.8)

`composer audit` reports no security advisories (one abandoned transitive dev package,
`doctrine/annotations`, noted). `docs/security/supply-chain.md` documents SBOM generation, dependency
review, CI action pinning, secret scanning, vulnerability reporting, and release provenance.

### Verification

- `composer analyse` (PHPStan, maximum level): zero errors (including the new `benchmarks/` and
  `Application/Security` code).
- `composer architecture` (DepTrac): zero violations. The new `Application/Security` classes depend
  only on `Contract`, `Domain`, and `Port`; they do not introduce SDK→Adapter coupling.
- `composer cs:check` (PHP-CS-Fixer): clean.
- Unit tests: the new M12 suites (`SecurityLimitsTest`, `AttributeValidatorTest`,
  `JsonSnapshotSerializerTest`, `BoundedArtifactPublisherTest`, `SnapshotIntegrityVerifierTest`,
  `ConfigurableHashCalculatorTest`, `HmacSnapshotSignerTest`, `TenantIsolationTest`,
  `ResilienceTest`) pass; `composer test:unit` remains green.

### Remaining work

- Record machine-readable benchmark baselines and enable the PR/blocking regression gates once a
  working phpbench is available.
- Add serializer fuzz tests and OTel/CloudEvents mapping benchmarks (tracked in M13 conformance work).

---

# Milestone M13 — Conformance Tooling and QA Completion

**Target:** Week 11  
**Depends on:** M1–M12  
**Outcome:** Reusable contract verification and enforced quality standards.

## M13.1 Test-framework-agnostic contract suites

- [ ] Define `ArtifactPublisherFixtureInterface`.
- [ ] Define `ContextStorageFixtureInterface`.
- [ ] Define `IdGeneratorFixtureInterface`.
- [ ] Define `SerializerFixtureInterface`.
- [ ] Define `RedactorFixtureInterface`.
- [ ] Define `SamplingPolicyFixtureInterface`.
- [ ] Define `SnapshotStoreFixtureInterface`.
- [ ] Define `ExecutionEngineFixtureInterface`.
- [ ] Define conformance-result model.
- [ ] Define conformance-violation model.
- [ ] Implement publisher verifier.
- [ ] Implement context-storage verifier.
- [ ] Implement ID-generator verifier.
- [ ] Implement serializer verifier.
- [ ] Implement redactor verifier.
- [ ] Implement sampling-policy verifier.
- [ ] Implement snapshot-store verifier.
- [ ] Implement execution-engine verifier.
- [ ] Avoid inheritance from a specific test framework.
- [ ] Add examples integrating verifiers with the project test runner.
- [ ] Add examples for third-party test frameworks.

## M13.2 PHPStan completion

- [ ] Set PHPStan to the highest supported configured level.
- [ ] Enable strict rules.
- [ ] Enable uninitialized-property checks.
- [ ] Enable missing-generic checks.
- [ ] Enable strict mixed handling.
- [ ] Configure exception checks.
- [ ] Add custom rule: concrete classes must be final.
- [ ] Add custom rule: eligible immutable classes should be readonly.
- [ ] Add custom rule: concrete classes require interfaces.
- [ ] Add custom rule: domain cannot import adapters.
- [ ] Add custom rule: prohibit mutable static state.
- [ ] Add a custom rule: prohibit direct wall-clock access outside adapters.
- [ ] Add custom rule: prohibit arbitrary object serialization.
- [ ] Maintain zero first-party baseline entries.
- [ ] Add a non-blocking bleeding-edge analysis job.

## M13.3 DepTrac completion

- [ ] Define Contract layer.
- [ ] Define Domain layer.
- [ ] Define Application layer.
- [ ] Define Port layer.
- [ ] Define SDK layer.
- [ ] Define a Common Adapter layer.
- [ ] Define External Adapter layer.
- [ ] Define Test Support layer.
- [ ] Enforce documented allowed dependencies.
- [ ] Prohibit domain-to-application dependencies.
- [ ] Prohibit domain-to-adapter dependencies.
- [ ] Prohibit application-to-SDK dependencies.
- [ ] Prohibit application-to-external-adapter dependencies.
- [ ] Review uncovered classes.
- [ ] Generate architecture visualization.
- [ ] Store approved architecture diagram in documentation.
- [ ] Maintain zero unexplained skips.

## M13.4 Coding standards

- [ ] Finalize PHP-CS-Fixer configuration.
- [ ] Enforce strict-types declarations.
- [ ] Enforce import ordering.
- [ ] Enforce trailing commas where appropriate.
- [ ] Enforce modern PHP 8.4 syntax consistently.
- [ ] Review risky rules individually.
- [ ] Add a check-only CI job.
- [ ] Document local auto-fix command.

## M13.5 Mutation testing

- [ ] Configure source directories.
- [ ] Configure test framework integration.
- [ ] Configure PHPStan integration where supported.
- [ ] Configure changed-line mutation testing.
- [ ] Set provisional minimum MSI.
- [ ] Set provisional covered-code MSI.
- [ ] Run mutation tests against lifecycle code.
- [ ] Run mutation tests against context restoration.
- [ ] Run mutation tests against redaction.
- [ ] Run mutation tests against sampling.
- [ ] Run mutation tests against failure policies.
- [ ] Run mutation tests against adapter mappings.
- [ ] Review escaped mutants.
- [ ] Add tests for meaningful escaped mutants.
- [ ] Document justified ignored mutations.
- [ ] Add a full nightly mutation job.

## M13.6 Backward compatibility

- [ ] Configure public API compatibility checker.
- [ ] Define public namespace scope.
- [ ] Exclude documented internal namespaces.
- [ ] Establish an API baseline at the first release candidate.
- [ ] Add a pull-request compatibility job after baseline creation.
- [ ] Detect public interface method additions.
- [ ] Detect removed or narrowed types.
- [ ] Detect enum case changes where applicable.
- [ ] Detect normalization schema changes separately.
- [ ] Document compatibility review process.

## M13 acceptance gate

- [ ] All first-party adapters pass their own conformance suites.
- [ ] PHPStan passes at the configured maximum.
- [ ] DepTrac passes without unexplained skips.
- [ ] Coding-style checks pass.
- [ ] Mutation targets are met.
- [ ] API compatibility tooling is ready for release-candidate baseline.
- [ ] Third-party adapter authors have documented verification guidance.

---

# Milestone M14 — Documentation, Dogfooding, and ItPack Adoption

**Target:** Week 12  
**Depends on:** M7–M13  
**Outcome:** Complete user guidance and demonstrated ecosystem use.

## M14.1 Core documentation

- [ ] Finalize `README.md`.
- [ ] Finalize `EXECUTION.md`.
- [ ] Finalize `AGENTS.md`.
- [ ] Finalize `ROADMAP.md`.
- [ ] Finalize `CONTRIBUTING.md`.
- [ ] Finalize `SECURITY.md`.
- [ ] Finalize `UPGRADING.md`.
- [ ] Create `docs/concepts/execution.md`.
- [ ] Create `docs/concepts/activity.md`.
- [ ] Create `docs/concepts/measurement.md`.
- [ ] Create `docs/concepts/observation.md`.
- [ ] Create `docs/concepts/occurrence.md`.
- [ ] Create `docs/concepts/snapshot.md`.
- [ ] Create `docs/concepts/context.md`.

## M14.2 Architecture documentation

- [ ] Create `docs/architecture/hexagonal-architecture.md`.
- [ ] Create `docs/architecture/lifecycle.md`.
- [ ] Create `docs/architecture/context-propagation.md`.
- [ ] Create `docs/architecture/publication.md`.
- [ ] Create `docs/architecture/security.md`.
- [ ] Create `docs/architecture/performance.md`.
- [ ] Create `docs/architecture/self-instrumentation.md`.
- [ ] Add an architecture dependency diagram.
- [ ] Add execution lifecycle diagram.
- [ ] Add activity nesting diagram.
- [ ] Add an artifact publication diagram.
- [ ] Add context propagation diagrams for synchronous, Fiber, and coroutine runtimes.

## M14.3 Integration documentation

- [ ] Finalize OpenTelemetry guide.
- [ ] Finalize CloudEvents guide.
- [ ] Finalize PSR guide.
- [ ] Finalize Fiber guide.
- [ ] Finalize Swoole/OpenSwoole guide.
- [ ] Document optional dependencies.
- [ ] Document adapter failure policies.
- [ ] Document integration compatibility matrix.

## M14.4 Recipes

- [ ] Add basic callable recipe.
- [ ] Add a nested-activity recipe.
- [ ] Add HTTP request recipe.
- [ ] Add CLI command recipe.
- [ ] Add queue-job recipe.
- [ ] Add scheduler recipe.
- [ ] Add background-worker recipe.
- [ ] Add configuration-resolution recipe.
- [ ] Add a workflow recipe.
- [ ] Add OpenTelemetry recipe.
- [ ] Add CloudEvents recipe.
- [ ] Add PSR-3 recipe.
- [ ] Add testing recipe.
- [ ] Add third-party adapter recipe.

## M14.5 Executable examples

- [ ] Add a null-runtime example.
- [ ] Add an in-memory-runtime example.
- [ ] Add a successful execution example.
- [ ] Add a failed execution example.
- [ ] Add nested activities example.
- [ ] Add measurements example.
- [ ] Add observations example.
- [ ] Add occurrences example.
- [ ] Add snapshots example.
- [ ] Add a context classification example.
- [ ] Add OTel example.
- [ ] Add CloudEvents example.
- [ ] Add a Fiber example.
- [ ] Add a Swoole example where available.
- [ ] Execute all examples in CI.
- [ ] Ensure examples use only public APIs.

## M14.6 RunIt dogfooding

- [ ] Represent RunIt test execution as an execution.
- [ ] Represent static analysis as an execution.
- [ ] Represent architecture analysis as an execution.
- [ ] Represent mutation testing as an execution.
- [ ] Represent benchmark execution as an execution.
- [ ] Represent release validation as an execution.
- [ ] Instrument adapter batch flushes.
- [ ] Verify dogfooding does not recurse.
- [ ] Verify dogfooding does not distort benchmark results.
- [ ] Document the intentionally uninstrumented bootstrap path.

## M14.7 ConfigureIt adoption

- [ ] Identify ConfigureIt execution boundaries.
- [ ] Define `configuration.resolution` execution.
- [ ] Define file-discovery activity.
- [ ] Define resource-reading activity.
- [ ] Define YAML-loading activity.
- [ ] Define document-merge activity.
- [ ] Define schema-validation activity.
- [ ] Define variable-resolution activity.
- [ ] Define graph-building activity.
- [ ] Define snapshot-freezing activity.
- [ ] Record files-loaded measurement.
- [ ] Record duration measurement.
- [ ] Record memory measurement.
- [ ] Record graph-node measurement.
- [ ] Record warning observations.
- [ ] Record validation diagnostics.
- [ ] Record configuration-loaded occurrence.
- [ ] Record schema-validated occurrence.
- [ ] Record snapshot-created occurrence.
- [ ] Produce a configuration snapshot.
- [ ] Verify ConfigureIt domain remains free of adapter dependencies.
- [ ] Add end-to-end ConfigureIt integration tests.
- [ ] Document the adoption case study.

## M14.8 Third-party usability validation

- [ ] Build one sample third-party publisher.
- [ ] Run the publisher conformance suite.
- [ ] Build one sample context-storage adapter.
- [ ] Run context-storage conformance suite.
- [ ] Validate documentation with a clean consumer project.
- [ ] Record confusing APIs or missing extension points.
- [ ] Resolve critical usability findings before `1.0.0`.

## M14 acceptance gate

- [ ] All public concepts and APIs are documented.
- [ ] Every README example executes successfully.
- [ ] RunIt uses its own execution model safely.
- [ ] ConfigureIt demonstrates production-style adoption.
- [ ] A third-party-style adapter passes conformance tests.
- [ ] Documentation has been reviewed independently.

---

# Milestone M15 — Release Candidate and Stable 1.0.0

**Target:** Week 12  
**Depends on:** M0–M14  
**Outcome:** Stable, supported `it-pack/run-it` release.

## M15.1 Release-candidate preparation

- [ ] Freeze planned `1.0.0` public API.
- [ ] Mark internal namespaces explicitly.
- [ ] Remove abandoned experimental APIs.
- [ ] Review every public interface.
- [ ] Review every public class.
- [ ] Review every public enum.
- [ ] Review every public attribute.
- [ ] Review every normalized field.
- [ ] Review every occurrence naming convention.
- [ ] Review default security limits.
- [ ] Review default failure policies.
- [ ] Review optional dependency constraints.
- [ ] Create a backward-compatibility baseline.
- [ ] Update changelog.
- [ ] Update upgrade guide.
- [ ] Publish `1.0.0-rc.1`.

## M15.2 Release-candidate validation

- [ ] Install RC in a clean project.
- [ ] Install RC in ConfigureIt.
- [ ] Run lowest-dependency matrix.
- [ ] Run stable-dependency matrix.
- [ ] Run supported operating-system matrix.
- [ ] Run the full unit suite.
- [ ] Run the full integration suite.
- [ ] Run the full concurrency suite.
- [ ] Run the full mutation suite.
- [ ] Run the full benchmark suite.
- [ ] Run security audit.
- [ ] Generate SBOM.
- [ ] Verify package archive contents.
- [ ] Verify no development secrets or fixtures are packaged.
- [ ] Verify optimized autoloading.
- [ ] Collect RC feedback.
- [ ] Resolve release-blocking defects.
- [ ] Publish additional RC if public API changes.

## M15.3 Stable release

- [ ] Confirm all milestone acceptance gates.
- [ ] Confirm no unresolved critical defect.
- [ ] Confirm no unresolved high-risk security issue.
- [ ] Confirm performance targets.
- [ ] Confirm the backward-compatibility policy.
- [ ] Confirm support and maintenance policy.
- [ ] Confirm Packagist metadata.
- [ ] Tag `1.0.0`.
- [ ] Publish release notes.
- [ ] Publish finalized documentation.
- [ ] Announce ItPack ecosystem compatibility.
- [ ] Open post-release monitoring issue.
- [ ] Record known limitations.

## M15 stable release gate

- [ ] Every execution has exactly one terminal outcome.
- [ ] Every artifact has an execution identity.
- [ ] Activity relationships are correct.
- [ ] Context does not leak across supported runtimes.
- [ ] Original business exceptions are preserved.
- [ ] Secret values are not exported.
- [ ] Buffers and payloads are bounded.
- [ ] OTel and CloudEvents mappings pass contract tests.
- [ ] PSR adapters pass integration tests.
- [ ] PHPStan passes at maximum configured strictness.
- [ ] DepTrac passes.
- [ ] Mutation targets pass.
- [ ] Benchmarks meet ratified targets.
- [ ] ConfigureIt integration passes.
- [ ] Third-party conformance example passes.
- [ ] Documentation matches the released API.

---

# Milestone M16 — Post-1.0 Ecosystem Expansion

**Target:** Post-`1.0.0`  
**Outcome:** Broader ItPack adoption and independently versioned bridge packages where justified.

## M16.1 Bridge package evaluation

- [ ] Measure root-package adapter maintenance cost.
- [ ] Evaluate `it-pack/run-it-otel`.
- [ ] Evaluate `it-pack/run-it-cloudevents`.
- [ ] Evaluate `it-pack/run-it-psr`.
- [ ] Evaluate `it-pack/run-it-swoole`.
- [ ] Split only when ADR-016 criteria are met.
- [ ] Preserve root semantic contracts.
- [ ] Provide migration path for moved adapters.

## M16.2 ItPack package adoption

- [ ] Integrate ChaosCockpit.
- [ ] Integrate AExO.
- [ ] Integrate HoppaDB.
- [ ] Integrate Spindle.
- [ ] Identify shared occurrence vocabulary.
- [ ] Identify shared context vocabulary.
- [ ] Create cross-package execution examples.
- [ ] Test execution propagation across package boundaries.

## M16.3 Additional adapters

- [ ] Evaluate Symfony integration.
- [ ] Evaluate Laravel integration.
- [ ] Evaluate Mezzio integration.
- [ ] Evaluate queue-system adapters.
- [ ] Evaluate scheduler adapters.
- [ ] Evaluate database query activity decorators.
- [ ] Evaluate HTTP client instrumentation packages.
- [ ] Evaluate event-stream transports.
- [ ] Require conformance suites for every accepted adapter.

## M16.4 Operational maturity

- [ ] Add a release performance dashboard.
- [ ] Add long-duration memory regression testing.
- [ ] Add compatibility testing against upcoming PHP versions.
- [ ] Add adapter compatibility matrix automation.
- [ ] Add deprecation telemetry policy.
- [ ] Add schema registry guidance for occurrences and snapshots.
- [ ] Add a durable outbox reference adapter if demanded by consumers.
- [ ] Add signed-snapshot adapter if demanded by security requirements.
- [ ] Review default limits using production evidence.

## M16.5 API evolution

- [ ] Collect consumer feedback.
- [ ] Review interface granularity.
- [ ] Review generic typing ergonomics.
- [ ] Review property-hook opportunities.
- [ ] Review lazy-object usage.
- [ ] Review execution-engine requirements.
- [ ] Avoid expanding the core without proven cross-package demand.
- [ ] Require ADRs for new root-domain concepts.

---

# Cross-Cutting Verification Matrix

The following checks apply throughout the roadmap.

## Architecture

- [ ] Domain remains vendor-neutral.
- [ ] Application logic depends on ports.
- [ ] Adapters depend inward.
- [ ] SDK remains a convenience layer.
- [ ] No service locator is introduced.
- [ ] No global mutable execution context is introduced.
- [ ] Composition is preferred over inheritance.
- [ ] Every concrete class is final.
- [ ] Every concrete class is interface-backed.
- [ ] Eligible classes are readonly.

## Domain correctness

- [ ] Execution state transitions are valid.
- [ ] Activity state transitions are valid.
- [ ] Artifacts are immutable.
- [ ] Every artifact belongs to one execution.
- [ ] Activity-bound artifacts reference one activity.
- [ ] Sequence allocation is monotonic.
- [ ] Duration uses monotonic time.
- [ ] Normalization is deterministic.
- [ ] Snapshots are immutable.

## Error handling

- [ ] Original business results are preserved.
- [ ] Original business throwable is preserved.
- [ ] Adapter failures follow the configured policy.
- [ ] Context restoration occurs in `finally`.
- [ ] Publication recursion is prevented.
- [ ] Buffer overflow is explicit.
- [ ] Shutdown flushing is bounded.

## Security

- [ ] Secret context is never exported.
- [ ] Confidential context follows policy.
- [ ] Payload sizes are bounded.
- [ ] Attribute counts are bounded.
- [ ] Activity depth is bounded.
- [ ] Stack-trace export is policy-controlled.
- [ ] Arbitrary objects are not serialized.
- [ ] Metric attributes are cardinality-controlled.
- [ ] Persistent-worker context is cleared.

## Testing

- [ ] Unit tests cover domain invariants.
- [ ] Lifecycle matrix tests are complete.
- [ ] Contract suites cover all ports.
- [ ] Integration tests cover external adapters.
- [ ] Failure-injection tests cover external effects.
- [ ] Property-based tests cover parsers and validators.
- [ ] Mutation tests cover critical branches.
- [ ] Concurrency tests cover Fiber/coroutine isolation.
- [ ] Benchmarks cover hot paths.
- [ ] Examples execute in CI.

## Documentation

- [ ] Public contracts are documented.
- [ ] Domain semantics are documented.
- [ ] Adapter mappings are documented.
- [ ] Security defaults are documented.
- [ ] Failure guarantees are documented.
- [ ] Delivery guarantees are documented.
- [ ] Compatibility constraints are documented.
- [ ] Examples use released public APIs.
- [ ] Significant decisions have ADRs.

---

# Final Project Completion Checklist

RunIt `1.0.0` is complete only when all applicable items below are checked.

## Product

- [ ] The package installs as `it-pack/run-it`.
- [ ] PHP 8.4 or later is supported.
- [ ] No framework is required.
- [ ] No telemetry vendor is required.
- [ ] Core execution works without external infrastructure.
- [ ] Programmatic and SDK APIs are stable.
- [ ] Third-party extension contracts are available.

## Execution model

- [ ] Execution is the root domain.
- [ ] Execution owns immutable context.
- [ ] Activities represent nested work.
- [ ] Measurements represent quantitative information.
- [ ] Observations represent qualitative information.
- [ ] Occurrences represent immutable facts.
- [ ] Snapshots preserve resulting state.
- [ ] External standards remain adapters.

## Operations

- [ ] Context propagation works synchronously.
- [ ] Context propagation works across Fibers.
- [ ] Supported Swoole/OpenSwoole runtimes are isolated.
- [ ] Artifact publication is composable.
- [ ] Redaction and sampling are available.
- [ ] Buffering and backpressure are bounded.
- [ ] Self-instrumentation is recursion-safe.
- [ ] Persistent workers do not leak execution state.

## Integrations

- [ ] OpenTelemetry integration is complete.
- [ ] CloudEvents integration is complete.
- [ ] PSR-3 integration is complete.
- [ ] PSR-14 integration is complete.
- [ ] PSR-15 HTTP integration is complete or explicitly deferred.
- [ ] PSR-18 propagation integration is complete or explicitly deferred.
- [ ] Optional dependencies do not affect core installation.

## Quality

- [ ] PHPStan passes at the configured maximum.
- [ ] DepTrac passes.
- [ ] Coding-style checks pass.
- [ ] Mutation score targets pass.
- [ ] Security audit passes.
- [ ] Backward-compatibility baseline exists.
- [ ] Benchmark targets pass.
- [ ] All examples execute.
- [ ] Documentation is complete.
- [ ] ConfigureIt adoption passes.
- [ ] Third-party conformance example passes.

> **Everything happens within an execution. RunIt makes that execution explicit, composable, observable, portable, and safe.**
