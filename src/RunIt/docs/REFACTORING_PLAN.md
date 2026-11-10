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
- [x] **Compatibility baseline:** Keep the API checker strict and the committed API and normalization-schema snapshots as comparison points. A deliberate contract change requires maintainer approval and coordinated migration of affected consumers: review the contract diff, run relevant RunIt and downstream checks against the changed checkout, update snapshots only after approval, record the change in the changelog, and coordinate split releases where necessary.
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
- [ ] Define safe observation fields and classification, and the configurable drop/report mechanism.
- [ ] Choose an evidence-backed span-store limit and specify eviction scope, duplicate-ID ordering, and compatibility-safe configuration.
- [ ] Determine the exact API for the opt-in concurrency validation hook in Phase 1.
- [ ] Define coverage attribution enforcement, justified exceptions, negative controls, and the CI environment.
- [ ] Reconcile individual coding rules and exceptions with the existing `docs/QA.md` standards.
- [ ] Choose the publication-health default destination and resolve nullable callback API compatibility in Phase 4.

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

**Depends on:** none. **Outcome:** reproducible current state and isolated regression targets. **Status: In progress** (2026-11-05).

- [x] Record revision, dirty state, PHP version and extensions, Composer dependency state, and relevant runtime adapters. Do not overwrite or revert existing changes.
- [x] Run `composer validate --strict`, `composer api:check`, `composer analyse`, `composer refactor:dry`, `composer cs:check`, `composer architecture`, `composer lint:length`, `composer test`, `composer coverage`, and `composer audit`; capture exit codes and distinguish pre-existing failures from new ones. Run Rector before PHP-CS-Fixer. Inspect scripts in `composer.json` before changing commands.
- [x] Preserve coverage XML and denominator from the Unit-only run; do not merge results from Integration, Conformance, or Concurrency. Record branch-coverage and extension limitations.
- [x] Run the existing PhpMetrics and PHP Assumptions commands in `docs/REVIEW.md`, retain reports outside tracked source, and confirm which tools and versions are available after the update. Treat assumption reports as hypotheses.
- [x] Add or identify narrowly scoped fixtures for sensitive exception chains, fake clocks and byte estimators, failing publishers/reporters, interleaved Fibers, and third-party tokens. Put cross-layer tests in the appropriate non-Unit suite.
- [ ] Record baseline compatibility and representative hot-path benchmarks before altering publication, buffering, context storage, or serialization. Compatibility is accounted for by the [accepted reference run](#phase-0-compatibility-reference); performance remains open. Capture PHP version, workload, buffer sizes, and measurement variance.

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

At clean starting revision `919a75c80fa944b3b2164d0c70f47350d0846b80`, the
Swoole HTTP target harness gained post-handler clock-failure cases for successful
and failing handlers. PHP 8.4.27 and 8.5.12 each exited 1 with four target
failures: the two existing recorder failures and both new business-outcome
identity checks. Handler invocation and the second clock reading passed as
controls. The fake runner and nonthrowing observation recorder isolate this path;
measurement-recorder and initial clock failures, reporting, and real HTTP I/O
remain untested. This is a safety net, not a production fix or completion of
Phase 0; see `docs/QA.md` for the command.

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

**Exit:** baseline commands and limitations are recorded; each high-priority issue has a reproducible failing test or a documented reason it cannot yet be reproduced.

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

| Review issue                             | Existing reproduction                                                                                                                                                                                                                                                                                                                 | Remaining evidence                                                                                      |
|------------------------------------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|---------------------------------------------------------------------------------------------------------|
| Throwable redaction                      | `RedactingPublisherTest::testFailedExecutionThrowableCurrentlyBypassesRedaction`; composed `TelemetryPublisherTest::testFailedExecutionRedactionCurrentlyLeaksOuterThrowableToSpanEvent`; `tests/target-policy-regressions.php`; `tests/target-policy-psr3-regressions.php`; `tests/target-policy-otel-regressions.php`               | Broader exporter and configuration controls.                                                            |
| Sampling before redaction                | Factory-composed spy and typed-policy cases in `SamplingTest`; `tests/target-policy-regressions.php`                                                                                                                                                                                                                                  | Redaction-first implementation and broader typed routing tests.                                         |
| Buffer byte limit                        | `BufferingTest`; oversized and projected byte-bound controls in `tests/target-policy-regressions.php`                                                                                                                                                                                                                                 | Complete target-invariant matrix and approved edge semantics.                                           |
| Silent or recursive publication failures | [Evidence and target control](#publication-failure-evidence); [approved boundary](#decisions-required-before-dependent-changes)                                                                                                                                                                                                       | Phase 4 default destination, compatibility and regression matrix.                                       |
| Token interface compatibility            | Custom-token/composite ownership tests; approved `id(): int` break in changelog and API baseline                                                                                                                                                                                                                                      | Final migration, versioning and API-check verification for external implementers.                       |
| Unit coverage and attribution            | Unit-only XML and synthetic attribution controls recorded below                                                                                                                                                                                                                                                                       | Real-suite attribution, verified isolation control and 95% CI gate remain Phase 6 work.                 |
| Synchronous context under concurrency    | [Shared stack reproduces Fiber B seeing Fiber A's scope and parent ID](../tests/Concurrency/InterleavedFiberContextIsolationTest.php#L33); [Fiber storage isolates interleaved scopes](../tests/Concurrency/InterleavedFiberContextIsolationTest.php#L66). See the [approved boundary](#decisions-required-before-dependent-changes). | Mismatch reproduced; setup validation and its tests belong to Phase 1. OpenSwoole and Swow unavailable. |

The originally reported token break is approved, not erased, and coverage
enforcement belongs to Phase 6. Neither a nonzero baseline gate nor aggregate
coverage alone establishes whether Phase 0's reproduction exit is met. A
per-finding target-failure or documented inability is still required.

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
The default-risk characterization reproduces the mismatch; setup validation and
its regression tests are deferred to Phase 1 under the approved boundary. This is
distinct from unavailable OpenSwoole and Swow environments. Coverage below 95%
and the experimental attribution controls establish gaps, but do not yet provide
a CI threshold or isolation gate. The current no-callback behavior is reproduced,
and the recursion target already has a failing check. Verification of reporting
with the SDK default is deferred to Phase 4, where the default destination and
nullable callback compatibility will be decided.

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

## Phase 1: Compatibility and context ownership

**Completion depends on:** Phase 0 exit and the API identity decision; preparatory work may proceed earlier. **Review:** frozen interface break; concurrent context isolation.

- [ ] Inspect `src/Contract/Port/ContextTokenInterface.php`, `ContextStorageInterface`, the API baseline, all `id()` call sites, and concrete stack, Fiber, coroutine, and composite tokens. Verify composite delegation and ownership semantics under the retained contract.
- [x] Exercise custom tokens implementing the approved `id()` contract and their storage through composite attach/detach, correct owner delegation, foreign token rejection, repeated detach, and LIFO behavior. The former opaque interface is intentionally not supported by the new baseline.
- [ ] Complete the approved breaking-contract migration for affected internal consumers and retain correct token ownership and detachment semantics.
- [ ] Verify the approved break's changelog, baseline, versioning and migration documentation; run context storage conformance tests and `composer api:check` against the final tree.
- [x] Document `StackContextStorage` as synchronous-only at the SDK builder and concurrency entry points; show configuration of Fiber/coroutine storage for persistent workers.
- [ ] Add interleaving tests that demonstrate the default risk and verify isolated current scopes, parent identity, LIFO detach, restoration after exceptions, and repeated requests with runtime-specific storage. Use the coroutine extension where available and report skips explicitly.
- [ ] Implement the [approved concurrency validation boundary](#decisions-required-before-dependent-changes) and its opt-in custom-composition hook; choose the hook's API without changing the storage contract or requiring a new builder-interface method.
- [ ] Test ordinary synchronous nesting and generic setup without a runtime declaration; reject declared overlap with shared stack storage at setup with the prescribed actionable error; accept matching isolated storage; exercise the opt-in hook with custom composition. Retain the interleaving reproduction as mismatch evidence.

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

Further composed OpenTelemetry characterizations use real redacted artifacts and
in-memory exporters. In the tested occurrence configuration, an extension excluded
by the occurrence allow-list is still exported in both log and span-event fields;
the normalized occurrence time is used. For a redacted snapshot, the normalized
`created_at` is not read as the mapper's `time`, so its log uses the current wall
clock. The original content hash is exported to both signals even with
`SnapshotPayloadPolicy::None`. The tested mappings omit occurrence data and
snapshot payload; these cases do not establish safety for other configurations or
exporters. Routing, extension filtering, timestamp handling, and hash exposure
remain open security and correctness work.

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

Additional characterization shows that integer overflow in byte accounting
can throw after appending, leaving the item unaccounted and a negative estimate
after flushing. A throwing drop reporter can interrupt `DropOldest` after an
eviction, while `BlockWithTimeout` can append an oversized item after space is
freed. These are current behaviors, not approved policies or fixes.

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

**Preparatory characterization:** `OpenActivityPolicyTest` exercises a runner
with `FailExecutionOnOpenActivitiesPolicy` and an open activity. The critical
diagnostic says the execution is flagged as failed, but its published terminal
status remains succeeded or failed according to the business operation. Both the
returned object and thrown exception are preserved by identity, and the context
is detached. This describes current behavior, not the approved configurable
outcome; the misleading diagnostic and policy implementation remain open.

On PHP 8.5.12, the full `composer test -- --silent` run passed. The previous
PHP 8.4 direct-run claim is qualified by the refreshed Phase 0 results above.
The focused open-activity
test file passed with 5 tests and 28 assertions. Style and architecture checks
passed, and focused PHPStan analysis of that test file passed. The quality gate
stopped at the existing Rector callable-fixture suggestion; the independent
line-length check reported 26 violations outside these changes. Silent test runs
do not establish coverage for unavailable OpenSwoole or Swow extensions.

- [ ] Implement the [publication health reporting decision](#decisions-required-before-dependent-changes), including the replaceable SDK default, custom reporter, explicit silence, independent bounded/sanitized diagnostics and recursion guard. Decide the default destination and nullable callback compatibility; guard diagnostic extraction and keep fail-closed behavior explicit.
- [ ] With an injected destination rather than PHP's error log, test a throwing publisher with no custom callback, a working custom reporter, explicit silence, a throwing reporter, and a reporter re-entering publication. For each, cover successful and throwing business operations, assert the expected diagnostic or silence, exact result or original exception, and context restoration. Also cover fallback-sink and artifact-normalization failures.
- [x] Fix the `CallbackHealthReporter::report()` fail-open hole: guard artifact normalization and fallback reporting; verify runner outcomes and context restoration when either fails. Secondary failures go to a bounded, payload-free system-log notice; a throwing last-resort sink is contained. The normal fallback retains its existing behavior.
- [ ] In `DefaultExecutionRunner::publishTerminal()`, publish the completed execution independently from snapshot creation and publication; preserve normal execution-before-snapshot ordering unless explicitly changed. Extend `tests/Unit/Application/Runner/ResilienceTest.php` for successful and failing business operations, snapshot factory failure, execution publication failure, snapshot publication failure, original exception identity, and context restoration.
- [ ] For `FailExecutionOnOpenActivitiesPolicy`, first add a test to `tests/Unit/Application/Policy/OpenActivityPolicyTest.php` asserting the current observation, terminal status, and original result. Implement only the approved outcome/message semantics; update lifecycle documentation and compatibility notes if status behavior changes.
- [ ] Test nested executions, open activities, repeated completion, and adapter/health failures together at the appropriate suite level. Document the chosen publication and completion failure guarantees.

**Exit:** terminal outcomes remain observable and health failures are reported unless explicitly disabled, without masking business results; lifecycle and failure-injection tests pass under the approved policy.

The callback reporter fix is limited to its own secondary failure path. On PHP
8.5.12, the focused publication and runner tests passed, as did `composer
quality`; the focused tests also passed on
PHP 8.4.27. The system error log may fail silently, and a throwing system-log
destination was not reproduced. Recursive callback publication and the runner's
independent reporting path remain open; this change does not complete Phase 4.
The ten-subject benchmark suite passed without a controlled before/after
comparison and does not characterize this exceptional path.

## Phase 5: OpenTelemetry retention

**Depends on:** Phase 0 and the span-store retention decision. **Review:** unbounded span-context store.

**Preparatory characterization:** The default in-memory store retains execution
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
an evidence-backed production limit. Selecting the default and its policy remains
open.

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
