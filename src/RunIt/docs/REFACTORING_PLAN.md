# RunIt Refactoring Plan

**Status:** Planning; no implementation is authorized by this document alone.

**Source:** [`REVIEW.md`](REVIEW.md), reviewed 2026-11-05. Paths below are relative to the RunIt package unless stated otherwise.

## Scope and execution rules

- [ ] Reproduce each finding against the current tree before changing it. The review is a snapshot, and dependencies were updated after its initial audit.
- [ ] Deliver each phase as small, independently reviewable changes with regression tests. Preserve existing unrelated working-tree changes.
- [ ] Preserve execution invariants, original business exceptions, redaction boundaries, optional integrations, and frozen public contracts. Do not regenerate an API or schema baseline to hide a break.
- [ ] Treat the recorded 88.18% unit statement coverage and 137 PHPStan errors as historical measurements, not current guarantees. Re-measure after dependency or source changes.
- [ ] Treat the previous six dependency advisories as superseded: the subsequent audit found no vulnerability advisories. `doctrine/annotations` remains an abandoned **development** dependency via `just-bases/php-qa-base` and PHPBench; track it separately.
- [ ] Use PHP 8.4 as the compatibility floor and run the supported newer PHP matrix where available. Record unavailable extensions or environments rather than claiming those paths passed.

### Decisions required before dependent changes

- [ ] **API identity:** Decide whether to restore the frozen opaque `ContextTokenInterface` contract or explicitly approve a breaking versioned contract. The review establishes a break, not permission to change the baseline. Retaining concrete `id()` methods does not justify requiring them from third-party implementations.
- [ ] **Redaction semantics:** Specify which throwable fields, nested causes, and traces are removed or masked, and whether explicitly configured trace export can remain enabled. Confirm whether sampling policies must receive a redacted projection and how that affects policies expecting concrete artifact interfaces.
- [ ] **Redacted observations:** Decide whether OpenTelemetry must emit a safe redacted observation or make a drop visible through a defined health policy. Specify safe fields and classification without exposing payloads.
- [ ] **Open-activity outcome:** Decide whether the policy changes terminal status or only emits a critical observation, and document how the chosen semantics preserve the business result. The existing contract and review do not decide this.
- [ ] **Span-store retention:** Decide the retention bound or lifecycle and what happens to late child/parent lookups after eviction. Establish whether the change can remain internal to the frozen public API.
- [ ] **Concurrency default:** Decide whether documentation and configuration validation suffice or whether automatic runtime detection is desired. Do not silently change the synchronous default or assume it is safe for interleaved execution.
- [ ] **Coverage attribution:** Define the exact class-under-test attribution policy, justified collaborators, and CI environment needed to enforce it. Aggregate unit-only coverage alone does not establish per-test isolation.
- [ ] **Coding standards:** Resolve the requested member ordering against the existing PHPStan rule in `docs/QA.md`; decide the scope and exemptions for factory-only construction, interfaces, one type per file, literals, imports, `else`/`elseif`, Yoda conditions, and 120-column lines. Do not assume blanket factory or interface creation is an architectural improvement.

## Phase 0: Baseline and safety net

**Depends on:** none. **Outcome:** reproducible current state and isolated regression targets.

- [ ] Record revision, dirty state, PHP version and extensions, Composer dependency state, and relevant runtime adapters. Do not overwrite or revert existing changes.
- [ ] Run `composer validate --strict`, `composer api:check`, `composer analyse`, `composer cs:check`, `composer architecture`, `composer lint:length`, `composer test`, `composer coverage`, and `composer audit`; capture exit codes and distinguish pre-existing failures from new ones. Inspect scripts in `composer.json` before changing commands.
- [ ] Preserve coverage XML and denominator from the Unit-only run; do not merge results from Integration, Conformance, or Concurrency. Record branch-coverage and extension limitations.
- [ ] Run the existing PhpMetrics and PHP Assumptions commands in `docs/REVIEW.md`, retain reports outside tracked source, and confirm which tools and versions are available after the update. Treat assumption reports as hypotheses.
- [ ] Add or identify narrowly scoped fixtures for sensitive exception chains, fake clocks and byte estimators, failing publishers/reporters, interleaved Fibers, and third-party tokens. Put cross-layer tests in the appropriate non-Unit suite.
- [ ] Record baseline compatibility and representative hot-path benchmarks before altering publication, buffering, context storage, or serialization. Capture PHP version, workload, buffer sizes, and measurement variance.

**Exit:** baseline commands and limitations are recorded; each high-priority issue has a reproducible failing test or a documented reason it cannot yet be reproduced.

## Phase 1: Compatibility and context ownership

**Depends on:** Phase 0 and the API identity decision. **Review:** frozen interface break; concurrent context isolation.

- [ ] Inspect `src/Contract/Port/ContextTokenInterface.php`, `ContextStorageInterface`, the API baseline, all `id()` call sites, and concrete stack, Fiber, coroutine, and composite tokens. In particular, `src/Application/Propagation/CompositeContextToken.php` delegates to the inner token's `id()`; removing only the interface method is insufficient.
- [ ] Add an external-style test token implementing the baseline interface without `id()` and a storage implementation that returns it. Exercise composite attach/detach, correct owner delegation, foreign token rejection, repeated detach, and LIFO behavior.
- [ ] Implement the approved compatibility approach using opaque token identity or a separately negotiated capability as appropriate. Preserve existing concrete methods where needed for shipped callers; do not require methods absent from the frozen third-party interface.
- [ ] Run context storage conformance tests and `composer api:check`. If an intentional break is explicitly approved, document versioning and migration, update `docs/CHANGELOG.md`, and regenerate the baseline only as part of that approved contract change.
- [ ] Document `StackContextStorage` as synchronous-only at the SDK builder and concurrency entry points; show configuration of Fiber/coroutine storage for persistent workers.
- [ ] Add interleaving tests that demonstrate the default risk and verify isolated current scopes, parent identity, LIFO detach, restoration after exceptions, and repeated requests with runtime-specific storage. Use the coroutine extension where available and report skips explicitly.
- [ ] If runtime misuse detection is approved, add it without global mutable state or an undocumented default behavior change; test synchronous use and interleaving separately.

**Exit:** external token implementations remain usable under the approved compatibility policy; API check and relevant conformance/concurrency tests pass; concurrency configuration is explicit.

## Phase 2: Publication security and artifact routing

**Depends on:** Phase 0 and the redaction/routing decisions. **Review:** nested throwable leakage, sampling order, lost redacted observations.

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

- [ ] Re-run narrow regression suites first, then `composer test`, `composer coverage`, `composer analyse`, `composer architecture`, `composer cs:check`, `composer lint:length`, `composer validate --strict`, `composer api:check`, and `composer audit`; run `composer quality` after its prerequisites pass. Record exact results and skipped extension-dependent tests.
- [ ] Run targeted mutation testing for state transitions, failure handling, context restoration, redaction, sampling, buffer overflow, and adapter mappings; investigate surviving meaningful mutants rather than lowering thresholds.
- [ ] Re-run relevant hot-path benchmarks and persistent-worker tests against Phase 0 baselines. Capture metrics, environment, and any regressions; repeat concurrency tests with supported Fiber/Swoole/OpenSwoole environments where available.
- [ ] Run the third-party consumer/conformance scenario, including an external context token implementation, optional adapters, and a clean install on PHP 8.4. Verify no new mandatory runtime dependency or accidental public-symbol/schema change.
- [ ] Update `docs/CHANGELOG.md`, security/publication/lifecycle/architecture/QA documentation, examples, vocabulary references, and ADRs where behavior or public contracts actually changed. Update release records and API baselines only for explicitly approved public changes.
- [ ] Reconcile every finding in `docs/REVIEW.md` with implementation evidence, test names, command results, or a documented owner and deferral. Do not mark an unresolved decision or an unavailable test environment as complete.

**Release exit:** approved semantics are documented; security and lifecycle regressions pass; API compatibility is preserved or a deliberate versioned break is approved; Unit coverage and isolation meet their gates; quality, architecture, and audit results are recorded; remaining risks are explicit.
