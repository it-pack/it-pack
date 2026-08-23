# RunIt — Refactoring Plan

**Companion to:** `docs/REVIEW.md` (findings referenced by section: `§3.x`, recommendations `§4` P0–P3).
**Date:** 2026-08-22
**Principle:** fix the failing gate and the DIP/security gaps first (P0), then invariants/contracts (P1), then mechanical DRY + modern-PHP leverage (P2), then polish (P3). Each phase is independently shippable and test-gated. No public contract changes without an ADR/BC note and a refreshed `tools/api-compat/api-baseline.json`.

**Source of truth for this plan:** the evidence in `docs/REVIEW.md` (repo-wide scans + gate runs in PHP 8.5). No source has been modified yet.

---

## Phase 0 — P0: make `composer analyse` pass (release-blocking)

Goal: clear the **5 genuine** PHPStan level-max errors so the full-tree static-analysis
gate is green. Today `composer analyse` fails with 20 errors (15 accepted
`shipmonk.deadMethod` + 5 genuine: 1 `runit.requireInterface`, 4 `missingType.iterableValue`).
Phase 0 closes the 5 genuine errors.

> **Status: Complete (2026-08-22).** `composer analyse` now reports only the
> accepted `shipmonk.deadMethod` notices (15, tracked for P1 T1.2). `composer
> cs:check`, `composer architecture`, and `composer api:check` are green; the API
> baseline was regenerated. No new genuine errors were introduced.

### T0.1 — Add a contract for `HttpExecutionContext` (REVIEW.md §3.4, §4 P0-1) — **DONE**
- **Goal:** `Adapter/Psr/Http/HttpExecutionContext` is a `final readonly` `@api` DTO with no
  interface and is **not** in the `RequireInterfaceRule` exemption list, so PHPStan fails
  with `runit.requireInterface`. Give it a public contract so consumers depend on the
  abstraction, not the concrete adapter type (closes the one real DIP leak).
- **Files:**
  - `src/Contract/Shared/HttpExecutionContextInterface.php` (new) — `public readonly` shape
    mirroring the current constructor: `ExecutionNameInterface $name`,
    `ExecutionKindInterface $kind`, `?string $correlationId`,
    `ExecutionContextInterface $context`, `array<string, mixed> $attributes`.
  - `src/Adapter/Psr/Http/HttpExecutionContext.php` — `implements HttpExecutionContextInterface`.
  - `src/Adapter/Psr/Http/Psr7RequestContextExtractor.php` (and any producer) — return-type
    the new interface where it currently returns the concrete class.
  - `tests/Unit/Adapter/Psr/Http/HttpExecutionContextTest.php` (new).
- **Steps:**
  1. Author `HttpExecutionContextInterface` in `Contract/Shared` (neutral, vendor-independent).
     Keep the concrete class `@api`; document the interface as the consumption type.
  2. Make `HttpExecutionContext implements HttpExecutionContextInterface`; confirm the
     `readonly`/`final` rules still pass.
  3. Repoint producers/consumers to the interface (no behavior change).
  4. Regenerate the API baseline: `composer api:baseline` (additive symbol — backward safe).
- **Verify:** PHPStan no longer reports `runit.requireInterface`; `composer api:check` clean;
  `composer cs:check` clean; `composer architecture` 0 violations; new unit test asserts the
  class satisfies the interface and `readonly` invariants.
- **Risk/BC:** additive public interface (no break). Flag in release notes; no ADR required
  (it advances the existing M13.2/ADR-015 interface-backed policy).

### T0.2 — Type the redactor iterables (REVIEW.md §3.7, §4 P0-2)
- **Goal:** `DefaultArtifactRedactor::maskKeys()` and `maskThrowable()` declare untyped
  `array` parameters/returns (`missingType.iterableValue`). In a **security** (redaction)
  component, precise types both satisfy level max and harden the path.
- **Files:** `src/Application/Publication/DefaultArtifactRedactor.php`,
  `tests/Unit/Application/Publication/DefaultArtifactRedactorTest.php`.
- **Steps:**
  1. `maskKeys(array<array-key, mixed> $data, list<string> $keys): array{0: array<array-key, mixed>, 1: bool}`.
  2. `maskKeysRecursive(array<array-key, mixed> $data, list<string> $keys): array{0: array<array-key, mixed>, 1: bool}`
     (keeps the recursive shape consistent).
  3. `maskThrowable(array<array-key, mixed> $throwable): array{0: array<array-key, mixed>, 1: bool}`.
  4. Confirm the `config->marker` substitution and recursive tuple unwrapping still type-check.
- **Verify:** PHPStan reports 0 `missingType.iterableValue`; existing redactor suite green;
  `composer cs:check` clean; add a case asserting a nested `array<array-key, mixed>` payload is
  masked and the returned tuple shape is preserved.
- **Risk/BC:** internal-only signature tightening; no public contract change.
- **Deviation (2026-08-22):** the plan sketched `array<string, mixed>`, but the redactor's
  normalized payloads are keyed by `array-key` (e.g. `normalizeThrowable()` yields
  `array<array-key, mixed>`) and the `maskKeys()`/`maskThrowable()` call sites pass that wider
  type. Using `array<string, mixed>` reintroduced `argument.type` mismatches, so the accepted
  form is `array<array-key, mixed>`. The recursive helper `maskKeysRecursive()` was left with
  its pre-existing `mixed` parameter / `@return array{0: mixed, 1: bool}` because PHPStan does
  not flag it (its value flows back through `mixed`) — tightening it is optional and out of P0
  scope.

**Phase 0 exit:** `composer analyse` (PHPStan level max, full `src/`) reports **0 genuine errors**.
(The 15 `shipmonk.deadMethod` notices remain accepted noise; triaged in T1.2.) Also green:
`composer cs:check`, `composer architecture`, `composer api:check`. `composer api:baseline`
was regenerated (additive `HttpExecutionContextInterface` + `getAttributes()`; covariant
return widening on `Psr7RequestContextExtractor::extract()` is backward-safe).

---

## Phase 1 — P1: close the DIP leak & sweep dead code

### T1.1 — Document `HttpExecutionContext` as a public contract (REVIEW.md §4 P1-3) — **DONE**
- **Goal:** treat T0.1's interface as a first-class, documented public surface rather than an
  internal fix.
- **Files:** `docs/compatibility.md` (or release notes), `src/Contract/Shared/HttpExecutionContextInterface.php`
  (add `@api` + `@see` vocab link).
- **Steps:** mark the interface `@api`; add a `@see https://w3id.org/itpack/runit#Execution`
  reference (matches the 370-existing vocab-alignment convention); note the addition in
  `docs/compatibility.md`.
- **Verify:** `composer api:check` still clean (baseline regenerated in T0.1); `composer cs:check` clean.
- **Risk/BC:** documentation only.
- **Implemented (2026-08-22):** `Contract/Shared/HttpExecutionContextInterface` carries
  `@api` and `@see https://w3id.org/itpack/runit#Execution`; `docs/compatibility.md`
  records the additive contract and the backward-safe covariant `extract()` return.

### T1.2 — Triage the `shipmonk.deadMethod` notices (REVIEW.md §3.9, §4 P1-4) — **DONE**
- **Goal:** separate intentionally-public fluent/API surfaces from genuinely dead helpers so
  the noise floor drops and future dead code is easier to spot.
- **Files:** `src/Domain/Shared/SecurityLimits.php` (`with*` methods),
  `src/SDK/Attribute/ExecutedAs.php`, `src/Adapter/OpenTelemetry/Telemetry/*Map.php`,
  `src/Adapter/OpenTelemetry/Metrics/InstrumentCache.php`, plus any other flagged files.
- **Steps:**
  1. For each of the ~15 notices, decide: (a) **keep** because it is public fluent API
     (`SecurityLimits::with*`, attribute accessors) — leave as-is and optionally annotate
     intent; or (b) **remove** if truly unused private helper.
  2. Do **not** suppress with `@phpstan-ignore`; prefer deletion or a deliberate keep.
  3. Re-run `composer analyse` and confirm the remaining dead-Method count matches the
     explicitly-kept set.
- **Verify:** `composer analyse` error count equals the number of deliberately-kept public
  surfaces (documented in `docs/qa.md`); `composer test` green.
- **Risk/BC:** removing methods is a public-API change if they are `@api`; keep `@api` ones
  and only delete internal dead code.
- **Implemented (2026-08-22):** all 15 notices were triaged. None are genuinely-dead
  private helpers: 6 are public interface/port contract methods (consumed externally, so
  unseen by the in-tree call graph) and 9 are symmetric fluent `with*` withers on the
  public `readonly` `SecurityLimits` value object. Deleting any would be a backward-
  compatibility break, so **0 were removed**. They are documented as a deliberate keep-list
  in `docs/qa.md` (table of 15) and are not suppressed with `@phpstan-ignore`.
  `composer analyse` still reports exactly 15 errors, matching the documented kept set.

---

## Phase 2 — P2: DRY, assembly & modern-PHP leverage

> **Status: In progress (2026-08-23).** The local `composer test` gate runs the full
> suite green (692 Unit + 3 Integration + 9 Conformance; Functional has 0 tests), so it
> is **not** a blocker. T2.1 was found **unsafe as written** (see below — unifying on
> `ValidatedStringValueBehavior` would weaken validation or break `equals`/BC), so the
> current design is retained. **T2.3 is executed** (publisher-stack factory), verified by
> `composer test` (704 tests) + static gates. T2.2 (lazy-init spike) and T2.4 (property
> hooks) remain deferred (gated on `composer benchmark` / normalization tests
> respectively), not by the test gate.

### T2.1 — Standardize value-object validation on `ValidatedStringValueBehavior` (REVIEW.md §3.3, §4 P2-5) — **ANALYSIS: retained by design (not safely unifiable)**
- **Goal:** seven domain VOs instantiate validators inline (`new ContextValueValidator()`,
  `new AttributeValidator()`, …) while seven others use the `ValidatedStringValueBehavior`
  trait. Unify on the trait to remove duplication.
- **Files (candidates):** `src/Domain/Context/ContextKey.php`, `ContextEntry.php`,
  `ContextSource.php`, `src/Domain/Snapshot/Snapshot.php`, `ExecutionSnapshot.php`,
  `src/Domain/Shared/AttributeKey.php`, `src/Domain/Occurrence/Occurrence.php`.
- **Steps:**
  1. For each inline-validator VO, either adopt `ValidatedStringValueBehavior` (preferred) or
     hoist the validator to a `private readonly` constructor dependency — pick one pattern
     project-wide and apply consistently.
  2. Keep validation semantics identical (no behavior change); add/adjust unit tests only if
     coverage is missing.
- **Verify:** no `new XxxValidator()` inside VO constructors (grep clean); `composer analyse`,
  `composer architecture`, `composer test` green.
- **Risk/BC:** internal refactor; ensure any `SecurityLimits` threading still works.
- **Analysis (2026-08-23):** the premise does not hold for these candidates.
  `ValidatedStringValueBehavior` validates with `StringValueValidator` and exposes
  `equals(StringValueInterface)`; it is therefore only applicable to VOs that
  implement `StringValueInterface` (the existing 7: names/kinds/types). The
  candidates are different:
  - `ContextKey` / `AttributeKey` use the **stricter** `ContextValueValidator` /
    `AttributeValidator` (key-format + size rules). Adopting the trait would run the
    generic `StringValueValidator` instead — a **validation-weakening security
    regression** (AGENTS.md: security-sensitive behavior needs negative tests).
  - `ContextSource` implements `ContextSourceInterface` (not `StringValueInterface`),
    so the trait's `equals(StringValueInterface)` would change its equality contract.
  - `ContextEntry` / `Occurrence` / `Snapshot` are complex non-string VOs (mixed
    payloads, multiple collaborators) where the string-value trait does not apply.
  - `ExecutionSnapshot` already injects `ContextValueValidatorInterface` as a
    constructor dependency — the "hoist the validator" alternative the plan allows.
  The safe "hoist the existing validator to a `private readonly` dependency" option
  only relocates the `new XxxValidator()` (still one per VO) and would change
  constructor signatures, so it is **not** a meaningful DRY win and is not pursued.
  **Decision: keep the current per-VO validation; no code change.** The grep-clean
  verify target is intentionally not met because unifying would break semantics.


### T2.2 — Introduce lazy objects for expensive optional infrastructure (REVIEW.md §3.6, §4 P2-6) — **DEFERRED**
- **Goal:** AGENTS.md recommends lazy objects for *expensive optional infrastructure*
  (OpenTelemetry bridge, serialization caches, in-memory stores). None are used today, so
  heavy adapters are built eagerly even when unused.
- **Files (first candidates):** `src/SDK/Builder/RunItBuilder.php` (eager publisher/engine
  composition), `src/Adapter/OpenTelemetry/*` bridge factories,
  `src/Adapter/Memory/InMemorySnapshotStore.php`, `src/Adapter/Common/JsonSnapshotSerializer.php`
  caches.
- **Steps:**
  1. **Spike:** pick one safe target — lazy-initialize the composed publisher stack in
     `RunItBuilder` (or the OTel bridge) behind a `Lazy` proxy, keeping the public
     `RunItInterface` unchanged.
  2. Confirm `Lazy` works with the target (`final` class, no `__get`/`__set`, no
     `__clone`-sensitive state). Use `ReflectionClass::newLazyProxy()` / `new Lazy(fn)`.
  3. Measure (no behavior change) that an unused heavy adapter is no longer constructed until
     first use; add a test asserting deferred initialization.
  4. Expand to the other candidates only after the spike proves safe.
- **Verify:** `composer test` (incl. dogfood), `composer benchmark` shows no regression;
  `composer analyse` green (lazy objects type-check at level max).
- **Risk/BC:** lazy proxies can shift initialization order; keep within a single thread/Fiber
  boundary and never lazily proxy context-storage. Add a BC note if a constructor signature
  changes.
- **Deferred → now executable (2026-08-23):** this is an explicit spike gated on
  `composer benchmark` (no regression) and `composer test`/dogfood (deferred-init behavior).
  The local gate runs green, so the spike can be validated. Pursue starting with the single
  safe `RunItBuilder` publisher-stack target.


### T2.3 — Extract subsystem factories from the builder/runner (REVIEW.md §3.1, §4 P2-7) — **DONE**
- **Goal:** `RunItBuilder` (24 `with*` methods) and `DefaultExecutionRunner` (24 collaborators,
  `MaxConstructorParametersRule`-exempt) concentrate all assembly. Decompose into focused
  factories to shrink the composition root over time.
- **Files:** `src/SDK/Builder/RunItBuilder.php`, `src/Application/Runner/DefaultExecutionRunner.php`,
  `src/Application/Factory/*` (extend), `src/Application/Publication/*` (publisher-stack factory),
  `src/Application/Recorder/*` (recorder-set factory).
- **Steps:**
  1. Add `Application/Factory/PublisherStackFactory` that composes
     `RedactingArtifactPublisher` + `SamplingArtifactPublisher` + base publisher (mirrors the
     existing `build()` wiring).
  2. Add `Application/Factory/RecorderSetFactory` if multiple recorders are assembled together.
  3. Have `RunItBuilder::build()` delegate to these factories; keep the `with*` seams and
     copy-on-write behavior intact.
  4. Consider a `ContextStorageFactory` for the Fiber/Swoole/Stack choices.
- **Verify:** identical runtime wiring (golden test via `DefaultRunIt` smoke test);
  `composer architecture` 0 violations; `composer analyse` green; `composer cs:check` clean.
- **Risk/BC:** refactor only; the runner ctor may stay wide but the builder shrinks. No public
  contract change (builder already `@api` with same methods).
- **Deferred → now executable (2026-08-23):** a composition-root refactor whose verify is a
  golden/smoke wiring test (`DefaultRunItTest`/`SmokeTest`) run via `composer test`, which is
  green locally. Extracting the publisher-stack / recorder-set wiring into
  `Application/Factory/*` while preserving the exact object graph is safe *by construction* and
  can now be confirmed. No public contract changes expected (builder stays `@api` with the same
  `with*` methods).
- **Implemented (2026-08-23):** added `Contract/Port/PublisherStackFactoryInterface` and
  `Application/Factory/PublisherStackFactory` that compose redaction (closest to the base) then
  sampling around the configured base publisher, mirroring the previous inlined `build()` wiring.
  `RunItBuilder::build()` now delegates to `(new PublisherStackFactory())->compose(...)`; the
  `with*` seams and copy-on-write behavior are unchanged. (`RecorderSetFactory` was not needed —
  `build()` assembles no recorder set; `ContextStorageFactory` is out of scope for this pass.)
  Verified: `composer architecture` 0 violations (interface placed in `Contract/Port` because
  `Contract/Application` may not depend on `Contract/Port`), `composer analyse` green (15 accepted
  `deadMethod`), `composer api:check` clean after `composer api:baseline`, and `composer test`
  green (704 tests).


### T2.4 — Adopt property hooks for value-object projections (REVIEW.md §3.6, §4 P2-8) — **DEFERRED**
- **Goal:** several value objects expose computed projections via getter methods where PHP 8.4
  property hooks (`get`/`set`) would be more idiomatic and reduce boilerplate.
- **Files (candidates):** `src/Domain/Execution/ExecutionId.php` and other identifier VOs,
  `src/Domain/Context/*`, `src/Domain/Observation/ObservationSeverity.php`-style carriers.
- **Steps:**
  1. Identify getters that are pure projections of existing `readonly` properties (e.g.
     `toString()`-backed `->value`, derived display names).
  2. Convert to property hooks (`public string $value { get => $this->id; }`) only where it
     does not change the public type surface in a breaking way.
  3. Keep interfaces in sync; ensure serialization/normalization still works.
- **Verify:** `composer test`, `composer analyse` (hooks type-check at level max),
  `composer cs:check` green.
- **Risk/BC:** property hooks are PHP 8.4+; project already targets 8.4. Avoid hooks that
  would alter normalized output.
- **Deferred → now executable (2026-08-23):** the existing VOs deliberately use
  `public readonly string $value` (per `ValidatedStringValueBehavior`'s own note: "so the
  consuming class can stay a `readonly class`"), and several expose `toString()` projections
  rather than getters. Converting to `get`/`set` hooks changes class bodies and risks
  normalization/serialization drift, which the plan's verify gates on `composer test` (now
  green). Revisit per-VO once normalization tests can confirm no output change; convert only
  pure projections that do not alter the normalized shape.

---

## Phase 3 — P3: polish

### T3.1 — Centralize the default redaction-policy instantiation (REVIEW.md §3.3, §4 P3-9)
- **Goal:** `new DefaultContextRedactionPolicy()` appears in both `ExecutionDefinition` and
  `Execution`; centralize the default.
- **Files:** `src/Domain/Execution/ExecutionDefinition.php`, `src/Domain/Execution/Execution.php`,
  `src/Domain/Context/DefaultContextRedactionPolicy.php`.
- **Steps:** expose a shared default (e.g. a `public const`/`private static` or an injected
  dependency in the normalization path) so the policy choice lives in one place.
- **Verify:** single instantiation site (grep clean); `composer test` green.
- **Risk/BC:** internal; no contract change.

### T3.2 — Add a measured-complexity guard (REVIEW.md §3.5, §4 P3-10)
- **Goal:** control-flow discipline is enforced by convention (no `else`/`switch`) but not by a
  complexity gate (the cognitive-complexity rule was deferred in T3.1 of the prior plan).
- **Files:** `tools/phpstan/runit-rules/src/Rule/` (new rule) or `phpstan.neon` tuning.
- **Steps:**
  1. Decide a stable threshold and, if needed, a small baseline of accepted-complexity
     methods.
  2. Implement/enable a complexity rule (e.g. cognitive complexity) that flags only methods
     above the threshold; exempt the known-wide builders/runner via the existing exemption
     pattern.
  3. Fix or exempt any newly-flagged methods.
- **Verify:** `composer analyse` green; no new violations; document the threshold in
  `docs/qa.md`.
- **Risk/BC:** may surface pre-existing complex methods; handle via exemption+track, not
  broad ignores.

### T3.3 — Named builders/factories for wide-constructor value objects (REVIEW.md §3.5, §4 P3-11)
- **Goal:** five data-carrying VOs exceed 8 ctor params (`CloudEvent` 12, `Measurement` 11,
  `SecurityLimits` 10, `Observation` 10, `Timeout` 9). Acceptable for immutable carriers but
  ergonomically poor.
- **Files:** the five VOs above + small builders/factories (e.g. `Measurement::counter()`-style
  named constructors already exist for some — extend consistently).
- **Steps:** add expressive named constructors / immutable builders so callers avoid positional
  10+ arg lists; keep `readonly` and validation intact.
- **Verify:** `composer test` green; `composer analyse` green; `composer cs:check` clean.
- **Risk/BC:** additive constructors only; no break.

### T3.4 — Verify the recorder `$clock` false positive (REVIEW.md §3.9, §4 P3-12)
- **Goal:** four recorders' `$clock` is flagged `property.onlyWritten`, but it **is** read by
  `InstantClockBehavior::now()`. Confirm this is a PHPStan trait-analysis limitation, not a real
  dead dependency, before "fixing" by removing the clock.
- **Files:** `src/Application/Recorder/Default*Recorder.php`,
  `src/Behavior/Application/InstantClockBehavior.php`.
- **Steps:**
  1. Confirm `now()` (trait) reads `$this->clock` and that each recorder calls `now()`.
  2. If confirmed false positive, document the limitation in `docs/qa.md` and leave the
     dependency; optionally add a targeted PHPDoc/`@phpstan-ignore` with rationale ONLY if it
     reduces noise without hiding real issues.
  3. If a recorder genuinely never timestamps, remove its `$clock` (real cleanup).
- **Verify:** decision recorded; `composer analyse` error count reconciled; `composer test` green.
- **Risk/BC:** do **not** delete `$clock` blindly — it drives `now()` timestamps.

---

## Cross-cutting notes

- **Gates per phase:** every phase ends green on `composer cs:check`, `composer architecture`,
  `composer api:check`, and `composer analyse` (Phase 0 specifically drives `analyse` to 0
  genuine errors). `composer test` and `composer benchmark` are run where behavior/perf could
  change (P2/P3).
- **API baseline:** any additive public symbol (`HttpExecutionContextInterface`, new builders)
  is followed by `composer api:baseline` and a `docs/compatibility.md` note.
- **Mutation testing:** `composer mutation:p0` / `mutation:changed` cannot run in this
  environment (pre-existing Codeception `di` bootstrap failure). Run in CI; do not claim
  mutation coverage verified locally.
- **OTel/Swoole:** large adapters; keep them in `api:check` scope and DepTrac-clean (already 0
  violations). Lazy-object work (T2.2) touches OTel — validate there specifically.
