# RunIt — Refactoring Plan

**Companion to:** `docs/REVIEW.md` (findings referenced by section: `§3.x`, recommendations `§4` P0–P3).
**Date:** 2026-08-24
**Principle:** close the exception-preservation and observability holes first
(P0) — they are the highest-consequence residuals precisely because the
surrounding guarantees were just hardened — then make the security default real
and fix bounded-state defects (P1), then DRY residue and contract hygiene (P2),
then defensive-typing and documentation polish (P3). Each phase is independently
shippable and test-gated. Public-contract changes carry a BC note and a
refreshed `tools/api-compat/api-baseline.json`; behavior defaults that change
hot-path cost carry benchmark evidence.

**Source of truth for this plan:** the evidence in `docs/REVIEW.md` (repo-wide
scans, three full-layer deep reads, and an all-green gate run under PHP 8.5.8).
No source has been modified yet.

---

## Phase 0 — P0: exception-preservation & observability holes

> **Status: Complete (2026-08-24).** All health-callback sites are guarded — a throwing
> handler lands in `FallbackErrorLogReporter` (one bounded line, primary + handler fault)
> and never touches business flow; startup-publish faults no longer abort the operation;
> setup faults (identity/start/scope/attach) are reported exactly once before surfacing.
> The default stack now carries recursion protection (`FailSafeArtifactPublisher` depth
> guard → health) and bound-exceeded drops are observable through the publication-failure
> handler as `BoundDropException` notices. The builder consumes
> `PublisherStackFactoryInterface` via a new `withPublisherStackFactory()` seam (baseline
> refreshed). Green: Unit/Conformance/Integration suites incl. 9 new injection tests,
> `analyse`, `cs:check`, `architecture`, `api:check`.

Goal: make the ADR-008 guarantee hold against *any* collaborator behavior —
including misbehaving health callbacks — and ensure every non-terminal
execution and every bounded drop leaves an observable trace.

### T0.1 — Guard all health-callback invocations (REVIEW.md §3.5.1, §3.6, §4 P0-1)
- **Goal:** `reportFailure()` invokes the user closure unguarded. A throwing
  callback on the success path escapes into the outer catch, drives
  `completeFailure()` with the callback's own throwable, and is rethrown as the
  business failure. The same exposure exists through `CallbackHealthReporter`
  and `BoundedArtifactPublisher::$onDropped`. Worst case today: a callback
  throw during the startup execution publish prevents the business operation
  from running at all.
- **Files:** `src/Application/Runner/DefaultExecutionRunner.php`,
  `src/Application/Publication/CallbackHealthReporter.php`,
  `src/Application/Publication/BoundedArtifactPublisher.php`,
  `tests/Unit/Application/Runner/ResilienceTest.php`,
  `tests/Unit/Application/Publication/*`.
- **Steps:**
  1. Wrap each callback invocation site in try/catch. On callback fault:
     report the *original* failure through a safe fallback sink (e.g. a new
     `FallbackErrorLogReporter` writing a single bounded line), then report the
     callback fault through the same fallback — never rethrow into business
     flow.
  2. Apply identically in `reportFailure()`, `CallbackHealthReporter::report()`,
     and the `onDropped` invocation, so no seam can smuggle a userland throw
     into publication flow.
  3. Protect the startup publish (`safePublish($execution)` before the operation
     runs): a fault there must record-and-continue, never abort the operation.
  4. Tests: callback that throws on success path (result preserved, both faults
     visible in fallback sink), callback that throws during startup publish
     (operation still runs), callback that throws during failure path (original
     Throwable preserved), `onDropped` that throws (publish flow continues).
- **Verify:** `composer test` green incl. new injection cases;
  `composer mutation:p0` in CI covers the new catch paths (failure policies are
  AGENTS.md mutation priority); `analyse`/`cs:check` green.
- **Risk/BC:** pure hardening; no contract change. Document the fallback sink in
  the runner docblock and `docs/architecture/publication.md`.

### T0.2 — Report pre-try-block faults (REVIEW.md §3.5.2, §4 P0-2)
- **Goal:** identifier factories, `coordinator->start()`, `ExecutionScope`
  construction, and context attach sit outside the try. A custom collaborator
  failing there leaves a non-terminal execution with **no** health report ever,
  contradicting the "always reported" posture.
- **Files:** `src/Application/Runner/DefaultExecutionRunner.php`,
  `tests/Unit/Application/Runner/ResilienceTest.php`.
- **Steps:**
  1. Extend the try region (or add an equivalent early catch-and-report) so
     definition resolution, identity allocation, start, scope assembly, and
     attach faults are reported through the health path.
  2. Preserve current rethrow semantics for these setup faults (they are
     genuine configuration errors), while guaranteeing the report happened.
  3. Failure-injection tests: throwing id factory, throwing coordinator-start,
     throwing storage attach — each asserting exactly one health report plus
     the original fault surfaced.
- **Verify:** new tests green; existing suite unchanged; `analyse`/`cs` clean.
- **Risk/BC:** none; observability only.

### T0.3 — Recursion guard and drop observability in the default stack (REVIEW.md §3.5.3–§3.5.4, §4 P0-3)
- **Goal:** `GuardedArtifactPublisher` is reachable only through
  `PublicationPipeline`, which nothing composes — the builder-wired stack
  everyone uses lacks recursion protection required by AGENTS.md
  self-instrumentation rules. Separately, `BoundedArtifactPublisher` drops are
  structurally invisible in default wiring (no `onDropped`, no builder seam).
- **Files:** `src/SDK/Builder/RunItBuilder.php`,
  `src/Application/Factory/PublisherStackFactory.php`,
  `src/Contract/Port/PublisherStackFactoryInterface.php`,
  `src/Application/Runner/DefaultExecutionRunner.php`,
  `tests/Unit/SDK/Builder/RunItBuilderTest.php`, `docs/QA.md`.
- **Steps:**
  1. Compose the recursion guard into the default stack — either wrap in
     `RunItBuilder::build()` (outermost) or fold guard behavior into
     `FailSafeArtifactPublisher`; recursive publishes must land in the health
     path, never loop.
  2. Add a builder seam for bound-drop observability: simplest correct shape is
     routing `onDropped` to the configured publication-failure handler (a drop
     becomes a reported notice), keeping the raw closure param for advanced use.
  3. Consume `PublisherStackFactoryInterface` in the builder (it currently news
     the concrete factory) — this also resolves REVIEW.md's dead-end-port
     finding without removal.
  4. Tests: recursive publish through a built runtime lands in health exactly
     once; bound-exceeded drop observable through the handler; golden smoke test
     proving default wiring order unchanged otherwise.
- **Verify:** `composer architecture` still 0 violations; new tests green;
  `composer api:baseline` refreshed if the factory interface gains consumers
  (additive only); `docs/architecture/self-instrumentation.md` updated.
- **Risk/BC:** additive seam + behavior addition (guard always-on is the point);
  document in release notes.

**Phase 0 exit:** injection tests prove no userland fault can replace a business
outcome or escape reporting; recursion protection and drop observability hold in
the default wiring; all gates green.

---

## Phase 1 — P1: real security default & bounded-state defects

> **Status: Complete (2026-08-24).** Deny-by-default redaction shipped: the builder
> installs the default redactor unless `withRedactor()` is explicitly called —
> `withRedactor(null)` is a recorded opt-out. **Semantics deviation, verified during
> implementation:** the default config's payload toggles are *key-driven*, so out of the
> box the guarantee is unconditional throwable-message masking; occurrence/snapshot
> payload masking activates by configuring `sensitiveDataKeys` through a custom redactor.
> Docs/recipe updated to teach exactly this. OTel gained
> `withOccurrenceLogAttributeAllowList()` (extensions-only filter; structural identity
> attributes always export). Bounded-state cluster fixed: terminal snapshots release
> their counter on every exit path incl. count/payload drops; `close()` reports remainder
> drops to health; fail-safe reports *before* policy so fail-closed rethrows are visible;
> drain() propagation documented. Snapshot integrity tooling kept as first-class opt-in:
> new recipe `docs/recipes/snapshot-integrity.md`, verifier marked `@api`. Benchmark
> delta recorded (redaction stage ≈0.5–1.0 µs/artifact). Green: 758 tests,
> `analyse`, `cs:check`, `architecture`, `api:check`.

### T1.1 — Make redaction the default and teach it (REVIEW.md §3.6, §4 P1-4)
- **Goal:** the builder installs no redactor; no example passes `withRedactor`;
  neither OTel doc mentions it — out-of-the-box RunIt exports unredacted, while
  the runner docblock implies using the builder addresses this.
- **Files:** `src/SDK/Builder/RunItBuilder.php`,
  `src/Application/Publication/DefaultArtifactRedactor.php`,
  `docs/recipes/opentelemetry.md`, `docs/integrations/opentelemetry.md`,
  `examples/opentelemetry.php`, `benchmarks/ExecutionBench.php`,
  `docs/benchmarks.md`, tests.
- **Steps:**
  1. Decide and implement the default: install `DefaultArtifactRedactor` when
     `withRedactor()` was never called (deny-by-default, explicit opt-out via a
     nullable clear-seam), **or** keep opt-in and emit a loud one-time
     build()-time notice. Prefer deny-by-default per AGENTS.md's
     treat-artifacts-as-exfiltration-paths stance.
  2. Benchmark the delta: `benchRedaction` exists; record before/after figures
     in `docs/benchmarks.md` (AGENTS.md: performance changes require
     measurements — a default redactor adds per-publish normalization cost).
  3. Update both OTel docs + example to configure/demonstrate redaction;
     fix the example's five empty catch blocks while there (they model silent
     failure handling).
  4. Add an allow-list option for OTel log attributes mirroring the metrics
     path (`DefaultOccurrenceLogMapper` copies extension attributes unbounded
     today).
- **Verify:** built runtime masks sensitive content by default (new builder
  test); benchmark figures attached whatever the decision; docs/examples
  updated; `analyse`/`cs`/`architecture` green.
- **Risk/BC:** default-on redaction changes observable export content — flag
  prominently in release notes and `docs/upgrading` guidance; the clear-seam
  preserves escape hatch.

### T1.2 — Bounded-state hygiene cluster (REVIEW.md §3.5.5, §3.7, §4 P1-5)
- **Goal:** four small defects break the "bounded and observable" story: an
  oversized terminal snapshot strands its counter (release happens after the
  payload gate); `close()` counts remainder drops silently; standalone buffer
  drains propagate sink faults unreported; `FailClosedPolicy` rethrows before
  health reporting executes.
- **Files:** `src/Application/Publication/BoundedArtifactPublisher.php`,
  `BufferedArtifactPublisher.php`, `FailSafeArtifactPublisher.php`,
  `FailClosedPolicy.php`, tests under `tests/Unit/Application/Publication/`.
- **Steps:**
  1. Move terminal-snapshot counter release ahead of (or independent of) the
     payload-size gate so any classified-terminal snapshot releases its
     execution counter, dropped or published.
  2. Report `close()` remainder drops through the internal health reporter;
     document drain() propagation semantics for standalone use (or route them
     through health too).
  3. In `FailSafeArtifactPublisher`, report *before* delegating to the policy so
     fail-closed rethrows remain observable.
- **Verify:** targeted tests for each defect (oversized terminal snapshot
  releases budget; remainder drops reported; fail-closed visible in health
  channel); full suite green; `mutation:p0` in CI for the policy ordering.
- **Risk/BC:** behavior hardening; no signature changes.

### T1.3 — Resolve dead-end contracts (REVIEW.md §3.1, §3.8, §4 P1-6)
- **Goal:** `SnapshotSignerInterface` is implemented but wired into nothing;
  `SnapshotIntegrityVerifier` is consumer tooling referenced nowhere outside its
  test; both invite "is this supported?" confusion. (The factory-interface half
  of this finding is resolved in T0.3.)
- **Files:** `src/Contract/Port/SnapshotSignerInterface.php`,
  `src/Adapter/Common/HmacSnapshotSigner.php`,
  `src/Application/Security/SnapshotIntegrityVerifier*.php`,
  `docs/concepts/snapshot.md`, `docs/QA.md`.
- **Steps:**
  1. Decide per contract: wire (e.g., optional signer seam on snapshot store /
     verifier construction) or retire. Default recommendation: keep the signer
     as documented opt-in tooling with a recipe showing sign+verify end-to-end,
     and mark `SnapshotIntegrityVerifier` explicitly `@api` with the same
     treatment — OR remove both if no near-term consumer exists. Pick one;
     document the choice.
  2. If removed: BC note + baseline refresh + QA.md update (mirrors the
     UnsupportedOperationExceptionInterface precedent).
- **Verify:** whichever way — zero "implemented but unreferenced" contracts
  remain (scripted scan); gates green; compatibility doc updated.
- **Risk/BC:** removal is a pre-1.0 deliberate break requiring BC note; wiring
  is additive only.

---

## Phase 2 — P2: DRY residue & contract hygiene

> **Status: Complete (2026-08-24).** `SequencedBehavior` adopted by all six artifacts
> (`count()` written once); `TimestampedBehavior` **deleted** — its `count()`→`timestamp()`
> mapping was a semantic bug contradicting `Countable`, and the contract test pinning it
> was corrected. Equality unified behind `StringValueEquality` (class check restored in
> `ContextSource`). Validators share `BoundedKeyGuard`; snapshot construction collapsed:
> `Snapshot::create()` is now the single template factory (`: static`, late-static
> `new static`, optional validator threaded through) and `ExecutionSnapshot`'s duplicated
> `create()`/`build()` overrides were removed outright — no caller ever passed the
> injected validator. One scoped, rationale-documented `new.static` ignore added for the
> extension-base factory (QA.md). Adapters read projections via `ArtifactField`
> (incl. `ExecutionAwareLogger` defaults). All six drift corrections applied;
> `PublicationPipelineTest` now constructs via named args. Green: 758 tests,
> `analyse`, `cs:check`, `architecture`, `api:check`.

### T2.1 — Behavior traits and equality deduplication (REVIEW.md §3.3, §4 P2-7)
- **Goal:** `SequencedBehavior`/`TimestampedBehavior` have zero production users
  while six artifacts hand-write identical `count()` bodies; `equals()` exists
  four times; `ContextSource::equals()` diverges by dropping the class check.
- **Files:** `src/Behavior/Domain/SequencedBehavior.php`,
  `TimestampedBehavior.php`, the six artifact classes,
  `StringIdentifierBehavior`, `ValidatedStringValueBehavior`,
  `ContextKey`, `AttributeKey`, `ContextSource`, fixtures/tests.
- **Steps:**
  1. Adopt-or-delete the two traits: either mix them into the six artifacts
     (harmonized with their interfaces) or delete them and their fixture users.
     Adoption preferred — the traits exist for exactly this.
  2. Extract a shared equality helper (behavior trait method or small final
     helper) implementing class-check + value-compare; repoint all five sites;
     restore the class-identity check in `ContextSource::equals()`.
  3. Confirm exact-output tests still pass untouched (pure refactor).
- **Verify:** grep shows single `equals` implementation behind all VOs;
  `ContextSource` cross-type comparison rejected by test; suite green.
- **Risk/BC:** none — identical observable behavior.

### T2.2 — Validator key-check and snapshot-create deduplication (REVIEW.md §3.3, §4 P2-7)
- **Goal:** `assertValidKey()` duplicated between `AttributeValidator` and
  `ContextValueValidator` (same logic, different exception family);
  `ExecutionSnapshot::create` copies `Snapshot::create` differing only in an
  injected validator.
- **Files:** `src/Behavior/Domain/AttributeValidator.php`,
  `BoundedPrimitiveValueWalker.php`, `src/Domain/Context/ContextValueValidator.php`,
  `src/Domain/Snapshot/{Snapshot,ExecutionSnapshot}.php`, tests.
- **Steps:**
  1. Extend the walker pattern: parameterize key assertion (exception factories)
     next to the value walk, or extract a sibling `BoundedKeyValidator` helper —
     one implementation, two exception families.
  2. Have `ExecutionSnapshot::create` delegate to `Snapshot::build()` (protected
     template hook) instead of copying validate/hash/build; keep its extra
     `$validator` parameter.
- **Verify:** parity tests (existing `ValidatorParityTest`) green unchanged;
  snapshot round-trip/exact-output tests green; complexity rule quiet.
- **Risk/BC:** internal-only.

### T2.3 — Adapter readers adopt `ArtifactField` (REVIEW.md §3.3, §4 P2-8)
- **Goal:** adapters reading projections hardcode field-name literals
  (`ObservationLoggerPublisher`, `DefaultOccurrenceLogMapper`,
  `CloudEventPublisher`) — a vocabulary rename would silently break them, the
  exact S10 risk the constant family exists to prevent.
- **Files:** the three adapter classes above + sweep for others
  (`grep "'[a-z_]*'" src/Adapter` against `ArtifactField` values).
- **Steps:** replace literals with `ArtifactField::*` imports (adapters may
  import Domain\Shared per layer rules); assert byte-identical output via
  existing adapter mapping tests; add one rename-safety test asserting adapter
  output keys equal `ArtifactField` constants.
- **Verify:** scripted scan finds zero projection-field literals in adapters;
  suites green.
- **Risk/BC:** none — identical strings.

### T2.4 — Doc/code drift corrections (REVIEW.md §3.2, §3.5, §3.8, §4 P2-9)
- **Goal:** six mismatches between what code does and what docs/types claim.
- **Files:** `src/Domain/Execution/ExecutionFailure.php`,
  `src/Application/Publication/OccurrenceTypeFilter.php`,
  `src/Application/Policy/ChildExecutionPolicy.php`,
  `src/Application/Publication/CallbackHealthReporter.php` +
  `src/SDK/Builder/RunItBuilderInterface.php`,
  `src/Application/Publication/PublicationPipeline.php`,
  `src/Domain/Context/ContextValueValidator.php` (via exception message), tests.
- **Steps:**
  1. Fix `ExecutionFailure::of()` `@throws` to the real type
     (`InvalidLifecycleTransitionException`).
  2. Align `OccurrenceTypeFilter` with its docblock ("non-occurrences pass") or
     the doc with the code — decide intended route-filter semantics, add a test
     pinning it.
  3. Delete `ChildExecutionPolicy`'s dead null branch.
  4. Document the second artifact argument of the health-callback seam in the
     builder interface (or stop forwarding it — pick one).
  5. Make `PublicationPipeline` fields private readonly with a proper factory
     method (it is consumed nowhere but tests; safe now).
  6. Improve the oversized-nested-array violation message in the context walk
     (currently reports "objects and resources are rejected").
- **Verify:** docblock/type scans clean; each item pinned by a test where
  behavior changed; suite green.
- **Risk/BC:** message-text change is observable; note in release notes.
  Pipeline visibility change is internal (no external consumers).

---

## Phase 3 — P3: decisions, defensive edges, polish

### T3.1 — Activity transition matrix: extend or document (REVIEW.md §3.8, §4 P2-10)
- **Goal:** executions forbid completing to non-terminal statuses; activities
  accept any target status once running (e.g., completing to `Running` yields
  `endedAt` set with a non-terminal status). Undocumented asymmetry.
- **Files:** `src/Domain/Activity/ActivityStatus(+Interface)`,
  `src/Domain/Activity/Activity.php`, ADR under `docs/adr/`,
  `docs/concepts/activity.md`, tests.
- **Steps:**
  1. Decide: extend the matrix discipline to activity completion targets
     (`canTransitionTo()` mirror, rejecting non-terminal targets) or document
     why activities are exempt. Recommend extending — AGENTS.md states the
     invariant for activities too ("completed activity cannot be reopened";
     a Running-with-endedAt record is neither open nor complete).
  2. If extending: add matrix + guard mirroring T1.1 of the previous plan,
     ADR-024 documenting the symmetry, negative tests.
  3. If documenting: explicit rationale in concept page + interface docblock.
- **Verify:** negative tests for rejected targets (or documented exemption in
  QA.md); coordinator/activity suites green; BC note if breaking.
- **Risk/BC:** extension breaks direct-aggregate misuse (pre-1.0 acceptable,
  mirrors ADR-023 precedent).

### T3.2 — Defensive typing edges (REVIEW.md §3.7, §4 P3-11)
- **Goal:** three cheap hardenings: throwable codes assumed int (extension
  exceptions historically violate this), estimator recursion uncapped
  (defense-in-depth — inputs are validated today), validators allocated per
  construction site on artifact paths.
- **Files:** `src/Domain/Shared/DefaultThrowableMapper.php` +
  `ThrowableDescription` (accept int, coerce `(int)` with documented clamp),
  `src/Application/Publication/PayloadSectionByteEstimator.php`
  (depth cap from `SecurityLimits::maxSerializationDepth`),
  construction sites allocating `new ContextValueValidator()`/
  `new AttributeValidator()` inline.
- **Steps:**
  1. Coerce code defensively; keep `code(): int` contract; test with an
      extension-style exception carrying a non-int code property.
  2. Thread a depth cap through `sizeOf()`; oversize returns the opaque-value
      constant; test with a deep array.
  3. Hoist shared validator instances to private static/class-level constants
      where stateless (both validators are stateless given limits default) — or
      inject once at construction; measure nothing (cold path), just remove
      churn.
- **Verify:** new edge-case tests green; `analyse` level-max quiet; suite green.
- **Risk/BC:** coercion clamps exotic codes — document.

### T3.3 — Configuration and documentation nits (REVIEW.md §3.9, §4 P3-12)
- **Goal:** seven small leftovers.
- **Files/steps (checklist):**
  1. `deptrac.yaml`: drop stale `Callback` alternation token.
  2. Delete stale `tests/Support/_generated/AcceptanceTesterActions.php`.
  3. `phpstan.neon`: remove empty `./tests/Functional` path (or populate the
     suite — see T1.1-adjacent decision; removal matches pipeline honesty).
  4. `docs/benchmarks.md`: add the missing `benchOtelNullProviderRecord`
     baseline row.
  5. `docs/conformance.md`: correct `PortFixtureInterface` path
     (`src/Conformance/Contract/`).
  6. `examples/opentelemetry.php`: replace five empty catches with reported
     failures (pairs with T1.1 doc work).
  7. `DefaultOccurrenceRecorder`: replace the last `'application/json'`
     literal with `MediaTypes::APPLICATION_JSON`.
- **Verify:** scripted greps clean; `composer validate --strict`,
  `analyse`, `architecture` green.
- **Risk/BC:** none.

---

## Cross-cutting notes

- **Gates per phase:** every phase ends green on `composer validate --strict`,
  `cs:check`, `architecture`, `api:check`, `analyse`, and the full test
  pipeline (Unit + Integration + Conformance + Concurrency). Phase 0 requires
  failure-injection proof; Phase 1 requires benchmark figures for the redaction
  default decision.
- **API baseline discipline:** additive symbols (T0.3 seams) get
  `composer api:baseline` + `docs/compatibility.md` notes; removals (T1.3
  retire-path) need BC notes and baseline refresh, mirroring the marker-deletion
  precedent.
- **ADR requirements:** T3.1's extension path mandates **ADR-024**
  (activity completion matrix). T1.1's deny-by-default redaction is a security
  default change — record it in `docs/architecture/security.md` even though no
  aggregate boundary moves.
- **Mutation testing:** local Infection stays blocked (Codeception bootstrap
  limitation); run `composer mutation:p0` in CI for T0.1, T0.2, and T1.2 —
  failure policies and state transitions are exactly AGENTS.md's mutation
  priority list. Do not claim mutation coverage verified locally.
- **Benchmark discipline:** any task touching publish-path cost (T1.1
  explicitly; T0.3 guard wrapper marginally) attaches before/after figures from
  `composer benchmark` to the task's implementation note in this file, per
  AGENTS.md.
- **Vocabulary alignment:** new public names introduced here (fallback reporter,
  drop-observability seam, activity matrix additions) receive `@see` IRI
  references and taxonomy entries where they define new controlled terms, in the
  same change.
- **Ordering flexibility:** T2.3/T3.3 are mechanical and may run parallel to
  earlier phases provided serialization-output guarantees hold. T2.1/T2.2 should
  precede T3.2 to avoid churning the same files twice. T1.1's default-redactor
  decision should land **after** T0.3 so the guard+observability wiring it
  composes with is already in place.
