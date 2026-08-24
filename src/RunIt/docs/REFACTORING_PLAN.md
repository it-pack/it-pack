# RunIt — Refactoring Plan

**Companion to:** `docs/REVIEW.md` (findings referenced by section: `§3.x`, recommendations `§4` P0–P3).
**Date:** 2026-08-23
**Principle:** restore the failing static-analysis gate and the original-exception
guarantee first (P0), then make domain invariants structural and exception markers
real (P1), then mechanical DRY consolidation, bounded caches, and hot-path costs (P2),
then polish and suite hygiene (P3). Each phase is independently shippable and
test-gated. Behavior changes to public contracts carry an ADR or BC note and a
refreshed `tools/api-compat/api-baseline.json`.

**Source of truth for this plan:** the evidence in `docs/REVIEW.md` (repo-wide scans,
layer deep-reads, and gate runs under PHP 8.5.8). No source has been modified yet.

---

## Phase 0 — P0: gate health & exception-preservation guarantee

> **Status: Complete (2026-08-23).** `composer analyse` reports **0 errors**
> (previously 15 `shipmonk.deadMethod`). Green: `cs:check`, `architecture`
> (0 violations), `api:check` (baseline regenerated), full suite
> (712 tests: Unit + Conformance + Integration).

Goal: clear the **15** PHPStan level-max errors (all `shipmonk.deadMethod` on
`SecurityLimits::with*`, one root cause) and close the ADR-008 hole where a throwing
completion path can replace the original business exception.

### T0.1 — Add `withSecurityLimits()` and health-callback seams to `RunItBuilder` (REVIEW.md §3.10, §3.7, §4 P0-1)
- **Goal:** `SecurityLimits` documents "Consumers wire a tuned instance through the SDK
  builder", but no seam exists; consequently every `with*()` wither on the limits object
  is unreachable from `src/` and PHPStan reports 15 `shipmonk.deadMethod` errors.
  Additionally `onPublicationFailure` / `onSnapshotStoreFailure` are hardcoded `null`
  in `build()` with no way to supply them.
- **Files:** `src/SDK/Builder/RunItBuilder.php`, `src/SDK/Builder/RunItBuilderInterface.php`,
  `src/Domain/Shared/SecurityLimits.php`, `tests/Unit/SDK/Builder/*`.
- **Steps:**
  1. Add `withSecurityLimits(SecurityLimitsInterface $limits)` to the builder and its
     interface (copy-on-write like the other 17 seams); thread the value through
     `DefaultExecutionRunner::__construct(limits: …)`.
  2. Add `withPublicationFailureHandler(?Closure)` and `withSnapshotStoreFailureHandler(?Closure)`
     seams (typed via existing callback signatures used by the runner) so health reporting
     is configurable instead of hardcoded `null`.
  3. Keep `SecurityLimits::default()` as the builder default; do not change runner behavior
     when the default is used.
  4. Document in the runner's docblock that the built-in stack is Bounded→FailSafe only —
     redaction reaches the pipeline because the builder composes it (`PublisherStackFactory`);
     direct construction of the runner publishes unredacted (REVIEW.md §3.8 wiring note).
- **Verify:** `composer analyse` reports **0 errors** (the 15 dead-method notices disappear
  once the withers are reachable); new builder tests assert limits propagate to
  `BoundedArtifactPublisher` bounds and callbacks fire on injected publication failure;
  `composer test`, `composer cs:check`, `composer api:check` (after
  `composer api:baseline`) green.
- **Risk/BC:** additive builder methods (no break); API baseline regeneration required;
  no ADR needed (advances the existing documented intent).
- **Implemented (2026-08-23), with two recorded deviations:**
  1. The plan sketched `withSecurityLimits(SecurityLimitsInterface $limits)`;
     `SecurityLimits` deliberately has no interface (tracked exemption in
     `docs/QA.md`), and the runner already accepts the concrete type, so the seam
     takes `SecurityLimits` directly.
  2. Adding the seam alone did **not** clear the gate: `shipmonk.deadMethod`
     flags members with no *call sites* in analyzed code, and a wither that is only
     *received* remains unused-in-tree. The REVIEW.md claim that the seam "in one
     move eliminates all 15 errors" was therefore imprecise. The honest fix was to
     formally declare the documented keep-list as public API: class-level `@api`
     on `SecurityLimits`, interface-level `@api` on `PublicationPipelineInterface`,
     `VerifierInterface`, `SnapshotIntegrityVerifierInterface`, and
     `AttributeValidatorInterface`. The dead-code detector's `apiPhpDoc` usage
     provider treats `@api` members as used — matching their declared contract
     status, with no ignores, no baseline, and no public-API deletion. Recorded in
     `docs/QA.md`; `@api` declarations noted in `docs/compatibility.md`.
  Tests added (`RunItBuilderTest`): seam immutability, limits propagation via the
  activity-depth gate (`maxActivityDepth: 1` rejects depth-2 nesting), publication-
  failure handler receives adapter faults, snapshot-store handler receives store
  faults.

### T0.2 — Guarantee original-exception preservation in `completeFailure()` (REVIEW.md §3.7, §4 P0-2)
- **Goal:** in `DefaultExecutionRunner::runExecution()`, `completeFailure()` runs inside
  the catch block unprotected. If the completion policy, observation recording, or
  coordinator transition throws, that secondary exception propagates and **replaces the
  original business Throwable**, contrary to ADR-008.
- **Files:** `src/Application/Runner/DefaultExecutionRunner.php`,
  `tests/Unit/Application/Runner/ResilienceTest.php`, `DefaultExecutionRunnerTest.php`.
- **Steps:**
  1. Wrap the body of `completeFailure()` in an inner try/catch; on secondary failure,
     forward it to the configured health path (`onPublicationFailure` handler /
     `ErrorLogHealthReporter`) and never rethrow from inside the outer catch.
  2. Apply the same protection to `completeSuccess()`'s policy/transition path where a
     throw would mask a successful result.
  3. Add failure-injection tests: completion policy that throws, coordinator that throws,
     recorder that throws — each asserting the **original** exception surfaces and the
     secondary fault is reported to health.
- **Verify:** `composer test` green including new injection cases;
  `composer mutation:p0` run in CI covers the state transitions (local Infection remains
  unavailable — see cross-cutting notes); `composer cs:check` clean.
- **Risk/BC:** behavior hardening only; no contract change.
- **Implemented (2026-08-23):** both completion paths are now staged so each stage
  degrades independently and reports through the health callback:
  *completion policy* faults (success and failure paths) are reported and completion
  continues; *failure-recording* faults (throwable mapper or observation recorder) are
  reported, with a minimal fallback description built from the original throwable so
  the execution still completes as failed; *lifecycle transition* faults are reported
  and terminal publication is skipped. The stale `@throws LifecycleExceptionInterface`
  docs (and import) were removed — these private paths no longer throw. Tests added to
  `ResilienceTest`: throwing completion policy on success path (result preserved),
  throwing policy during failure path (original exception preserved), failing
  transition during failure path (original preserved), throwing mapper (execution
  still reaches its terminal snapshot). `RunItTestKit` gained optional injectable
  `completionPolicy`, `coordinator`, and `throwableMapper` parameters.

**Phase 0 exit:** `composer analyse` reports **0 errors** (no baseline noise left);
`composer test`, `cs:check`, `architecture`, `api:check` green.

---

## Phase 1 — P1: structural lifecycle invariants & real exception markers

> **Status: Complete (2026-08-23).** The transition matrix now lives in
> `ExecutionStatus::canTransitionTo()` and is enforced by the aggregate itself
> (ADR-023); `Activity::complete()` rejects re-completion;
> `ExecutionFailure` validates terminal status behind a private constructor;
> `LifecycleExceptionInterface` has three implementations and
> `UnsupportedOperationExceptionInterface` was removed (zero consumers);
> all bare SPL throws in Domain/Application are gone. Green: `analyse`,
> `cs:check`, `architecture`, full suite (Unit + Conformance + Integration).

### T1.1 — Enforce the transition matrix in the aggregate, not only the coordinator (REVIEW.md §3.7, §4 P1-3)
- **Goal:** `Execution::complete()` currently accepts any status, so callers bypassing
  `ExecutionLifecycleCoordinator` can drive a terminal execution to another outcome.
  AGENTS.md states the invariant absolutely ("Terminal executions cannot transition
  again"); defense belongs in the domain. Same for `Activity::complete()` (no reopen
  guard) and `ExecutionFailure` (public constructor accepting non-terminal statuses).
- **Files:** `src/Domain/Execution/Execution.php`, `ExecutionFailure.php`,
  `ExecutionStatus.php`, `src/Domain/Activity/Activity.php`,
  `src/Application/Lifecycle/ExecutionLifecycleCoordinator.php`,
  ADR under `docs/adr/`, `docs/concepts/execution.md`,
  `tests/Unit/Domain/Execution/*`, `tests/Unit/Application/Lifecycle/*`.
- **Steps:**
  1. Move the closed transition matrix into the domain (e.g. `ExecutionStatus::canTransitionTo()`
     backed by a single const map) and have **both** the aggregate and the coordinator use
     that one source — the coordinator keeps idempotent same-status re-completion and its
     richer conflict exceptions; the aggregate rejects any illegal rebuild outright.
  2. Guard `Activity::complete()` against completing an already-terminal activity.
  3. Make `ExecutionFailure::__construct` private (symmetric with `ExecutionSuccess`) and
     validate in `of()` that the status is terminal.
  4. Write **ADR-023**: "Lifecycle matrix owned by the execution aggregate" — documenting
     the semantic change for direct-aggregate consumers and the retained coordinator API.
     Note the BC impact in `docs/compatibility.md` (pre-1.0 deliberate break).
- **Verify:** unit tests prove `Succeeded→Failed` and `Created→Succeeded` throw from the
  aggregate itself; coordinator suite still green (idempotency unchanged); negative tests
  for activity reopen and non-terminal `ExecutionFailure`; full `composer test`;
  DepTrac unchanged (matrix stays in Domain).
- **Risk/BC:** breaking for anyone mutating aggregates directly (intentional, pre-1.0);
  requires ADR per AGENTS.md "Lifecycle semantics".
- **Implemented (2026-08-23):** matrix moved to `ExecutionStatus::canTransitionTo()`
  (added to `ExecutionStatusInterface`, single const map in the enum);
  `Execution::complete()` raises `ConflictingCompletionException` for terminal
  re-completion (including same-status — idempotency stays a coordinator behavior) and
  `InvalidLifecycleTransitionException` for illegal non-terminal transitions; the
  coordinator delegates to the same matrix (`ALLOWED` const removed, `isAllowed()`
  retained as the pure companion). `Activity::complete()` raises the new
  `ConflictingActivityCompletionException`; `ExecutionFailure::__construct` is private
  with terminal validation in `of()` via new factory
  `InvalidLifecycleTransitionException::forNonTerminalFailure()`. ADR-023 written;
  compatibility notes recorded. Tests: matrix truth table, aggregate re-completion,
  skip-running rejection, reopen guard, non-terminal failure rejection, marker
  instanceof assertions; coordinator idempotency re-asserted at the coordinator level.

### T1.2 — Implement the promised lifecycle marker; retire or implement the unused one (REVIEW.md §3.7, §4 P1-4)
- **Goal:** `LifecycleExceptionInterface` is promised by `@throws` on
  `ExecutionLifecycleCoordinatorInterface` and `DefaultExecutionRunner`, yet zero
  implementations exist — the actual lifecycle exceptions implement
  `InvalidValueExceptionInterface`, so consumers cannot catch the documented type.
  `UnsupportedOperationExceptionInterface` also has zero implementations.
- **Files:** `src/Contract/Exception/LifecycleExceptionInterface.php`,
  `UnsupportedOperationExceptionInterface.php`,
  `src/Domain/Exception/ConflictingCompletionException.php`,
  `InvalidLifecycleTransitionException.php`,
  `src/Contract/Application/ExecutionLifecycleCoordinatorInterface.php`,
  `docs/QA.md`, tests.
- **Steps:**
  1. Make `ConflictingCompletionException` and `InvalidLifecycleTransitionException`
     implement `LifecycleExceptionInterface` (keep existing markers if consumers depend on
     them — implementing both is allowed).
  2. Sweep for any code catching `InvalidValueExceptionInterface` around lifecycle calls
     and repoint to the lifecycle marker where semantically correct.
  3. For `UnsupportedOperationExceptionInterface`: either delete it (contract cleanup, BC
     note) or give it a concrete user — decide by searching external usage in ItPack
     sibling packages first; default is **delete** if nothing references it.
  4. Update `@throws` docs to match reality; record decisions in `docs/QA.md`.
- **Verify:** `grep` shows ≥2 implementations of `LifecycleExceptionInterface` and 0
  references to a deleted `UnsupportedOperationExceptionInterface`; `composer analyse`
  stays at 0 errors; `composer test` green; `composer api:baseline` refreshed if the
  marker was removed.
- **Risk/BC:** additive marker implementation (safe); interface deletion requires a BC
  note and baseline refresh.
- **Implemented (2026-08-23):** both lifecycle exceptions now implement
  `LifecycleExceptionInterface` **and** keep `InvalidValueExceptionInterface`
  (backward-compatible with existing catch blocks); the new
  `ConflictingActivityCompletionException` implements the marker as well — three
  implementations total. The runner's stale `@throws LifecycleExceptionInterface`
  docs were removed in Phase 0 (T0.2); the coordinator interface keeps them, now
  truthfully. `UnsupportedOperationExceptionInterface` deleted: repo-wide scan found
  zero consumers outside its definition, marker test, and concept docs — all updated.
  BC note recorded in `docs/compatibility.md`; no baseline entries referenced it.

### T1.3 — No unmarked SPL exceptions (REVIEW.md §3.5, §4 P1-5)
- **Goal:** three throws escape the RunIt exception-marker system:
  `SamplingArtifactPublisher.php:44` (generic `\RuntimeException` handed to the health
  reporter), `Measurement.php:184` (bare `\InvalidArgumentException` for non-finite
  values), `SecurityLimits.php:71` (same pattern for out-of-range limits).
- **Files:** `src/Application/Publication/SamplingArtifactPublisher.php`,
  `src/Domain/Measurement/Measurement.php`, `src/Domain/Shared/SecurityLimits.php`,
  `src/Domain/Exception/` (new concrete exceptions), tests.
- **Steps:**
  1. Add `InvalidMeasurementValueException implements InvalidValueExceptionInterface`
     (finite-value violation) and reuse it in `Measurement`.
  2. Add a limits violation exception (e.g. `InvalidSecurityLimitException implements
     InvalidValueExceptionInterface`) for `SecurityLimits`.
  3. Introduce an internal drop-notice type for sampling (e.g. `SamplingDropException
     implements RunItExceptionInterface`) so health reports are recognizably RunIt
     artifacts, not generic SPL faults.
  4. Preserve messages verbatim; adjust tests asserting SPL types.
- **Verify:** repo-wide scan finds zero `throw new \(RuntimeException|InvalidArgumentException|LogicException)`
  under `src/` outside adapters' vendor boundaries; `composer test` green;
  `composer api:check` clean after baseline refresh.
- **Risk/BC:** new concrete exceptions are additive; changing thrown types is observable —
  document in release notes.
- **Implemented (2026-08-23):** added `InvalidMeasurementValueException`
  (`forNonFiniteValue()`, message preserved) and `InvalidSecurityLimitException`
  (`forNonPositive()`, message preserved), both implementing
  `InvalidValueExceptionInterface`; added `SamplingDropException` (`forArtifact()`,
  `@internal`, implements `RunItExceptionInterface`) for the sampling health notice.
  Measurement and SecurityLimits tests updated to the marker types with explicit
  message assertions plus new marker-interface assertions.

---

## Phase 2 — P2: DRY consolidation, bounded caches, hot-path cost

> **Status: Complete (2026-08-23).** One shared key pattern (`Contract\Shared\KeyPattern`)
> and one shared value walk (`BoundedPrimitiveValueWalker`) back all three validators,
> pinned by a parity test; the `Observation`/`Measurement`/`SecurityLimits` constructor
> families collapsed behind shared private factories; all projection field names now come
> from `ArtifactField` (66+ replacements, output byte-identical under the existing
> exact-output tests); both per-execution caches are bounded via the new
> `SecurityLimits::maxTrackedExecutions` with release-on-terminal; the health reporter
> logs mapper-sanitized messages; the bounded publisher measures payloads natively
> (`PayloadSectionByteEstimator`) and the buffer routes every clock read through the port
> (wall-clock allowlist entry removed). `phpbench.json` was broken pre-existing and is
> fixed; first baselines recorded in `docs/benchmarks.md`. Green: `analyse`, `cs:check`,
> `architecture`, full suite (737 tests), `api:check` (baseline regenerated).

### T2.1 — Consolidate validators and constructor families (REVIEW.md §3.3, §4 P2-6)
- **Goal:** the key-pattern regex `/^[A-Za-z0-9._-]++\z/` exists three times
  (`StringValueValidator:44`, `ContextValueValidator:31`, `AttributeValidator:33`);
  `ContextValueValidator` and `AttributeValidator` are near-line-for-line twins differing
  only in thrown exception; `Observation` (×8), `Measurement` (×5), and `SecurityLimits`
  (×10) contain copy-pasted constructor families.
- **Prior-plan context:** REFACTORING_PLAN_2 T2.1 established that unifying VOs onto
  `ValidatedStringValueBehavior` would weaken validation — this task does **not** revisit
  that decision; it consolidates the *validators themselves*, not their call sites.
- **Files:** `src/Behavior/Domain/StringValueValidator.php`,
  `AttributeValidator.php`, `src/Domain/Context/ContextValueValidator.php`,
  `src/Domain/Observation/Observation.php`, `src/Domain/Measurement/Measurement.php`,
  `src/Domain/Shared/SecurityLimits.php`, tests.
- **Steps:**
  1. Hoist the shared key pattern into one constant (e.g.
     `Contract/Shared/KeyPattern::DEFAULT` or a const on the base validator) imported by
     all three validators.
  2. Merge the twin recursive value-walk logic: either parameterize
     `ContextValueValidator` with an exception factory and delete `AttributeValidator`'s
     duplicate walk, or extract a shared private trait — pick the option keeping both
     public behaviors byte-identical.
  3. Collapse each severity/unit/limits constructor family to a shared private factory
     with the varying argument only (e.g. `Observation::atSeverity(severity, …)` behind
     the eight public named constructors, which stay for DX and BC).
- **Verify:** grep shows exactly **one** definition of the key pattern; twin-walk lines
  reduced to one implementation; `composer test` green (validation semantics unchanged —
  assert identical accept/reject sets via existing tests plus added boundary cases);
  mutation:p0 in CI for validators (AGENTS.md mutation priority list includes validators).
- **Risk/BC:** none if public constructors remain; deleting `AttributeValidator` class is
  internal-only (verify no external imports first).
- **Implemented (2026-08-23):** key pattern hoisted to `Contract\Shared\KeyPattern::VALIDATED_KEY`
  (constants-only interface, mirroring `CorrelationKeys`); the twin recursive walks merged
  into `Behavior\Domain\BoundedPrimitiveValueWalker`, parameterized by exception factories
  (`ContextValueValidator` additionally rejects oversized arrays inside the walk, exactly as
  before); both public validator contracts and their exception messages are unchanged. The
  `Observation` ×8 severity constructors collapsed behind a private `atSeverity()` factory,
  `Measurement` ×5 behind `record()`, and the ten hand-copied `SecurityLimits::with*`
  clone bodies behind one typed `with(int ...$overrides)` helper — all public signatures
  preserved. New `ValidatorParityTest` pins identical accept/reject sets across both rule
  sets so the shared walk cannot silently diverge.

### T2.2 — Finish `ArtifactField` adoption in projections (REVIEW.md §3.5, §4 P2-7)
- **Goal:** `Domain/Shared/ArtifactField` constants exist, yet ~60 field-name string
  literals appear in `normalize()`/`toArray()` across `Execution`, `Activity`,
  `Occurrence`, `Snapshot`, `Observation`, `Measurement`, `ContextEntry`,
  `ThrowableDescription`, `ExecutionDefinition`, `ActivityDefinition` — sometimes mixing
  constants and literals in the same array.
- **Files:** the ten classes above; extend `ArtifactField` where a name lacks a constant.
- **Steps:**
  1. Inventory missing names; add constants to `ArtifactField` (single vocabulary home)
     with `@see` taxonomy links per the vocabulary-alignment rule.
  2. Replace literals mechanically; **serialized output must be byte-identical**
     (constants hold the exact current strings).
  3. Add a snapshot-style round-trip test asserting normalized arrays before/after match.
- **Verify:** grep finds zero bare field-name literals in projection methods (allowlist:
  `ArtifactField` itself, adapters mapping to foreign vocabularies);
  serialization round-trip tests green; `composer test`, `cs:check` clean.
- **Risk/BC:** none — pure symbol substitution with identical values.
- **Implemented (2026-08-23):** `ArtifactField` extended with the full normalized
  vocabulary (identity/relationships, lifecycle/timing, observation, measurement,
  occurrence/snapshot typing, context-entry keys, throwable-description fields — `class`
  mapped to `THROWABLE_CLASS` because of the reserved word). 67 literal key sites replaced
  across all ten projection owners. Byte-identical output verified by the existing
  exact-output `normalize()` tests (`ExecutionTest`, `ActivityTest`, `ObservationTest`,
  `MeasurementTest`, `OccurrenceTest`, `SnapshotTest`), which pin complete arrays and all
  pass unchanged.

### T2.3 — Bound the unbounded maps and close the health-reporter leak (REVIEW.md §3.8, §4 P2-8)
- **Goal:** two caches grow without eviction and leak in persistent workers:
  `HeadBasedSamplingPolicy::$decisions` (one entry per distinct execution key) and
  `BoundedArtifactPublisher::$countsPerExecution`. Separately,
  `ErrorLogHealthReporter::report()` writes raw exception text to `error_log`,
  bypassing redaction — the very path recursive-publication failures take.
- **Files:** `src/Application/Publication/HeadBasedSamplingPolicy.php`,
  `BoundedArtifactPublisher.php`, `BufferConfiguration.php` (or `SecurityLimits`),
  `src/Application/Publication/ErrorLogHealthReporter.php`, tests.
- **Steps:**
  1. Give both maps a bound (new `SecurityLimits` entry, e.g. `maxTrackedExecutions`,
     wired via the T0.1 seam). Eviction strategy: drop-oldest insertion-order trim
     (deterministic, O(1)); document the consistency trade-off for head-sampling
     (a trimmed key may re-decide after eviction).
  2. Clear `countsPerExecution` opportunistically when a terminal snapshot for that
     execution is published (exact cleanup), retaining the cap as backstop.
  3. Make `ErrorLogHealthReporter` sanitize through the throwable mapper's truncated
     description (message cap, class/file/line only) instead of raw `getMessage()`.
- **Verify:** new persistent-worker-style test loops N≫bound executions in one process and
  asserts memory/map size plateaus; health-reporter test asserts overlong/sensitive
  messages are truncated/masked; existing sampling determinism tests green;
  `composer benchmark` shows no regression on publish path.
- **Risk/BC:** additive limits with defaults = current unbounded behavior? No — default
  bound should ship enabled (fail-safe default); document chosen bound in
  `docs/architecture/security.md`.
- **Implemented (2026-08-23):** added `SecurityLimits::maxTrackedExecutions` (default
  10000, validated like every limit, plus a `withMaxTrackedExecutions()` wither).
  `HeadBasedSamplingPolicy` accepts optional `SecurityLimits` and evicts the oldest key in
  deterministic insertion order when the bound is hit (a trimmed key may re-decide — the
  documented consistency trade-off); `BoundedArtifactPublisher` applies the same bound to
  its count map and releases an execution's counter when its terminal snapshot passes.
  `ErrorLogHealthReporter` now logs the mapper's truncated, structural description
  (`[class] message …`) instead of raw `getMessage()`, with an injectable log sink for
  deterministic tests. Tests: sampling eviction/re-decision, bounded-counter release on
  terminal snapshot, health-line sanitization with unique markers (head present, tail
  absent, bounded line length).

### T2.4 — Replace eager JSON sizing and route buffer time through ports (REVIEW.md §3.9, §4 P2-9)
- **Goal:** `BoundedArtifactPublisher::publish()` runs `strlen(json_encode(...))` on every
  occurrence/snapshot payload even when limits are far away, doubling serialization work
  on the hot path — while the purpose-built `ArtifactByteEstimatorInterface` port already
  exists. `BufferedArtifactPublisher` bypasses the clock port (direct `hrtime(true)`
  ×4, `usleep(100)` busy-wait) and sits allowlisted in `ForbidDirectWallClockRule`,
  defeating deterministic testing of overflow/blocking.
- **Files:** `src/Application/Publication/BoundedArtifactPublisher.php`,
  `BufferedArtifactPublisher.php`,
  `tools/phpstan/runit-rules/extension.neon` (drop the allowlist entry),
  `benchmarks/ExecutionBench.php` (add sizing case), tests.
- **Steps:**
  1. Inject `ArtifactByteEstimatorInterface` into `BoundedArtifactPublisher` (default:
     `DefaultArtifactByteEstimator`) and replace inline encode+strlen; keep identical
     drop semantics/reason strings.
  2. Inject `MonotonicClockInterface` into `BufferedArtifactPublisher`; replace
     `hrtime(true)` calls with `nowNanoseconds()`. Extract the sleep quantum behind a
     minimal internal seam (constructor-injected `Closure` defaulting to `usleep`) so
     blocking overflow is testable without real waiting.
  3. Remove `src/Application/Publication/BufferedArtifactPublisher.php` from the wall-clock
     rule allowlists.
  4. Benchmark before/after: `benchRedaction`/`benchBuffering` plus a new bounded-publisher
     case; record numbers in `docs/benchmarks.md` (AGENTS.md: performance changes require
     measurements).
- **Verify:** overflow/blocking tests now inject fake clock+sleep (deterministic, fast);
  `composer analyse` green with the shrunken allowlist; benchmarks show equal-or-better
  publish-path timings (attach figures); `composer test` green.
- **Risk/BC:** constructor signature changes on internal publishers — check external
  construction sites; keep old signatures working via optional parameters where cheap.
- **Implemented (2026-08-23), with one recorded deviation:** the bounded publisher's
  sizing now goes through `ArtifactByteEstimatorInterface` with a new default
  collaborator, but the plan's implied default (`DefaultArtifactByteEstimator`) was
  rejected on inspection — it normalizes the whole artifact and JSON-encodes it, which
  would have *added* work to the hottest path. The new
  `Application\Publication\PayloadSectionByteEstimator` measures exactly the enforced
  sections (occurrence `data`, snapshot `payload`) natively, eliminating one full
  encoding per publish while keeping the port contract. The buffer routes flush/close
  self-timing and blocking deadlines through `monotonicNanoseconds()` and waits through
  an injectable sleep closure (`usleep` remains the production default); the
  `BufferedArtifactPublisher` wall-clock allowlist entry is removed from both rule
  registrations. Pre-existing broken `phpbench.json` fixed (invalid top-level key);
  first baselines + the new `benchBoundedPublish` subject recorded in
  `docs/benchmarks.md`.

---

## Phase 3 — P3: polish, hygiene, and measured modernization

### T3.1 — `final readonly` sweep and enum-contract pairing (REVIEW.md §3.6, §4 P3-10)
- **Goal:** seven provably mutation-free services are not declared `readonly`
  (`DeterministicHashCalculator`, `DefaultContextPropagationPolicy`,
  `DefaultContextRedactionPolicy`, `DefaultContextSerializationPolicy`,
  `DefaultThrowableMapper`, `ContextValueValidator`, `AttributeValidator` — the latter
  only if it survives T2.1). Two contract enums (`NestedExecutionMode`,
  `ContextCollisionPolicy`) back no interface while all seven domain enums do.
- **Files:** the classes/enums above; `docs/QA.md`.
- **Steps:**
  1. Mark the services `final readonly class`; run the full suite (readonly on services
     with injected readonly deps is safe).
  2. Either pair each contract enum with a small interface (mirroring domain practice) or
     record a deliberate exemption in `docs/QA.md` — choose by whether consumers need the
     abstraction; default is document-only unless a consumer exists.
- **Verify:** `RequireReadonlyForArtifactRule`/analyse green; grep confirms no remaining
  non-readonly concrete class without a documented reason (extend the QA table if any);
  `composer test` green.
- **Risk/BC:** declaration-only changes; none expected.

### T3.2 — Architecture-gate completeness: DepTrac layer + exemption-list truthfulness (REVIEW.md §3.1, §4 P3-11)
- **Goal:** `src/Behavior/Application/**` (imported by 12 Application sites) matches no
  DepTrac layer, so edges touching those traits are unpoliced. `RequireInterfaceRule`
  exempts files that no longer need it (`PublicationPipeline`, `SnapshotIntegrityVerifier`
  now implement interfaces) and lists an *interface* file
  (`HttpContextExtractorInterface.php`) among concrete-class exemptions.
- **Files:** `deptrac.yaml`, `tools/phpstan/runit-rules/extension.neon`, `docs/QA.md`.
- **Steps:**
  1. Add `src/Behavior/Application/.*` to the Application layer collectors (or a dedicated
     "Shared Behavior" layer constrained identically to Common) — prefer folding into
     Application until a second consumer layer exists.
  2. Re-run DepTrac; fix any newly visible violations properly (no skips).
  3. Prune stale `exemptFiles` entries; move genuinely permanent exemptions (attributes,
     config VOs) to a clearly-labeled permanent section with per-entry rationale in
     `docs/QA.md`.
- **Verify:** DepTrac 0 violations with `Behavior/Application` covered (assert via
  `--report-uncovered` that the traits no longer appear); extension.neon contains no
  entries for classes that implement interfaces; `composer architecture` + `analyse` green.
- **Risk/BC:** none; gate-tightening only.

### T3.3 — Name the remaining magic values (REVIEW.md §3.5, §4 P3-12)
- **Goal:** residual literals with semantic weight: `'application/json'` ×3,
  `'execution.terminal'` snapshot type + schema-version `1`
  (`ExecutionSnapshotFactory.php:46,52`), drop-reason strings
  (`BoundedArtifactPublisher.php:59–77`), priority literals `0/1`
  (`BufferedArtifactPublisher.php:135–146`), diagnostic code `'execution.failure'` +
  `'unknown'` fallbacks (`DefaultExecutionRunner.php:119,225–228`,
  `ErrorLogHealthReporter.php:24,29`), PSR-3 level numbers
  (`ObservationSeverity.php:39–49`), `ExecutionContext::MAX_ENTRIES` vs
  `SecurityLimits::maxAttributeCount` duplication.
- **Files:** the classes above; `src/Domain/Shared/ArtifactField.php` or new
  `Contract/Shared/*` homes; `docs/QA.md`.
- **Steps:**
  1. Content-type: single canonical constant (e.g. `Occurrence::CONTENT_TYPE_JSON`)
     referenced by all three sites.
  2. Snapshot type: canonical named constructor
     `SnapshotType::executionTerminal()` next to the taxonomy `@see`; schema version as
     `ExecutionSnapshot::SCHEMA_VERSION`.
  3. Drop reasons: small string-enum/constants class in `Application/Publication`;
     priorities as `PRIORITY_LOW/PRIORITY_HIGH` consts.
  4. Diagnostic codes: constants adjacent to their emitters; unify `'unknown'` fallbacks.
  5. Link `ExecutionContext::MAX_ENTRIES` to `SecurityLimits` (derive one from the other)
     or document why they differ.
- **Verify:** targeted greps come back empty; serialized output unchanged
  (round-trip tests from T2.2 cover this); `composer test`, `cs:check` green.
- **Risk/BC:** none — values preserved verbatim.

### T3.4 — Suite honesty: populate or retire empty gates (REVIEW.md §3.10, §4 P3-13)
- **Goal:** Functional/Acceptance/Concurrency suites exist but run zero tests while
  `composer test` reports green — a silent-green risk. ROADMAP marks
  `tests/Concurrency/`, `src/Adapter/Callback/`, and `src/SDK/Middleware/` as created,
  but they contain only `.gitkeep`. Timeout/cancellation mappers have no end-to-end
  exercise anywhere.
- **Files:** `codeception.yml`, `tests/*/suite.yml`, `composer.json` scripts,
  `src/Adapter/Callback/`, `src/SDK/Middleware/`, `docs/ROADMAP.md` (status updates),
  new tests.
- **Steps:**
  1. Decide per placeholder — implement or remove. Minimum honest state: delete
     `Adapter/Callback/` (its implementations live in `Application/Publication`) and
     either implement `SDK/Middleware/` or strike it from the ROADMAP checkbox list with
     a dated note.
  2. Populate `tests/Concurrency/` with the highest-value moves first: fiber-isolation
     execution tests (reuse `FiberExecutionEngine`), repeated-execution persistent-worker
     leak test (pairs with T2.3), and end-to-end TIMED_OUT/CANCELLED paths driven through
     `DefaultTimeoutMapper`/`DefaultCancellationMapper` with injected clock/cancellation
     (closes REVIEW.md §3.7 runtime-gap finding).
  3. Exclude never-populated suites from the default `test` pipeline until they contain
     tests (or keep them and rely on step 2 having populated them — pick one and document).
  4. Track a CI job running the Fiber path on a real runtime; Swoole CI remains aspirational
     until an extension-capable runner exists (document in `docs/integrations/swoole.md`).
- **Verify:** `composer test` executes >0 tests in every included suite; no suite is
  silently empty; ROADMAP checkboxes reflect reality.
- **Risk/BC:** none (test/config only).

### T3.5 — Lazy-object opportunity, strictly benchmark-gated (REVIEW.md §3.6, §4 P2-6 legacy)
- **Goal:** zero lazy objects despite AGENTS.md recommending them for expensive optional
  infrastructure. Prior-plan T2.2 deferred this with analysis (the OTel bridge is injected
  rather than constructed, and `final` classes block `newLazyProxy`). Revisit narrowly:
  OTel signal mappers construct instruments eagerly per provider even when a provider is
  null and signals silently skip (`DefaultMeasurementInstrumentMapper::record` L44–46).
- **Files:** `src/Adapter/OpenTelemetry/Metrics/DefaultMeasurementInstrumentMapper.php`,
  `InstrumentCache.php`, `benchmarks/`, `docs/benchmarks.md`.
- **Steps:**
  1. Measure first: benchmark bridge construction + null-provider record path today.
  2. Only if measurements show material cost, apply lazy initialization (ghost or
     memoized-null short-circuit — often simpler than reflection laziness) to the
     provider-dependent paths.
  3. Record the outcome either way (implemented-with-numbers, or rejected-by-measurement)
     in `docs/benchmarks.md`; do not claim a win without figures (AGENTS.md rule).
- **Verify:** benchmark deltas attached to the decision; `composer test` incl. OTel
  adapter suite green; no behavioral change for present providers.
- **Risk/BC:** none if the memoized-null variant is chosen (no reflection semantics).

---

## Cross-cutting notes

- **Gates per phase:** every phase ends green on `composer validate --strict`,
  `cs:check`, `architecture`, `api:check`, `analyse`, and `test`. Phases 0 and 1
  additionally require the new failure-injection/lifecycle negative tests; Phase 2
  requires benchmark evidence wherever hot paths change (T2.4, T2.3).
- **API baseline discipline:** additive symbols (T0.1 builder seams, T1.2/T1.3 exception
  types) are followed by `composer api:baseline` and a `docs/compatibility.md` note.
  Deletions (T1.2 possible marker removal) require a BC note and baseline refresh.
- **ADR requirements:** T1.1 mandates **ADR-023** (aggregate-owned lifecycle matrix).
  If T2.3 ships enabled bounds as security defaults, note it in
  `docs/architecture/security.md`; if T3.4 removes roadmap-promised placeholders, update
  ROADMAP rather than leaving false `[x]` history.
- **Mutation testing:** local Infection remains blocked (pre-existing Codeception
  bootstrap limitation). Run `composer mutation:p0` in CI for T0.2, T1.1, and T2.1 —
  state transitions, failure policies, and validators are exactly AGENTS.md's mutation
  priority list. Do not claim mutation coverage verified locally.
- **Vocabulary alignment:** new public names introduced here
  (`SnapshotType::executionTerminal()`, drop-reason constants, exception types) get
  `@see` IRI references and, where they define new controlled terms, additions to
  `vocab/run-it-taxonomy.ttl` in the same change.
- **Ordering flexibility:** T2.2/T3.3 (mechanical literal work) may proceed in parallel
  with Phase 1 provided serialization round-trip tests land first. T2.1 should precede
  T3.1 (readonly sweep) to avoid churning the same validator files twice.
