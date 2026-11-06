# RunIt Refactoring Plan

**Status:** Implementation in progress (Phase 0 incomplete; dependent phases pending).

**Source:** [`REVIEW.md`](REVIEW.md), reviewed 2026-11-05. Paths below are relative to the RunIt package unless stated otherwise.

## Scope and execution rules

- [ ] Reproduce each finding against the current tree before changing it. The review is a snapshot, and dependencies were updated after its initial audit.
- [ ] Deliver each phase as small, independently reviewable changes with regression tests. Preserve existing unrelated working-tree changes.
- [ ] Preserve execution invariants, original business exceptions, redaction boundaries, optional integrations, and frozen public contracts. Do not regenerate an API or schema baseline to hide a break.
- [ ] Treat the recorded 88.18% unit statement coverage and 137 PHPStan errors as historical measurements, not current guarantees. Re-measure after dependency or source changes.
- [ ] Treat the previous six dependency advisories as superseded: the subsequent audit found no vulnerability advisories. `doctrine/annotations` remains an abandoned **development** dependency via `just-bases/php-qa-base` and PHPBench; track it separately.
- [ ] Use PHP 8.4 as the compatibility floor and run the supported newer PHP matrix where available. Record unavailable extensions or environments rather than claiming those paths passed.

### Decisions required before dependent changes

Checked items record approved policy direction, not completed implementation or
verification. The unchecked design details below must still be resolved where applicable.

- [x] **API identity:** Retain `ContextTokenInterface::id(): int` as an explicitly approved breaking contract. Update the API baseline and changelog and migrate affected ItPack implementations. The storage-defined value is stable for the token lifetime but is not necessarily unique; detachment must not rely on it alone.
- [x] **Throwable redaction defaults:** Make redaction configurable. By default, mask throwable messages at every cause depth and omit traces; permit trace export only through explicit configuration. Preserve bounded processing and document the security implications of opting in.
- [x] **Sampling and redaction order:** Make the order configurable, with redaction before sampling by default. Sampling first must be an explicit choice documented as exposing unredacted artifacts to samplers. Preserve built-in type-dependent sampling behavior when redaction happens first.
- [x] **Throwable metadata:** Preserve throwable class names and error codes by default for diagnosis, with configuration to remove or mask them where required.
- [x] **Redacted observations:** Make handling configurable: emit a sanitized observation by default, or allow explicit drop-and-report behavior. When safe mapping is impossible, report the drop through the health path. Specify safe fields and classification without exposing payloads during implementation.
- [x] **Open-activity outcome:** Make the outcome configurable. By default, record a critical diagnostic without changing the operation-derived execution status; allow an explicit policy to mark the execution failed. Preserve the operation's returned value or original exception in either case.
- [x] **Span-store retention:** Use a configurable finite default limit and evict oldest entries. Late lookups after eviction treat the parent as unavailable and must not change execution results. Choose and document the default limit with workload evidence; establish whether configuration can remain compatible with the frozen public API.
- [x] **Concurrency default:** Retain the synchronous default and require explicit runtime-appropriate storage for interleaved executions. Document the limitation and add feasible configuration validation; do not silently switch storage or assume the default is concurrency-safe.
- [x] **Coverage attribution policy:** Collect coverage from Unit tests only and require an explicit class under test, while permitting justified real collaborators and practical documented exceptions. Define the enforcement mechanism and CI environment; aggregate coverage alone does not establish per-test attribution.
- [x] **Coding standards principle:** Keep existing enforced conventions and adopt additional rules only when useful, with focused exceptions. Do not impose blanket factory-only construction or interface creation. Reconcile individual rules, including member ordering, with `docs/QA.md` before changing or adding enforcement; avoid unnecessary public API changes.

**Open design details:**
- [ ] Define safe observation fields and classification, and the configurable drop/report mechanism.
- [ ] Choose an evidence-backed span-store limit and specify eviction scope, duplicate-ID ordering, and compatibility-safe configuration.
- [ ] Determine feasible concurrency configuration validation and its behavior without silently changing the default.
- [ ] Define coverage attribution enforcement, justified exceptions, negative controls, and the CI environment.
- [ ] Reconcile individual coding rules and exceptions with the existing `docs/QA.md` standards.

## Phase 0: Baseline and safety net

**Depends on:** none. **Outcome:** reproducible current state and isolated regression targets. **Status: In progress** (2026-11-05).

- [ ] Record revision, dirty state, PHP version and extensions, Composer dependency state, and relevant runtime adapters. Do not overwrite or revert existing changes.
- [ ] Run `composer validate --strict`, `composer api:check`, `composer analyse`, `composer refactor:dry`, `composer cs:check`, `composer architecture`, `composer lint:length`, `composer test`, `composer coverage`, and `composer audit`; capture exit codes and distinguish pre-existing failures from new ones. Run Rector before PHP-CS-Fixer. Inspect scripts in `composer.json` before changing commands.
- [ ] Preserve coverage XML and denominator from the Unit-only run; do not merge results from Integration, Conformance, or Concurrency. Record branch-coverage and extension limitations.
- [ ] Run the existing PhpMetrics and PHP Assumptions commands in `docs/REVIEW.md`, retain reports outside tracked source, and confirm which tools and versions are available after the update. Treat assumption reports as hypotheses.
- [x] Add or identify narrowly scoped fixtures for sensitive exception chains, fake clocks and byte estimators, failing publishers/reporters, interleaved Fibers, and third-party tokens. Put cross-layer tests in the appropriate non-Unit suite.
- [ ] Record baseline compatibility and representative hot-path benchmarks before altering publication, buffering, context storage, or serialization. Capture PHP version, workload, buffer sizes, and measurement variance.

**Historical Phase 0 results**, recorded in `docs/REVIEW.md` before the approved API baseline update; these do not establish the current worktree's quality-gate status:

| Check                        | Result                                                                                   |
|------------------------------|------------------------------------------------------------------------------------------|
| `composer validate --strict` | Passed                                                                                   |
| `composer architecture`      | Passed, 395 analyzed classes                                                             |
| `composer lint:length`       | Passed                                                                                   |
| `composer api:check`         | Failed: 1 BC break (`ContextTokenInterface::id()`)                                       |
| `composer cs:check`          | Passed after fixing 2 test files                                                         |
| `composer analyse`           | Failed: 138 PHPStan errors; attribution of every remaining diagnostic is not established |
| `composer audit`             | No advisories; 1 abandoned package (`doctrine/annotations`)                              |
| Full test suite              | Passed: 875 tests, 5,919 assertions across four suites                                   |
| Coverage                     | 88.14% unit statement coverage (3,554/4,032 lines)                                       |
| Benchmarks                   | 10 subjects, 0 failures                                                                  |

**Phase 0 additions:**
- `tests/Support/Fixture/SensitiveExceptionChain.php` — nested throwable chain with classified values (API key, DB password, session token) at three depths.
- `tests/Support/Fixture/DeterministicArtifactByteEstimator.php` — deterministic byte estimator for buffer admission tests.
- `tests/Concurrency/InterleavedFiberContextIsolationTest.php` — interleaved fiber isolation test.
- CS fixes: two missing-space-after-`if` fixes in Swoole unit tests.
- The current `RedactingPublisherTest::testNestedThrowableSecretsAreRedacted` checks that classified markers are absent from a published observation; it passed on 2026-11-06 (7 tests in the focused suite). This does not verify failed-execution or adapter export paths.
- Other available fixtures include `DeterministicMonotonicClock`, `ThrowingArtifactPublisher`, throwing health callbacks in `ResilienceTest`, and the independent `RunItThirdPartySample` token. Full custom-token ownership regression coverage remains a Phase 1 task.

**Exit:** baseline commands and limitations are recorded; each high-priority issue has a reproducible failing test or a documented reason it cannot yet be reproduced.

**Outstanding:** The historical baseline used revision `b777c0a4425de7d10db87c82e995913b46dda2b9` with unpreserved working-tree changes; current HEAD and worktree differ. The recorded Unit coverage XML and PhpMetrics/PHP Assumptions reports under `/tmp/kilo` are no longer present. Re-establish and preserve the evidence, record exact command exit codes and remaining diagnostic attribution, and obtain a buffered-publisher drain/close benchmark with workloads and variance. The historical coverage run used an isolated direct Unit command rather than the exact `composer coverage` command.

The API identity and redaction policy directions above are now recorded; implementation details and regression evidence remain open. The approved token baseline change supersedes the historical API break result. On 2026-11-06, `composer api:check` exited 1 on the current dirty tree for nine additive symbols and reported zero backward-compatibility breaks; this does not authorize a further baseline update. The current observation-level nested-redaction test passes, but failed-execution and adapter export paths, sampler input, byte admission, default failure reporting, concurrency risk, and complete token-ownership scenarios still require individual reproduction or documented reasons they cannot yet be reproduced. Phase 0 is not complete.

## Phase 1: Compatibility and context ownership

**Completion depends on:** Phase 0 exit and the API identity decision; preparatory work may proceed earlier. **Review:** frozen interface break; concurrent context isolation.

- [ ] Inspect `src/Contract/Port/ContextTokenInterface.php`, `ContextStorageInterface`, the API baseline, all `id()` call sites, and concrete stack, Fiber, coroutine, and composite tokens. Verify composite delegation and ownership semantics under the retained contract.
- [x] Exercise custom tokens implementing the approved `id()` contract and their storage through composite attach/detach, correct owner delegation, foreign token rejection, repeated detach, and LIFO behavior. The former opaque interface is intentionally not supported by the new baseline.
- [ ] Complete the approved breaking-contract migration for affected internal consumers and retain correct token ownership and detachment semantics.
- [ ] Verify the approved break's changelog, baseline, versioning and migration documentation; run context storage conformance tests and `composer api:check` against the final tree.
- [x] Document `StackContextStorage` as synchronous-only at the SDK builder and concurrency entry points; show configuration of Fiber/coroutine storage for persistent workers.
- [ ] Add interleaving tests that demonstrate the default risk and verify isolated current scopes, parent identity, LIFO detach, restoration after exceptions, and repeated requests with runtime-specific storage. Use the coroutine extension where available and report skips explicitly.
- [ ] If runtime misuse detection is approved, add it without global mutable state or an undocumented default behavior change; test synchronous use and interleaving separately.

**Exit:** token implementations conform to the approved breaking contract; affected internal consumers are migrated; API check and relevant conformance/concurrency tests pass; concurrency configuration is explicit.

**Progress:** The builder and integration guides explain the synchronous default and explicit Fiber/coroutine storage. The Swoole example retains the immutable builder returned by `withContextStorage()`. The Fiber concurrency suite demonstrates default-stack interleaving, verifies nested parent identity and failure restoration, and covers a second execution on the same Fiber while another Fiber is suspended. Remaining runtime-specific cases are tracked below. The Fiber engine does not automatically propagate caller-local ambient scope into its new Fiber; the operation receives the explicit scope instead. Phase 0 remains open. The approved `id()` break is recorded in the changelog and API baseline; current RunIt, AsyncIt and RunItThirdPartySample token implementations already declare the method. Composite-token tests exercise delegation and ownership with custom tokens.

**Consumer follow-up:** AsyncIt's `CoroutineContextStorageTrait` previously checked only a token's integer ID. A direct reproduction confirmed that a token from a different storage instance with a matching ID could detach the first instance's scope. The trait now verifies exact issued-token identity as well as LIFO order; regression tests cover memory, Revolt, Swoole, and Swow storage. Native Swow remains untested without its extension.

**Further Phase 1 evidence (2026-11-06):** `InterleavedFiberContextIsolationTest` demonstrates that the synchronous default exposes a suspended Fiber's ambient scope to another Fiber, which is then incorrectly linked as its child. The Fiber-specific storage test also verifies the nested execution's parent identity while interleaved. On PHP 8.5, the concurrency suite passed (7 tests, 145 assertions) and conformance passed (52 tests, 111 assertions). Composite storage now tracks issued wrapper identity, rejects foreign and forged wrappers even when child storages are shared, and retains the token after a failed out-of-order detach. Focused tests cover colliding custom token IDs, repeated detaches, retry and runtime detector changes. `composer api:check` exited 1 for the same nine additive symbols and reported zero backward-compatibility breaks; no baseline was regenerated. `composer lint:length` failed on four lines in unrelated publication and SDK files. PHP 8.4 was not tested.

`composer quality` stopped at `cs:check` (exit 8), reporting formatting differences in 32 files; the changed concurrency test passed a focused PHP-CS-Fixer dry run. Later quality stages were not run by that command. Separate `composer architecture` passed (404 classes); `composer analyse` failed with 141 diagnostics, including three on existing assertions in the modified concurrency test. Attribution of the full set against the current committed tree has not been established.

**Composite hot-path measurement:** On PHP 8.5.12 CLI NTS with OPcache and JIT disabled, a synchronous depth-one attach/immediate-detach microbenchmark used the same `StackContextStorage` and scope, 20,000 warm-up pairs and 12 alternating samples of 100,000 pairs. The median increased from 1,940.57 to 2,209.06 ns/pair (13.8%); sample ranges were 1,849.77–2,219.50 and 2,116.74–3,456.29 ns/pair respectively. This is a narrow microbenchmark, not an end-to-end or concurrent workload; the high final sample indicates variability. Nested contexts, memory and other runtimes remain unmeasured.

**AsyncIt verification and tradeoff:** The ownership regression passed (11 tests, 39 assertions), including abandoned tokens; its existing memory and Swoole tests passed (10 tests, 15 assertions). AsyncIt style passed, while analysis reported 31 unrelated errors and architecture could not run due to the existing `phparkitect.php` parse error at line 257. The coroutine storage now uses weak token keys instead of scanning weak references on attach. In an actual Revolt adapter microbenchmark with 10,000 attach/detach pairs and 0, 10, 200, and 1,000 live tokens in suspended Fibers, the pre-change medians were 25.512, 33.361, 173.238, and 781.529 ms; the corresponding WeakMap medians were 24.504, 24.377, 24.904, and 25.540 ms. Each measurement used one warmup and five samples on PHP 8.5.12 CLI NTS with CLI OPcache and JIT disabled, on WSL2 x86-64. This does not measure event-loop scheduling or production throughput; a separate synthetic same-slot nesting workload still scaled with nesting depth. Further runtime, memory and nested-workload measurements remain open in #37.

**Historical verification:** On PHP 8.5, the concurrency suite passed (6 tests, 138 assertions), conformance passed (52 tests, 111 assertions), architecture and line-length checks passed, and the Swoole example ran with the extension loaded. Immediately after the approved baseline update, `composer api:check` and the composite storage test passed (4 tests, 12 assertions). The later current-tree API check is recorded above. `composer cs:check` reported existing import ordering in `tests/Support/Fixture/RunItTestKit.php`, which was not modified by this work. PHP 8.4 and OpenSwoole were not tested.

Historically AsyncIt's focused memory storage, Swoole storage and context-isolation verifier tests passed (12 tests total); RunItThirdPartySample's `composer test` passed (4 tests, 12 assertions). The later AsyncIt ownership regression covers the foreign-token collision separately.

**Tracked follow-ups:** The following issues record deferred work; linking them does not satisfy Phase 0 or Phase 1 exit criteria, resolve failed gates, or authorize a baseline update.

- [#38: Composite context storage ownership overhead](issues/composite-context-storage-performance.md) — representative performance and memory measurements and any evidence-backed optimization.
- [#37: AsyncIt context-token cleanup cost](issues/asyncit-context-storage-cleanup-performance.md) — scan replaced with weak keys; broader runtime, nested-workload and memory measurements remain open.
- [#40: Context ownership and runtime validation](issues/phase-1-runtime-validation.md) — Phase 0 evidence, supported runtime coverage, remaining isolation cases and configuration validation.
- [#39: Context refactoring quality gate failures](issues/context-refactoring-quality-gates.md) — reproduce and resolve outstanding quality and API-check results.

**Next work:** Proceed with Phase 2 preparatory mapping and focused regression tests, starting with the actual normalized artifact shapes and sensitive export paths. Phase 0 and the relevant Phase 2 design details remain prerequisites for completing dependent implementation and declaring the phase done; keep the tracked follow-ups open until their acceptance criteria are met.

## Phase 2: Publication security and artifact routing

**Depends on:** Phase 0 and the redaction/routing decisions; preparatory mapping and regression tests may proceed while the tracked Phase 0/1 follow-ups remain open. **Review:** nested throwable leakage, sampling order, lost redacted observations.

**Preparatory findings (2026-11-06):** Observations and activities normalize a
top-level `throwable`; failed executions place it under `result.throwable`.
Throwable projections contain `class`, `message`, `code`, `previous`, and `trace`.
Observation `message` is separate from its throwable. Occurrence `data` and
snapshot `payload` are key-masked only when keys are configured. The default
redactor currently handles only the top-level throwable, masks rather than omits
string traces, and has no explicit recursion bound of its own. Unknown keys and
non-array values pass through. Changed artifacts become generic projections,
which do not retain the original typed sampling or adapter interfaces.
`PublisherStackFactory` currently calls sampling before redaction. Simply
reversing it would cause type-dependent sampling and OpenTelemetry dispatch to
misclassify or drop changed artifacts. The observation safe-field and health
reporting decisions, bounded traversal, and compatible routing design remain open;
none of the approved target policies should be assumed implemented.

Focused tests now cover nested observation throwable masking, preservation of
class and code, original immutability, explicit opt-out, and PSR logger export
with configured masking. Failed execution protection, trace omission, and
composed OpenTelemetry export remain unverified or known not to meet the target.
At revision `4ed686910a41005d2df3b2bd9d430181f4cf6240` on PHP 8.5.12, the
Unit suite passed (839 tests, 5747 assertions); `composer api:check` reported no
breaks but exited nonzero for ten existing additions. Phase 0 remains incomplete.
The subsequent `composer quality` stopped at Rector's existing callable-fixture
suggestion; `composer cs:check` passed, `composer analyse` reported 147 errors,
and `composer lint:length` reported 26 violations. Both focused redaction test
files also passed on PHP 8.4.27; this does not establish full PHP 8.4
compatibility or complete the Phase 0 baseline.
On PHP 8.5.12, a subsequent Unit-only `composer coverage` run passed 843 tests
and reported 3,604/4,086 covered lines (88.20%); aggregate coverage does not
establish per-test attribution or the planned isolation guarantee.

- [ ] Map the actual `normalize()` shapes for execution results, observations, causes, and snapshots; inspect `DefaultArtifactRedactor`, `ArtifactRedactionConfig`, `RedactedArtifact`, and consumers before selecting an implementation. Establish bounded recursion and how unknown fields and unsupported values are treated.
- [ ] Add negative tests in `tests/Unit/Application/Publication/RedactingPublisherTest.php`, `tests/Unit/Adapter/Psr/Log/ObservationLoggerPublisherTest.php`, and `tests/Unit/Adapter/OpenTelemetry/Trace/ExecutionSpanMapperTest.php` for secrets in the outer message, execution `result.throwable`, multiple previous causes, and trace. Assert both intended masking and preservation of non-sensitive metadata; cover disabled/explicit opt-out configurations.
- [ ] Implement the approved recursive masking and trace policy for all applicable artifact shapes. Ensure no mutation of original immutable artifacts, no unrestricted serialization, and no secret-bearing fallback/error logs.
- [ ] Exercise actual composed pipelines to PSR-3 and OpenTelemetry with in-memory sinks, asserting secrets are absent in exported fields and throwable arrays. Include a failed execution and a redacted observation, not only a unit-level normalized array.
- [ ] In `src/Application/Factory/PublisherStackFactory.php`, align the execution order with the approved contract. Extend `tests/Unit/Application/Publication/SamplingTest.php` with a spy `SamplingPolicyInterface` and sink to prove what each receives. Test severity-floor, error-override, execution-kind, unchanged artifacts, and null-decorator combinations: `ErrorOverrideSamplePolicy` depends on `ObservationInterface`, which a generic redacted wrapper does not retain. Measure serialization costs before deployment.
- [ ] Implement the approved safe handling for redacted observations in `src/Adapter/OpenTelemetry/TelemetryPublisher.php` and its mapper path, or make a deliberate drop visible via the health path. Check changed executions, activities, and measurements as well as occurrences and snapshots, because generic wrappers may bypass typed dispatch. Avoid inferring type from an ambiguous user-controlled field or exposing the original unredacted artifact through a wrapper.
  - [ ] Test malformed/unknown redacted projections, mapped and dropped artifacts, no-provider configurations, recursive publication, original business exceptions, and third-party publishers. Keep redaction before every externally observable boundary.
- [ ] Update relevant security, publication, adapter, and configuration documentation and changelog; update schemas or public contracts only after an explicit compatibility decision.

**Exit:** sensitive marker strings do not reach the sampler or sinks except where an explicitly approved policy permits them; routing and negative tests pass, including failure paths.

## Phase 3: Bounded buffering and performance

**Depends on:** Phase 0; coordinate queue representation changes with byte-limit changes. **Review:** hard byte bound and repeated `array_shift()`.

- [ ] In `src/Application/Publication/BufferedArtifactPublisher.php`, define admission as both item-count and projected estimated-byte capacity, using overflow-safe arithmetic. Establish behavior for an item larger than the full limit and for invalid estimator results or configuration values without silently inventing public semantics.
- [ ] Expand `tests/Unit/Application/Publication/BufferingTest.php` with deterministic estimates: empty/exact/full/nearly-full buffers, oversized first item, multiple evictions, both count and byte constraints, zero or boundary values where supported, and every overflow strategy (`Fail`, `DropNewest`, `DropOldest`, `DropLowPriority`, `BlockWithTimeout`).
- [ ] Implement capacity checks on every append, including all replacement and timeout paths. Evict enough eligible entries where the approved strategy permits; otherwise drop/fail without violating the bound. Preserve priority, FIFO ordering, health notifications, counts, and exception behavior.
- [ ] Test flush, expiration, failed inner publication, timeout, close budget, repeated close, observer callbacks, and accounting after each removal. Assert `estimatedBytes() <= maxEstimatedBytes` after every accepted operation; note estimates are not exact process-memory limits.
- [ ] Benchmark existing drain/close with representative sizes and overflow/priority patterns using `composer benchmark` or a focused PHPBench benchmark. Compare a head-index or queue implementation only after preserving observable ordering and eviction behavior.
- [ ] If measurements justify it, replace repeated array shifting with an internal representation that preserves the tested behavior; benchmark again and record environment, results, and regressions. Do not claim a speedup without measurements.
- [ ] Update buffer and performance documentation, including oversized-item behavior and the distinction between estimated and actual memory.

**Exit:** admission invariants and all overflow tests pass; benchmark evidence and any remaining performance tradeoff are recorded.

## Phase 4: Failure handling and lifecycle outcomes

**Depends on:** Phase 0; open-activity behavior additionally depends on its decision gate. **Review:** silent failure, lost terminal execution, ambiguous open-activity policy.

- [ ] Trace `DefaultExecutionRunner`, `FailSafeArtifactPublisher`, `FailOpenPolicy`, and existing fallback reporter behavior. Define a recursion-safe default health sink that does not send failures back through the failing publisher, expose secrets, or replace the business outcome.
- [ ] Add failure-injection tests for no callback, successful callback, callback throwing, fallback sink throwing, inner publisher throwing, recursive publication, and artifact normalization throwing during failure reporting. Check bounded/sanitized reporting and verify no failure is silently discarded under the chosen policy.
- [ ] Implement guarded fallback reporting for the default fail-open path and guard diagnostic extraction itself. Keep fail-closed behavior explicit and preserve the original throwable and context restoration in `finally`.
- [ ] In `DefaultExecutionRunner::publishTerminal()`, publish the completed execution independently from snapshot creation and publication; preserve normal execution-before-snapshot ordering unless explicitly changed. Extend `tests/Unit/Application/Runner/ResilienceTest.php` for successful and failing business operations, snapshot factory failure, execution publication failure, snapshot publication failure, original exception identity, and context restoration.
- [ ] For `FailExecutionOnOpenActivitiesPolicy`, first add a test to `tests/Unit/Application/Policy/OpenActivityPolicyTest.php` asserting the current observation, terminal status, and original result. Implement only the approved outcome/message semantics; update lifecycle documentation and compatibility notes if status behavior changes.
- [ ] Test nested executions, open activities, repeated completion, and adapter/health failures together at the appropriate suite level. Document the chosen publication and completion failure guarantees.

**Exit:** terminal outcomes and health failures remain observable without masking business results; lifecycle and failure-injection tests pass under the approved policy.

## Phase 5: OpenTelemetry retention

**Depends on:** Phase 0 and the span-store retention decision. **Review:** unbounded span-context store.

- [ ] Trace `TelemetryPublisher::fromConfig()`, `InMemoryExecutionSpanStore`, execution/activity span mappers, and `ExecutionSpanStoreInterface`; characterize parent lookup order, lifetime, concurrency, and consumer-provided store behavior.
- [ ] Add tests with many unique executions and activities, nested and late-arriving children, duplicate IDs, and a long-lived publisher. Assert the approved bound or lifecycle, parent lookup before and after eviction, and behavior of injected third-party stores.
- [ ] Implement the approved bounded or lifecycle-aware strategy without changing a frozen contract unless explicitly authorized. Specify deterministic eviction, memory limits, and behavior for missing parents; do not retain live spans or use global state.
- [ ] Measure persistent-worker memory and mapper overhead before and after under the same workload. Update OpenTelemetry configuration and operational documentation with the retention tradeoff.

**Exit:** store growth is demonstrably bounded under the agreed policy, parent behavior is tested, and measurements are recorded.

## Phase 6: Coverage, QA, architecture, and dependency maintenance

**Depends on:** Phase 0; security/lifecycle tests from Phases 1-5 contribute to the floor. **Review:** unit coverage, attribution, failed gates, architecture scope, and abandoned dependency.

- [ ] Re-run `composer coverage` with PCOV against Unit only; parse the resulting statement numerator and denominator. Prioritize uncovered security, lifecycle, error handling, boundary, and adapter code rather than padding tests for a percentage.
- [ ] Add focused Unit tests until statement coverage is **at least 95%** over the agreed production source set. Keep integration, conformance, and concurrency tests in their own suites; document environment-dependent unreachable branches and do not silently exclude them to make the floor pass.
- [ ] Audit test-class coverage attribution using the installed PHPUnit/Codeception coverage metadata or another verified mechanism. For each test class, explicitly identify the class under test and allowed collaborators; demonstrate that unrelated indirectly executed code is not credited. Add a deliberately leaking fixture/test that causes the isolation check to fail, then keep a regression check without contaminating normal coverage.
- [ ] Implement a reproducible CI threshold check on the Unit-only report: fail on missing/invalid report, wrong suite, zero denominator, or coverage below 95%; add tests for values below, equal to, and above the threshold. Ensure separate CI runs cannot overwrite or merge broader-suite coverage into this report.
- [ ] Wire the coverage and isolation checks into package and monorepo quality workflows (`.github/workflows/quality.yml` and `../../.github/workflows/quality.yml`) with the required coverage driver. Document the exact metric, denominator, command, and fresh result in `docs/QA.md`.
- [ ] Enforce `composer api:check` in the active monorepo workflow as well as the standalone package workflow; a nested package workflow is not automatically executed by the monorepo. Keep the baseline unchanged unless a public break is deliberately approved.
- [ ] Re-run PHPStan at configured maximum level; group errors by root cause, correct invalid fixture types and production typing/imports, then eliminate remaining first-party errors without broad ignores or a new baseline. Re-run `composer cs:check` and fix only actual style failures.
- [ ] Inventory actual dependency edges and compare `phparkitect.php` against `docs/architecture/hexagonal-architecture.md`, especially `Contract\Port` and Behavior layer directions. Agree intended layers before changing rules; add architectural regression checks for forbidden dependencies without adding skips to force green.
- [ ] Update stale DepTrac references in `docs/QA.md` and architecture docs to accurately describe PHPArkitect and the effective rules. Update ADRs first if the intended boundary itself changes.
- [ ] Re-run `composer audit`, `composer audit --no-dev`, and `composer why doctrine/annotations --tree`; track the abandoned dependency through PHPBench and `just-bases/php-qa-base`. Do not imply that an abandoned development dependency is an active vulnerability or introduce unsupported pins to suppress the warning.

**Exit:** at least 95% isolated Unit statement coverage is enforced in CI, coverage attribution is verified by a negative control, PHPStan/style/architecture gates pass, and dependency status is current.

## Phase 7: Maintainability, duplication, and design inventory

**Depends on:** approved coding standards and passing behavior tests. **Review:** duplication, assembly complexity, factories/interfaces, coding rules, PhpMetrics and PHPA.

- [ ] Replace paired literal reads with existing `ArtifactField` constants in `DefaultOccurrenceLogMapper` where they represent the same keys. Add mapping tests for redacted occurrences and snapshots; assert normalized keys and public schemas do not change.
- [ ] Inventory repeated mapping/validation logic and repeated literals across adapters; classify each as shared semantics, intentional adapter-specific behavior, or unrelated coincidence. Extract a focused shared abstraction only when it removes real divergence without coupling layers or obscuring behavior. Retain before/after contract tests.
- [ ] Examine `DefaultExecutionRunner` constructor and builder assembly; map collaborators to responsibilities and identify whether an internal scope-creation seam reduces change propagation. Prototype only if it preserves constructor/API compatibility; avoid generic dependency bags and unnecessary public factories.
- [ ] Audit DI services for interface-backed seams and direct instantiations in services, distinguishing replaceable dependencies from value objects, tokens, exceptions, and composition-root construction. Record actual violations against the approved rule and remediate only with compatibility-safe, meaningful seams.
- [ ] Scan source and tests for abstract classes, multiple named types per file, missing imports, Yoda conditions, `else`/`elseif`, magic literals, declaration order, parameter layout, and lines over 120 columns. Record scope and exceptions, including comments/generated files excluded by `tools/lint/line-length.php`; implement agreed checks and fixes without accidental schema or API changes.
- [ ] Produce a **named method-level** cyclomatic-complexity inventory for every method above 10; the review's PhpMetrics class maxima do not identify those methods. Re-run class metrics and investigate outliers, particularly redaction and mapping. If the installed tool cannot provide method names, document the exact tooling gap and select a verified analyzer rather than guessing.
- [ ] Inspect PHPA assumptions individually, verify each against its contract and tests, and document actionable defects versus intentional guards. Do not remove valid token, Fiber, or null checks just to reduce a heuristic count.
- [ ] Assess existing readonly classes, Fibers, WeakMap, traits, property hooks, virtual properties, lazy objects, and weak references against real invariants and measured bottlenecks. Introduce a feature only with a concrete benefit, ownership/lifetime analysis, and tests or benchmarks where relevant.

**Exit:** agreed conventions have a reproducible inventory and checks; priority duplication and complexity issues have evidence-backed resolutions or explicit deferrals, with no public API or schema drift.

## Phase 8: Release validation and documentation

**Depends on:** all applicable earlier phases and resolved decision gates.

- [ ] Re-run narrow regression suites first, then `composer test`, `composer coverage`, `composer analyse`, `composer architecture`, `composer refactor:dry`, `composer cs:check`, `composer lint:length`, `composer validate --strict`, `composer api:check`, and `composer audit`; run `composer quality` after its prerequisites pass. Run Rector before PHP-CS-Fixer. Record exact results and skipped extension-dependent tests.
- [ ] Run targeted mutation testing for state transitions, failure handling, context restoration, redaction, sampling, buffer overflow, and adapter mappings; investigate surviving meaningful mutants rather than lowering thresholds.
- [ ] Re-run relevant hot-path benchmarks and persistent-worker tests against Phase 0 baselines. Capture metrics, environment, and any regressions; repeat concurrency tests with supported Fiber/Swoole/OpenSwoole environments where available.
- [ ] Run the third-party consumer/conformance scenario, including an external context token implementation, optional adapters, and a clean install on PHP 8.4. Verify no new mandatory runtime dependency or accidental public-symbol/schema change.
- [ ] Update `docs/CHANGELOG.md`, security/publication/lifecycle/architecture/QA documentation, examples, vocabulary references, and ADRs where behavior or public contracts actually changed. Update release records and API baselines only for explicitly approved public changes.
- [ ] Reconcile every finding in `docs/REVIEW.md` with implementation evidence, test names, command results, or a documented owner and deferral. Do not mark an unresolved decision or an unavailable test environment as complete.

**Release exit:** approved semantics are documented; security and lifecycle regressions pass; API compatibility is preserved or a deliberate versioned break is approved; Unit coverage and isolation meet their gates; quality, architecture, and audit results are recorded; remaining risks are explicit.
