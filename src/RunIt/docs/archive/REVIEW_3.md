# RunIt — In-Depth Code Review

- **Subject:** `it-pack/src/RunIt` (current working tree)
- **Date:** 2026-08-23
- **Reviewer:** autonomous review (ox-alpha)
- **Scope:** features, architecture, maintenance, performance, maintainability,
  security, DX, SOLID, DRY, hexagonal architecture (ports/adapters/factories),
  modern PHP leverage (fibers, weak maps/refs, lazy objects, property hooks,
  traits, readonly classes), interface coverage, complexity, control-flow and
  magic-value hygiene.
- **Method:** repo-wide static scans across all `src/` (381 PHP files: 212
  concrete classes, 145 interfaces, 14 enums, 10 traits) + deep reading of every
  layer via focused passes + execution of quality gates in this environment
  (PHP 8.5.8). Prior findings in `docs/archive/REVIEW_2.md` were re-verified.
  No source was modified.

---

## 1. Conceptual review checklist

The review assessed, at a conceptual (not line-level) level:

1. **Architectural integrity** of the hexagonal boundary — whether ports,
   adapters, and factories hold under DepTrac and custom PHPStan rules, and
   whether vendor neutrality survives inspection.
2. **SOLID and DRY discipline** — interface backing of every concrete class,
   duplication across value objects, validators, and recorders.
3. **Modern PHP leverage** — readonly classes, fibers, weak maps, lazy objects,
   property hooks/virtual properties, and traits — including places where the
   project consciously declined a feature and documented why.
4. **Control-flow, complexity, and magic-value hygiene** — guard-clause style,
   complexity gates, centralized vocabulary constants.
5. **Security and performance posture** — redaction ordering, bounded buffers,
   recursion guards, hot-path costs, exception preservation guarantees.
6. **Maintenance and developer experience** — quality-gate health, builder ergonomics,
   conformance tooling, documentation coherence, test-suite shape.

---

## 2. Summary scorecard

| Dimension | Verdict | Evidence |
|---|---|---|
| Hexagonal architecture / ports & adapters | ✅ Strong | DepTrac **0 violations** (2293 allowed edges); zero core→adapter imports |
| Ports completeness | ✅ Strong | All 13 ports implemented *and* consumed; no dead ports |
| SOLID | ✅ Strong | SRP/OCP/ISP/DIP visible; 145 small interfaces; decorator composition |
| DRY | △ Adequate | Behaviors good; tripled key regex, twin validators, ×8/×5/×10 intra-class copy-paste |
| Interface coverage | △ Adequate | **192/212** explicit-backed; ~15 tracked exemptions (`docs/QA.md`, extension.neon); 2 transitively backed |
| Control flow / complexity | ✅ Strong | **0** `else`/`elseif`/`switch`; `ForbidElseRule` + `ComplexityGuardRule` (19) active |
| Magic strings / numbers | △ Adequate | Context keys centralized; but ~60 field-name literals bypass existing `ArtifactField` constants |
| Modern PHP (readonly/enum/fiber/weakmap/trait) | ✅ Strong | 112 `readonly` classes, 14 enums, WeakMap-per-Fiber isolation, 10 focused traits |
| Modern PHP (lazy objects / property hooks) | △ Conscious gap | **0** lazy objects; **0** hooks — hooks declined *with documented rationale*; lazy remains an open opportunity |
| Domain semantics | ⚠ Two defects | Terminal invariant not structural (`Execution::complete()` unguarded); two dead exception markers |
| Security | ✅ Strong | Deny-by-default redaction, bounds, recursion guards; two leak paths found (health reporter, unbounded caches) |
| Performance | ✅ Strong overall | Monotonic-clock rule enforced; hot-path eager JSON sizing + direct `hrtime` in buffer noted |
| Developer experience | ✅ Strong | Immutable 17-seam builder; framework-neutral conformance kit with runnable example |
| Quality gates (validate/cs/architecture/api/tests) | ✅ Pass | cs 0/512; deptrac 0; api clean; Unit 692 ✔; Conformance+Integration 12 ✔ |
| Quality gate (phpstan `analyse`) | ✗ Fails | **15 errors**, all `shipmonk.deadMethod` on `SecurityLimits::with*` — one root cause |

Legend: ✅ strong · △ adequate · ✗ gap · ⚠ defect.

---

## 3. Detailed findings

### 3.1 Architecture & hexagonal boundaries

- **Dependency direction holds.** DepTrac reports **0 violations** across
  **2293** allowed edges; the custom `ForbidDomainImportsAdaptersRule` backs it
  at the call level. Greps confirm zero `Adapter\` imports from Domain,
  Application, or SDK, and zero upward imports from adapters to SDK/Application.
- **Ports are complete.** All 13 ports in `Contract/Port/` have at least one
  implementation and at least one consumer — no dead ports. Adapters keep their
  own SPI contracts locally (`Adapter/*/Contract/`), which keeps core ports
  vendor-free; core contracts contain zero external-vendor references.
- **Factories are purposeful.** `Application/Factory/PublisherStackFactory`
  composes redaction before sampling before the base publisher — the
  security-critical ordering is centralized, not scattered.
  `ExecutionFactory`/`ExecutionSnapshotFactory` assemble domain artifacts.
  Heavy assembly remains concentrated in `RunItBuilder` +
  `DefaultExecutionRunner` (composition root), an accepted pattern.
- **One gate blind spot.** `src/Behavior/Application/**`
  (`InstantClockBehavior`, `DelegatesPublisherLifecycleBehavior`) is imported
  by 12 Application call sites but matches **no DepTrac layer**
  (`deptrac.yaml` covers only `Behavior/Domain`). Edges touching these traits
  are architecturally unpoliced; a vendor import there would go unnoticed.
  The remaining "uncovered" artifacts reported by DepTrac are line-level
  references to vendor types inside adapters — benign by design.
- **Stale rule config.** `RequireInterfaceRule` exempts
  `PublicationPipeline` and `SnapshotIntegrityVerifier`, but both implement
  interfaces today; `HttpContextExtractorInterface.php` (an interface) sits in
  an exemption list meant for concrete classes. Harmless but misleading.

### 3.2 SOLID

- **SRP:** Classes remain small and single-purpose; no mixed responsibilities
  observed in any layer. Longest methods stay ≤ ~61 lines; nesting never
  exceeds depth 3.
- **OCP:** Publication is decorator-composed
  (guard → fail-safe → filter → redact → sample → buffer → sink);
  new behavior adds a decorator, not a modification.
- **ISP/DIP:** 145 fine-grained interfaces; consumers depend on contracts.
  The tracked exemption list (config VOs, maps, attributes) is the only
  deviation and is documented in `docs/QA.md`.

### 3.3 DRY

Prior-review follow-ups partially landed; duplication persists in three forms:

1. **Triplated key pattern.** `/^[A-Za-z0-9._-]++\z/` exists three times:
   `Behavior/Domain/StringValueValidator.php:44`,
   `Domain/Context/ContextValueValidator.php:31`,
   `Behavior/Domain/AttributeValidator.php:33`.
2. **Twin validators.** `ContextValueValidator::assertValidKey/assertSupportedValueRecursive`
   and `AttributeValidator::assertValidKey/assertSupportedValue`
   are near-line-for-line equivalents differing only in thrown exception type.
3. **Intra-class copy-paste.** `Observation` has 8 near-identical severity
   constructors (`Observation.php:48–246`); `Measurement` has 5
   (`Measurement.php:60–179`); `SecurityLimits` has 10 ~1000-char `with*()`
   clones (`SecurityLimits.php:79–154`). A shared private factory would
   collapse each family.
4. Additionally, `ContextKey`, `AttributeKey`, and `ContextSource` hand-copy
   the VO skeleton that `ValidatedStringValueBehavior` provides because the
   trait's validation hook does not fit key-specific rules.

### 3.4 Interface coverage

- Repo-wide scan: **212 concrete classes, 20 without an explicit interface**
  (~90.6% backed). Of the 20: ~15 are documented exemptions in
  `tools/phpstan/runit-rules/extension.neon` ("tracked follow-up" per
  `docs/QA.md`); `ExecutionAwareLogger`/`ObservationRecordingLogger` are
  transitively backed via PSR-3 `AbstractLogger`; `ExecutedAs` is a PHP
  attribute; the remainder are immutable config/map VOs inside adapters.
- The convention is enforced by a custom rule rather than aspiration, and the
  previous review's genuine gap (`HttpExecutionContext`) is fixed — it now
  implements `HttpExecutionContextInterface`.
- Residual concern: exemptions are growing as a permanent category rather than
  shrinking toward zero. Each entry should carry its follow-up note in
  `docs/QA.md`; several currently do not.

### 3.5 Control flow, complexity & magic values

- **`else`/`elseif`: 0** in executable code (one doc-comment mention).
  `switch`: 0. Guard clauses + `match` everywhere; `ForbidElseRule` enforces.
- **Complexity gate exists.** `ComplexityGuardRule` (threshold 19) closes the
  prior review's P3 recommendation T3.2. No current method exceeds it;
  worst offenders are `runExecution()` (~61 lines) and `build()` (57).
- **Magic values — mixed picture.** Context/correlation keys are centralized
  (`Contract/Shared/CorrelationKeys`), and no occurrence-type literals exist in
  domain logic. However:
  - `ArtifactField` constants exist yet most `normalize()`/`toArray()`
    projections still use string literals (`'name'`, `'status'`,
    `'started_at'`, …) — ~60 sites across `Execution`, `Activity`,
    `Occurrence`, `Snapshot`, `Observation`, `Measurement`, `ContextEntry`,
    `ThrowableDescription`, and both definitions — sometimes mixing constants
    and literals in the same array.
  - `'application/json'` default duplicated 3× (`Occurrence.php:67`,
    `Snapshot.php:70`, `ExecutionSnapshot.php:47`).
  - Remaining literals worth constants: `'execution.terminal'` snapshot type
    (`ExecutionSnapshotFactory.php:46`), schema-version literal `1` (same file),
    drop-reason strings in `BoundedArtifactPublisher.php:59–77`, priority
    literals `0/1` in `BufferedArtifactPublisher.php:135–146`, diagnostic code
    `'execution.failure'` (`DefaultExecutionRunner.php:228`).
  - **Rule violation:** exceptions must implement RunIt markers, but
    `SamplingArtifactPublisher.php:44` throws generic `\RuntimeException`,
    `Measurement.php:184` throws bare `\InvalidArgumentException`, and
    `SecurityLimits.php:71` likewise escapes the marker system.

### 3.6 Modern PHP leverage

- **`readonly`: 112 of 212** concrete classes. Domain audit found 7 stateless
  or config-holding services that could be `final readonly` but are not
  (`DeterministicHashCalculator`, `DefaultContextPropagationPolicy`,
  `DefaultContextRedactionPolicy`, `DefaultContextSerializationPolicy`,
  `DefaultThrowableMapper`, `ContextValueValidator`, `AttributeValidator`) —
  all provably mutation-free after construction. Zero post-construction writes
  exist anywhere in scope.
- **Enums: 14**, used for genuinely closed sets. Nit: `NestedExecutionMode`
  and `ContextCollisionPolicy` implement no contract while all 7 domain enums
  back their interfaces — inconsistent application of the enum-contract pairing.
- **Fibers:** exemplary. `FiberContextStorage` anchors a per-Fiber LIFO stack
  in a `WeakMap`, releases entries when a stack empties (no manual cleanup),
  and validates detach ownership (anchor + top-of-stack). Verified by tests
  asserting completed Fibers are collectable (`WeakReference` assertions in
  `FiberContextStorageTest`). `CoroutineContextStorage` mirrors this for
  Swoole coroutine ids and explicitly handles id reuse.
- **WeakMap:** used where appropriate (context storages, serializer cache,
  metadata-reader cache). `spl_object_hash` is banned by rule.
  `WeakReference` unused — correct, since correctness nowhere depends on it.
- **Lazy objects: 0.** AGENTS.md recommends them for expensive optional
  infrastructure (OTel bridge, serialization caches). Still an open,
  legitimate opportunity — e.g., lazily initializing OTel mappers when their
  provider is null anyway.
- **Property hooks / virtual properties: 0 — but consciously declined.**
  Five files (e.g. `ContextKey.php:29–34`) document why `readonly` properties
  beat virtual hooks (class stays `readonly`, validated once at construction),
  and nearly all getters are interface-mandated, so hooks cannot replace them
  without breaking contracts. This is a reasoned trade-off, not an oversight.

### 3.7 Lifecycle & domain semantics

Two defects deserve attention:

1. **Terminal-state invariant is policy, not structure.**
   `ExecutionLifecycleCoordinator` enforces the transition matrix correctly
   (idempotent re-completion, conflict detection), but the aggregate itself is
   unguarded: `Execution::complete()` (`Execution.php:92–103`) accepts any
   status, so any caller bypassing the coordinator can drive a terminal
   execution to a different outcome. `Activity::complete()` has no reopen
   guard either, and `ExecutionFailure::__construct` is public and accepts
   non-terminal statuses (asymmetric with private `ExecutionSuccess` ctor).
   AGENTS.md phrases this invariant absolutely; defense belongs in the domain.
2. **Dead exception markers.** `LifecycleExceptionInterface` is promised by
   `@throws` on `ExecutionLifecycleCoordinatorInterface` and
   `DefaultExecutionRunner`, yet **zero implementations exist** — the actual
   lifecycle exceptions implement `InvalidValueExceptionInterface`, so
   consumers cannot catch the documented type.
   `UnsupportedOperationExceptionInterface` also has zero implementations.
   Both markers are currently fiction.

Related runtime gaps:

- **Original-exception preservation hole.** In
  `DefaultExecutionRunner::runExecution()`, the failure path calls
  `completeFailure()` *inside* the catch block without inner protection; if
  the completion policy, observation recording, or coordinator transition
  throws, that exception propagates and replaces the original business
  exception — contrary to ADR-008's guarantee.
- **Timeout/cancellation are mapped but never triggered.**
  `DefaultTimeoutMapper` and `DefaultCancellationMapper` are consumed only by
  tests; no application-level enforcement exists. This is acceptable while
  engines delegate to runtimes, but the TIMED_OUT/CANCELLED paths are
  effectively untested end-to-end.

### 3.8 Security

- **Strong defaults confirmed:** deny-by-default redaction (ADR-012) applied
  before sampling/buffer/adapters via `PublisherStackFactory`;
  `BoundedArtifactPublisher` caps artifacts-per-execution and payload bytes;
  `PublicationGuard` prevents recursive publication (ADR-014); throwable
  normalization truncates messages/traces and disables trace capture by
  default; snapshot integrity uses timing-safe `hash_equals`.
- **Leak path 1:** `ErrorLogHealthReporter::report()` writes raw exception
  message + file/line straight to `error_log`, bypassing the redaction
  pipeline. Health reporting is exactly the path recursive-publication
  failures take, so redacted content can re-enter through it.
- **Leak path 2 (retention):** `HeadBasedSamplingPolicy::$decisions`
  (`HeadBasedSamplingPolicy.php:31`) grows one entry per distinct execution
  key and is never evicted — unbounded memory in persistent workers.
  `BoundedArtifactPublisher::$countsPerExecution` has the same shape.
- **Wiring-dependent redaction:** `DefaultExecutionRunner` builds its internal
  stack as Bounded→FailSafe only; redaction reaches the pipeline solely
  because `RunItBuilder` injects the composed stack. Direct construction of
  the runner publishes unredacted. Acceptable for a library, but worth an
  assertion or doc warning.

### 3.9 Performance

- Hot-path discipline holds: reflection confined to wrap-time metadata reading
  (WeakMap-cached), monotonic durations via port, no eager stack traces.
- **Hot-path cost found:** `BoundedArtifactPublisher::publish()` runs
  `strlen(json_encode(...))` on *every* occurrence/snapshot payload purely to
  enforce size limits (`BoundedArtifactPublisher.php:66,75`) — full
  normalization+encoding even when limits are far away. An incremental byte
  estimator (the `ArtifactByteEstimatorInterface` port already exists!) would
  avoid double serialization.
- **Clock-port inconsistency:** `BufferedArtifactPublisher` calls `hrtime(true)`
  directly (4 sites) and busy-waits with `usleep(100)`; it is allowlisted in
  `ForbidDirectWallClockRule`. Deterministic testing of overflow/blocking is
  therefore impossible; routing through `MonotonicClockInterface` (with a
  sleep seam) would restore testability.
- Minor: `ExecutionScope::clearOpenActivity()` is O(n) per completion via
  `array_filter` + `array_values`; negligible at realistic activity depths.

### 3.10 Developer experience & maintainability

- **Builder:** `RunItBuilder` is immutable copy-on-write with 17 typed `with*`
  seams and fail-fast `build()` validation of five required ports.
  Two ergonomic gaps: there is **no `withSecurityLimits()`** despite
  `SecurityLimits` documenting "Consumers wire a tuned instance through the
  SDK builder", and publication-health callbacks
  (`onPublicationFailure`/`onSnapshotStoreFailure`) are hardcoded `null` with
  no seam. The missing limits seam is also the *root cause of the only
  PHPStan failures* — all 15 `shipmonk.deadMethod` errors point at
  `SecurityLimits::with*` methods nothing can reach.
- **Conformance kit is genuinely framework-neutral:** verifiers depend only on
  port types plus `PortFixtureInterface`; results are plain
  `IteratorAggregate/Countable` objects; a runnable third-party example ships
  at `examples/conformance/third-party-adapter.php`. Dogfooded by the
  Conformance suite itself.
- **Docs & vocab:** concept pages, recipes, integration guides, 22 ADRs, and
  OWL/SKOS vocabularies are present and consistent; code carries 100+
  `@see` vocabulary IRIs aligned with the taxonomy requirement.
- **Suite hygiene:** Unit (692 tests) is healthy and includes failure-injection,
  fiber-isolation, tenant-isolation, and persistent-worker cases. But
  Functional/Acceptance/Concurrency suites are declared and empty, and
  `test:functional` runs nothing — a silent green.
- **Placeholders:** `src/Adapter/Callback/` and `src/SDK/Middleware/` contain
  only `.gitkeep`.

### 3.11 Quality gates executed in this environment

| Gate | Result |
|---|---|
| `composer validate --strict` | PASS |
| `cs:check` | PASS (0 of 512 files fixable) |
| `architecture` (DepTrac) | PASS (0 violations, 2293 allowed, 154 benign uncovered vendor-type refs) |
| `analyse` (PHPStan max + custom rules) | **FAIL — 15 errors**, all `shipmonk.deadMethod` on `SecurityLimits::with*` |
| `api:check` (backward compat) | PASS |
| `test` (Unit + Conformance + Integration) | PASS (692 + 12 tests, 5362 assertions) |
| `mutation`, `benchmark` | Not executed (time-prohibitive here; see Assumptions) |

---

## 4. Prioritized recommendations

### P0 — restore gate health and the exception-preservation guarantee

1. **Add `withSecurityLimits()` (and health-callback seams) to `RunItBuilder`**
   and pass the configured limits into the runner. This honors the
   `SecurityLimits` docblock promise and eliminates all 15 current PHPStan
   errors in one move.
2. **Protect `completeFailure()`**: wrap the failure-recording body in an
   inner try/catch that routes secondary faults to the health path, so the
   original business `Throwable` always wins the catch block (ADR-008).

### P1 — make domain invariants structural and markers real

3. Enforce terminal immutability inside the aggregate:
   `Execution::complete()` rejects non-terminal→terminal transitions from a
   terminal state (or delegates to the coordinator's matrix);
   make `ExecutionFailure::__construct` private/validated to terminal statuses;
   add the missing reopen guard to `Activity::complete()`.
4. Implement `LifecycleExceptionInterface` in
   `ConflictingCompletionException` + `InvalidLifecycleTransitionException`
   (matching the documented `@throws`), and implement or delete
   `UnsupportedOperationExceptionInterface`.
5. Replace the three bare SPL throws (`SamplingArtifactPublisher`,
   `Measurement`, `SecurityLimits`) with marker-carrying exceptions.

### P2 — DRY consolidation, bounds, and hot-path cost

6. Consolidate the tripled key regex into one shared constant; merge
   `AttributeValidator` into `ContextValueValidator` (parameterize the
   exception); collapse `Observation`/`Measurement` constructor families into
   shared private factories.
7. Finish `ArtifactField` adoption in all `normalize()`/`toArray()` projections.
8. Bound `HeadBasedSamplingPolicy::$decisions` and
   `BoundedArtifactPublisher::$countsPerExecution` (size cap or per-execution
   eviction) for persistent-worker safety; route health-reporter output
   through redaction-aware formatting.
9. Use `ArtifactByteEstimatorInterface` instead of eager `json_encode` sizing
   in `BoundedArtifactPublisher`; route `BufferedArtifactPublisher` time
   through the clock port and remove its wall-clock allowlist entry.

### P3 — polish and hygiene

10. Convert the seven mutation-free services to `final readonly`; pair the two
    contract enums with interfaces or document why not.
11. Clean stale entries from `RequireInterfaceRule` exemptions; add
    `src/Behavior/Application` to a DepTrac layer (e.g. a "Shared Behavior"
    layer constrained like Common).
12. Centralize `'application/json'`, snapshot-type, schema-version, priority,
    and drop-reason literals as named constants.
13. Populate or retire the empty Functional/Acceptance/Concurrency suites and
    the `Adapter/Callback` + `SDK/Middleware` placeholders; consider a CI job
    exercising real Swoole/Fiber runtimes.

---

## 5. Blockers & risks

- **`composer analyse` is red** for a single, easily fixed root cause (P0-1).
  Any "quality" claim should be treated as blocked until it lands.
- **Exception-preservation hole (§3.7)** is a correctness risk in failure
  paths precisely where consumers rely on ADR-008 semantics.
- Mutation and benchmark suites were not run in this session; recent
  lifecycle/security changes are therefore unverified against mutation
  coverage in this environment.

---

## 6. Follow-up on `docs/archive/REVIEW_2.md`

| Prior finding | Status |
|---|---|
| P0-1 `HttpExecutionContext` lacks interface | **Fixed** (`HttpExecutionContextInterface`) |
| P0-2 Redactor iterable typing | **Fixed** (0 `missingType.iterableValue` errors) |
| P1-4 sweep `deadMethod` notices | **Not fixed** — grew into the only analyse failures |
| P2-5 standardize VO validation on trait | Partially — behaviors expanded, twin validators remain |
| P2-6 introduce lazy objects | Not addressed (opportunity stands) |
| P2-7 decompose runner assembly | Not addressed (accepted composition root) |
| P2-8 property hooks | Consciously declined, rationale documented in 5 files |
| P3-9 centralize default redaction policy | Fixed (single `DefaultContextRedactionPolicy`) |
| P3-10 measured complexity guard | **Fixed** (`ComplexityGuardRule`, threshold 19) |
| P3-11 named builders for wide VOs | Not addressed; signatures unchanged (≤14 params) |
| P3-12 verify recorder `$clock` false positives | Superseded — current tree reports no such errors |

---

## 7. Assumptions

- Reviewed the **current working tree** (not necessarily a tagged release);
  prior reviews live in `docs/archive/`.
- Gates executed under **PHP 8.5.8** while the package targets `^8.4`;
  no version-specific divergence was observed, but 8.4-runtime parity was not
  separately verified.
- Infection/mutation and phpbench were **not** executed (session time budget);
  their status is unknown here and prior sessions also could not run Infection.
- Structural claims (counts of classes/interfaces/readonly/enums/else/hook
  usage) come from repo-wide scripted scans; semantic claims cite specific
  files and lines read during this review. Not all 381 files were read
  line-by-line; representative coverage per layer was prioritized.
