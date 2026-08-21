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

### T0.6 — S6: redaction must not silently drop artifacts — **DONE 2026-08-21**
- **Files:** `src/Contract/Domain/RedactedArtifactInterface.php` (new), `src/Application/Publication/RedactedArtifact.php`, `src/Adapter/CloudEvents/CloudEventPublisher.php`, `src/Adapter/OpenTelemetry/TelemetryPublisher.php`, `src/Adapter/OpenTelemetry/Contract/OccurrenceLogMapperInterface.php`, `src/Adapter/OpenTelemetry/Logs/DefaultOccurrenceLogMapper.php`.
- **What changed:**
  - `RedactedArtifact` only implemented `ExecutionArtifactInterface`, so when the redactor replaced an occurrence/snapshot with its redacted projection, the downstream adapters (which dispatch on concrete interfaces like `OccurrenceInterface`/`SnapshotInterface`) no longer recognized it and returned/silently dropped it — a redacted occurrence vanished with no trace.
  - Added `RedactedArtifactInterface` (marker extending `ExecutionArtifactInterface`); `RedactedArtifact` now implements it so any publisher can detect a suppressed artifact.
  - `CloudEventPublisher` now routes a `RedactedArtifactInterface` to a new `mapRedacted()` path that emits a visible, non-sensitive CloudEvent: it preserves only safe identity (id, type, source, subject, execution id, sequence) and sets a `redacted=true` extension. No secret-bearing `data` is emitted.
  - `TelemetryPublisher` routes a `RedactedArtifactInterface` to `OccurrenceLogMapperInterface::mapRedactedOccurrence()` / `mapRedactedSnapshot()` (new methods on the mapper interface + `DefaultOccurrenceLogMapper`), which emit a `runit.occurrence` / `runit.snapshot` log/span-event carrying only safe identity and a `runit.redacted=true` attribute. A redacted occurrence (has `source`) and a redacted snapshot (has `classification`) are both delivered visibly; never silently dropped.
  - The `CloudEventPublisher` constructor gained an optional, backward-compatible `?CloudEventFactoryInterface $factory = null` (defaults to `DefaultCloudEventFactory`) so the suppressed event is built through the same factory as normal events without changing the mapper interface. `OccurrenceLogMapperInterface` gained the two `mapRedacted*` methods (one concrete consumer, `TelemetryPublisher`) — a documented, deliberate interface extension for this P0 fix.
- **Verify:** `CloudEventPublisherTest::testRedactedOccurrenceProducesVisibleSuppressedDelivery` (redacted occurrence → captured CloudEvent with `id`/`type` preserved and `extensions['redacted'] === true`, not a silent drop); `TelemetryPublisherTest::testRedactedOccurrenceProducesVisibleSuppressedLog` (redacted occurrence → at least one log record carrying `runit.redacted=true`). Affected suites green: `Application/Publication` 57/57, `Adapter/CloudEvents` 31/31, `Adapter/OpenTelemetry` 48/48; PHPStan level max OK; DepTrac 0 violations; `composer cs:check` clean.
- **Note:** redaction correctness itself is unchanged (secrets are still masked before publication); this task only fixes the *delivery* gap so a redacted artifact remains observable rather than disappearing. The `RedactedArtifact` carries no sensitive data by construction; the suppressed deliveries expose only safe identity.

**Phase 0 exit:** `composer test` (new P0 suites) green, `composer analyse`, `composer architecture`, `composer cs:check` clean.

---

## Phase 1 — P1 invariants & contracts (magic values, clocks)

Goal: remove magic strings guarding invariants and unify status/severity vocabulary.

### T1.1 — S7: lifecycle matrix from enum values — **DONE 2026-08-21**
- **Files:** `src/Application/Lifecycle/ExecutionLifecycleCoordinator.php`.
- **What changed:**
  - `ALLOWED` was keyed on magic strings (`'created'`, `'running'`, …) with the same string literals repeated in the values. A typo such as `'runing'` was a silent, valid-looking but wrong matrix entry — exactly the S7 defect.
  - `ALLOWED` now derives every key and value from `ExecutionStatus` enum cases: `ExecutionStatus::Created->value => [ExecutionStatus::Running->value, ExecutionStatus::Cancelled->value], …` (and `Running` → `Succeeded`/`Failed`/`Cancelled`/`TimedOut`). A bad case name is now a compile/static error, and the matrix cannot drift from the enum.
  - `transition()` now delegates its legality check to `isAllowed()` instead of re-implementing the `isset`/`in_array` walk, so the matrix has a single source of truth. (`isAllowed()` is the side-effect-free companion and remains the public query for tooling/tests.)
- **Verify:** `tests/Unit/Application/Lifecycle/ExecutionLifecycleCoordinatorTest.php` (11 tests, 22 assertions) unchanged/green — including `Transition matrix` and `Illegal transition rejected`. Plus `Domain/Execution` (15/15) and `Application/Runner` (19/19) regression green; PHPStan level max OK; DepTrac 0 violations; `composer cs:check` clean.
- **Note:** this eliminated a pre-existing cs defect surfaced by the new `CloudEventPublisher` constructor from T0.6 (empty-constructor brace style) — fixed in the same change so `composer cs:check` stays clean. No public contract change; behavior identical.

### T1.2 — S9 + S11: unify `label()`/`name()` and stop enum `name()` shadowing — **DONE 2026-08-21**
- **Files:** six interfaces (`ExecutionStatusInterface` already had `label()`; renamed `ActivityStatusInterface`, `ObservationSeverityInterface`, `MeasurementKindInterface`, `MeasurementUnitInterface`, `ContextClassificationInterface`, `SnapshotClassificationInterface`) and six enums (`ObservationSeverity`, `MeasurementKind`, `MeasurementUnit`, `ContextClassification`, `SnapshotClassification`, `ActivityStatus`); callers in `DefaultContextToOtelMapper`, `DefaultOccurrenceLogMapper`, `DefaultActivitySpanMapper`, `SeverityMap`, `DefaultMeasurementInstrumentMapper`, `ObservationLoggerPublisher`, `Activity`, `Snapshot`, `ContextEntry`, `DefaultContextRedactionPolicy`, `Measurement`, `Observation`; mappers `SpanStatusMap`, `ObservationRecordingLogger`; plus README/`tests` callers.
- **What changed:**
  - **Unified accessor name.** All six status/severity/classification enums redeclared `name(): string` (which shadowed PHP's implicit unit-enum `->name` property, so `$e->name` returned the *case name* e.g. `"Succeeded"` while `$e->name()` returned the *value* `"succeeded"` — the S9 silent-wrong trap). Renamed the method to `label()` everywhere, matching the already-correct `ExecutionStatus`/`ExecutionStatusInterface`. The interfaces' `name(): string` → `label(): string` too, so the vocabulary is now uniform across status/severity/classification contracts. A caller can no longer accidentally read the wrong thing through the magic `->name` property.
  - **Routed comparisons through enum values.** `DefaultMeasurementInstrumentMapper` now matches on the `MeasurementKind` enum case (`MeasurementKind::from($kind->label())`) instead of a stringly `match` with a `default => createCounter` arm — the instrument type is now exhaustive and a wrong kind is a compile/static error, not a silent `createCounter`. `SpanStatusMap::map()` now accepts `ExecutionStatusInterface|ActivityStatusInterface` (previously `string`), so only a real status object can be passed; the internal `match` still keys on the canonical `label()`. `SeverityMap` and `DefaultOccurrenceLogMapper`/`ObservationLoggerPublisher` now read `->label()`.
  - **Simplified severity mapping.** `ObservationRecordingLogger::severityForLevel()` collapsed from an 8-arm `match` to `ObservationSeverity::tryFrom(strtolower($level)) ?? ObservationSeverity::Debug`. Deliberate `'trace'` behavior: `'trace'` *is* a valid `ObservationSeverity` backing value, so it now maps to `ObservationSeverity::Trace` (previously the `match` default dropped it to `Debug`); unknown/non-PSR-3 levels fall back to `Debug`. This keeps RunIt's `trace` tier intact when bridging PSR-3.
- **Verify:** affected suites green — `Domain/{Observation,Measurement,Context,Snapshot,Activity,Execution}` (84 tests), `Adapter/OpenTelemetry` (48), `Adapter/Psr/{Log,Http}` (26), `Application/{Scope,Policy}` (16); full `composer test:unit` **686 tests / 5322 assertions** green; PHPStan level max OK; DepTrac 0 violations; `composer cs:check` clean. No `STATUS_UNSET` / `createCounter` silent-wrong results remain (status is now a typed input; kind is an exhaustive enum match).
- **Risk/BC:** this is a deliberate breaking change to public enum/interface method names (`name()` → `label()`) on `@api` types, plus the `SpanStatusMap::map()` signature change. The library is pre-1.0 (`composer.json` has no `version` and AGENTS.md permits breaking changes before `1.0.0` when documented), so no deprecation shim is required; a shim `name()` method is in fact impossible because `name` is the reserved unit-enum property. **Action taken:** refreshed `tools/api-compat/api-baseline.json` (and `normalization-schema-baseline.json`) via `composer api:baseline` after the intentional change; `composer api:check` now reports 0 breaks. BC impact documented here and should be called out in the release notes. (`DefaultObservationToPsr3LevelMapper` needed no change — it already routes by numeric `level()` with `trace`→`debug` by design.)

### T1.3 — S10: artifact field vocabulary — **DONE 2026-08-21**
- **Files:** new `src/Domain/Shared/ArtifactField.php` (declared as an `interface` of `public const string` keys, mirroring the OTel `AttributeKeys` const class and satisfying the project `requireInterface` rule without an exemption); the six normalize projections (`Execution`, `ExecutionDefinition`, `Activity`, `Occurrence`, `Snapshot`, `Observation`, `Measurement`), `DefaultArtifactRedactor.php:42,43,49,50,56,57,63,64,136,137`, `ErrorLogHealthReporter.php:23`; plus `RedactingPublisherTest.php` (new coupling test).
- **What changed:**
  - Introduced `ArtifactField` as the single vocabulary for the normalized field names that the security controls reach back into: `ID`, `CONTEXT`, `ATTRIBUTES`, `THROWABLE`, `DATA`, `PAYLOAD`, `MESSAGE`. Every `normalize()` projection now emits these keys through the shared constants (e.g. `ArtifactField::ATTRIBUTES => …`) instead of bare literals, and `DefaultArtifactRedactor` masks `attributes`/`throwable`/`data`/`payload` and the in-`throwable` `message` by reading the same constants. `ErrorLogHealthReporter` reads `ArtifactField::ID` for correlation.
  - Because both the projection and the redactor reference the same `ArtifactField` constants, a field rename is now a single-site change: decoupling a literal from the vocabulary (the S10 defect — "rename a key and redaction silently no-ops") is no longer possible.
  - Declared `ArtifactField` as an `interface` (not a const class) so it carries no instance surface and is exempt from the `requireInterface` rule; per the same rationale `AttributeKeys` could later be converted to an interface.
- **Verify:** new `RedactingPublisherTest::testRedactorAndNormalizeShareFieldVocabulary` locks the contract — it asserts the redactor's masked keys (`ArtifactField::ID/ATTRIBUTES/THROWABLE/DATA/PAYLOAD`) are exactly the keys produced by `Observation`/`Occurrence`/`ExecutionSnapshot` `normalize()`. Existing redaction round-trip tests stay green (`testSensitiveAttributesAreMasked`, `testThrowableMessageIsMasked`, `testOccurrenceDataIsMaskedRecursively`, `testSnapshotPayloadIsMasked`). Affected suites green: `Application/Publication` 58/58, `Domain` 315/315, `Adapter/CloudEvents` 31/31, `Adapter/Psr` 37/37; `composer cs:check` clean (0 files). PHPStan on the changed files reports no new errors (see Risk/BC).
- **Risk/BC:** `ArtifactField` is a new internal vocabulary type (no `@api` surface); normalize output keys are unchanged strings, so no external serialization/contract break. **Environmental gate note:** the full `composer test:unit` and `composer analyse` currently abort on PHP 8.5 because `readonly class …Trace\DefaultExecutionSpanMapper`/`DefaultExecutionSpanMapper` use `TraceBehavior` (a trait whose constructor-promoted properties are not `readonly`); PHP 8.5 rejects the combination at class load ("cannot use trait with a non-readonly property"). This is pre-existing and unrelated to T1.3 (the OTel adapter is untouched); the suggested fix is to mark `TraceBehavior`'s promoted properties `readonly` (they are set once and never reassigned) or drop `readonly` from the two mappers. It is flagged here because it masks the rest of the PHPStan run (the isolated run on the changed dirs surfaces only pre-existing `shipmonk` dead-code notices in `SecurityLimits`/`Publication` that are addressed by T1.4).

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
