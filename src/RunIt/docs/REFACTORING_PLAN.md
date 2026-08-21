# RunIt — Refactoring Plan

**Companion to:** `docs/REVIEW.md` (findings referenced by ID: `S1`–`S14`, `D1`–`D6`, `I1`–`I4`, `F1`, `P1`/`P2` from §10.2).
**Date:** 2026-08-21
**Principle:** fix correctness/security first (P0), then invariants/contracts (P1), then mechanical DRY + feature leverage (P2/P3). Each phase is independently shippable and test-gated. No public contract changes without an ADR/BC note.

---

## Phase 0 — P0 correctness & security (do first, blocks release)

Goal: eliminate the six reachable-from-public-API defects. Each task ends with a red/green test.

### T0.1 — S1: wire secret context redaction into normalization
- **Files:** `src/Domain/Execution/Execution.php`, `src/Domain/Execution/ExecutionDefinition.php`, `src/Domain/Context/DefaultContextRedactionPolicy.php`, `src/Contract/Domain/ContextRedactionPolicyInterface.php`, `src/Application/Publication/*Recorders*`, `src/SDK/DefaultRunIt.php`/`RunItBuilder.php`.
- **Steps:**
  1. Add a `ContextRedactionPolicyInterface` dependency to the normalize path. Prefer: `ExecutionContext::toArray(?ContextRedactionPolicyInterface $policy = null)` and apply it; `Execution`/`ExecutionDefinition` call it with the configured policy (injected via the recorder/scope or builder).
  2. `Execution::normalize()` (`Execution.php:142`) and `ExecutionDefinition::normalize()` must emit the **redacted** context, keyed off `ContextClassification::isSecret()`/`isExportable()`, not key-name lists.
  3. Keep `DefaultContextRedactionPolicy` as the default deny-secret policy; make it the wired default in the SDK builder.
- **Verify:** new negative test — a `secret.*` entry is **absent** from Execution-start, Execution-complete, and `ExecutionSnapshot` normalized output under default wiring. Conformance `RedactorVerifier` still green.
- **Risk/BC:** changes a serialization field's content → flag in release notes (ADR-012). Internal only; no documented contract break.

### T0.2 — S2: bound serializer recursion for objects
- **Files:** `src/Adapter/Common/JsonSnapshotSerializer.php:74-109`.
- **Steps:**
  1. In `assertSafe()`, increment `$depth` for the `is_object()` branch (`JsonSnapshotSerializer.php:89` currently passes `$depth` unchanged).
  2. Reject cycles (track visited object identities) and reject any non-`JsonSerializable` object.
  3. Route `json_decode(..., 512, ...)` depth through `SecurityLimits::maxSerializationDepth` (ties to S12).
- **Verify:** regression test — self-referential `JsonSerializable` throws `SerializationException` (not SIGSEGV); object chain deeper than `maxSerializationDepth` throws.

### T0.3 — S3: bound context value recursion
- **Files:** `src/Behavior/Domain/ContextValueValidator.php:61-84`, `src/Behavior/Domain/AttributeValidator.php`, `src/Domain/Context/ContextEntry.php`.
- **Steps:**
  1. Add `$depth` param + `?SecurityLimits $limits`; enforce `maxContextDepth` (or reuse `maxAttributeDepth`). Mirror `AttributeValidator`.
  2. Extract one shared bounded recursive walk used by both validators (fixes D5 too).
  3. Thread `SecurityLimits` into `ContextEntry` (currently none) so tuning takes effect on context.
- **Verify:** negative test — deeply nested array (>= depth bound) throws `InvalidContextValueException`, not SIGSEGV; depth 1k/50k both safe.

### T0.4 — S4: validate `ExecutionSnapshot` payload
- **Files:** `src/Domain/Snapshot/ExecutionSnapshot.php:22-54`, `src/Domain/Snapshot/Snapshot.php`.
- **Steps:** `ExecutionSnapshot::create` must run the same recursive-primitive validator `Snapshot::create` uses before `json_encode`.
- **Verify:** test — `ExecutionSnapshot::create(..., ['obj' => new stdClass()], ...)` throws; primitives still accepted.

### T0.5 — S5: `WeakMap` metadata cache
- **Files:** `src/SDK/Attribute/DefaultExecutedAsMetadataReader.php:27-28,66-94`.
- **Steps:**
  1. Replace `private array $cache` with `WeakMap` keyed on the callable object (`Closure`/`object` cases). Drop `spl_object_hash` key for objects.
  2. Remove public `cache()` exposure (or return a snapshot derived from live keys).
- **Verify:** regression test — read `#[ExecutedAs]` closure A; `unset($a)`; read fresh unattributed closure B → B returns `null`, not A's metadata. Assert cache does not grow unbounded across many distinct closures.

### T0.6 — S6: redaction must not silently drop artifacts
- **Files:** `src/Application/Publication/RedactedArtifact.php`, `RedactingArtifactPublisher.php:33`, `DefaultArtifactRedactor.php`, adapters (`CloudEventPublisher`, OTel mappers).
- **Steps:**
  1. Make `RedactedArtifact` carry enough type identity for adapters to still dispatch, **or** make redaction a terminal, observable outcome (publish a `redacted` marker occurrence / warn via health reporter) instead of delivering nothing.
  2. Prefer: adapters treat `RedactedArtifact` as an explicit "suppressed" signal, not silence.
- **Verify:** test — a redacted Occurrence still produces a visible (suppressed) delivery or a health-report warning; never an empty silent drop.

**Phase 0 exit:** `composer test` (new P0 suites) green, `composer analyse`, `composer architecture`, `composer cs:check` clean.

---

## Phase 1 — P1 invariants & contracts (magic values, clocks)

Goal: remove magic strings guarding invariants and unify status/severity vocabulary.

### T1.1 — S7: lifecycle matrix from enum values
- **Files:** `src/Application/Lifecycle/ExecutionLifecycleCoordinator.php:34-37,52,80`.
- **Steps:** rewrite `ALLOWED` as `ExecutionStatus::Created->value => [ExecutionStatus::Running->value, ExecutionStatus::Cancelled->value], …`; delegate `transition()` → `isAllowed()`.
- **Verify:** lifecycle tests unchanged; typo in matrix now a compile/static error.

### T1.2 — S9 + S11: unify `label()`/`name()` and stop enum `name()` shadowing
- **Files:** `ExecutionStatusInterface.php:23`, `ActivityStatusInterface.php:19`; enums `ObservationSeverity`, `MeasurementKind`, `MeasurementUnit`, `ContextClassification`, `SnapshotClassification`, `ActivityStatus`; `Execution.php:133`, `Activity.php`; `DefaultExecutionSpanMapper.php:84/86`, `DefaultActivitySpanMapper.php:86`, `SpanStatusMap.php`, `SeverityMap.php`, `DefaultObservationToPsr3LevelMapper.php`, `ObservationRecordingLogger.php`, `DefaultMeasurementInstrumentMapper.php:76-81`.
- **Steps:**
  1. Pick one accessor name across status contracts (recommend `label()`); update both interfaces + implementations.
  2. Rename the six enum `name(): string` methods (e.g. to `label()`) to stop shadowing PHP's unit-enum `->name`.
  3. Route all string comparisons through enum values; reduce `ObservationRecordingLogger::severityForLevel` to `ObservationSeverity::tryFrom(...) ?? Debug` (decide `'trace'` behavior deliberately).
- **Verify:** static analysis + mapper tests; no `STATUS_UNSET`/`createCounter` silent-wrong results.
- **Risk/BC:** changes public enum/interface method names → ADR + BC note; provide deprecation shim if pre-1.0 not yet declared.

### T1.3 — S10: artifact field vocabulary
- **Files:** `src/Domain/Shared/*` (new `ArtifactField` enum or `ArtifactKeys` const class), all six `normalize()` methods, `DefaultArtifactRedactor.php:42,49,56,63`, `ErrorLogHealthReporter.php:23`.
- **Steps:** define the vocabulary; use it in `normalize()` **and** the redactor so a key rename is a single-site change.
- **Verify:** redactor round-trip tests; rename one key → redactor still masks.

### T1.4 — S8 + S12: type-safe limits & unified constants
- **Files:** `src/Domain/Shared/SecurityLimits.php`, `AttributeValidator.php`, `ContextValueValidator.php`, `ExecutionContext.php`, `JsonSnapshotSerializer.php:60`, `DefaultCloudEventJsonSerializer.php:68`, `BufferedArtifactPublisher.php:233,316`, OTel adapters (`*_NANOS_*` literals).
- **Steps:**
  1. Replace `SecurityLimits::with(string $name, int $value)` with explicit withers (`withMaxAttributeCount(int)`, …) or enum-keyed `with(SecurityLimit, int)`.
  2. Standardize on `SecurityLimits $limits = new SecurityLimits()` default; delete duplicated `const`s; thread `SecurityLimits` into `ContextValueValidator`/`ExecutionContext`.
  3. Use `Duration::NANOS_PER_SECOND`/`NANOS_PER_MICROSECOND`; route `json_decode` depth through `maxSerializationDepth`.
- **Verify:** limit-config tests; depth bound honored for objects+arrays.

### T1.5 — P1 (§10.2): property hooks for string value objects
- **Files:** `src/Behavior/Domain/ValidatedStringValueBehavior.php`, `ValidatedStringIdentifierBehavior.php`, `src/Domain/Context/ContextKey.php`, `ContextSource.php`, `src/Domain/Shared/AttributeKey.php`, and the ~17 consumers (`ExecutionId`, `ActivityId`, `OccurrenceId`, `CorrelationId`, `SnapshotId`, `ExecutionKind`, `ActivityKind`, `MeasurementName`, `OccurrenceType`, …).
- **Steps:** rename backing to `$raw`; add `public string $value { get => $this->raw; }`; keep `toString()` as stable API. Verify no native-serialize/reflection-equality tests rely on `$value`.
- **Verify:** PHPStan max + value-object tests; `OccurrenceType::version()` still works.

---

## Phase 2 — P2 mechanical DRY, factories, interfaces, feature leverage

### T2.1 — D1: `clone`-wither builder
- **Files:** `src/SDK/Builder/RunItBuilder.php:77-327,74,329-390`.
- **Steps:** convert each `withX()` to `$clone = clone $this; $clone->x = $x; return $clone;`; remove `withMonotonicClock()` alias (or deprecate); switch `:364-387` 22-arg call to named args; add `withHashCalculator()`/`withExecutionFactory()`/`withLifecycleCoordinator()`/`withSnapshotFactory()` seams (F1-adjacent).
- **Verify:** builder test suite; ~230 lines removed.

### T2.2 — D2: publisher lifecycle trait
- **Files:** `src/Application/Publication/{Filtering, Sampling, Redacting, Guarded, FailSafe, Bounded}ArtifactPublisher.php`.
- **Steps:** extract `DelegatesPublisherLifecycleBehavior` (flush/close delegation) into `Behavior/`; apply to the six decorators.
- **Verify:** publisher tests unchanged.

### T2.3 — D3/D4/D5/D6: unify duplicated logic
- **Files:** `DefaultExecutionSpanMapper`/`DefaultActivitySpanMapper` (after T1.2), 4 recorders (`now()` helper), `ExecutionStatus`/`ActivityStatus::isTerminal()`, `StringValueValidator`/`StringIdentifierValidator`, key regex constant, `ExecutionLifecycleCoordinator::transition()`.
- **Steps:** one shared span mapper template (after S9); `MonotonicClockInterface::now(): InstantInterface` or shared trait (9 call sites); shared `isTerminal` trait; shared validator regex constant; `transition()` delegates to `isAllowed()`.
- **Verify:** mapper/recorder/validator/lifecycle tests.

### T2.4 — F1: `FactoryInterface` vocabulary
- **Files:** `src/Common/Contract/FactoryInterface.php`, `ExecutionFactoryInterface`, `ExecutionSnapshotFactoryInterface`, `CloudEventFactoryInterface`.
- **Steps:** either extend `FactoryInterface` with covariant `build()`, or rename it to `IdentifierFactoryMarkerInterface`.
- **Verify:** factory tests; DepTrac clean.

### T2.5 — I1–I4: missing interfaces
- **Files:** `SnapshotIntegrityVerifier.php` (+ new `Contract/Application/Security/SnapshotIntegrityVerifierInterface.php`), `PublicationPipeline.php` (`@internal` or interface), `Behavior/Domain/{ContextValueValidator,AttributeValidator}.php` (+ interfaces), `Conformance/Verifier/*` (+ `VerifierInterface`).
- **Verify:** DepTrac + PHPStan; interface-everywhere rule satisfied.

### T2.6 — S13: monotonic clock on the hot path
- **Files:** `src/Contract/Port/MonotonicClockInterface.php`, `Activity.php`, `Measurement.php`, `DefaultActivityRunner.php`, `DefaultExecutionRunner.php`, `src/Domain/Shared/Duration.php` (new `Duration` value object if adopting monotonic durations).
- **Steps:** measure activity/execution durations with `monotonicNanoseconds()`; or relax ADR-009 wording. Resolve engine once in ctor (§6.3).
- **Verify:** duration tests; engine allocated once.

### T2.7 — S14: collapse filter/policy duplicates
- **Files:** `ObservationSeverityFilter`/`SeveritySamplePolicy`, `ExecutionKindFilter`/`ExecutionKindSamplePolicy`, etc.
- **Steps:** one composable `Predicate` + sampling decision type; keep ADR/contract names stable.
- **Verify:** policy/filter tests.

### T2.8 — P2 (§10.2): `readonly class` eligibility
- **Files:** `RunItBuilder`, `BridgeConfig`, `CloudEventsBridgeConfig`, `CurrentExecution`, `FiberContextStorage::$main`.
- **Steps:** mark `readonly` where all props are initialized-once.
- **Verify:** PHPStan max.

### T2.9 — split two-type files & move interfaces
- **Files:** `Adapter/Psr/Http/HttpContextExtractorInterface.php`, `Psr7ResponseCorrelationEnricher.php`, `Propagation/DefaultHeaderPropagator.php`.
- **Steps:** one type per file; move interfaces under `Contract/`.
- **Verify:** DepTrac; autoload.

---

## Phase 3 — P3 guardrails & hygiene

- **T3.1:** add phpstan/cs rules — `else`/`elseif` → error; forbid literals matching enum values outside the enum file; cognitive-complexity + max-ctor-param thresholds; forbid `spl_object_hash` as cache key.
- **T3.2:** Infection targets for S1–S6 areas (`ContextValueValidator`, `ExecutionLifecycleCoordinator`, `JsonSnapshotSerializer`, `ExecutionSnapshot`, `DefaultExecutedAsMetadataReader`, `DefaultArtifactRedactor`).
- **T3.3:** untrack generated artifacts (`build/coverage`, `build/*.xml`, `vendor/`); add `.gitignore`; fix stale coverage index referencing `SafeArtifactPublisher.php`.
- **T3.4:** centralize duplicated header/context-key defaults (`X-Correlation-Id` ×3, `X-Execution-Id` ×2, `ctx.correlation.id` ×2, `CORRELATION_ATTRIBUTE_KEY` ×2) into one vocabulary.

---

## Ordering & dependencies

```
Phase 0 (T0.1–T0.6)  ── independent, parallelizable; all P0, blocks release
        │
Phase 1 (T1.1–T1.5)  ── T1.2 before T2.3; T1.3 after T0.1 context shape settles
        │
Phase 2 (T2.1–T2.9)  ── T2.3 depends on T1.2; T2.6 depends on T1.4 limits
        │
Phase 3 (T3.1–T3.4)  ── guardrails; run continuously after each phase
```

**Cross-cutting checks after every phase:** `composer test`, `composer analyse`, `composer architecture`, `composer cs:check`, `composer mutation` (where added).

**Definition of done:** all `S`/`D`/`I`/`F`/`P` items closed; PHPStan max + DepTrac 0 violations; new P0 regression/negative tests green; no silent artifact drops; context secrets provably withheld by default.
