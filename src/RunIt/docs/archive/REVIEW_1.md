# RunIt — In-Depth Review

**Date:** 2026-08-21
**Scope:** `src/RunIt/src` (358 PHP files, ~18.7k LOC) and `tests` (133 files).
**Target:** PHP `^8.4`, hexagonal ports-and-adapters, vendor-neutral execution model.
**Method:** Four parallel deep-dive analyses (architecture/interfaces/factories, SOLID/DRY/else/magic, PHP 8.4 features, security/performance/DX/maintainability), followed by direct source verification of every P0/security claim below.

> Findings are marked **[VERIFIED]** when confirmed by reading the source in this session, or **[DERIVED]** when established by sub-analysis and recommended for a validating test before action. Recommendations that change public contracts are flagged.

---

## 1. Executive summary

RunIt is architecturally mature: clean hexagonal layering (DepTrac **0 violations**), ~110 interfaces, pervasive `final`/`readonly`, PHPStan at level max with `ignoreErrors: []`, a conformance test harness, and a domain vocabulary backed by RDF. The structural and stylistic rules the brief asked about (no `else`, no magic strings, SOLID, DRY, interface-per-class) are *mostly* honored and the codebase already contains the correct patterns to fix its own gaps.

The real risk is **not** style — it is a small number of **correctness/security defects that are reachable from the public API** and currently untested:

| # | Severity | Issue | Location |
|---|----------|-------|----------|
| S1 | **CRITICAL** | Secret context is exported verbatim in normalized artifacts (redaction policy exists but is never wired in) | `Execution.php:142`, `ExecutionDefinition.php:90`, `DefaultContextRedactionPolicy.php` (no caller) |
| S2 | **CRITICAL** | `JsonSnapshotSerializer` recurses into `JsonSerializable` objects **without incrementing depth** → stack overflow / SIGSEGV on self-reference | `JsonSnapshotSerializer.php:89` |
| S3 | **CRITICAL** | `ContextValueValidator` recurses with **no depth bound** → SIGSEGV from public `ContextEntry::internal()` | `ContextValueValidator.php:76-78` |
| S4 | **HIGH** | `ExecutionSnapshot::create` skips recursive-primitive payload validation → objects survive serialization | `ExecutionSnapshot.php:22-54` |
| S5 | **HIGH** | `DefaultExecutedAsMetadataReader` keys its cache by `spl_object_hash` (recycled) with no bound → metadata misattribution + unbounded growth in persistent workers | `DefaultExecutedAsMetadataReader.php:28,86` |
| S6 | **HIGH** | Redaction can silently drop entire artifacts (adapters dispatch on concrete type, `RedactedArtifact` implements only `ExecutionArtifactInterface`) | `RedactedArtifact.php`, `RedactingArtifactPublisher.php:33` |

Lower-severity but high-leverage items: lifecycle matrix encoded as raw strings (S7), `SecurityLimits::with()` silently no-ops (S8), ~500 lines of mechanical duplication for which the repo already holds the fix (D1–D4), and several `final`/interface-coverage gaps (I1–I4).

---

## 2. Strengths (preserve)

- **Hexagonal boundaries hold mechanically.** DepTrac reports 0 violations; Domain imports no Application/SDK/Adapter code.
- **Ports are small and focused.** `src/Contract/Port/*` (11 interfaces) each cover one capability. Adapters implement the corresponding port and keep external libs (PSR, OTel, Swoole) confined to the adapter layer.
- **`final` + `readonly` discipline is strong.** ~52 `readonly` classes; essentially every concrete class is `final`. Domain invariants are enforced by immutable value objects.
- **Fibers are used correctly and isolated.** All `Fiber` usage lives in runtime adapters; `WeakMap` is used in exactly the right place (`FiberContextStorage` keyed by Fiber object).
- **Serialization is custom (`NormalizableInterface`), never native `serialize()` of value objects** — property-hook adoption is therefore safe.
- **The `else`/`elseif` rule is essentially satisfied.** Only ~7 real occurrences across 18.7k LOC, zero `switch` (all 10 branch tables use `match`).
- **Enum-vs-validated-string decision is deliberate and correct.** Open vocabulary uses `ValidatedStringValueBehavior`; closed internal sets use enums.
- **Quality gates are real:** PHPStan level max, DepTrac, PHP-CS-Fixer, custom phpstan rules, conformance verifiers.

---

## 3. Architecture, ports & adapters

### 3.1 Hexagonal boundaries — [VERIFIED]
DepTrac layering is `Common → Contract → Domain → Application → SDK`, adapters on top. 0 violations.
- **Tension (informational):** `src/Domain/Factory/StringIdentifierFactory.php:10` imports `Contract\Port\IdentifierGeneratorInterface`. AGENTS.md's dependency table lists **Ports only for Application/SDK/Adapters, not Domain** ("Domain → Contracts"). deptrac's Domain layer permits `Port`, so the two sources disagree and deptrac hides it. *Recommendation:* reconcile AGENTS.md and deptrac so this becomes a visible, intentional decision.
- **Adapters depend on `Contract\Application` interfaces** (e.g. `Psr/Http/ExecutionMiddleware.php:7-8`, `Fiber/FiberExecutionEngine.php`). defensible, but document it explicitly so reviewers don't "fix" it.
- **Misplaced concrete:** `HttpExecutionContext` (a `final readonly` DTO) is declared inside `Adapter/Psr/Http/HttpContextExtractorInterface.php:30`. Move to its own file / rename.

### 3.2 Ports/adapters — sound. See §3.1 and §6.

---

## 4. Interface coverage — [DERIVED]

Rule: *every concrete class implements an appropriate interface.* Exceptions (marker exception interfaces) are treated as covered.

**Real service classes missing an interface (violations):**
- `Application/Security/SnapshotIntegrityVerifier.php:23` — substantive `verifyIntegrity()` service, **no `SnapshotIntegrityVerifierInterface`**. (I1)
- `Application/Publication/PublicationPipeline.php:39` — composes the whole pipeline; mark `@internal` or add an interface. (I2)
- `Behavior/Domain/ContextValueValidator.php`, `Behavior/Domain/AttributeValidator.php` — validation behavior, no interface. (I3)

**Acceptable without interfaces:** readonly config DTOs (`ArtifactRedactionConfig`, `BufferConfiguration`, `PublicationRoute`, `*BridgeConfig`), constants-holders (`SecurityLimits`, `AttributeKeys`), PHP attributes (`ExecutedAs`), conformance result DTOs, and all exceptions (marker interface). Consider `@api`/`@internal` to signal intent.

**Test-support inconsistency:** the 9 `Conformance/Verifier/*` classes share no `VerifierInterface`. (I4)

---

## 5. Factories — [DERIVED]

- `RunItBuilder` (immutable `with*(): self` + `build()`) and `BridgeConfigBuilder`/`CloudEventsBridgeConfigBuilder` are clean and consistent.
- **`FactoryInterface` (`Common/Contract/FactoryInterface.php:9`, `build(): object`) is orphaned.** Only `StringIdentifierFactoryInterface` extends it. The other factories use different verbs: `ExecutionFactoryInterface` → `start()`/`create()`, `ExecutionSnapshotFactoryInterface` → `terminal()`, `CloudEventFactoryInterface` → `create()`. *Recommendation:* either have the three extend `FactoryInterface` with covariant `build()`, or rename `FactoryInterface` to `IdentifierFactoryMarkerInterface` so it stops implying a universal vocabulary. (F1)

---

## 6. SOLID

### 6.1 SRP (god constructors / god classes) — [DERIVED]
- `DefaultExecutionRunner.php:66-95` — **23 constructor parameters**, five of them identical `StringIdentifierFactoryInterface` forwarded to `ExecutionScope`. Needs a parameter object (`ArtifactIdentityFactories`).
- `ExecutionScope.php:64-85` — 15 params, four identical factories; also exposes public mutators (`setCurrentActivityId`, `trackOpenActivity`, `clearOpenActivity`) so external code mutates scope state.
- `BufferedArtifactPublisher.php` — 325 lines / 22 methods (buffering, byte accounting, priority, 5 overflow strategies, expiry, flush, shutdown budget, health).
- `RunItBuilder.php` — 416 lines / 22 methods (see D1).
- `DefaultExecutionRunner.php:364-387` calls the 22-arg ctor **positionally with two bare `null`s**. Named args should be mandatory.

### 6.2 OCP — [DERIVED]
- `Adapter/OpenTelemetry/TelemetryPublisher.php:93-128` — six-branch `instanceof` over artifact types; every new artifact type edits this class. Replace with a `class-string`-keyed mapper registry / double dispatch.
- `DefaultExecutionRunner.php:98` hardwires `new FailOpenPolicy()` even though `PublicationPipeline.php:64` already proves the `?? new FailOpenPolicy()` seam is trivial. A consumer cannot choose fail-closed publication through the runner.

### 6.3 DIP — [DERIVED]
- `DefaultExecutionRunner.php:65` types the concrete `FailSafeArtifactPublisher` (not `ArtifactPublisherInterface`); `:97-98` news up `BoundedArtifactPublisher`/`FailSafeArtifactPublisher`/`FailOpenPolicy` internally.
- `DefaultExecutionRunner.php:261-264` does `return $this->engine ?? new SynchronousExecutionEngine();` **per execution** (per-execution allocation on the hot path). Resolve once in the constructor.
- `RunItBuilder.php:342-352` hardwires `DeterministicHashCalculator`/`ExecutionFactory`/`ExecutionLifecycleCoordinator`/`ExecutionSnapshotFactory` with no `withX()` seam, though the interfaces exist.

### 6.4 ISP / consistency — [VERIFIED]
- `ExecutionStatusInterface::label()` vs `ActivityStatusInterface::name()` name the **same concept** differently (`Execution.php:133` uses `label()`, `Activity.php` uses `name()`). This blocks a shared span mapper.
- **Six enums redeclare `name(): string`** (`ObservationSeverity`, `MeasurementKind`, `MeasurementUnit`, `ContextClassification`, `SnapshotClassification`, `ActivityStatus`), shadowing PHP's implicit `->name` unit enum property. `SpanStatusMap::map(string)` and `DefaultMeasurementInstrumentMapper` match raw strings with a `default` arm, so passing `->name` (the implicit property) instead of `->name()` yields a **silently wrong** result (`STATUS_UNSET`, `createCounter`). `ExecutionStatus` avoided this by choosing `label()`. *Recommendation (BC-relevant):* pick one accessor name across status contracts and rename the enum methods to avoid shadowing. (S9)

---

## 7. DRY — [DERIVED]

| ID | Duplication | Fix already in-repo |
|----|-------------|--------------------|
| D1 | `RunItBuilder.php:77-327` — 12 `withX()` methods each re-list 13 ctor args positionally (~250 lines) | `BridgeConfigBuilder.php:49-56` uses `clone $this` withers (3 lines). Adopt it; removes ~230 lines. Also `withMonotonicClock()` is a pure alias of `withClock()`. |
| D2 | 6 decorator publishers × byte-identical `flush()`+`close()` (12 identical bodies) | extract a `DelegatesPublisherLifecycleBehavior` trait into `Behavior/`. |
| D3 | `DefaultExecutionSpanMapper` ≈ `DefaultActivitySpanMapper` (~110 lines each, identical structure, identical `recordThrowable()`) | unify after S9. |
| D4 | 4 recorders × identical `now()` helper; `Instant::of($clock->nowSeconds())` appears 9× | add `MonotonicClockInterface::now(): InstantInterface` or a shared trait. |
| D5 | `ExecutionStatus::isTerminal()` == `ActivityStatus::isTerminal()`; `StringValueValidator` ≈ `StringIdentifierValidator`; key regex `'/^[A-Za-z0-9._-]++\z/'` triplicated; `ContextValueValidator::assertSupportedValue` ≈ `AttributeValidator::assertSupportedValue` | extract shared traits; consolidate regex constant. (Fixing D5 also fixes S3 by sharing one bounded recursive walk.) |
| D6 | `ExecutionLifecycleCoordinator::transition()` re-computes the same compound expression its own `isAllowed()` already encodes | delegate `transition()` → `isAllowed()`. |

---

## 8. Low complexity & no `else`/`elseif` — [VERIFIED (counts)]

Only ~7 real `else`/`elseif` across 18.7k LOC; zero `switch`. Remaining occurrences are trivially removable:
- `SDK/DefaultRunIt.php:64-76` (nested if/else) → guarded early returns.
- `Adapter/CloudEvents/Mapping/DefaultCloudEventToOccurrenceMapper.php:50-56` (if/elseif/else) → `match(true)`.
- `Adapter/Fiber/FiberContextStorage.php:89-93` and `Adapter/Swoole/CoroutineContextStorage.php:91-95` (identical if/else) → guard + return.
- `Application/Publication/BufferedArtifactPublisher.php:219-223` (if/else) → `?->`.

**Verdict:** the rule is well observed; reaching zero is < 1 hour.

---

## 9. Magic strings & numbers — [VERIFIED for S7/S8; DERIVED otherwise]

### 9.1 Magic strings (security-relevant)
- **S7 [VERIFIED]** `ExecutionLifecycleCoordinator.php:34-37` encodes the transition matrix as raw strings (`'created' => ['running','cancelled'], …`) while `ExecutionStatus` sits right there. A typo silently permits/forbids a transition. Rewrite as `ExecutionStatus::Created->value => [ExecutionStatus::Running->value, …]`.
- **S8 [DERIVED]** `SecurityLimits::with(string $name, int $value)` compares the name 10× and **returns an unchanged copy for an unknown name** (silent no-op); the 10 property names are listed 3× (PHPDoc, validation loop, `with()`). Make it type-safe (`withMaxAttributeCount(int)` withers or enum-keyed).
- **Normalized schema is magic strings, and a security control depends on it [VERIFIED].** All six artifacts build payloads from bare literals (`'id'`,`'context'`,`'attributes'`,`'throwable'`,`'data'`,`'payload'`, …). `DefaultArtifactRedactor.php:42,49,56,63` and `ErrorLogHealthReporter.php:23` reach back in by literal. Rename a key and redaction silently no-ops. Introduce an `ArtifactField` enum / `ArtifactKeys` const class (mirror `AttributeKeys`) and use it in `normalize()` **and** the redactor. (S10)
- Severity vocabulary duplicated at 4 sites (`ObservationSeverity` + `DefaultObservationToPsr3LevelMapper`, `SeverityMap`, `ObservationRecordingLogger`, `ObservationRecordingLogger::severityForLevel` is reducible to `ObservationSeverity::tryFrom(...) ?? Debug`). (S11)
- `MeasurementKind` values re-hardcoded in `DefaultMeasurementInstrumentMapper.php:76-81`.
- `CorrelationAttributeKey = 'correlation.id'` duplicated in both CloudEvents mappers (must agree for round-trip). Header defaults (`X-Correlation-Id`, `X-Execution-Id`, `ctx.correlation.id`) duplicated across 3 adapters.
- Builder dependency-name strings don't match property names (`RunItBuilder.php:404,409,414`: `forDependency('sequenceAllocator')` but property is `$allocator`).

### 9.2 Magic numbers / unbounded limits — [VERIFIED for S2/S3]
- **S2/S3 [VERIFIED]** depth bounds: `JsonSnapshotSerializer` does not increment depth for objects (S2); `ContextValueValidator` has no depth parameter at all (S3).
- **S12 [VERIFIED]** the *same four* limits are hardcoded in four competing places: `SecurityLimits` (injectable), `AttributeValidator`, `ContextValueValidator` (no depth → S3), `ExecutionContext::MAX_ENTRIES`. `AttributeValidator` accepts `?SecurityLimits` and falls back to its own consts; `ContextValueValidator` and `ExecutionContext` accept **no** `SecurityLimits`, so tuning it has no effect on context. Standardize on the `SecurityLimits $limits = new SecurityLimits()` default already used by `DefaultActivityRunner`/`DefaultExecutionRunner`/`ExecutionScope`/`JsonSnapshotSerializer`.
- `Duration::NANOS_PER_SECOND` exists but OTel adapters use raw `1_000_000_000` (7 sites); `NANOS_PER_MICROSECOND` ignored in `BufferedArtifactPublisher.php:233,316`.
- `BufferedArtifactPublisher.php:31` `BLOCK_BUDGET_MICROSECONDS = 2000` and `usleep(100)` are hardcoded though `BufferConfiguration` exposes 6 other tunables.
- Priority scale returns bare `0`/`1` (compare at `:211`,`:275`) — use an enum/named consts.
- `json_decode(..., 512, ...)` depth ignores `SecurityLimits::maxSerializationDepth`.
- `ExecutionMiddleware.php:92,102` inline HTTP 500/400 thresholds.

---

## 10. PHP 8.4 feature leverage

### 10.1 Already correct
- `Fiber` isolated to adapters; `WeakMap` used correctly in `FiberContextStorage`; enums for closed sets; `match` idiomatic; custom `NormalizableInterface` serialization (safe for hooks).

### 10.2 Opportunities — [DERIVED]
- **Property hooks for string value objects (highest ROI).** ~17 value objects (`ExecutionId`, `ActivityId`, `OccurrenceId`, `CorrelationId`, `ContextKey`, `ContextSource`, `AttributeKey`, `ExecutionKind`, `ActivityKind`, `MeasurementName`, `OccurrenceType`, …) store `private readonly string $value` and expose only `toString()`. Add a virtual `get` hook `public string $value { get => $this->raw; }` (rename backing to `$raw`; keep `toString()` as stable API). Matches the existing enum `->value` convention and AGENTS.md guidance. Risk: rename backing to avoid the `$value`/`$value{get}` clash; virtual props are excluded from native serialize (safe here). (P1)
- **`readonly class` eligibility (cheap wins):** `RunItBuilder`, `BridgeConfig`, `CloudEventsBridgeConfig`, `CurrentExecution`, `FiberContextStorage::$main` are effectively immutable — mark `readonly`. (P2)
- **Traits:** `ValidatedStringValueBehavior`/`ValidatedStringIdentifierBehavior` hold `private readonly string $value` (strictly "stateful"); defensible for immutable value objects, but interacts with P1 (rename backing). Other `Behavior/Domain` traits are stateless and focused — good.
- **WeakMap / WeakReference:** `WeakMap` is the only object-keyed association and is used correctly; `CoroutineContextStorage` is keyed by coroutine *id* (not an object) so `WeakMap` is N/A; `WeakReference` has no candidate. **Exception:** S5's metadata cache should be a `WeakMap` keyed on the closure (explicitly endorsed by AGENTS.md) — fixes both poisoning and unbounded growth.
- **Lazy objects:** limited applicability. `TelemetryPublisher::fromConfig()` eagerly builds 5 mappers + span store + instrument cache even when a signal is disabled, but each mapper holds only small static maps — **benchmark before doing this**; do not add speculatively. Core builder objects are cheap.
- **`never`:** unused and low-value (builder uses return-or-throw; exception factories *return* instances). Not worth forcing.

---

## 11. Security — [VERIFIED for S1–S6]

- **S1 [VERIFIED, CRITICAL] Secret context leak.** `Execution::normalize()` embeds the *entire* context via `->toArray()` (`Execution.php:142`); `DefaultArtifactRedactor` masks only `attributes`/`throwable`/`data`/`payload` and **never inspects the `context` field**; `DefaultContextRedactionPolicy` (the deny-by-default secret policy) has **no runtime caller in `src/`** — only its own test. A `secret`-classified context entry (`secret.token`) is therefore exported verbatim in the Execution-start, Execution-complete, and `ExecutionSnapshot` artifacts. Directly violates ADR-012 / `docs/architecture/security.md` ("redact before external publication"). *Fix:* apply `DefaultContextRedactionPolicy` inside `Execution::normalize()`/`ExecutionDefinition::normalize()` (or thread a policy into `toArray()`); key off `ContextClassification::isSecret()`, not key-name lists. Add a negative test asserting a secret entry is absent from every exported artifact under default wiring.
- **S2 [VERIFIED, CRITICAL] Serializer DoS.** `JsonSnapshotSerializer::assertSafe()` recurses into `JsonSerializable` objects passing `$depth` unchanged (`JsonSnapshotSerializer.php:89`); the depth guard fires only for arrays. A self-referential `JsonSerializable` overflows the stack → SIGSEGV (uncatchable). Also object chains deeper than `maxSerializationDepth` are not counted. *Fix:* increment depth for objects and reject cycles / non-`JsonSerializable` objects. Regression test for self-reference + over-depth.
- **S3 [VERIFIED, CRITICAL] Context recursion DoS.** `ContextValueValidator::assertSupportedValue()` recurses with no depth bound (`ContextValueValidator.php:76-78`); breadth is capped (`MAX_ARRAY_ELEMENTS`) but depth is not. Reachable from public `ContextEntry::internal()` → segfault. *Fix:* thread `$depth` + `?SecurityLimits` (mirror `AttributeValidator`); share one bounded recursive walk with `AttributeValidator` (also fixes D5).
- **S4 [VERIFIED, HIGH] Snapshot payload validation bypass.** `ExecutionSnapshot::create` (`ExecutionSnapshot.php:22-54`) `json_encode`s the payload and builds without the recursive-primitive validation that `Snapshot::create` performs; objects survive. *Fix:* run the same validator as `Snapshot::create`.
- **S5 [VERIFIED, HIGH] Metadata cache poisoning + unbounded growth.** `DefaultExecutedAsMetadataReader` keys by `spl_object_hash` (recycled after free) with no eviction (`DefaultExecutedAsMetadataReader.php:28,86`). In persistent workers (Swoole/Fiber) an unattributed closure can inherit another closure's `#[ExecutedAs]` metadata → telemetry misattribution, no error. *Fix:* `WeakMap` keyed on the callable object (bounds growth automatically). Regression test: read A, free A, read fresh unattributed B → B must not get A's metadata.
- **S6 [VERIFIED, HIGH] Silent artifact drop on redaction.** Adapters dispatch on concrete artifact types (`CloudEventPublisher` checks `instanceof OccurrenceInterface`; OTel checks concrete classes). When `RedactingArtifactPublisher` wraps an Observation/Occurrence/Snapshot in `RedactedArtifact` (which implements only `ExecutionArtifactInterface`), the adapter delivers **nothing** — no error. A misconfigured deny-list *suppresses* business-critical artifacts. *Fix:* make redaction a terminal, observable outcome (warn/log) or preserve type dispatch; add a "redactor drops artifact" test.

**Positive:** context model encodes deny-by-default at the type level (`ContextClassification` + `ContextEntry::secret()`); OTel propagation mapper correctly drops secret/non-propagatable entries; publication bounds enforced via `SecurityLimits`; Fiber/coroutine isolation keeps context out of process-global state; recursion guard correct for the single-path case.

---

## 12. Performance — [DERIVED/VERIFIED]

- **S13 [VERIFIED] `MonotonicClockInterface::monotonicNanoseconds()` is dead in `src/`.** `SystemMonotonicClock` implements it but nothing calls it; `Activity`/`Measurement`/duration math use wall-clock `Instant` only. ADR-009 names monotonic duration as the subject, yet elapsed-time is not actually monotonic — either measure with `monotonicNanoseconds()` (introduce a `Duration` value object) or relax ADR-009.
- Hot-path allocations: `DefaultExecutionRunner` news up an engine per execution (§6.3); `TelemetryPublisher` builds two stateless `SpanStatusMap`/`SpanKindMap` per span mapper (should be shared/reused — AGENTS.md: "reusable immutable mappers").
- Reflection cache (S5) grows unbounded in persistent workers.
- Buffer block-budget/`usleep` hardcoded (§9.2).
- Positive: no reflection on the invocation hot path (only at wrap time); custom normalization avoids eager serialization.

---

## 13. DX

- **Strengths:** fluent, validated `RunItBuilder`; `ExecutedCallable` decorator; `ExecutionScope`/`ActivityScope` scopes; clear exception hierarchy with named constructors; conformance verifiers for adapter authors.
- **Gaps:** `DefaultExecutionRunner`'s 23-arg ctor + positional `null`s (§6.1) is easy to misuse; `withMonotonicClock()`/`withClock()` alias ambiguity (D1); `SecurityLimits::with()` silent no-op (S8) contradicts "difficult to misure"; `cache()` on a public `@api` metadata reader exposes internal state (S5).

---

## 14. Maintainability

- File/class count is high (~358) but structure is consistent and names follow AGENTS.md vocabulary.
- ~34 Policy + ~26 Mapper classes include near-identical filter-vs-policy copies (`ObservationSeverityFilter` vs `SeveritySamplePolicy`, `ExecutionKindFilter` vs `ExecutionKindSamplePolicy`) that diverge by ad-hoc naming (S14) — collapse into one composable `Predicate` + sampling decision while keeping ADR/contract names stable.
- Three files declare two types each (`HttpContextExtractorInterface.php`, `Psr7ResponseCorrelationEnricher.php`, `DefaultHeaderPropagator.php`) — split and move interfaces under `Contract/`.
- **Stale tracked artifacts [DERIVED]:** `build/coverage`, `build/*.xml`, `vendor/` tracked; a coverage index references `src/Application/Propagation/SafeArtifactPublisher.php`, which does not exist. Add `.gitignore`; stop committing generated artifacts (AGENTS.md).

---

## 15. Testing gaps

- **No negative test for secret export** (the S1 path) — this is why S1 shipped; AGENTS.md requires negative tests for security behavior.
- No regression test for serializer self-reference (S2), context depth (S3), snapshot object payload (S4), or metadata cache poisoning (S5).
- `ContextValueValidator` and `ExecutionLifecycleCoordinator` deserve Infection targets.

---

## 16. Prioritized recommendations

| Pri | IDs | Action |
|-----|-----|--------|
| **P0** | S1 | Wire `DefaultContextRedactionPolicy` into `Execution`/`ExecutionDefinition` normalization; key on `ContextClassification`, not key names; add secret-export negative test. |
| **P0** | S2 | Increment depth for objects in `JsonSnapshotSerializer::assertSafe`; reject cycles/non-`JsonSerializable`; add self-reference + over-depth regression test. |
| **P0** | S3 | Add depth bound + `?SecurityLimits` to `ContextValueValidator`; share the recursive walk with `AttributeValidator`; add negative test. |
| **P0** | S4 | Validate `ExecutionSnapshot` payload with the same recursive-primitive check as `Snapshot::create`. |
| **P0** | S5 | Replace `spl_object_hash` cache with a `WeakMap` keyed on the callable; add poisoning regression test. |
| **P0** | S6 | Make redaction a terminal/observable outcome or preserve type dispatch; add drop test. |
| **P1** | S7,S9,S10,S11 | Replace magic lifecycle/status/severity/kind strings with enum values; add `ArtifactField`/`ArtifactKeys`; unify `label()`/`name()`; resolve enum `name()` shadowing (BC review). |
| **P1** | S8,S12 | Type-safe `SecurityLimits::with()`; unify limit constants via `SecurityLimits` default; route `json_decode` depth through `maxSerializationDepth`; use `Duration` constants. |
| **P1** | P1 (§10.2) | Property hooks for string value objects (one mechanical change across the trait + 3 manual classes). |
| **P2** | D1–D6,F1,I1–I4,S13,S14 | Adopt `clone`-wither builder; lifecycle-delegation trait; unify span mappers/recorders/status `isTerminal`; unify `FactoryInterface`; add missing interfaces; use monotonic clock; collapse filter/policy dupes; split two-type files. |
| **P2** | P2 (§10.2) | Mark eligible classes `readonly`. |
| **P3** | — | Add phpstan/cs rules: `else`→error, forbid literals matching enum values outside the enum file, cognitive-complexity + max-ctor-param thresholds, forbid `spl_object_hash` as cache key; Infection targets for S1–S6. |

---

## 17. Verification notes

Personally read and confirmed this session: `Execution.php:127-148`, `DefaultContextRedactionPolicy.php`, `DefaultArtifactRedactor.php`, `ContextValueValidator.php`, `JsonSnapshotSerializer.php`, `ExecutionSnapshot.php:22-54`, `DefaultExecutedAsMetadataReader.php`, `ExecutionLifecycleCoordinator.php`, `RunItBuilder` wither pattern, `BridgeConfigBuilder` wither pattern, `FiberContextStorage` `WeakMap` usage, `AttributeKeys` const class. DepTrac (0 violations) and the 7-`else` count are taken from sub-analysis and recommended to re-run in CI. Sub-analysis findings marked **[DERIVED]** should receive a validating test before implementation, but none contradict the verified evidence.

No code was changed. This document is analysis only.
