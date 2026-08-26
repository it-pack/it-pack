# RunIt — Refactoring Plan

**Companion to:** `docs/REVIEW.md` (findings referenced by their IDs: `R-1…R-14`,
recommended follow-up order in its `§13`).
**Date:** 2026-08-26
**Principle:** the previous cycle closed all P1 defects; what remains are P2
process-completion items (test-side imports, interface-backing exemptions, member
ordering, mutation enforcement) and P3 polish. This plan therefore runs **mechanical
normalization first** (zero-risk diffs that shrink review noise), then installs the
**guards that stop those categories from regrowing**, then makes the two structural
decisions that need an explicit ruling (**interface backing**, **vocabulary homes**),
then enforces **mutation testing in CI**, and finally lists **opportunistic
modernization** tasks that are each independently skippable. Hygiene phases must not
change behavior: their diffs must be mechanically verifiable and free of API-surface
changes.

**Source of truth for this plan:** `docs/REVIEW.md` (token-level scans over 394 types,
full pcov run of the shipped pipeline — 872 tests / 5876 assertions / 98.02% line
coverage — plus targeted deep reads). No source is modified by the plan itself; each
phase below starts from the committed baseline.

---

## Cross-cutting rules for all phases

- Gates after every task: `composer validate --strict`,
  `vendor/bin/php-cs-fixer fix --dry-run`, `vendor/bin/phpstan analyse`,
  `vendor/bin/deptrac --config-file=deptrac.yaml`,
  `php tools/api-compat/api-compat.php check`, `composer lint:length`,
  `vendor/bin/codecept run Unit,Conformance,Integration,Concurrency`.
- Coverage is measured only by the repository pipeline configuration over `src`;
  report the ratio produced by that same command every time (baseline today:
  **98.02% lines**). The ≥95% bar is a standing regression floor, not a target to chase.
- Behavior-preserving passes must show byte-identical observable outputs wherever the
  task touches user-visible strings or serialized structures (see T1.3, T3.2).
- Public-API changes require an ADR touch *in the same commit* (AGENTS change
  discipline); additive-only surface changes still refresh the api-compat baseline if
  `tools/api-compat/config.json` scopes demand it.
- One coherent change per commit; do not refactor unrelated code opportunistically.

---

## Phase 1 — Mechanical normalization (behavior-free)

> **Status: Pending**
> Closes the raw material behind REVIEW R-1 (imports), R-3 (ordering), R-12 (fixture
> constants). Purely textual; every diff must be reproducible by a scanner.

### T1.1 — Member-declaration-order normalization (R-3)
- **Goal:** every class/interface/trait declares members in the required order:
  trait `use` → constants → properties/hooks → constructor → factory/named-constructor
  statics → instance methods → other statics; public before protected/private within a
  method section.
- **Files (17 order sites + 7 visibility sites from REVIEW §8 R-3):**
  - Constant after property: `src/Application/Publication/ErrorLogHealthReporter.php:30`.
  - Property after constructor: `SeveritySamplePolicy.php:28`,
    `ExecutionKindSamplePolicy.php:38`, `ExecutionKindFilter.php:38`,
    `ObservationSeverityFilter.php:28`, `SDK/Decorator/ExecutedCallable.php:42`,
    `Behavior/Domain/BoundedKeyGuard.php:31`.
  - Factory/static ordering (private static helpers ahead of public factories):
    `Domain/Observation/Observation.php`, `Domain/Measurement/Measurement.php`,
    `Application/Publication/BridgeConfigBuilder` (Adapter/OpenTelemetry),
    `Adapter/OpenTelemetry/TelemetryPublisher.php`, `Adapter/CloudEvents/CloudEvent.php`,
    `Domain/Shared/SecurityLimits.php`, `Domain/Context/DefaultContextRedactionPolicy.php`,
    `Domain/Context/ContextEntry.php`, `Domain/Shared/Instant.php`.
  - Public-after-more-private within a section: `AttributeValidator.php:66`,
    `Propagation/CompositeContextStorage.php:46`, `Swoole/CoroutineContextStorage.php:52`,
    `Fiber/FiberContextStorage.php:54`, `Measurement.php:96`,
    `ContextValueValidator.php:72`, `SecurityLimits.php:115`.
- **Steps:**
  1. Move members; no signature, body, or visibility changes.
  2. Where moving a private static helper below factories changes call graph order, rely
     on PHP hoisting — assert no side effects at load time (none exist: helpers are pure).
- **Verify:** rerun the token-order audit used in the review (394 types) → 0 violations;
  full gate chain green; `git diff --stat` limited to the listed files.
- **Risk/BC:** none (declaration order is not observable).

### T1.2 — Test-suite import pass (R-1)
- **Goal:** zero leading-backslash FQCN references on executable lines outside the two
  documented Swoole runtime probes.
- **Files:** ~100+ sites under `tests/**` flagged by the review scan — heaviest:
  `tests/Unit/Application/Runner/ResilienceTest.php` (113, 147–177, 332, 399…),
  `tests/Unit/Application/Runner/DefaultActivityRunnerTest.php:34-39`,
  `tests/Conformance/RunItPortConformanceTest.php:130`,
  `tests/Unit/Application/Security/TenantIsolationTest.php:141,150`,
  `tests/Unit/Adapter/Swoole/CoroutineContextStorageTest.php:84`,
  `tests/Unit/Domain/Measurement/MeasurementTest.php`, OTel/Psr/CloudEvents mapper tests.
- **Steps:**
  1. Scripted pass: collect referenced classes per file, insert alphabetically sorted
     `use` statements, shorten references; run `composer cs:fix` to normalize.
  2. Keep the two intentional `\Swoole\Coroutine::class` probes in
     `src/Adapter/Swoole/SwooleRuntimeDetector.php:15-16` and
     `SwooleCoroutineIdProvider.php:14-15` as-is, but add a one-line comment marking them
     as optional-extension probes (`class_exists` must not autoload a missing extension).
- **Verify:** rerun the review's un-imported-FQCN scan excluding the probe allowlist → 0;
  pipeline suite green; test counts unchanged (872).
- **Risk/BC:** none (test-only).

### T1.3 — Fixture constant hoist (R-12)
- **Goal:** single source for the fixture execution identity.
- **Files:** `src/Conformance/Fixture/RunItPortFixture.php` (`'exec-0001'` ×5).
- **Steps:** add `private const string FIXED_EXECUTION_ID = 'exec-0001';` next to
  `FIXED_INSTANT_NANOS`; reference everywhere.
- **Verify:** conformance suite green; normalized outputs byte-identical
  (`RunItPortConformanceTest` asserts artifact identities already).
- **Risk/BC:** none (fixture is @api but construction output unchanged).

---

## Phase 2 — Guards: make the categories non-regrowable

> **Status: Pending**
> Converts Phase 1 results into machine enforcement using the repository's established
> custom-PHPStan-rule pattern (`tools/phpstan/runit-rules`). Also settles the tooling-
> scope question once (R-5, R-6, R-13).

### T2.1 — `MemberOrderRule` (guards T1.1)
- **Files:** `tools/phpstan/runit-rules/src/Rule/MemberOrderRule.php` (new),
  `tools/phpstan/runit-rules/extension.neon` (register with `srcRoot` like siblings),
  `docs/QA.md` ("RunIt-authored rules" table row).
- **Steps:**
  1. Node rule over `Stmt\ClassLike`; rank members exactly as T1.1's convention;
     flag first deviation per class with identifier `runit.memberOrder`.
  2. Encode public-before-protected/private within contiguous same-category runs.
  3. Allowlist: none initially; add `exemptFiles` only if a genuine false positive appears.
- **Verify:** phpstan green on the normalized tree; deliberately re-break one file in a
  scratch build to prove the rule fires, then revert.
- **Risk/BC:** none (dev dependency only).

### T2.2 — Leading-backslash reference guard (guards T1.2)
- **Files:** new `ForbidLeadingBackslashReferenceRule` (or extension of the existing
  ruleset) registered for `src` **and** `tests` roots; QA.md rule-table row; allowlist
  entries for the two Swoole probe files.
- **Steps:** flag `Name\FullyQualified` resolutions on executable lines whose file lacks a
  matching `use` import; permit `namespace`-relative global functions (`strlen`, …) via
  configuration toggle.
- **Verify:** green after T1.2; scratch-test firing as in T2.1.
- **Risk/BC:** none.

### T2.3 — Tooling scope resolution (R-5, R-6, R-13) — decision + minimal work
- **Goal:** one recorded answer to "do library disciplines bind first-party tools?"
- **Recommended resolution (default unless overruled):**
  1. Extend `composer lint:length` scan roots to include `tools/**` (excluding vendored
     stubs); wrap the ~10 offending lines in `ApiCompat.php` and the five rule files.
  2. Convert the three `else`/`elseif` sites in `ApiCompat.php` (:186, :563, :749) and the
     two in `ForbidReflectionOnHotPathRule` to early-return style; then point
     `ForbidElseRule` at the tools root as well.
  3. Keep `AbstractSrcRule` abstract **but** record it as the single sanctioned exception
     in `docs/QA.md` (tooling base class, never shipped) — converting to a trait buys
     nothing here; revisit only if a second base emerges.
- **Alternative (if widening scopes proves noisy):** keep tools out of scope and write the
  exemption explicitly into QA.md's coding-style section; both outcomes are acceptable,
  silence is not.
- **Verify:** gates extended accordingly and green; QA.md documents the decision.
- **Risk/BC:** none.

---

## Phase 3 — Structural decisions: interface backing & vocabulary homes

> **Status: Pending**
> Resolves REVIEW R-2 and R-7/R-11. Requires an ADR amendment because AGENTS'
> "every concrete class implements an appropriate interface" needs a precise, auditable
> reading.

### T3.1 — Interface backing: split the rule, then close the gaps (R-2)
- **Decision artifact:** amend `docs/adr/ADR-015-final-interface-backed-classes.md`
  (new ADR if numbering prefers append-only) defining two lawful categories:
  1. **Seam-backed** — anything consumed across a boundary MUST implement an interface.
  2. **Private leaf** — final adapter-private value/config/map/log-helper types with no
     external consumer MAY be exempt, provided they are `final readonly` (or `final`) and
     listed in the rule's `exemptFiles`.
- **Files/steps:**
  1. Classify the 24 unbacked classes (list in REVIEW §8 R-2). Promote to seam-backed
     where a consumer exists outside their own file/namespace:
     - PSR loggers: `ExecutionAwareLogger`, `ObservationRecordingLogger` → introduce
       `Psr\Log\Contract\*Interface` seams (consumers: middleware, bridge configs).
     - Lookup maps: `SeverityMap`, `SpanKindMap`, `SpanStatusMap` → `*MapInterface` in
       `Adapter/OpenTelemetry/Contract` (mappers accept them injected today).
     - Health reporter: `FallbackErrorLogReporter` → implement the existing
       internal health-reporter contract if compatible, else its own narrow interface.
     - `InstrumentCache` → keep private leaf (single-consumer cache).
  2. Everything classified private-leaf goes into `RequireInterfaceRule`'s `exemptFiles`
     with a one-word reason comment; the rule keeps enforcing everything else.
  3. Builders (`BridgeConfigBuilder`, `CloudEventsBridgeConfigBuilder`) stay unbacked:
     builders are creation edges by definition (record the reasoning in the ADR).
- **Verify:** `RequireInterfaceRule` green with the exemption list; api-compat clean
  (additive interfaces); DepTrac unaffected; pipeline green.
- **Risk/BC:** additive interfaces only; baseline refresh if the compat config demands.

### T3.2 — Vocabulary consolidation for recurring literals (R-7)
- **Goal:** every repeated normalize/event key resolves to a named constant home.
- **Files/steps:**
  1. Audit strays against existing homes and adopt them: `ArtifactField`,
     `AttributeKeys`, `CloudEventExtensionKey` (mappers in
     `Adapter/OpenTelemetry/Logs`, `Adapter/Psr/Log/ObservationLoggerPublisher.php`,
     `Adapter/CloudEvents/*`).
  2. New homes where none exists:
     `JsonSnapshotSerializer::JSON_CONTENT_TYPE = 'application/json'` (+ reuse in
     `DefaultCloudEventToOccurrenceMapper`, `OccurrenceRecorderInterface` docblock site);
     `DeterministicHashCalculator::ALGORITHM_SHA256 = 'sha256'` (shared by
     `HmacSnapshotSigner`, `ConfigurableHashCalculator` defaults);
     `'specversion'` → constant on `Adapter/CloudEvents/CloudEvent`.
  3. Status words (`'failed'`, `'cancelled'`, `'timed_out'`) already have enum backs —
     replace remaining string literals in `SpanStatusMap`/span mappers with enum-derived
     values (`->value`).
- **Verify:** magic-literal frequency rescan shows no ≥3-occurrence key strings outside
  their constant homes; byte-identical mapper outputs (existing mapper tests assert
  payloads; add snapshot assertions if any gap).
- **Risk/BC:** none observable (constants carry identical values).

### T3.3 — Publisher-stack wrapping authority (R-11)
- **Goal:** one owner for redact/sample decoration.
- **Files:** `src/Application/Publication/PublicationPipeline.php` (build()),
  `src/Application/Factory/PublisherStackFactory.php`.
- **Steps:** `PublicationPipeline::build()` delegates redact/sample wrapping to an
  injected `PublisherStackFactoryInterface` (constructor param with default
  `new PublisherStackFactory()`), removing the duplicated two branches.
- **Verify:** publication suites (BasicPublisherTest, RedactingPublisherTest,
  SamplingTest, FailureHandlingTest) green unchanged; builder spy seam tests
  (`SpyPublisherStackFactory`) still intercept composition.
- **Risk/BC:** constructor param is additive-with-default; api-compat clean.

---

## Phase 4 — Mutation testing: enforce, don't admire

> **Status: Pending**
> Closes REVIEW R-4. Infection runs locally since Phase 1 of the prior plan; what is
> missing is a CI-owned baseline and ratcheted floors.

### T4.1 — CI mutation job
- **Files:** `.github/workflows/quality.yml` (or dedicated `mutation.yml`),
  `docs/QA.md` (mutation section update).
- **Steps:**
  1. Job runs `composer mutation:p0` (critical-branching profile) on PRs touching `src/**`;
     weekly/full `composer mutation` schedule for the whole profile.
  2. Publish the MSI summary as a job artifact (`var/infection.summary.json`); fail the
     job only on floor breaches (floors arrive in T4.2), never on timeout noise — set
     generous job-level timeout and `--threads=max`.
  3. Record baseline MSI from the first successful full run into QA.md's M13.5 table.
- **Verify:** workflow green on a clean branch; summary artifact attached.
- **Risk/BC:** CI-only.

### T4.2 — Ratchet the floors
- **Files:** `infection.json5`, `infection.s1s6.json5` (`minMsi`/`minCoveredMsi`),
  `docs/QA.md` provisional-floor paragraph (replace with measured table).
- **Steps:** set both floors to `(CI baseline − ε)` with ε≈5 points; comment linking the
  QA.md baseline table; delete the "provisional 0" commentary.
- **Verify:** CI mutation job fails when floors lowered artificially (scratch proof), then
  restored.
- **Risk/BC:** none.

### T4.3 — Conditional: covering-test selection (time-boxed spike)
- **Trigger:** only if CI reproduces the local symptom (full Unit suite per mutant).
- **Steps:** spike a PHPUnit-driven mutant runner (Infection's phpunit integration)
  behind a feature branch; compare wall-clock and kill rates on one hot directory
  (`src/Application/Publication`). Adopt only if per-mutant wall-clock drops >5× without
  losing kills; otherwise document the outcome in QA.md and keep Codeception adapter.
- **Verify:** written comparison in the PR description; no permanent config churn on a
  negative result.

---

## Phase 5 — Opportunistic modernization (each task independently skippable)

> **Status: Pending**
> REVIEW R-8/R-9/R-10/R-14. None may regress hot-path predictability; AGENTS' "do not use
> a feature merely because it exists" governs.

### T5.1 — Property hooks pilot (R-8)
- **Candidates (pick ≤3):** derived projections on value objects — e.g. `Duration::seconds`
  as a virtual `get` hook over nanoseconds; `Snapshot::payloadSize()` if telemetry needs
  it; `ExecutionContext::isExpired(clock)` stays a method (takes args → wrong shape).
- **Steps:** convert candidates; keep native getter names identical; add `@api` stability
  notes; measure nothing (no hot-path claim without benchmarks).
- **Verify:** domain unit suites green; api-compat clean (hooks preserve call syntax).
- **Risk/BC:** low; hooks are call-compatible with methods for readers.

### T5.2 — Lazy-object pilot for optional infrastructure (R-9)
- **Candidate:** `ExecutionScope`'s snapshot store accessor — materialize via
  `ReflectionClass::newLazyGhost()` when unset instead of eager branch.
- **Steps:** implement behind the existing accessor; add Fiber/persistent-worker leak test
  asserting laziness does not retain state across executions; benchmark scope construction
  before/after (AGENTS requires measurement claims).
- **Verify:** concurrency suite green; benchmark delta recorded in PR; revert if slower.
- **Risk/BC:** none observable.

### T5.3 — First-class callable sweep (R-10)
- **Steps:** mechanical replacement of trivial closures (`fn ($x) => $this->handle($x)`)
  with first-class callable syntax where argument lists match exactly; cs-fixer run.
- **Verify:** pipeline green; diff purely syntactic.
- **Risk/BC:** none.

### T5.4 — Strict-DIP default factories (R-14)
- **Goal:** remove residual `new Default*()` materialization from application-service
  bodies (`DefaultExecutionRunner` bootstrap, `ExecutionScope` accessors).
- **Steps:** add optional factory parameters defaulting to `null` and resolving to the
  current concrete defaults inside named constructors only; services receive ready
  collaborators. Additive parameters only; no signature breaks (api-compat watch).
- **Verify:** pipeline + builder seam tests green; api-compat clean.
- **Risk/BC:** additive-with-default; document in UPGRADING if constructors are public API
  (they are @api) — additive trailing params are BC-safe.

---

## Milestone ordering & exit criteria

| Phase | Milestone | Exit criterion |
|---|---|---|
| 1 | Mechanical normalization | Order audit 0 violations; FQCN scan 0 (probe allowlist only); fixture constants single-source |
| 2 | Guards installed | `MemberOrderRule` + backslash guard fire on induced violations; tooling scope decision recorded in QA.md |
| 3 | Structure settled | ADR amended; `RequireInterfaceRule` green with explicit exemption list; literal-frequency rescan clean; single wrapping authority |
| 4 | Mutation enforced | CI job green; baseline recorded in QA.md; floors = baseline−ε and provably enforced |
| 5 | Modernization (optional) | Each landed task individually benchmarked/gated; skipped tasks remain listed here without debt |

Phases 1–2 must land in order (normalize, then freeze). Phase 3 can start after Phase 1
(different files). Phase 4 is independent and encouraged to start immediately — it is the
longest-pole because it depends on CI capacity. Phase 5 tasks have no ordering
constraints and may be dropped without consequence.

## Out of scope (recorded, not planned)

- Any new product feature, default change, or contract semantic — none were found in
  REVIEW.md; this cycle is completion + enforcement + polish only.
- Replacing the Codeception adapter for tests generally (only the conditional T4.3 spike).
- Introducing DI containers, attribute-based autowiring, or framework bindings.
- Renaming public identifiers for taste; vocabulary renames would violate BC discipline.
- Performance rewrites without a measured regression (benchmarks gate any such work).
