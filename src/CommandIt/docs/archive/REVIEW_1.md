# CommandIt — Final Review

**Subject:** `it-pack/command-it` (PHP `>= 8.4`, no runtime dependencies)
**Scope:** features, architecture, maintainability, security, performance, developer experience (DX).
**Method:** full source read of `src/` (domain, application, SDK, adapters, contracts, behavior), `composer.json`, `deptrac.yaml`, `phpstan.neon`, `README.md`, `docs/ROADMAP.md`, `docs/architecture/MODEL.md`; quality gates executed (`composer quality` → validate, cs, analyse, architecture, test, audit) and pass. Behavioral claims are traced to concrete code, not assumptions.

Status of gates at review time: PHPStan **level max**, 0 errors; DepTrac **0 violations**; Unit 127 / Functional 9 / Integration 2 green; `composer audit` clean. Mutation testing (`composer mutation`) could **not** be executed in this environment (the Codeception infection adapter runs the empty default `Acceptance` suite and aborts on missing coverage), so an MSI floor is not yet established — see `docs/ROADMAP.md` M8 notes.

---

## 1. Scorecard

| Dimension | Verdict | One-line rationale |
|---|---|---|
| Features | **Strong (v0.x)** | Bus, registry, middleware, lazy handlers, fiber-isolated context, reporting decorators, storage, argv/array/CLI input, validation/coercion, safe serialization. |
| Architecture | **Strong** | Enforced hexagonal ports-and-adapters; DepTrac green; immutable/readonly domain; vocabulary-aligned. |
| Maintainability | **Good** | Strong typing, `final`/`readonly`, ADRs, tests. A few dead/unused pieces and doc mismatches. |
| Security | **Good design, weak default** | Excellent primitives (redaction, bounds, classification, safe serializer, original-exception preservation) but the **safe composition is opt-in**, not default. |
| Performance | **Strong** | Lazy resolution, monotonic clock, bounded buffers, no reflection/hot-path serialization. |
| DX | **Good, with friction** | Clear vocabulary and docs, but high port-construction boilerplate and aspirational `suggest` entries. |

Overall: a well-engineered, well-bounded library. The highest-leverage improvements are **safe-by-default wiring** (security/DX) and **removing dead/aspirational surface** (maintainability/correctness).

---

## 2. Features

**What is present and works**
- CQRS-style command bus: `CommandIt` (`src/SDK/CommandIt.php`) assembles registry → resolver → coordinator → executor; `register(definition, factory)` + `dispatch(command)`.
- Explicit lifecycle `received → executing → succeeded|failed` with immutable `CommandExecution` copies and terminal-transition guards (`src/Domain/CommandExecution.php:101-137`).
- Handler middleware chain, applied in declared order (`src/Application/MiddlewareChain.php`).
- Lazy handler resolution: the factory is wrapped in `LazyHandlerProxy` and instantiated exactly once, on first `handle()`/`handles()` (`src/Application/LazyHandlerProxy.php:38-45`, `src/Application/HandlerResolver.php`).
- Input validation + coercion shared across core and adapters via `InputCoercionBehavior` (`src/Behavior/Domain/InputCoercionBehavior.php`), used by `CommandInputParser`, `ArrayInputAdapter`, `ArgvInputParser`, and `ConsoleCommandBridge`.
- CLI projection onto Symfony Console (`Adapter/CLI`), optional `symfony/console`.
- Pluggable ports: clock, id generator, reporter, context provider, storage.
- Reporting decorators (`RedactingExecutionReporter`, `GuardedExecutionReporter`, `CollectingExecutionReporter` with `OverflowPolicy`), context storage (`AmbientContextStorage`, `FiberBoundContextStorage`), storage (`InMemory`, `Null`), `SafeSerializer`, `RandomIdentifierGenerator`, `SystemClock`, `RedactionPolicy`.
- Tests across Unit/Functional/Integration; dedicated persistent-worker isolation and fiber-context tests.

**Gaps / incomplete (documented or latent)**
- **`handlerKey` is dead data.** `CommandDefinition::$handlerKey` is set by the builder (`src/SDK/CommandDefinitionBuilder.php:36,73-92`) and exposed via `handlerKey()` (`src/Domain/CommandDefinition.php:57`), but it is **never read**: `CommandRegistry` keys by `definition->name()` and returns the factory registered alongside the definition (`src/Application/CommandRegistry.php:27-53`). The field implies an external handler locator that does not exist. It is misleading API surface. → Remove, or wire a `HandlerLocator` keyed by `handlerKey`.
- **`SerializerInterface` / `SafeSerializer` are never composed.** `SafeSerializer` exists and is correct (`src/Adapter/Common/SafeSerializer.php:44-59`), but a tree-grep shows it is instantiated **nowhere** and the executor stores the `CommandExecution` artifact object directly (`src/Application/CommandExecutor.php:69,81,88`). AGENTS.md states "result persistence is delegated to a port" and "serialization is explicit" — currently the serialization port is unexercised by the core. → Either wire it (e.g., store normalized snapshots) or document it explicitly as a consumer-only port so it is not mistaken for dead code.
- **`CANCELLED` is unreachable in the executor.** The status exists (`src/Domain/CommandStatus.php:23`) but no cancellation port/trigger is wired; the executor only transitions to `Succeeded`/`Failed`. This is acceptable per AGENTS.md ("optional, via Cancellation port") but should be called out as not-yet-wired.
- **No PSR adapters shipped.** `composer.json` `suggest` lists `psr/log`, `psr/event-dispatcher`, `psr/clock` as "integration" (`composer.json:48-53`), but `src/Adapter` contains only `CLI, Common, Context, Fiber, Input, Memory, Null, Reporting` — no PSR adapters. The wording risks implying ready-made adapters. → Ship minimal PSR-3/PSR-14/PSR-20 adapters, or soften the `suggest` text to "bring-your-own adapter".
- **No context-enrichment API on the dispatch path.** The executor seeds only `command` + `execution_id` (both `Internal`) into the context (`src/Application/CommandExecutor.php:96-102`). Handlers/middleware have no supported way to attach classified/secret context that flows to reporters, so the rich `Classification`/redaction model is underused by the default flow. → Add an enrichment hook (e.g., middleware-returned context or a `withContext` builder step).

---

## 3. Architecture

**Strengths**
- Clear layering: `Contract` (incl. `Port`), `Domain` (+ `Behavior/Domain`), `Application`, `SDK`, `Adapter`. Dependency direction is enforced by DepTrac with `RunIt` and `SymfonyConsole` deliberately absent from every allow-list (`deptrac.yaml:45-73`) — verified 0 violations.
- Domain is pure: no framework, no ports; value objects are validated once on construction (`ValidatedStringValueBehavior`).
- Ports are small and intent-revealing (`ClockInterface` splits wall-clock vs monotonic; `ExecutionReporterInterface` carries stable versioned occurrence constants `command.received/handled/failed.v1`).
- `LazyHandlerProxy` lives in `Application` (not `Adapter`) precisely because `Application → Adapter` is forbidden — a correct boundary decision (see `src/Application/LazyHandlerProxy.php:20-22`).
- Vocabulary alignment: `@see https://w3id.org/itpack/runit#…` references on core contracts; M8 corrected the inaccurate `runit:Command`/`CommandStatus` IRIs.

**Observations**
- **`FiberBoundContextStorage` depends on a concrete, not an interface.** Constructor type-hints `AmbientContextStorage` (`src/Adapter/Context/FiberBoundContextStorage.php:32-35`) and calls its internal `stack()` (`AmbientContextStorage::stack()` returns the `@internal` `ContextStack`, `src/Adapter/Context/AmbientContextStorage.php:45-48`). This couples two sibling adapters and leaks an internal type. → Accept the interface (`ExecutionContextProviderInterface`) or make `stack()` private and route the sync case through the public `enter/leave` contract.
- **Not every concrete class implements an interface**, a minor deviation from AGENTS.md ("every concrete class must implement an appropriate interface"). Most notably `MiddlewareChain` (`src/Application/MiddlewareChain.php`) and `CommandInputParser` (`src/Application/CommandInputParser.php`) are application-core and would benefit from interfaces (test seams, decorator/mockability). SDK builders and several adapters are reasonable without interfaces.

---

## 4. Maintainability

**Strengths**
- PHPStan at **level max** with `ignoreErrors: []` — no errors suppressed.
- Naming follows the CommandIt vocabulary consistently (no "manager/helper" smells).
- ADRs capture the three load-bearing decisions (`docs/adr/0001-0003`).
- Tests are behavior-oriented, not private-implementation-coupled; 138 total tests.

**Issues**
1. **Dead/unused surface** (see §2): `handlerKey`, `SafeSerializer` wiring, and the absent PSR adapters add maintenance cost and mislead readers.
2. **Inaccurate docblock.** `CommandIt::has()` is documented `@throws HandlerNotFoundException` (`src/SDK/CommandIt.php:100-106`) but the body only calls `registry->has()` and returns `bool` — it never throws. Misleads consumers into a false `try/catch`.
3. **`GuardedExecutionReporter` recursion guard is instance-stateful.** The `$depth` counter (`src/Adapter/Reporting/GuardedExecutionReporter.php:29,92-115`) is shared across all callers of one instance. It correctly breaks single-call-stack re-entrancy, but if the same reporter instance is shared across concurrent Fibers/coroutines and publication re-enters from another strand, the counter can be interleaved. Acceptable for the common single-threaded sink, but worth a note if a shared sink is used under a coroutine runtime.
4. **`coerceInputValue` silently coerces bad input to `0`/`0.0`/`''`** for non-scalars (`src/Behavior/Domain/InputCoercionBehavior.php:29-38`). This is a correctness/clarity bug as much as a security one: malformed input is not rejected, it is silently zeroed. (Detailed in §5.)

---

## 5. Security

**Strong primitives (verified)**
- **Original exception is preserved.** On handler failure the executor catches, records `command.failed.v1`, and rethrows the **original** `Throwable` (`src/Application/CommandExecutor.php:78-84`); coordinator/reporter/storage failures are best-effort and never replace it (`src/Application/CommandExecutionCoordinator.php:103-122`, `CommandExecutor::record` at `:113-119`). This is the single most important resilience invariant and it is correct and tested.
- **Bounded reporting buffer.** `CollectingExecutionReporter` enforces `limit` with `OverflowPolicy` (`DropOldest`/`DropNewest`/`Throw`) (`src/Adapter/Reporting/CollectingExecutionReporter.php:71-90`).
- **Redaction policy** caps string length (1024), array cardinality (64), nesting depth (8), and redacts by key substring, without leaking type/size of secrets (`src/Adapter/Common/RedactionPolicy.php`).
- **Safe serialization** rejects resources, closures, and objects lacking `toArray()`/`Stringable` (`src/Adapter/Common/SafeSerializer.php:44-59`) — no arbitrary object serialization.
- **Context isolation** via per-Fiber `WeakMap` (`src/Adapter/Context/FiberBoundContextStorage.php:29-48`) and token-checked `leave()` (`src/Adapter/Context/ContextStack.php:38-47`); tested for persistent-worker leak safety.

**The central security weakness: safety is opt-in, not default**
- The recommended entry point `CommandIt` passes the caller-supplied reporter **directly** to the coordinator (`src/SDK/CommandIt.php:55`). It does **not** wrap it in `RedactingExecutionReporter` or `GuardedExecutionReporter`. The README "Getting started" uses a bare `CollectingExecutionReporter` (`README.md:45,52-58`) and the Security section frames redaction/guarding as *optional* ("Use it when…", `README.md:143-147`).
- Consequence: a consumer who follows the happy path gets **no secret redaction and no publication recursion guard**. If a handler (or a downstream reporter) attaches a secret/PII value to an observation/occurrence context, it reaches the external sink unredacted. AGENTS.md lists redaction and fail-closed behavior as requirements; the default assembly does not enforce them.
- **Recommendation (P0):** provide a safe-by-default composition — e.g. a `CommandIt::create()` factory (or make the constructor wrap a supplied reporter in `Guarded`+`Redacting` unless explicitly opted out). At minimum, document prominently that redaction/guarding must be composed and that the bare path is *not* safe for sensitive data.

**Secondary security findings**
- **Silent type coercion** (`InputCoercionBehavior::coerceInputValue`): non-scalar input to an `int`/`float`/`string` arg becomes `0`/`0.0`/`''` rather than being rejected. An attacker/garbled payload thus yields a plausible-but-wrong value (e.g., `count=array` → `0`), which can mask validation failures or skew logic. → Throw `InvalidCommandException` on type mismatch (keep `bool`/`'1','true'` niceties, reject the rest).
- **No cardinality/size guard on `CommandContext` entries themselves.** Redaction bounds *values* but the executor never attaches user/sensitive data; the risk is entirely on caller-supplied reporter context. With the default (unredacted) reporter, large arrays/objects passed to `reportObservation` are forwarded verbatim. Redaction by default (above) closes this.
- `InMemoryCommandExecutionStorage` is unbounded; acceptable as ephemeral, but a long-running worker that actually stores every execution could grow. The `Null` adapter is the safe default and is used by `CommandIt` only if the consumer passes it — note the SDK requires a storage port (no default), so a consumer could pass `InMemory` and retain all executions. Document retention expectations.

---

## 6. Performance

**Strengths**
- **Lazy handler resolution** avoids eager instantiation of expensive handlers (`LazyHandlerProxy`).
- **Monotonic clock** for durations (`hrtime()`), wall-clock only for timestamps (`src/Contract/Port/ClockInterface.php`, `src/Adapter/Common/SystemClock.php`) — correct and cheap.
- **No reflection on the hot path**; middleware composition uses plain closures (`MiddlewareChain::process`).
- **No serialization on the hot path** (the serializer port is unused), and buffers are bounded — good, though it also means the serializer is currently dead (§2).
- Per-dispatch cost is modest: 2 time reads, one `random_bytes(16)`, a few immutable copies, one WeakMap/array lookup for context. A phpbench suite exists (`benchmarks/CommandDispatchBench.php`) for `dispatch` and `handlerResolution`.

**Observations**
- `CommandContext::with()` copies the whole entries array per call — fine (called once per dispatch). `MiddlewareChain` rebuilds the closure chain per dispatch — fine for typical small `n`.
- **Benchmark is not part of `composer quality`** and was not executed here; the suite exists and is runnable via `composer benchmark`. Consider adding a lightweight perf budget check or at least running it in CI to catch regressions.
- `GuardedExecutionReporter::$depth` mutation is trivial but adds a write per publication; negligible.

---

## 7. Developer Experience

**Strengths**
- Consistent, self-describing vocabulary (`Command`, `CommandExecution`, `CommandResult`, `Argument`/`Option`, `ExecutionReporter`).
- `README.md` is accurate and runnable for the core+CLI path, with a working getting-started example and a clear security section.
- Immutable builders (`CommandDefinitionBuilder` returns new instances; `build()` validates) reduce misuse.
- Stable, versioned occurrence constants make integrations forward-compatible.
- Errors are domain-specific (`InvalidCommandException`, `HandlerNotFoundException`, `CommandExecutionException`, `SerializationException`) with marker interfaces.

**Friction points**
- High construction boilerplate: `CommandIt` requires **five** port arguments (`clock, ids, reporter, context, storage`); only `registry`/`middlewares` default (`src/SDK/CommandIt.php:45-53`). The README shows five manual `new` calls (`README.md:52-58`). A convenience factory with in-box defaults (clock, ids, reporter, ambient context, null/in-memory storage) would materially lower onboarding friction.
- `suggest` entries imply PSR adapters that do not exist (§2) — can frustrate a consumer expecting `psr/log` out of the box.
- `CommandIt::has()` docblock claims a throw it never makes (§4.2) — a small but real trust hazard.
- No top-level "errors & exceptions" or "how to extend with a port" guide beyond ADRs/README; acceptable for v0.x but worth a CONTRIBUTING/extension note.

---

## 8. Prioritized Recommendations

**P0 — security/defaults**
1. Make the safe composition the default: wrap the supplied reporter in `GuardedExecutionReporter` + `RedactingExecutionReporter` inside `CommandIt` (or provide `CommandIt::create()` that does), so redaction/recursion-guard are applied unless explicitly opted out. Update README to state the bare path is *not* safe for sensitive data.
2. Reject (don't silently coerce) type-mismatched input in `InputCoercionBehavior::coerceInputValue` (`InvalidCommandException`).

**P1 — correctness/maintainability**
3. Remove or wire `handlerKey` (currently dead). If kept, route `CommandRegistry`/resolver via a `HandlerLocator` keyed by it.
4. Wire or explicitly document `SerializerInterface`/`SafeSerializer`; if consumer-only, say so and add a usage example.
5. Fix the `CommandIt::has()` docblock (remove the false `@throws`).
6. Soften or fulfill the PSR `suggest` entries (ship minimal PSR-3/14/20 adapters, or reword to "bring-your-own").

**P2 — architecture/DX/polish**
7. Depend on `ExecutionContextProviderInterface` (not concrete `AmbientContextStorage`) in `FiberBoundContextStorage`; make `AmbientContextStorage::stack()` private.
8. Add interfaces for `MiddlewareChain` and `CommandInputParser` (application-core seams).
9. Add a `CommandIt` convenience factory with in-box defaults to cut boilerplate.
10. Add a context-enrichment hook so handlers/middleware can attach classified context that flows to reporters.
11. Run `composer benchmark` in CI and, once the mutation runner is fixed (restrict to the `Unit` suite / add coverage config), set an MSI floor.

---

## 9. Conclusion

CommandIt is a disciplined, well-bounded library: the architecture is enforced by tooling, the domain is immutable and vocabulary-aligned, the resilience invariant (original exception preserved, storage/reporter failures never replace the business result) is correct, and performance posture is sound. The review surfaced **no architectural or correctness defects in the core execution path**, and quality gates are green.

The most important findings are about **defaults and dead surface**, not the design: the headline safety features (redaction, publication recursion guard) are correct but **opt-in**, leaving the documented happy path unsafe for sensitive data; and a few declared pieces (`handlerKey`, `SafeSerializer` wiring, PSR `suggest`) are unused/aspirational. Addressing the P0 items (safe-by-default reporter composition + strict input coercion) would close the only material gap between the library's stated security intent and its out-of-the-box behavior.
