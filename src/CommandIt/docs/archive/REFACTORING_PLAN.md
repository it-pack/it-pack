# CommandIt — Refactoring Plan

**Source of truth:** `docs/REVIEW.md` (final review, 2026-08-21).
**Goal:** close the gaps identified in the review — primarily *safe-by-default behavior* and *removing dead/aspirational surface* — without disturbing the verified architecture, domain invariants, or quality gates.
**Ordering:** P0 (security/correctness) first, then P1 (maintainability/correctness), then P2 (DX/architecture polish). Each item is independently shippable and must keep `composer quality` green.

Conventions applied from `AGENTS.md`: every concrete class `final` + implements an interface where it is a seam; immutable/readonly preferred; external effects only via ports; original business exception always preserved; DepTrac direction unchanged; no new runtime dependencies.

---

## How to execute

1. Pick one item below. Read the referenced files first.
2. Make the smallest change that satisfies *Acceptance*.
3. Run `composer quality` (validate → cs → analyse → architecture → test → audit). Keep it green.
4. Add/adjust tests at the narrowest level.
5. Update `docs/ROADMAP.md` task status and note the change in `docs/REVIEW.md` follow-up if behavior changed.
6. Do **not** bundle unrelated items.

---

## P0 — Security & correctness (do first)

### R1. Safe-by-default reporter composition
- **Problem (REVIEW §5, central weakness):** `CommandIt` passes the caller's reporter straight to the coordinator (`src/SDK/CommandIt.php:55`); the README happy path uses a bare `CollectingExecutionReporter` (`README.md:45,52-58`). No `RedactingExecutionReporter` / `GuardedExecutionReporter` is applied by default, so secrets and self-observation loops are unguarded on the documented path.
- **Change:** introduce a convenience assembly that wraps a supplied reporter in `GuardedExecutionReporter` + `RedactingExecutionReporter` unless explicitly opted out. Keep the explicit constructor intact for consumers who want full control.
  - Add `CommandIt::create(ClockInterface, IdentifierGeneratorInterface, ExecutionReporterInterface, ExecutionContextProviderInterface, CommandExecutionStorageInterface, array $middlewares = [])` (or a `CommandItFactory`) that internally composes `new GuardedExecutionReporter(new RedactingExecutionReporter($reporter, new RedactionPolicy()), $health)` where `$health` defaults to `NullExecutionReporter`.
  - Make the existing constructor remain public (no BC break); `create()` is additive.
- **Files:** `src/SDK/CommandIt.php` (add `create()`/`Factory`), `src/Adapter/Reporting/NullExecutionReporter.php` (ensure exists; if not, add minimal null reporter), `README.md` (lead with safe example).
- **Acceptance:** default `CommandIt::create(...)` dispatch with a secret-keyed observation context is redacted before reaching the sink; a reporter that re-reports through the same path does not infinite-loop (routed to health). Existing `new CommandIt(...)` callers untouched.
- **Risk/compat:** additive only; no public signature change.

### R2. Strict input type coercion
- **Problem (REVIEW §4.3, §5):** `InputCoercionBehavior::coerceInputValue` returns `0`/`0.0`/`''` for non-scalar input instead of rejecting it (`src/Behavior/Domain/InputCoercionBehavior.php:29-38`), silently zeroing malformed/attacker input.
- **Change:** throw `InvalidCommandException` on type mismatch; keep the friendly `bool` aliases (`1/true/yes/on`, `0/false/no/off`).
  - For `int`/`float`/`double`/`string`: require `\is_scalar`; otherwise throw.
  - Keep `bool` coercion as-is.
  - `default` branch (unknown declared type): keep passthrough but document it is intentionally unvalidated (e.g. `array`).
- **Files:** `src/Behavior/Domain/InputCoercionBehavior.php`, tests `tests/Unit/Application/CommandInputParserTest.php`, `tests/Unit/Adapter/Input/ArrayInputAdapterTest.php`, `tests/Unit/Adapter/CLI/ArgvInputParserTest.php`.
- **Acceptance:** passing a non-scalar to an `int`/`float`/`string` arg throws `InvalidCommandException`; existing valid coercions still pass; new negative tests cover each type.
- **Risk/compat:** could change behavior for a caller relying on silent `0` — but that was a latent bug; cover with tests and note in `docs/REVIEW.md` follow-up. No API change.

---

## P1 — Maintainability & correctness

### R3. Resolve the dead `handlerKey`
- **Problem (REVIEW §2, §4.1):** `CommandDefinition::$handlerKey` is set by the builder and exposed via `handlerKey()` (`src/Domain/CommandDefinition.php:57`, `src/SDK/CommandDefinitionBuilder.php:36,73-92`) but never read; `CommandRegistry` keys by name (`src/Application/CommandRegistry.php:27-53`).
- **Options (choose one):**
  - **(a) Remove:** delete `handlerKey()` from `CommandDefinitionInterface` + `CommandDefinition`, and `handler()` from `CommandDefinitionBuilder`. Simplest, least surface.
  - **(b) Wire:** add a `HandlerLocatorInterface` (port) keyed by `handlerKey`; `HandlerResolver` uses it when present, falls back to registry factory. More features, more surface.
- **Recommendation:** (a) for this pass; revisit (b) only with a concrete consumer need.
- **Files:** per option above + tests referencing `handler()`.
- **Acceptance:** no unused field; `grep -rn handlerKey src` returns nothing (option a) or a real read path exists (option b). `composer quality` green.
- **Risk/compat:** removing a public interface method is a BC break pre-1.0 — acceptable per AGENTS.md but must be documented in `docs/ROADMAP.md`/changelog.

### R4. Wire or explicitly document `SerializerInterface` / `SafeSerializer`
- **Problem (REVIEW §2, §4.1):** `SafeSerializer` exists and is correct (`src/Adapter/Common/SafeSerializer.php:44-59`) but is instantiated nowhere; the executor stores the artifact object directly (`src/Application/CommandExecutor.php:69,81,88`).
- **Change (recommended):** add a `SerializationPort` step in the storage path — when persisting, normalize the result payload via `SerializerInterface` before handing to `CommandExecutionStorageInterface`. Provide `SafeSerializer` as the default. This realizes the AGENTS.md "result persistence delegated to a port" intent and exercises the serializer.
  - Add `SerializerInterface $serializer` to `CommandExecutor` (and `CommandIt`); normalize `result->payload()` before `store()`.
- **Alternative:** if storage-object-as-is is intentional, document `SerializerInterface` as consumer-only and add a usage example; do not leave it looking dead.
- **Files:** `src/Application/CommandExecutor.php`, `src/SDK/CommandIt.php`, `src/Contract/Port/SerializerInterface.php` (unchanged), tests `tests/Unit/Adapter/Common/SafeSerializerTest.php` (extend with executor integration).
- **Acceptance:** serialized/normalized payload appears in stored artifact; objects without `toArray()`/`Stringable` still rejected (caught best-effort per `record()`).
- **Risk/compat:** additive constructor param (BC break pre-1.0; document). Keep `record()` best-effort so serialization failure never replaces the business result.

### R5. Fix `CommandIt::has()` docblock
- **Problem (REVIEW §4.2):** `src/SDK/CommandIt.php:100-106` documents `@throws HandlerNotFoundException` but returns `bool` and never throws.
- **Change:** remove the `@throws` line (and the misleading comment). No code change.
- **Files:** `src/SDK/CommandIt.php`.
- **Acceptance:** docblock matches behavior; phpstan/cs clean.
- **Risk/compat:** none.

### R6. Reconcile PSR `suggest` with reality
- **Problem (REVIEW §2, §4.2, §7):** `composer.json` `suggest` lists `psr/log`, `psr/event-dispatcher`, `psr/clock` as "integration" (`composer.json:48-53`) but no PSR adapters exist in `src/Adapter`.
- **Change (choose one):**
  - **(a) Ship minimal adapters:** `Adapter/Psr` with `Psr3ExecutionReporter` (observation/logger), `Psr14ExecutionReporter` (occurrence/dispatcher), `Psr20Clock` (PSR-20 → `ClockInterface`). Small, dependency-free in core (PSR interfaces are `suggest`).
  - **(b) Reword** `suggest` to "bring-your-own adapter; see `SerializerInterface`/`ExecutionReporterInterface` ports".
- **Recommendation:** (b) now, (a) as a later milestone when a consumer needs it.
- **Files:** `composer.json` (reword) or new `src/Adapter/Psr/*` + tests.
- **Acceptance:** `suggest` text matches shipped code; no false implication.
- **Risk/compat:** none (docs) or additive adapters (no core dep).

---

## P2 — Architecture, DX & polish

### R7. Decouple `FiberBoundContextStorage` from concrete `AmbientContextStorage`
- **Problem (REVIEW §3):** constructor type-hints concrete `AmbientContextStorage` (`src/Adapter/Context/FiberBoundContextStorage.php:32-35`) and calls its `@internal` `stack()` (`src/Adapter/Context/AmbientContextStorage.php:45-48`).
- **Change:** accept `ExecutionContextProviderInterface` for the sync fallback; route the sync case through the public `enter`/`leave`/`current` contract (or keep an internal stack object but make it private and not exposed as `@internal` to siblings).
- **Files:** `src/Adapter/Context/FiberBoundContextStorage.php`, `src/Adapter/Context/AmbientContextStorage.php`.
- **Acceptance:** `FiberBoundContextStorage` depends only on interfaces/ports; persistent-worker isolation tests still pass.
- **Risk/compat:** internal adapter change; keep `AmbientContextStorage::stack()` accessible to the one sibling only if required, otherwise remove.

### R8. Add interfaces for `MiddlewareChain` and `CommandInputParser`
- **Problem (REVIEW §3):** application-core classes lack interfaces (`src/Application/MiddlewareChain.php`, `src/Application/CommandInputParser.php`), a minor deviation from AGENTS.md ("every concrete class implements an appropriate interface").
- **Change:** extract `MiddlewareChainInterface` (`process(CommandInterface, CommandHandlerInterface): CommandResultInterface`) and `CommandInputParserInterface` (`parse(CommandDefinitionInterface, array, array): CommandInputInterface`). Keep `InputCoercionBehavior` shared.
- **Files:** new interfaces under `src/Contract/Application/`, update the two classes, update call sites (`CommandExecutor`, `CommandIt`).
- **Acceptance:** interfaces exist; DepTrac still green; tests unchanged in behavior.
- **Risk/compat:** additive; refactor internal wiring only.

### R9. Convenience factory to cut construction boilerplate
- **Problem (REVIEW §7):** `CommandIt` requires five port arguments; README shows five manual `new`s (`README.md:52-58`).
- **Change:** provide `CommandIt::create()` (or `CommandItFactory::minimal()`) that supplies in-box defaults — `SystemClock`, `RandomIdentifierGenerator`, `Guarded`+`Redacting` reporter over a `CollectingExecutionReporter` (reuses R1), `AmbientContextStorage` (or `FiberBoundContextStorage` with `FiberRuntimeDetector`), `NullCommandExecutionStorage` — while still allowing overrides.
- **Files:** `src/SDK/CommandIt.php` (factory), `README.md` (show `create()` first).
- **Acceptance:** getting-started collapses to one factory call; existing explicit constructor untouched.
- **Risk/compat:** additive.

### R10. Context-enrichment hook
- **Problem (REVIEW §2, §5):** executor seeds only `command` + `execution_id` (`src/Application/CommandExecutor.php:96-102`); handlers/middleware cannot attach classified/secret context that flows to reporters, underusing the `Classification`/redaction model.
- **Change:** let middleware (or handler) return/attach context to the active `ExecutionContextProviderInterface` during `process()`. Simplest: `CommandMiddlewareInterface::process()` may receive the current context provider, or the handler result carries a context patch merged before reporting. Keep copy-on-write and token restore in `finally`.
- **Files:** `src/Contract/Command/CommandMiddlewareInterface.php`, `src/Application/MiddlewareChain.php`, `src/Application/CommandExecutor.php`, plus a reporter/context test.
- **Acceptance:** a middleware-attached `Secret` entry is present in reporter context but redacted by the default `RedactingExecutionReporter`.
- **Risk/compat:** interface change to middleware signature (pre-1.0, document) or add an optional context-patch return type.

### R11. Benchmark in CI + set MSI floor
- **Problem (REVIEW §6, M8 notes):** `composer benchmark` exists (`benchmarks/CommandDispatchBench.php`, `phpbench.json`) but is not in `composer quality`; mutation (`infection`) could not run (Codeception adapter runs empty `Acceptance` suite → exit 143).
- **Change:**
  - Restrict the mutation runner to the `Unit` suite (e.g. `infection` config targeting `tests/Unit` with coverage) so `composer mutation` runs; then set a realistic `--min-msi` floor.
  - Add `composer benchmark` to a CI job (not the `quality` gate, to keep it fast) and capture a baseline.
- **Files:** `infection.json5`, `codeception.yml` (suite scoping) or a `phpunit.xml.dist` for `tests/Unit`, CI config.
- **Acceptance:** `composer mutation` executes and reports MSI; benchmark runs in CI with a recorded baseline.
- **Risk/compat:** tooling only.

### R12. Document the `CANCELLED` status as not-yet-wired
- **Problem (REVIEW §2):** `CommandStatus::Cancelled` exists (`src/Domain/CommandStatus.php:23`) but the executor never transitions to it.
- **Change:** add a one-line note in `docs/architecture/MODEL.md` and `README.md` that cancellation is a reserved status, pending a `CancellationPort`. No code change required now.
- **Acceptance:** docs match code.
- **Risk/compat:** none.

---

## Suggested sequencing (milestones)

| Milestone | Items | Gate |
|---|---|---|
| M9 — Safety & correctness | R1, R2 | `composer quality` green; new redaction + coercion-negative tests |
| M10 — Clean surface | R3, R4, R5, R6 | `composer quality` green; dead surface removed/documented |
| M11 — Architecture & DX | R7, R8, R9, R10, R12 | DepTrac green; DX examples updated |
| M12 — Confidence tooling | R11 | `composer mutation` runs + MSI floor; benchmark in CI |

## Quality-gate expectations (unchanged)
Every item must keep `composer quality` (validate → cs → analyse → architecture → test → audit) green. DepTrac direction (Domain/Application/SDK/Adapter → Contract/Port, never → RunIt/SymfonyConsole) must remain 0 violations. PHPStan stays at `level: max` with `ignoreErrors: []`.

## Open questions to resolve before R1/R4/R10
- Is `CommandIt::create()` the preferred safe-default entry, or should the existing constructor's default reporter be wrapped? (Recommendation: additive `create()`; keep explicit constructor.)
- For R4, is normalizing the result payload before storage acceptable, or should storage receive the raw artifact and serialization be a consumer concern only? (Recommendation: normalize payload at the port.)
- For R10, middlewares attaching context vs. handler-returned context patch? (Recommendation: middleware-enriched context via the provider, copy-on-write.)
