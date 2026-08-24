# DeriveIt — Consolidation Plan: Aligning with the it-pack Execution Model

> Companion to `docs/EXECUTION.md` (the shared It-Pack execution model), the
> sister package `it-pack/run-it` (the model's reference implementation), and
> `docs/REVIEW.md` / `docs/REFACTORING_PLAN.md`.
> Prepared: 2026-08-24 · Package: `it-pack/derive-it` · Status: **C1–C3
> implemented; C4 (bridge package) open.** Gates after C3: `test:unit` **269
> tests / 572 assertions**, cs 0, analyse 0, deptrac 0 violations, goldens
> byte-stable. Plan deviations, all gate- or scope-driven and recorded in the
> implementation-status notes below: `DeriveObservationSeverity` landed with C3's
> consumers (dead-code gate), the first observation site is the previously-silent
> re-expose skip warning, per-trait assembly was de-duplicated (`assemble()`), and
> `DeriveIt::diff()` gained an optional trailing `?DeriveEventSinkInterface`
> parameter (additive) so CI drift hosts can wire telemetry without touching the
> other facade signatures.

**Hard constraint (from the review brief):** DeriveIt must **not** depend on
RunIt — not at composer level, and not at code level — yet must be able to work
with it in a decoupled state.

**Assumptions (stated per house style):**
- "Work with RunIt" means: when a consumer runs a derivation inside a RunIt
  execution, the derivation's activities, measurements, observations,
  occurrences, and resulting snapshot appear on that execution — without
  DeriveIt importing a single RunIt symbol.
- DeriveIt remains a dev-time tool; alignment must not change any derived
  output, public API signature, or quality-gate baseline.
- Where this document proposes names, they are suggestions for review, not
  final decisions.

---

## 0. TL;DR

DeriveIt already *behaves* like an execution producer — every facade call is a
bounded unit of work with an immutable input (`DeriveConfig` ≈ context), nested
work stages (pipeline steps ≈ activities), countable facts (`BuildMeta` counts ≈
measurements), diagnostics (leakage/collision/drift ≈ observations), immutable
result records (`DerivationResult`, `DeriveDiff` ≈ snapshots) — but it has no
vocabulary for any of that.

The recommended alignment is **Option D**: give DeriveIt its own tiny,
dependency-free emission contract (a null-object event sink plus a handful of
readonly events) whose shape deliberately mirrors RunIt's recorder surface, and
move all actual RunIt wiring into a future bridge package
(`it-pack/derive-it-runit`) following the established ConfigureItPsr11 /
`run-it-*` house pattern. Core stays pure (deps remain `php`, `nette/php-generator`,
`psr/cache`); the bridge is where the two packages meet.

---

## 1. What the execution model asks of DeriveIt

`docs/EXECUTION.md` defines six views over one root concept:

| Concept | Answers | DeriveIt equivalent today |
|---|---|---|
| **Execution** | one logical runtime operation | one derivation run (facade call or CLI invocation). EXECUTION.md explicitly lists "Build Process" as an execution — CI drift detection via `DeriveIt::diff()` is exactly that. |
| **Context** | *where am I executing?* | `DeriveConfig` + target class(es) + PHP version — an immutable input snapshot in everything but name |
| **Activity** | *what is happening now?* | invisible; pipeline stages exist only as call frames |
| **Measurement** | *how much?* | partially: `BuildMeta::$methods/$substitutions/$constants` are counts, but unframed and untimed |
| **Observation** | *what did we notice?* | only as thrown exceptions (leakage, collisions, empty surface) or CLI stderr text |
| **Occurrence** | *what happened?* | implicit ("an interface was derived"); never recorded as a fact |
| **Snapshot** | *what is the resulting state?* | closest match: `DerivationResult` / `BuildMeta` / `DeriveDiff` are already immutable result records |

Two structural facts drive the whole plan:

1. **Snapshots already exist.** DeriveIt's structured results are readonly value
   objects describing resulting state. They lack execution identity, timing, and
   sequence — which is precisely the metadata a host should attach, not the tool.
2. **Failures are fail-fast by design** (`LogicException` family, golden-file
   determinism). That is correct for the domain and should be preserved: the
   *host* decides whether a thrown derivation failure becomes a failed activity,
   an error observation, or a process exit — which is exactly how RunIt's
   throwable-normalization works.

---

## 2. Findings from inspecting RunIt

### F-1 — The SDK is the intended integration seam

`RunItInterface` exposes exactly three relevant verbs:

```php
run(ExecutionDefinitionInterface $definition, callable $operation): mixed; // run inside an execution
wrap(callable $operation, $definition = null): ExecutedCallableInterface;  // re-wrap any callable
currentExecution(): ?ExecutionScopeInterface;
```

The callable handed to `run()`/activity runners receives an
`ExecutionScopeInterface` carrying the five recorders:

```php
$scope->activities()->run($definition, fn(ActivityScopeInterface $s) => ...);
$scope->measurements()->counter($name, $value, ...);
$scope->observations()->record($message, $severity, ...);
$scope->occurrences()->record(...);
$scope->snapshots()->record(...);
```

Crucially, **the recorders bind ownership, timing, and sequence automatically**.
An integration therefore needs to transport *facts* (name + attributes), never
identity plumbing — a strong hint about what a decoupled DeriveIt should emit.

### F-2 — Names and kinds are open validated strings

`ExecutionKind`, activity kinds, measurement names, occurrence types, and
snapshot types are extensible validated strings (ADR-018), not closed enums:
"a new execution kind … should not require a code change in the domain".
A `derivation.*` vocabulary can be introduced by consumers/bridges with **zero
changes to RunIt**. This removes the last excuse for a direct dependency.

### F-3 — `#[ExecutedAs]` is metadata-only and optional

The SDK attribute decorates third-party code so `wrap()` can pick up a default
definition. Referencing it would import a RunIt symbol into DeriveIt —
unnecessary, since a bridge can supply definitions explicitly. DeriveIt should
instead *document* canonical execution/activity/kind labels (§5.3) so hosts and
dashboards see consistent values regardless of who wires the bridge.

### F-4 — The house pattern for cross-package integration is separate packages

Evidence across the monorepo:
- No sibling references RunIt today (`rg "RunIt"` outside `src/RunIt` → no hits);
  whoever integrates first sets the precedent.
- ConfigureIt integrations live in dedicated packages (`ConfigureItPsr11`,
  `ConfigureItSymfony`, `ConfigureItTesting`) that depend on both sides.
- EXECUTION.md itself plans the same split for the model
  (`it-pack/run-it-context`, `-otel`, `-psr`, …).
- RunIt's own dependency policy: core tiny, external standards opt-in via
  `suggest`, integration guides per target (`docs/integrations/*`).

⇒ A future `it-pack/derive-it-runit` bridge package is the idiomatic home for
wiring — not a require, not a suggest inside DeriveIt's runtime path.

### F-5 — RunIt ships a conformance toolkit for port implementors

`src/Conformance/` verifies any implementation of `ArtifactPublisherInterface`,
`ContextStorageInterface`, etc., runner-independent. The future bridge can reuse
this to prove its publisher adapters behave (ordering, recursion guard) instead
of inventing ad-hoc tests — free credibility, zero coupling for DeriveIt core.

### F-6 — Dependency footprints argue for separation

DeriveIt: `php >=8.4`, `nette/php-generator`, `psr/cache`.
RunIt requires `just-bases/php-obs-base` and carries adapter machinery
(fibers/swoole/PSR/OTel/CloudEvents). A direct or suggested dependency would
drag that footprint into a tool whose install base wants a generator and
nothing else — reinforcing Option D over B/C.

---

## 3. Gap analysis (DeriveIt ↔ execution model)

| Gap | Today | Consequence |
|---|---|---|
| No execution identity/boundaries | facade methods return bare results | a CI drift job cannot correlate N derivations as one build execution |
| No observation channel | exceptions or stderr strings | leakage/collision diagnostics can't flow into logs/OTel without parsing text |
| No activity visibility | pipeline stages are private call frames | profiling/tracing of large family scans impossible without Xdebug |
| Measurements unframed | counts buried in `BuildMeta`, returned but unnamed | dashboards would need bespoke extraction per field |
| Occurrences absent | "drift detected" lives only inside a `DeriveDiff` value | no event stream of what happened during a scan |
| CLI ports ad-hoc | `$output`/`$error` closures | right instinct (ports!), wrong granularity — they carry rendered bytes, not facts |

All gaps share one cause: DeriveIt emits **values**, never **events**.

---

## 4. Options considered

| Option | Idea | Verdict | Why |
|---|---|---|---|
| **A — Defer** | do nothing; DeriveIt is dev-time only | ✗ rejected | contradicts EXECUTION.md's "every package builds upon this model"; drift-detection-in-CI is squarely a listed execution type; deferral forfeits the chance to set a good precedent for the other 20+ It-Pack tools |
| **B — Require `it-pack/run-it`** | instrument directly against RunIt contracts | ✗ rejected | violates the hard constraint; drags `php-obs-base` + adapter surface into a minimal tool; couples release cadences |
| **C — Suggest + guard** | `suggest` run-it, feature-detect classes before use | ◐ rejected for core | softens composer coupling but keeps hard symbol-level coupling inside `src/`; blurs DeriveIt's own hexagonal boundary (a Generator layer referencing another package's Application contracts); acceptable only inside the bridge |
| **D — Own emission ports + external bridge** *(recommended)* | DeriveIt defines a dependency-free sink + event vocabulary mirroring RunIt's recorder shape; a separate `derive-it-runit` package maps them onto `ExecutionScopeInterface` recorders | ✅ recommended | satisfies the constraint literally and in spirit; zero-overhead null default preserves behavior/gates; mapping to RunIt becomes mechanical because shapes correspond; matches F-2/F-4/F-5 evidence |
| **E — Attribute-first** | adopt/consume `#[ExecutedAs]` for declarative wrapping | ✗ rejected for core | attribute lives in RunIt SDK (import = coupling, F-3); wrapping is host-side anyway — a bridge can wrap `DeriveItFactory`-built closures without DeriveIt knowing |

Why D over C even though C looks cheaper: the moment DeriveIt's internals
mention RunIt types — even behind `class_exists()` guards — its Contract layer
stops being self-contained, deptrac's clean layer story erodes, and tests need
conditional paths. Option D costs one interface + ~5 small DTOs and buys a
contract that is testable, serializable, and honest.

---

## 5. Recommended architecture (Option D)

```
┌─────────────────────────────── consumer / host ───────────────────────────────┐
│                                                                               │
│  ┌──────────────────┐     wraps      ┌──────────────────────────────────────┐ │
│  │  it-pack/run-it  │◄───────────────│  it-pack/derive-it-runit (bridge,    │ │
│  │  ExecutionScope  │   implements   │  future pkg; depends on BOTH sides)  │ │
│  │    recorders     │                │  RunItDerivationEventSink            │ │
│  └────────▲─────────┘                └──────────────▲───────────────────────┘ │
│           │ publishes facts                         │ implements              │
└───────────┼─────────────────────────────────────────┼─────────────────────────┘
            │                                         │
   ┌────────┴───────────────────── it-pack/derive-it ─┴────────────────────────┐
   │  Contract/Telemetry/DeriveEventSinkInterface   (+ NullDeriveEventSink)    │
   │  Generator emits events at stage boundaries        ▲                      │
   │  Entry (DeriveItFactory) threads the sink ─────────┘                      │
   └───────────────────────────────────────────────────────────────────────────┘
```

### 5.1 The core contract (dependency-free)

Method-per-kind rather than one generic event object: it maps 1:1 onto RunIt's
recorder methods (making the bridge mechanical), keeps IDE discoverability, and
PHP 8.4 named arguments keep payloads ergonomic. Payloads are plain arrays of
scalars — serializable by design, so a later CloudEvents export needs no new
core surface.

```php
namespace ItPack\DeriveIt\Contract\Telemetry;

interface DeriveEventSinkInterface
{
    /** @param array<string, string|int|float|bool|null> $attributes */
    public function activityStarted(string $name, array $attributes = []): void;

    /** @param array<string, string|int|float|bool|null> $attributes */
    public function activityFinished(string $name, bool $successful, array $attributes = []): void;

    /** @param array<string, string|int|float|bool|null> $attributes */
    public function measured(string $name, int|float $value, string $unit, array $attributes = []): void;

    /**
     * @param DeriveObservationSeverity::* $severity  local enum, PSR-3-shaped
     * @param array<string, string|int|float|bool|null> $attributes
     */
    public function observed(string $message, string $severity, array $attributes = []): void;

    /** Low-cardinality fact, e.g. `derivation.completed`, `drift.detected`. */
    public function occurred(string $type, array $attributes = []): void;
}
```

Plus:

- `final class NullDeriveEventSink implements DeriveEventSinkInterface {}` —
  every method an empty body. Default everywhere; zero allocation overhead.
- `enum DeriveObservationSeverity: string { Debug, Info, Warning, Error }` —
  PSR-3-shaped so the PSR adapter in RunIt maps it trivially.
- Threading: `DeriveItFactory::__construct(…, ?DeriveEventSinkInterface $sink = null)`
  → passed through the existing constructor-injection style into derivers.
  The static facade keeps its current signatures (uses the null sink).

### 5.2 Emission points (minimal viable instrumentation)

Deliberately coarse — per class/family member, **not** per method — to keep hot
paths quiet:

| Stage (activity) | Emits |
|---|---|
| derivation run begins/ends (facade entry, per top-level call) | `occurred('derivation.started'/'derivation.completed', {mode, target})` |
| graph walk (`FamilyDeriver::discover`, resolver calls in per-trait mode) | `activityStarted/Finished('graph.walk')`, `measured('family.members', n, 'count')` |
| surface extraction + filtering (per adaptee) | `activityStarted/Finished('surface.extract')`, `measured('methods.derived', n)`, `measured('methods.dropped', n)` |
| signature building + substitution | `measured('substitutions.applied', count(BuildMeta::$substitutions))` |
| constants lifting | `measured('constants.lifted', n)` |
| leakage audit (non-fatal notices only) | `observed(message, Warning/Error, {interface, type})` |
| rendering | `measured('source.bytes', strlen, 'By')` |
| `DeriveDiff::compute()` | `occurred('drift.detected'\|'drift.clean', {fqcn})` |
| naming collision / empty-surface / leakage **failures** | *nothing thrown-side* — keep fail-fast pure; hosts map throwables (F: RunIt normalizes them) |

Non-negotiables preserved: exception semantics unchanged; results unchanged;
goldens untouched (null sink default); no static state (sink instance-scoped).

### 5.3 Canonical vocabulary (documented so bridges agree)

Following RunIt's dot-separated conventions (open kinds — no RunIt change needed):

| Term | Value | Notes |
|---|---|---|
| execution kind | `derivation.run` | covers flat/per-trait/family/family-adapter/CLI |
| execution name | `derive.<mode>` e.g. `derive.family` | mode from `DeriveMode` |
| activities | `graph.walk`, `surface.extract`, `signature.build`, `types.substitute`, `leakage.audit`, `source.render`, `diff.compute` | nesting follows real call depth |
| occurrence types | `derivation.completed`, `adapter.generated`, `drift.detected`, `drift.clean` | low-cardinality per conformance guidance |
| measurements | `family.members`, `methods.derived`, `methods.dropped`, `substitutions.applied`, `constants.lifted`, `source.bytes` | units: `count` / `By` |

Timing, memory, ids, correlation, sampling, context storage: **out of core** —
the host/bridge owns clock and identity (RunIt recorders fill these in anyway);
DeriveIt emits only countable facts.

### 5.4 The bridge (future package, not part of DeriveIt)

`it-pack/derive-it-runit` (monorepo sibling, like ConfigureItPsr11):

- `composer.json`: requires `it-pack/run-it` + `it-pack/derive-it`. DeriveIt
  itself lists nothing new (not even under `suggest` — the bridge's existence
  is discoverable via Packagist, matching RunIt's own opt-in policy).
- `RunItDerivationEventSink implements DeriveEventSinkInterface`: constructor
  takes `ExecutionScopeInterface` (or the recorders individually) and forwards
  each fact — ownership/timing/sequence filled by RunIt.
- Optional helper: `DeriveItExecutions::wrap(RunItInterface $runit, DeriveMode $mode, callable $deriveOperation)`
  producing a `#[ExecutedAs]`-equivalent wrapped runner using §5.3 names.
- Docs page modeled on RunIt's `docs/integrations/*` + reuse of RunIt's
  conformance verifiers for any publisher adapters involved.

### 5.5 CLI alignment (optional, small)

`DeriveCommand`'s `$output`/`$error` closures stay (they are delivery adapters).
Add a verbose flag constructing a callback `DeriveEventSinkInterface` that
writes human-readable lines to stderr — turning the existing ad-hoc channel
into the same fact pipeline, with stdout byte-contract untouched.

---

## 6. Phased milestones (each gate-green, goldens byte-stable)

> Sequenced so every phase is independently shippable; C1–C3 live in DeriveIt,
> C4 is a new sibling package.

### C1 — Contracts & threading (pure additive)
1. Add `Contract/Telemetry/DeriveEventSinkInterface`, `NullDeriveEventSink`,
   `DeriveObservationSeverity` enum.
2. Thread optional sink through `DeriveItFactory` → derivers (default null).
3. deptrac: extend layers (`Contract` sublayer; Generator may use Contract).
**Acceptance:** all gates green; zero output diffs; new contract covered by
unit tests incl. null-sink no-op proof.

### C2 — Emission points
1. Instrument per §5.2 (activities around walk/extract/render; measurements
   from `BuildMeta`; occurrences for start/complete).
2. Unit tests: recording sink captures exact event sequences for a fixture
   (flat + per-trait + family ordering); failure paths emit nothing extra.
**Acceptance:** gates green; infection covers new branches; goldens stable.

### C3 — Drift + CLI surfaces
1. `DeriveDiff`/`DeriveIt::diff()` emit drift occurrences.
2. CLI `--verbose` diagnostics sink writing to the injected error closure.
**Acceptance:** stdout contracts unchanged; CLI tests extended.

### C4 — Bridge package `it-pack/derive-it-runit`
1. Scaffold sibling package (MIT, php ^8.4, requires run-it + derive-it).
2. Sink implementation + `DeriveItExecutions` wrapper + integration docs +
   conformance-checked examples.
3. Functional test deriving a fixture inside a real RunIt execution and
   asserting recorded artifacts (activities/measures/observations/occurrence).
**Acceptance:** RunIt conformance verifiers pass for involved adapters;
both upstream repos' suites unaffected.

### Documentation updates
- `docs/guide/architecture.md`: telemetry layer + data-flow note.
- New `docs/integrations/runit.md` (or bridge-hosted): recipes for CI drift
  jobs as executions.
- AGENTS.md layout table gains `Contract/Telemetry/`.

---

## 7. Risks & mitigations

| Risk | Mitigation |
|---|---|
| Event churn destabilizes the contract | method-per-kind + scalar attributes; treat additions as non-breaking, removals/rename via minor-major policy like RunIt's UPGRADING.md |
| Instrumentation noise slows large scans | coarse emission (per member, not per method); null sink is empty-body (no dispatch cost); benchmark before/after on the biggest fixture family |
| Vocabulary drift vs RunIt conventions | §5.3 table is the single source; bridge docs cross-link RunIt's names-and-kinds page; revisit after first bridge release |
| Overreach: rebuilding RunIt inside DeriveIt | explicit non-goals below — if a need appears there, it belongs in the bridge |
| Two sources of truth for severity | local enum mirrors PSR-3 levels; documented mapping row in §5.3 |

**Explicit non-goals for DeriveIt core:** clocks/timing, identifier generation,
context storage/propagation, sampling/redaction/publishing pipelines,
CloudEvents/OTel mappings, execution registries. All of these exist in RunIt
and are host concerns.

---

## 8. Open questions for the maintainer

1. Should `DeriveEventSinkInterface` live under `Contract/Telemetry/` (new
   sublayer) or `Contract/Execution/`? (Plan assumes Telemetry.)
2. Is a `snapshotProduced(DerivationResult $result)` convenience method wanted
   on the sink (passing the rich object to bridges), or should bridges rebuild
   snapshots from emitted facts? (Passing the object couples sinks to
   `Generator\DerivationResult` — acceptable since it sits next to the
   interface in DeriveIt's own Contract space, but it narrows the audience.)
3. For the bridge package name: `derive-it-runit` (mirrors `run-it-psr`
   direction-of-dependency naming) vs `runit-derive-it`. Plan assumes the former.
