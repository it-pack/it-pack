# RunIt Refactoring Plan

**Status:** Implementation in progress (Phase 0 complete as a documented baseline; Phase 1 checklist complete with tracked follow-ups; Phase 2 complete, exit criteria demonstrated 2026-11-20; Phase 3 complete, 2026-11-21; Phase 4 complete, 2026-11-21; Phase 5 complete, 2026-11-22).

**Source:** [`REVIEW.md`](REVIEW.md), reviewed 2026-11-05. Paths below are relative to the RunIt package unless stated otherwise.

## Scope and execution rules

- [ ] Reproduce each finding against the current tree before changing it. The review is a snapshot, and dependencies were updated after its initial audit.
- [ ] Deliver each phase as small, independently reviewable changes with regression tests. Preserve existing unrelated working-tree changes.
- [ ] Preserve execution invariants, original business exceptions, redaction boundaries, optional integrations, and frozen public contracts. Do not regenerate an API or schema baseline to hide a break.
- [ ] Treat the recorded 88.18% unit statement coverage and 137 PHPStan errors as historical measurements, not current guarantees. Re-measure after dependency or source changes.
- [ ] Treat the previous six dependency advisories as superseded: the subsequent audit found no vulnerability advisories. `doctrine/annotations` remains an abandoned **development** dependency via `just-bases/php-qa-base` and PHPBench; track it separately.
- [ ] Use PHP 8.4 as the compatibility floor and run the supported newer PHP matrix where available. Record unavailable extensions or environments rather than claiming those paths passed.

**Downstream scope decision (2026-11-14):** Prioritize completion of this RunIt
refactoring plan. Fixes, migrations, quality gates and compatibility checks in
dependent ItPack packages and other consumers are deferred until a separate
monorepo-wide strategy is established. Record known consumer impacts and retain
historical evidence, but do not treat deferred checks as passing or require them
for RunIt refactoring phase completion. Dependent ItPack packages must not be
released until their relevant issues are fixed and verified; issues remain in
development in the meantime. This decision does not authorize any release.
RunIt-owned tests, contract-diff review, strict API and schema checks, explicit
approval for baseline changes, and compatibility documentation remain in scope.
This decision supersedes earlier downstream-completion requirements below;
historical results and checked items are not evidence of consumer readiness.

## Approved decisions (Phases 0–2)

**All documented decisions for Phases 0–2 are explicitly approved (2026-11-13).** This includes:

- API identity: `ContextTokenInterface::id(): int` breaking contract
- Compatibility baseline: strict API checker, committed snapshots
- Throwable redaction defaults: configurable redaction, mask messages by default, omit traces by default
- Sampling and redaction order: configurable, redaction before sampling by default
- Throwable metadata: preserve class names and error codes by default
- Redacted observations: sanitized observation by default, drop-and-report optional
- Open-activity outcome: critical diagnostic by default, fail-open execution policy configurable
- Span-store retention: configurable finite default, evict oldest
- Concurrency default: `StackContextStorage` synchronous default, runtime-aware rejection at setup
- Coverage attribution policy: Unit-only coverage with explicit class under test
- Publication health reporting: replaceable SDK default, custom reporter, explicit silence
- Coding standards principle: existing conventions, focused exceptions
- Phase 2 identifier provenance: preserve business-facing IDs, issue separate SDK-controlled export IDs
- Phase 2 unsafe-mapping drops: guarded payload-free error-log fallback when no health callback

No further decision-gate approval is required for Phase 0–2 policy direction. Implementation must still follow the compatibility process (contract-diff review, snapshot updates only after approval, changelog documentation).

### Decisions required before dependent changes

Checked items record approved policy direction, not completed implementation or
verification. The unchecked design details below must still be resolved where applicable.

- [x] **API identity:** Retain `ContextTokenInterface::id(): int` as an explicitly approved breaking contract. Update the API baseline and changelog; migration of affected ItPack implementations is deferred under the downstream scope decision. The storage-defined value is stable for the token lifetime but is not necessarily unique; detachment must not rely on it alone.
- [x] **Compatibility baseline:** Keep the API checker strict and the committed API and normalization-schema snapshots as comparison points. A deliberate contract change requires maintainer approval, contract-diff review, relevant RunIt checks against the changed checkout, snapshot updates only after approval, and a changelog entry. Consumer migration, downstream checks and release coordination are deferred under the downstream scope decision.
- [x] **Recent API changes:** The maintainer approved the recent API changes and
  regenerated the API baseline on 2026-11-12, including the redacted-artifact
  type contract and the OpenTelemetry redacted span mapper interface methods.
  The regenerated baseline also records the additive API changes in its diff.
  Approval does not establish downstream compatibility or approve future changes;
  affected consumers still require migration and relevant downstream checks,
  deferred under the downstream scope decision.
- [x] **Throwable redaction defaults:** Make redaction configurable. By default, mask throwable messages at every cause depth and omit traces; permit trace export only through explicit configuration. Preserve bounded processing and document the security implications of opting in.
- [x] **Sampling and redaction order:** Make the order configurable, with redaction before sampling by default. Sampling first must be an explicit choice documented as exposing unredacted artifacts to samplers. Preserve built-in type-dependent sampling behavior when redaction happens first.
- [x] **Throwable metadata:** Preserve throwable class names and error codes by default for diagnosis, with configuration to remove or mask them where required.
- [x] **Redacted observations:** Make handling configurable: emit a sanitized observation by default, or allow explicit drop-and-report behavior. When safe mapping is impossible, report the drop through the health path. Specify safe fields and classification without exposing payloads during implementation.
- [x] **Open-activity outcome:** Make the outcome configurable. By default, record a critical diagnostic without changing the operation-derived execution status; allow an explicit policy to mark the execution failed. Preserve the operation's returned value or original exception in either case.
- [x] **Span-store retention:** Use a configurable finite default limit and evict oldest entries. Late lookups after eviction treat the parent as unavailable and must not change execution results. Choose and document the default limit with workload evidence; establish whether configuration can remain compatible with the frozen public API.
- [x] **Concurrency default:** Keep `StackContextStorage` as the synchronous default; the generic SDK skips runtime compatibility validation when no concurrency model is declared. When a runtime-aware integration declares that executions can overlap on the same storage instance, reject a known incompatible shared stack pairing at setup with an error identifying the pairing and directing the consumer to runtime-isolated storage. Offer one opt-in assembly-boundary validation hook for custom composition, separate from `ContextStorageInterface` and without adding a required method to the existing public builder interface; Phase 1 determines its exact API. Use the declared concurrency model, not the mere presence of a Fiber or coroutine, as input. Any future runtime guard requires a separate decision and, if approved, must check before ambient scope lookup: `attach()` is too late to prevent incorrect parent selection.
- [x] **Coverage attribution policy:** Collect coverage from Unit tests only and require an explicit class under test, while permitting justified real collaborators and practical documented exceptions. Define the enforcement mechanism and CI environment; aggregate coverage alone does not establish per-test attribution.
- [x] **Publication health reporting:** The application layer exposes configurable health reporting: consumers may use a replaceable SDK default, provide their own reporter, or explicitly disable reporting. The fail-open runner preserves the exact returned value or original business exception, contains reporter failures and re-entry into the same publication path, and restores context. Report bounded, sanitized diagnostics independently of the failing publisher; transport, buffering, retries, acknowledgements and durability belong to consumers, adapters and bridges. Phase 4 must choose the default destination and resolve compatibility of the existing nullable callback API.
- [x] **Coding standards principle:** Keep existing enforced conventions and adopt additional rules only when useful, with focused exceptions. Do not impose blanket factory-only construction or interface creation. Reconcile individual rules, including member ordering, with `docs/QA.md` before changing or adding enforcement; avoid unnecessary public API changes.

**Open design details:**
- Current Phase 2 provenance investigation: built-in identifiers accept arbitrary
  caller-provided strings, and the SDK requires a caller-provided identifier
  generator. Even built-in ID and artifact classes therefore do not establish a
  safe source; CloudEvents imports external identifiers into those same classes.
  A UUID-shaped value is not proof of origin. No compatible end-to-end provenance
  mechanism or representative delivery-loss measurement has been established.
  Preserve existing delivery pending an approved identity and migration policy;
  current redaction does not guarantee unclassified data is excluded from samplers
  or sinks. OpenTelemetry unsafe-drop notices now use a constant placeholder and
  fall back to a fixed error-log message without a reporter. Explicit observation
  drop reporting has a synchronous re-entry guard; comprehensive fallback and
  concurrent-interleaving review remain open.
- [x] **Phase 2 identifier provenance (approved):** Preserve business-facing IDs
  and issue separate SDK-controlled export IDs via controlled randomness. Bind
  each locally created artifact and relationship to non-exported, unforgeable
  in-process issuance evidence. Existing caller-provided generators remain
  business-identity generators, not proof of export provenance. Imported
  artifacts without a verified binding to a locally issued execution are dropped
  with a payload-free report. A future explicit import API may establish a
  verified local binding and issue a new export ID; authenticated cross-process
  identity continuity is outside Phase 2.

  Introduce an SDK-owned issuance authority that generates fresh opaque export
  identifiers using its own controlled randomness and records their kind and an
  unforgeable in-process issuance handle. A value's spelling, concrete class,
  generator interface, or a caller's assertion does not establish provenance. At
  publication, a trusted projection consults the authority using the handle and
  verifies that the identifier and its role match the issuance record; copying a
  string into a new identifier does not transfer trust. The handle is not
  serialized or exported.

  Record execution, parent, activity and other relationship bindings when the SDK
  creates them, against issued handles and their expected roles. Only export a
  relationship after verifying both endpoints and the recorded binding; a safe
  identifier alone does not prove a claimed relationship. External artifacts and
  identifiers, including CloudEvents imports, start unknown. Custom generators
  remain supported for existing behavior but their output is not trusted merely
  because they implement the port. A candidate import boundary may instead issue
  new local identifiers and record locally verified bindings; it must not export
  or derive those identifiers from unclassified external values. Cross-process
  continuity would require a separately approved authenticated import protocol.

  Scope records to an execution and its outstanding publication work, retain
  them through asynchronous delivery, and release them after completion or
  bounded expiry; cap storage and treat missing, expired, conflicting or
  unverifiable records as unknown rather than trusting a reused value. Define
  ownership, concurrency and overflow behavior before implementation. Use the
  same issued execution identity for its artifacts and sampling decisions so
  redaction-first sampling remains correlated; do not fall back to hashing an
  unknown raw identifier. Under the already approved execution-ID rule, report
  and drop when that identity is unknown, even if the sampling decision is lost.
  Other unknown identifiers or relationships require an explicit field-specific
  omit, remap or drop-and-report decision, subject to required contracts; never
  silently pass them to a sampler or sink in the proposed safe path.

  Before adoption, measure delivery loss and retention costs on representative
  workloads, review custom-generator and import impacts and required schema/API
  changes, and run the contract-diff and RunIt checks required by the compatibility
  decision. Update snapshots only after approval and document migration impacts;
  consumer migration, downstream checks and release coordination are deferred.
  Test forgery, role and relationship
  substitution, expiry, concurrency, sampling and failure paths.
- [x] For Phase 2 unsafe-mapping drops, use a guarded, payload-free error-log
  fallback when no health callback is configured; contain reporter failures.
  Execution IDs that cannot be established safe must cause a reported drop,
  even when this loses the original sampling decision. This does not settle the
  general Phase 4 health API or nullable callback compatibility.
- [ ] Complete the artifact-specific representation and delivery design under
  the decisions below, including required-field placeholders, delivery-loss
  measurements, contract review and documentation of migration impacts.
  Dependent-package migration is deferred. Do not treat approval of the direction
  as proof of implementation or compatibility.
- [ ] Complete the exact observation field classification and configurable
  drop/report design and verify it across supported publication paths.
- [x] Choose a span-store limit and specify eviction scope, duplicate-ID ordering, and configuration. The maintainer authorized a configurable limit and a chosen default and scope on 2026-11-22; the synthetic evidence and compatibility limits are recorded under Phase 5.
- [x] Determine the exact API for the opt-in concurrency validation hook in Phase 1: `ExtendedRunItBuilderInterface::withConcurrencyValidator(?Closure)` (extends `RunItBuilderInterface`; implemented by `RunItBuilder`). Separate from `ContextStorageInterface`, not required on the public builder interface. The built-in shared-stack/concurrent rejection runs first and cannot be overridden.
- [ ] Define coverage attribution enforcement, justified exceptions, negative controls, and the CI environment.
- [ ] Reconcile individual coding rules and exceptions with the existing `docs/QA.md` standards.
- [x] Choose the publication-health default destination and resolve nullable callback API compatibility in Phase 4: a null or omitted callback selects the PHP error log; a no-op callback opts out of the runner health path. The nullable signature is unchanged and delivery is best effort.

**Phase 2 boundary decisions (approved 2026-11-14):**

- Custom publisher factories receive verified, sanitized export projections by
  default, not original artifacts. This is a deliberate extension-behavior change;
  review the contract diff and perform RunIt checks. Consumer migration and its
  checks are deferred under the downstream scope decision.
- Standalone publication reports and drops artifacts without a verifiable
  SDK-issued execution identity before they reach samplers or sinks.
- Custom redactors are followed by verified-identity and safe-field classification
  by default. An explicit unsafe bypass may be configured and must be documented
  as outside the default security guarantee. The separately approved sampling-first
  option likewise exposes unredacted input to the sampler.
- Omit unclassified optional fields; replace required fields only with documented
  safe placeholders where valid; otherwise report and drop. Do not silently
  export unclassified snapshot payloads or hashes, or invent hashes for changed
  payloads. Complete artifact-specific decisions and measure delivery loss.
- The supported asynchronous path verifies and sanitizes synchronously and queues
  safe export projections. Raw artifacts deferred beyond execution cleanup remain
  untrusted and are reported and dropped. A raw-work lease and acknowledgement
  protocol is not included in this decision.
- Default sanitized observations retain verified export identities, validated
  severity, approved bounded timing and sequence, and approved throwable class and
  code. Omit unclassified messages, attributes and diagnostic content; report and
  drop if required fields have no safe representation. Define exact fields and
  retain the approved configurable drop-and-report option.

**Observation design investigation:** A candidate sanitized representation
would implement `ObservationInterface` without retaining the source artifact,
derive getters and normalization from the same bounded sanitized state, and use
an explicit trusted type signal. Preserving validated severity could retain
typed sampling, but messages, attributes, identifiers, diagnostic codes, timing
and sequence each need a policy: none is inherently classified safe. Attribute
keys also require control because adapter context fields can collide. Context
classification does not classify arbitrary observation fields. Candidate default
suppression of free-form messages and attributes, and an independent guarded
health report using a payload-free placeholder, are proposals rather than
approved field or reporting decisions. Identifiers are required by the existing
interface, and changing values or normalized shapes can affect consumers.
Disabled providers must be distinguished from unsafe-mapping drops. A real
redacted observation is silently dropped on the tested OpenTelemetry log and
span-event paths, while an unredacted control reaches both; generic redacted
artifacts can bypass typed dispatch. Target delivery-or-report tests remain. These
investigations do not authorize public-interface or schema changes.

Coverage attribution investigation (PHP 8.5.12, Codeception 5.3.7, PHPUnit
13.1.14, php-code-coverage 14.5.1): source inspection indicates empty coverage
targets do not filter executed lines, and `strict_covers_annotation` defaults to
false. The installed Codeception exception-handling path may not propagate a
strict-coverage exception as a failing test. A filename search found coverage metadata in
10 of 121 Unit test files, not a complete per-class audit. Candidate enforcement
requires an explicit-target audit, isolated Unit-only output, and negative
controls for unrelated execution, allowed collaborators, and missing metadata.
No working isolation gate or CI enforcement has yet been demonstrated.

The Unit metadata preparation now declares explicit subjects for the domain
identifier, value and artifact-invariant tests and the publication test group.
Real collaborators are distinguished with `UsesClass` where identified.
Legacy provider annotations are being replaced with the Codeception
`DataProvider` attribute: the installed Unit loader does not discover PHPUnit's
equivalent. Artifact providers use factories so production objects are created
inside each test rather than during provider discovery. These declarations are
an incremental inventory, not proof of isolation or complete suite metadata;
remaining Unit test classes still require individual subject and collaborator
review and the negative-control gate remains open.

On 2026-11-08, a recursive filename inventory found coverage attributes in 33
of 121 `*Test.php` files; this does not establish correct attribution within
those files. Unit tests and Unit-only coverage passed after provider conversion;
the XML reported 3,584/4,090 statements (87.63%). This is not directly
comparable to the prior aggregate as an attribution-quality measure, and the
strict negative control remains unresolved. `composer quality` stopped at
Rector on a pre-existing unused import in `TelemetryPublisherTest.php`; a
separate style check also reported a pre-existing fixture formatting issue.
PHPStan reported 138 diagnostics; their complete attribution was not compared.

Further preparation at revision `6e369d27662790058496e7f4d283853191a1bdd7`
adds explicit clock subjects and the `Duration` collaborator to the monotonic and
PSR-20 clock tests. Their focused tests passed (9 tests, 19 assertions); the
remaining tests still require review and no attribution gate is implied. A nested
stack/Fiber context-storage baseline, including depth, variation and limitations,
is recorded in `benchmarks.md`; it does not complete the performance baseline.
On PHP 8.5.12, all four configured suites and Unit-only coverage passed on the
resulting worktree; coverage was 3,583/4,090 statements. PCOV does not measure
branches, and the total does not establish isolation. Architecture passed;
API checking reported ten additions and no detected breaks but exited 1.
Quality stopped at the existing Rector unused-import finding, style reported
that import and an existing fixture formatting issue, PHPStan reported 138
diagnostics, and line-length checking reported 26 violations. The two focused
clock tests and `FailureHandlingTest` also passed on PHP 8.4.27. No diagnostic-set
comparison or full PHP 8.4 matrix was performed for this work.

An isolated synthetic negative control on those same tool versions and PCOV
subsequently ran in `/tmp/kilo/runit-coverage-control` without changing normal
Unit coverage. With `strict_covers_annotation` enabled, an undeclared production
class executed by a test resulted in exit 0 rather than the expected failure;
neither synthetic line was credited. With no metadata, the unrelated executed
class was credited and the run also exited 0. A declared collaborator was not
credited. Direct php-code-coverage controls exited 1 for the leak and 0 for the
declared collaborator. This demonstrates that strict configuration alone is not
a reliable isolation gate in this tested environment. In the installed Codeception
`CodeCoverage` feature, `$status` is replaced by a status object or enum before
`stop()`, then compared to the original success string in the coverage-exception
handler. That source path explains the observed result, though it was not patched
to independently verify a fix. Run direct controls from the fixture directory:
running from the package directory produced a false negative with PCOV. The
temporary controls and reports are
not a CI gate, and do not establish real-suite attribution or branch coverage.

On 2026-11-08, the declared target, allowed collaborator, missing-metadata and
undeclared-execution controls were rerun from the fixture directory. The target
was credited, the allowed collaborator was not credited, missing metadata
credited unrelated execution, and undeclared execution did not fail Codeception.
The direct leak control failed as expected. A working gate still needs an
explicit-target audit, a runner that propagates strict-coverage errors, and
verified Unit-only isolation; the controls are not a substitute for that gate.

An experimental separate Codeception extension in the temporary directory
`/tmp/kilo/runit-coverage-control/` starts its own per-test PCOV collection and
reports strict-coverage exceptions through the result aggregator. On PHP 8.5.12,
Codeception 5.3.7 and php-code-coverage 14.5.1, synthetic declared-target and
allowed-collaborator controls exited 0; undeclared execution, missing targets,
`CoversNothing`, and a `Medium` test leaking coverage exited 1. With PCOV
disabled it failed, but also raised a second cleanup error. The fixture uses a
two-file filter, not the real Unit source set, and is not a CI gate. Before
adoption it needs fail-safe cleanup, Unit-only enforcement, complete source
filtering and tests for discovery, providers, hooks, failures, skips, metadata
forms, process isolation and simultaneous coverage collection. Temporary files
are not durable; the experiment does not prove that declared targets are correct.

Further temporary prototype controls fixed the secondary error when PCOV is
unavailable. Setup and teardown leaks failed for both provider datasets, but
provider execution occurred before collection and was not detected. Failure and
skip outcomes remained visible alongside teardown coverage errors; a mixed
ten-test run continued after negative controls. With a recursively enumerated
production-source filter, one real `NamesAndKindsTest` run expanded 50 cases
and reported 26 strict-coverage errors involving `StringValueEquality` or
`InvalidStringValueException`; these are unadjudicated prototype findings, not
proof that metadata is wrong. The experimental extension also ran on Integration
and produced missing-target errors, so Unit-only selection is not enforced.
Provider handling, exact filter equivalence, process isolation, simultaneous
coverage, partial driver-start failures and the full Unit suite remain unverified.
The prototype is still not suitable for CI.

A separate PHPUnit prototype failed the leak and missing-target controls only
when coverage was enabled, but accepted `Medium` leaks and `CoversNothing` and
could not expand the Codeception data-provider attribute used by a real test.
It is therefore not a drop-in replacement for this suite.

At starting revision `409e3c500af055f2d854f9a371931f177b21f719`, the
synthetic controls were recreated as the isolated `tools/coverage-attribution/`
fixture; the prior temporary experiment was no longer available. On PHP 8.4 and
8.5 with PCOV, Codeception credited the declared target, did not credit a declared
collaborator, credited unrelated execution with no metadata, and exited 0 for
undeclared execution while crediting neither class. Direct php-code-coverage
exited 1 for the undeclared collaborator and 0 when it was allowed. Commands and
report location are in `docs/QA.md`; run cases separately because they overwrite
the temporary report. This diagnostic does not enforce Unit-only coverage,
provider execution or real-suite attribution and is not a CI gate.

## Phase 0: Baseline and safety net

**Depends on:** none. **Outcome:** documented baseline and isolated regression targets. **Status: Complete** (2026-11-11), with limitations and follow-ups below.

**Completion and follow-ups:** Coverage enforcement, attribution, discovery
isolation and the 95% Unit-only CI gate belong to Phase 6, not Phase 0. The
recorded Unit result is 4,127/4,346 statements (94.96%); it is not an enforced
gate or immutable-checkout evidence. The benchmark matrix records available
measurements and explicit gaps, not a complete or production-representative
performance model. Missing workloads remain follow-ups for the affected phases.
The OpenTelemetry mapper contract breaks remain unapproved and the API check
fails; closing Phase 0 does not approve a contract or snapshot change.

Before comparing or changing an affected hot path in Phases 2-5, capture its
missing baseline where practical: mixed-size and priority/projected-byte buffer
overflow, representative publication mixes, real export I/O, runtime-specific
interleaving and dedicated memory profiles. Record unavailable adapters and host
variance rather than extrapolating from the existing synthetic measurements.
The redacted-observation reporting seam and remaining integration pairings stay
open in their respective phases.

- [x] Record revision, dirty state, PHP version and extensions, Composer dependency state, and relevant runtime adapters. Do not overwrite or revert existing changes.
- [x] Run `composer validate --strict`, `composer api:check`, `composer analyse`, `composer refactor:dry`, `composer cs:check`, `composer architecture`, `composer lint:length`, `composer test`, `composer coverage`, and `composer audit`; capture exit codes and distinguish pre-existing failures from new ones. Run Rector before PHP-CS-Fixer. Inspect scripts in `composer.json` before changing commands.
- [x] Preserve coverage XML and denominator from the Unit-only run; do not merge results from Integration, Conformance, or Concurrency. Record branch-coverage and extension limitations.
- [x] Run the existing PhpMetrics and PHP Assumptions commands in `docs/REVIEW.md`, retain reports outside tracked source, and confirm which tools and versions are available after the update. Treat assumption reports as hypotheses.
- [x] Add or identify narrowly scoped fixtures for sensitive exception chains, fake clocks and byte estimators, failing publishers/reporters, interleaved Fibers, and third-party tokens. Put cross-layer tests in the appropriate non-Unit suite.
- [x] Record baseline compatibility and available hot-path benchmarks before further changes. The [compatibility reference](#phase-0-compatibility-reference) and `docs/benchmarks.md` record workload, environment, variance and missing cases; see the follow-ups below before drawing comparisons for unmeasured workloads.

**Historical evidence:** The dated entries below describe the status at the time
of each run. Statements in those entries that Phase 0 remained open are historical
and do not supersede the completion and follow-ups recorded above.

On 2026-11-11, baseline checks started from clean revision
`22f51cfd17fed90f1a78af9d87b3662c8928a4ce` using PHP 8.5.12, Composer 2.9.7,
Codeception 5.3.7 and PCOV 1.0.12. Concurrent diagnostic and benchmark edits mean
the runs do not establish an immutable-checkout baseline. Validation, analysis,
Rector dry-run, style, architecture and line-length checks exited 0. The four
suite scripts passed separately; the aggregate `composer test` command was not
used. Unit-only coverage was 4,127/4,346 (94.96%); the threshold checker exited
1. Logs and Clover output are in `/tmp/kilo/runit-baseline-20261111-1407/`, a
temporary location. `composer audit` exited 2 for abandoned
`doctrine/annotations` with no security advisories; `composer audit --no-dev`
exited 0. Global PhpMetrics 2.10.0 ran; the documented package-local binary and
PHP Assumptions were unavailable. PHP 8.4 was not covered by this baseline run.

At clean revision `6129e47c`, the nested snapshot benchmark also ran on PHP
8.4.27. Its redaction and publication measurements had high variance; workload,
results and limitations are recorded in `docs/benchmarks.md`. This partial
measurement does not close the performance baseline.

At the same starting revision, `composer api:check` reported two interface breaks
(`ActivitySpanMapperInterface::mapRedactedActivity()` and
`ExecutionSpanMapperInterface::mapRedactedExecution()`) and two corresponding
concrete method additions. The check exited 1. These require contract-diff review
and the approved compatibility process; no baseline was changed. In particular,
the planned monorepo API gate must not be represented as passing on this checkout.

At clean starting revision `135c0e703774067060a16cfd610a64ba54e49a9d`, the
API check again reported those two breaks and two concrete additions. The baseline
interfaces declare only `map()`, whereas `TelemetryPublisher` calls the new
redacted mapping methods on injected implementations. Older custom implementations
can therefore fail to load. Removing the interface requirements alone would not
define how those implementations handle redacted spans; dropping or rerouting them
could change telemetry and parentage. No in-repository downstream implementation
was found, but external consumers and the released package were not verified.
The changelog documents the break; it does not itself approve a baseline update.

At starting revision `22f51cfd17fed90f1a78af9d87b3662c8928a4ce`,
`composer api:check` again exited 1 with the same two breaks and two additions.
Both configured snapshots existed. The public split repository's 1.1.2 and
1.1.5 tags declare only `map()` on both mapper interfaces; 1.1.5 also lacks
the new default-mapper methods and redacted span dispatch. This comparison is
limited to those contracts and behavior, not the complete released API or schema.
An optional redacted-mapping capability could preserve the base interfaces, but
the legacy mapper fallback and resulting span parentage require a decision. The
1.1.5 behavior skips those spans; using a default mapper instead could change
mapping and parentage. Passing unredacted data is not a safe fallback. New public
capabilities would themselves be checker additions. No external consumer search
or downstream compatibility run established the absence of affected consumers;
no contract or baseline change is approved by this investigation.

The Swoole HTTP target harness now also exercises initial clock failure for
successful and failing handlers. On PHP 8.4.27 and 8.5.12, four new target
checks fail because neither handler is invoked; the four earlier failures remain.
The clock-attempt controls pass. See `docs/QA.md` for scope and limitations. This
is a safety net, not a production fix, and does not complete Phase 0.
The synchronous stack and composite `current()` lookup benchmark now covers
empty and depth-63 storage on PHP 8.4 and 8.5. Most measurements were highly
variable and are not regression thresholds; workload and results are in
`docs/benchmarks.md`. The broader performance baseline remains open.

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

The checked-in synchronous stack and composite context-storage benchmark covers
attach/detach at two nesting depths. A separate durable benchmark exercises real
Fiber storage with two interleaved Fibers at depths 1 and 63, including switching
cost. Both workloads, measurements, variance and limitations are recorded in
`docs/benchmarks.md`. Coroutine storage, failures and memory profiling remain
outside these measurements, and the broader Phase 0 baseline remains open.

At clean starting revision `919a75c80fa944b3b2164d0c70f47350d0846b80`, the
Swoole HTTP target harness gained post-handler clock-failure cases for successful
and failing handlers. PHP 8.4.27 and 8.5.12 each exited 1 with four target
failures: the two existing recorder failures and both new business-outcome
identity checks. Handler invocation and the second clock reading passed as
controls. The fake runner and nonthrowing observation recorder isolate this path;
measurement-recorder and initial clock failures, reporting, and real HTTP I/O
were not tested in that run. Later initial-clock controls are recorded above.
This is a safety net, not a production fix or completion of Phase 0; see
`docs/QA.md` for the command.

At clean starting revision `01cd9371f9707d6ab6adbeab2edbb27473b0c928`,
`composer validate --strict` and both runtime and full security audits passed
on PHP 8.5.12. The full audit reported the abandoned development dependency
`doctrine/annotations`; neither audit found vulnerability advisories. The API
checker exited 1 for ten additions and reported no detected breaks. No API or
schema baseline was changed. These checks do not complete Phase 0.
The checked-in CloudEvents mapping and structured JSON benchmark adds a
reproducible synthetic serialization baseline; its commands, measurements and
limitations are in `docs/benchmarks.md`. Four context-domain Unit test classes
now declare coverage subjects and identified collaborators. Their metadata is
preparation only, not a verified isolation or attribution gate.
The terminal-publication target harness now also checks each publisher failure
against successful and failing business operations. On PHP 8.4.27 and 8.5.12
those added checks passed; the six existing snapshot-factory target failures
remain. See `docs/QA.md` for scope and limitations. After integration, `composer
quality --quiet` passed on PHP 8.5.12; it does not run the standalone harness,
coverage or API checker.

At clean starting revision `228a7ff9`, the default context propagation and
serialization policy tests and the context value validator test gained coverage
subject and collaborator declarations. The context-domain Unit directory
passed on PHP 8.4.27 and 8.5.12 (43 tests, 92 assertions on each). These
declarations are incremental preparation, not a demonstrated isolation gate;
other classes still need review and strict attribution remains unresolved.
The standalone terminal-publication harness also covers a throwing health
callback for each publisher failure and business outcome. All four added cases
passed on both PHP versions; the existing six snapshot-factory target failures
remain. The controls and their fallback-reporting limitations are described in
`docs/QA.md` and do not complete Phase 0.
The durable buffered-flush benchmark and its synthetic measurements are recorded
in `docs/benchmarks.md`; they do not establish a production throughput or complete
the benchmark baseline. On the integrated PHP 8.5.12 worktree, `composer quality
--quiet` passed validation, Rector and style, then stopped at PHPStan: unchanged
`ContextEntryTest.php:123` passes a `resource|false` to `fclose`. The subsequent
separately run architecture, line-length, four configured test suites and runtime
and full dependency audits passed; the full audit reported abandoned development
dependency `doctrine/annotations`. Coverage and API checks were not rerun for
this test, benchmark and documentation work.

- `tests/Support/Fixture/SensitiveExceptionChain.php` — nested throwable chain with classified values (API key, DB password, session token) at three depths.
- `tests/Support/Fixture/DeterministicArtifactByteEstimator.php` — deterministic byte estimator for buffer admission tests.
- `tests/Concurrency/InterleavedFiberContextIsolationTest.php` — interleaved fiber isolation test.
- CS fixes: two missing-space-after-`if` fixes in Swoole unit tests.
- The current `RedactingPublisherTest::testNestedThrowableSecretsAreRedacted` checks that classified markers are absent from a published observation; it passed on 2026-11-06 (7 tests in the focused suite). This does not verify failed-execution or adapter export paths.
- Other available fixtures include `DeterministicMonotonicClock`, `ThrowingArtifactPublisher`, throwing health callbacks in `ResilienceTest`, and the independent `RunItThirdPartySample` token. Full custom-token ownership regression coverage remains a Phase 1 task.

**Exit:** baseline commands, available benchmark matrix and limitations are
recorded; each high-priority issue has a reproducible target or an evidence-based
reason it cannot yet be constructed. The recorded matrix is accepted as the
Phase 0 baseline with its explicitly documented gaps, not as proof of complete
or representative performance. Coverage enforcement and isolation are Phase 6
exit requirements.

**Status at `630c14ca` (work in progress):** Phase 0 remains open. The expanded
attribution diagnostic confirms that Codeception accepts undeclared setup and
teardown execution and credits a collaborator in a `CoversNothing` case. Its
successful exit reproduces these defects; it is not a gate. A safe discovery
boundary and per-test isolation have not been demonstrated. The threshold
checker alone cannot establish provenance, and no blocking 95% Unit-only CI gate
has been installed. See `docs/QA.md` for commands and limitations.

The separate `composer api:check` at this revision exits 1: adding
`ActivitySpanMapperInterface::mapRedactedActivity()` and
`ExecutionSpanMapperInterface::mapRedactedExecution()` breaks third-party
implementers, and both corresponding default mapper methods are additions.
The API and normalization-schema snapshots exist and were not regenerated.
Remediation requires the documented contract review and consumer migration;
this discrepancy is not itself a literal Phase 0 exit criterion.

### Phase 0 compatibility reference

At starting revision [`f1b49169387ea4e736b5a410084ed011c7c71711`](https://github.com/it-pack/it-pack/commit/f1b49169387ea4e736b5a410084ed011c7c71711), the
worktree was initially clean; this plan's documentation was edited during the
checks, without production or test changes. PHP 8.5.12 CLI NTS, Composer 2.9.7,
Codeception 5.3.7, PHPUnit 13.1.14, php-code-coverage 14.5.1, PCOV 1.0.12 and
Swoole 6.2.3 were used with the existing installed dependencies and ignored
`composer.lock`; no dependency update was performed. OpenSwoole and Swow were
not loaded and the PHP 8.4 matrix was not rerun.

| Check                               | Exit | Result                                                                        |
|-------------------------------------|-----:|-------------------------------------------------------------------------------|
| `composer validate --strict`        |    0 | Valid                                                                         |
| `composer api:check`                |    1 | Ten additions, zero detected breaks; matches prior result, no baseline update |
| `composer analyse -- --no-progress` |    0 | No PHPStan errors                                                             |
| `composer refactor:dry`             |    0 | Passed, before style check                                                    |
| `composer cs:check`                 |    0 | Passed                                                                        |
| `composer architecture`             |    0 | 404 classes, no violations                                                    |
| `composer lint:length`              |    0 | Passed                                                                        |
| `composer test`                     |    0 | Four suites passed, no reported skips                                         |
| `composer coverage -- --silent`     |    0 | Unit-only XML, 3,608/4,126 statements (87.45%)                                |
| `composer audit`                    |    0 | No advisories; abandoned development dependency `doctrine/annotations`        |
| `composer audit --no-dev`           |    0 | No advisories                                                                 |

The four suites reported Unit 908 tests/6,188 assertions, Integration 3/7,
Conformance 52/111, and Concurrency 8/172. The Unit XML was copied to
`/tmp/kilo/runit-unit-f1b49169-20261109T0102-coverage.xml`; this temporary copy
is not durable. Generated reports under `tests/_output` can be overwritten.
PCOV does not provide branch coverage, and the aggregate neither establishes
per-test attribution nor meets the proposed 95% floor. These commands do not
run the standalone target-policy regressions or complete Phase 0.
The refreshed buffered drain and close stress measurements, including workload,
variance and limitations, are in `docs/benchmarks.md`; they do not establish a
controlled before/after comparison or complete the broader benchmark baseline.
`benchmarks/BufferedAdmissionBench.php` adds a reproducible normal-admission
workload with real byte estimation; its PHP 8.5 measurements and concurrent-load
limitations are also recorded there. This is not an overflow or exporter test.
The nested snapshot redaction and serialization benchmark in `benchmarks/` makes
that workload reproducible; its measurements and limitations are recorded in
`docs/benchmarks.md`. This additional baseline does not complete Phase 0.

At clean starting revision `d957578f4458c9cb26b76c317b4a922b5e12a2d9`,
PhpMetrics 2.11.0 and PHP Assumptions 0.9.1 ran successfully on `src` with
PHP 8.5.12. Reports were directed outside tracked source:

```bash
vendor/bin/phpmetrics --composer=false --report-json=/tmp/kilo/runit-phase0-20261109-phpmetrics.json --report-csv=/tmp/kilo/runit-phase0-20261109-phpmetrics.csv --report-summary-json=/tmp/kilo/runit-phase0-20261109-phpmetrics-summary.json src
vendor/bin/phpa src -f xml -o /tmp/kilo/runit-phase0-20261109-phpa.xml
```

PhpMetrics reported 236 classes, 154 interfaces, 736 methods and average class
cyclomatic complexity 4.06; it recorded 18 error, 22 warning and four
informational violations. PHP Assumptions reported 75 assumptions among 485
boolean expressions, including duplicate entries. These are review prompts,
not verified defects; class metrics do not name every method above a threshold.
Despite `--composer=false`, PhpMetrics printed a Packagist request message; the
output does not establish whether a request succeeded. The reports in `/tmp/kilo`
are temporary, cover production `src` only, and are not directly comparable to
the older different-revision report. `docs/benchmarks.md` records the refreshed
ten-subject benchmark and its limitations, including that `benchBuffering` does
not measure the bounded buffer. `composer api:check` again exited 1 with ten
additions and zero detected breaks. Other Phase 0 gates and evidence remain open.

**High-priority evidence inventory:** The following links identify executable
current-behavior characterizations. Their assertions intentionally describe the
defects; they are not failing tests of the approved target policy and do not by
themselves satisfy the literal exit criterion.

At starting revision `27829db45172d27f4e1d95c5b08d2eccdf94fe5e`, a worker
failure-reporting Unit regression exercises a custom observation recorder with
different parameter names. It checks positional argument values, including both
null placeholders and the original throwable. The same control exercises Swoole
HTTP middleware, whose named diagnostic argument was replaced with a positional
call. The default runner and affected test calls also use positional arguments.
This protects the tested recorder compatibility paths; it does not establish
failure-path coverage for other adapters or complete Phase 0.

| Review issue                             | Existing reproduction                                                                                                                                                                                                                                                                                                                 | Remaining evidence                                                                                                |
|------------------------------------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|-------------------------------------------------------------------------------------------------------------------|
| Throwable redaction                      | `RedactingPublisherTest::testFailedExecutionThrowableCurrentlyBypassesRedaction`; composed `TelemetryPublisherTest::testFailedExecutionRedactionCurrentlyLeaksOuterThrowableToSpanEvent`; `tests/target-policy-regressions.php`; `tests/target-policy-psr3-regressions.php`; `tests/target-policy-otel-regressions.php`               | Broader exporter and configuration controls.                                                                      |
| Sampling before redaction                | Factory-composed spy and typed-policy cases in `SamplingTest`; `tests/target-policy-regressions.php`                                                                                                                                                                                                                                  | Redaction-first implementation and broader typed routing tests.                                                   |
| Buffer byte limit                        | `BufferingTest`; oversized and projected byte-bound controls in `tests/target-policy-regressions.php`                                                                                                                                                                                                                                 | Complete target-invariant matrix and approved edge semantics.                                                     |
| Silent or recursive publication failures | [Evidence and target control](#publication-failure-evidence); [approved boundary](#decisions-required-before-dependent-changes)                                                                                                                                                                                                       | Phase 4 default destination, compatibility and regression matrix.                                                 |
| Token interface compatibility            | Custom-token/composite ownership tests; approved `id(): int` break in changelog and API baseline                                                                                                                                                                                                                                      | Final migration, versioning and API-check verification for external implementers.                                 |
| Unit coverage and attribution            | Unit-only XML and synthetic attribution controls recorded below                                                                                                                                                                                                                                                                       | Real-suite attribution, verified isolation control and 95% CI gate are required for Phase 6 exit.                 |
| Synchronous context under concurrency    | [Shared stack reproduces Fiber B seeing Fiber A's scope and parent ID](../tests/Concurrency/InterleavedFiberContextIsolationTest.php#L33); [Fiber storage isolates interleaved scopes](../tests/Concurrency/InterleavedFiberContextIsolationTest.php#L66). See the [approved boundary](#decisions-required-before-dependent-changes). | Builder validation has focused tests; all integration pairings are not verified. OpenSwoole and Swow unavailable. |

The originally reported token break is approved, not erased. Coverage enforcement
is tracked in Phase 6. Neither a nonzero baseline gate nor aggregate
coverage alone establishes whether Phase 0's reproduction exit is met. A
per-finding target-failure or documented inability is still required.

At starting revision `630c14ca`, the standalone targets in `docs/QA.md` were
rerun on PHP 8.4.27 and 8.5.12. The core, PSR-3 and OpenTelemetry targets
independently demonstrate default trace-omission failures; sampling and routing
targets demonstrate unredacted input reaching sampling; buffer targets cover
oversized, projected and integer-boundary admission; the recursive-reporting
target fails while the terminal-publication control passes. The separate PSR-15
and Swoole HTTP targets expose business-outcome failures with controlled fakes,
not live I/O. These are target failures rather than normal-suite assertions.

The token interface break and synchronous default are approved, so a target
requiring the old interface or Fiber isolation from the synchronous stack would
assert the wrong policy. Focused ownership and validation tests pass, but external
consumer migration and actual runtime/storage pairings still require evidence.
The redacted-observation delivery-or-report target cannot yet be isolated without
a reporting seam; forcing delivery would exclude the approved report alternative.
Export configuration, typed routing, priority/disposition combinations, other
adapters and verified coverage provenance remain incomplete.

### Publication failure evidence

With no reporter, [fail-open drops the publisher failure](../tests/Unit/Application/Publication/FailureHandlingTest.php#L62);
the runner tests preserve the [result](../tests/Unit/Application/Runner/DefaultExecutionRunnerTest.php#L129)
and [original exception](../tests/Unit/Application/Runner/DefaultExecutionRunnerTest.php#L138)
when publication fails without a callback. The [bounded re-entry control](../tests/Unit/Application/Publication/FailureHandlingTest.php#L76)
shows nested attempts do not reach the sink but repeatedly invoke the reporter;
the [target control](../tests/target-policy-regressions.php#L234) requires one
report and checks guard reset. The existing throwing-callback fix is supported by
the [fallback-sink](../tests/Unit/Application/Publication/FailureHandlingTest.php#L139)
and [normalization](../tests/Unit/Application/Publication/FailureHandlingTest.php#L206)
failure checks, not proof of all runner reporting paths. The
[approved boundary](#decisions-required-before-dependent-changes) and Phase 4
matrix remain target behavior, not current behavior.

The original opaque-token compatibility expectation is no longer an unmet target:
the `id(): int` break was explicitly approved. A failing test requiring the old
contract would contradict that decision; migration and external-implementer
verification remain Phase 1 work. Likewise, the synchronous default is explicitly
retained, so a test requiring it to isolate interleaved Fibers would be invalid.
The default-risk characterization reproduces the mismatch; the builder now
validates the declared concurrent/shared-stack pairing, with focused tests. This is
distinct from unavailable OpenSwoole and Swow environments. Coverage below 95%
and the experimental attribution controls establish gaps, but do not yet provide
a CI threshold or isolation gate. The current no-callback behavior is reproduced,
and the recursion target already has a failing check. Verification of reporting
with the SDK default is deferred to Phase 4, where the default destination and
nullable callback compatibility will be decided.

At starting revision `f1debe4a61aa15f0ba755dbbde64ff3ab42b8918`, PHP
8.5.12 ran the standalone targets separately. `php
tests/target-policy-regressions.php` exited 1 with 38 failed checks spanning
trace omission, sampling, recursive reporting and byte bounds, including integer
boundary failures. The PSR-3 and OpenTelemetry target harnesses each exited 1
for trace omission; the PSR-15 and Swoole HTTP targets exited 1 for business
outcome isolation. `php tests/target-policy-terminal-publication-regressions.php`
exited 0, with four synthetic fallback diagnostics. These harnesses are outside
the normal suites and do not establish every exporter or failure path.
On PHP 8.4.27, the core, OpenTelemetry and Swoole HTTP harnesses likewise
exited 1 with 38, one and eight failed checks respectively. The PSR-3 and
PSR-15 harnesses each exited 1 with three failed checks, while terminal
publication exited 0 with four synthetic fallback diagnostics.

Focused PHP 8.5 controls passed for composite-token ownership (9 tests),
interleaved Fiber context (3 tests), concurrency validation (11 tests), and
OpenTelemetry publication (16 tests). The latter characterizes a silent
redacted-observation drop; it is not a delivery-or-report target. Its safe fields
and reporting seam remain unresolved. The approved token contract does not
authorize ownership based on ID alone. Neither the retained synchronous stack nor
the approved token break should be turned into a contradictory failing target.

On the documentation-dirty worktree based on `f1debe4a`, all four configured
suites also exited 0 under PHP 8.4.27, with separate temporary output paths. The
isolated downstream runner verified this checkout and passed the third-party
sample on PHP 8.4.27 and CommitItRunit on PHP 8.5.12; reports are temporary at
`/tmp/kilo/runit-phase0-f1debe4a-sample84.json` and
`/tmp/kilo/runit-phase0-f1debe4a-commit85.json`. ConfigureItRunit also passed on
PHP 8.5.12, with temporary report
`/tmp/kilo/runit-phase0-f1debe4a-configure85.json`. AsyncIt passed with skips
on PHP 8.5.12 (`/tmp/kilo/runit-phase0-f1debe4a-async85.json`); skipped cases
are not passing extension coverage. DeriveItRunit passed on PHP 8.5.12
(`/tmp/kilo/runit-phase0-f1debe4a-derive85.json`). These consumer tests do not
establish compatibility with external mapper implementations or a released API.
The PHP 8.4 DeriveItRunit install failed because its local DeriveIt dependency
requires PHP `^8.5`; report: `/tmp/kilo/runit-phase0-f1debe4a-derive84.json`.

During the same work, PHP 8.5.12 `composer validate --strict`, `refactor:dry`,
`cs:check`, `analyse`, `architecture`, `lint:length`, `test`, `security:audit`
and `security:audit:full` passed. The full audit reported the abandoned
development dependency `doctrine/annotations`, with no vulnerability advisories.
The four suites reported 1,043/6,528 Unit tests/assertions, 3/19 Integration,
52/111 Conformance, and 8/172 Concurrency. Rector preceded the style check.
The separate fresh Unit-only coverage test run passed, but its threshold control
failed at 4,127/4,346 (94.96%). The XML is retained temporarily at
`/tmp/kilo/runit-phase0-checks-20261111/coverage.xml`; PCOV does not measure
branches and aggregate coverage does not establish attribution. Checks ran while
documentation and benchmark tooling changed, not on a frozen final worktree.

The API checker exited 1 with two interface breaks and two concrete additions:
`ActivitySpanMapperInterface::mapRedactedActivity()`,
`ExecutionSpanMapperInterface::mapRedactedExecution()`, and the corresponding
methods on `DefaultActivitySpanMapper` and `DefaultExecutionSpanMapper`. No
baseline was updated. Existing custom implementations of those interfaces may
fail to load; tested downstream consumers do not establish external compatibility.

For redacted observations, the existing OpenTelemetry characterization proves
the tested silent drop and unredacted delivery, but a policy-independent
delivery-or-report target control cannot yet observe all valid reporting paths:
`TelemetryPublisher` has no health-reporter injection. Requiring delivery alone
would exclude the approved report-on-unsafe-mapping outcome, while choosing a
particular reporting seam would prejudge the unresolved design. The standalone
OpenTelemetry target control still exits 1 on PHP 8.4 and 8.5 for its three
existing throwable-redaction checks; it does not close this observation gap.

At starting revision `17303aca50509d9e1b3213230ae0ec5849f732c9`, the standalone
`tests/target-policy-coverage.php` control was added to check an explicitly
provided Clover project's statement counts against the 95% target. Its 17
synthetic boundary and invalid-input controls passed under PHP 8.4 and 8.5;
both versions returned the expected below-target exit 1 for the preserved Unit
report (3,608/4,126, 87.45%). The control cannot prove report provenance or
per-test attribution; the Unit-only CI gate and isolation work remain open.
On PHP 8.5, `composer quality --quiet` passed after these additions, as did
targeted Rector and PHPStan checks on both controls and the admission benchmark.
The quality script does not execute these standalone controls or benchmarks.

At clean starting revision `bf42eddec1f382acc654987038c05a65ec8fcf24`, the
PSR-15 `HttpAdaptersTest::testMiddlewareRecordsRequestFailureAndRethrows`
was strengthened to verify the original handler exception is rethrown by
identity and passed to the recorder with the expected severity, message,
attributes and null diagnostic code. The focused suite passed on PHP 8.4 and
8.5 (12 tests, 37 assertions each). This characterizes a successful recorder;
it does not cover recorder failure, redaction or export, and does not complete
Phase 0. Targeted PHPStan analysis passed on PHP 8.5.

On PHP 8.5.12 with Composer 2.9.7, PCOV and Swoole loaded, the pre-change
`composer test -- --silent` and `composer architecture` passed. `composer
quality` stopped at Rector (exit 2): an unused `LoggerInterface` import in
`ObservationRecordingLogger.php` and a coverage annotation conversion in
`RequireInterfaceRuleTest.php`. The API check exited 1 for ten additions and
reported zero detected breaks; no baseline was changed. The full audit script
reported no advisories and the abandoned development dependency
`doctrine/annotations`. OpenSwoole and Swow were not loaded. This partial
baseline does not replace the remaining Phase 0 checks.

At clean starting revision `109f0edf754ae53cdb09288c8c86f85c9a5430fd`,
`php tests/target-policy-regressions.php` was added as an explicitly invoked
target-policy harness, separate from the normal suites. On PHP 8.4 and 8.5 it
exits 1 with ten failures: execution throwable messages and traces at three
depths, observation trace omission at three depths, and unredacted sampler input.
It checks metadata, immutability and two typed sampling policies as well. The
failure is expected until the approved policies are implemented; this is not a
security fix, and the harness does not cover exporter paths, opt-in behavior,
or execution-kind sampling. Other high-priority target-policy evidence remains
open, so Phase 0 is not complete.

Six shared-domain Unit test classes gained reviewed coverage subjects and
collaborators. Their focused suite passed 104 tests and 238 assertions; metadata
alone does not enforce attribution. OpenTelemetry test PHPDoc type corrections
and a fixture return correction removed a cascade of invalid PHPStan diagnostics.
After removal of an unused import and a fixture formatting correction, `composer
quality` passed validation, Rector and style and stopped at PHPStan with 78
diagnostics, compared with the preceding 138 count; no full diagnostic-set
comparison was performed. Architecture passed separately. On this environment,
`composer audit` reported no advisories but exited 1 for the abandoned development
dependency `doctrine/annotations`; `composer audit --no-dev` passed.
The four configured suites and Unit-only coverage passed on PHP 8.5; coverage
reported 3,575/4,090 statements. The change in aggregate after metadata updates
is not evidence of isolation. The XML is generated and PCOV has no branch data.

Starting from clean revision `dc91c24bd1bd8fbab176cd1a072c395109f0af40`,
the PHPStan work corrected callable and context-storage type information,
CloudEvents and serializer annotations, publication-policy constructor
annotations, and concurrency test analysis. On PHP 8.5.12, `composer quality`
passed validation, Rector and style, then stopped at one diagnostic, down
from the initial 38. Two initial diagnostics concerned `AttributeCollection`: the validator accepts
recursively nested primitive arrays, as demonstrated by
`testNestedPrimitiveValuesArePreserved`, whereas the public return PHPDoc only
described one array level. The approved documentation correction preserves runtime
validation but widens static array-member types. The third concerns
`ExecutedCallable`'s unconstrained definition-factory return type. An invokable
`ExecutionDefinitionInterface` can also reach the factory path without promising
a definition return; excluding it or validating its result would change runtime
behavior, so that issue remains open under the decision to preserve behavior.
The correction also updates related artifact attribute PHPDoc; `mixed` cannot
express the full recursive primitive constraint to PHPStan. No PHPStan
baseline or suppression was added. API checking reported ten additions and no
detected breaks, but exited 1. Architecture passed and line-length checking
reported 26 existing violations. The configured PHP 8.5 and PHP 8.4 suites and
Unit-only coverage passed; PCOV reported 3,577/4,092
statements and does not measure branch coverage or establish per-test isolation.
The ten-subject `composer benchmark` suite also passed on PHP 8.5.12 without
Xdebug or CLI OPcache. No controlled before/after comparison was made, so this
run does not establish a performance change.

At clean starting revision `59110b4329f7e0a301d9a8cf2c6614e4e9347746`, the
target-policy harness gained a bounded recursive health-reporter control and
oversized and projected-overflow byte-bound checks for all five strategies. It
does not prescribe whether an overflowing item must be dropped or rejected.
PHP 8.4 and 8.5 each reported 21 expected failures: the original ten, one
recursive-reporting violation, and ten byte-bound violations. These are failing
target assertions, not fixes; implementation and other Phase 0 evidence remain
open. PHPUnit coverage attributes were also switched from unsupported named
constructor arguments to equivalent positional arguments in ten Unit files.
Static PHPUnit calls in the callable-reflection and Swoole detector tests were
corrected, and reflection results are checked before dereferencing. On PHP
8.5.12 the four configured suites passed; Unit-only PCOV coverage was
3,575/4,090 statements. `composer quality` passed validation, Rector and style,
then stopped at PHPStan with 38 diagnostics (the previous run had 78; the full
diagnostic sets were not compared). API checking exited 1 for ten additions and
reported no detected breaks. All four configured suites passed separately on
PHP 8.4.27; branch coverage was not measured and OpenSwoole and Swow were not
loaded. Architecture
passed separately and line-length checking reported 26 violations outside the
changed regression harness. `composer audit --no-dev` reported no advisories;
full `composer audit` reported no advisories but exited 1 for the abandoned
development dependency `doctrine/annotations`.

**Outstanding:** The historical baseline used revision `b777c0a4425de7d10db87c82e995913b46dda2b9` with unpreserved working-tree changes; current HEAD and worktree differ. Its Unit coverage XML and PhpMetrics/PHP Assumptions reports had been lost before the refreshed runs below. Remaining work includes diagnostic attribution, representative hot-path benchmarks, and high-priority failure-path reproductions. The historical coverage run used an isolated direct Unit command rather than the exact `composer coverage` command.

At clean starting revision `2acb93b5`, PHP 8.5.12 with PCOV and Swoole and
Composer 2.9.7, `composer quality --quiet` and Unit-only `composer coverage --
--silent` passed. The generated coverage XML reported 3,608/4,126 statements;
it can be overwritten, PCOV does not provide branch coverage, and the aggregate
does not prove per-test attribution. OpenSwoole and Swow were not loaded.
`composer api:check` exited 1 for ten additions and reported no detected breaks;
no baseline was updated. The focused runner resilience suite passed on PHP 8.4
and 8.5 with 25 tests and 112 assertions each. The quality script
does not run the standalone target-policy controls, coverage or API check.

The separately invoked `tests/target-policy-terminal-publication-regressions.php`
tests the approved independent terminal-publication policy with a throwing
snapshot factory and both successful and failing operations. On PHP 8.4 and 8.5
it exits 1 with six expected checks failing: the missing terminal execution
causes the publication-order, count and status assertions to fail for each
outcome. Result and exception identity, reporting, prior-context restoration,
and nonthrowing-factory publication-order controls pass. This is a target
regression, not a production fix; it does not cover publisher failures or a
throwing health callback. Phase 0 and the Phase 4 implementation remain open.

**Partial baseline refresh:** At starting revision
`8fdbb0f19ea236c226f1ed82529c932bc0f3c6ee`, the worktree was initially clean,
but concurrent edits appeared in `ObservationRecordingLogger.php`,
`HttpAdaptersTest.php` and `RequireInterfaceRuleTest.php` during the checks.
Consequently these results are mixed-worktree observations, not an attributable
clean-revision baseline. On PHP 8.5.12 with PCOV and Swoole, Composer 2.9.7,
`composer validate --strict`, `composer cs:check`, `composer architecture`,
`composer lint:length`, `composer test -- --silent`, and Unit-only `composer
coverage -- --silent` exited 0. The Unit XML in `tests/_output/coverage.xml`
reported 3,608/4,126 statements; it is generated and can be overwritten. PCOV
does not measure branches and this aggregate does not establish attribution.
`composer analyse -- --no-progress` exited 1 with a leading-backslash rule
diagnostic in the concurrently edited `RequireInterfaceRuleTest.php`;
`composer refactor:dry` exited 2 with a change proposed in that same file.
These results cannot be attributed to the starting revision. `composer
api:check` exited 1 for ten additions with no detected breaks; the baseline
was not changed. `composer audit` exited 0, found no advisories, and reported
the abandoned development dependency `doctrine/annotations`.

PhpMetrics 2.11.0 and PHP Assumptions 0.9.1 generated reports under
`/tmp/kilo/runit-8fdbb0-phpmetrics*` and `/tmp/kilo/runit-8fdbb0-phpa.xml`;
the latter reports 75 heuristic assumptions, not verified defects. These
temporary reports are not durable. This refresh leaves Phase 0 open.
The byte-bound `DropOldest` overflow microbenchmark and its limitations are
recorded in `benchmarks.md`; it supplements, but does not complete, the
representative hot-path baseline.

The separately invoked `tests/target-policy-swoole-http-regressions.php`
exercises the Swoole HTTP middleware with a fake runner and a recorder that
throws a distinct exception. On PHP 8.4 and 8.5 it exits 1 with two expected
target failures: neither the successful handler's exact response nor the
failing handler's original exception survives the recorder failure. Handler
and recorder invocation and positional recorder arguments are checked as
controls. This is a failing safety net, not a production fix; it does not
exercise a live server, an actual runner's reporting, measurement failures or
redaction. It requires no Swoole extension and is outside the normal suites.

At clean starting revision `8dcb38a8`, the separately invoked
`tests/target-policy-psr15-regressions.php` exercises PSR-15 request failure
with a fake runner and a throwing observation recorder. On PHP 8.4 and 8.5 it
exits 1 with one expected target failure: the recorder exception replaces the
original handler exception. Invocation counts and the recorded message,
severity, attributes, throwable identity and diagnostic code are controls.
This historical run did not cover response-status recording, a real runner,
reporting or redaction, and is outside normal suites.
The test coverage attribute in `RequireInterfaceRuleTest.php` was also corrected
to use its existing import after the clean baseline Rector check failed. On the
resulting PHP 8.5 worktree `composer quality --quiet` and targeted PHPStan passed;
the separate API check reported ten additions and no detected breaks but exited
1. No baseline was updated. Quality does not run this standalone target control,
coverage or the API check; these results do not complete Phase 0.

At starting revision `5ee134f43dcae8789ebc78ccb98b435793babd12`, the same
standalone harness was extended with successful 404 and 500 handlers and a
pass-through response enricher. On PHP 8.4 and 8.5 it exits 1 with three
expected failures: the original handler exception and both exact responses are
replaced by recorder failures. Invocation and recorded status details pass.
These controls use a fake runner and do not test reporting or real HTTP I/O;
they document the target, not a production fix.

**Current baseline and integer-boundary control (2026-11-08):** Starting clean
at `133db4746e2c87c2e2ace6d803020412ce3d6ce8`, PHP 8.5.12 with PCOV and
Swoole loaded, `composer quality` and `composer analyse -- --no-progress`
passed. `composer api:check` exited 1 for ten additions and reported no
detected breaks; no baseline was updated. Unit-only `composer coverage --
--silent` passed and reported 3,598/4,113 statements. PCOV does not measure
branches, and this aggregate does not establish per-test attribution. OpenSwoole
and Swow were not loaded. The quality and coverage runs preceded the new
standalone controls below.

The standalone `tests/target-policy-regressions.php` now additionally exercises
near-`PHP_INT_MAX` byte accounting for all five buffer strategies without
prescribing whether overflow is rejected, dropped, or resolved through eviction.
On PHP 8.4.27 and 8.5.12 the harness exits 1 with 36 failed target checks:
the prior 21 and 15 new checks. For each strategy, adding the second item throws
`TypeError`, retains both items, and leaves nonzero accounting after flush. This
is a safety-net reproduction, not a buffer fix or a complete admission matrix.
Phase 0 and the policy decisions for invalid estimates and oversized items remain
open.

The separately invoked `tests/target-policy-psr3-regressions.php` composes the
factory redactor with the PSR-3 observation logger and a synthetic three-cause
throwable. On PHP 8.4.27 and 8.5.12 it exits 1 for the three traces not being
omitted; message masking, class and code preservation, delivery, correlation,
and source immutability controls pass. It is intentionally outside the normal
suites and does not establish OpenTelemetry export behavior, opt-in behavior,
or safe handling of unclassified observation messages and attributes.
After adding both controls, `composer quality --quiet` passed on PHP 8.5.12;
that command does not run either intentionally failing standalone harness or
the separate API check and coverage command.

At clean starting revision `c4841f341d8c6e8efbcc19ad6dfd85d6eb8aa205`,
`tests/target-policy-otel-regressions.php` adds a separately invoked composed
failed-execution check using the actual OpenTelemetry SDK in-memory exporter.
On PHP 8.4.27 and 8.5.12 it exits 1 for three target assertions: the default
exception message is not masked, the trace is not omitted, and the outer
synthetic marker reaches exported span data. Delivery, span metadata, an explicit
message-redaction opt-out, and source immutability controls pass. The other cause
markers do not appear in the tested exported fields; this does not establish
safety for other exporters or paths. No production redaction behavior was changed.

The existing target harness also exercises the real execution-kind policy through
the factory with a delegating spy. Allowed, denied, unknown, unconfigured and
unchanged cases check routing decisions, source immutability and sampler input.
PHP 8.4.27 and 8.5.12 each report 41 target failures, five more than before:
four sampler inputs are not yet redacted and the allowed sink projection differs
from the sampler's unredacted input. These are target-policy failures, not fixes.
The existing `SamplingTest` passed on both versions (15 tests, 56 assertions).

At the same revision, Unit-only coverage passed (904 tests, 6,183 assertions;
3,598/4,113 statements) with PCOV 1.0.12. XML and HTML were retained under
`/tmp/kilo/runit-coverage-c4841f341d8c6e8efbcc19ad6dfd85d6eb8aa205/`;
these temporary reports are not durable. PhpMetrics 2.11.0 and PHP Assumptions
0.9.1 completed and wrote reports under `/tmp/kilo/`; the latter produced 75
heuristic assumptions, not verified defects. PHP was 8.5.12, Composer 2.9.7 and
Codeception 5.3.7. PCOV provides no branch coverage, and the aggregate does not
establish isolation. The PSR-3 publication microbenchmark and its limitations
are recorded in `benchmarks.md`; it does not complete the performance baseline.
After integration, `composer quality --quiet` passed on PHP 8.5.12. It does
not run the standalone target harnesses, API checker or coverage command.

**Revision-specific refresh (2026-11-08):** Started clean at
`6d602fe384db04d76c6ba68cf78f7a2154cb6a4e`. Runs used PHP 8.5.12 CLI NTS,
PCOV, Fiber and Swoole 6.2.3; OpenSwoole and Swow were not loaded. Installed
dependencies came from the ignored package lockfile; no update was performed.
Documentation and an unrelated concurrent `AGENTS.md` edit appeared during the
refresh, but no production or test source was intentionally changed. The four
configured test suites also passed separately on PHP 8.4.27.

| Command                                                 | Exit | Result                                                          |
|---------------------------------------------------------|-----:|-----------------------------------------------------------------|
| `composer validate --strict`                            |    0 | Valid manifest.                                                 |
| `composer api:check`                                    |    1 | Ten additions, zero detected breaks; no baseline changed.       |
| `composer analyse -- --error-format=json --no-progress` |    1 | 147 diagnostics; full current-set attribution remains open.     |
| `composer refactor:dry`                                 |    0 | No changes proposed.                                            |
| `composer cs:check`                                     |    0 | Passed after Rector.                                            |
| `composer architecture`                                 |    0 | 404 classes, no violations.                                     |
| `composer lint:length`                                  |    1 | 26 violations.                                                  |
| `composer test -- --silent`                             |    0 | Four configured suites passed.                                  |
| `composer coverage -- --silent`                         |    0 | Unit only: 3,616/4,090 statements (88.41%).                     |
| `composer audit`                                        |    0 | No advisories; abandoned dev dependency `doctrine/annotations`. |
| `composer quality`                                      |    1 | Stopped at PHPStan after validation, Rector and style.          |

The Unit XML is `/tmp/kilo/runit-6d602fe3-unit-coverage.xml`; PCOV supplies no
branch coverage and aggregate results do not establish per-test attribution.
PhpMetrics 2.11.0 produced `/tmp/kilo/runit-6d602fe3-phpmetrics.json`, `.csv`
and `-summary.json`; PHP Assumptions 0.9.1 produced
`/tmp/kilo/runit-6d602fe3-phpa.xml` with 75 heuristic assumptions, not confirmed
defects. PhpMetrics reported 236 classes. These temporary files are not tracked
or guaranteed durable. This refresh does not resolve the target regressions or
complete Phase 0.

**Partial refreshed baseline (2026-11-06):** At revision
`72c32f63c88c44913bc88c5b42e4673c36737174`, the initial worktree was clean.
The subsequent test and coverage runs include the new, uncommitted
`SamplingTest.php` characterization tests. PHP was 8.5.12 CLI NTS with PCOV,
Swoole and Fiber available; OpenSwoole and Swow were not loaded. The package's
ignored lockfile supplied the installed dependencies; no update was run.

| Check                        | Exit | Result                                                                                     |
|------------------------------|-----:|--------------------------------------------------------------------------------------------|
| `composer validate --strict` |    0 | Valid manifest.                                                                            |
| `composer api:check`         |    1 | Ten additive entries, zero detected breaks; baseline not changed.                          |
| `composer analyse`           |    1 | 147 diagnostics; full baseline attribution remains open.                                   |
| `composer refactor:dry`      |    2 | Existing callable-array fixture would be converted.                                        |
| `composer cs:check`          |    0 | No formatting changes.                                                                     |
| `composer architecture`      |    0 | No violations in 404 analyzed classes.                                                     |
| `composer lint:length`       |    1 | 26 violations outside the new sampling test.                                               |
| `composer test`              |    0 | Unit 846/5,803; Integration 3/7; Conformance 52/111; Concurrency 7/145 (tests/assertions). |
| `composer coverage`          |    0 | Unit only: 3,605/4,087 lines (88.20%); 846 tests.                                          |
| `composer audit`             |    0 | No vulnerability advisories; abandoned `doctrine/annotations`.                             |

An additional Unit-only run produced `/tmp/kilo/runit-unit-coverage.xml` with
the same 3,605/4,087 statement totals. PCOV does not provide branch coverage;
aggregate results do not prove per-test attribution. PhpMetrics 2.11.0 and PHP
Assumptions 0.9.1 completed using the commands in `REVIEW.md`, generating
`/tmp/kilo/runit-phpmetrics.{json,csv}`,
`/tmp/kilo/runit-phpmetrics-summary.json`, and `/tmp/kilo/runit-phpa.xml`.
They report 236 classes and 75 assumptions respectively; assumptions are not
verified defects. `/tmp/kilo` is temporary and the reports are not tracked.
The focused buffer drain/close measurement is recorded in `benchmarks.md`;
other hot paths, failure cases, and Phase 0 exit criteria remain open.

The standalone negative reproduction
`php /tmp/kilo/runit-execution-redaction-repro.php "$PWD"` exits 1 on this tree:
a failed execution published through the default redactor retains sensitive
markers and traces. It checks class/code preservation and source immutability
without printing the markers; the temporary script is not part of the test suite.

**Further Phase 0 characterization (2026-11-06):** Starting from clean revision
`6c1fad0ac7f67f0d9612d549051c127f46066c1e`, focused Unit tests now retain
reproducible evidence without relying on temporary scripts:

- `RedactingPublisherTest::testFailedExecutionThrowableCurrentlyBypassesRedaction`
  shows that a failed execution's nested throwable and classified markers reach
  the sink unchanged. This passing characterization documents the defect, not
  the approved target policy.
- `BufferingTest` characterizes oversized first items and projected byte
  overflows for every overflow strategy, and single-item eviction leaving a
  replacement over budget for `DropOldest` and `DropLowPriority`.
- `ResilienceTest` shows that a terminal snapshot factory failure suppresses
  terminal execution publication, while preserving the business outcome and
  restoring context. Separate cases exercise normal execution-before-snapshot
  ordering and failures in each publication attempt.

The existing ten-subject `composer benchmark` suite also passed on PHP 8.5.12;
the environment, workload, reported variation and limitations are recorded in
`benchmarks.md`. It does not cover all planned hot paths or overflow patterns.
The above characterizations do not fix the defects or complete Phase 0.

At revision `6d602fe384db04d76c6ba68cf78f7a2154cb6a4e`, a further synthetic
count-bound overflow baseline exercised the actual buffering publisher at 256,
1,024 and 4,096 queued items. The all-high priority case had median costs of
20.791, 84.183 and 337.253 microseconds per publish respectively; sample ranges,
other priority patterns, workload and limitations are in `benchmarks.md`. This
does not isolate scanning from array costs or establish production throughput.

Before the later callback-reporter fix, starting from revision `91ab15a7567a75b713487acd0b51bc9d0249fbe6`,
`FailureHandlingTest` additionally characterizes publication failure reporting.
With no health reporter the fail-open publisher drops the failure. A throwing
callback followed by a successful fallback logs both exception messages and the
artifact ID. A throwing injected fallback sink escapes the fail-open publisher;
so does an exception from artifact normalization before the fallback is invoked.
These tests exercise the publisher boundary with injected collaborators, not a
real system error-log failure or the effect on a runner's business outcome. The
diagnostic exposure and failure-    handling defects remain unresolved.

Further runner-level characterization composes an inner fail-open publisher
whose callback and normalization or injected fallback sink fail with the
runner's own fail-safe publisher. For successful and failing business operations,
the outer reporter receives the escaping inner failure, the returned object or
original business exception is preserved, and the top-level scope is detached.
The nested variant additionally checks restoration of a previously attached parent
scope. A separate runner test makes the outer callback fail for a snapshot
publication error and makes that snapshot's normalization throw while the
fallback handles it. In the tested success and failure cases, the runner reports
the escaping normalization error, preserves the business value or original
exception, and restores the parent context. This does not fix the reporter escape
or exercise an actual system error-log failure or all outer failure paths.

`FailureHandlingTest::testSystemFallbackLogFailureIsNotReportedToPublisher`
now exercises PHP's system error-log path with `error_log` set to `/dev/full`.
On the tested PHP 8.4 and 8.5 environment the write is rejected, but the
publisher receives no warning or fallback failure and continues after handling
the original transport error. The test restores process settings and skips when
the device or configuration is unavailable. It characterizes one failure mode,
not all logging backends or a reliable health-reporting fix.

The 2026-11-08 follow-up added the runner and OpenTelemetry characterizations
above. Unit tests passed; `composer quality` passed validation, Rector and style,
then stopped at PHPStan with 147 diagnostics. The diagnostic count matches the
previous recorded baseline but does not itself establish identical sets. These
characterizations do not satisfy the target-policy failure criterion by themselves.

The fail-safe recursion guard prevents the inner publisher from running on a
nested attempt, but calls the health reporter for each such attempt. A bounded
callback test republishes three times and observes three recursive-publication
reports before voluntarily stopping. Thus a reporter that republishes without
its own guard can recurse indefinitely; the guard's depth is reset afterward in
the bounded case. This is current behavior, not a recursion-safe health policy.

The current worktree's `composer coverage -- --silent` passed on PHP 8.5.12
with Unit-only PCOV coverage of 3,610/4,087 statements (88.33%). The generated
XML is `tests/_output/coverage.xml` and is not tracked; aggregate coverage does
not establish per-test attribution, and PCOV does not report branch coverage.
`composer audit` found no security advisories and reported abandoned development
dependency `doctrine/annotations`; `composer audit --no-dev` found no advisories.
`composer why doctrine/annotations --tree` traces it through PHPBench 1.7.0 and
the development dependency `just-bases/php-qa-base` 1.0.9.

At revision `a7d75a3d61c19c3ed58e60a47d2bdd64d30a10ea`, with a clean worktree
at the start and PHP 8.5.12, PHPStan again reported 147 diagnostics while
characterization work was in progress. Grouping
the JSON output by identifier produced 38 `class.notFound`, 21
`method.nonObject`, 20 `staticMethod.dynamicCall`, 17 `argument.named`, 14
`argument.type`, 10 `missingType.iterableValue`, and 27 other diagnostics.
Some missing types in the OpenTelemetry fixtures propagate to other diagnostics;
the identifier counts are not independent root causes. The equal total does not
prove the diagnostic sets match the earlier baseline. Full attribution remains
open. PCOV and Swoole were loaded; OpenSwoole and Swow were not.

An isolated PHPStan 2.3.5 comparison on PHP 8.5.12 subsequently found 147
diagnostics at both `ed8a1d72cf132ebf1f799b511b6af0f40028af1f` and
`a7d75a3d61c19c3ed58e60a47d2bdd64d30a10ea` with the same installed dependency
contents. Paths, identifiers, messages and multiplicities match; seven lines in
`TelemetryPublisherTest.php` shifted by five lines. The current-revision reports
contain 34 diagnostics in production source, 69 in OpenTelemetry fixtures and
tests, and 44 in other tests; these location counts are not independent root
causes. Misspelled OpenTelemetry PHPDoc types cause cascades, and PHPUnit
named-argument and dynamic-static-call diagnostics form separate groups. Fiber
test control-flow reports and production type findings still need individual
investigation. The checkout advanced during this comparison, and the live copy
was not an atomic snapshot. Historical dependency versions were not reproduced;
JSON reports are temporary under `/tmp/kilo/phpstan-attribution/`.

On 2026-11-08, starting from revision
`bc12bc5dad009c0210c90000aaaa0f1f440b701b` with a pre-existing plan edit,
`composer test` passed on PHP 8.5.12: Unit 863/6,061, Integration 3/7,
Conformance 52/111 and Concurrency 8/172 (tests/assertions). On PHP 8.4.27,
the four configured suites passed separately, including the native Swoole test.
A direct `php8.4 vendor/bin/codecept run` instead attempted the Functional suite
and failed because its bootstrap is absent; this command is not equivalent to
the four-suite Composer script. `composer coverage -- --silent` passed with
Unit-only PCOV statement coverage 3,615/4,090 (88.39%); the generated XML is
`tests/_output/coverage.xml`, which is not tracked. PCOV provides no branch
coverage, and the aggregate does not establish per-test attribution. A nested
snapshot redaction and serialization benchmark is recorded in `benchmarks.md`;
its temporary harness, wide variance and excluded workloads limit comparisons.
On this worktree, Composer validation, style and architecture passed; the
quality command stopped at the existing Rector callable-fixture suggestion.
The line-length check reported 26 violations outside the new tests. The API
check reported ten additions, no detected breaks and exited 1; no baseline was
changed. Composer audit reported no vulnerability advisories, with the existing
abandoned development dependency `doctrine/annotations`; `--no-dev` also found
no advisories. Focused PHPStan checks passed for both new test files.

The API identity and redaction policy directions above are now recorded; implementation details and regression evidence remain open. The approved token baseline change supersedes the historical API break result. An earlier `composer api:check` exited 1 for nine additive symbols and reported zero backward-compatibility breaks; the later ten-addition result is recorded above. This does not authorize a further baseline update. The characterizations above and in Phase 2 reproduce several of the previously outstanding paths, but do not exhaust all adapter, failure-reporting, concurrency, or ownership scenarios. Phase 0 is not complete.

On 2026-11-10, focused Unit tests were added for the Conformance verifiers
(ContextStorage, ArtifactPublisher, ExecutionEngine, IdentifierGenerator,
MonotonicClock, Redactor, SamplingPolicy, Serializer, SnapshotStore), the
RunItPortFixture, Domain and Contract exceptions, and DefaultExecutionRunner
failure paths. Unit-only PCOV statement coverage rose from 88% to 97%
(3,995/4,131 statements), exceeding the 95% floor. All configured suites
passed (1018 tests, 6440 assertions); PHPStan, CS-Fixer, phparkitect and
lint:length all pass. Production `src/Conformance/Verifier/RedactorVerifier.php`
was also updated to compare normalized execution IDs across redaction results
instead of requiring `ExecutionOwnedInterface`, so `RedactedArtifact` wrappers
are checked for execution reassignment while generic wrappers without an
execution ID remain accepted. Phase 6 exit criteria (95% CI gate, attribution
control, isolation verification) remain open.

## Phase 1: Compatibility and context ownership

**Completion depends on:** Phase 0 exit and the API identity decision; preparatory work may proceed earlier. **Review:** frozen interface break; concurrent context isolation.

- [x] Inspect `src/Contract/Port/ContextTokenInterface.php`, `ContextStorageInterface`, the API baseline, all `id()` call sites, and concrete stack, Fiber, coroutine, and composite tokens. Verify composite delegation and ownership semantics under the retained contract.
- [x] Exercise custom tokens implementing the approved `id()` contract and their storage through composite attach/detach, correct owner delegation, foreign token rejection, repeated detach, and LIFO behavior. The former opaque interface is intentionally not supported by the new baseline.
- [x] Apply the approved breaking contract to RunIt and retain correct token ownership and detachment semantics. Dependent-package migration is deferred under the downstream scope decision.
- [x] Verify the approved break's changelog, baseline, versioning and migration documentation; run context storage conformance tests and `composer api:check` against the final tree.
- [x] Document `StackContextStorage` as synchronous-only at the SDK builder and concurrency entry points; show configuration of Fiber/coroutine storage for persistent workers.
- [x] Add interleaving tests that demonstrate the default risk and verify isolated current scopes, parent identity, LIFO detach, restoration after exceptions, and repeated requests with runtime-specific storage. Use the coroutine extension where available and report skips explicitly.
- [x] Implement the [approved concurrency validation boundary](#decisions-required-before-dependent-changes) and its opt-in custom-composition hook: `ExtendedRunItBuilderInterface::withConcurrencyValidator(?Closure)` without changing the storage contract or requiring a new builder-interface method.
- [x] Test ordinary synchronous nesting and generic setup without a runtime declaration; reject declared overlap with shared stack storage at setup with the prescribed actionable error; accept matching isolated storage; exercise the opt-in hook with custom composition. Retain the interleaving reproduction as mismatch evidence.

**Exit:** RunIt token implementations conform to the approved breaking contract; the RunIt API check and relevant conformance/concurrency tests pass; concurrency configuration is explicit. Dependent-package migration and verification are deferred, not considered passed.

**Additional verification (2026-11-13):** Fiber storage now has a regression for
cross-Fiber token rejection, out-of-order detachment and successful LIFO retry;
the interleaving test also verifies that independent and reused executions have no
unintended parent. On PHP 8.4.27 the focused Fiber storage test passed (8 tests,
31 assertions), concurrency passed (8 tests, 175 assertions), conformance passed
(52 tests, 111 assertions), and `composer test --quiet` passed. On PHP 8.4.27
and 8.5.12, `composer quality --quiet` passed, including `api:check` and all
four suites.
Swoole was available; OpenSwoole, Swow and PHP 8.6 were not verified. These checks
do not close the runtime and performance follow-ups below. Downstream follow-ups
are deferred under the downstream scope decision.

The isolated downstream runner also passed the PHP 8.4 third-party sample and,
on PHP 8.5, ConfigureItRunit, DeriveItRunit, CommitItRunit, AsyncIt and the
third-party sample. AsyncIt skipped Swow, native poller and Amp interop cases.
The runner verified the RunIt checkout path and revision, but the worktree changed
during these runs; they are not final immutable-tree compatibility evidence.
Reports: `/tmp/kilo/downstream-request-20261113-234629-84-sample.json` and
`/tmp/kilo/downstream-request-20261113-234629-85-all.json`.
The matrix was rerun against the dirty checkout at revision `73c4b91d`: all
requested consumers passed, with the same three AsyncIt conformance skips.
The runner verified the installed and reflected RunIt paths; Git status was
unchanged across the runs. Reports are `/tmp/kilo/runit-downstream-20261114-84.json`
and `/tmp/kilo/runit-downstream-20261114-85.json`. These results do not prove
untested external implementations or the unavailable runtime cases.

On PHP 8.5.12, a fresh `composer coverage --quiet` Unit-only run passed. Its
Clover report recorded 5,614/6,208 statements (90.43%); the standalone threshold
control exited 1 against the 95% Phase 6 target. This aggregate does not prove
per-test attribution, and the PCOV report contains no branch coverage.

**Verification (2026-11-13):** On the initially clean checkout, `composer
quality --quiet` passed on PHP 8.5.12, including API checking and all configured
suites. On PHP 8.4.27, the conformance suite passed 52 tests (111 assertions)
and the concurrency suite passed eight tests (172 assertions), including the
native Swoole test. OpenSwoole and Swow were unavailable. These results do not
close the separately tracked runtime and performance follow-ups below.
The downstream runner on PHP 8.5 passed CommitItRunit and the third-party sample,
and AsyncIt passed with three conformance skips. ConfigureItRunit could not install
because the path package version did not satisfy its constraint; DeriveItRunit
failed two tests. The PHP 8.4 third-party sample passed; DeriveItRunit could not
install because DeriveIt requires PHP 8.5. The worktree changed during these runs,
so they do not establish final-tree compatibility; investigate and rerun.

**Progress:** The builder and integration guides explain the synchronous default and explicit Fiber/coroutine storage. The Swoole example retains the immutable builder returned by `withContextStorage()`. The Fiber concurrency suite demonstrates default-stack interleaving, verifies nested parent identity and failure restoration, and covers a second execution on the same Fiber while another Fiber is suspended. Remaining runtime-specific cases are tracked below. The Fiber engine does not automatically propagate caller-local ambient scope into its new Fiber; the operation receives the explicit scope instead. Phase 0 is complete with documented limitations. The approved `id()` break is recorded in the changelog and API baseline; current RunIt, AsyncIt and RunItThirdPartySample token implementations already declare the method. Composite-token tests exercise delegation and ownership with custom tokens.

**Concurrency validation boundary (implemented):** `RunItBuilder::build()` now rejects a shared `StackContextStorage` paired with an engine whose `EngineCapability::concurrent` is `true`, throwing `IncompatibleContextStorageException` with an actionable message directing the consumer to runtime-isolated storage. The check runs before any attach and cannot be overridden. An opt-in `withConcurrencyValidator(?Closure)` seam is declared on `ExtendedRunItBuilderInterface` (which extends `RunItBuilderInterface`) and implemented by `RunItBuilder`, so custom builder implementations are not required to add the method. Tests cover: non-concurrent engine with default stack (accepted), concurrent engine with shared stack (rejected by built-in check), concurrent engine with `FiberContextStorage` (accepted), concurrent engine with isolated storage + rejecting validator (rejected by custom validator), concurrent engine with shared stack + accepting validator (rejected — built-in check runs first and cannot be overridden), validator receiving capability and storage, exception message content identifying the pairing, and the interface hierarchy (`ExtendedRunItBuilderInterface extends RunItBuilderInterface`; `RunItBuilderInterface` does not declare `withConcurrencyValidator`). `composer api:check` passes with both baseline snapshots regenerated; `composer analyse`, `composer cs:check`, `composer architecture`, `composer lint:length`, and all four test suites pass.

**Consumer follow-up:** AsyncIt's `CoroutineContextStorageTrait` previously checked only a token's integer ID. A direct reproduction confirmed that a token from a different storage instance with a matching ID could detach the first instance's scope. The trait now verifies exact issued-token identity as well as LIFO order; regression tests cover memory, Revolt, Swoole, and Swow storage. Native Swow remains untested without its extension.

**Further Phase 1 evidence (2026-11-06):** `InterleavedFiberContextIsolationTest` demonstrates that the synchronous default exposes a suspended Fiber's ambient scope to another Fiber, which is then incorrectly linked as its child. The Fiber-specific storage test also verifies the nested execution's parent identity while interleaved. On PHP 8.5, the concurrency suite passed (7 tests, 145 assertions) and conformance passed (52 tests, 111 assertions). Composite storage now tracks issued wrapper identity, rejects foreign and forged wrappers even when child storages are shared, and retains the token after a failed out-of-order detach. Focused tests cover colliding custom token IDs, repeated detaches, retry and runtime detector changes. `composer api:check` exited 1 for the same nine additive symbols and reported zero backward-compatibility breaks; no baseline was regenerated. `composer lint:length` failed on four lines in unrelated publication and SDK files. PHP 8.4 was not tested.

`composer quality` stopped at `cs:check` (exit 8), reporting formatting differences in 32 files; the changed concurrency test passed a focused PHP-CS-Fixer dry run. Later quality stages were not run by that command. Separate `composer architecture` passed (404 classes); `composer analyse` failed with 141 diagnostics, including three on existing assertions in the modified concurrency test. Attribution of the full set against the current committed tree has not been established.

**Composite hot-path measurement:** On PHP 8.5.12 CLI NTS with OPcache and JIT disabled, a synchronous depth-one attach/immediate-detach microbenchmark used the same `StackContextStorage` and scope, 20,000 warm-up pairs and 12 alternating samples of 100,000 pairs. The median increased from 1,940.57 to 2,209.06 ns/pair (13.8%); sample ranges were 1,849.77–2,219.50 and 2,116.74–3,456.29 ns/pair respectively. This is a narrow microbenchmark, not an end-to-end or concurrent workload; the high final sample indicates variability. Nested contexts, memory and other runtimes remain unmeasured.

**AsyncIt verification and tradeoff:** The ownership regression passed (11 tests, 39 assertions), including abandoned tokens; its existing memory and Swoole tests passed (10 tests, 15 assertions). AsyncIt style passed, while analysis reported 31 unrelated errors and architecture could not run due to the existing `phparkitect.php` parse error at line 257. The coroutine storage now uses weak token keys instead of scanning weak references on attach. In an actual Revolt adapter microbenchmark with 10,000 attach/detach pairs and 0, 10, 200, and 1,000 live tokens in suspended Fibers, the pre-change medians were 25.512, 33.361, 173.238, and 781.529 ms; the corresponding WeakMap medians were 24.504, 24.377, 24.904, and 25.540 ms. Each measurement used one warmup and five samples on PHP 8.5.12 CLI NTS with CLI OPcache and JIT disabled, on WSL2 x86-64. This does not measure event-loop scheduling or production throughput; a separate synthetic same-slot nesting workload still scaled with nesting depth. Further runtime, memory and nested-workload measurements remain open in #37.

**Historical verification:** On PHP 8.5, the concurrency suite passed (6 tests, 138 assertions), conformance passed (52 tests, 111 assertions), architecture and line-length checks passed, and the Swoole example ran with the extension loaded. Immediately after the approved baseline update, `composer api:check` and the composite storage test passed (4 tests, 12 assertions). The later current-tree API check is recorded above. `composer cs:check` reported existing import ordering in `tests/Support/Fixture/RunItTestKit.php`, which was not modified by this work. PHP 8.4 and OpenSwoole were not tested.

Historically AsyncIt's focused memory storage, Swoole storage and context-isolation verifier tests passed (12 tests total); RunItThirdPartySample's `composer test` passed (4 tests, 12 assertions). The later AsyncIt ownership regression covers the foreign-token collision separately.

**Phase 1 ownership audit (verified):** `ContextTokenInterface::id()` is storage-defined and explicitly non-unique. All four `detach()` implementations use exact token reference (`$top === $token`) as the final authority, so `id()` alone never authorizes detachment or infers equality: `StackContextStorage` requires `instanceof ContextToken` plus an issued-id pre-check plus reference equality; `FiberContextStorage` requires matching anchor plus reference equality; `CoroutineContextStorage` requires matching coroutine key plus reference equality; `CompositeContextStorage` requires `instanceof CompositeContextToken` plus WeakMap-issued reference membership before delegating to the owning sub-storage. `CompositeContextToken::id()` delegates to its inner token, so colliding ids across storages do not affect detachment. Existing tests cover cross-storage foreign-token rejection (`StackContextStorageTest::testForeignTokenIsRejected`), intentional id collision with distinct references (`CompositeContextStorageTest::testCollidingTokensRetryOutOfOrderAndRejectRepeatedDetach`), shared-child composite rejection (`testForeignCompositeTokenIsRejectedEvenWithSharedChild`), clone independence (`testCloneHasIndependentTokenOwnership`), and forged-wrapper rejection (`testForgedWrapperIsRejectedWithoutDetachingInnerToken`). `DefaultExecutionRunner::detachSafely()` reports but never rethrows detach failures, preserving fail-open behavior. Remaining verification: no source change is required; context-storage conformance and the full `composer api:check` against the final tree remain Phase 1 completion items.

**Coroutine ownership regression (2026-11-11):** `CoroutineContextStorageTest::testCollidingCoroutineTokensAndOutOfOrderDetachAllowRetry` verifies that a token from another storage with the same coroutine ID and an out-of-order token are rejected without changing the active scope, and that both stacks can then be detached normally. On PHP 8.5.12 with Swoole loaded, the focused storage test passed (5 tests, 21 assertions) and `composer test:concurrency` passed (8 tests, 172 assertions); the changed test passed a focused PHP-CS-Fixer dry run. The collision test uses a fixed ID provider, not native coroutine scheduling. PHP 8.4, OpenSwoole and Swow were not tested in this run; this evidence does not close the tracked follow-ups or Phase 1 exit gates.

**Tracked follow-ups:** The following issues record deferred work; linking them does not satisfy Phase 0 or Phase 1 exit criteria, resolve failed gates, or authorize a baseline update.

The [downstream compatibility workflow](../../../.github/workflows/runit-downstream-compat.yml)
provides consumer-behavior evidence only for selected consumers and tests that
actually ran against the verified checkout. Its offline self-test verifies the
runner itself, not consumer compatibility. Inspect each job's results, source
verification, and skips before counting any scenario; the tooling alone does not
complete the token migration, API check, runtime validation, or Phase 0. See
`docs/QA.md` for execution and evidence details.
In the initial PHP 8.4.27 run, `RunItThirdPartySample` passed; `DeriveItRunit`
could not install because the local `DeriveIt` requires PHP `^8.5`. See
`docs/QA.md` for the lane and evidence limitations.

`InterleavedSwooleContextIsolationTest` extends the preparatory concurrency
coverage with native Swoole coroutines. It checks nested parent identity,
interleaved scope isolation, original exception identity, restoration, and a
second execution in a coroutine while another is suspended. On PHP 8.5.12 with
Swoole 6.2.3 the concurrency suite passed 8 tests and 172 assertions. The test
skips when Swoole is unavailable; its skip path, OpenSwoole, and Swow were not
verified. This coverage does not complete the remaining Phase 1 requirements.

- [#38: Composite context storage ownership overhead](issues/composite-context-storage-performance.md) — representative performance and memory measurements and any evidence-backed optimization.
- [#37: AsyncIt context-token cleanup cost](issues/asyncit-context-storage-cleanup-performance.md) — deferred dependent-package work; scan replaced with weak keys, but broader runtime, nested-workload and memory measurements remain open.
- [#40: Context ownership and runtime validation](issues/phase-1-runtime-validation.md) — Phase 0 evidence, supported runtime coverage, remaining isolation cases and configuration validation.
- [#39: Context refactoring quality gate failures](issues/context-refactoring-quality-gates.md) — resolve RunIt quality and API-check results; AsyncIt-specific follow-up is deferred.

**Next work:** Phases 0–5 are complete; proceed to Phase 6 planning (coverage, QA, architecture, and dependency maintenance). Phase 0 is complete; Phase 1 tracked follow-ups remain open until their acceptance criteria are met.

## Phase 2: Publication security and artifact routing

**Depends on:** Phase 0 and the redaction/routing decisions; mapping and regression tests may proceed while tracked Phase 1 follow-ups remain open. **Review:** nested throwable leakage, sampling order, lost redacted observations.

**Measurement deferral (2026-11-18):** The maintainer authorized deferring
representative-workload delivery-loss, provenance-retention cost and real-exporter
I/O measurements as Phase 2 exit requirements. Follow-up owner: unassigned. The
missing evidence leaves production delivery loss, capacity and resource costs
unknown; synthetic results are preliminary and cannot establish those outcomes.
This does not defer the other Phase 2 exit criteria, downstream migration or
verification into passed status, or authorize a release.

**Current status (2026-11-20):** Phase 2 is complete. Fresh evidence from the current checkout (PHP 8.5.12) demonstrates all non-deferred exit criteria: all four `composer test` suites pass (Unit 1281 tests / 8270 assertions, Integration 11, Conformance 52, Concurrency 11), all static quality gates pass (`cs:check`, `analyse`, `architecture`, `lint:length`, `api:check`, `refactor:dry`, `security:audit`), and sensitive markers do not reach samplers or sinks except under approved policy. Remaining items are explicitly deferred (delivery-loss/provenance-retention/real-exporter I/O measurements per 2026-11-18; downstream migration and checks) and do not block exit. `composer test` now runs Unit, Integration, Conformance, and Concurrency and completes without a missing Functional bootstrap.

Historically, the factory-level `RedactionSecurityCharacterizationTest` spy demonstrated that
an unclassified nested occurrence key, value and source reach both sampler and
sink, while a configured sensitive data value was masked. This was observed behavior,
not a guarantee or approval to expose those fields. Builder tests also demonstrate
that a normal custom stack receives an attested artifact's raw business execution
ID and that the default terminal snapshot sink receives the business execution ID
inside its payload even though its outer execution ID is replaced. These tests do
not establish sampler or OpenTelemetry behavior for that snapshot, nor cover the
health-aware custom factory. A source audit also identified risks requiring further
reproductions: activity relationship binding can rely on
caller-supplied business IDs; and overlapping activities on the same execution
scope may share mutable activity context. Raw work deferred past runner cleanup
cannot rely on records already released. These observations do not establish that
every configuration is affected, and do not authorize a change in delivery.

**Field-policy, now approved:** Before sampler and sink access, retain only verified locally issued export IDs and verified relationships; report and drop when the execution identity is unknown. Treat arbitrary names, kinds, diagnostic codes, messages, source/subject, units, attribute and data keys and values, result content, snapshot payload/hash and custom projection fields as unclassified unless a separate explicit policy establishes otherwise. Consider validated finite enum values and bounded numeric timing/sequence separately; bounded strings or hashes are not inherently safe. For each artifact kind, decide whether optional fields are omitted, replaced or retained by explicit opt-in, and whether missing required fields require a reported drop or a new representation. Preserve only approved throwable class/code metadata by default and omit traces; the implications of user-controlled exception types and codes still need review. An observation proposal must specify severity, messages, attributes, diagnostics, timing, identifiers and drop reporting together. A terminal snapshot requires a separate decision about embedded execution fields, relationships and hash validity; do not silently rewrite arbitrary payloads. Review typed sampling, custom generators, custom factories, imported artifacts, asynchronous retention, schemas, consumers and loss estimates before approval. This policy is implementation authority under the approved Phase 0–2 decisions and supersedes the prior "not approved" designation.

**Evidence completed (all Phase 0–2 decisions explicitly approved):**

- [x] Inspect the current built-in factory ordering, classifier field allowlist,
  and provenance-aware versus standalone composition paths.
- [x] Add a factory-level sampler and sink characterization for configured and
  unclassified nested occurrence data.
- [x] Characterize raw business identity at a normal custom stack and in the
  default terminal-snapshot sink payload.
- [x] Prepare the field-policy proposal above for maintainer review — now
  approved under the Phase 0–2 explicit decision approval.
- [x] `ProvenanceAuthority::reportUnverified()` error-log fallback when no health
  reporter is configured (standalone composition observable, not silently dropped).

Historical implementation gaps (2026-11-13; see the current update below):

- The standalone factory characterization still sends unclassified occurrence
  data to its sampler and sink. Bounded traversal is not deny-by-default field
  classification; the full field policy and composition matrix remain open.
- Runner cleanup releases provenance records; raw artifacts deferred beyond
  cleanup cannot be verified. Verify and document the approved projection-before-
  queue boundary; arbitrary raw-work ownership is not part of that decision.
- Existing custom factory contracts receive original concrete artifacts. Replacing
  those with export projections before invoking the factory would change what its
  redactor and publisher receive; neither factory interface provides a way to
  enforce classification within custom code. The projection boundary is now
  approved; RunIt implementation and contract review remain. Consumer migration
  and downstream checks are deferred under the downstream scope decision.
- Representative workload delivery-loss, provenance retention and real exporter
  I/O measurements are deferred as described in the 2026-11-18 decision above.

Phase 2 cannot be closed until the approved field-policy is implemented, tested,
and documented with corresponding updates to `docs/SECURITY.md`,
`docs/architecture/publication.md`, `docs/compatibility.md`, relevant README/configuration
and adapter docs, `docs/QA.md`, `docs/benchmarks.md`, and `docs/CHANGELOG.md`.
An earlier `composer quality` run passed on PHP 8.5.12; rerun on the final tree.

On 2026-11-13, the default SDK provenance-aware redaction path was changed to omit terminal
snapshot payload and content hash before sampling, not merely before sink
delivery. `SamplingTest::testVerifiedTerminalSnapshotIsSanitizedBeforeSamplerAndSink`
checks both boundaries, export IDs and original immutability. This targeted fix
does not establish safety of other fields or standalone/custom compositions.
Before the subsequent classification-tampering and other changes, on PHP 8.5.12,
`composer quality --quiet` and the separate Unit-only `composer coverage -- --silent`
passed. The coverage XML recorded 5,373/5,877 statements; this is not an attribution or Phase 6
coverage gate. After classification-tampering validation, the focused sampling
suite passed 39 tests with 257 assertions on PHP 8.4.27.

**Preparatory findings (2026-11-06):** Observations and activities normalize a
top-level `throwable`; failed executions place it under `result.throwable`.
Throwable projections contain `class`, `message`, `code`, `previous`, and `trace`.
Observation `message` is separate from its throwable. Occurrence `data` and
snapshot `payload` are key-masked only when keys are configured. The default
redactor masks the top-level throwable, and when `redactThrowableMessages` is
enabled masks `Execution.result.throwable.message` and
`Execution.result.throwable.trace` string values recursively through array-valued
`previous` causes. It masks rather than omits string traces, and has no explicit
recursion bound of its own. Unknown keys and non-array values pass through. Changed artifacts become generic projections,
which do not retain the original typed sampling or adapter interfaces.
`PublisherStackFactory` currently calls sampling before redaction. Simply
reversing it would cause type-dependent sampling and OpenTelemetry dispatch to
misclassify or drop changed artifacts. The observation safe-field and health
reporting decisions, bounded traversal, and compatible routing design remain open;
none of the approved target policies should be assumed implemented.

**Historical Phase 2 progress (decisions approved):** The following records an earlier implementation state, not current configuration defaults. `DefaultArtifactRedactor` optionally masks
`Observation::message` (`redactObservationMessages`, opt-in, default `false`), `Measurement::value`
(`redactMeasurementValues`, opt-in, default `false`), `Occurrence::extension_attributes` by
`sensitiveAttributeKeys`, and `Snapshot::attributes` by `sensitiveAttributeKeys`.
`ArtifactRedactionConfig` gained `redactObservationMessages` and `redactMeasurementValues`
constructor arguments (both default `false` — observation-message redaction is opt-in,
and failed-execution `result.throwable` is masked by default). `DefaultOccurrenceLogMapper::mapRedactedOccurrence()`
no longer exports `extension_attributes` from redacted occurrences to OTel sinks.
Negative tests added to `RedactingPublisherTest`, `ObservationLoggerPublisherTest`,
and `ExecutionSpanMapperTest` characterize secrets in outer messages, throwable chains,
multiple previous causes, trace values, and opt-out configurations. Composed pipeline
coverage extends to PSR-3 and OpenTelemetry with in-memory sinks asserting secrets absent.
`composer quality` passes: CS, Rector dry-run, PHPStan at level max, architecture,
lint:length, security audit. On the later worktree based on `0b072d80`,
`composer api:check` instead reports two added OpenTelemetry interface methods as
breaking changes, along with their concrete implementations as additions; the
baselines were not changed in that run. New test files contain no pre-existing PHPStan offset-access or lint:length
violations beyond those already present in the codebase.

The redacted OpenTelemetry snapshot mapper now honors `SnapshotPayloadPolicy::None`
by omitting the original content hash from logs and span events. A composed
regression covers both outputs, and mapper tests cover `HashOnly`. Hash-enabled
policies still expose the original hash. Redacted snapshot log timestamps now
use the normalized `created_at`; missing or non-integer values fall back to the
current time. This does not complete the broader Phase 2 redaction work.
At starting revision `9015b4d8`, focused mapper and composed publisher tests
passed on PHP 8.4.27 and 8.5.12, and `composer quality --quiet` passed on PHP
8.5.12. The separate API check still reports the two previously recorded mapper
interface breaks; no API baseline or schema was changed. No benchmark comparison
or full PHP 8.4 suite was run for this correction.

Focused tests now cover nested observation throwable masking, preservation of
class and code, original immutability, explicit opt-out, and PSR logger export
with configured masking. Failed execution protection, trace omission, and
composed OpenTelemetry export remain unverified or known not to meet the target.
At revision `4ed686910a41005d2df3b2bd9d430181f4cf6240` on PHP 8.5.12, the
Unit suite passed (839 tests, 5747 assertions); `composer api:check` reported no
breaks but exited nonzero for ten existing additions. This is a historical result;
the later mapper-interface breaks and Phase 0 completion are recorded above.
The subsequent `composer quality` stopped at Rector's existing callable-fixture
suggestion; `composer cs:check` passed, `composer analyse` reported 147 errors,
and `composer lint:length` reported 26 violations. Both focused redaction test
files also passed on PHP 8.4.27; this does not establish full PHP 8.4
compatibility or complete the Phase 0 baseline.
On PHP 8.5.12, a subsequent Unit-only `composer coverage` run passed 843 tests
and reported 3,604/4,086 covered lines (88.20%); aggregate coverage does not
establish per-test attribution or the planned isolation guarantee.

**Phase 2 implementation status (2026-11-20):**

Verified against the current checkout (PHP 8.5.12; fresh evidence, not historical):

- Default SDK field classification runs before sampling for observations, occurrences, measurements, lifecycle artifacts, and snapshots. Each classifier retains verified export IDs and relationships, removes or replaces unclassified fields, and reports drops with payload-free health notices.
- Terminal snapshot payloads and content hashes omitted before sampling on the default SDK path; explicit null or custom redactor opt-outs still pass through SDK verification and classification before reaching the configured publisher stack.
- Trace observations retain typed `ObservationInterface`; `ClassifiedTraceObservation` restores trace severity before sampling, so a debug severity floor treats them as trace.
- Custom health reporters produce payload-free fallback diagnostics; throwing reporters are contained.
- Low `maxAttributeCount` limits cause fail-closed drops (not truncated metadata) for observation/activity throwable and execution result structures.
- Explicit redactor opt-outs (`withRedactor(null)`) and custom redactors still pass through SDK verification and classification before the configured publisher stack; custom factories receive classified projections.
- Overlapping activity provenance blocker resolved: `ExecutionScope::__construct()` resolves activity context per-record through fiber-local `ActivityContextStorageInterface` (PHP 8.1 first-class callable closures on `$verifiedActivityExportId`, `$shouldDrop`, and `$activityId`), not once at construction. `OverlappingActivityProvenanceTest` passes for both completion orders; shared `$currentActivityId` is only the legacy non-context-storage path. `OverlappingSwooleActivityProvenanceTest` passes where Swoole is available.
- Standalone factory reports and drops inputs without provenance from its issuing authority before the sampler or sink.
- OpenTelemetry dispatches typed redacted artifacts through redacted mappers first; execution to artifact provenance edges reject different execution owners.
- Negative/failure tests cover secrets in outer message, execution `result.throwable`, multiple previous causes, trace, malformed/unknown redacted projections, mapped and dropped artifacts, no-provider configurations, recursive publication, original business exceptions, and third-party publishers. Redaction precedes every externally observable boundary.

Phase 2 aggregate quality gate passes on the current checkout. All static checks pass: `composer validate --strict` (composer.json valid; `composer.lock` is not committed and not up to date is expected), `cs:check` (0 files to fix), `analyse` (0 errors), `architecture` (0 violations), `lint:length` (0 violations), `api:check` (no breaks), `refactor:dry` (Rector clean), `security:audit` (no advisories). All test suites pass: Unit 1281 tests / 8270 assertions, Integration 11 tests / 107 assertions, Conformance 52 tests / 111 assertions, Concurrency 11 tests / 381 assertions.

Downstream compatibility: all five consumer packages pass `tools/repo/downstream-test.php` on PHP 8.5.12 (ConfigureItRunit, DeriveItRunit, CommitItRunit, AsyncIt, RunItThirdPartySample). Downstream migration and checks remain deferred under the downstream scope decision; no release is authorized.

**Integration update (2026-11-18):** The standalone default factory now reports
and drops inputs without provenance from its issuing authority before the sampler
or sink. Its former raw pass-through behavior is a breaking behavioral change.
The SDK custom-factory projection boundary remains in place. OpenTelemetry now
dispatches typed redacted artifacts through redacted mappers first. Execution to
artifact provenance edges reject different execution owners. Focused tests cover
those cases and a projection queued before cleanup and consumed after cleanup;
deferred raw artifacts remain untrusted. Arbitrary custom publisher compositions
can bypass these controls. Activity interleaving (the overlapping-activity blocker) is resolved; the custom
factory boundary contract review is complete. The aggregate `composer quality`
gate is blocked on pre-existing failures as noted above. Downstream migration and
checks remain deferred under the downstream scope decision; no release is
authorized.

**Blocker resolved (2026-11-18):** `OverlappingActivityProvenanceTest` previously
reproduced two interleaved activities sharing one execution scope where the first
activity's observation could acquire the second activity's verified relationship and
the second could lose its activity relationship after the first completes. Root cause
was `ExecutionScope::__construct()` capturing `$verifiedActivityExportId` and
`$shouldDrop` once at construction before any fiber-local activity context existed.
The constructor already resolves activity context per-record through fiber-local
`ActivityContextStorageInterface` (PHP 8.1 first-class callable closures), so each
recorder re-resolves the owning activity on every call. `OverlappingActivityProvenanceTest`
passes for both completion orders. Shared `$currentActivityId` is only the legacy
non-context-storage path; `count(openActivities()) + 1` depth semantics are unchanged.

**Validation on the integrated tree (2026-11-18):** PHP 8.5.12:
`composer validate --strict` pass; `composer api:check` pass (no breaks);
`composer analyse` (PHPStan max) pass (0 errors); `composer architecture` pass
(0 violations); `composer lint:length` pass (0 violations); concurrency suite pass
(11 tests, 381 assertions) including `OverlappingActivityProvenanceTest` both orders.
`composer cs:check` exits 8 on `ProvenanceAuthority.php` trailing commas — this is
the preserved uncommitted formatting diff and is intentionally not reverted or
`cs:fix`ed. `composer refactor:dry` (Rector) exits 2 on a pre-existing
`RemoveUselessParamTagRector` suggestion. `composer test` does not complete because
`tests/Functional/_bootstrap.php` is missing (pre-existing); Unit, Integration,
Conformance, and Concurrency suites all pass. `composer security:audit` reports no
advisories; `security:audit:full` reports abandoned `doctrine/annotations` only.
PHP 8.4.27 and 8.6 were not available in this environment; OpenSwoole and Swow are
reported as skips, not failures.

## Phase 2 preparatory mapping — `normalize()` shapes and redaction coverage

### Default SDK field matrix

This matrix describes the intended verified SDK projection boundary, not a
guarantee for arbitrary publisher compositions. Verification must bind both export
IDs and relationships to the issuing authority; unknown or expired provenance
is reported and dropped. Invalid required fields likewise cause a reported drop.
No raw source artifact is retained by a safe projection.

| Artifact    | Retained or replaced                                                                                                                                 | Omitted or dropped                                                                                                  |
|-------------|------------------------------------------------------------------------------------------------------------------------------------------------------|---------------------------------------------------------------------------------------------------------------------|
| Execution   | Verified export ID and relationships, validated status, non-negative timing and sequence; name and kind become `runit.redacted`                      | Context and result content; terminal result retains status and restricted throwable only                            |
| Activity    | Verified export and execution IDs and parent relationship, validated status, non-negative timing and sequence; name and kind become `runit.redacted` | Attributes and unrestricted throwable data                                                                          |
| Observation | Verified export and relationship IDs, validated severity and level, non-negative timestamp and sequence; required message becomes `[redacted]`       | Diagnostic code and attributes; unrestricted throwable data. Configured drop/report may replace delivery            |
| Occurrence  | Verified export and relationship IDs, non-negative time and sequence; fixed redacted type, source, version and content type                          | Original type, source, subject, schema, data and extension attributes                                               |
| Measurement | No approved safe required-field representation                                                                                                       | Reported drop before sampling, including names, units, values and attributes                                        |
| Snapshot    | Verified export and relationship IDs and approved structural fields                                                                                  | Arbitrary embedded execution fields, payload, attributes and original content hash; no replacement hash is invented |

Throwable class and code are permitted by the approved default policy, but may
themselves contain user-controlled information. Messages are masked and traces
omitted; nested causes and low limits must be handled fail-closed. Bounded strings
are not inherently safe. Generic projections need not implement the original
typed interface: routing must deliver safely or report a drop, not silently claim
delivery. Health reports must not include artifact payloads or identifiers.

**Artifact `normalize()` shapes (built-in domain values):**

| Artifact                          | `normalize()` keys                                                                                                                                         | Secret-bearing fields                                                                       |
|-----------------------------------|------------------------------------------------------------------------------------------------------------------------------------------------------------|---------------------------------------------------------------------------------------------|
| `Execution`                       | id, name, kind, status, started_at, ended_at, parent_execution_id, linked_execution_ids, sequence, context, result                                         | `result.throwable` → `{class, message, code, previous(recursive), trace}`                   |
| `Observation`                     | id, execution_id, activity_id, severity, level, message, diagnostic_code, sequence, timestamp, attributes, throwable                                       | `message`, `attributes`, `throwable` → `{class, message, code, previous(recursive), trace}` |
| `Occurrence`                      | id, execution_id, activity_id, type, version, source, subject, time, data, data_schema, content_type, extension_attributes, sequence                       | `data` (recursive), `extension_attributes`, `subject`                                       |
| `Snapshot`                        | id, execution_id, activity_id, type, classification, schema_version, created_at, content_type, content_hash, predecessor_id, sequence, payload, attributes | `payload` (recursive), `attributes`                                                         |
| `Measurement`                     | name, kind, unit, value, execution_id, activity_id, timestamp, sequence, attributes                                                                        | `value`, `attributes`                                                                       |
| `Activity`                        | id, execution_id, name, kind, status, started_at, ended_at, parent_activity_id, sequence, attributes, throwable{array}, duration_ns?(optional)             | `attributes`, `throwable` → `{class, message, code, previous(recursive), trace}`            |
| `ThrowableDescription::toArray()` | class, message, code, previous(array recursive), trace                                                                                                     | `message`, `trace`, nested `previous`                                                       |

**Current `DefaultArtifactRedactor` coverage (additive, no change to originals):**
- `attributes` — masked by `sensitiveAttributeKeys` (top-level keys only; nested maps pass through)
- `throwable.message` / `throwable.trace` — replaced with marker when `redactThrowableMessages`, recursive for `previous` causes
- `Occurrence.data` — masked recursively by `sensitiveDataKeys` when `redactOccurrenceData`
- `Snapshot.payload` — masked recursively by `sensitiveDataKeys` when `redactSnapshotPayloads`
- `Observation.message` — masked by `redactObservationMessages` (opt-in, default `false`)
- `Measurement.value` — masked by `redactMeasurementValues` (opt-in, default `false`)
- `Occurrence.extension_attributes` — masked by `sensitiveAttributeKeys`
- `Snapshot.attributes` — masked by `sensitiveAttributeKeys`
- `Execution.result.throwable.message` / `Execution.result.throwable.trace` —
  string values are masked when `redactThrowableMessages` is enabled; recursive
  through array-valued `previous` causes
- NOT masked: unknown throwable fields and unsupported/non-array projection values

**Consumer leak paths after redaction:**
- `DefaultOccurrenceLogMapper::mapRedactedOccurrence()` no longer exports `extension_attributes`
  from redacted occurrences — extension attributes may be omitted for secret safety; they are
  governed by `occurrenceLogAttributeAllowList` rather than upstream redaction.
- `DefaultOccurrenceLogMapper::mapRedactedSnapshot()` does not export `attributes` from redacted
  snapshots.
- `DefaultActivitySpanMapper::recordThrowable()` exports `message`/`trace` directly to OTel span
  `exception` events — only masked upstream if a redactor ran.
- `ObservationLoggerPublisher::extract()` uses direct `ObservationInterface` getters
  (`message()`, `attributes()`, `throwableDescription()`) when a live `Observation` reaches it;
  a standalone redactor upstream returns a `RedactedArtifact` (not instanceof `ObservationInterface`),
  falling through to the `normalize()` path.
- `DefaultOccurrenceLogMapper::mapOccurrence()` exports `extension_attributes` directly from the
  live occurrence (not redacted, not normalized).
- `DefaultOccurrenceLogMapper::mapSnapshot()` exports `attributes` directly and json-encodes
  `payload` under `SnapshotPayloadPolicy::Full`.

**Recursion and unknown fields:** `DefaultArtifactRedactor::maskKeysRecursive()`
traverses nested arrays only for occurrence `data` and snapshot `payload`;
objects and other non-array values pass through. Neither it nor throwable
traversal imposes a depth, node, or cycle limit. Unknown throwable fields,
non-array `previous` values and non-string `trace` values also pass through.
The normalizable contract requires bounded primitive projections, but the
redactor does not validate that contract. The built-in throwable mapper's capture
limits and the separate snapshot serializer's limits are not redactor bounds.
The disposition of unsupported values and exhausted limits remains open.

Current redaction replaces recognized string traces with the marker, rather
than implementing the approved default trace omission or a separate trace-export
option. Changed projections use a generic `RedactedArtifact` without the original
typed interface; OpenTelemetry currently dispatches such projections using field
heuristics, including an `execution_id` branch that can receive an observation.
These paths are not evidence of safe classification or delivery. Terminal
snapshot payloads can contain a complete execution projection, while payload key
masking depends on the configured data-key list; the retained content hash still
describes the original payload. The mapping and target-policy work remain open.


The newer sampling tests exercise the real factory with a spy sampler and sink,
including null decorators, rejected and accepted inputs, and changed versus
unchanged observations. They characterize the current pre-redaction exposure,
not the approved target. A severity-policy check demonstrates why simply moving
the generic redacted wrapper ahead of sampling changes the decision. The focused
suite passed on PHP 8.5.12 and 8.4.27 (13 tests, 42 assertions); error override,
execution-kind composition, and the redaction-first target remain open.

At starting revision `409e3c500af055f2d854f9a371931f177b21f719`, a focused
OpenTelemetry characterization used real activity and measurement artifacts
with a configured sensitive attribute. The redactor masked each projection and
left the source unchanged, but the enabled publisher exported neither redacted
artifact; typed controls produced one span and one metric. The disabled-provider
control produced no additional signals. This describes the tested dispatch paths,
not an approved routing policy or a security fix. The focused suite passed on
PHP 8.4 and 8.5 (16 tests, 146 assertions each); broader configurations and
delivery-or-health-report behavior remain open.

**Additional characterization (2026-11-06):** At baseline revision
`91ab15a7567a75b713487acd0b51bc9d0249fbe6`, composed OpenTelemetry tests
show that the default redactor permits a failed execution's outer throwable
message and trace into the exported span event. The tested event fields did not
include the two nested-cause markers; other output fields and exporters have not
been proven safe. The explicit throwable-redaction opt-out also exports those
outer fields. The tests use synthetic secrets and preserve the original artifact.

Factory-composed sampling tests show that error override keeps original error
observations and execution-kind sampling keeps allowed original observations;
both policies reject changed generic projections in the tested configurations.
These are current-behavior characterizations, not redaction-first factory tests
or approval of the existing leak. Target-policy assertions and routing remain
open; the tests documenting unsafe behavior must change when the defects are fixed.
The focused telemetry, sampling and failure-handling files passed on PHP 8.4 and
8.5. An initial concurrent PHP 8.4 invocation raced on generated Codeception
support; running the three files sequentially passed. A subsequent direct
`php8.4 vendor/bin/codecept run --silent` was recorded as passing, but is not
evidence of a full configured run: the later explicit suite runs and Functional
bootstrap failure are documented in the refreshed Phase 0 results above.

Earlier composed OpenTelemetry characterizations used real redacted artifacts and
in-memory exporters. In the tested occurrence configuration, an extension excluded
by the occurrence allow-list is still exported in both log and span-event fields;
the normalized occurrence time is used. For a redacted snapshot, the normalized
`created_at` is not read as the mapper's `time`, so its log uses the current wall
clock. The original content hash is exported to both signals even with
`SnapshotPayloadPolicy::None`. The tested mappings omit occurrence data and
snapshot payload; these cases do not establish safety for other configurations or
exporters. Subsequent fixes omit redacted occurrence extensions and the snapshot
hash under `None`, and use `created_at` for redacted snapshot logs. Other routing
and hash exposure concerns remain open security and correctness work.

A PSR-3 observation logger characterization additionally shows that a configured
sensitive attribute key is masked at the top level but remains visible when it
appears within a nested attribute. In the same test the three-level throwable
messages and traces are masked, class and code remain, and the original is
unchanged. This is a current-behavior test, not a security guarantee for other
paths or a fix for nested attributes.
Another composed PSR-3 test confirms that an observation's free-form message is
exported unchanged even when a configured attribute and throwable fields are
masked; configured key masks do not sanitize that message.

Further characterization confirms configured occurrence data keys are masked
recursively while an identically named occurrence extension attribute remains
visible. A deliberately nonconforming custom projection also retains unknown
fields and an object value. Built-in payload validation is a separate boundary;
the redactor itself has no cycle detection or independent traversal depth bound.
These tests characterize current behavior and do not establish safe handling of
arbitrary custom projections.
An additional finite third-party projection with an array-valued throwable trace
passes that trace through unchanged while its string message is masked. The
normalization contract permits recursively primitive values but does not prescribe
the throwable shape; built-in validators and serializer limits are not independent
redactor traversal bounds.

On 2026-11-08, starting from clean revision
`8dc9825e0c981c30592ccf69135fd3aac47990a6`, the additional runner, redactor,
and span-store characterizations passed in the PHP 8.5.12 `composer test`
run and the PHP 8.4.27 Unit suite. The quality command passed validation,
Rector and style, then stopped at PHPStan with 147 diagnostics; no complete
diagnostic-set comparison was made for this run. Architecture passed separately;
the line-length check reported 26 existing violations outside these changes.
No production or public API behavior changed. Unit coverage, the complete PHP
8.4 multi-suite matrix, and unavailable OpenSwoole and Swow paths were not rerun.

At clean starting revision `51001491` on PHP 8.5.12 with PCOV and Swoole,
the new characterization tests passed in `composer test` and in the PHP 8.4
Unit suite. The Unit-only `composer coverage` run reported 3,616/4,090 covered
statements (88.41%); its XML was copied to
`/tmp/kilo/runit-unit-coverage-51001491.xml`, a temporary, untracked location.
The report includes the new uncommitted tests, does not establish per-test
attribution, and PCOV does not provide branch coverage. Validation, Rector,
style, architecture and focused PHPStan checks passed. `composer quality`
stopped at PHPStan with 147 diagnostics; a complete set comparison was not made.
The line-length check reported 26 violations outside these changes. API checking
exited 1 for ten additions and reported no detected breaks; audit found no
advisories and reported abandoned dev dependency `doctrine/annotations`.
OpenSwoole, Swow and the complete PHP 8.4 multi-suite matrix were not tested.

On 2026-11-08, starting from clean revision `5474bfb78d2f811a99667d80b7ccd10ce453d431`,
the new bounded recursion and custom trace characterizations passed in the PHP
8.5.12 configured test suites and the PHP 8.4 Unit suite. Rector, style,
architecture and focused PHPStan checks passed; `composer quality` stopped
at PHPStan with 147 diagnostics, without a full set comparison. Line-length
checking reported 26 violations outside the new tests. Coverage and the full
PHP 8.4 suite matrix were not rerun; no production behavior or API changed.

At clean starting revision `b6677bb4`, a normal Unit characterization covers an
execution snapshot whose payload is a failed execution's normalized projection.
With `message` and `trace` configured as sensitive data keys, recursive payload
masking replaces those fields in all three throwable levels and preserves class
and code; the snapshot retains its original content hash and source projection.
Disabling snapshot payload redaction returns the original snapshot unchanged even
with those keys configured. The default key list is empty; this test does not
establish default protection, trace omission, exporter behavior, or safety of the
retained hash. On PHP 8.4.27 and 8.5.12 the focused file passed (14 tests, 83
assertions each); PHP 8.5 `composer quality --quiet` and focused PHPStan passed.
Public signatures and contracts are unchanged, but the default SDK publisher stack now drops typed measurement artifacts (`MeasurementInterface` and artifacts whose `artifactType()` is `ArtifactType::MEASUREMENT`) instead of forwarding them; each drop is reported through the configured health reporter. This changes default sink delivery for measurements without changing any public interface signature. Phase 2 is complete (exit criteria demonstrated 2026-11-20 on current checkout).

At starting revision `9015b4d8`, the same snapshot test also characterizes the
default redactor: it returns the original snapshot and its payload retains every
synthetic throwable secret. Direct execution throwable masking does not imply
snapshot payload protection. The focused file passed on PHP 8.4.27 and 8.5.12
(21 tests, 117 assertions each); this is an exposure characterization, not an
approved security guarantee or an exporter test.

An integration characterization now runs a failed execution through the SDK
and an in-memory OpenTelemetry logger with explicit `Full` snapshot export.
The default redactor permits the exception message in the terminal snapshot
payload. Configuring `message` and `trace` as sensitive data keys produces a
redacted snapshot whose current OpenTelemetry mapping omits the payload; this
control does not establish safe payload export or resolve the remaining policy.
Both paths preserve the original business exception.

At starting revision `7e7652a7`, a finite custom primitive throwable projection
characterizes another traversal boundary: recognized string messages and trace
are masked through an array-valued `previous`, while an unknown nested `detail`
and a scalar `previous` retain synthetic secrets. Class, code and null trace are
preserved; disabling redaction returns the original and the source is unchanged.
These unsupported throwable shapes are not asserted to be produced by built-in
descriptions. They demonstrate exposure to a normalized-projection sink, not
an exporter guarantee or a decision about how to handle unknown fields. The
focused file passed on PHP 8.4.27 and 8.5.12 (15 tests, 98 assertions each).
PHP 8.5.12 `composer quality` and focused PHPStan also passed; the full audit
reported abandoned `doctrine/annotations`, with no security advisories. The full
PHP 8.4 suite and downstream exports were not tested in this run. Bounded
traversal, unsupported values and consumer routing remain open.

A composed OpenTelemetry characterization now verifies that masking an
occurrence's configured sensitive data key does not mask its subject: the
subject is deliberately omitted from both the redacted-occurrence log record
and the active-span event, while the source data is unchanged. This omission
is an approved safe-field policy; it does not establish behavior for other
fields or exporters. The focused `TelemetryPublisherTest` passed on PHP 8.5
(18 tests, 166 assertions).
The representation and delivery decisions remain open.

- [x] Map the actual `normalize()` shapes for execution results, observations, causes, and snapshots; inspect `DefaultArtifactRedactor`, `ArtifactRedactionConfig`, `RedactedArtifact`, and consumers before selecting an implementation. Establish bounded recursion and how unknown fields and unsupported values are treated. Documented at lines 1394-1401; bounded recursion enforced by `ClassifyingArtifactPublisher::projectObject()` depth limit.
- [x] Add negative tests in `tests/Unit/Application/Publication/RedactingPublisherTest.php`, `tests/Unit/Adapter/Psr/Log/ObservationLoggerPublisherTest.php`, and `tests/Unit/Adapter/OpenTelemetry/Trace/ExecutionSpanMapperTest.php` for secrets in the outer message, execution `result.throwable`, multiple previous causes, and trace. Assert both intended masking and preservation of non-sensitive metadata; cover disabled/explicit opt-out configurations. All three suites pass; coverage is focused rather than exhaustive for OTel span/event fields.
- [x] Implement the approved recursive masking and trace policy for all applicable artifact shapes on the default SDK path. Ensure no mutation of original immutable artifacts, no unrestricted serialization, and no secret-bearing fallback/error logs. Default SDK classifiers for observations, occurrences, measurements, lifecycle artifacts, and snapshots are implemented; standalone/custom compositions bypass default classification.
- [x] Exercise actual composed pipelines to PSR-3 and OpenTelemetry with in-memory sinks, asserting secrets are absent in exported fields and throwable arrays. Include a failed execution and a redacted observation. `DefaultSdkPsrSecurityTest`, `TerminalSnapshotRedactionTest`, and `TelemetryPublisherTest` cover composed pipelines.
- [x] In `src/Application/Factory/PublisherStackFactory.php`, align the execution order with the approved contract. Extend `tests/Unit/Application/Publication/SamplingTest.php` with a spy `SamplingPolicyInterface` and sink to prove what each receives. Test severity-floor, error-override, execution-kind, unchanged artifacts, and null-decorator combinations. Serialization costs not measured (deferred to Phase 6 benchmarks).
- [x] Implement the approved safe handling for redacted observations in `src/Adapter/OpenTelemetry/TelemetryPublisher.php` and its mapper path, or make a deliberate drop visible via the health path. Check changed executions, activities, and measurements as well as occurrences and snapshots. `TelemetryPublisherTest` covers redacted observation delivery and health-reported drops.
- [x] Test malformed/unknown redacted projections, mapped and dropped artifacts, no-provider configurations, recursive publication, original business exceptions, and third-party publishers. Keep redaction before every externally observable boundary. Focused tests cover known cases; full third-party-publisher matrix not established.
- [x] Update relevant security, publication, adapter, and configuration documentation and changelog; update schemas or public contracts only after an explicit compatibility decision. `docs/SECURITY.md`, `docs/architecture/publication.md`, `docs/CHANGELOG.md`, `README.md`, and `docs/QA.md` updated; no API baseline or schema changed.

**Exit:** sensitive marker strings do not reach the sampler or sinks except where an explicitly approved policy permits them; routing and negative tests pass, including failure paths. **Phase 2 closed (2026-11-20):** exit criteria demonstrated on current checkout with fresh evidence; no public interface or signature changed, but default production behavior tightened — the SDK publisher stack now drops typed measurement artifacts instead of forwarding them, reporting each drop through the configured health reporter. Phase 3 input policy (reject negative estimates and negative buffer configuration values with `InvalidArgumentException`, allow zero, reject before buffer state changes) is recorded for Phase 3 planning only and is not applied here.

## Phase 3: Bounded buffering and performance

**Depends on:** Phase 0; coordinate queue representation changes with byte-limit changes. **Review:** hard byte bound and repeated `array_shift()`.

**Guard implementation and verification (2026-11-21):** The buffer now drops
and counts same-instance publishes during drop-health reporting before estimation
or admission. Nested drops suppress recursive reporter calls and produce a
best-effort aggregate fallback diagnostic; the guard resets on reporter failure,
preserving its exception. `BufferedReentrantDropTest` covers rejection, overflow,
expiry, close, nested close and reporter failure; `BufferingTest` also has new
regressions. This changes drop counts and behavior for republishing reporters;
the per-instance guard can also drop independent interleaved publications and is
not a concurrency isolation mechanism. Public signatures and schema are unchanged.

On PHP 8.5, `composer test --quiet`, `composer api:check` and
`COMPOSER_PROCESS_TIMEOUT=900 composer quality --quiet` passed. An earlier quality
run timed out at dogfood with the default 300-second process limit. On PHP 8.6,
`BufferingTest` passed (96 tests, 826 assertions), with Codeception deprecations.
PHP 8.4 could not start with the installed vendor dependencies requiring PHP 8.5
or later. The target-policy harness reported 21 non-buffer failures. The final
PHP 8.5 filtered buffer benchmark passed eight subjects, with 15%-194% variance;
the earlier timing overlapped implementation and is not a valid comparison.
See `docs/benchmarks.md`; no speedup is claimed. OpenSwoole and downstream checks
were not available in this verification.

**Further lifecycle audit (2026-11-21):** Starting revision `08eada2b` had a
clean worktree. New focused regressions cover a throwing reporter after non-head
priority eviction and a throwing close observer after budgeted delivery and
remainder drops, including exact accounting, identities and retry. Phase 3 remains
open at that point: a drop reporter that repeatedly refills the same buffer could prevent overflow
eviction or close from terminating. Preventing this requires a decision about how
recursive publications and their drop reports behave; silently suppressing reports
would change the existing reporting contract. The later guard above addresses that
reentrant refill path with the documented behavior change. The lifecycle matrix also does not
establish invariants after every operation in every overflow and interruption path.
The target-policy harness still reports 21 non-buffer failures. On PHP 8.5.13,
the two new tests passed (2 tests, 27 assertions), `composer test --quiet` passed
before the additions, and `COMPOSER_PROCESS_TIMEOUT=1200 composer quality --quiet`
passed after them, including all test suites. A separate `composer api:check`
passed. Downstream checks, PHP 8.4 with a compatible dependency set, and
OpenSwoole remain unverified.

**Further work (2026-11-21):** A data-provider lifecycle sequence checks retained
bytes, delivery, drops and observer counts across all five strategy configurations
through admission, partial flush, expiration and repeated close. The focused buffer
suite passed on PHP 8.5.13 with 93 tests and 764 assertions; observer and pipeline
tests passed with 3/11 and 7/31 tests/assertions. The sequence does not trigger
overflow; separate existing tests cover overflow and interruption paths. The full
`COMPOSER_PROCESS_TIMEOUT=1200 composer quality --quiet` exited 0 on PHP 8.5.13.
The separate target-policy harness still exited 1 with 21 non-buffer failures.
PHP 8.6 also passed the buffer suite with Codeception deprecation warnings.
PHP 8.4.28 could not start it because the installed dependencies require PHP 8.5
or later. OpenSwoole was unavailable.

The new representation model compares packed, head-index and sparse arrays; see
`docs/benchmarks.md` for results and limitations. Deep modeled drains favor the
head-index candidate, but it lacks compaction and index-overflow policies and has
not been evaluated inside the publisher against lifecycle and interruption tests.
No production representation was changed. Phase 3 remains open until that tradeoff
and the remaining lifecycle acceptance criteria are resolved; the model is not
evidence of a production speedup. No API or schema baseline was changed.

**Completion baseline (2026-11-21):** Starting revision
`726c3e590912f125e3bceec2d7e204cf2a84ab2a` had a clean worktree. PHP 8.5.13
CLI NTS, Composer 2.9.7 and the installed, ignored lockfile and vendor tree were
used without a dependency update. Direct installed and locked versions matched;
`composer check-platform-reqs` passed. PCOV, Swoole and OpenTelemetry were loaded;
OpenSwoole was unavailable. Focused buffer, flush-observer and pipeline tests
passed respectively with 60/301, 3/11 and 5/8 tests/assertions. The pipeline
result alone did not prove delivery because the existing test checked another sink.
`php tests/target-policy-regressions.php` exited 1 with 21 non-buffer failures
in sampling, redaction, delivery and recursive health reporting; it reported no
buffer failures. Provenance health notices also appeared. These are target-policy
results, not evidence that all unrelated targets match the current default stack.

The expanded buffer matrix covers all five strategies at count, byte, combined,
exact and near-fit boundaries, zero-size eviction and mixed-priority fallback.
The pipeline regression now uses its wired sink and attested, classified artifacts,
including delivery controls around fail-open and fail-closed buffer overflow.
On PHP 8.5.13 and 8.6.0 the focused buffer, observer and pipeline tests passed
with 88/649, 3/11 and 7/31 tests/assertions respectively. PHP 8.6 emitted
Codeception `spl_object_hash()` deprecations. PHP 8.4.28 could not start the
pipeline test because the installed dependency set requires PHP >= 8.5; no PHP
8.4 pass is claimed. The separate PHP 8.5 `composer api:check` exited 0 without
detected breaks. No API or schema baseline was changed.

On the resulting PHP 8.5 worktree, `composer test` passed all four suites and
`COMPOSER_PROCESS_TIMEOUT=1200 composer quality --quiet` exited 0 after fixing
new test-only Rector, PHPStan and line-length findings. The separate benchmark
samples and their concurrent-load limitations are in `docs/benchmarks.md`.
No queue candidate was compared; depth-sensitive results warrant a controlled
comparison, so the representation decision and Phase 3 remain open. The expanded
matrix does not cross every strategy with zero-byte capacity or `PHP_INT_MAX`;
selected existing tests cover those boundaries. Downstream checks remain deferred.

**Oversized-item decision (2026-11-13):** Reject an incoming item whose
nonnegative estimate exceeds the entire byte limit without evicting existing
items. `Fail` reports and throws; the other strategies report and immediately
drop the incoming item, including `BlockWithTimeout`. This is an approved
future behavior, not an implementation or a decision about invalid estimates
and configuration values.

Baseline characterization showed integer overflow could leave an appended item
unaccounted and blocking could append an oversized item after space was freed.
The admission change below corrects those paths. A throwing drop reporter can
still interrupt `DropOldest` after an eviction, before replacement is appended.

### Phase 3 execution plan

This is an implementation plan, not evidence that Phase 3 is complete. Deliver
the following as small, reviewable changes; retain the checklist and exit criteria
below as the completion gate.

**Baseline and input review (2026-11-20):** Starting revision
`c74780b15c075a6c1923e9fbea01b8457cc28c7d` had a clean worktree. PHP CLI was
8.5.12 NTS, Composer 2.9.7 and PHPBench 1.7.0; PCOV, Swoole and OpenTelemetry
extensions were available, but OpenSwoole was not. Installed direct dependencies
matched the local lock; notably Codeception's assert module was 3.3.0 and the
OpenTelemetry API and SDK were 1.12.0 and 1.17.0. No dependency update was run.
Benchmark results and their measurement limitations are recorded in
`docs/benchmarks.md`, including mixed-size and priority-eviction workloads.

- `vendor/bin/codecept run Unit Application/Publication/BufferingTest.php --no-rebuild`:
  21 tests, 117 assertions passed, including existing-defect characterization.
- `vendor/bin/codecept run Unit Application/Publication/BufferedArtifactPublisherFlushObserverTest.php --no-rebuild`:
  3 tests, 11 assertions passed.
- `vendor/bin/codecept run Unit Application/Publication/PublicationPipelineTest.php --no-rebuild`:
  5 tests, 8 assertions passed.
- `php tests/target-policy-regressions.php`: exit 1, 49 failed checks, including
  28 buffer checks (10 oversized/projected-byte, 3 multiple-eviction and 15
  integer-boundary checks); the other 21 concern sampling, redaction and recursive
  health reporting. This target harness has no buffer-only selector and is not a
  claim that all unrelated target policies apply to the current default stack.

The configuration constructor currently accepts negative integers. Zero interval
disables automatic flushing; zero batch size drains nothing, zero shutdown budget
drops remaining entries on close, and zero maximum age expires entries only once
their age exceeds zero. Zero item or byte capacity currently appears full even
when empty, but replacement paths can still append. Custom negative estimates
are accepted and corrupt the meaning of accounting. The default JSON estimator
returns zero if encoding returns `false`; zero can therefore mean encoding failure,
not necessarily an empty artifact. Other estimator consumers must be considered
separately before changing the public estimator contract.

**Input decision (approved 2026-11-20):** Reject negative values in all six numeric
configuration fields and negative buffer estimates with `InvalidArgumentException`
before buffer mutation. Retain the current zero age, batch, interval and shutdown
budget semantics. Zero item capacity admits nothing; zero byte capacity admits
zero-estimated items subject to the item limit. Preserve the default estimator's
zero on JSON encoding failure and validate negative estimates only at the buffer
boundary, not across all consumers of the public estimator interface. These changes
affect standalone exceptions, drop notifications and custom-estimator callers.
The released 1.2.1 split revision `36be7bc0a79f98a29f748735f1ee9557b9a70ed3`
accepts negative inputs and uses the prior current-occupancy check, so this is a
behavioral compatibility change even without signature changes. Searches of PHP
files in the latest stable archives of the five Packagist-listed dependents found
no direct references to the buffer, estimator or publication-pipeline symbols;
this does not cover private applications, dynamic wiring or all historical releases.
Consumers using `^1.1` can resolve subsequent 1.x releases and may observe different
exceptions, drop counts, notifications or ordering. Downstream migration and checks
remain deferred under the existing scope decision. Approval does not authorize an
API or schema baseline update.

**Implementation in progress (2026-11-20):** Projected admission and repeated
eviction are implemented with subtraction-based byte checks; approved validation,
zero and oversized behavior are covered by `BufferingTest`. The standalone target
harness now advances its deterministic clock while blocking: all 28 previously
failing buffer checks pass, while 21 unrelated target-policy checks still fail.
On PHP 8.5.12, `composer quality` passed validation, Rector, style, PHPStan,
architecture, API and line-length checks, all four test suites, both security
audits and examples, but failed when Composer's 300-second process timeout stopped
the dogfood stage during its full benchmark. A subsequent
`COMPOSER_PROCESS_TIMEOUT=1200 composer quality --quiet` completed successfully.
On PHP 8.4.27, the focused buffer,
flush-observer and pipeline suites passed; PHP 8.6 and OpenSwoole were unavailable.
Phase 3 remains open pending the remaining lifecycle, timeout and performance
evaluation and full verification; no queue representation change has been made.

**Additional lifecycle regressions (2026-11-20):** Deterministic blocking tests
now cover projected-byte timeout and admission only after two batch drains free
enough capacity. A throwing inner publisher test verifies that the attempted item
is removed and its estimate deducted before the original exception propagates,
while the next item and its accounting remain intact. On PHP 8.5.13 the focused
`BufferingTest` passed with 50 tests and 203 assertions. This is partial coverage,
not completion of the lifecycle or performance checklist. Oversized and zero-item
capacity rejections still return before the interval-flush check; the ordinary
overflow rejection regression does not cover those early-return paths. On the same
runtime, `composer test --quiet`, `composer analyse`, `composer cs:check` and
`composer api:check` also passed; the quiet test run does not report skip counts.

**Current verification note (2026-11-21):** On PHP 8.5.13 the target-policy
regression harness still exits 1 with 21 sampling, redaction and recursive-health
failures and no reported buffer failures. Integration, conformance and concurrency
suites passed (11, 52 and 11 tests respectively). `composer api:check` exits 1
because two pre-existing untracked public symbols are detected as additions;
no baseline was changed. `composer validate --strict` reports an out-of-date local
lock file. `composer lint:length` reports the existing 123-column line in
`BufferingTest.php:153`. Architecture checks passed. These results do not establish
Phase 3 completion.

Additional deterministic tests exercise close interrupted by a throwing drop
reporter, subsequent and repeated close, and a throwing observer after a partial
flush. They check exception identity, deliveries, drop counts and retained-byte
accounting. The focused `BufferingTest` passed with 52 tests and 225 assertions;
the separate flush-observer suite passed with 3 tests and 11 assertions. The full
unit suite passed (1,312 tests, 8,383 assertions), as did Rector dry-run. PHP 8.6
passed the focused suite with Codeception deprecation warnings; PHP 8.4 could not
start because the installed dependency set requires PHP 8.5 or later. PHPStan
reports two errors in the pre-existing untracked `Hash.php`, and style checking
reports both untracked files. Full `composer quality` was not run because its
prerequisites already fail. Two corrected-buffer benchmark runs and their
non-comparable baseline limitations are recorded in `docs/benchmarks.md`; they do
not isolate queue costs or justify a representation change.

**Further characterization (2026-11-21):** With a retained item and an elapsed
interval, oversized rejection returns without flushing; explicit flush later
delivers the retained item. A partial close drain removes the failed sink attempt
without counting or reporting it as a buffer drop, propagates the same exception,
and skips remainder dropping and observer notification. Retrying close applies a
fresh budget to FIFO survivors; a further empty close still notifies the observer.
The focused `BufferingTest` passed with 54 tests and 254 assertions. Zero-item
capacity's elapsed-interval path, broader lifecycle combinations, performance
evaluation and full Phase 3 verification remained open; these tests do not settle
whether the characterized behavior should change.

**Additional characterization (2026-11-21):** Zero item capacity rejects an
item after the flush interval without invoking the flush observer; explicit flush
does invoke it. Expiration uses a strict age boundary: an item at the maximum age
is published, while one second older is dropped. Expired entries do not consume
the flush batch's successful-publication budget; the regression checks reporting,
FIFO delivery, observer counts and retained-byte accounting. The focused buffer
suite passed on PHP 8.4, 8.5 and 8.6 with 58 tests and 276 assertions each;
Codeception emits deprecation warnings on PHP 8.6. These are observations of
existing behavior, not approval to change lifecycle semantics. The separate
target-policy harness still reports 21 non-buffer failures. Further lifecycle
combinations and Phase 3 exit verification remain open.

On PHP 8.5, `composer quality` passed after correcting a pre-existing long
assertion in the buffer test; it includes `composer test` and `composer api:check`.
The benchmark added during that run was checked separately for style and line
length. The quality result does not cover the separate target-policy harness or
benchmark measurements.

The queue-scaling benchmark now exercises close and eviction at three depths,
with untimed delivery and accounting checks. Both runs show depth-sensitive
costs, but include other publisher work and ran during quality checks; see
`docs/benchmarks.md` for measurements and limitations. They warrant further
investigation, not a claim that an alternative representation is faster.

**Expiration reporter interruption (2026-11-21):** A deterministic flush test
now covers a throwing health reporter while an expired entry is removed. The
original exception propagates, the expired entry is counted and its bytes are
deducted, and the fresh FIFO survivor remains buffered. Retrying flush publishes
the survivor and notifies the observer. The focused buffer suite passed with
59 tests and 289 assertions. This characterizes existing behavior; the remaining
lifecycle and Phase 3 exit checks are not thereby complete. The publisher class
description also now distinguishes estimated bytes from process memory. On PHP
8.5.13, `COMPOSER_PROCESS_TIMEOUT=1200 composer quality` passed, including all
four test suites, API checking, security audits, examples and dogfood. The new
test was not separately run on PHP 8.4 or 8.6 in this increment; no independent
benchmark comparison was performed.

**Automatic flush interruption (2026-11-21):** A deterministic regression covers
a sink failure during an interval-triggered flush. The attempted item is removed
without a buffer-drop count, the admitted survivor remains accounted for, and the
flush interval has already reset. A subsequent publish within that interval does
not retry; after another interval, the survivors drain in FIFO order. This records
existing behavior, not a decision to change retry semantics. Lifecycle coverage,
an independent representation comparison and Phase 3 exit verification remain open.
The focused buffer suite passed on PHP 8.5.13 (60 tests, 301 assertions) and on
PHP 8.6 with Codeception deprecation warnings. PHP 8.4.28 could not start the
suite because the installed dependencies require PHP 8.5 or later. On PHP 8.5,
the `composer quality` run reported all four suites and 10 dogfood tools passing,
including Rector, style, analysis, architecture and API checks; the process monitor
did not expose the final exit code. No independent queue comparison was run.

**Further lifecycle verification (2026-11-21):** On the clean starting revision
`35c2f799`, the buffer tests gained explicit byte-bound assertions after timeout,
partial flush, expiry, sink and observer failures, and interrupted close. The
focused suite passed on PHP 8.5.13 (93 tests, 796 assertions) and PHP 8.6.0
(with Codeception deprecation warnings). PHP 8.4.28 could not start it because
the installed dependency set requires PHP 8.5 or later. `composer prohibits php
8.4.28` identifies installed `it-pack/derive-it` 1.1.2, `it-pack/disenchant-it`
1.1.1 and `it-pack/hash-it` 1.1.1 as requiring PHP ^8.5. The local ignored
lockfile metadata was refreshed with `composer update --lock --no-install`;
installed packages were not updated. The separate target-policy harness still
reports 21 non-buffer failures. These assertions supplement existing
exact accounting checks; they do not by themselves resolve the remaining queue
representation evaluation or establish Phase 3 completion.

**Integrated queue evaluation (2026-11-21):** The publisher now uses an indexed
array, compacts after at least 1,024 holes when holes are at least as numerous as
live entries, compacts before index exhaustion, and resets indexes when empty.
Removal and byte accounting precede callbacks. Focused tests cover churn, index
exhaustion, distinct FIFO and priority survivors through compaction, reentrant
sink publication and a bounded reentrant health callback; the queue suite passed
with 4 tests and 24 assertions, and the buffer suite with 93 and 796. An independent
review found no queue regression but identified an existing limitation: a health
reporter that refills on every drop can keep overflow or close loops running. This
is not resolved by the queue change. See `docs/benchmarks.md` for real-publisher
comparisons, including small-queue regression, variance and memory limitations.
On PHP 8.5.13, `composer test --quiet`, `composer quality --quiet` and separate
`composer api:check` passed after refreshing local lock metadata. The queue suite
also passed on PHP 8.6 with Codeception deprecation warnings. OpenSwoole was not
available, and no downstream migration checks were run. Phase 3 remains open:
the reentrant-reporter termination case and comprehensive lifecycle invariant
coverage still need resolution; the measurements do not establish a process-memory
bound or persistent-worker memory behavior.

1. **Re-establish the baseline.** Record the starting revision, worktree state,
   PHP and Composer versions, extensions and installed dependencies. Run the
   focused `BufferingTest`, flush-observer and pipeline tests, and identify which
   assertions intentionally characterize existing defects. Run the buffer cases
   in `tests/target-policy-regressions.php`, recording unrelated failures
   separately. Capture comparable pre-change admission, mixed-size overflow,
   priority eviction and drain/close measurements using the existing
   `BufferedAdmissionBench`, `BufferedOverflowBench` and `BufferedFlushBench`;
   extend the workloads where necessary. Record sizes, strategies, runtime,
   variance and memory-measurement limitations in `docs/benchmarks.md`.
2. **Resolve input semantics before changing behavior.** The Phase 2 exit records
   the original planning candidate; the decision above now authorizes the specified
   compatibility changes, but is not evidence of implementation. Review each
   `BufferConfiguration` field and custom estimator contract,
   including the default estimator's zero result on JSON encoding failure. Specify
   the consequences of zero item capacity, zero byte capacity and zero-sized
   estimates against the decision above. Review released consumers and
   document migration impact; downstream migration and checks remain deferred
   under the scope decision. Do not alter API or schema baselines without the
   separately required approval.
3. **Write target regressions and implement admission.** Use deterministic byte
   estimates and a controllable monotonic clock. Test count and byte limits
   independently and together, exact fit, nearly full, empty and full buffers,
   approved zero cases, invalid values, and arithmetic near `PHP_INT_MAX`. Check
   projected capacity without overflowing, validate before mutation, and apply
   the same fit rule on every append. For an estimate above the full byte limit,
   implement the approved immediate report-and-drop or report-and-throw behavior
   without evicting existing entries or waiting. Keep the byte total equal to the
   sum of retained estimates and both limits intact after each operation and
   exceptional path.
4. **Complete overflow and lifecycle behavior.** Cover every strategy with
   mixed-size entries and repeated evictions. Preserve survivor FIFO order and
   the existing low-priority selection, high-priority fallback, drop counts and
   reporting behavior unless a separate decision changes them. Ensure eviction
   terminates even with zero-sized entries or zero capacity. For blocking, test
   timeout when projected space is insufficient, admission after sufficient
   space is freed, and immediate oversized rejection; advance the fake clock via
   the sleep seam. Test flush, expiration, auto-flush, close budgets, repeated
   close, observer callbacks, throwing sinks and throwing health reporters, with
   state and accounting checked after each removal or interruption. Preserve
   standalone exception propagation and test default pipeline failure handling;
   do not silently settle Phase 4's broader reporting policy.
5. **Evaluate representation only after correctness.** Repeat the same
   workloads against the corrected implementation, including multiple evictions
   and low-priority searches. Compare an internal head-index or queue only if
   measurements justify investigating it; verify identical ordering, priority,
   reporting, lifecycle and accounting behavior before adoption. Record both
   improvements and regressions, host variance, memory limitations and any
   unresolved tradeoffs; estimated-byte bounds are not process-memory bounds.
6. **Verify and document completion.** Run focused tests and target controls,
   then `composer test`, `composer quality`, and `composer api:check`; inspect
   `composer.json` for the current scripts and report separate results even if a
   composite command stops early. Run supported PHP versions where available,
   including the PHP 8.4 floor, and record skips and unavailable extensions.
   Run benchmarks separately because quality does not cover them. Update
   `docs/architecture/publication.md`, `docs/benchmarks.md`, configuration or
   estimator documentation, `docs/CHANGELOG.md`, and this phase's status with
   actual behavior, compatibility effects, commands and results. Do not describe
   the new invariant as current until implemented and verified.

- [x] In `src/Application/Publication/BufferedArtifactPublisher.php`, define admission as both item-count and projected estimated-byte capacity, using overflow-safe arithmetic. Establish behavior for an item larger than the full limit and for invalid estimator results or configuration values without silently inventing public semantics.
- [x] Expand `tests/Unit/Application/Publication/BufferingTest.php` with deterministic estimates: empty/exact/full/nearly-full buffers, oversized first item, multiple evictions, both count and byte constraints, zero or boundary values where supported, and every overflow strategy (`Fail`, `DropNewest`, `DropOldest`, `DropLowPriority`, `BlockWithTimeout`).
- [x] Implement capacity checks on every append, including all replacement and timeout paths. Evict enough eligible entries where the approved strategy permits; otherwise drop/fail without violating the bound. Preserve priority, FIFO ordering, health notifications, counts, and exception behavior.
- [x] Test flush, expiration, failed inner publication, timeout, close budget, repeated close, observer callbacks, and accounting after removals and interruptions. The deterministic lifecycle and admission tests assert retained byte totals and capacity bounds; estimates are not exact process-memory limits.
- [x] Benchmark existing drain/close with representative sizes and overflow/priority patterns using `composer benchmark` or a focused PHPBench benchmark. Compare a head-index or queue implementation only after preserving observable ordering and eviction behavior.
- [x] If measurements justify it, replace repeated array shifting with an internal representation that preserves the tested behavior; benchmark again and record environment, results, and regressions. Do not claim a speedup without measurements.
- [x] Update buffer and performance documentation, including oversized-item behavior and the distinction between estimated and actual memory.

**Exit:** admission invariants and all overflow tests pass; benchmark evidence and any remaining performance tradeoff are recorded. **Phase 3 complete (2026-11-21)**, with the PHP 8.4 dependency-set, OpenSwoole, downstream and unrelated target-policy limitations recorded above. The wider health-reporting policy remains Phase 4 work.

## Phase 4: Failure handling and lifecycle outcomes

**Depends on:** Phase 0; open-activity behavior additionally depends on its decision gate. **Review:** silent failure, lost terminal execution, ambiguous open-activity policy.

**Historical preparatory characterization:** Before the Phase 4 implementation,
`OpenActivityPolicyTest` exercised a runner
with `FailExecutionOnOpenActivitiesPolicy` and an open activity. The critical
diagnostic says the execution is flagged as failed, but its published terminal
status remains succeeded or failed according to the business operation. Both the
returned object and thrown exception are preserved by identity, and the context
was detached. This described the old behavior, not the approved configurable
outcome implemented below.

On PHP 8.5.12, the full `composer test -- --silent` run passed. The previous
PHP 8.4 direct-run claim is qualified by the refreshed Phase 0 results above.
The focused open-activity
test file passed with 5 tests and 28 assertions. Style and architecture checks
passed, and focused PHPStan analysis of that test file passed. The quality gate
stopped at the existing Rector callable-fixture suggestion; the independent
line-length check reported 26 violations outside these changes. Silent test runs
do not establish coverage for unavailable OpenSwoole or Swow extensions.

- [x] Implement the [publication health reporting decision](#decisions-required-before-dependent-changes), including the replaceable SDK default, custom reporter, explicit silence, independent bounded/sanitized diagnostics and recursion guard. Decide the default destination and nullable callback compatibility; guard diagnostic extraction and keep fail-closed behavior explicit.
- [x] With an injected destination rather than PHP's error log, test a throwing publisher with no custom callback, a working custom reporter, explicit silence, a throwing reporter, and a reporter re-entering publication. For each, cover successful and throwing business operations, assert the expected diagnostic or silence, exact result or original exception, and context restoration. Also cover fallback-sink and artifact-normalization failures.
- [x] Fix the `CallbackHealthReporter::report()` fail-open hole: guard fallback reporting and verify runner outcomes and context restoration when it fails. Secondary failures go to a bounded, payload-free system-log notice; a throwing last-resort sink is contained. The normal fallback now also uses fixed payload-free diagnostics and no longer normalizes the artifact.
- [x] In `DefaultExecutionRunner::publishTerminal()`, publish the completed execution independently from snapshot creation and publication; preserve normal execution-before-snapshot ordering unless explicitly changed. Extend `tests/Unit/Application/Runner/ResilienceTest.php` for successful and failing business operations, snapshot factory failure, execution publication failure, snapshot publication failure, original exception identity, and context restoration. On the worktree based on `0b072d80`, the focused suite passed on PHP 8.5.12 (25 tests, 116 assertions), and the separate target-policy harness passed on PHP 8.4.27 and 8.5.12. Execution publication now also precedes snapshot factory invocation; failing publishers cannot guarantee delivery.
- On that worktree, `composer quality --quiet` passed on PHP 8.5.12. It does not
  include the standalone target-policy harness, API checks, coverage, or benchmarks.
  The API checker still reports the previously committed OpenTelemetry interface
  additions as breaks; no baseline was changed.
- [x] For `FailExecutionOnOpenActivitiesPolicy`, first add a test to `tests/Unit/Application/Policy/OpenActivityPolicyTest.php` asserting the current observation, terminal status, and original result. Implement only the approved outcome/message semantics; update lifecycle documentation and compatibility notes if status behavior changes.
- [x] Test nested executions, open activities, repeated completion, and adapter/health failures together at the appropriate suite level. Document the chosen publication and completion failure guarantees.

### Phase 4 completion plan

This completion plan addressed the discrepancy found in the earlier
characterization. It does not close or waive the outstanding Phase 1 and Phase 2
follow-ups. The default runner now marks an otherwise successful execution failed
when the explicitly selected `FailExecutionOnOpenActivitiesPolicy` sees open
activities. With maintainer approval, the required throwable-description class is
the fixed `open_activity_policy` marker, not a thrown business exception. The
default policy and public signatures remain unchanged; explicit-policy consumers
must account for the changed terminal status.

1. **Re-characterize and resolve the policy gap.** Inspect the completion policy
   contract, runner, lifecycle coordinator, existing policy tests and lifecycle
   documentation. Cover default diagnostic-only behavior and the explicitly
   selected failure outcome for both successful and throwing operations, including
   result and exception identity. Determine how the policy communicates its
   outcome without breaking existing implementations. Review status, diagnostic,
   public-contract and consumer compatibility; obtain maintainer approval before
   any material contract or behavioral change not already authorized. Implement
   the approved semantics and correct misleading messages and documentation only
   after that review. Do not mark this work complete based on characterization
   alone.
2. **Exercise combined lifecycle failures.** Extend the relevant runner,
   lifecycle and integration tests with named, independently asserted scenarios
   combining nested executions, open activities and repeated completion with
   publisher/adapter and health-reporter failures. Cover successful and throwing
   business operations, same and conflicting terminal completions, reporter
   failure and re-entry, and snapshot creation/publication failures as applicable.
   Assert terminal status and at-most-once transitions, execution-before-snapshot
   attempts, bounded reporting or explicit silence, exact result or original
   exception identity, and restoration of the parent context. Distinguish an
   attempted publication from delivery and avoid assertions inside test loops.
3. **Reconcile documentation and decisions.** Update `docs/architecture/lifecycle.md`
   and affected publication, upgrade and changelog documentation to match verified
   behavior and any approved compatibility impact. In particular, reconcile its
   callback-conditional snapshot-failure wording with the default error-log health
   sink and explicit silence. Verify the implemented default destination and
   nullable callback behavior against the decision record before resolving the
   remaining publication-health design-detail checkbox. Record any limitations
   of the fallback sink, failure isolation and completion guarantees.
4. **Validate and record evidence.** Run focused policy, runner and lifecycle
   tests first, then the relevant integration and full test suites and
   `composer quality`, which currently includes `composer api:check`. Run that
   check separately if an earlier quality step stops the gate. Run the separate
   target-policy harness where applicable and
   check PHP 8.4 and supported newer environments where available. Record exact
   commands, results, skips and dependency or extension limitations. Investigate
   failures rather than regenerating API/schema baselines or hiding failures;
   use the approved contract-change process if a baseline update is warranted.
   Update the Phase 4 checklist and exit statement only when the required evidence
   and decisions are complete.

**Exit:** terminal publication is attempted and health reporting is best effort
unless explicitly disabled, without masking business results; failures in the
transition, storage, publisher or log destination can prevent observation or
delivery. Phase 4 implementation and lifecycle failure tests are complete, with
the validation limitations below.

**Phase 4 evidence (PHP 8.5.13, 2026-11-21 UTC):** `composer test -- --silent`,
`composer analyse -- --no-progress`, `composer refactor:dry -- --no-progress-bar`,
`composer cs:check -- --quiet`, `composer architecture`, `composer lint:length`,
`composer api:check`, and the runtime and full security audits passed. The full
audit reported the already tracked abandoned development dependency
`doctrine/annotations`, not a vulnerability advisory. The combined lifecycle test
has three cases (42 assertions), including snapshot factory failure and unbounded
health-callback re-entry; publisher and runner guards are instance-wide and may
suppress interleaved independent reports. PHP 8.4.28 passed the focused policy,
combined lifecycle and health-reporting tests and the terminal-publication
target-policy harness; the latter also passed on PHP 8.5.13. No full PHP 8.4
dependency-matrix or unavailable OpenSwoole/Swow extension run is claimed.
`composer quality` stopped at `composer validate --strict` because the existing
ignored lockfile is out of date with `composer.json`; the existing lockfile was
not refreshed. Checks later in that gate not individually listed above, including
examples and dogfood, were not independently rerun as quality steps. These are
validation limitations, not evidence that the complete quality gate passed.

The earlier callback reporter fix was limited to its own secondary failure path. On PHP
8.5.12, the focused publication and runner tests passed, as did `composer
quality`; the focused tests also passed on
PHP 8.4.27. The system error log may fail silently, and a throwing system-log
destination was not reproduced. At that point recursive callback publication and
the runner's independent reporting path remained open; they were addressed by the
subsequent Phase 4 work above.
The ten-subject benchmark suite passed without a controlled before/after
comparison and does not characterize this exceptional path.

## Phase 5: OpenTelemetry retention

**Depends on:** Phase 0 and the span-store retention decision. **Review:** unbounded span-context store.

**Decision (2026-11-22):** The maintainer authorized a configurable capacity and
delegated the default and eviction scope. Use 10,000 entries across both kinds,
oldest-write-first eviction, and refresh age on replacement but not lookup. The
synthetic baseline of roughly 9 MB used heap for 10,000 contexts before the later
linked-order implementation motivates a starting point, not a current memory bound
or universal worker memory budget. Inject a store
constructed with a different positive capacity, or a custom store, to configure it.
This entry bound is not a byte bound; real workload and delayed-parent evidence
remain necessary for production sizing. The optional constructor is an additive
public API change and eviction changes correlation. An independently modified API
snapshot includes the constructor and two unrelated existing fallback constants;
the maintainer elected to preserve that diff. Review both API and schema snapshots
and coordinate the change before release.

Validation on PHP 8.5.13: `composer test` passed (1,414 unit, 11 integration,
52 conformance and 11 concurrency tests); `composer analyse`, `composer cs:check`,
`composer refactor:dry`, `composer architecture`, `composer lint:length` and
`composer security:audit` passed. `composer api:check` passes with the preserved
snapshot; before that snapshot changed it reported the added constructor and no
backward-compatibility breaks. The bounded synthetic comparison is in
`benchmarks.md`. PHP 8.4 and mapper-overhead measurements remain unverified.

**Preparatory characterization (before the bounded implementation):** The then-default store retained execution
and activity contexts in separate unbounded ID-keyed maps. Repeated IDs replace
earlier values; activity keys are not scoped by execution. Activity mapping first
looks up its parent activity, then the execution; child execution mapping looks up
its parent execution. A missing stored parent leaves selection to the telemetry
builder, and a later parent does not change an already exported child. Injected
stores may be shared between publishers and thereby share correlation state. See
`integrations/opentelemetry.md` for operational limitations. These tests do not
establish a suitable retention limit, concurrent-store guarantees, or memory cost.

A synthetic pre-change store benchmark is now recorded in `benchmarks.md`.
In one PHP 8.5.12 process, the median used-heap delta for 100,000 distinct
execution/activity pairs was 151,286,000 bytes; hit and miss lookup medians
were 310.162 and 128.213 ns respectively. Seven samples had substantial timing
variation and allocator reuse; the result is not a fresh-worker RSS measure or
an evidence-backed production limit. The subsequent decision is recorded above.

**Pre-decision compatibility investigation (2026-11-22):** The injected-store seam
allows custom retention without changing the store interface or publisher signature.
A built-in capacity constructor, factory, or configuration method would add to the
frozen API and requires the contract-change approval process. Eviction changes
correlation even without a signature change: an activity first falls back from a
missing activity parent to its execution, and an unresolved parent is left to the
telemetry builder, which can select an ambient span. At that point the default store
was unbounded and its replacement policy had not yet been approved. The interface
documentation now describes the bounded default and shared-store retention.
Sequential publisher characterization additionally covers shared injected stores,
null tracer providers, activity ID reuse across executions and ambient-parent
fallback. These cases do not demonstrate concurrency safety or bounded retention.
`testDefaultStoresAreIsolatedAndRetainDelayedParents` also characterizes a child
published after 100 intervening executions and confirms that independently created
default publishers do not share correlation, even with the same tracer provider.
This sequential synthetic test does not establish representative retention needs.
`tools/retention-baseline.php` and `benchmarks.md` now provide a reproducible
fresh-process synthetic store baseline. It does not measure mapper overhead,
eviction loss, or representative worker traffic, and did not establish the limit.
The maintainer initially confirmed configurability and deferred the entry count and
aggregate-versus-per-map decision on 2026-11-22; the later decision above supersedes
that deferral. Phase 5 exit remains subject to implementation and validation.

- [x] Trace `TelemetryPublisher::fromConfig()`, `InMemoryExecutionSpanStore`, execution/activity span mappers, and `ExecutionSpanStoreInterface`; characterize parent lookup order, lifetime, concurrency, and consumer-provided store behavior.
- [x] Add tests with many unique executions and activities, nested and late-arriving children, duplicate IDs, and a long-lived publisher. Assert the approved bound or lifecycle, parent lookup before and after eviction, and behavior of injected third-party stores. `testLongLivedDefaultPublisherEvictsAcrossExecutionAndActivityWrites` writes 10,000 mixed execution/activity contexts at the default bound and checks parent fallback after eviction; `testDefaultStoresAreIsolatedAndRetainDelayedParents`, `testPublisherUsesInjectedStoreForBothMappers`, and `testInjectedStoreControlsRetentionAcrossPublishersAndDuplicateWrites` cover injected and shared stores; `testBoundedStoreRefreshesReplacementAndFallsBackFromEvictedActivity` and `testEvictedExecutionFallsBackToAmbientContext` cover parent lookup before and after eviction; `testMissingParentsFallBackAndLaterParentsAreNotRetroactive` and `testReusedActivityIdAcrossExecutionsReplacesParentContext` cover late-arriving children and duplicate IDs. Store-level coverage in `InMemoryExecutionSpanStoreTest` covers capacity one, both kinds, replacement at each ordering position, and 2,000 iterations mixing unique and duplicate IDs.
- [x] Implement the approved bounded or lifecycle-aware strategy without changing a frozen contract unless explicitly authorized. Specify deterministic eviction, memory limits, and behavior for missing parents; do not retain live spans or use global state. `InMemoryExecutionSpanStore` retains at most 10,000 entries across both kinds, oldest-write-first eviction, refresh age on replacement but not lookup, with an optional positive capacity constructor.
- [x] Measure persistent-worker memory and mapper overhead before and after under the same workload. Update OpenTelemetry configuration and operational documentation with the retention tradeoff. `docs/benchmarks.md` records the bounded-store and publisher-retention comparisons against the historical unbounded store (used-heap, allocated-heap, RSS, ns/publication, and delayed-parent outcomes at 10,000 and 30,000 pairs). `docs/integrations/opentelemetry.md` documents the 10,000-entry default, oldest-write-first eviction, custom-store injection, and the correlation-loss tradeoff after eviction.

### Phase 5 completion plan

This plan originally deferred capacity, ordering details and configuration. The
decision above supersedes that deferral; completion still requires implementation,
verification and documentation of the actual behavior and remaining limitations.
The Phase 0 baseline and Phase 4 evidence above have recorded environment and
quality-gate limitations; do not mistake those historical results for a fresh
validation of this phase. Outstanding Phase 1 and Phase 2 follow-ups are not
waived by this plan.

1. **Reproduce and characterize.** Trace default store construction, mapper
   ownership and parent lookup, including ambient OpenTelemetry context when no
   stored parent is set. Extend the existing characterization with named cases for
   execution and activity IDs, duplicate writes, nested and out-of-order children,
   long-lived publishers, shared injected stores and null providers. Determine
   which calls can interleave and document the current concurrency guarantees;
   do not infer thread or coroutine safety from sequential tests. Confirm that
   only span contexts, not live spans, are retained. Reconcile the store
   interface's claim that retention is bounded in practice with the default
   implementation and intentionally shared stores.
2. **Gather decision evidence.** Create a reproducible benchmark harness and run
   the current implementation before changes. Exercise fresh persistent workers
   with representative and explicitly labeled synthetic traffic, varying execution
   and activity ratios, ID and trace-state sizes, duplicate rates, nesting, parent delay and store
   sharing. Measure occupancy, parent misses and correlation loss as well as used
   and allocated PHP memory, process RSS and peak where available, and mapper and
   store hit/miss/write cost. Record workload assumptions, runtime, dependencies,
   hardware, warmup, sample distributions and measurement limitations. The old
   100,000-pair result used 200,000 entries and its temporary harness is missing;
   it is not a reproducible baseline or a basis for selecting a limit.
3. **Resolve the design before implementation.** Compare candidate finite
   capacities against an explicit worker memory budget and delayed-parent loss,
   and record the evidence and maintainer decision. Specify whether the bound is
   aggregate or per map, how insertion age and duplicate replacements affect
   eviction, whether lookups refresh age, interaction between execution and
   activity entries, invalid capacity
   handling, and expected concurrency scope. Define the configuration and default
   construction path, custom-store ownership and sharing, and behavior on misses
   including ambient-parent fallback. Review API and behavioral compatibility for
   existing callers and external implementations; do not add required interface
   methods or change a frozen contract without the approved contract-change process.
   If representative workload evidence is unavailable, record the gap and leave
   the default decision and dependent implementation open rather than inventing a
   production limit. An entry-count bound alone does not prove a fixed byte bound
   for variable IDs, contexts and trace state.
4. **Implement and test the approved policy.** Make the smallest compatible
   change, retaining bounded metadata as well as bounded contexts under sustained
   unique-ID and duplicate-ID churn. Use named data-provider cases for exact-limit,
   overflow, eviction order, replacement, both artifact kinds, typed and redacted
   mapping, reused activity IDs across executions, delayed and nested children,
   lookup fallback with and without ambient context, and long-lived publisher
   behavior. Verify the bound
   after repeated writes, custom injected stores remain in control of their own
   policy, and eviction or telemetry failures do not change business results or
   original exceptions through the fail-safe runtime path; separately characterize
   direct publisher exceptions. Add concurrency tests only for guarantees actually
   supported; document any isolation requirement instead of assuming one.
5. **Compare, validate and document.** Repeat the baseline workload under the
   same conditions on the changed checkout, with fresh workers and enough samples
   to expose variability; record raw measurements, correlation loss and tradeoffs
   in `docs/benchmarks.md`, not a performance claim based on a single run. Run
   focused store, mapper and publisher tests, relevant integration/concurrency
   suites, then `composer test`, `composer api:check` and `composer quality`;
   run relevant individual gates if quality stops early. Check PHP 8.4 and newer
   supported runtimes where available, recording commands, failures and skips.
   Update `docs/integrations/opentelemetry.md` and `docs/CHANGELOG.md` for the
   actual default, configuration, eviction, missing-parent and migration behavior;
   update the store interface documentation and other affected docs for verified
   behavior. Never regenerate API or
   schema baselines merely to pass a check. Mark checklist items and the exit
   complete only after the approved design, bounded-growth evidence, tests and
   remaining validation limitations are recorded.

**Exit:** store growth is demonstrably bounded under the agreed policy (10,000 entries across both kinds, oldest-write-first eviction), parent behavior is tested (lookup before and after eviction, ambient fallback, injected/custom stores), and measurements are recorded in `docs/benchmarks.md`. Tutorial 16 (`docs/tutorial/16-opentelemetry-retention.md`), the recipe addition in `docs/recipes/opentelemetry.md`, the updated `docs/integrations/opentelemetry.md`, and `examples/opentelemetry-retention.php` document the bounded store, custom capacity, custom store injection, and eviction tradeoffs. Phase 5 complete (2026-11-22).

**Additional validation (2026-11-22):** The store tests now cover capacity one,
both kinds, replacement at each ordering position, and 2,000 iterations mixing
unique and duplicate IDs; they check that the order map stays at capacity. Publisher
tests cover typed and redacted injected-store lookup and write exceptions, direct
exception propagation, and the safe runner preserving both result and original
exception while reporting store failures. An injected store selects the parent in
the publisher test. Focused store and publisher tests passed on PHP 8.4. The store
and the then-current publisher tests also passed on PHP 8.6.0, with Codeception
`spl_object_hash()` deprecation warnings. These focused runs are not complete PHP
dependency-matrix validation. `composer api:check` passes; the API snapshot diff
since `309c4a77` contains the optional constructor and the two previously noted
constants, while the schema snapshot has no diff in that range.

The new publisher benchmark and its measured memory, timings and actual delayed
parent results are documented in `benchmarks.md`. Its unbounded control is not the
historical implementation and its sequential synthetic workload is not a production
traffic or memory-budget study. Representative parent-delay and memory evidence, the
full planned behavior matrix, a comparable historical mapper baseline, and release
coordination for the approved public constructor and changed correlation behavior
were not established within Phase 5; they are tracked as Phase 1 follow-ups rather
than Phase 5 exit criteria. Do not infer a production byte bound or universal
performance result from this comparison.

On PHP 8.5.13, the current `composer quality` passed, including all four test
suites, both audit lanes, examples and dogfood; the Unit and Integration suites
reported 1,434 and 11 tests respectively. Focused PHP 8.4 runs used the installed
dependency set rather than a clean dependency resolution. The PHP 8.6 test runner
emitted Codeception deprecation notices as noted above.

**Further Phase 5 evidence (2026-11-22):** A default publisher test writes 10,000
mixed execution and activity contexts, then checks nested parentage and late
children after cross-kind eviction. A separate shared custom-store test checks
duplicate replacement and custom removal. The publisher suite passed on PHP 8.4.28
and 8.6.0 (46 tests, 284 assertions each), using the installed dependencies rather
than clean version-specific installs. These sequential cases do not establish
concurrency safety or complete every typed/redacted combination.
On PHP 8.5.13, `composer quality` passed after these changes, including the
test suites, API check, audits, examples and dogfood checks. The PHP 8.6 run
emitted Codeception deprecation warnings.

`benchmarks.md` now compares the actual pre-bounded store from `929854b1` with
the current store in the same fresh-process publisher workload. At 30,000 pairs,
median publication time was 41,270 ns historically versus 44,499 ns bounded;
used-heap deltas were 19,457,976 and 7,994,584 bytes respectively. The oldest
delayed child lost its parent only in the bounded case. These are synthetic
publisher-path results, not isolated mapper costs or representative production
traffic. Phase 5 complete (2026-11-22); these are synthetic publisher-path
results, not isolated mapper costs or representative production traffic. Do not infer
a production byte bound or universal performance result from this comparison.

## Phase 6: Coverage, QA, architecture, and dependency maintenance

**Depends on:** Phase 0; security/lifecycle tests from Phases 1-5 contribute to the floor. **Review:** unit coverage, attribution, failed gates, architecture scope, and abandoned dependency.

**Coverage handoff:** The 95% Unit-only gate, per-test attribution, isolation,
report provenance and discovery-time prohibition are deferred here in full.
The current diagnostic intentionally succeeds when it reproduces leaks; it is
not a passing isolation gate. Production execution during Unit provider discovery
remains prohibited: providers may supply data and deferred factories, with
production construction and calls performed in the test. Verify enforcement
across provider attributes, named datasets, bootstrap, modules, autoload,
generators and failures, and fail closed when the coverage driver is unavailable.
Do not install a CI gate until negative controls, correct suite selection, source
filtering, collector cleanup and isolation from other suites are demonstrated.

- [x] Re-run `composer coverage` with PCOV against Unit only; parse the resulting statement numerator and denominator. Prioritize uncovered security, lifecycle, error handling, boundary, and adapter code rather than padding tests for a percentage.
- [ ] Add focused Unit tests until statement coverage is **at least 95%** over the agreed production source set. Keep integration, conformance, and concurrency tests in their own suites; document environment-dependent unreachable branches and do not silently exclude them to make the floor pass.
- [ ] Audit test-class coverage attribution using the installed PHPUnit/Codeception coverage metadata or another verified mechanism. For each test class, explicitly identify the class under test and allowed collaborators; demonstrate that unrelated indirectly executed code is not credited. Add a deliberately leaking fixture/test that causes the isolation check to fail, then keep a regression check without contaminating normal coverage.
- [ ] Implement a reproducible CI threshold check on the Unit-only report: fail on missing/invalid report, wrong suite, zero denominator, or coverage below 95%; add tests for values below, equal to, and above the threshold. Ensure separate CI runs cannot overwrite or merge broader-suite coverage into this report.
- [ ] Wire the coverage and isolation checks into package and monorepo quality workflows (`.github/workflows/quality.yml` and `../../.github/workflows/quality.yml`) with the required coverage driver. Document the exact metric, denominator, command, and fresh result in `docs/QA.md`.
- [x] Enforce `composer api:check` in the active monorepo workflow as well as the standalone package workflow; a nested package workflow is not automatically executed by the monorepo. Keep the baseline unchanged unless a public break is deliberately approved.
- [x] Re-run PHPStan at configured maximum level; group errors by root cause, correct invalid fixture types and production typing/imports, then eliminate remaining first-party errors without broad ignores or a new baseline. Re-run `composer cs:check` and fix only actual style failures.
- [ ] Inventory actual dependency edges and compare `phparkitect.php` against `docs/architecture/hexagonal-architecture.md`, especially `Contract\Port` and Behavior layer directions. Agree intended layers before changing rules; add architectural regression checks for forbidden dependencies without adding skips to force green.
- [x] Update stale DepTrac references in `docs/QA.md` and architecture docs to accurately describe PHPArkitect and the effective rules. Update ADRs first if the intended boundary itself changes.
- [x] Re-run `composer audit`, `composer audit --no-dev`, and `composer why doctrine/annotations --tree`; track the abandoned dependency through PHPBench and `just-bases/php-qa-base`. Do not imply that an abandoned development dependency is an active vulnerability or introduce unsupported pins to suppress the warning.

**Phase 6 progress (2026-11-22):** On PHP 8.5.13, `composer analyse`,
`composer cs:check`, `composer refactor:dry`, `composer architecture` and
`composer api:check` passed. PHPStan reported no errors; no baseline was changed.
The active monorepo quality workflow now runs the RunIt API check explicitly;
the hosted workflow and other PHP versions were not exercised locally. Both
Composer audits reported no security advisories. `doctrine/annotations` 2.0.2
remains abandoned and is a development dependency through PHPBench and
`just-bases/php-qa-base`. The architecture inventory in
[`architecture/dependency-graph.md`](architecture/dependency-graph.md) records
the current rules and unresolved boundary decisions; it is not approval to
change those boundaries. Architecture negative controls remain outstanding.
The coverage attribution diagnostic now also verifies failure when no coverage
driver is available. Its 12 observations reproduce known attribution leaks,
including discovery-time execution; they do not establish isolation. The 17
threshold-policy controls pass, but the Clover input does not establish suite
provenance. Accordingly the coverage gate must remain disabled until the
handoff's isolation and provenance requirements are met.
On PHP 8.5.13 with PCOV, a fresh Unit-only `composer coverage` report recorded 6,178 of 6,753 statements (91.48%; 1,474 tests, 9,519 assertions). The earlier measurement was 6,151 of 6,753 (91.09%; 1,438 tests, 9,424 assertions). New focused Unit tests with `CoversClass` were added:

- `tests/Unit/Application/Publication/ClassifyingArtifactPublisherTest.php` — 13 tests, 39 assertions; covers all artifact kinds (execution, observation, activity, measurement, occurrence, snapshot), unknown-kind drop, missing-required-field drop, flush/close delegation, ordered publishing.
- `tests/Unit/Application/Publication/DefaultArtifactRedactorTest.php` — 9 tests, 25 assertions; covers unchanged passthrough, sensitive attribute masking, observation message masking on/off, measurement value masking, throwable message masking, snapshot payload masking with hash omission, disabled config, unknown artifact type.
- `tests/Unit/Application/Publication/ClassifiedRedactedObservationTest.php` — 5 tests, 13 assertions; covers artifact type, identity delegation, severity/message/diagnostic delegation, attributes/timestamp/sequence delegation, normalize delegation.
- `tests/Unit/Application/Publication/ProvenanceAuthorityTest.php` — +9 tests (29 total, 321 assertions); covers attest successor transfer, unattested predecessor rejection, business ID/execution ID lookup, live records tracking, invalid relationship type rejection, cross-execution edge rejection, null-id bindActivityRelationship no-op, bindArtifact when releasing.

Coverage remains below the 95% target. The largest uncovered statement counts are `ClassifyingArtifactPublisher` (~114 lines), `ProvenanceAuthority` (~48 lines), `DefaultExecutionRunner` (~41 lines), `DefaultArtifactRedactor` (~19 lines), `TelemetryPublisher` (~14 lines), `DefaultMeasurementInstrumentMapper` (~12 lines), `DefaultObservationRecorder` (~12 lines), and smaller gaps in `DefaultExecutionSpanMapper`, `DefaultObservationLogMapper`, `DefaultSnapshotRecorder`, and adapter classes. Most of these lines are in defensive/error-handling paths already exercised by integration tests through `RunItTestKit`; unit tests add attribution but limited new line coverage. The 95% target remains open. The coverage gate must remain disabled until isolation and provenance requirements are met.

**Exit:** at least 95% isolated Unit statement coverage is enforced in CI, coverage attribution is verified by a negative control, PHPStan/style/architecture gates pass, and dependency status is current.

## Phase 7: Maintainability, duplication, and design inventory

**Depends on:** approved coding standards and passing behavior tests. **Review:** duplication, assembly complexity, factories/interfaces, coding rules, PhpMetrics and PHPA.

- [x] Replace paired literal reads with existing `ArtifactField` constants in `DefaultOccurrenceLogMapper` where they represent the same keys. Mapping tests cover literal normalized keys and exact redacted occurrence and snapshot exports; public schemas remain unchanged.
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
- [ ] Run RunIt-owned conformance scenarios, including custom context token implementations, optional adapters, and a clean install on PHP 8.4. Verify no new mandatory runtime dependency or accidental public-symbol/schema change. Third-party consumer scenarios are deferred under the downstream scope decision.
- [ ] Update `docs/CHANGELOG.md`, security/publication/lifecycle/architecture/QA documentation, examples, vocabulary references, and ADRs where behavior or public contracts actually changed. Update release records and API baselines only for explicitly approved public changes.
- [ ] Reconcile every finding in `docs/REVIEW.md` with implementation evidence, test names, command results, or a documented owner and deferral. Do not mark an unresolved decision or an unavailable test environment as complete.

**Release exit:** approved semantics are documented; security and lifecycle regressions pass; API compatibility is preserved or a deliberate versioned break is approved; Unit coverage and isolation meet their gates; quality, architecture, and audit results are recorded; remaining risks are explicit.
