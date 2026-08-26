# RunIt — In-Depth Technical Review

> **Review date:** 2026-08-26
> **Scope:** the `run-it` package (`src/RunIt`) — library source (`src/`), test suites
> (`tests/`), first-party tooling (`tools/lint`, `tools/api-compat`,
> `tools/phpstan/runit-rules`, `tools/dogfood`), documentation, and quality gates.
> **Baseline:** post-refactoring-roadmap tree (Phases 0–4 complete and committed).

---

## 1. Assumptions

1. This document is a **review artifact only** — no production code was changed while
   producing it. Findings cite evidence (`file:line`) as of the baseline above.
2. Thresholds are taken from the reviewer's brief and from `AGENTS.md`: coverage ≥95%,
   ≤120 columns, one type per file, no `else`/`elseif`, no Yoda conditions, imports for
   all references, interface backing for classes, declaration-order convention.
3. Coverage was re-measured on the day of review via the pinned pipeline
   (`Unit, Conformance, Integration, Concurrency`). Mutation numbers remain
   CI-owned per the Phase 1 caveat recorded in `docs/QA.md`; local Infection aborts were
   unblocked but per-mutant covering-test selection does not narrow in this environment.
4. Generated Codeception actors (`tests/Support/_generated/**`) are excluded from
   mechanical audits by design (regenerated artifacts); they are likewise exempt in
   `composer lint:length`.
5. "Dev tooling" (`tools/*`) is first-party but historically outside the enforced rule
   scopes (`srcRoot: %currentWorkingDirectory%/src`); deviations there are reported, not
   assumed to be violations of library policy.

## 2. Method

- **Scripted audits** (reproducible): else/elseif scan, Yoda-condition scan, un-imported
  FQCN reference scan, one-type-per-file scan, abstract-class scan, interface-backing
  scan, >120-column scan, `new`-instantiation classification, token-based member-order
  audit (394 types checked), magic-literal frequency analysis.
- **Manual deep-dives:** hexagonal boundary greps + `deptrac.yaml` layer model, ADR set,
  publication pipeline assembly, SDK builder seam, conformance kit, security posture
  (`docs/security/threat-model.md`, redaction pipeline), performance posture
  (monotonic clocks, bounded buffers, benchmarks), DX surface (README, examples,
  composer scripts).
- **Verification:** full pipeline run (872 tests), coverage report, gate sweep
  (phpstan, DepTrac, api-compat, php-cs-fixer, `lint:length`, composer validate).

## 3. Executive summary

| Dimension | Verdict | Highlights |
|---|---|---|
| Features | Strong | Execution/Activity/Context/Measurement/Observation/Occurrence/Snapshot model; Fiber & Swoole runtimes; OTel/CloudEvents/PSR bridges; conformance kit |
| Architecture (hexagonal) | Strong | Domain/Application/Contract import nothing upward (grep-verified); 9-layer DepTrac green; 19 ADRs; adapters map-only |
| Ports/adapters/factories | Strong | All ports in `Contract/Port`; creation confined to builders/factories/named constructors with sanctioned defaulting at composition edges |
| SOLID | Strong | Small contracts (≤9 methods), DIP via ports everywhere, LSP-safe value semantics, ISP respected |
| DRY | Good | Dedupe pass landed (recording publishers, lifecycle spies, message prefix); minor overlap left in stack wrapping (R-11) |
| Modern PHP | Good | 123 readonly classes, 14 enums, 217 promoted-ctor props, WeakMap used purposefully, Fiber adapter; hooks/lazy unused (R-8/R-9) |
| Mechanical discipline | Good | src: zero `else`, zero Yoda, zero >120 cols, zero multi-type files; tests/tools carry residuals (R-1, R-5–R-7, R-13) |
| Interface backing | Good | 198/222 concrete classes implement an interface; 24 internal value/config/map/log helper classes do not (R-2) |
| Complexity | Strong | `ComplexityGuardRule` threshold 19 enforced and green; max ctor params 16 enforced |
| Security | Strong | Deny-by-default redaction (ADR-012), bounded payloads, recursion guards (ADR-014), no arbitrary-object serialization, threat model documented |
| Performance | Strong | Monotonic clocks (ADR-009/019), byte estimator, instrument cache, benchmarks present; hot-path reflection forbidden by rule |
| Maintainability | Strong | QA.md gate overview, dogfooding harness, UPGRADING/CHANGELOG, vocabulary TTLs referenced from code |
| DX | Strong | README (68 sections), 18 runnable examples, ergonomic composer script chain incl. `quality` aggregate |
| Verification | Good | Coverage **98.02% lines** (≥95% met), leakage rule documented, conformance self-exercised; mutation floor pending CI baseline (R-4) |

**Bottom line:** the codebase is in materially better shape than at the previous review.
All P1 items from the prior cycle are closed. Remaining findings are P2 process-completion
items (test-side imports, interface-backing exemptions, member ordering, mutation floor)
and P3 polish.

## 4. Feature assessment

The seven-concept execution model is complete and internally consistent:

- **Execution runtime** — lifecycle state machine (`Created→Running→{Succeeded, Failed,
  Cancelled, Timed_Out}`) with coordinator + policies; nested executions via explicit
  Child/Detached modes; idempotent terminal transitions.
- **Activity runtime** — nested activities with depth limits (`ActivityDepthExceededException`),
  open-activity resolution handed to four shipped completion policies sharing one message
  prefix (`ExecutionCompletionPolicyInterface::OPEN_ACTIVITY_MESSAGE_PREFIX`).
- **Context** — immutable, classified values with propagation policies and deny-by-default
  redaction; ambient storage behind adapter-backed ports (Fiber/Swoole/Stack).
- **Publication** — decorator pipeline (buffer → sample → redact → filter → fail-safe →
  guard) assembled by `PublicationPipeline::build()`; overflow/failure policies explicit;
  health reporting separated from the failing path.
- **Integrations** — OpenTelemetry (traces/metrics/logs), CloudEvents (bidirectional mappers),
  PSR-3/7/11/14/18 surfaces, Swoole coroutine and Fiber context storages.
- **Conformance kit** — nine framework-neutral verifiers + fixture contract, self-exercised
  to ≥95% (measured 100% verifier line coverage) with negative fixtures.

No missing core capability was identified against `EXECUTION.md`.

## 5. Architecture (hexagonal, SOLID)

### 5.1 Boundary integrity — verified, not asserted

```
grep '^use ItPack\\RunIt\\(Adapter|SDK)' src/Domain src/Application   → 0 hits
grep '^use (Psr\\|Swoole|OpenTelemetry|CloudEvents)'        src/Domain → 0 hits
Contract/ imports of Application/Domain/Adapter                          → 0 hits
deptrac --config-file=deptrac.yaml                                       → 0 violations, 0 uncovered layers
```

Layers: `Common, Contract, Port, Domain, Application, SDK, Common Adapter, External Adapter,
Test Support`. Adapters depend only on Contracts/Ports/Domain; mapping logic lives in the
adapter; no vendor type leaks into a domain signature (checked across OTel/CloudEvents/Psr
mappers).

### 5.2 Factories, builders, instantiation discipline

Creation sites are concentrated exactly where they belong:

- `RunItBuilder` (SDK seam, 367 lines) — the consumer-facing composition root.
- `PublicationPipeline::build()` — publisher-stack assembly (builder semantics despite the name).
- `ExecutionFactory` / `ExecutionSnapshotFactory` / `StringIdentifierFactory` /
  `PublisherStackFactory` — injected factories for identity, aggregates, and stacks.
- Named constructors on value objects (`Observation::info()`, `Measurement::counter()`,
  `Snapshot::create()`, `Instant::of()`, exception factories `InvalidXException::from…()`).
- **Sanctioned defaulting:** `DefaultExecutionRunner` materializes defaults via promoted
  parameters (`private SecurityLimits $limits = new SecurityLimits()`, line ~126) and its
  bootstrap path; `ExecutionScope` lazily news-up default recorders/runners on first access.
  These are composition-edge defaults, not service-location — acceptable, with a strict-DIP
  polish option in R-12.
- Exceptions are created inline at throw sites (universally accepted; excluded from audit).

### 5.3 SOLID observations

- **SRP:** largest class is 399 lines (`DefaultExecutionRunner`) and reads as orchestration
  only; recorders/runners/coordinators each own one concern.
- **OCP:** publisher decorators and sampling/redaction/filter predicates compose without
  modification; policies are pluggable at every lifecycle joint.
- **LSP:** immutable value objects with structural equality (`StringValueEquality` behavior);
  policies honor "never change business result/exception" contract (ADR-008).
- **ISP:** widest contract has 9 methods (`OccurrenceInterface`); most have 3–6. No fat
  interfaces found.
- **DIP:** every external effect sits behind a port (`MonotonicClockInterface`,
  `SerializerInterface`, `SnapshotStoreInterface`, `ContextStorageInterface`, …); time,
  identity, randomness all injected.

### 5.4 DRY observations

Post-Phase-2/3 deduplication removed the recording-publisher ×4, lifecycle-spy ×2,
failing-store ×2 and open-activity-message ×4 duplications. Remaining minor overlaps:
redact/sample wrapping exists both in `PublisherStackFactory::compose()` and inside
`PublicationPipeline::build()` (R-11), and normalized-field key strings recur across
adapters (R-7).

## 6. Modern PHP leverage

| Feature | State | Assessment |
|---|---|---|
| Enums (backed) | 14 (`ExecutionStatus`, `ActivityStatus`, `ViolationSeverity`, …) | Used for genuinely closed sets; names/kinds deliberately extensible strings per ADR-018 |
| Readonly classes | 123 of 222 concrete classes | Artifacts/context fully immutable; remaining non-readonly are stateful runtime objects where mutation is the point |
| Promoted properties | 217 constructor promotions | Consistent, DI-friendly |
| Property hooks / virtual properties | **0 uses** | AGENTS encourages hooks for validated/computed projections — untapped (R-8) |
| Fibers | Dedicated adapter (`Adapter/Fiber`, context storage/engine tests, concurrency suite) | Correctly isolated behind ports; persistent-worker leak tests present |
| WeakMap | 3 purposeful uses (serializer cycle guard, `FiberContextStorage`, attribute metadata reader) | Non-owning associations done right; no lifetime hazards found |
| WeakReference | 0 | Fine — no current need |
| Lazy objects (`newLazyGhost/Proxy`) | 0 | Deliberate hot-path predictability; candidate for optional infra in `ExecutionScope` (R-9) |
| First-class callables | 0 | Minor modernization nicety (R-10) |
| `match` expressions | 11 files | Where applicable |

No feature is used decoratively; the AGENTS "do not use it merely because it exists" rule
is being honored — which also explains the hook/lazy gaps.

## 7. Mechanical discipline audit

| Criterion | Result | Evidence |
|---|---|---|
| No `else`/`elseif` in library+tests | **0 hits** | `ForbidElseRule` enforces `src`; tests verified clean by scan |
| No Yoda conditions | **0 hits** (precise literal-on-left scanner over stripped sources) | — |
| One type per file | **0 violations in src**; 7 test files match the documented exemption set verbatim (QA.md “One-type-per-file rule scope”) | e.g. `tests/Unit/SDK/Builder/RunItBuilderTest.php` |
| ≤120 columns | **0 violations** in `src`+`tests` (guard: `composer lint:length`, wired into `quality`); generated actors exempt | tools exceed (R-13) |
| Imports for all references | **src clean** except two deliberate `\Swoole\Coroutine` probes (`SwooleRuntimeDetector.php:15-16`, `SwooleCoroutineIdProvider.php:14-15`); **tests carry ~100+ inline FQCN references** (R-1) | scan output |
| No abstract classes | **0 in `src`**; 1 in tooling base (`AbstractSrcRule`) (R-5) | — |
| Traits harmonize with interfaces | 9 traits, all paired with the contract whose methods they implement (`SequencedBehavior`↔`SequencedInterface`, etc.) | `src/Behavior/**` |
| Constants/enums vs magic literals | Enum/status vocabularies solid; recurring normalize-key strings across adapters (R-7); fixture ids repeat (R-12) | frequency scan |
| Declaration order | 17 ordering deviations + 7 public-after-private visibility deviations out of 394 types (R-3) | token audit |

## 8. Findings register

Severity: **P2** = should fix before treating the criterion as met; **P3** = polish.

### R-1 (P2) — Un-imported FQCN references across the test suite

~100+ executable-line references use leading-backslash FQCNs instead of imports
(`tests/Unit/Application/Runner/ResilienceTest.php:113,155-157,332`,
`tests/Unit/Application/Runner/DefaultActivityRunnerTest.php:34-39`,
`tests/Conformance/RunItPortConformanceTest.php:130`,
`tests/Unit/Application/Security/TenantIsolationTest.php:141,150`,
`tests/Unit/Adapter/Swoole/CoroutineContextStorageTest.php:84`,
`tests/Unit/Domain/Measurement/MeasurementTest.php`, and OTel/Psr/CloudEvents mapper tests).
The Phase 3 import pass covered `src` only. **Recommendation:** rerun the T3.1-style
import pass over `tests/**` and add a PHPStan/cs-fixer guard so the category cannot regrow.
The two `\Swoole\` probes in `src` should keep FQCN form *or* import an alias — either way,
document them as intentional optional-extension probes.

### R-2 (P2) — 24 concrete classes without interface backing

`AGENTS.md` requires every concrete class to implement an appropriate interface; 198/222
do. The 24 without are concentrated in config/value objects, lookup maps, PSR loggers and
behavior-internal helpers:

> `ArtifactRedactionConfig`, `BufferConfiguration`, `PublicationRoute`, `SecurityLimits`,
> `FallbackErrorLogReporter`, `BridgeConfig(Builder)`, `BridgeCapability`,
> `CloudEventsBridgeConfig(Builder)`, `CloudEventMappingPolicy`, `CloudEvent`,
> `SeverityMap`, `SpanKindMap`, `SpanStatusMap`, `InstrumentCache`,
> `OccurrenceEventWrapper`, `ExecutionAwareLogger`, `ObservationRecordingLogger`,
> `BoundedKeyGuard`, `BoundedPrimitiveValueWalker`, `StringValueEquality`,
> `ConformanceViolation`, `ExecutedAs`.

Several are genuinely internal collaborators where an interface would be ceremony without a
consumer — which the same AGENTS section warns against. **Recommendation:** split the rule:
introduce narrow interfaces where a seam exists today (loggers, maps, health reporters),
and record an explicit exemption category ("adapter-private value/config/map types") in
ADR-015 so the backing claim stays auditable.

### R-3 (P2) — Member-declaration order deviations (17 + 7 sites)

Token-level audit of 394 types against the required order (traits → constants →
properties → constructor → factories → instance methods → other statics; public before
protected/private within a section):

- Constant after property: `ErrorLogHealthReporter.php:30`
- Property after constructor (promoted-prop assignment pattern):
  `SeveritySamplePolicy.php:28`, `ExecutionKindSamplePolicy.php:38`,
  `ExecutionKindFilter.php:38`, `ObservationSeverityFilter.php:28`,
  `ExecutedCallable.php:42`, `BoundedKeyGuard.php:31`
- Factory/static ordering: private static helpers interleaved ahead of public factories in
  `Observation.php` (`atSeverity` before `debug/info/…`), `Measurement.php`,
  `BridgeConfigBuilder`, `TelemetryPublisher`, `CloudEvent.php`, `SecurityLimits.php`,
  `DefaultContextRedactionPolicy.php`, `ContextEntry.php`, `Instant.php`
- Public after more-private within a section (×7): `AttributeValidator.php:66`,
  `CompositeContextStorage.php:46`, `CoroutineContextStorage.php:52`,
  `FiberContextStorage.php:54`, `Measurement.php:96`, `ContextValueValidator.php:72`,
  `SecurityLimits.php:115`

**Recommendation:** one mechanical normalization commit, then extend the existing custom
ruleset with a `MemberOrderRule` (same pattern as `RequireFinalRule`) so ordering is
machine-enforced rather than re-audited.

### R-4 (P2) — Mutation testing configured but floors unenforced

Infection runs locally since Phase 1 (dogfood exclusion fixed the abort), with a critical-
branching profile (`infection.s1s6.json5`) targeting AGENTS' mutation priorities.
However `minMsi/minCoveredMsi` are provisional `0` pending a CI baseline, and local
per-mutant selection does not narrow under the Codeception adapter here (documented in
QA.md). **Recommendation:** add the CI mutation job, capture the baseline, ratchet floors
to baseline−ε; consider a PHPUnit-based mutant runner if per-mutant selection remains
un-narrowed, since that is what makes MSI meaningful.

### R-5 (P3) — One abstract class remains

`AbstractSrcRule` (phpstan rules tooling) is the single `abstract class`. Justified as a
shared base, but it contradicts the stated no-abstract preference. Convert to a trait +
composition, or exempt tooling explicitly in AGENTS.

### R-6 (P3) — Tooling outside enforced disciplines

`else`/`elseif` in `tools/api-compat/ApiCompat.php` (186, 563, 749) and
`ForbidReflectionOnHotPathRule` itself; long lines in `ApiCompat` and several rule files.
Rules target `srcRoot: …/src` only. Either extend scopes to first-party tools or document
tooling as exempt in QA.md's coding-style section.

### R-7 (P3) — Recurring normalize-key string literals

Frequency scan: `'source'` ×6, `'sequence'` ×5, `'failed'`/`'cancelled'`/`'timed_out'` ×4
each, `'specversion'`, `'classification'`, `'artifact'`, `'exception'` across adapters;
plus `'application/json'` ×4 and `'sha256'` ×3. Vocabulary homes exist
(`ArtifactField`, `AttributeKeys`, `CloudEventExtensionKey`) but adoption is partial —
several mappers still inline keys. Consolidate the strays into the existing vocabularies;
add `JsonSnapshotSerializer::JSON_CONTENT_TYPE` and a hash-algorithm constant
(`DeterministicHashCalculator::ALGORITHM_SHA256`).

### R-8 (P3) — Property hooks unused

Zero `get/set` hook sites despite AGENTS encouraging hooks for validated/computed
projections. Natural candidates: computed projections on value objects
(e.g. `Duration::seconds()` derived view, virtual `Snapshot::payloadSize()` if ever needed).
Opportunity only; current getter style is consistent and harmless.

### R-9 (P3) — Lazy-object option unused

`ExecutionScope` materializes default recorders/runners eagerly on first accessor call via
explicit branches. PHP 8.4 lazy ghosts/proxies could defer optional infra (e.g.
snapshot store) with the documented hot-path caveat. Optional.

### R-10 (P3) — First-class callable syntax absent

`strlen(...)`-style conversions would shave closure noise in mapper pipelines. Cosmetic.

### R-11 (P3) — Small DRY overlap in stack wrapping

`PublicationPipeline::build()` wraps redact/sample layers itself while
`PublisherStackFactory::compose()` owns the same two wrappers for the builder seam.
Delegate those two layers to the shared factory to keep one wrapping authority.

### R-12 (P3) — Fixture identifier repetition

`'exec-0001'` appears five times in `RunItPortFixture` alongside the already-hoisted
instant constant. Hoist `FIXED_EXECUTION_ID = 'exec-0001'` (T4.1 pattern).

### R-13 (P3) — Line-length guard does not cover tools

`composer lint:length` scans `src`+`tests`; ApiCompat and some rule files exceed 120
columns. Extend the scan to `tools/**` (excluding vendored stubs) or record the exemption.

### R-14 (P3) — Strict-DIP polish for inline defaults

`DefaultExecutionRunner`'s promoted defaults and `ExecutionScope`'s inline recorder
materialization could accept default-*factory* parameters instead of concrete defaults,
removing the last `new` sites from application services. Low urgency; behavior-neutral.

## 9. Security assessment

- **Deny-by-default secrets:** sensitive attributes require explicit classification
  (ADR-012); `DefaultArtifactRedactor` masks attributes, throwable messages, occurrence
  data and snapshot payloads behind typed toggles; the RedactorVerifier proves masking and
  non-leakage with negative fixtures.
- **Bounds everywhere:** `SecurityLimits` caps attribute count/key length/value size/
  nesting depth; `BufferConfiguration` bounds queues with explicit flush/overflow policy;
  `PayloadSectionByteEstimator` gates oversized occurrences before buffering.
- **Recursion safety:** `PublicationGuard` + `FailSafeArtifactPublisher` keep publication
  failures off the business path and route health to a separate reporter (ADR-014),
  with dedicated recursion tests.
- **No arbitrary object serialization:** raw `serialize()/unserialize()` absent; snapshot
  hashing/signing (`HmacSnapshotSigner`) and deterministic JSON only;
  `ForbidArbitraryObjectSerializationRule` enforces this statically.
- **Tenant isolation** has dedicated tests (cross-execution ownership assertions).
- Threat model (`docs/security/threat-model.md`) and supply-chain notes exist; CI runs
  `composer audit`.

No security defects found. The exfiltration paths called out in AGENTS are each covered by
a control plus a test.

## 10. Performance assessment

- Monotonic timing end-to-end (`MonotonicClockInterface`, ADR-009/019);
  `ForbidDirectWallClockRule` blocks wall-clock misuse in hot namespaces.
- Hot-path hygiene is machine-checked: `ForbidReflectionOnHotPathRule` covers
  Domain/Runner/Engine construction and static calls.
- Bounded buffering with deadline-flush budgets (`BLOCK_BUDGET_MICROSECONDS`);
  OTel instruments cached (`InstrumentCache`); exporters batched rather than per-artifact.
- Benchmarks exist (`benchmarks/ExecutionBench.php`, `docs/benchmarks.md`); claims in docs
  are measurement-linked per QA.md policy.

No hot-path regressions or unbounded structures found.

## 11. Maintainability & DX

- **Docs:** README models the domain up front; EXECUTION.md is canonical; 19 ADRs capture
  every decision this review relies on; QA.md documents each gate including failure
  policies; vocabulary TTLs are referenced from code via `@see`.
- **Tooling UX:** one-command gates (`composer quality` = validate → cs → analyse →
  architecture → lint:length → test → security:audit); dogfood harness exercises the CLI
  tooling against itself.
- **Examples:** 18 runnable scripts including fibers and conformance usage; no imaginary
  framework glue.
- **Upgradability:** UPGRADING.md + CHANGELOG + compatibility doc + api-compat baseline
  make BC drift detectable rather than aspirational.
- **Error surfaces:** exceptions are domain-typed markers with named constructors carrying
  contextual detail (`ConflictingCompletionException::forConflict`), never bare
  `RuntimeException` in `src` (verified by scan).

## 12. Verification

| Gate | Result (review day) |
|---|---|
| Pipeline suite | OK — 872 tests, 5876 assertions |
| Statement/line coverage | **98.02%** (3954/4034 lines), methods 93.54% — ≥95% met |
| Coverage leakage | Unit mirrors Domain/Application/SDK; shared doubles live in `tests/Support/Fixture`; leakage rule documented in QA.md §T0.5 and spot-checked |
| Conformance | Every verifier happy-path + negative-exercised; `Verifier/*` classes at 100% line coverage |
| Mutation | Configured (default + critical-branching profile), locally runnable, floors provisional — see R-4 |
| Static analysis | PHPStan at configured level: 0 errors (custom ruleset: final, readonly-for-artifacts, no-var, no-mixed, complexity ≤19, ctor params ≤16, forbid wall-clock/reflection/serialization/spl-hash, else-free) |
| Architecture | DepTrac: 0 violations, 0 uncovered |
| API compatibility | api-compat check clean against baseline |
| Style / length | cs-fixer 0/562; `lint:length` 0 violations |

## 13. Recommended follow-ups (ordered)

1. Close R-1: import pass over `tests/**` + regrowth guard.
2. Close R-3: member-order normalization commit + `MemberOrderRule`.
3. Land CI mutation job; ratchet MSI floors (R-4).
4. Resolve interface-backing split decision for the 24 classes (R-2) via ADR-015 update.
5. Consolidate stray normalize-key literals into existing vocabularies (R-7) and fixture ids (R-12).
6. Decide tooling scope question once: widen rules/guards to `tools/**` or exempt explicitly (R-5, R-6, R-13).
7. Opportunistic modernization: property hooks where projections appear, first-class callables (R-8/R-10); delegate pipeline wrap overlap (R-11).

---

*Review produced by autonomous audit; scripted scanners are reproducible from the method
section. No code was modified in the course of this review.*
