# CommandIt — Refactoring Plan

**Source of truth:** `docs/REVIEW.md` (in-depth review, 2026-08-24).
**Goal:** implement the review's P0–P3 roadmap as independently shippable milestones — fixing correctness defects first, making the architecture gate real, proving the failure guarantees with tests and mutation floors, then paying down design debt within the pre-1.0 window.
**Ordering:** milestones are numbered in recommended execution order. Each is independently shippable and must keep `composer quality` green. Cross-milestone dependencies are stated explicitly.

Conventions applied from `AGENTS.md`: every behavior change lands with tests at the narrowest level; original business exceptions are always preserved; external effects stay behind ports; DepTrac direction unchanged; public-surface changes are deliberate, documented, and (where they alter semantics) backed by an ADR in `docs/adr/`.

---

## How to execute

1. Pick one milestone. Read the referenced source and test files first.
2. Work the tasks in order; each task lists its files, change, and acceptance check.
3. Add/adjust tests **before or with** the behavior change; run the narrowest suite first (`composer test:unit`), then the full gate.
4. Run `composer quality` (validate → cs → analyse → architecture → test → audit). Keep it green.
5. Update the **Status** column in the milestone table below and tick task checkboxes as you go (`docs/ROADMAP.md` is archived; this file is the live tracker).
6. Do **not** bundle unrelated items into a milestone PR.
7. Where a milestone says **ADR required**, write the ADR in the same change and link it from the affected contracts' docblocks.

---

## Milestone Overview

| ID | Milestone | Findings | Depends on | ADR | Size | Status |
|---|---|---|---|---|---|---|
| M1 | Publication-path correctness hotfixes | F-02, F-03 | — | — | S | **Done (2026-08-25)** |
| M2 | CLI trustworthiness & registry policy | F-04, F-18, F-05 | — | 0004 | S/M | **Done (2026-08-25)** |
| M3 | Architecture-gate integrity & tooling hygiene | F-01, F-19 + config rot | — | — | M | **Done (2026-08-25)** |
| M4 | Failure-injection proof & mutation gates | F-10, F-11 | M1 | — | M | **Done (2026-08-28)** |
| M5 | Publication-pipeline hardening & reporting vocabulary | F-13, F-14, F-15 + §12 register (reporting half) | M1 | — | M | **Done (2026-08-28)** |
| M6 | Lifecycle semantics & domain invariant hardening | transition matrix, ctors, `endedAt`, F-16 | M4 (proof harness helps) | 0005 | M/L | **Done (2026-08-29)** |
| M7 | Port-surface refinement (ISP/LSP) pre-1.0 | F-06, F-07, serializer default | M4 | 0006, 0007 | M/L | Done (2026-08-29) |
| M8 | Structural DRY & typing consolidation | F-09, F-08 + §12 register (typing half) | — | — | M | Planned |
| M9 | Bounds & concurrency robustness | F-12, F-17 | M5 (marker/constants in place) | 0008 | M | Planned |
| M10 | Modern PHP evolution & docs polish | readonly adapters, lazy spike, perf micro-opts, changelog | M7, M8 | — | M | Planned |

### Traceability — every REVIEW.md finding lands somewhere

| Finding | Milestone | Finding | Milestone |
|---|---|---|---|
| F-01 DepTrac guard decorative | M3 | F-11 mutation thresholds 0 | M4 |
| F-02 truncation summary bug | M1 | F-12 fiber-unsafe guard counter | M9 |
| F-03 unguarded `fail()` in catch | M1 | F-13 redaction marker drift | M5 |
| F-04 CLI bridge leaks | M2 | F-14 objects bypass redaction | M5 |
| F-05 registry last-wins overwrite | M2 | F-15 substring over-redaction | M5 |
| F-06 reporter ISP dead weight | M7 | F-16 `entries()` secret exposure | M6 |
| F-07 coordinator LSP seam | M7 | F-17 unbounded retention surfaces | M9 |
| F-08 duck-typed adapter utilities | M8 (optional interface) | F-18 argv silent garbage | M2 |
| F-09 Argument/Option clones | M8 | F-19 archive link rot | M3 |
| F-10 missing reporter-failure injection test | M4 | §12 magic values | M5 (reporting) / M8 (typing) |

### Open decisions (resolve before the owning milestone starts)

| # | Decision | Options | Default recommendation | Blocks |
|---|---|---|---|---|
| D1 | Where Symfony-coupled CLI classes live | (a) keep `Adapter/CLI` namespaces, retarget DepTrac layers; (b) move to `src/Adapter/SymfonyConsole/` | **Resolved:** (a) — zero consumer break; revisit at 1.0 | M3 |
| D2 | Duplicate command registration | (a) throw; (b) document last-wins | **Resolved:** (a) — ADR 0004, `InvalidCommandException::duplicateCommand()` | M2 |
| D3 | Wire `SafeSerializer` by default in Bootstrap | (a) yes, normalize-on-store default; (b) stay `null`, consumer-only | (a) matches safe-by-default ethos | M7 |
| D4 | Transition matrix stance | (a) enforce legal predecessors; (b) document permissive aggregate | (a) enforce | M6 |
| D5 | In-memory storage bound default | (a) bounded 1000/DropOldest; (b) unbounded + docs | (a) | M9 |

---

## M1 — Publication-path correctness hotfixes

**Goal:** eliminate the two defects that can mislead forensics or break the library's core promise (original `Throwable` always wins). No public API change.

**Findings:** REVIEW F-02, F-03.

### Task 1.1 — Fix the truncation summary in `RedactionPolicy`

- [x] Capture the pre-slice count and compute the omitted delta from it:

```php
if (\count($value) > $this->maxArrayEntries) {
    $originalCount = \count($value);
    $value = \array_slice($value, 0, $this->maxArrayEntries, true);
    $value['_truncated'] = \sprintf('%d entries omitted', $originalCount - $this->maxArrayEntries);
}
```

- [x] Guard the synthetic key against collision: if `'_truncated'` already exists among kept keys, fall back to `'_truncated_2'` (loop while occupied). Document the sentinel keys in the class docblock.
- Files: `src/Adapter/Common/RedactionPolicy.php`.
- Tests (`tests/Unit/Adapter/Common/RedactionPolicyTest.php`):
  - 70-entry array ⇒ exactly 64 kept entries + `_truncated === '6 entries omitted'`.
  - Array already containing `_truncated` ⇒ sentinel renamed, original data preserved.
- Acceptance: `RedactionPolicyTest` green including the two new cases; `composer analyse` clean.

### Task 1.2 — Make the original `Throwable` unconditionally win in `CommandExecutor`

- [x] Wrap the failure-recording sequence inside the catch block defensively:

```php
} catch (\Throwable $thrown) {
    try {
        $durationMs = $this->durationMs(...);
        $failed = $this->coordinator->fail(...);
        $this->record(fn () => $this->storage->store($executionId, $failed));
    } catch (\Throwable) {
        // Recording the failure must never replace the business exception
        // (AGENTS.md: preserve the original Throwable). Health-routing of
        // recording failures is tracked for the reporter-port work (M5/M7).
    }

    throw $thrown;
}
```

- Files: `src/Application/CommandExecutor.php` (`dispatch()`, lines ~80–86). Update the class docblock only if wording changes.
- Tests (`tests/Unit/Application/CommandExecutorTest.php`):
  - Coordinator stub whose `fail()` throws `LogicException` + handler that throws a domain exception ⇒ dispatcher rethrows **the same instance** (`assertSame`), not the `LogicException`.
  - Storage stub whose second `store()` throws on the failure path ⇒ original exception still propagates.
- Acceptance: both new tests pass; full unit suite green; `composer quality` green.

---

## M2 — CLI trustworthiness & registry policy

**Goal:** the CLI boundary stops accepting garbage silently, prints errors where POSIX expects them, and routes payload rendering through the serialization port. Registration collisions become loud.

**Findings:** REVIEW F-04, F-18, F-05. **ADR:** `0004-command-name-collision-policy.md` (D2 outcome).

### Task 2.1 — Bridge output via the serializer port, errors on stderr

- [x] Add an optional constructor parameter `private ?SerializerInterface $serializer = null` to `ConsoleCommandBridge` (additive, BC-safe).
- [x] Rewrite `writeResult()`:
  - arrays **and objects**: if a serializer is present, `normalize()` first (catch `SerializationException` ⇒ render `{"error":"unserializable payload"}` and return FAILURE instead of dumping raw objects);
  - encode with `\json_encode($payload, \JSON_THROW_ON_ERROR)` inside try/catch; on `JsonException` render `'null'` plus a `<comment>` warning line — never silently;
  - scalars unchanged; `null` renders nothing.
- [x] Error path: replace `$output->writeln('<error>…</error>')` with `$output->getErrorOutput()->writeln(sprintf('<error>%s</error>', …))`; align the class docblock (stderr claim at lines 27–29 becomes true). *(Implemented via a `ConsoleOutputInterface` guard so `BufferedOutput`-based tests keep working; the error JSON is rendered through the same stderr-capable channel.)*
- Files: `src/Adapter/CLI/ConsoleCommandBridge.php`; `src/Bootstrap/CommandItFactory.php` stays untouched (bridge construction lives with `ConsoleCommandRegistry` consumers).
- Tests (`tests/Unit/Adapter/CLI/ConsoleCommandTest.php`):
  - handler throws ⇒ error text appears in **stderr buffer**, stdout empty, exit FAILURE;
  - handler returns object payload **without** serializer ⇒ normalized-error output, exit FAILURE (or documented fallback), raw object properties never printed;
  - object payload **with** `SafeSerializer` ⇒ JSON of `toArray()` form, exit SUCCESS;
  - payload triggering `JsonException` (invalid UTF-8 string) ⇒ `'null'` + warning, no exception escapes.
- Acceptance: all four new cases pass; existing five ConsoleCommand tests still green.

### Task 2.2 — Define and enforce the argv token grammar

- [x] Write the intended grammar down first (class docblock of `ArgvInputParser` + USER_GUIDE section):
  - long options only (`--name`, `--name=value`, `--name value`);
  - a token beginning with `-` followed by a digit or `.`, when expected as an option value, **is** the value (`--limit -5` ⇒ `limit=-5`);
  - `--` terminates option parsing; all later tokens are positional;
  - unknown provided option names are rejected with `InvalidCommandException::malformed('Unknown option "--%s".')`;
  - surplus positionals beyond declared arguments are rejected (`Unexpected argument "%s".`);
  - single-dash short flags: explicitly unsupported, documented.
- [x] Implement in `split()`:
  - collect `$providedOptions` while parsing; after the loop, diff against declared option names and throw on unknowns;
  - handle `--` by switching a `$optionsEnded` flag (and stop emitting `'' => true`);
  - value-lookahead accepts `-5`, `-5.5`, `-0.25` style tokens as values;
  - flatten nesting while you're there (guard clauses instead of the depth-4 `else` chain — removes three of the five repo-wide `else`s; the remaining `elseif/else` pair in `ArrayInputAdapter` is out of scope).
- Files: `src/Adapter/CLI/ArgvInputParser.php`.
- Tests (`tests/Unit/Adapter/CLI/ArgvInputParserTest.php`):
  - `--limit -5` and `--limit=-5` both coerce to int `-5`;
  - `--forc` (undeclared) throws with quoted name;
  - `-- --not-an-option` treats trailing token as positional;
  - extra positional throws;
  - existing five tests remain green (update any that relied on silent behavior).
- Acceptance: suite green; USER_GUIDE argv section matches implementation.

### Task 2.3 — Registry collision policy (+ ADR 0004)

- [x] Per D2 (recommend: throw): in `CommandRegistry::register()`, if `has($name)` ⇒ `throw InvalidCommandException::duplicateCommand($name)` *(dedicated named constructor instead of `malformed()` so the message reads cleanly)*.
- [x] Write `docs/adr/0004-command-name-collision-policy.md`: context (silent overwrite surprises CLI projection), decision (fail fast pre-1.0; explicit replacement API may come later), consequences.
- Files: `src/Application/CommandRegistry.php`, `docs/adr/0004-*.md`.
- Tests (`tests/Unit/Application/CommandRegistryTest.php`): duplicate register throws with the name in the message; re-registering after removing… (no remove API exists — assert throw only).
- Acceptance: green; ADR linked from `CommandRegistryInterface::register` docblock (`@see`).

---

## M3 — Architecture-gate integrity & tooling hygiene

**Goal:** make "Symfony-free core" *enforced* rather than aspirational, and clear out config rot so gates describe reality.

**Findings:** REVIEW F-01, F-19, §11 config rot. **Decision:** D1 resolved here.

### Task 3.1 — Fix the DepTrac Symfony layer (D1, recommend option a)

- [x] Change the vendor regex to match real FQCNs: `classNameRegex: '/^Symfony\\Component\\Console\\/'`.
- [x] Give the `SymfonyConsole` layer a ruleset mirroring Adapter's allowances so its own classes may use contracts and vendor types. *(Implemented as Contract/Domain/Port — the dead `Common` allowance was dropped with the layer.)*
- [x] Retarget layer membership (option a): remove `src/Adapter/CLI/.*` from the Adapter layer collector (`value: src/Adapter/(?!SymfonyConsole/|CLI/).*`) and add `directory: src/Adapter/CLI/.*` to the `SymfonyConsole` layer. Namespaces stay `ItPack\CommandIt\Adapter\CLI\...` — zero consumer impact.
- [x] Delete the dead `Common` layer (no `src/Common/`, plus every ruleset reference). Keep the `RunIt` regex layer.
- [x] Update the explanatory comment to describe what is now actually enforced.
- Files: `deptrac.yaml` only.
- Verification (three-step proof):
  1. `composer architecture` ⇒ 0 violations.
  2. `vendor/bin/deptrac debug:unassigned --config-file=deptrac.yaml` ⇒ **none** (this is the regression test for F-01; previously Symfony vendor tokens were invisible).
  3. Negative probe on a scratch branch: temporarily add `use Symfony\Component\Console\Output\NullOutput;` to any `src/Domain/**` file ⇒ `composer architecture` must FAIL; revert. Record the probe result in the PR description.
- Acceptance: all three steps behave as specified.

### Task 3.2 — Static-analysis and suite scaffolding cleanup

- [x] Remove `./tests/Acceptance` from `phpstan.neon` paths.
- [x] Delete dead Acceptance scaffolding: `tests/Acceptance.suite.yml` + empty dir, `AcceptanceTester.php` + its `_generated` actions; regenerated actors via `codecept build`. *(No codeception.yml suite entry existed — suites auto-discover from `*.suite.yml`.)*
- [x] Reconcile lockfile policy — resolved: keep ignoring `composer.lock` (library convention), rename the fictional CI leg `locked` → `highest` with an explanatory comment, keeping a truthful highest-vs-lowest matrix.
- Files: `phpstan.neon`, `tests/Acceptance*`, `codeception.yml`, `.github/workflows/quality.yml`, `.gitignore`.
- Acceptance: `composer analyse` and `composer test` green; CI workflow YAML valid (matrix legs reference real files).

### Task 3.3 — Repair archive links (F-19)

- [x] Fix citations to archived docs to their real `_1` filenames (README 1×, USER_GUIDE 5×). Verification grep yields only existing filenames.
- Acceptance: `grep -rn "archive/[A-Z_]*\.md" README.md docs/USER_GUIDE.md` yields only existing filenames.

---

## M4 — Failure-injection proof & mutation gates

**Goal:** convert the fail-open guarantees from "implemented" to "proven", and give the mutation suite teeth so future regressions in these paths surface automatically.

**Findings:** REVIEW F-10, F-11. **Depends on:** M1 (tests target the hardened catch path).

### Task 4.1 — Bare-reporter and bare-storage injection tests

- [x] Executor-level test with a reporter whose `reportOccurrence()` throws on the second call (wire it **directly** into `CommandExecutionCoordinator`, no `GuardedExecutionReporter`):
  - success dispatch ⇒ result returned intact, no reporter exception escapes;
  - handler-throws dispatch ⇒ **original** business `Throwable` rethrown (`assertSame`), execution completed FAILED, failure occurrence attempt swallowed.
- [x] Storage-level test: `InMemory`-stub whose `store()` always throws ⇒ both success and failure dispatches preserve business outcomes *(landed in M1 as the two preservation tests; M4 adds the bare-reporter + middleware cases).*
- [x] Middleware-throws-before-`next()` case: recorded as FAILED with the middleware's exception rethrown (add if not already covered explicitly).
- Files: `tests/Unit/Application/CommandExecutorTest.php` (new provider-based cases).
- Acceptance: suite green; each case names the guarantee it pins in its method name/docblock (mutation testing will target them next).

### Task 4.2 — Mutation floors

- [x] Set initial thresholds in `composer.json` script `mutation`: `--min-msi=55 --min-covered-msi=65` (calibrate after a baseline local run; raise over time).
- [x] Run `composer mutation` locally; triage the timed-out mutants. **Key finding:** the Infection↔Codeception adapter misclassifies most mutants as "time outs" (each process exits well under the 120s timeout, yet ~451/544 are flagged), which inflates reported MSI — see follow-up note. 12 genuinely-executed escapes were triaged: 10 killed by new assertions; 2 accepted as equivalent (the `'1'` boolean-arm `MatchArmRemoval` and the `RandomIdentifierGenerator` version-nibble integer mutants, which preserve the RFC 4122 v4 shape).
- [x] Keep the nightly CI schedule; add the floors there too (same script) — `composer mutation` already reads the floors from `composer.json`, so CI inherits them.
- Acceptance: `composer mutation` exits 0 at the chosen floors; floors recorded in this file's Status table.

---

## M5 — Publication-pipeline hardening & reporting vocabulary

**Goal:** close the remaining quiet-leak edges in the publication pipeline and give reporters/consumers a stable, constant-backed vocabulary.

**Findings:** REVIEW F-13, F-14, F-15, §12 register (reporting half).

### Task 5.1 — Unify the redaction-marker story (F-13)

- [ ] Declare the classification-stage sentinel beside its contract: `CommandContextInterface::REDACTED = '***'` (Domain may depend on Contract — verified direction), use it in `CommandContext::redacted()`.
- [ ] Declare `RedactionPolicyInterface::DEFAULT_MARKER = '***redacted***'`; `RedactionPolicy` defaults to it.
- [ ] Update USER_GUIDE (§ around 470 and 618–620) to describe the honest two-stage model: classification masking (`***`, always applied to ambient context) followed by policy masking (`***redacted***`, applied when the safe wrapper is used); bare-reporter users see `***`.
- Acceptance: docs and code agree; grep finds no inline `'***'` literals outside the constants.

### Task 5.2 — Objects in published context (F-14)

- [ ] `RedactionPolicy::redactValue()`: add an `\is_object($value)` branch — objects with `toArray()` reduce through it; `\Stringable` reduce to `(string)`; anything else ⇒ `marker . '(object)'`. Mirrors `SafeSerializer`'s accepted shapes so policy and serializer agree.
- Tests: nested object-with-`toArray` gets reduced and its sensitive keys redacted; arbitrary object ⇒ `(object)` marker.
- Acceptance: no object passes through `redact()` unchanged.

### Task 5.3 — Matching strategy transparency (F-15)

- [ ] Document the substring semantics prominently (`@param` + class docblock): case-insensitive substring, safe-direction over-redaction (`key` ⊂ `monkey`).
- [ ] Add an optional exact-match mode: `bool $exactKeyMatch = false` constructor flag — when true, sensitivity compares whole lowercased keys (`\in_array`). Default stays substring (BC).
- Tests: exact mode does not redact `monkey`; substring mode still does.
- Acceptance: USER_GUIDE security section shows both modes.

### Task 5.4 — Constant-backed reporting vocabulary (§12 register, reporting half)

- [ ] Occurrence context keys → public constants on `ExecutionReporterInterface`: `CONTEXT_EXECUTION_ID = 'execution_id'`, `CONTEXT_COMMAND = 'command'`, `CONTEXT_STATUS = 'status'`, `CONTEXT_ERROR_CLASS`, `CONTEXT_ERROR_MESSAGE`. Use them in `CommandExecutionCoordinator` and `CommandExecutor::buildContext()`.
- [ ] Measurement identity → `ExecutionReporterInterface::MEASUREMENT_DURATION = 'duration_ms'` + `DURATION_UNIT_MS = 'ms'`; use in coordinator.
- [ ] Health signals → `GuardedExecutionReporter::SIGNAL_RECURSION_GUARDED` / `SIGNAL_PUBLICATION_FAILED`; kind labels → private const set (`KIND_OBSERVATION`, …) in Guarded/Collecting.
- [x] Activity id prefix → `CollectingExecutionReporter::ACTIVITY_ID_PREFIX = 'act-'`.
- Files: `src/Contract/Port/ExecutionReporterInterface.php`, `src/Application/CommandExecutionCoordinator.php`, `src/Application/CommandExecutor.php`, `src/Adapter/Reporting/{GuardedExecutionReporter,CollectingExecutionReporter}.php`.
- Acceptance: `composer analyse` + suites green; grep shows no bare occurrences of these strings in logic (tests may assert literal values deliberately).

---

## M6 — Lifecycle semantics & domain invariant hardening

**Goal:** make the execution state machine honest — illegal transitions impossible by construction, invalid states unrepresentable through public constructors.

**Findings:** REVIEW §14 P2 items 13–15, F-16. **Decisions:** D4. **ADR:** `0005-execution-transition-matrix.md`.

### Task 6.1 — Enforce the transition matrix (D4, recommend enforce)

- [x] In `CommandExecution::transition()`, restrict moves to legal predecessors:

```text
Defined   → Received
Received  → Executing
Executing → Succeeded | Failed        (via complete())
(Cancelled reserved — no incoming edges until a CancellationPort exists)
```

- [x] Implementation: private const map `ALLOWED_TRANSITIONS` (status value → list of allowed next statuses) consulted before copying; violation ⇒ `CommandExecutionException` with both statuses interpolated. `complete()` additionally requires current status `Executing` (today any non-terminal completes).
- [x] Write `docs/adr/0005-execution-transition-matrix.md` (context: aggregate was terminal-only-permissive; decision: strict matrix matching AGENTS lifecycle diagram; consequences: custom coordinators driving unusual sequences must follow the machine).
- Files: `src/Domain/CommandExecution.php`; coordinator already conforms (`begin` Defined→Received; `succeed/fail` Received→Executing→terminal).
- Tests (`tests/Unit/Domain/CommandExecutionTest.php`): update/extend — Defined→Failed throws; Failed→Received throws; Received→Received throws; complete-from-Received throws; the happy chain still returns copies. Prior test asserting arbitrary non-terminal transitions gets inverted intentionally (behavior change — note in PR).
- Mutation: the matrix is branching security/lifecycle logic — confirm Task 4.2's floors catch killed/alive mutants here.
- Acceptance: green incl. coordinator suite; ADR linked from `transition()` docblock.

### Task 6.2 — Constructor hardening

- [x] `CommandExecution::__construct` → **private**; `define()` remains the public factory; `transition()/complete()` construct internally (same class — allowed). Removes the terminal-without-result / result-without-terminal invalid states (REVIEW §8.2).
- [x] `complete()` validates `endedAt >= startedAt`, else `CommandExecutionException('Execution ended before it started.')`.
- Files: `src/Domain/CommandExecution.php`; fix any test constructing directly (switch to `define()`).
- Acceptance: suite green; `grep -rn "new CommandExecution(" src tests` shows only intra-class sites.

### Task 6.3 — `CommandContext` entry-shape validation + secret-exposure guidance (F-16)

- [x] Constructor (or a private `fromEntries` used by `with()`): iterate entries; each must be `array{value: mixed, classification: Classification}` — else `InvalidCommandException::malformed('Invalid context entry for key "%s".')`. Prevents the deferred fatal in `redacted()`.
- [x] `entries()`: docblock gains an explicit security warning (raw Secret values — internal/serialization use only) **and** add `redactedEntries(): array<string,mixed>` as the safe accessor for general consumers.
- Files: `src/Domain/CommandContext.php`, `src/Contract/Domain/CommandContextInterface.php` (additive method).
- Tests: malformed entry throws at construction; `redactedEntries()` hides secrets symmetrically with `redacted()`.
- Acceptance: green; interface addition documented as intentional pre-1.0 API growth.

---

## M7 — Port-surface refinement (ISP/LSP) pre-1.0

**Goal:** fix the two contract-level lies identified by SOLID analysis while the surface is still cheap to change, and settle the serializer default.

**Findings:** REVIEW F-06, F-07, D3. **ADR:** `0006-reporter-interface-segregation.md`, `0007-default-serializer.md`.

### Task 7.1 — Split the reporter port along real usage (F-06)

- [x] New `Contract/Port/LifecycleReporterInterface`:

```php
reportOccurrence(string $type, array $context = []): void
reportMeasurement(string $name, int|float $value, string $unit): void
```

- [x] `ExecutionReporterInterface extends LifecycleReporterInterface` (keeps `reportObservation`, `beginActivity`, `endActivity`) — existing implementations and consumers compile unchanged.
- [x] Narrow consumption: `CommandExecutionCoordinator` constructor + property type become `LifecycleReporterInterface`; `CommandIt`/`CommandItFactory` keep accepting/wiring full reporters (superset satisfies subset).
- [x] ADR 0006 documents: core emits occurrences/measurements only today; activity/observation methods serve adapters and future model legs; the fat interface persists for composition compatibility, reviewed again when Activities land.
- Acceptance: `composer analyse` green; a test fixture implementing **only** `LifecycleReporterInterface` successfully drives a full dispatch through a hand-assembled executor (proves the seam).

### Task 7.2 — Make the coordinator contract tell the truth (F-07)

- [x] Change `CommandExecutionCoordinatorInterface` signatures from `ExecutionArtifactInterface` to the concrete `Domain\CommandExecution` (Application contracts may reference Domain — verified direction). Delete `toExecution()` and its `LogicException`.
- [x] Rationale in ADR 0006 (shared: contract honesty pre-1.0); polymorphic artifacts remain a post-model possibility, noted as such.
- Files: `src/Contract/Application/CommandExecutionCoordinatorInterface.php`, `src/Application/CommandExecutionCoordinator.php`, any test doubles typed against the old signature.
- Acceptance: no `instanceof` downcast remains in the coordinator; suites green. Breaking-signature change documented in PR (pre-1.0 policy).

### Task 7.3 — Default serializer wiring (D3, recommend wire)

- [x] `CommandItFactory::create()`: `$serializer ??= new SafeSerializer();` — payloads become normalized-before-store by default; explicit `null` passthrough removed (callers wanting verbatim storage pass a no-op `SerializerInterface` — provide `PassthroughSerializer` in `Adapter/Common` for that escape hatch).
- [x] ADR 0007: safe-by-default alignment; consequence — stored snapshots are primitive-only unless opted out.
- Files: `src/Bootstrap/CommandItFactory.php`, new `src/Adapter/Common/PassthroughSerializer.php`, `examples/01-quickstart.php` output check (should be unaffected), USER_GUIDE factory-defaults table.
- Tests: factory-produced instance stores a normalized snapshot for an object payload with `toArray()` (extend `CommandExecutorTest` integration case); example 05 (custom ports) still runs.
- Acceptance: `php examples/05-custom-ports.php` exit 0; suites green.

---

## M8 — Structural DRY & typing consolidation

**Goal:** remove wholesale duplication and inline policy literals without changing observable behavior (pure refactor milestone — tests should need no behavioral edits).

**Findings:** REVIEW F-09, F-08, §12 register (typing half).

### Task 8.1 — Shared input-spec behavior (F-09)

- [ ] New `Behavior/Domain/InputSpecBehavior`: stateless getters derived from abstract primitives `name()` and `kind()` (mirrors `CommandNamedBehavior`'s abstract-method harmony): `isRequired()`, `defaultValueOrNull()`, `typeOrString()`, plus a `describe(): string` used by coercion error messages.
- [ ] Slim `Argument`/`Option` to promoted-constructor + primitives + trait; interfaces untouched (twin contracts stay — they are semantically distinct seams).
- Acceptance: `composer analyse` + full suites green with **zero** test edits; LOC delta negative in Domain.

### Task 8.2 — Identifier-name validation behavior

- [ ] New `Behavior/Domain/IdentifierNameBehavior extends` the `assertAdditionalRules()` hook pattern shared by `ArgumentName`/`OptionName` (regex + message live once).
- Acceptance: VO suites green; duplicated regex gone (single definition site).

### Task 8.3 — Enum label trait + missing label contracts

- [ ] `Behavior/Domain/LabelableBehavior` providing `label(): string { return $this->value; }`; adopted by `CommandStatus`, `CommandResultStatus`, `InputKind` (enums may use traits).
- [ ] Add dedicated label interfaces mirroring the existing pattern for the two bare enums: `ClassificationInterface` (Contract/Domain), `OverflowPolicyInterface` (Adapter/Reporting); enums implement.
- Acceptance: `ClosedEnumsTest` extended to cover the two newly contracted enums.

### Task 8.4 — Coercion vocabulary & micro-typing

- [ ] `final class InputType` (Behavior/Domain) with `const STRING/INT/FLOAT/BOOL` + `normalize('double'): FLOAT` legacy alias map; `InputCoercionBehavior` match arms consume the constants; the unreachable `'double'` arm disappears after normalization (behavior identical — `'double'` inputs still coerce as float, now documented as legacy alias).
- [ ] Bool aliases → `private const BOOL_TRUE_ALIASES / BOOL_FALSE_ALIASES`.
- [ ] `ValidatedStringValueBehavior::MAX_LENGTH = 255` public const replaces the twice-used literal.
- [ ] Deduplicate `names()` into `InputCoercionBehavior` (protected final); delete the two private copies.
- [ ] Extract the unreachable-arm throw to a `private static function rejectUnknownType(string $type): never`.
- [ ] `LazyHandlerProxy::$factory` → typed `private Closure $factory` (constructor validates `is_callable` / accepts `Closure`).
- Acceptance: suites green; `composer mutation` still above floors (coercion branches are mutation targets — verify kills survived the refactor).

### Task 8.5 — Exception markers (optional, additive)

- [ ] Dedicated marker interfaces for the remaining four exceptions (`HandlerNotFoundExceptionInterface`, `InvalidCommandExceptionInterface`, `CommandExecutionExceptionInterface`, plus `BufferOverflowException` under the Reporting namespace) implementing `CommandExceptionInterface`; concrete finals implement them. Enables precise catching without coupling to concrete classes.
- Acceptance: `composer analyse` green; no consumer break (pure addition).

---

## M9 — Bounds & concurrency robustness

**Goal:** bound every unbounded retention surface and make the recursion guard correct under fibers — the two robustness gaps that bite only in production-shaped runtimes.

**Findings:** REVIEW F-17, F-12. **ADR:** `0008-context-provider-reset-semantics.md`. **Decision:** D5.

### Task 9.1 — Serializer cycle & depth guards

- [ ] `SafeSerializer::normalize()`: carry a visited-set (`SplObjectStorage`) through recursion; revisiting an object ⇒ `SerializationException::cyclic()`. Add `maxDepth` guard (default 32) ⇒ `SerializationException::tooDeep()` (named constructors).
- Tests: self-referential array throws `cyclic`; `toArray()` returning a structure containing the original object throws `cyclic`; 40-level nesting throws `tooDeep`.
- Acceptance: hostile-input tests green; existing SafeSerializer tests untouched.

### Task 9.2 — Bounded in-memory storage (D5, recommend bounded)

- [ ] `InMemoryCommandExecutionStorage` ctor: `int $maxEntries = 1000` (0 = unbounded, documented); insertions beyond the cap evict oldest (preserving insertion order via `array_key_first` + `unset` — avoids `array_shift` reindex cost).
- [ ] Additive API: `clear(): void`, `count(): int`.
- Tests: eviction order; zero means unbounded; round-trip after eviction of a middle entry.
- Acceptance: green; USER_GUIDE storage section updated; Null storage unchanged.

### Task 9.3 — Context provider reset semantics (+ ADR 0008)

- [ ] Additive API: `ContextStack::reset(): void` (clears items); `AmbientContextStorage::reset()` delegates; `FiberBoundContextStorage::reset()` resets **the calling fiber's** stack only (WeakMap is not iterable — process-wide reset is impossible by design; document that).
- [ ] ADR 0008: why reset is scoped per-fiber, persistent-worker guidance (call `reset()` between requests on the main scope), and why `reset()` stays off the port interface (adapter-specific operational concern, not a domain capability).
- Tests: Functional — repeated dispatch + `reset()` leaves zero residue on main and current fiber; mismatched-token pop after reset throws `InvalidContextTokenException` (loud, not silent).
- Acceptance: `PersistentWorkerIsolationTest` extended; suites green.

### Task 9.4 — Fiber-safe recursion counter (F-12)

- [ ] `GuardedExecutionReporter`: replace `private int $depth` with `WeakMap<object,int>` keyed by `Fiber::getCurrent()`; fall back to a plain int field when not suspended in a fiber (main scope). Increment/decrement helpers encapsulate both modes.
- Tests (Functional): fiber A publishes; its delegate synchronously suspends into fiber B which publishes through the **same** reporter ⇒ B reaches the delegate (depth independent), A's outermost frame unaffected; previously this false-routed to health.
- Acceptance: new interleaving test green; `GuardedExecutionReporterTest` green unchanged.

---

## M10 — Modern PHP evolution & docs polish

**Goal:** harvest the safe modernization wins the review flagged as conspicuously absent, with the AGENTS.md measurement discipline for anything touching hot paths.

**Findings:** REVIEW §7, §10, §11 polish items.

### Task 10.1 — `readonly` for stateless adapters

- [ ] Convert to `final readonly`: `SystemClock`, `RandomIdentifierGenerator`, `SafeSerializer`, `ArgvInputParser`, `ArrayInputAdapter`, `RedactionPolicy` (all hold only constructor-injected values — verify no property writes exist first).
- Acceptance: `composer analyse` green; scanner rerun (`CONCRETE CLASSES WITHOUT implements` / readonly counts) recorded in PR description.

### Task 10.2 — Native lazy-object spike (evidence-gated)

- [ ] Prototype a `LazyGhostHandlerResolver` decorator behind `HandlerResolverInterface` using `newLazyGhost`/`newLazyProxy`; benchmark against `benchHandlerResolution` with phpbench (same revs/iterations, ≥5 runs).
- [ ] Adopt **only if** measurably better or equal-and-simpler; otherwise record the spike result in this file and park. No performance claims without the numbers (AGENTS.md).
- Acceptance: either merged with benchmark table, or parked with data.

### Task 10.3 — Dispatch-path micro-optimizations (evidence-gated)

- [ ] Hoist repeated `->toString()` computations in `CommandExecutor`/`Coordinator` into locals (name/id computed once per dispatch).
- [ ] Evaluate caching the assembled `MiddlewareChain` when the middleware list is unchanged (identity-keyed memo in the executor); accept only with a `benchDispatch` before/after table.
- Acceptance: benchmarks attached; no behavioral tests changed.

### Task 10.4 — Docs, changelog, and parked-feature records

- [ ] Create `CHANGELOG.md` (Keep-a-Changelog skeleton); backfill an `[Unreleased]` section summarizing M1–M10 behavior changes (argv grammar, registry throw, transition matrix, serializer default, bridge stderr, storage bounds, new interfaces).
- [ ] USER_GUIDE refresh pass covering every changed default/policy (cross-reference the ADRs).
- [ ] Park with one-paragraph records in this file: property-hook adoption (blocked by readonly-until-model-legs), CancellationPort (reserved `Cancelled` stays unreachable), sampling/routing stage, PSR adapters, RunIt package.
- Acceptance: docs build clean (links resolve); `composer quality` green as the closing gate.

---

## Completion checklist (whole plan)

- [ ] All 19 REVIEW findings show a landed PR or an explicit deferral note above.
- [ ] `composer quality` green at every merge; final run includes mutation floors and a fresh phpbench capture.
- [ ] `vendor/bin/deptrac debug:unassigned` ⇒ none (gate integrity held).
- [ ] Examples 01–06 still exit 0 (`for f in examples/0*.php; do php "$f" || exit 1; done`).
- [ ] `docs/adr/` contains 0004–0008; REVIEW.md gains a "Follow-up 2026-MM-DD" section pointing at this plan's completed status table.

> **M4 tooling follow-up (investigate before trusting mutation metrics):** the
> Infection↔Codeception integration in this repo misclassifies the vast majority
> of mutants as "time outs" even when runs complete in seconds (verified: a
> full serial run reported 451/544 timed out, but wall time makes that
> mathematically impossible, and a single-mutant run killed cleanly in ~50s).
> Timed-out mutants count as *killed* in Infection's MSI math, so reported MSI
> (~76–100% across runs) is inflated and not a trustworthy regression signal.
> The `--min-msi=55/--min-covered-msi=65` floors are therefore a conservative
> lower bound, not a measured quality bar. Candidate root causes: the Codeception
> adapter's per-mutant process handling and/or `coverage`-driven reruns under
> pcov. Recommended next step (M4.5 / fold into M10): switch Infection's
> `testFramework` to `phpunit` (the suites are plain PHPUnit classes under
> `tests/Unit` and runnable directly) or fix the adapter/coverage wiring so mutant
> outcomes are real. Until then, rely on the hand-triaged escape list (10 killed,
> 2 equivalent-accepted) and keep the floors as a guardrail against silent
> regressions in the failure/publication paths.
