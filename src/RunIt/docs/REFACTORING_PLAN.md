# RunIt — Refactoring Plan

**Companion to:** `docs/REVIEW.md` (findings referenced by their IDs:
`§3 P1-1…P3-3`, recommended order `§6`).
**Date:** 2026-08-25
**Principle:** the only material defects found this cycle live in the
*verification apparatus*, not product code — so the plan closes verification
first (coverage bar, mutation viability, conformance self-exercise), then runs
the mechanical hygiene passes that reduce review noise (imports, PSR-4
fixtures, line length), then sweeps the cosmetic residues. Every phase is
independently shippable and gate-gated; hygiene phases must not change
behavior, so their diffs should be mechanically verifiable and free of API
surface changes.

**Source of truth for this plan:** `docs/REVIEW.md` (scripted/token-level
scans over 222 concrete classes, full pcov run of the shipped pipeline —
760 tests / 5575 assertions / 90.50% statement coverage — an Infection trial,
and targeted deep reads). No source has been modified yet.

---

## Cross-cutting rules for all phases

- Gates after every task: `composer validate --strict`, `vendor/bin/php-cs-fixer fix --dry-run`,
  `vendor/bin/phpstan analyse`, `vendor/bin/deptrac --config-file=deptrac.yaml`,
  `php tools/api-compat/api-compat.php check`,
  `vendor/bin/codecept run Unit,Conformance,Integration,Concurrency`.
- Coverage is measured **only** by the repository pipeline configuration
  (`codeception.yml`, pcov) over `src`; report the statement ratio produced by
  the same command every time so numbers stay comparable.
- No behavior changes in Phases 2–4 without a new REVIEW finding; if a hygiene
  edit would alter semantics, stop and split it out.
- No baselines are expected to move: no public-signature changes are planned.
  If a task turns out to touch a contract, add an UPGRADING/compatibility note
  and refresh `tools/api-compat/api-baseline.json` in the same commit.
- Mutation runs remain CI-reported until Phase 1 lands locally.

---

## Phase 0 — Verification gap: raise statement coverage to ≥95% (REVIEW §3 P1-1)

> **Status: Complete (2026-08-25).** Statement coverage is **95.24%
> (3278/3442)**, up from 90.50% — +163 covered statements via 69 new tests
> (813 Unit tests; 829 across the shipped pipeline). Closed by task:
> T0.1 publication branches (Routing lifecycle incl. fallback flush/close,
> BoundDrop factories via count-dropped terminal snapshots + eviction +
> throwing drop-handler, decorator lifecycle delegation trait); T0.2
> MeasurementUnit label+symbol truth table (labels are the lowercase
> vocabulary, symbols are the short forms — both now pinned); T0.3 depth and
> rejection boundaries exercised through the runner (`NestedBoundariesTest`)
> plus a success-path nesting case reaching ActivityScope delegations;
> T0.4 long-tail batches: runner bound-drop→health path with artifact-id
> fallback, ActivityOnly nesting semantics (runner + builder seams), OTel log
> mapper subject/activity/span-event/redacted paths, builder engine/nested/
> completion/mapper withers, serializer object+depth rejections, buffered
> publisher auto-flush/max-age/low-priority-fallback/default-sleep, PSR event
> extractor+projection skips, CloudEvent sequence/time coercion and envelope
> rejections, SeverityMap/SpanKindMap truth tables, ConformanceResult query
> API, SecurityLimits wither table, snapshot-chain predecessor cases,
> redacted-snapshot routing. `RunItTestKit` gained additive `limits` and
> `nestedPolicy` seams. **Deviation:** the two defensive catch blocks around
> `safePublish()` in the runner remain uncovered — nothing can escape
> `FailSafeArtifactPublisher` by design (guarded health + fail-open policy),
> so they are unreachable without contortion; recorded here rather than
> faked. The conformance-verifier gaps (~105 stmts) are deliberately left for
> Phase 2. QA.md records the new floor and the T0.5 leakage rule.

Goal: close the measured gap from **90.50% (3115/3442)** to **≥95%**
(≥3270 statements, i.e. **+155**) following the review's table order —
assertion-strengthening on existing tests before any new test files, and no
production-code edits except where a test exposes a genuine defect (in which
case record it as its own finding first).

### T0.1 — Publication-path branch closure (~16 stmts)
- **Targets:** `RoutingArtifactPublisher` route-miss/fallback branches (~10);
  `BoundDropException::forDropped()` factory paths (6);
  `SamplingDropException` (1); `DelegatesPublisherLifecycleBehavior`
  delegate branch (4).
- **Steps:**
  1. Extend `tests/Unit/Application/Publication/*`: artifact routed to a
     non-matching publisher (fallback exercised), empty route list, drop
     factories asserted via exception shape + message template.
  2. Prefer adding assertions to existing cases over new files; reuse
     `tests/Support/Fixture` spies instead of inline fakes (see T3.2).
- **Verify:** per-file coverage of each target ≥90%; suite green;
  `analyse`/`cs:check` green.

### T0.2 — Domain value-set completion (~12 stmts)
- **Target:** `Domain/Measurement/MeasurementUnit.php` — most enum cases never
  constructed (7.7% file coverage).
- **Steps:**
  1. Add a compact truth-table test constructing every case and asserting
     `label()`/normalization round-trips, mirroring the existing
     `ObservationSeverity`/`ExecutionStatus` matrix tests.
- **Verify:** `MeasurementUnit.php` at 100% line-statements.

### T0.3 — Exception named constructors (~6 stmts)
- **Targets:** `ActivityDepthExceededException`, `RejectedNestedExecutionException`.
- **Steps:**
  1. Assert each named constructor through the runner/runner-policy paths that
     trigger them (nested-depth limit, policy rejection) rather than calling
     the factory directly, so the tests pin behavior, not implementation.
- **Verify:** both files 100%; existing resilience tests still green.

### T0.4 — Long-tail strengthening (~85 stmts across ~30 files)
- **Goal:** lift the 50–90% band to ≥95% per file.
- **Steps:**
  1. Generate the current lowest-30 list from the coverage XML (the review's
     method, `--coverage-xml`) and work bottom-up in small batches.
  2. For each file: add missing-branch assertions to the *existing* test class
     first; create a new test class only when no natural home exists.
  3. Re-run coverage after each batch of ~5 files to track the aggregate.
- **Verify:** total statement coverage **≥95.00%**; record the final number in
  this plan's status block and in `docs/QA.md` (gate overview table).
- **Risk/BC:** none; tests only. Guard against accidental coupling: a new Unit
  test must not reach into Integration-only bootstrap (leakage rule below).

### T0.5 — Leakage audit as a standing rule
- **Goal:** keep suites honest while coverage grows (review §5 assumption).
- **Steps:**
  1. Document the audit basis in `docs/QA.md`: Unit mirrors Domain/Application/
     SDK namespaces; shared fakes live in `tests/Support/Fixture`; Conformance
     owns verifier exercise (see Phase 2).
  2. Spot-check rule: any Unit test requiring an Integration-only bootstrap or
     network/filesystem is misplaced — move it in the same PR that adds it.

---

## Phase 1 — Unblock local mutation testing; set an MSI floor (REVIEW §3 P1-2)

> **Status: Complete (2026-08-26), with a documented local-performance caveat.**
> T1.1: the dogfood teardown abort is fixed. `Tests\Unit\Tools\Dogfood\DogfoodRunnerTest`
> carries `@group dogfood`; both Infection profiles now pass
> `testFrameworkExtraArgs: "Unit --skip-group dogfood"`, so Infection's initial-test
> run no longer receives a SIGTERM-misattributed failure and proceeds through all source
> files, printing an MSI. Per-file runs confirm the pipeline works end-to-end (e.g.
> `FailSafeArtifactPublisher` / `BoundDropException` scopes execute and report). The
> preferred signal-handling fix from the plan did not apply: the current dogfood path uses
> a fake process runner, not a real subprocess, so the abort was a teardown-misattribution,
> resolved by exclusion as the plan's sanctioned fallback. T1.2: MSI floors in both configs
> are set **provisionally to 0** with a comment. A clean baseline could not be captured
> locally because, in this environment, Infection runs the **full Unit suite per mutant**
> (the Codeception adapter's covering-test selection does not narrow under the local pcov
> setup) and every mutant hits the 60s timeout — a pre-existing, documented limitation
> (QA.md M13.5). The authoritative baseline and the ratcheted floor are owned by CI, which
> runs `infection.json5` (full) and `infection.s1s6.json5` (critical branching) without the
> local timeout pressure. **Deviation recorded:** local `composer mutation` is now runnable
> (no abort) but a full local MSI number requires CI; the `staticAnalysisTool: phpstan`
> integration remains disabled (see QA.md) and can be re-enabled as a follow-up now that
> `composer analyse` is green.

Goal: make `composer mutation` runnable on a developer machine again, then put
a numeric floor under critical branching so future refactors get a mutation
signal, not just CI folklore.

### T1.1 — Fix or exclude the dogfood subprocess under Infection
- **Diagnosis (from review):** Infection itself works (pcov present); the run
  aborts because `tests/Unit/Tools/Dogfood/DogfoodRunnerTest.php`
  (`runit.dogfood.fail`) errors at `tools/dogfood/src/Run/DogfoodRunner.php:168`
  during graceful termination — the subprocess does not survive Infection's
  process teardown.
- **Files:** `tools/dogfood/src/Run/DogfoodRunner.php`,
  `tests/Unit/Tools/Dogfood/DogfoodRunnerTest.php`,
  `infection.json5`, `infection.s1s6.json5`.
- **Steps:**
  1. Preferred: make `DogfoodRunner` tolerate early SIGTERM/SIGINT — handle
     the termination signal around the proc call at :168 so the failing-run
     scenario still reports its expected exit path when torn down mid-flight.
  2. Verify the dogfood test passes standalone under `php -d pcov.enabled=1`
     and inside an Infection initial run.
  3. If signal handling proves runtime-dependent (proc_open signal delivery
     varies), fall back to excluding `tests/Unit/Tools/Dogfood` from both
     infection configs with a comment explaining why (dogfood validates the
     tooling, not library mutations) and note the exclusion here.
- **Verify:** `vendor/bin/infection --configuration=infection.s1s6.json5
  --only-covering-test-cases --no-interaction` completes and prints an MSI.

### T1.2 — Establish the MSI floor for critical branching
- **Steps:**
  1. Run the full `infection.json5` profile once locally; capture baseline MSI
     and per-mutator escapes in `docs/QA.md` (mutation section).
  2. Set `msi`/`coveredMsi` floors in CI config at baseline − small epsilon
     (document values here when set); AGENTS.md mutation priorities (state
     transitions, failure policies, context restoration, redaction, sampling,
     validators, adapter mappings) must show zero unresolved escapes above the
     floor before raising it.
- **Verify:** local full-profile run green; floors committed; remove the
  "CI-only" caveat from QA.md's pipeline-honesty note.

---

## Phase 2 — Conformance kit self-exercise (REVIEW §3 P1-3)

> **Status: Complete (2026-08-26).**
> Happy-path coverage already existed (`tests/Conformance/RunItPortConformanceTest.php`,
> zero-violation assertions per bundled adapter). T2.1 added a negative suite — nine
> `*VerifierNegativeTest` classes in `tests/Conformance/` (43 tests) — that breaks one
> port contract at a time and asserts the expected violation identifier and severity,
> including the mutation-detection branches via impure fixture doubles
> (`tests/Support/Fixture/Conformance/ImpureNormalizeArtifact|Observation|Snapshot`) and
> the typed `OverridingPortFixture` decorator; `RunItPortFixture` itself is untouched.
> Every `src/Conformance/Verifier/*` class now measures **100% line coverage** from the
> Conformance suite alone (target ≥95%). **Deviation recorded:** the plan's
> "keep verifier internals untouched" constraint could not hold — the new negative tests
> proved that `ContextStorageVerifier::safeAttach()/safeDetach()` and
> `SerializerVerifier::checkNormalize()` called `$result->add(...)` on the immutable
> result and discarded the returned instance, silently dropping `attach.throws`,
> `detach.throws`, and all `normalize.*` findings. Both verifiers were fixed to thread
> the updated `ConformanceResult` through; this is precisely the silent-regression class
> Phase 2 exists to catch. `docs/conformance.md` gained the "Self-exercised in CI"
> paragraph.

Goal: the project's own suite must feed every bundled verifier end-to-end, so
the public conformance contract cannot regress silently
(`ArtifactPublisherVerifier` currently sits at 30.6%).

### T2.1 — Self-conformance suite
- **Files:** `tests/Conformance/` (new self-test(s)),
  `src/Conformance/Verifier/*`, `src/Conformance/Fixture/RunItPortFixture.php`.
- **Steps:**
  1. Add Conformance-suite tests that run each bundled verifier against
     `RunItPortFixture` happy fixtures and assert **zero** violations.
  2. Add negative fixtures per verifier: deliberately break one port contract
     (drop-before-close, count/payload mismatch, sequence gaps) and assert the
     verifier reports the expected violation kind/severity.
  3. Keep verifier internals untouched; if exercising a branch requires new
     fixture shapes, extend `RunItPortFixture` (it is the sanctioned fixture
     surface).
- **Verify:** every `src/Conformance/Verifier/*` class ≥95% covered; suite
  green; `docs/conformance.md` gains a short "self-exercised in CI" paragraph.
- **Risk/BC:** none (test-side + fixture additions; fixture is @api — additive
  methods only, baseline refresh if touched).

---

## Phase 3 — Mechanical hygiene passes (REVIEW §3 P2-1..P2-3)

> **Status: Complete (2026-08-26), scan-driven (site counts drifted from the plan).**
> **T3.1 — inline FQCNs:** the scripted scan found 11 executable-line sites plus 3
> generic `@var` annotations (14 total, matching the plan's count after drift); all are
> imported now and the scan returns zero hits. No aliasing was required.
> **T3.2 — test PSR-4:** `HmacSnapshotSignerTest` was split into its own file (true PSR-4
> breakage). Reusable doubles were extracted to `tests/Support/Fixture` with dedupe:
> four recording publishers → one `RecordingArtifactPublisher`, two lifecycle spies → one
> `LifecycleSpy`, two failing stores → one `ThrowingSnapshotStore`, two failing publishers
> → the existing `ThrowingArtifactPublisher`, `NeverSamplePolicySpy` → shipped
> `NeverSamplePolicy`, plus new failure-injection doubles (`Throwing*` family,
> `EmptyArtifact`, `FakeContextToken`). The one-type-per-file allowance scope is documented
> in `docs/QA.md`; test counts unchanged (813 Unit / 865 with Conformance).
> **T3.3 — ≤120 columns:** 332 offending code lines across ~120 files were wrapped
> (signatures one parameter per line, parent lists alphabetically one interface per line,
> long literals split or hoisted). The guard ships as `composer lint:length`
> (`tools/lint/line-length.php`) wired into `composer quality`; the preferred PHPStan
> `FileRule` variant is unavailable against the phar-based PHPStan build, so the plan's
> sanctioned script alternative was chosen. **Incident note:** an intermediate automated
> wrapper produced silent truncations; it was reverted wholesale from git and redone with
> a constrained transformer plus per-file manual passes, verified by lint, full suites,
> PHPStan, PHP-CS-Fixer, and diff audits for dropped tails/parents.

Goal: eliminate the noise categories a reviewer must re-check every cycle.
These are behavior-preserving, diff-verifiable passes.

### T3.1 — Import all inline FQCNs (14 sites)
- **Sites:** return types on `Observation`/`Snapshot`/`Occurrence::
  executionId()/activityId()`, `DefaultExecutionRunner` ActivityName/Kind
  constructions (:169–170), `DefaultObservationRecorder` parameter type (:73),
  `DefaultCloudEventToOccurrenceMapper::map()` return type (:37),
  `ValidatedStringValueBehavior` default-parameter value (:43), plus five
  generic `@var` annotations in runners/recorders.
- **Steps:**
  1. Add the missing `use` imports; shorten references; keep PHPDoc generics
     accurate (`@var StringIdentifierFactoryInterface<ActivityIdInterface>`).
  2. No aliasing unless a genuine name collision exists (then alias with the
     domain term, not `X as X2`).
- **Verify:** scripted scan for `\ItPack\RunIt\` outside namespace/use/docblock
  `@see` links returns zero executable-line hits; gates green.

### T3.2 — Test PSR-4: one type per file (10 files + 1 true mismatch)
- **Defects:** `HmacSnapshotSignerTest` declared inside
  `ConfigurableHashCalculatorTest.php` (rename/split — real PSR-4 breakage);
  inline helper classes in 10 test files, worst `ResilienceTest` (10 fakes),
  `RunItBuilderTest` (8 helpers).
- **Steps:**
  1. Split/rename the mismatched file first (its own commit for reviewability).
  2. Move genuinely reusable fakes/spies into `tests/Support/Fixture`
     (deduplicate against existing spies — e.g. `RecordingPublisher` vs
     builder-suite spies), namespaced `Tests\Support\Fixture`.
  3. Keep single-use trivial fakes inline only where extraction would obscure
     the test's subject; document that allowance in `docs/QA.md` (one-type
     rule scope) so future scans don't re-flag them.
- **Verify:** scripted one-type-per-file scan clean or explicitly exempted;
  full suite green; no test-count regression.

### T3.3 — Enforce ≤120 columns (167 lines)
- **Worst offenders:** contract interfaces with wrapped unions/signatures
  (`MeasurementRecorderInterface` up to 194 chars), long extends lists
  (`ActivityInterface`:174), string literals.
- **Steps:**
  1. Wrap signatures one parameter/argument per line per AGENTS.md readability
     guidance; break extends lists alphabetically one interface per line.
  2. For long literals (message templates, IRIs), split with concatenation or
     hoist to a named constant (prefer the latter when the literal repeats).
  3. Add a guard so the category doesn't regrow: prefer a lightweight custom
     PHPStan rule consistent with the existing `tools/phpstan/runit-rules`
     set (fail on >120 outside docblocks); alternatively a documented
     `composer lint:length` script wired into `composer quality`.
- **Verify:** scan returns zero >120 non-docblock lines in `src` and `tests`;
  the chosen guard runs green in the standard gate chain.

---

## Phase 4 — Cosmetic residues (REVIEW §3 P3-1..P3-3)

> **Status: Pending**

### T4.1 — Name the fixture instant (P3-1)
- Hoist `Instant::of(1_700_000_000)` in `RunItPortFixture` to a private
  constant (`FIXED_INSTANT_NANOS` or a prebuilt `self::instant()` helper);
  single source for the three construction sites.

### T4.2 — Shared open-activity message template (P3-2)
- The "Activity %s was still open…" template lives in three classes; hoist to
  a named constant on the owning contract/interface (vocabulary home) and
  reference it everywhere; verify messages byte-identical before/after.

### T4.3 — Remove trailing `else` in tests (P3-3)
- Five sites (`DefaultActivityRunnerTest`, `TenantIsolationTest`,
  `ExecutionScopeTest`, fiber integration test): convert to early-return/
  exception-pattern style matching `src` discipline.

- **Verify (phase):** gates green; diff contains no behavioral changes.

---

## Milestone ordering & exit criteria

| Phase | Milestone | Exit criterion |
|---|---|---|
| 0 | Verification gap closed | Statement coverage ≥95.00% via the pinned pipeline; number recorded in QA.md |
| 1 | Local mutation viable | Infection completes locally; MSI floor committed |
| 2 | Conformance self-proof | All verifiers ≥95% covered incl. negative fixtures |
| 3 | Hygiene noise = 0 | FQCN/PSR-4/length scans clean with guards in place |
| 4 | Residues cleared | Cosmetic items done; no behavior deltas |

Phases 0–2 may interleave (different files), but land in order — coverage
first, since T1/T2 measurements depend on the strengthened suite.

---

## Out of scope (recorded, not planned)

- Any new product feature, default change, or contract change — none were
  flagged by REVIEW.md; product-code defects found during Phase 0 testing get
  their own finding + task instead of being folded in silently.
- Lazy-object adoption remains declined by measurement
  (`docs/benchmarks.md` ≈0.29 µs null path); revisit only with a consumer use case.
