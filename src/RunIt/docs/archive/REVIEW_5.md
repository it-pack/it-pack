# RunIt — In-Depth Code Review

- **Subject:** `it-pack/src/RunIt` (current working tree, post Phase-0–3 refactoring)
- **Date:** 2026-08-25
- **Reviewer:** autonomous review (ox-alpha)
- **Scope:** features, architecture, maintenance, performance, maintainability,
  security, DX, SOLID, DRY, hexagonal architecture (ports/adapters/factories),
  modern PHP leverage (fibers, weak maps/refs, lazy objects, property hooks,
  virtual properties, traits, readonly classes), interface backing of every
  class, absence of abstract classes, complexity/control-flow hygiene,
  magic-literal discipline, import completeness, PSR-4 one-type-per-file,
  declaration-order compliance, line-length discipline, instantiation policy,
  test coverage (≥95% target), coverage leakage, mutation testing.
- **Method:** repo-wide scripted/token-level scans over `src` and `tests`
  (222 concrete classes, 13 ports, 8 adapter namespaces), a full pcov coverage
  run of the shipped pipeline (`Unit,Conformance,Integration,Concurrency`,
  760 tests / 5575 assertions), an Infection trial run to re-test local
  mutation viability, and targeted deep reads where scans flagged anomalies.
  No source was modified; every quality gate was executed in this environment
  (PHP 8.5.8).

---

## 1. Conceptual review checklist

1. **Architectural integrity** of the hexagonal boundary after Phases 0–3:
   ports, adapters, factories, dependency direction.
2. **SOLID and DRY**: single-responsibility shape, open/closed via decorators
   and policies, shared behaviors vs residual duplication.
3. **Interface backing**: every concrete class backed or permanently exempted;
   zero abstract classes; traits/composition as the inheritance substitute.
4. **Code discipline**: no `else`/`elseif`, no Yoda conditions, no magic
   literals (constants/enums living in contracts), imports complete, PSR-4
   one-type-per-file, ≤120 columns, mandated declaration order, `new` confined
   to factories/builders/DI seams.
5. **Modern PHP leverage**: readonly classes, enums, property hooks/virtual
   properties, fibers, weak maps, lazy objects, first-class-callable traits —
   used where they earn complexity, declined where measured.
6. **Verification quality**: statement coverage against the 95% bar, per-file
   gap census, suite-leakage audit, mutation-testing viability diagnosis.
7. **Security/performance posture**: bounds enforced end-to-end, redaction
   defaults, hot-path costs, exception-preservation guarantees post-hardening.

---

## 2. Summary scorecard

| Dimension | Verdict | Evidence |
|---|---|---|
| Hexagonal architecture | ✅ Strong | DepTrac 0 violations; Domain/Application import no adapters; 13 ports under `Contract/Port`, 8 adapter namespaces, dedicated factory layer |
| Ports & factories | ✅ Strong | Every port has ≥1 implementation and consumer; `PublisherStackFactory` wired through builder seam (Phase 0); named constructors + `StringIdentifierFactory` cover domain construction |
| SOLID | ✅ Strong | Decorator pipeline for publication concerns; policy objects for failure/sampling/completion; ISP respected (narrow recorders) |
| DRY | ✅ Strong | SequencedBehavior across six artifacts; StringValueEquality/BoundedKeyGuard shared walkers; residue limited to one message template reused ×3 |
| Interface backing | ✅ Complete | 196/222 concrete classes explicitly implement an interface; remaining 24 are exactly the QA.md exemption set (Behavior/Conformance helpers, attributes, PSR-3 loggers, config builders, pure carriers, last-resort sink) — all rationale-backed |
| Abstract classes | ✅ None | Zero `abstract class` declarations; `Snapshot` is the single documented non-final refinement base (ADR-015/QA.md) |
| Control flow | ✅ Strong | **Zero** `else`/`elseif`/`switch` in `src`; ComplexityGuardRule green; tests contain ~5 `else` sites (cosmetic) |
| Yoda conditions | ✅ None | Scan clean across `src` and `tests` |
| Magic literals | ✅ Strong | Projections via `ArtifactField`; content types/drop reasons/schema versions named; residual: `1_700_000_000` ×3 in the port fixture |
| Imports | ⚠ 14 FQCN strays | Nine real code lines (domain return types, runner VOs, mapper signature, trait default) + five generic annotations use inline `\ItPack\RunIt\…` instead of imports |
| PSR-4 one-type-per-file | ⚠ Test fixtures | `src` is fully compliant; **10 test files define helper classes inline**, including a true filename/class mismatch (`HmacSnapshotSignerTest` inside `ConfigurableHashCalculatorTest.php`) |
| Line length ≤120 | ⚠ 167 lines | All in signatures/extends lists/string literals; worst offenders are contract interfaces (191-char wrapped unions) |
| Declaration order | ✅ Compliant | Token-level audit: zero violations of use→const→property→constructor→factory→method→static ordering and visibility monotonicity |
| Instantiation policy | ✅ Compliant | All 63 `new` sites classify into legitimate buckets: exception throws, named constructors/factory bodies, DI default collaborators, harness internals; no business-method wiring |
| Modern PHP | ✅ Strong | 123 readonly classes, 14 enums, 9 traits, 118 property-hook sites, WeakMap for Fiber stacks + attribute cache, Fiber engine adapter; lazy objects deliberately unused (measured ≈0.29 µs null path) |
| Test coverage | ❌ 90.50% | Below the 95% bar: 3115/3442 statements; 6 fully-uncovered files; precise closure list below (+155 stmts needed) |
| Mutation testing | ⚠ Blocked locally | Infection now *runs* (pcov present) but aborts: dogfood subprocess test errors under Infection's runner (`DogfoodRunner.php:168`) |
| Security | ✅ Strong | Deny-by-default redaction (Phase 1), bounded caches/walkers with depth caps, throwable-code coercion, fail-safe recursion guard |
| Performance | ✅ Strong | Native byte estimation, monotonic clock ports, benchmark deltas recorded; hot paths reflection-free |
| DX | ✅ Strong | 20-seam immutable builder, conformance kit, honest gate reporting, runnable benchmarks; README examples match current API |
| Quality gates | ✅ All pass | validate ✔ cs ✔ analyse 0 errors ✔ deptrac 0 ✔ api-baseline ✔ tests **760** ✔ |

Legend: ✅ strong · △ adequate · ⚠ defect · ❌ below bar.

The two material defects are both in **verification**, not product code: the
coverage shortfall and the locally-blocked mutation run.

---

## 3. Findings

### P1-1 — Statement coverage is 90.50%, below the 95% requirement

Measured with pcov over the full shipped pipeline (760 tests): **3115/3442**
statements (90.50%). Closing to 95% requires **+155 covered statements**. The
gap is concentrated and cheap to close:

| Target | Uncovered stmts | Notes |
|---|---|---|
| `Application/Publication/RoutingArtifactPublisher.php` | ~10 | route-miss/fallback branches |
| `Conformance/Verifier/ArtifactPublisherVerifier.php` | ~25 | verifier not self-exercised (see P1-3) |
| `Domain/Measurement/MeasurementUnit.php` | ~12 | most enum cases never constructed |
| `Application/Publication/BoundDropException.php` | 6 | factory paths untested |
| `Behavior/Application/Publication/DelegatesPublisherLifecycleBehavior.php` | 4 | delegate branch |
| `Domain/Exception/{ActivityDepthExceeded,RejectedNestedExecution}Exception.php` | 6 | named constructors |
| `Adapter/Swoole/*` | ~8 | requires ext-swoole guard paths |
| Long tail 50–90% files (~30 files) | ~85 | assertion-strengthening |

Recommendation: a dedicated coverage-closure phase ordered by this table;
assertion-strengthening on existing tests before new test files.

### P1-2 — Mutation testing cannot complete locally

pcov is available and Infection starts correctly, but the run aborts during
the initial test loop: `tests/Unit/Tools/Dogfood/DogfoodRunnerTest.php`
(`runit.dogfood.fail`) errors at `tools/dogfood/src/Run/DogfoodRunner.php:168`
under Infection's process management (graceful-termination path kills the
subprocess mid-run). This supersedes the earlier "bootstrap broken" diagnosis:
the framework works; one self-referential test does not survive process
teardown. Options: exclude `tests/Unit/Tools/Dogfood` in `infection*.json5`
(dogfood validates tooling, not library mutations), or make DogfoodRunner
tolerate early SIGTERM. Until then MSI remains a CI-only signal — keep the
honest-pipeline note in place.

### P1-3 — Conformance kit is not self-exercised

`ArtifactPublisherVerifier` sits at 30.6% coverage: the project's own suite
does not feed the verifier its happy/negative fixtures end-to-end. This is an
inverse-leakage risk — the public conformance contract can regress silently.
Add a Conformance-suite self-test running each bundled verifier against
`RunItPortFixture`.

### P2-1 — Inline FQCN references bypass imports (14 sites)

Nine executable lines: `Observation/Snapshot/Occurrence::executionId()/
activityId()` return types, `DefaultExecutionRunner` ActivityName/Kind
constructions, `DefaultObservationRecorder` parameter type,
`DefaultCloudEventToOccurrenceMapper::map()` return type,
`ValidatedStringValueBehavior` default-parameter value. Five are
`@var`/generic annotations in runners/recorders. Mechanical fix: add imports,
shorten references.

### P2-2 — Ten test files violate one-type-per-file; one filename mismatch

Inline helper classes live beside their tests in 10 files (worst:
`ResilienceTest` defines 10 fakes). One file is a genuine PSR-4 defect:
`HmacSnapshotSignerTest` is declared inside `ConfigurableHashCalculatorTest.php`.
Consolidate reusable fakes into `tests/Support/Fixture` (several already exist
there — `RecordingPublisher`-style spies duplicate them), delete or rename the
mismatched file.

### P2-3 — 167 source lines exceed 120 columns

All are wrapped-signature/extends-list/literal cases, concentrated in contract
interfaces (`MeasurementRecorderInterface` up to 194 chars) and long union
returns. Mechanical wrap pass; no logic changes.

### P3-1 — Fixture magic number

`RunItPortFixture` uses `Instant::of(1_700_000_000)` three times; hoist to a
named fixture constant.

### P3-2 — DRY residue

One message template ("Activity %s was still open…") appears in three classes;
candidates for a shared message-format constant. Cosmetic.

### P3-3 — Trailing `else` in tests

Five cosmetic `else` branches in tests (`DefaultActivityRunnerTest`,
`TenantIsolationTest`, `ExecutionScopeTest`, fiber integration test). Align
with the src discipline for consistency.

---

## 4. Verified strengths (evidence-backed)

1. **Declaration order and instantiation policy are fully compliant.** A
   token-level audit found zero ordering violations across all class-like
   declarations, and every one of the 63 production `new` sites resolves to a
   sanctioned bucket (exception throws, named constructors, factory/builder
   bodies, DI defaults such as `new SecurityLimits()`, harness internals).
2. **Interface backing is complete under the documented exemption model.**
   196 of 222 concrete classes implement interfaces; the remaining 24 map
   one-to-one onto the permanent QA.md exemptions. Exceptions all carry marker
   interfaces (verified individually after scanner false positives).
3. **No abstract classes anywhere; inheritance is replaced by nine focused
   traits plus composition** (decorator publication pipeline, policy objects),
   matching the AGENTS.md model exactly.
4. **Modern PHP is adopted where it pays and declined where measured:**
   property hooks at 118 sites, WeakMap for per-Fiber stacks and the attribute
   metadata cache, a Fiber execution-engine adapter, lazy objects consciously
   unused with the ≈0.29 µs null-path measurement recorded in
   `docs/benchmarks.md`.
5. **Control-flow and literal hygiene hold in production code:** zero
   `else`/`elseif`/Yoda hits in `src`; projections, content types, schema
   versions, drop reasons, and diagnostic codes all live in named constants or
   enums within contracts.
6. **Hexagonal boundaries remain intact after three hardening phases:**
   DepTrac reports 0 violations; Domain and Application still import nothing
   from adapters; ports gained consumers through the Phase 0 builder seam.

---

## 5. Assumptions

- The 95% bar was evaluated as **statement coverage over `src`** produced by
  the repository's own shipped pipeline (`Unit,Conformance,Integration,
  Concurrency`), which is what `codeception.yml` configures coverage for.
- "Every class fully backed by interfaces" was evaluated with the existing,
  rationale-backed exemption registry in `docs/QA.md` treated as authoritative
  (it encodes AGENTS.md's "no semantic value" rule).
- Mutation testing was retried rather than assumed broken; the new failure
  mode above replaces the stale "bootstrap" explanation carried in prior docs.
- Coverage leakage was audited structurally (suite↔namespace mirroring, shared
  fixture placement, verifier self-exercise) rather than by per-suite coverage
  diffs, which would require three additional full runs; the one concrete
  inverse-gap found (P1-3) is reported.

## 6. Recommended order of work

1. **P1-1** coverage closure phase (table-driven, assertion-first).
2. **P1-2** unblock local mutation (dogfood exclusion or SIGTERM tolerance);
   then set an MSI floor for critical branching.
3. **P1-3** conformance self-exercise suite.
4. **P2-1..P2-3** mechanical hygiene passes (imports, test-fixture PSR-4 +
   mismatched file, 120-column wrap).
5. **P3-*** cosmetic residues.

No product-code defects were found in this pass; all P1 items concern the
verification apparatus that protects the product code.
