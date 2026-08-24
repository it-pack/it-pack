# RunIt — In-Depth Code Review

- **Subject:** `it-pack/src/RunIt` (current working tree, post-refactoring-plan)
- **Date:** 2026-08-24
- **Reviewer:** autonomous review (ox-alpha)
- **Scope:** features, architecture, maintenance, performance, maintainability,
  security, DX, SOLID, DRY, hexagonal architecture (ports/adapters/factories),
  modern PHP leverage (fibers, weak maps/refs, lazy objects, property hooks,
  traits, readonly classes), interface coverage, complexity, control-flow and
  magic-value hygiene.
- **Method:** repo-wide scripted scans (218 concrete classes, 146 interfaces,
  14 enums, 10 traits) + three independent deep-read passes (Domain/Contract/
  Behavior; Application/SDK; Adapters/tests/conformance/config) + execution of
  every quality gate in this environment (PHP 8.5.8). Prior findings in
  `docs/archive/REVIEW_3.md` re-verified item by item. No source was modified.

---

## 1. Conceptual review checklist

1. **Architectural integrity** of the hexagonal boundary after the refactoring
   plan — ports/adapters/factories under DepTrac and the custom rule set.
2. **SOLID and DRY** post-consolidation: shared validators/walkers, collapsed
   constructor families, projection vocabulary adoption.
3. **Modern PHP leverage** including consciously declined features and their
   documented rationale.
4. **Control flow, complexity, and magic-value hygiene** after centralization.
5. **Security and performance posture**: bounds actually enforced end-to-end,
   redaction ordering, hot-path costs, exception-preservation guarantees.
6. **Maintenance and DX**: gate health, builder/conformance ergonomics,
   test-suite honesty, documentation coherence.
7. **Fresh verification** of every prior P0–P3 fix plus regression hunting in
   the code the refactoring itself introduced.

---

## 2. Summary scorecard

| Dimension | Verdict | Evidence |
|---|---|---|
| Hexagonal architecture / ports & adapters | ✅ Strong | DepTrac **0 violations** (2354 allowed); Behavior/Application now policed; zero core→adapter imports |
| Ports & factories | △ Strong, 2 loose ends | All ports implemented+consumed except `PublisherStackFactoryInterface` (unconsumed) and `SnapshotSignerInterface` (unwired) |
| SOLID | ✅ Strong | SRP/OCP/ISP/DIP visible; decorator composition; exemptions rationale-backed |
| DRY | ✅ Strong, residue remains | Key pattern single-sourced; walks merged; families collapsed — but `equals()` ×4, `assertValidKey()` ×2, `ExecutionSnapshot::create` copy, dead traits vs 6× `count()` boilerplate |
| Interface coverage | ✅ Strong | 206/218 explicit-backed; every exemption permanent + rationale-backed in QA.md; enums documented deliberate |
| Control flow / complexity | ✅ Strong | **0** `else`/`elseif`/`switch`; ComplexityGuardRule(19); no method >~61 lines; nesting ≤3 |
| Magic strings / numbers | ✅ Strong | Projections literal-free via `ArtifactField`; content types/types/schema/drop-reasons/priorities/diagnostic codes all named constants; few documented strays |
| Modern PHP (readonly/enum/fiber/weakmap/trait) | ✅ Strong | **121** readonly classes, 14 enums, 10 focused traits, WeakMap-per-Fiber isolation |
| Modern PHP (lazy objects / property hooks) | △ Measured decline | Lazy rejected *by measurement* (≈0.29 µs null path); hooks declined with recorded rationale — both documented |
| Domain semantics | ✅ Strong | Aggregate-owned matrix enforced; markers real (3 implementations); one asymmetry open (activity accepts non-terminal completion status) |
| Exception preservation | ⚠ One live hole | Staged completion is airtight for internal faults — but a **throwing health callback** can replace the business Throwable or abort the operation |
| Security | ✅ Strong defaults, opt-in redaction | Redact-before-export ordering centralized; **no default redactor** and no doc/example mentions `withRedactor` |
| Performance | ✅ Strong | Native payload estimation, clock-port discipline, bounded caches; hot paths reflection-free |
| Developer experience | ✅ Strong | 19-seam immutable builder, framework-neutral conformance kit, honest test pipeline, benchmarks runnable |
| Quality gates (all) | ✅ **All pass** | validate ✔ cs 0/523 ✔ analyse **0 errors** ✔ architecture 0 ✔ api ✔ tests **742** ✔ |

Legend: ✅ strong · △ adequate · ⚠ defect.

For the first time in the project's review history, **every quality gate is green**
— the remaining findings are hardening items, not gate failures.

---

## 3. Detailed findings

### 3.1 Architecture & hexagonal boundaries

- **Dependency direction holds everywhere.** DepTrac reports 0 violations across
  2354 allowed edges; `Behavior/Application` is now policed inside the
  Application layer and `Behavior/Domain` inside Domain. Zero adapter imports
  from core, zero upward imports from adapters, zero vendor types in contracts.
- **Ports are complete and consumed** — with two exceptions worth a decision:
  `PublisherStackFactoryInterface` has an implementation but **no consumer**
  anywhere (builder instantiates the concrete factory directly), and
  `SnapshotSignerInterface` (`HmacSnapshotSigner`) is implemented but wired into
  nothing. Both are candidates for adopt-or-remove; neither is load-bearing.
- **Placeholder cleanup verified clean.** `Adapter/Callback`, `SDK/Middleware`,
  and the Acceptance suite are gone with zero dangling imports; ROADMAP carries
  dated removal notes. Residue: `deptrac.yaml` still lists `Callback` in the
  Common-Adapter regex (harmless), and a stale generated
  `tests/Support/_generated/AcceptanceTesterActions.php` survives.
- **Factories:** `PublisherStackFactory` composes redaction closest to the sink
  and sampling outside it — the security-critical ordering lives in exactly one
  place and is invoked by the builder. The richer `PublicationPipeline`
  (guard→failsafe→filter→redact→sample→buffer→sink) exists, is correct, and is
  consumed **only by its own test** — see §3.5 for why that matters.

### 3.2 SOLID & interface coverage

- Every concrete class outside the documented exemption set implements a
  contract; every exemption entry in `extension.neon` was re-verified to exist
  and genuinely lack an interface (one harmless no-op entry: `AttributeKeys` is
  itself an interface). Rationales are categorized (attributes, transitively
  backed PSR-3 loggers, self-building builders, data carriers) rather than
  listed as pending work.
- SRP/OCP hold: publisher behavior changes by decoration; no class exhibits
  mixed responsibilities; the widest constructor remains the composition root
  (`DefaultExecutionRunner`, 23 params) — still the accepted trade-off.
- Two contract-level oddities: `PublicationPipeline` exposes **public mutable
  promoted fields** (unusual for this codebase's immutability discipline), and
  `CallbackHealthReporter` forwards a second artifact argument into a seam
  documented as `Closure(\Throwable): void` — an undocumented arity contract.

### 3.3 DRY

The refactoring consolidated what mattered; four residues remain:

1. `SequencedBehavior`/`TimestampedBehavior` are used **only by test fixtures**,
   while six artifacts hand-write identical `count()` bodies — adopt or delete.
2. `equals()` is written four times (identifier/string-value behaviors,
   `ContextKey`, `AttributeKey`), and `ContextSource::equals()` diverges by
   dropping the class-identity check the other four enforce.
3. `assertValidKey()` is duplicated between `AttributeValidator` and
   `ContextValueValidator` (identical logic, different exception family) — the
   same parameterization that unified the value walk would unify this.
4. `ExecutionSnapshot::create` copies `Snapshot::create` nearly line-for-line,
   differing only in an injected validator parameter.

Verified clean: key regex single-sourced via `KeyPattern`; the shared
`BoundedPrimitiveValueWalker` reproduces both legacy semantics exactly (git-diff
confirmed); `ArtifactField` adoption is complete inside Domain — though adapters
that read projections (`ObservationLoggerPublisher`, `DefaultOccurrenceLogMapper`,
`CloudEventPublisher`) still use field-name literals, recreating the exact
"S10 rename breaks redaction" risk `ArtifactField`'s own docblock warns about.

### 3.4 Control flow, complexity & magic values

- **Zero** `else`/`elseif`/`switch` (single doc-comment mention). Nesting ≤3;
  longest method ≈61 lines (`runExecution`); ComplexityGuardRule(19) flags
  nothing. Wide constructors remain confined to the accepted composition roots.
- Magic-value centralization holds: projections are literal-free, media types,
  snapshot type, schema version, drop reasons, priorities, diagnostic codes,
  log prefix, and PSR-3 levels are named constants, and
  `ExecutionContext::MAX_ENTRIES` derives from `SecurityLimits`.
  Documented strays: `'execution timed out'` default cause (`Timeout`),
  exception subject strings in value-object factories, and one leftover
  `'application/json'` default in `DefaultOccurrenceRecorder`.

### 3.5 Lifecycle, publication & the exception-preservation hole

All REVIEW_3 lifecycle fixes hold: aggregate-owned matrix, aggregate guards,
real `LifecycleExceptionInterface` implementations, private `ExecutionFailure`
constructor, staged completion protection. Fresh audit found the remaining
holes:

1. **A throwing health callback becomes the business failure.**
   `reportFailure()` invokes the user closure unguarded. On the success path a
   callback throw escapes into the outer catch, drives `completeFailure` with
   the callback's own throwable, and is rethrown as if the business operation
   had failed. Worse: a callback throw during the startup execution publish
   aborts the business operation entirely. The same exposure exists through
   `CallbackHealthReporter`. The ADR-008 guarantee currently depends on user
   closures honoring a `void` contract nothing enforces.
2. **Pre-try-block faults are never reported.** Identifier factories,
   `coordinator->start()`, scope construction, and context attach sit before
   the try; a custom collaborator failing there leaves a non-terminal execution
   with no health report at all.
3. **The default stack has no recursion guard.** `GuardedArtifactPublisher`
   exists but is reachable only through `PublicationPipeline`, which nothing in
   src composes. AGENTS.md mandates recursion protection on the publication
   path; the builder-wired stack — the one everyone will use — lacks it.
4. **Bounded drops are invisible by default.** The runner constructs
   `BoundedArtifactPublisher` without `onDropped`, and no builder seam reaches
   it; bound-exceeded drops in the default wiring are structurally unobservable.
5. Small bounded-state defects: an oversized **terminal** snapshot is dropped
   by the payload gate *before* the release-on-terminal branch, stranding its
   counter until generic eviction; `BufferedArtifactPublisher::close()` counts
   remainder drops without health reporting; standalone buffer drains propagate
   sink faults unreported; `FailClosedPolicy` rethrows before health reporting
   executes, so fail-closed paths are unobservable via the health channel.

### 3.6 Security

- Deny-by-default posture intact where wired: `PublisherStackFactory` orders
  redaction before sampling before any adapter; `ArtifactRedactionConfig`
  defaults to masking throwable messages, occurrence data, and snapshot
  payloads once a redactor is installed; the instrument cache is bounded; the
  OTel bridge never touches global providers and exports snapshot payloads only
  under explicit `Full` policy; PSR-3 bridges keep stable templates, reserved
  exception keys, and recursion guards.
- **The gap is default wiring and guidance, not components:** the builder
  installs no redactor, no example passes `withRedactor`, and neither OTel doc
  mentions redaction — while the runner docblock implies using the builder
  addresses unredacted publishing. Out-of-the-box RunIt exports unredacted.
  Additionally, occurrence extension attributes are copied into OTel log
  attributes without the metric-style allow-list (bounded upstream by
  validation, but looser than the metrics path).
- `ErrorLogHealthReporter` sanitizes messages through the mapper as designed,
  but still emits raw file/line — acceptable operator metadata, worth a
  docblock note.

### 3.7 Performance

- Hot-path work is lean: native payload-section estimation replaced encoding,
  all buffer timing flows through the monotonic-clock port with an injectable
  sleep seam, caches are bounded with release-on-terminal, and reflection
  remains confined to wrap-time metadata reading behind a WeakMap.
- Remaining costs worth knowing: projection-based redaction calls
  `normalize()` on every artifact even when nothing matches (inherent, but
  benchmark-worthy); domain validators are allocated per construction site on
  artifact-creation paths; `PayloadSectionByteEstimator` recursion has no depth
  cap (defense-in-depth only — inputs are depth-bounded by validators at
  creation); `ThrowableDescription` assumes `getCode(): int`, which native SPL
  signatures guarantee but extension-provided exceptions have historically
  violated — cheap `(int)` coercion would remove the edge.
- Benchmarks run and are recorded (`docs/benchmarks.md`); two new subjects pin
  the bounded gate and the OTel no-op path.

### 3.8 Modern PHP leverage

- `readonly`: **121 of 218** concrete classes; the seven services converted in
  Phase 3 hold; the only non-final class is the documented `Snapshot` extension
  base; exceptions justifiably remain plain `final`.
- Fibers: isolation model unchanged and sound — WeakMap-per-Fiber stacks,
  emptied-stack eviction, anchor/LIFO detach validation, coroutine-id reuse
  handled in the Swoole mirror, worker reset/flush hooks intact.
- Lazy objects: rejected **by measurement** in the last cycle (null-provider
  bridge path ≈0.29 µs, pinned by a benchmark subject); property hooks remain
  consciously declined with recorded rationale — every getter maps to an
  interface method, so hooks cannot replace them without breaking contracts.
  Both decisions are documented where future maintainers will find them.

### 3.9 Maintenance, DX & tests

- **All gates green** — including the first fully-green `composer analyse`
  (level max + 20 custom rules) in review history. Backward-compat check clean.
- Test pipeline is honest: Unit (573), Integration (3), Conformance (9),
  Concurrency (4) run by default; Functional excluded until populated.
  Concurrency covers fiber isolation across eight parallel fibers on one
  runtime, persistent-worker sequential reuse, and timeout/cancellation
  classification through mapper→coordinator→aggregate.
- Conformance kit remains genuinely framework-neutral with a runnable
  third-party example; minor doc nits (fixture path in `conformance.md`, a
  duplicated echo in the example, dev-autoload dependency in the in-repo
  runner).
- Documentation nits: `benchOtelNullProviderRecord` lacks a row in the
  baselines table; empty-catch blocks in `examples/opentelemetry.php` model
  poor failure hygiene; stale generated Acceptance actor; `deptrac.yaml`
  Callback token; phpstan analyzes the empty Functional dir.

---

## 4. Prioritized recommendations

### P0 — close the exception-preservation and observability holes

1. Guard health callbacks: wrap `reportFailure()` (and `onDropped`/
   `CallbackHealthReporter` invocation) so a throwing callback is itself
   captured and reported through a safe fallback (e.g., `error_log`), never
   escaping into business flow. Also protect the startup publish path so an
   internal fault cannot prevent the operation from running.
2. Move scope construction/id allocation/coordinator-start/attach inside the
   reported region (or add an equivalent catch-and-report), so every
   non-terminal execution leaves a trace.
3. Add the recursion guard to the default stack (compose
   `GuardedArtifactPublisher` in `RunItBuilder`/runner wiring, or fold the
   guard into `FailSafeArtifactPublisher`), and wire bounded-drop
   observability (builder seam or default health reporter for `onDropped`).

### P1 — make the security default real and fix bounded-state defects

4. Ship a deny-by-default redactor (builder default when none configured, or
   loud build()-time warning) and update runner/OTel docs + examples to show
   `withRedactor`; add an allow-list option for OTel log attributes mirroring
   the metrics path.
5. Release the execution counter before (or regardless of) the payload gate so
   dropped terminal snapshots do not strand counters; report `close()`
   remainder drops; make fail-closed policy failures visible to health;
   document standalone-buffer drain semantics.
6. Resolve the two dead-end contracts: consume `PublisherStackFactoryInterface`
   in the builder (it already uses the implementation) or remove it; wire or
   retire `SnapshotSignerInterface`.

### P2 — DRY residue and contract hygiene

7. Adopt-or-delete `SequencedBehavior`/`TimestampedBehavior`; unify `equals()`
   (and restore the class check in `ContextSource`); parameterize
   `assertValidKey()` like the value walk; deduplicate
   `ExecutionSnapshot::create` against `Snapshot::create`.
8. Point adapter projection readers at `ArtifactField` constants to close the
   S10 rename risk for good.
9. Fix doc/code drift: `ExecutionFailure` `@throws` type, `OccurrenceTypeFilter`
   accept-semantics, `ChildExecutionPolicy` dead branch,
   `CallbackHealthReporter` arity documentation, `PublicationPipeline` field
   visibility, oversized-array violation message.
10. Decide the activity-matrix question: either extend transition validation to
    activity completion targets or document explicitly why activities permit
    completing to any status while executions forbid it.

### P3 — polish

11. Defensive typing edges: coerce throwable codes, cap estimator depth,
    consider sharing one validator instance across construction sites.
12. Config/doc nits: stale `Callback` token, stale generated Acceptance actor,
    phpstan Functional path, benchmarks baseline row, conformance doc path,
    example empty catches, `'application/json'` recorder default.

---

## 5. Blockers & risks

- None gate-blocking: CI-facing gates are fully green.
- The exception-preservation hole (§3.5.1) is the highest-consequence residual
  risk precisely because the surrounding guarantees were just hardened — users
  reasonably trust them now.
- Mutation/benchmark regression gates remain CI-only concerns locally
  (Infection bootstrap limitation persists; phpbench works but baselines are
  single-run).

---

## 6. Follow-up on `docs/archive/REVIEW_3.md`

Every P0–P3 recommendation was re-verified as landed and holding: builder
seams, staged completion protection, aggregate-owned matrix (ADR-023),
exception markers (3 implementations; unused marker deleted), validator/walker
consolidation, `ArtifactField` adoption, bounded caches + sanitized health
reporting, native byte estimation + clock-port discipline, readonly sweep,
DepTrac layer completeness, magic-value constants, suite honesty, and the
measurement-backed lazy-object rejection. The scorecard deltas versus REVIEW_3
are improvements across every previously-flagged dimension; all findings in
this review are new or residual.

---

## 7. Assumptions

- Reviewed the current working tree at HEAD; sibling-package commits between
  phases did not touch RunIt.
- Gates executed under PHP 8.5.8 against a `^8.4` package; no version-specific
  divergence observed, 8.4-runtime parity not separately verified.
- Infection/mutation not executed (pre-existing local limitation); benchmark
  figures are environment-relative (no opcache, containerized) and meaningful
  for same-host comparison only.
- Structural claims come from scripted scans; semantic claims cite files read
  during this review (three full-layer passes plus targeted verification of
  every high-severity claim, including reproducing the health-callback escape
  path by reading and the estimator/validator boundaries by direct check). Not
  all 400+ files were read line-by-line.
