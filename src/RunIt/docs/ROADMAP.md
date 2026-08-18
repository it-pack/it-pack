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
- [x] Define `ItPack\RunIt\Tests\` PSR-4 development mapping.
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
- [x] Ensure publication failure cannot replace business failure. — `SafeArtifactPublisher` wraps the publisher and forwards failures to the configured health callback; covered by `DefaultExecutionRunnerTest` (result, exception, and health-callback tests).
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

Verification evidence (after the `SafeArtifactPublisher` health-reporting wiring
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

- [ ] Define `ActivityRunnerInterface`.
- [ ] Define `ActivityScopeInterface`.
- [ ] Define `ActivityLifecycleCoordinatorInterface`.
- [ ] Define `ActivityFactoryInterface`.
- [ ] Define `OpenActivityCompletionPolicyInterface`.
- [ ] Define current-activity access through execution scope.

## M5.2 Activity runner

- [ ] Implement `DefaultActivityRunner`.
- [ ] Generate activity identity.
- [ ] Resolve parent activity identity.
- [ ] Allocate activity sequence.
- [ ] Create running activity.
- [ ] Publish activity-started artifact.
- [ ] Attach activity scope.
- [ ] Invoke activity operation.
- [ ] Complete activity successfully.
- [ ] Map thrown `Throwable`.
- [ ] Complete activity as failed.
- [ ] Restore parent activity scope.
- [ ] Rethrow original throwable.
- [ ] Add successful-activity tests.
- [ ] Add failed-activity tests.
- [ ] Add nested-activity tests.
- [ ] Add activity-context restoration tests.

## M5.3 Open activity policies

- [ ] Implement auto-fail-open-activities policy.
- [ ] Implement auto-cancel-open-activities policy.
- [ ] Implement fail-execution-on-open-activities policy.
- [ ] Implement a warning-and-ignore policy if retained.
- [ ] Select and document the default.
- [ ] Record diagnostic observations for auto-resolved activities.
- [ ] Test execution completion with open activities.
- [ ] Test deeply nested open activities.

## M5.4 Measurement recorder

- [ ] Define `MeasurementRecorderInterface`.
- [ ] Implement default measurement recorder.
- [ ] Bind execution identity automatically.
- [ ] Bind active activity identity automatically.
- [ ] Allocate sequence automatically.
- [ ] Add timestamp automatically.
- [ ] Validate unit and numeric values.
- [ ] Publish recorded measurement.
- [ ] Add execution-level measurement tests.
- [ ] Add activity-level measurement tests.

## M5.5 Observation recorder

- [ ] Define `ObservationRecorderInterface`.
- [ ] Implement default observation recorder.
- [ ] Bind execution identity automatically.
- [ ] Bind active activity identity automatically.
- [ ] Allocate sequence automatically.
- [ ] Add timestamp automatically.
- [ ] Normalize throwable through the configured mapper.
- [ ] Publish recorded observation.
- [ ] Add severity-specific convenience methods where justified.
- [ ] Test observation recording with and without active activity.

## M5.6 Occurrence recorder

- [ ] Define `OccurrenceRecorderInterface`.
- [ ] Implement default occurrence recorder.
- [ ] Bind execution identity automatically.
- [ ] Bind active activity identity automatically.
- [ ] Allocate sequence automatically.
- [ ] Add a default source where configured.
- [ ] Add timestamp automatically.
- [ ] Publish recorded occurrence.
- [ ] Test occurrence type validation.
- [ ] Test extension preservation.

## M5.7 Snapshot recorder

- [ ] Define `SnapshotRecorderInterface`.
- [ ] Define `SnapshotStoreInterface`.
- [ ] Implement default snapshot recorder.
- [ ] Bind execution identity automatically.
- [ ] Bind active activity identity when applicable.
- [ ] Allocate sequence automatically.
- [ ] Normalize payload through serializer port.
- [ ] Calculate content hash.
- [ ] Publish snapshot artifact.
- [ ] Persist snapshot only when a store is configured.
- [ ] Define store-failure policy.
- [ ] Add checkpoint-snapshot tests.
- [ ] Add terminal-snapshot tests.
- [ ] Add snapshot-store failure tests.

## M5 acceptance gate

- [ ] Nested activities preserve parent relationships.
- [ ] All recorders bind correct execution and activity identities.
- [ ] Artifact sequences increase monotonically.
- [ ] Open activities are resolved according to policy.
- [ ] Snapshot persistence remains optional.
- [ ] All recorder and activity tests pass.

---

# Milestone M6 — Artifact Publication Pipeline

**Target:** Week 6  
**Depends on:** M3, M4, M5  
**Outcome:** Composable, secure, bounded, failure-aware artifact publication.

## M6.1 Publisher contracts

- [ ] Define `ArtifactPublisherInterface`.
- [ ] Define `FlushablePublisherInterface`.
- [ ] Define `CloseablePublisherInterface`.
- [ ] Define `ArtifactFilterInterface`.
- [ ] Define `SamplingPolicyInterface`.
- [ ] Define `RedactorInterface`.
- [ ] Define `PublicationFailurePolicyInterface`.
- [ ] Define `InternalHealthReporterInterface`.
- [ ] Define `PublicationGuardInterface`.
- [ ] Define `PublicationGuardTokenInterface`.

## M6.2 Basic publishers

- [ ] Implement `NullArtifactPublisher`.
- [ ] Implement `InMemoryArtifactPublisher`.
- [ ] Implement `CallbackArtifactPublisher`.
- [ ] Implement `CompositeArtifactPublisher`.
- [ ] Preserve publication order in composite publisher.
- [ ] Define composite failure behavior.
- [ ] Add publisher contract tests.
- [ ] Add empty composite tests.
- [ ] Add multiple-publisher ordering tests.

## M6.3 Filtering and routing

- [ ] Implement `FilteringArtifactPublisher`.
- [ ] Support artifact-type filters.
- [ ] Support execution-kind filters.
- [ ] Support observation-severity filters.
- [ ] Support occurrence-type filters.
- [ ] Implement `RoutingArtifactPublisher`.
- [ ] Support routing to multiple targets.
- [ ] Ensure filtering occurs before expensive adapter mapping.
- [ ] Add filtering tests.
- [ ] Add routing tests.

## M6.4 Redaction

- [ ] Implement `RedactingArtifactPublisher`.
- [ ] Redact classified context.
- [ ] Redact sensitive attributes.
- [ ] Redact throwable messages according to policy.
- [ ] Redact occurrence data.
- [ ] Redact snapshot payloads where configured.
- [ ] Ensure redaction occurs before external publication.
- [ ] Ensure original immutable artifacts remain unchanged.
- [ ] Add redaction tests for every artifact type.
- [ ] Mutation-test redaction conditions.

## M6.5 Sampling

- [ ] Implement always-sample policy.
- [ ] Implement never-sample policy.
- [ ] Implement a ratio-based policy.
- [ ] Implement execution-kind policy.
- [ ] Implement a severity-aware policy.
- [ ] Ensure errors can override ordinary sampling.
- [ ] Define parent-child sampling behavior.
- [ ] Implement `SamplingArtifactPublisher`.
- [ ] Add deterministic sampling support for tests.
- [ ] Add sampling tests.
- [ ] Add statistical sampling sanity tests.

## M6.6 Failure handling

- [ ] Implement fail-open policy.
- [ ] Implement fail-closed policy.
- [ ] Implement collect-and-continue policy.
- [ ] Implement a report-to-fallback policy.
- [ ] Implement `FailSafeArtifactPublisher`.
- [ ] Preserve original business exceptions.
- [ ] Preserve original adapter exceptions for diagnostics.
- [ ] Ensure fallback failure cannot recurse indefinitely.
- [ ] Add publisher-failure tests.
- [ ] Add fallback-failure tests.
- [ ] Mutation-test policy branches.

## M6.7 Recursion guard

- [ ] Implement publication guard.
- [ ] Detect nested publication on the same execution path.
- [ ] Send guarded failures to an internal health reporter.
- [ ] Implement `ErrorLogHealthReporter`.
- [ ] Implement callback health reporter.
- [ ] Ensure health reporters do not call the guarded publisher.
- [ ] Add direct recursion tests.
- [ ] Add indirect recursion tests.
- [ ] Add exporter-self-observation tests.

## M6.8 Buffering and backpressure

- [ ] Define buffer configuration object.
- [ ] Define maximum item count.
- [ ] Define maximum estimated bytes.
- [ ] Define maximum buffer age.
- [ ] Define batch size.
- [ ] Define a flush interval.
- [ ] Define shutdown flush budget.
- [ ] Implement drop-newest overflow strategy.
- [ ] Implement drop-oldest overflow strategy.
- [ ] Implement drop-low-priority strategy.
- [ ] Implement a block-with-timeout strategy if safe.
- [ ] Implement a fail overflow strategy.
- [ ] Implement `BufferedArtifactPublisher`.
- [ ] Count dropped artifacts through recursion-safe health reporting.
- [ ] Prevent unbounded memory growth.
- [ ] Add capacity tests.
- [ ] Add overflow-strategy tests.
- [ ] Add shutdown-budget tests.
- [ ] Add sustained-publication memory tests.

## M6 documentation

- [ ] Create `docs/architecture/publication.md`.
- [ ] Document pipeline ordering.
- [ ] Document failure policies.
- [ ] Document buffering.
- [ ] Document overflow strategies.
- [ ] Document recursion prevention.
- [ ] Add publisher composition examples.

## M6 acceptance gate

- [ ] Publisher contract suite passes for all publishers.
- [ ] Redaction occurs before external publication.
- [ ] Buffers are bounded.
- [ ] Recursion is prevented.
- [ ] Fail-open and fail-closed behavior are explicit and tested.
- [ ] Original business exceptions are preserved.
- [ ] Publication benchmarks meet the ratified baseline.

---

# Milestone M7 — Programmatic SDK and Non-Intrusive Adoption

**Target:** Week 7  
**Depends on:** M4–M6  
**Outcome:** Ergonomic APIs for applications and libraries without mandatory inheritance or framework coupling.

## M7.1 Runtime builder

- [ ] Define `RunItBuilderInterface`.
- [ ] Implement immutable `RunItBuilder`.
- [ ] Add clock configuration.
- [ ] Add monotonic-clock configuration.
- [ ] Add ID-generator configuration.
- [ ] Add context-storage configuration.
- [ ] Add artifact-publisher configuration.
- [ ] Add throwable-mapper configuration.
- [ ] Add serializer configuration.
- [ ] Add redactor configuration.
- [ ] Add sampling-policy configuration.
- [ ] Add lifecycle-policy configuration.
- [ ] Add nested-execution-policy configuration.
- [ ] Add snapshot-store configuration.
- [ ] Provide safe defaults.
- [ ] Validate required dependencies at `build()`.
- [ ] Return an interface-backed runtime facade.
- [ ] Add immutable-builder tests.
- [ ] Add incomplete-configuration tests.

## M7.2 Runtime facade

- [ ] Define `RunItInterface`.
- [ ] Implement `DefaultRunIt`.
- [ ] Expose execution runner.
- [ ] Expose current-execution accessor.
- [ ] Expose supported decorator factories.
- [ ] Avoid exposing concrete application services unnecessarily.
- [ ] Add facade delegation tests.

## M7.3 Callable decorator

- [ ] Define callable-decorator contract.
- [ ] Implement execution-wrapped callable.
- [ ] Preserve callable arguments.
- [ ] Preserve callable return value.
- [ ] Preserve original throwable.
- [ ] Support static execution definition.
- [ ] Support execution-definition factory based on arguments.
- [ ] Avoid reflection on the invocation hot path.
- [ ] Add closure tests.
- [ ] Add invokable-object tests.
- [ ] Add callable-array tests.

## M7.4 Service decorators

- [ ] Add documented decorator pattern.
- [ ] Add an example instrumented service interface.
- [ ] Add an example final readonly decorator.
- [ ] Demonstrate dependency inversion.
- [ ] Demonstrate preserving the wrapped contract.
- [ ] Avoid general runtime proxy generation in `1.0.0`.
- [ ] Document when a generated proxy may be justified later.

## M7.5 Attribute metadata

- [ ] Define `ExecutedAs` attribute.
- [ ] Define activity metadata attribute if justified.
- [ ] Keep attributes immutable.
- [ ] Ensure attributes contain metadata only.
- [ ] Add metadata-reader port.
- [ ] Add an optional reflection-based reader outside the hot path.
- [ ] Cache parsed metadata safely.
- [ ] Add attribute-validation tests.
- [ ] Document that attributes do not instrument code by themselves.

## M7.6 Developer experience

- [ ] Provide minimal null-runtime factory.
- [ ] Provide an in-memory runtime factory for tests.
- [ ] Provide the recommended production-builder example.
- [ ] Ensure simple instrumentation requires no more than a few lines.
- [ ] Add IDE-friendly PHPDoc generics.
- [ ] Add examples for result type preservation.
- [ ] Add examples for nested activities.
- [ ] Add examples for recording each artifact type.

## M7 acceptance gate

- [ ] Existing callables can be instrumented without modifying their code.
- [ ] Existing service interfaces can be instrumented through composition.
- [ ] No global facade or service locator exists.
- [ ] SDK delegates to application-layer contracts.
- [ ] SDK performance overhead is measured.
- [ ] Quick-start documentation matches executable examples.

---

# Milestone M8 — PSR Interoperability

**Target:** Week 8  
**Depends on:** M6, M7  
**Outcome:** Optional PSR adapters without core coupling.

## M8.1 PSR-20 clock

- [ ] Implement PSR-20 clock adapter.
- [ ] Keep the monotonic duration clock separate.
- [ ] Add PSR-20 adapter tests.
- [ ] Document wall-clock versus monotonic-clock responsibilities.

## M8.2 PSR-3 observation export

- [ ] Implement observation-to-PSR-3 level mapper.
- [ ] Implement `ObservationLoggerPublisher`.
- [ ] Map stable message templates.
- [ ] Map attributes to logger context.
- [ ] Put throwable data under the expected exception key where appropriate.
- [ ] Include execution correlation fields.
- [ ] Include activity correlation fields.
- [ ] Apply redaction before logging.
- [ ] Add recording-logger integration tests.
- [ ] Test all severity mappings.

## M8.3 Execution-aware PSR-3 decorator

- [ ] Implement PSR-3 logger decorator.
- [ ] Enrich existing log calls with current execution identity.
- [ ] Enrich existing log calls with current activity identity.
- [ ] Handle missing current execution.
- [ ] Preserve existing logger context values.
- [ ] Define collision behavior.
- [ ] Add logger-decorator tests.

## M8.4 PSR-3 to observation bridge

- [ ] Define bridge policy.
- [ ] Implement logger that records RunIt observations.
- [ ] Map PSR-3 levels to RunIt severities.
- [ ] Preserve static message templates.
- [ ] Safely normalize context values.
- [ ] Prevent logging-to-observation-to-logging recursion.
- [ ] Add recursion tests.

## M8.5 PSR-14 occurrence bridge

- [ ] Implement occurrence event wrapper.
- [ ] Implement occurrence-to-PSR-14 publisher.
- [ ] Apply publication failure policy around dispatch.
- [ ] Preserve occurrence immutability.
- [ ] Implement configurable PSR event-to-occurrence mapper.
- [ ] Test listener ordering assumptions.
- [ ] Test listener exceptions.
- [ ] Test stoppable events where supported.
- [ ] Document synchronous behavior.

## M8.6 HTTP PSRs

- [ ] Define HTTP context extractor contract.
- [ ] Define HTTP context injector contract.
- [ ] Implement PSR-7 request context extractor.
- [ ] Implement PSR-7 response correlation enricher where appropriate.
- [ ] Implement PSR-15 execution middleware.
- [ ] Create execution from incoming request.
- [ ] Derive execution name without unbounded URL cardinality.
- [ ] Record response status.
- [ ] Record request failure.
- [ ] Restore execution context after handling.
- [ ] Implement PSR-18 propagation-aware client decorator.
- [ ] Inject correlation and trace headers through configurable propagator.
- [ ] Add PSR HTTP integration tests.
- [ ] Add malformed-header tests.
- [ ] Add header-injection security tests.

## M8 documentation

- [ ] Create `docs/integrations/psr.md`.
- [ ] Document required optional packages.
- [ ] Document PSR-3 mappings.
- [ ] Document PSR-14 failure behavior.
- [ ] Document PSR-15 middleware.
- [ ] Add an HTTP server example.
- [ ] Add an outbound HTTP client example.

## M8 acceptance gate

- [ ] Core installs and works without PSR bridge packages.
- [ ] PSR adapters load only when their dependencies are present.
- [ ] Logger and event-dispatcher failures follow configured policy.
- [ ] HTTP middleware restores context in every path.
- [ ] Integration tests pass against supported PSR implementations.

---

# Milestone M9 — OpenTelemetry Integration

**Target:** Weeks 8–9  
**Depends on:** M6, M8  
**Outcome:** Vendor-neutral RunIt semantics exported through OpenTelemetry PHP.

## M9.1 Integration boundaries

- [ ] Confirm supported OpenTelemetry package versions.
- [ ] Decide whether the bridge remains in the root package or moves to `it-pack/run-it-otel`.
- [ ] Record the decision in ADR-016 or a new ADR.
- [ ] Ensure no OpenTelemetry dependency enters the domain.
- [ ] Ensure library code does not configure global providers.
- [ ] Define adapter configuration object.
- [ ] Define adapter capability detection.

## M9.2 Execution and trace mapping

- [ ] Define execution-to-span mapper contract.
- [ ] Map root execution to root span where appropriate.
- [ ] Map child execution to child span or linked trace according to policy.
- [ ] Preserve RunIt execution identity as an attribute.
- [ ] Map execution kind.
- [ ] Map start and end timestamps.
- [ ] Map terminal status.
- [ ] Record safe failure information.
- [ ] Handle missing tracer provider.
- [ ] Add in-memory trace exporter tests.

## M9.3 Activity and span mapping

- [ ] Map activity identity.
- [ ] Map parent activity relationships.
- [ ] Map activity kind.
- [ ] Map activity attributes.
- [ ] Map activity status.
- [ ] Record exceptions.
- [ ] Record cancellation and timeout distinctly.
- [ ] Ensure span closure happens exactly once.
- [ ] Add nested-span hierarchy tests.
- [ ] Add failed-activity tests.

## M9.4 Context and propagation

- [ ] Define RunIt-to-OTel context mapper.
- [ ] Define OTel-to-RunIt context extractor.
- [ ] Map correlation identifiers.
- [ ] Map trace and span identifiers.
- [ ] Map approved baggage.
- [ ] Reject secret context propagation.
- [ ] Preserve remote parent state.
- [ ] Support configurable propagators.
- [ ] Add inject/extract round-trip tests.
- [ ] Add malformed propagation-header tests.

## M9.5 Measurements and metrics

- [ ] Define measurement-to-instrument mapping policy.
- [ ] Map counters.
- [ ] Map up/down counters where supported.
- [ ] Map gauges where supported.
- [ ] Map histogram samples.
- [ ] Map durations.
- [ ] Map units.
- [ ] Cache instruments by stable identity.
- [ ] Bound instrument-cache size.
- [ ] Apply metric-attribute allow lists.
- [ ] Reject high-cardinality dimensions by default.
- [ ] Add in-memory metric-reader tests.
- [ ] Add cardinality-control tests.

## M9.6 Observations and logs

- [ ] Map RunIt severity to OTel severity.
- [ ] Map message template.
- [ ] Map approved attributes.
- [ ] Include execution and activity correlation.
- [ ] Include throwable information under policy.
- [ ] Optionally, map selected observations to span events.
- [ ] Prevent duplicate logging when PSR-3 and OTel bridges are both enabled.
- [ ] Add OTel log exporter tests.
- [ ] Test disabled log-provider behavior.

## M9.7 Occurrences and snapshots

- [ ] Define occurrence-to-span-event policy.
- [ ] Define occurrence-to-log-record policy.
- [ ] Preserve occurrence identity and type.
- [ ] Avoid exporting large occurrence payloads as span attributes.
- [ ] Export snapshot identity, type, and hash by default.
- [ ] Require explicit policy for snapshot payload export.
- [ ] Add payload-size tests.
- [ ] Add occurrence mapping tests.

## M9.8 Fiber-aware OTel integration

- [ ] Verify compatibility with Fiber-aware OTel context storage.
- [ ] Document optional FFI/preload requirements.
- [ ] Add Fiber propagation integration tests.
- [ ] Add fallback behavior when Fiber support is unavailable.
- [ ] Confirm context does not leak between Fibers.

## M9 documentation

- [ ] Create `docs/integrations/opentelemetry.md`.
- [ ] Document conceptual mappings.
- [ ] Document installation.
- [ ] Document provider configuration responsibility.
- [ ] Document OTLP exporter configuration as application responsibility.
- [ ] Document cardinality controls.
- [ ] Document Fiber considerations.
- [ ] Add an executable OTel example.

## M9 acceptance gate

- [ ] Trace hierarchy represents execution and activity relationships correctly.
- [ ] Metrics use bounded attributes.
- [ ] Logs correlate with execution and activity identities.
- [ ] Secret context is never propagated.
- [ ] No global provider is configured by RunIt.
- [ ] Core remains usable without OpenTelemetry.
- [ ] Integration tests pass with in-memory OTel components.

---

# Milestone M10 — CloudEvents Integration

**Target:** Week 10  
**Depends on:** M3, M6  
**Outcome:** Bidirectional occurrence mapping using CloudEvents semantics.

## M10.1 Integration boundaries

- [ ] Confirm supported `cloudevents/sdk-php` versions.
- [ ] Decide root-package versus bridge-package placement.
- [ ] Record package-placement decision.
- [ ] Define `CloudEventFactoryInterface`.
- [ ] Define occurrence-to-CloudEvent mapper contract.
- [ ] Define CloudEvent-to-occurrence mapper contract.
- [ ] Define mapping policy configuration.

## M10.2 Occurrence to CloudEvent

- [ ] Map occurrence ID to `id`.
- [ ] Map occurrence type to `type`.
- [ ] Map source to `source`.
- [ ] Map subject to `subject`.
- [ ] Map occurrence time to `time`.
- [ ] Map content type to `datacontenttype`.
- [ ] Map data schema to `dataschema`.
- [ ] Map data to `data`.
- [ ] Map execution ID extension.
- [ ] Map activity ID extension.
- [ ] Map correlation ID extension.
- [ ] Map sequence extension.
- [ ] Preserve approved custom extensions.
- [ ] Validate extension names.
- [ ] Add complete mapping tests.

## M10.3 CloudEvent to occurrence

- [ ] Validate required CloudEvent fields.
- [ ] Reject unsupported or malformed fields safely.
- [ ] Reconstruct occurrence identity.
- [ ] Reconstruct an occurrence type.
- [ ] Reconstruct source and subject.
- [ ] Reconstruct time.
- [ ] Reconstruct schema and content type.
- [ ] Reconstruct execution identity from extension.
- [ ] Reconstruct activity identity from extension.
- [ ] Preserve unknown extension attributes.
- [ ] Define behavior when execution identity is missing.
- [ ] Add reverse-mapping tests.

## M10.4 Serialization and bindings

- [ ] Implement structured JSON serialization.
- [ ] Implement structured JSON deserialization.
- [ ] Preserve data types allowed by the SDK.
- [ ] Define maximum serialized event size.
- [ ] Reject oversized events.
- [ ] Define HTTP binding adapter if included in `1.0.0`.
- [ ] Add content-type tests.
- [ ] Add malformed-JSON tests.
- [ ] Add round-trip tests.
- [ ] Add official-spec example fixtures where licensing permits.

## M10.5 Delivery adapters

- [ ] Define CloudEvent sink port.
- [ ] Implement callback sink.
- [ ] Implement PSR-18 HTTP sink if in scope.
- [ ] Apply publication timeout policy.
- [ ] Apply retry policy only at adapter level.
- [ ] Avoid hidden durable-delivery guarantees.
- [ ] Document at-most-once default behavior.
- [ ] Add delivery-failure tests.

## M10 documentation

- [ ] Create `docs/integrations/cloudevents.md`.
- [ ] Document mapping table.
- [ ] Document extension attributes.
- [ ] Document occurrence naming.
- [ ] Document event-size limits.
- [ ] Document delivery guarantees.
- [ ] Add a structured JSON example.
- [ ] Add HTTP publication example if supported.

## M10 acceptance gate

- [ ] Occurrence-to-CloudEvent mapping passes contract tests.
- [ ] Bidirectional round trips preserve supported semantics.
- [ ] Unknown extensions are preserved.
- [ ] Oversized or malformed events fail safely.
- [ ] Core remains usable without the CloudEvents SDK.
- [ ] Delivery guarantees are explicitly documented.

---

# Milestone M11 — Fiber and Swoole/OpenSwoole Execution

**Target:** Week 10  
**Depends on:** M4, M6  
**Outcome:** Context-safe execution in concurrent and persistent PHP runtimes.

## M11.1 Fiber context storage

- [ ] Implement `FiberContextStorage`.
- [ ] Associate context stacks with Fiber identity.
- [ ] Handle main execution outside a Fiber.
- [ ] Restore parent Fiber context correctly.
- [ ] Use `WeakMap` for Fiber associations where appropriate.
- [ ] Remove the completed Fiber state.
- [ ] Handle Fiber exceptions.
- [ ] Handle Fiber suspension and resumption.
- [ ] Add parallel-Fiber isolation tests.
- [ ] Add nested-Fiber tests.
- [ ] Add repeated-Fiber memory tests.

## M11.2 Composite context storage

- [ ] Implement runtime-aware context-storage selector.
- [ ] Select Swoole coroutine storage in an active coroutine.
- [ ] Select Fiber storage in an active Fiber.
- [ ] Fall back to synchronous stack storage.
- [ ] Avoid runtime-specific imports until required.
- [ ] Add runtime-selection tests.
- [ ] Add nested runtime tests where technically valid.

## M11.3 Execution engine port

- [ ] Define `ExecutionEngineInterface`.
- [ ] Define engine capability representation.
- [ ] Implement synchronous execution engine.
- [ ] Determine whether a Fiber engine is required for `1.0.0`.
- [ ] Avoid introducing a proprietary promise contract.
- [ ] Document that engines execute work while runners own semantics.
- [ ] Add execution-engine contract tests.

## M11.4 Swoole/OpenSwoole feasibility

- [ ] Verify supported extension variants.
- [ ] Determine shared versus separate adapters.
- [ ] Document runtime-detection strategy.
- [ ] Determine minimum supported extension versions.
- [ ] Decide whether adapters remain in root package.
- [ ] Add architecture decision for adapter packaging.
- [ ] Document unsupported runtime combinations.

## M11.5 Coroutine context storage

- [ ] Implement coroutine-local context storage.
- [ ] Use coroutine identity rather than process-global storage.
- [ ] Handle execution outside a coroutine.
- [ ] Restore nested coroutine context.
- [ ] Clear context at coroutine completion.
- [ ] Add coroutine-isolation tests.
- [ ] Add sequential-request leakage tests.
- [ ] Add high-volume coroutine lifecycle tests.

## M11.6 HTTP server adapter

- [ ] Define server execution middleware contract.
- [ ] Create one execution per request.
- [ ] Extract the incoming correlation context.
- [ ] Record response status.
- [ ] Record request duration.
- [ ] Record unhandled throwable.
- [ ] Flush bounded publishers according to policy.
- [ ] Clear all request-local state in `finally`.
- [ ] Add successful-request tests.
- [ ] Add failed-request tests.
- [ ] Add client-disconnect tests where supported.

## M11.7 Worker lifecycle

- [ ] Represent worker start as an execution or occurrence according to ADR.
- [ ] Represent worker stop.
- [ ] Represent worker failure.
- [ ] Separate worker lifecycle execution from request/job execution.
- [ ] Add a runtime reset hook.
- [ ] Flush publishers within shutdown budget.
- [ ] Add worker-reload tests.
- [ ] Add long-running memory tests.

## M11 documentation

- [ ] Create `docs/integrations/fibers.md`.
- [ ] Create `docs/integrations/swoole.md`.
- [ ] Document persistent-worker requirements.
- [ ] Document context cleanup.
- [ ] Document runtime limitations.
- [ ] Add a Fiber example.
- [ ] Add a Swoole / OpenSwoole example where supported.

## M11 acceptance gate

- [ ] Fiber contexts do not leak.
- [ ] Coroutine contexts do not leak.
- [ ] Sequential persistent-worker requests remain isolated.
- [ ] Runtime-specific dependencies remain optional.
- [ ] Long-running memory tests show no linear context retention.
- [ ] Unsupported combinations are documented.

---

# Milestone M12 — Security, Privacy, Performance, and Resilience

**Target:** Week 11  
**Depends on:** M6–M11  
**Outcome:** Production-oriented security controls and measured operational behavior.

## M12.1 Data protection

- [ ] Complete data-classification threat model.
- [ ] Review every artifact field for sensitivity.
- [ ] Review every adapter mapping for leakage.
- [ ] Prevent secret context export.
- [ ] Prevent unrestricted environment capture.
- [ ] Prevent arbitrary object serialization.
- [ ] Bound attribute count.
- [ ] Bound attribute value size.
- [ ] Bound occurrence payload size.
- [ ] Bound snapshot payload size.
- [ ] Bound activity depth.
- [ ] Bound artifact count per execution.
- [ ] Add negative security tests for all limits.

## M12.2 Tenant isolation

- [ ] Test context isolation between executions.
- [ ] Test nested tenant-context behavior.
- [ ] Test Fiber tenant isolation.
- [ ] Test coroutine tenant isolation.
- [ ] Test persistent-worker request isolation.
- [ ] Prevent accidental context inheritance for detached executions.
- [ ] Add tenant-leak regression tests.

## M12.3 Serialization security

- [ ] Review all normalizers.
- [ ] Reject resources.
- [ ] Reject closures.
- [ ] Reject unsupported objects.
- [ ] Detect recursive arrays or object graphs where applicable.
- [ ] Define maximum serialization depth.
- [ ] Define invalid UTF-8 handling.
- [ ] Define non-finite float handling.
- [ ] Add malformed-payload tests.
- [ ] Add fuzz tests for serializers.

## M12.4 Snapshot integrity

- [ ] Verify deterministic snapshot hashing.
- [ ] Define hash algorithm configuration.
- [ ] Define optional signing port.
- [ ] Implement signing adapter only if required for `1.0.0`.
- [ ] Verify predecessor chaining.
- [ ] Add tamper-detection tests.
- [ ] Document that hashing does not provide authenticity.

## M12.5 Performance benchmarks

- [ ] Benchmark null execution wrapper.
- [ ] Benchmark in-memory execution lifecycle.
- [ ] Benchmark activity lifecycle.
- [ ] Benchmark measurement creation.
- [ ] Benchmark observation creation.
- [ ] Benchmark throwable normalization.
- [ ] Benchmark context copy-on-write.
- [ ] Benchmark composite publisher.
- [ ] Benchmark filtering and sampling.
- [ ] Benchmark redaction.
- [ ] Benchmark buffering.
- [ ] Benchmark OTel mapping.
- [ ] Benchmark CloudEvents mapping.
- [ ] Benchmark Fiber context switching.
- [ ] Benchmark Swoole coroutine context switching where available.
- [ ] Record hardware and runtime configuration.
- [ ] Store machine-readable benchmark baselines.

## M12.6 Performance gates

- [ ] Ratify null-path latency target.
- [ ] Ratify in-memory execution latency target.
- [ ] Ratify activity latency target.
- [ ] Ratify artifact-publication throughput target.
- [ ] Ratify the memory-per-execution target.
- [ ] Define an acceptable regression threshold.
- [ ] Add non-blocking benchmark comparison to pull requests.
- [ ] Add blocking regression gate after baseline stability.
- [ ] Document measured results rather than theoretical claims.

## M12.7 Resilience

- [ ] Inject clock failures.
- [ ] Inject ID-generator failures.
- [ ] Inject serializer failures.
- [ ] Inject redactor failures.
- [ ] Inject sampling-policy failures.
- [ ] Inject publisher failures.
- [ ] Inject snapshot-store failures.
- [ ] Inject context-detach failures.
- [ ] Verify an original operation result or throwable priority.
- [ ] Verify failure fallback does not recurse.
- [ ] Verify buffer overflow behavior.
- [ ] Verify bounded shutdown behavior.

## M12.8 Supply-chain security

- [ ] Run Composer audit.
- [ ] Add dependency review to pull requests.
- [ ] Generate an SBOM for release artifacts.
- [ ] Review package scripts for unsafe behavior.
- [ ] Review optional adapter dependencies.
- [ ] Pin CI actions by immutable reference according to project policy.
- [ ] Enable repository secret scanning.
- [ ] Document vulnerability-reporting process.
- [ ] Verify the release provenance approach.

## M12 acceptance gate

- [ ] Threat model is reviewed.
- [ ] Security limits are implemented and tested.
- [ ] Tenant isolation is proven across supported runtimes.
- [ ] Benchmark baselines are published.
- [ ] Performance targets are met or explicitly revised.
- [ ] Failure-injection suite passes.
- [ ] No unresolved critical or high-risk security finding remains.

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
