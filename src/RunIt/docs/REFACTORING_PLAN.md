# RunIt — Refactoring Plan

**Companion to:** `docs/REVIEW.md` (findings referenced by ID: `S1`–`S14`, `D1`–`D6`, `I1`–`I4`, `F1`, `P1`/`P2` from §10.2).
**Date:** 2026-08-21
**Principle:** fix correctness/security first (P0), then invariants/contracts (P1), then mechanical DRY + feature leverage (P2/P3). Each phase is independently shippable and test-gated. No public contract changes without an ADR/BC note.

---

## Phase 0 — P0 correctness & security (do first, blocks release)

Goal: eliminate the six reachable-from-public-API defects. Each task ends with a red/green test.

### T0.1 — S1: wire secret context redaction into normalization — **DONE 2026-08-21**
- **Files:** `src/Domain/Execution/Execution.php`, `src/Domain/Execution/ExecutionDefinition.php`, `src/Domain/Context/DefaultContextRedactionPolicy.php`, `src/Contract/Domain/ContextRedactionPolicyInterface.php`, `src/Domain/Context/ExecutionContext.php`, `tests/Unit/Domain/Execution/ExecutionContextRedactionTest.php`.
- **Chosen approach:** applied the default deny-secret policy at the **domain boundary** rather than only in the SDK builder. `ExecutionContext::toArray()` gained an optional `?ContextRedactionPolicyInterface $policy = null` parameter (added to both the concrete class and `ExecutionContextInterface` so the contract stays in sync). `Execution::normalize()` and `ExecutionDefinition::normalize()` now pass `new DefaultContextRedactionPolicy()`, so secret and non-exportable entries are withheld from any normalized projection. Because `ExecutionSnapshotFactory::terminal()` uses `Execution::normalize()` as the snapshot payload, this also covers the terminal `ExecutionSnapshot` output — all three targets (start, complete, terminal snapshot) in one change.
- **Why domain boundary, not builder wiring:** `NormalizableInterface` already mandates that normalization "never include secret or restricted values unless an explicit policy allows them." Default-deny at the boundary guarantees no leak under *any* wiring (including direct `normalize()` calls and in-memory stores), which is stronger than relying on a redactor being in the pipeline. The `toArray(?policy)` signature still lets a caller supply an explicit policy for the rare case that requires it.
- **Verify:** `tests/Unit/Domain/Execution/ExecutionContextRedactionTest.php` (5 tests) — secret entry absent from Execution/ExecutionDefinition/terminal-snapshot normalized output; raw context preserved when no policy is supplied. Plus regression: `Domain/Execution`, `Domain/Context`, `Application/Factory`, `Application/Runner` suites green; PHPStan level max OK; DepTrac 0 violations; `composer cs:check` clean.
- **Risk/BC:** changes the content of the `context` field in normalized artifacts (secrets now dropped by default). Internal only; no documented contract break, but flag in release notes (ADR-012).

### T0.2 — S2: bound serializer recursion for objects — **DONE 2026-08-21**
- **Files:** `src/Adapter/Common/JsonSnapshotSerializer.php`, `tests/Unit/Adapter/Common/JsonSnapshotSerializerTest.php`.
- **What changed:**
  - `assertSafe()` now increments `$depth` for the `JsonSerializable` object branch (previously passed `$depth` unchanged → unbounded recursion → SIGSEGV). The depth bound `SecurityLimits::maxSerializationDepth` is enforced for both objects and arrays.
  - Cycle detection: an ancestor set (`\WeakMap<object, true>`, the project-preferred non-owning association) tracks objects currently on the recursion stack; a cyclic reference throws `SerializationException` ("cyclic object reference detected") instead of overflowing the stack. The set is released in a `finally` so shared (non-cyclic) object references still serialize normally.
  - `json_encode`/`json_decode` depth is now routed through `SecurityLimits::maxSerializationDepth` (the previous hardcoded `512` is gone), tying encode/decode depth to the enforced bound (S12 tie-in). The limit is asserted as `positive-int` via an inline `@var` so it satisfies the `json_encode`/`json_decode` `int<1, max>` signature without widening the `SecurityLimits` contract.
- **Verify:** `tests/Unit/Adapter/Common/JsonSnapshotSerializerTest.php` grew from 7 to 11 tests — new cases for self-referential `JsonSerializable` (throws), object/array payload exceeding depth (throws), and shared (non-cyclic) object reference (serializes correctly). Plus `Adapter/Common`, `Application/Recorder`, `Domain/Snapshot`, `Adapter/CloudEvents` suites green; PHPStan level max OK; DepTrac 0 violations; `composer cs:check` clean.
- **Risk/BC:** internal only; no contract change. Tuning `maxSerializationDepth` now also governs encode/decode depth (previously fixed at 512).

### T0.3 — S3: bound context value recursion — **DONE 2026-08-21**
- **Files:** `src/Domain/Context/ContextValueValidator.php` (moved from `src/Behavior/Domain/`), `src/Contract/Domain/ContextValueValidatorInterface.php` (new), `src/Contract/Exception/InvalidContextValueException.php`, `tests/Unit/Domain/Context/ContextValueValidatorTest.php` (moved).
- **What changed:**
  - `ContextValueValidator::assertSupportedValue()` now runs a depth-bounded recursive walk (mirrors `AttributeValidator`): a new `MAX_DEPTH = 8` constant (overridable via `SecurityLimits::maxAttributeDepth`), an optional `?SecurityLimits $limits` constructor, and a private `assertSupportedValueRecursive($value, $depth, $maxDepth)` that throws `InvalidContextValueException::valueTooDeep()` at `depth >= maxDepth`. A deeply nested payload now throws instead of overflowing the stack.
  - `assertValidKey()` also consults `SecurityLimits::maxAttributeKeyBytes` when supplied.
  - **Namespace move:** `ContextValueValidator` is a domain service, not a trait, so it was relocated from `ItPack\RunIt\Behavior\Domain` to `ItPack\RunIt\Domain\Context` (alongside `ContextEntry`, `ContextKey`, `DefaultContextRedactionPolicy`). The 5 callers (`ContextEntry`, `ContextKey`, `Occurrence`, `Snapshot`, `CloudEvent`) and the test were repointed.
  - The move into `src/Domain` triggered the project's `runit.requireInterface` rule, so a `ContextValueValidatorInterface` contract was added and the class implements it (advances I3 for this validator).
- **Verify:** `tests/Unit/Domain/Context/ContextValueValidatorTest.php` (5 tests) — scalar accepted, object rejected, shallow nesting (depth 7) accepted, deep nesting (depth 64) throws `valueTooDeep` (no SIGSEGV), and `maxAttributeDepth` override honored. Plus `Domain/Context`, `Domain/Occurrence`, `Domain/Snapshot`, `Adapter/CloudEvents`, `Application/Recorder` suites green; PHPStan level max OK; DepTrac 0 violations; `composer cs:check` clean.
- **Deferred:** the full shared-walk unification with `AttributeValidator` (D5) and threading `SecurityLimits` into `ContextEntry`'s named constructors (would change their public signatures) remain Phase 2 work; the critical recursion bound is in place via the constant default. `AttributeValidator` (and `StringValueValidator`/`StringIdentifierValidator`) still live in `Behavior/Domain` and could be relocated to a `Domain\Validation` namespace in a follow-up for consistency.

### T0.4 — S4: validate `ExecutionSnapshot` payload — **DONE 2026-08-21**
- **Files:** `src/Domain/Snapshot/ExecutionSnapshot.php`, `tests/Unit/Domain/Snapshot/SnapshotTest.php` (execution-snapshot cases added).
- **What changed:**
  - `ExecutionSnapshot::create()` bypassed `Snapshot::create()` (it calls `self::build()` directly) and so never ran the recursive-primitive validator — a non-serializable payload such as `['obj' => new stdClass()]` fell straight into `json_encode`, silently producing `{}`/lossy content and a non-reproducible hash. Added `new ContextValueValidator()->assertSupportedValue($payload)` before `json_encode`, reusing the same `ContextValueValidator` (and therefore the same depth/length/element bounds and `InvalidContextValueException` family) already enforced by `Snapshot::create`. This closes the second half of S4 and keeps `ExecutionSnapshot` behavior identical to `Snapshot::create`.
- **Verify:** two new cases in `tests/Unit/Domain/Snapshot/SnapshotTest.php` — `testExecutionSnapshotRejectsNonPrimitivePayload` (`['obj' => new stdClass()]` throws `InvalidContextValueException`) and `testExecutionSnapshotAcceptsPrimitivePayload` (mixed scalar/null payload preserved through `payload()`). Snapshot suite 7/7 green; `SnapshotIntegrityVerifierTest` 6/6 green; PHPStan level max OK; DepTrac 0 violations; `composer cs:check` clean.
- **Note:** `Snapshot::create()` already validated (it was the subject of S4's original review); T0.4 only closes the `ExecutionSnapshot` override gap. Both paths now share the exact same validator, so behavior cannot diverge again.

### T0.5 — S5: `WeakMap` metadata cache — **DONE 2026-08-21**
- **Files:** `src/SDK/Attribute/DefaultExecutedAsMetadataReader.php`, `tests/Unit/SDK/Attribute/ExecutedAsMetadataReaderTest.php`.
- **What changed:**
  - The reader kept one `private array $cache` keyed by a string. For a plain closure it used `'closure@' . spl_object_hash($operation)`. `spl_object_hash` can be reused once the original object is freed and its slot is recycled by a new object, so a fresh unattributed closure B could inherit the freed closure A's resolved `#[ExecutedAs]` metadata — a silent correctness leak.
  - Replaced the single array cache with two stores: object-based callables (`Closure` and invokable instances) go into a `WeakMap` keyed on the callable **object identity**, and `string` / `[class, method]` array callables keep a small string-keyed array (their keys are stable, collision-free names, not memory addresses). `reflect()` was split into `reflectObject(object)` and `reflectLiteral(callable)` so PHPStan keeps the callable-array shape; `literalKey()`/`reflectLiteral()` now throw `\LogicException` if handed an object callable, documenting that object callables must go through the `WeakMap` path. Object entries are released automatically when the callable is no longer referenced, so the cache cannot grow unbounded across many distinct closures.
  - The public `cache()` method (not part of `ExecutedAsMetadataReaderInterface`) was removed to stop exposing internal cache state; the existing `testMetadataIsCachedPerCallable` now asserts identity-stable caching via `read()` instead of `cache()`.
- **Verify:** `tests/Unit/SDK/Attribute/ExecutedAsMetadataReaderTest.php` — `testFreedClosureDoesNotLeakMetadataToFreshClosure` (read attributed A; `unset($a)`; `gc_collect_cycles()`; fresh unattributed B returns `null`), `testObjectCacheDoesNotGrowUnboundedAcrossManyCallables` (2000 distinct closures, then a fresh closure still resolves `null`), plus the existing method/class/free-function/closure-bound cases. Full `tests/Unit/SDK` suite 35/35 green; PHPStan level max OK; DepTrac 0 violations; `composer cs:check` clean.

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
- **Files:** `src/Domain/Shared/SecurityLimits.php`, `src/Behavior/Domain/AttributeValidator.php`, `src/Domain/Context/ContextValueValidator.php`, `src/Domain/Context/ExecutionContext.php`, `src/Adapter/Common/JsonSnapshotSerializer.php:60`, `src/Adapter/CloudEvents/Serialization/DefaultCloudEventJsonSerializer.php:68`, `src/Application/Publication/BufferedArtifactPublisher.php:233,316`, OTel adapters (`*_NANOS_*` literals).
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
- **Files:** `SnapshotIntegrityVerifier.php` (+ new `Contract/Application/Security/SnapshotIntegrityVerifierInterface.php`), `PublicationPipeline.php` (`@internal` or interface), `src/Behavior/Domain/AttributeValidator.php` (+ interface; `ContextValueValidator` already moved to `src/Domain/Context` with `ContextValueValidatorInterface` in T0.3), `Conformance/Verifier/*` (+ `VerifierInterface`).
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
