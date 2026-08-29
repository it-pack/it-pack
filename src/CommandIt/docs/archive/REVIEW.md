# CommandIt — In-Depth Code Review

- **Date:** 2026-08-24
- **Scope:** Full working tree of `it-pack/command-it` (`src/`, `tests/`, `examples/`, `benchmarks/`, tooling configs). Includes recent uncommitted work (runnable examples under `examples/`).
- **Method:** Four parallel layer audits (Domain/Behavior; Application/SDK/Bootstrap/Contract; Ports+Adapters; Tests/Docs/DX) cross-checked against a tokenizer-based conformance scan of all of `src/`, live runs of every quality gate, and first-hand re-verification of every load-bearing finding cited below.
- **Environment:** PHP 8.5.8 runtime against a `^8.4` target; symfony/console present as a dev-transitive package (suggest-only by design).

## 0. Assumptions and Limitations

1. This reviews the **current working tree**, not a tagged release; findings may already be fixed on other branches.
2. The mutation suite and benchmarks were **not executed** in this pass (time-boxed); prior `var/infection.log` contents were inspected instead. Gate results for everything else are from actual runs recorded in §2.
3. The project is pre-1.0 and mid-plan: the AGENTS.md execution model names Activity, Measurement, Observation, Occurrence artifacts and a Cancellation port that **do not exist yet**. Where current code diverges from that target model, this review says so explicitly rather than treating it as a defect of finished scope.
4. `examples/` is intentionally outside phpstan/deptrac/php-cs-fixer paths (verified); it is assessed qualitatively only.

## 1. Executive Summary

CommandIt is an unusually disciplined hexagonal library for its stage: dependency direction is provably clean, 50/50 concrete classes are `final`, the Domain is uniformly immutable, complexity is very low (two methods at ~40 lines, five `else` sites repo-wide), and the failure philosophy (original business `Throwable` preserved; publication fail-open) is implemented correctly and mostly tested. DX is a highlight: precise exception hierarchy, accurate docs snippets, guarded examples.

The material risks are concentrated, not diffuse:

1. **The DepTrac Symfony guard is decorative.** A wrong `classNameRegex` leaves vendor tokens unassigned, so "Symfony-free core" is enforced only by convention while the config comment claims otherwise (§3, F-01).
2. **Two correctness defects** in the publication path: a truncation summary that always reports `"0 entries omitted"`, and an unguarded `coordinator->fail()` inside the executor's catch that could replace the original business exception (F-02, F-03).
3. **Quiet exfiltration paths at the CLI edge**: result payloads are printed verbatim (objects included, bypassing the serializer port), and the docblock promises stderr but errors go to stdout (F-04).
4. **ISP/LSP debt in the lifecycle contracts**: reporters must implement activity/observation methods the core never calls; the coordinator demands a concrete artifact behind an interface promise (F-06, F-07).
5. **Test gaps exactly where guards are silent**: no injection test proves the original throwable survives a *bare* throwing reporter during dispatch; mutation thresholds are disabled so such escapes are invisible (F-10).

### Scorecard

| Dimension | Rating | One-line verdict |
|---|---|---|
| Features & API surface | Good | Core vertical/horizontal story works; model legs (activity/observation/cancellation) declared but unrealized |
| Hexagonal architecture | Strong | Direction provably clean; gate integrity gap (F-01) undermines the guarantee |
| SOLID | Good with debt | DIP exemplary; ISP reporter fat; LSP seam at coordinator |
| DRY | Good with outliers | Trait-based reuse genuinely works; `Argument`/`Option` wholesale clones |
| Interface backing | Very good | 41/50 classes backed; 9 deliberate utility/facade exceptions |
| Modern PHP leverage | Mixed | Fibers/WeakMap exemplary; lazy objects placeholder; hooks absent |
| Performance | Adequate | Bounded hot paths, monotonic durations; ~290µs noop dispatch invites profiling |
| Security | Good posture, sharp edges | Redaction-before-publication verified; payload/object/argv edges leak |
| DX | Strong | Hierarchy, messages, docs accuracy, examples all land |
| Maintenance | Strong | Zero TODO debt, empty baselines; gate hygiene nits |

## 2. Verification Evidence

### 2.1 Quality gates (executed during this review)

| Gate | Command | Result |
|---|---|---|
| Composer validate | `composer validate --strict` | OK |
| Coding style | `composer cs:check` | 0 of 143 files need fixing |
| Static analysis | `composer analyse` (PHPStan level `max`) | 0 errors across 139 files |
| Architecture | `composer architecture` (DepTrac) | 0 violations, **16 uncovered dependencies** (see F-01) |
| Tests | `composer test` | 149 tests / ~360 assertions pass (Unit 138, Functional 9, Integration 2) |
| Mutation | not run (thresholds `--min-msi=0`; see F-11) | — |
| Benchmarks | not run (subjects exist; see §8) | — |

### 2.2 Mechanical conformance scan (tokenizer pass over `src/`)

- 98 files, 4,536 LOC; 50 classes, 38 interfaces, 5 enums, 5 traits.
- **final:** 50/50 concrete classes.
- **readonly:** 13 classes — all of Domain (incl. 5 value objects). Application/SDK/Adapter/Bootstrap use plain promoted properties (justified where state exists; see §7).
- **Concrete classes without `implements`:** exactly 9 (enumerated in §6).
- **Methods ≥ 40 lines:** 2 — `CommandExecutor::dispatch()` (Application/CommandExecutor.php:56) and `ConsoleCommandBridge::execute()` (Adapter/CLI/ConsoleCommandBridge.php:74).
- **`else`/`elseif`:** exactly 5 sites repo-wide (listed in §12).
- **Feature hits:** `WeakMap` ×5 (one file); `newLazyGhost` ×1 (comment only); property hooks 0; direct `Fiber` usage confined to context adapters + runtime detector.

## 3. Architecture and Hexagonal Fidelity

### What is verifiably right

- **Dependency direction is clean in fact, not just intent.** No file in Contract/Domain/Behavior/Application imports from Adapter or SDK; Bootstrap is the sole composition root allowed to touch adapters (`deptrac.yaml:70-77`). Spot-verified import blocks (e.g., `src/Application/CommandExecutor.php:7-23`, `src/Adapter/Input/ArrayInputAdapter.php`).
- **Ports are complete.** All nine ports have in-box adapters; none of them import anything above Port/Contract:

| Port | In-box adapter(s) | Bootstrap default |
|---|---|---|
| ExecutionReporterInterface | Null, Collecting, Redacting (decorator), Guarded (decorator), SafeReporterFactory | Guarded(Redacting(Collecting)) via `Bootstrap/CommandItFactory.php:55` |
| CommandExecutionStorageInterface | InMemory, Null | Null |
| ExecutionContextProviderInterface | AmbientContextStorage, FiberBoundContextStorage, ContextStack (@internal) | FiberBound(Ambient) |
| RuntimeContextDetectorInterface | FiberRuntimeDetector | wired |
| ClockInterface | SystemClock (wall + `hrtime`) | wired |
| IdentifierGeneratorInterface | RandomIdentifierGenerator | wired |
| SerializerInterface | SafeSerializer | **not wired by default** (`null`) |
| RedactionPolicyInterface | RedactionPolicy | SafeReporterFactory ctor default |
| ContextTokenInterface | ContextToken value record | internal |

- **Decorator composition order is correct**: `SafeReporterFactory::wrap()` builds `Guarded(Redacting(delegate))` — redaction happens inside the guard, before the sink, and failures route to a distinct health reporter (`src/Adapter/Reporting/SafeReporterFactory.php:37-43`, `GuardedExecutionReporter.php:90-116`).
- **The shared coercion trait is the right kind of reuse**: `InputCoercionBehavior` is consumed by four implementations across Application and Adapters without creating an Adapter→Application edge (`src/Behavior/Domain/InputCoercionBehavior.php`, used at `CommandInputParser.php:21`, `ArrayInputAdapter.php:26`, `ArgvInputParser.php:27`, `ConsoleCommandBridge.php:35`).

### Findings

**F-01 [high] The DepTrac Symfony guard cannot fire.**
`deptrac.yaml:39-48` assigns Symfony vendor code via `classNameRegex: '/^Symfony\\Console/'`. Real FQCNs are `Symfony\Component\Console\...`, which never match; vendor tokens end up unassigned, and dependencies on unassigned tokens are counted as *uncovered* (16 today), not violations. Meanwhile `Adapter/CLI/ConsoleCommandBridge.php:13-18` imports and extends Symfony Console, and `deptrac.yaml:83-85` claims any such dependency "is a violation". Consequence: importing Symfony Console into Domain or Application would keep the gate green.

*Fix:* correct the regex to `/^Symfony\\Component\\Console\\/` (and add `/^ItPack\\RunIt/` stays fine), then reconcile naming: either move `Adapter/CLI/*` Symfony-coupled files to `src/Adapter/SymfonyConsole/` (the directory the config was written for) or update collectors/rulesets to name `CLI`. Also remove the dead `Common` layer (`deptrac.yaml:5-8`; `src/Common/` does not exist). After alignment, expect the current bridge imports to surface as real decisions: allow SymfonyConsole→vendor only, or relocate.

**F-05 [med] Registry silently overwrites duplicate command names.** `CommandRegistry::register()` is last-wins array assignment (`src/Application/CommandRegistry.php:29-33`). For a library whose registry feeds CLI projection, a collision should be loud or explicitly documented policy.

**F-08 [low] Duck-typed adapter utilities.** `ArgvInputParser`, `ArrayInputAdapter`, `SafeReporterFactory` mirror contract shapes without implementing them (`ArgvInputParser.php:25`, `ArrayInputAdapter.php:24`, `SafeReporterFactory.php:29`). Acceptable for composition utilities, but a small parser interface would let consumers swap argv/array parsing behind one type.

## 4. SOLID

- **DIP — exemplary.** Every external effect is constructor-injected interface; no container, service locator, static state anywhere in `src/`.
- **SRP — good.** `CommandExecutionCoordinator` is single-purpose; `CommandExecutor` (142 lines) mixes orchestration, persistence timing, normalization guarding, and duration math — cohesive but showing strain; splitting persistence-recording into a collaborator would sharpen it.
- **OCP — strong extension surface.** Consumers can swap clock, ids, reporter (+decorators), storage, serializer, context provider, registry, resolver, coordinator chain, middlewares — all through interfaces, none requiring core edits.

**F-06 [med] ISP: `ExecutionReporterInterface` carries dead weight.** `beginActivity`/`endActivity`/`reportObservation` have zero callers in Application/SDK (`Contract/Port/ExecutionReporterInterface.php:45-67`). Every custom reporter must implement methods the core never invokes. Split occurrence/measurement reporting from activity/observation before 1.0 freezes the surface.

**F-07 [med] LSP seam at the coordinator.** The public contract accepts `ExecutionArtifactInterface`, but the implementation requires the concrete `CommandExecution` and throws `LogicException` otherwise (`src/Application/CommandExecutionCoordinator.php:100-107`). Either narrow the contract types or make artifacts polymorphic; today the interface over-promises.

**F-03 [high→med, edge-triggered] Unguarded `fail()` inside the catch block can replace the original Throwable.** `CommandExecutor.php:80-86`: if `$this->coordinator->fail(...)` throws (e.g., a custom artifact rejected by `toExecution()`, or any coordinator override), the new exception propagates instead of `throw $thrown` — violating the class docblock's own promise (`CommandExecutor.php:32-33`). Unreachable with default wiring; cheap to harden by wrapping the fail-and-store sequence defensively.

## 5. DRY

Genuine wins first: string validation lives once in `ValidatedStringValueBehavior` with an `assertAdditionalRules()` hook; coercion lives once in `InputCoercionBehavior` shared across layers.

- **F-09 [med] `Argument` ≡ `Option` line-for-line clones** (`src/Domain/Argument.php:18-55` vs `Option.php:18-55`; twin interfaces too). Two concrete consumers exist, so a shared stateless input-spec trait is past the "speculative abstraction" threshold. ~38 duplicated lines plus drift risk.
- **[low] `ArgumentName` ≡ `OptionName` validation clone** — identical identifier regex and near-identical message (`ArgumentName.php:25-29`, `OptionName.php:25-29`). Belongs in a shared behavior hook.
- **[low] `label()` one-liner duplicated** across three domain enums plus `Classification`; trivially traitable if desired.
- **[low] `names()` helper duplicated** verbatim in `ArgvInputParser.php:92-95` and `ArrayInputAdapter.php:62-65`; intra-class repetition of the key-miss throw pattern ×4 in `CommandInput.php:42-67`.

## 6. Interface Backing and Type Discipline

Every enum implements its label contract except two: `Classification` (`Contract/Domain/Classification.php`) sits among pure interfaces without one, and `OverflowPolicy` (`Adapter/Reporting/OverflowPolicy.php`) is bare — inconsistent with the project's own enum discipline.

Concrete classes without interfaces (9 of 50):

| Class | Assessment |
|---|---|
| `SDK\CommandIt` | Facade/composition handle — acceptable without contract, though `CommandItInterface` would ease decoration |
| `SDK\CommandBuilder`, `SDK\CommandDefinitionBuilder` | Builders; a common builder contract is plausible but low-value |
| `Bootstrap\CommandItFactory` | Composition root — acceptable |
| `Adapter\Reporting\SafeReporterFactory` | Factory of decorators — acceptable |
| `Adapter\CLI\ConsoleCommandBridge` | Constrained: must extend `SymfonyCommand` (external base) — acceptable, document |
| `Adapter\CLI\ConsoleCommandRegistry`, `Adapter\CLI\ArgvInputParser`, `Adapter\Input\ArrayInputAdapter` | Utilities mirroring existing contract shapes (see F-08) |

Verdict: **interface backing is effectively complete** where it matters (all swappable collaborators). Empty marker interfaces (`ExecutionArtifactInterface`, exception markers) are documented and intentional. Minor typing debts: `LazyHandlerProxy::$factory` is natively untyped (`Application/LazyHandlerProxy.php:34`); broad `array<string,mixed>` carriers on inputs/context (partially inherent to a generic bus).

## 7. Modern PHP Leverage

| Feature | Status | Evidence / assessment |
|---|---|---|
| Fibers | **Exemplary** | Isolated behind `RuntimeContextDetectorInterface`; core never touches `Fiber`; `FiberBoundContextStorage` keys per-fiber stacks in a `WeakMap` with ambient fallback (`Adapter/Context/FiberBoundContextStorage.php:29-53`) |
| Weak maps | Used where correct | GC-safe per-Fiber stacks without teardown; functional tests cover fiber isolation and persistent-worker residue |
| Lazy objects | **Placeholder only** | `newLazyGhost`/`newLazyProxy` unused; hand-rolled memoized proxy documents the future swap (`Application/LazyHandlerProxy.php:16-19,38-45`). Works and is race-safe-by-construction (fresh proxy per dispatch), but native lazy objects would drop the bespoke machinery when adopted |
| Property hooks | Absent everywhere | Documented reason is real: readonly classes forbid hooked properties (`CommandDefinition.php:16-18`, `CommandInput.php:13-17`). AGENTS.md ambitions outpace implementation; revisit post-M2 where computed projections exist on non-readonly types |
| readonly classes | Domain-complete | All 13 Domain types; **opportunity:** stateless adapters (`SystemClock`, `RandomIdentifierGenerator`, `SafeSerializer`, both parsers, `RedactionPolicy`) hold only constructor-injected values and could be `readonly` for uniformity |
| final | Complete | 50/50 |
| Enums + match | Good | Closed sets with label contracts; coercion matrix uses `match`; `'double'` arm is a legacy `get_debug_type()` alias that can never match (`InputCoercionBehavior.php:62-68`) |
| `never` | Unused | `coerceScalar`'s unreachable default throws `LogicException` where a `never`-typed helper expresses intent better |
| First-class callables | Unused | Arrow fns suffice at current call sites |

**F-12 [med] The recursion guard counter is not fiber-safe.** `GuardedExecutionReporter::$depth` is instance-level mutable int state (`Adapter/Reporting/GuardedExecutionReporter.php:29,92-115`). Interleaved publications from concurrent fibers on one shared reporter corrupt depth counting (false "recursion-guarded" routing). Given fibers are a first-class runtime story here, make the counter fiber-bound (WeakMap keyed by fiber, mirroring the context storage pattern) or document single-threaded-publication assumptions.

## 8. Performance

- **Durations are monotonic and wall-clock is separate** — `SystemClock::monotonicNanoseconds()` feeds all duration math (`CommandExecutor.php:68,81,88`; `SystemClock.php`); wall clock only stamps `startedAt/endedAt`. Meets the stated requirement.
- **No reflection, no I/O, no eager serialization on hot paths.** Handler construction deferred until first `handle()`; unknown commands never construct handlers (`HandlerResolver.php:30-33`).
- **Per-dispatch allocations** (all small, all avoidable later): fresh `MiddlewareChain` + `array_reverse` copy + N+1 closures although the middleware list is fixed at construction (`MiddlewareChain.php:43-45`, `CommandExecutor.php:74`); fresh `LazyHandlerProxy`; repeated `->toString()` recomputation of the same names/ids (e.g., `CommandExecutor.php:58,75`; `Coordinator.php:49-50,68-69,90-91`); up to three occurrence-context merges.
- **Double redaction passes.** The coordinator already merges the classification-redacted view (`'***'`) into occurrences (`Coordinator.php:118-127`), then `RedactingExecutionReporter` substring-scans again — idempotent but redundant CPU on every publication, with divergent markers (see F-13).
- **Buffer overflow `DropOldest` is O(n) per overflow** (`array_shift`, `CollectingExecutionReporter.php:86-89`) — quadratic worst case under sustained overflow; acceptable at limit=1000, worth noting.
- **Benchmarks exist** (`benchmarks/CommandDispatchBench.php`, subjects `benchDispatch`/`benchHandlerResolution`); archived claim ≈290µs noop dispatch. That figure is high relative to the amount of core logic and likely dominated by per-dispatch adapter work — worth a profile pass before optimization, since AGENTS.md forbids claiming improvements without measurement.

## 9. Security

Verified-strong foundations:

- **Redaction precedes publication** on both paths: classification-driven merge into occurrence context (`Coordinator.php:126`) and policy-driven wrapping inside the guard (`SafeReporterFactory.php:37-43`); e2e test asserts plaintext secrets never reach sinks.
- **Health path transmits scalars only** — `{kind,label,error}`; no user context, no stack traces (`GuardedExecutionReporter.php:96-99,106-110`).
- **Bounds exist where promised**: reporter buffer default 1000 with four overflow policies incl. Throw (`CollectingExecutionReporter.php:27-28`); RedactionPolicy caps strings (1024+suffix), arrays (64+synthetic), depth 8.
- **Serializer rejects arbitrary objects/resources/closures** (`SafeSerializer.php:28-60`); no env capture anywhere; stack traces never exported.

Findings:

**F-02 [high] Truncation summary is always wrong.** `RedactionPolicy.php:79-82`: after `array_slice(..., maxArrayEntries)`, `count($value) - maxArrayEntries` is always ≤ 0, so every truncation records `"_truncated" => "0 entries omitted"`. Forensic data is actively misleading. Fix: capture `count($value)` before slicing. Also: the synthetic `_truncated` key can collide with a legitimate key.

**F-04 [med] CLI bridge leaks by design gap.** `ConsoleCommandBridge::execute()` writes handler payloads verbatim (`:109,116-133`): objects are `json_encode`d directly — bypassing the `SerializerInterface` port that exists precisely to normalize unsafe payloads — and encoding failure silently yields `'null'` (no `JSON_THROW_ON_ERROR`). Docblock says failures go to stderr (`:28-29`) but `:104` writes `<error>` to stdout; throwable messages may carry interpolated secrets. Fix: route output through the serializer port, encode with `JSON_THROW_ON_ERROR` + fallback, use `getErrorOutput()`.

**F-14 [med] Objects bypass redaction entirely.** `RedactionPolicy::redactValue()` has arrays/strings/scalars branches only (`RedactionPolicy.php:72-93`); an object nested in published context passes through untouched. Combined with F-04's raw-object output this is the most realistic secret-exfiltration path.

**F-15 [med] Substring matching over-redacts silently.** Defaults include `'key'`, `'auth'`, `'private'` (`RedactionPolicy.php:31-34`) matched case-insensitively via `str_contains` (`:98-106`): `monkey`, `author`, `privateNote` are redacted. Safe direction, but undocumented data loss; document or move to exact/camel-aware matching options.

**F-16 [med] `entries()` exports Secret values unguarded.** `CommandContextInterface` promises "Secret values are never exported", yet `entries()` hands out raw classified entries (`Domain/CommandContext.php:59-62`); only `redacted()` filters. Add a docstring warning or a redacted-entries variant.

**F-17 [med] Unbounded retention surfaces.** `InMemoryCommandExecutionStorage` is unbounded and last-wins overwrite with no clear/delete API (`Adapter/Memory/InMemoryCommandExecutionStorage.php:25`); `AmbientContextStorage` has no reset/request-scoping enforcement despite "request-local" docblock (`Adapter/Context/AmbientContextStorage.php:14`); `SafeSerializer` has no size bounds and **no cycle protection** (self-referential arrays recurse to stack exhaustion). Long-lived workers are the stated target runtime — bound these.

**F-18 [med] Argv parser accepts garbage silently.** `ArgvInputParser::split()` (:47-85): unknown options are collected then discarded because resolution iterates declared specs (`InputCoercionBehavior.php:86-102`) — typos like `--forc` vanish; negative values misparse (`--limit -5` ⇒ `limit=true` + stray positional, `:67`); single-dash flags unsupported (`:59` tests `--` only); `--` end-of-options marker becomes option `'' => true`. None are dangerous alone, together they undermine CLI trustworthiness. Reject unknown provided keys and document token grammar.

## 10. Developer Experience

- **Exception design is a genuine strength**: one root marker (`CommandExceptionInterface extends \Throwable`) enables `catch (CommandExceptionInterface)`, with specific finals mapping onto SPL bases (`InvalidCommandException`/`InvalidArgumentException`, `HandlerNotFoundException`/`RuntimeException`, ...). Asymmetry [low]: only 2 of 6 exceptions expose dedicated marker interfaces, forcing concrete-class catches elsewhere.
- **Error messages are specific and quoted** (samples: `'No handler is registered for command "%s".'`, `'Missing argument "%s".'`, `'Execution reporter buffer is full (%d entries).'`). Nit [low]: missing input reported as `malformed("Missing …")` conflates absence with malformation (`CommandInput.php:55,64`).
- **Docs accuracy is near-perfect at the seams that matter**: three spot-checks (reporter constants/signatures, factory parameter table, README quick start) match source exactly. Two rot issues: archive links cite `docs/archive/ROADMAP.md` etc. while files carry `_1` suffixes (README.md:211 vs USER_GUIDE.md:5,774) **F-19 [med]**; USER_GUIDE claims the `***redacted***` marker for domain-level redaction while the domain emits `'***'` and the policy marker applies only when the safe wrapper is used **F-13 [med]** — unify the sentinel (ideally as a constant beside `RedactionPolicyInterface`).
- **Examples are exemplary**: six narrated scripts covering both feature axes, zero external deps for 01–05, graceful `class_exists(Application::class)` guard in 06, all verified runnable.
- **Install story clean**: zero required deps beyond PHP ^8.4, suggest table maps each optional package to its adapter seam.

## 11. Maintenance and Quality Gates

- **All executable gates green** (§2.1) with **empty ignore baselines** (`phpstan.neon` `ignoreErrors: []`) and zero TODO/FIXME/HACK debt in first-party code — rare and commendable hygiene.
- **F-11 [med] Mutation testing is toothless as configured**: `--min-msi=0 --min-covered-msi=0` (composer.json:69) and CI gates it to nightly; `var/infection.log` already shows timed-out mutants going unnoticed. Set modest floors (e.g., covered-msi ≥ 60 initially) once the F-10 test lands.
- **F-10 [high] Missing failure-injection test at the sharpest seam.** Coordinator catches reporter throwables (`Coordinator.php:134-148`) and the executor swallows storage errors (`CommandExecutor.php:135-141`), but no test injects a throwing *bare* reporter into the dispatch path to prove the original `Throwable` survives unwrapped — the guarantee is currently protected only by the `Guarded` decorator tests. This is the single most valuable test to add.
- **Config rot [med]:** phpstan scans non-existent `tests/Acceptance` (`phpstan.neon:6`); dead Acceptance Codeception scaffolding remains; `.gitignore` excludes `composer.lock` while CI's quality matrix has a "locked" leg; no CHANGELOG (acceptable pre-1.0 given ADR discipline).
- **bc-check uses `what-changed`,** not roave/backward-compatibility-check — fine pre-1.0; revisit at freeze.

## 12. Complexity, Control Flow, Magic Values

**Control flow — compliant with the "no else" spirit, five literal exceptions.** Repo-wide inventory (all thin guards, none swallow errors):

1. `Adapter/Input/ArrayInputAdapter.php:42` — `} elseif (\in_array($key, $optionNames, true)) {`
2. `Adapter/Input/ArrayInputAdapter.php:44` — `} else {` (unknown-key rejection)
3. `Adapter/CLI/ArgvInputParser.php:65` — `} else {`
4. `Adapter/CLI/ArgvInputParser.php:70` — `} else {`
5. `Adapter/CLI/ArgvInputParser.php:74` — `} else {`

Candidates for early-continue restructuring if the standard is strict; deepest nesting is depth 4 in `split()` (:66-73). Only two methods reach 40 lines (§2.2). Guard-clause/`match` style is consistent throughout.

**Magic values register** (highest-signal items):

| Value | Location | Note |
|---|---|---|
| `'***'` vs `'***redacted***'` | `Domain/CommandContext.php:53` vs `Adapter/Common/RedactionPolicy.php:35` | Two divergent sentinels for one concept; belongs as a constant beside `RedactionPolicyInterface` (F-13) |
| `'int','float','double','string','bool'` | `InputCoercionBehavior.php:40-44,62-68` | Type-name literals; `'double'` unreachable legacy alias; candidate `TypeKind` enum/constants |
| `'1','true','yes','on',…` | `InputCoercionBehavior.php:114-117` | Bool aliases inline |
| `255` | `ValidatedStringValueBehavior.php:63-64` | Max length unnamed; subclasses cannot tune/discover |
| `'<'…'>'`, `'--'…`, `' '` | `Domain/Definition signature computation (`CommandDefinition.php:56-70`) | POSIX presentation baked into Domain |
| `'ms'`, `'duration_ms'` | `Coordinator.php:72,95,145` | Unit/name inline, duplicated call sites |
| `'execution_id'`, `'command'`, `'status'`, `'error_class'`, `'error_message'` | `Coordinator.php:49-93`; seed keys `CommandExecutor.php:102-103` | Occurrence context keys unconsted — consumers must hardcode matches |
| `'publication.recursion-guarded'`, `'publication.failed'` | `GuardedExecutionReporter.php:96,106` | Health signal strings unconsted |
| `'observation'|'occurrence'|'measurement'|'activity_start'|'activity_end'`, `'argument'|'option'`, `'act-'` prefix | Collecting/Guarded reporters; parsers/bridge | Kind labels threaded as strings despite existing enums |
| `1000`, `1024/64/8`, `1_000_000` | Collecting:27; RedactionPolicy:36-38; Executor:109 | Parameterized defaults (acceptable); divisor deserves a named constant |

Occurrence type literals themselves are done right: stable versioned constants on the port (`ExecutionReporterInterface.php:28-38`).

## 13. Test Coverage Assessment

149 tests across three suites. Present categories: domain invariants, lifecycle/coordinator transitions, executor behaviors, middleware ordering/short-circuit, lazy resolution, overflow policies (all four), recursion guard, redaction (unit + e2e), serializer rejections, argv/array parsers, real Symfony Console integration (exit codes, stderr-on-failure semantics at unit level), fiber isolation, persistent-worker residue, terminal idempotency, closed enum sets.

Absent categories: **property-based testing** (none), **cancellation/timeout behavior** (port doesn't exist; `Cancelled` reserved), **decode round-trip serialization** (normalize-only), **port conformance harness** for third-party adapter authors (USER_GUIDE encourages one), and the F-10 bare-reporter injection test.

Negative-test coverage for the security-critical paths is otherwise genuinely good: secrets-never-published e2e, buffer overflow policies, recursion loop breaking, business-exception preservation (through the wrapped path).

## 14. Prioritized Remediation Roadmap

**P0 — correctness (fix before next release)**
1. F-02: capture pre-slice count in `RedactionPolicy` truncation summary.
2. F-03: wrap `coordinator->fail()` + store in defensive try inside the executor catch so the original throwable always wins.
3. F-04: bridge output via `SerializerInterface` + `JSON_THROW_ON_ERROR` + `getErrorOutput()`.
4. F-01: fix DepTrac regex/naming; decide CLI-vs-SymfonyConsole placement; delete dead Common layer.

**P1 — enforcement & proof**
5. F-10: bare-reporter-throws injection test at executor/coordinator level.
6. F-11: set initial MSI floors; keep nightly schedule.
7. F-18: reject unknown argv options; document token grammar (negatives, `--`, single-dash).
8. F-05: explicit duplicate-registration policy (throw or document last-wins).
9. Clean phpstan Acceptance path + dead scaffolding; fix `_1` doc links (F-19); unify redaction marker (F-13).

**P2 — design debt (pre-1.0 window)**
10. F-06: split reporter port (occurrence/measurement vs activity/observation).
11. F-07: resolve coordinator artifact LSP (narrow contract or polymorphic artifacts).
12. F-09: shared input-spec trait for Argument/Option; ArgumentName/OptionName behavior.
13. Decide transition-matrix stance: enforce legal predecessor sets in `transition()` or document aggregate as permissive (`CommandExecution.php:101-115` currently allows backwards/self moves).
14. Harden construction: private `CommandExecution` constructor (route via `define()`), validate `CommandContext` entry shape, validate `endedAt >= startedAt`.
15. Bound retention (F-17): storage cap/eviction or documented consumer responsibility; serializer cycle guard; ambient reset API; F-12 fiber-safe guard counter; F-16 entries() guidance.

**P3 — polish**
16. Constants/enum-backed kinds for §12 register items; `TypeKind` for coercion; named length/divisor constants.
17. `readonly` on stateless adapters; adopt native lazy objects when ghost/proxy guarantees are needed; reconsider hooks for computed projections on mutable types; `never` for unreachable arms.
18. Marker interfaces for remaining exceptions; enum label contracts for `Classification`/`OverflowPolicy`.

## 15. Direct Answers to Review Criteria

- **Every class fully backed by interfaces?** Effectively yes where it matters: 41/50 concrete classes implement contracts; the 9 exceptions are facade/factory/builder/utility types enumerated in §6 with recommendations. All enums except two carry label contracts.
- **else/elseif avoided?** Five occurrences total (§12), all thin guards in two parsers — no hidden control flow; candidates listed if the standard is absolute.
- **Magic strings/numbers?** Register in §12; worst offenders are the dual redaction sentinels, coercion type-name literals (incl. unreachable `'double'`), unconsted occurrence context keys, and health signal strings. Occurrence types themselves are properly centralized.
- **Low complexity?** Yes — quantified: max method 41 lines, max nesting 4 (one site), guard-clause style pervasive, zero long-method clusters.
- **SOLID / hexagonal / factories?** DIP and hexagonal direction are best-in-class for a pre-1.0 library; ISP and one LSP seam are the debt (§4); factories/builders follow named-constructor and safe-default discipline with one stylistic split (immutable definition builder vs mutable command builder).

---

## Follow-ups

### 2026-08-25 — Refactoring plan milestones M1–M2 landed

Implemented per `docs/REFACTORING_PLAN.md` (see its status table for task-level detail):

- **F-02 fixed** — truncation summary now reports the true omitted count (pre-slice capture), and a colliding `_truncated` sentinel key is renamed `_truncated_N` instead of clobbering kept data (`src/Adapter/Common/RedactionPolicy.php`; 3 test cases).
- **F-03 hardened** — failure recording in `CommandExecutor::dispatch()` is defensive; a throwing coordinator/storage can no longer replace the original business `Throwable` (`src/Application/CommandExecutor.php`; 3 injection tests).
- **F-04 closed** — `ConsoleCommandBridge` routes errors to stderr when the output supports it (`ConsoleOutputInterface` guard), encodes with `JSON_THROW_ON_ERROR`, refuses raw objects and object-bearing arrays (depth-capped scan) unless a `SerializerInterface` normalizes them; refusal exits FAILURE with `{"error":"unserializable payload"}` / `"unencodable payload"` (`src/Adapter/CLI/ConsoleCommandBridge.php`; 4 new tests + serializer constructor param, additive).
- **F-05 resolved** — duplicate command registration fails fast via `InvalidCommandException::duplicateCommand()` (ADR `docs/adr/0004-command-name-collision-policy.md`).
- **F-18 resolved** — argv token grammar enforced: unknown options, surplus positionals, and short flags rejected loudly; negative numbers accepted as option values (`--limit -5`, `--limit=-5`) and positionals; `--` terminates option parsing (`src/Adapter/CLI/ArgvInputParser.php`; 7 new tests). Also removed three of the five repo-wide `else` branches.
- Docs: USER_GUIDE argv grammar, registration policy, and CLI output sections updated.

Verification: phpstan level max clean, DepTrac 0 violations (uncovered grew 16→24 from the new Symfony interface reference — the F-01 gap, scheduled M3), cs-fixer clean, composer validate strict OK, full suite 166 tests / 391 assertions green, examples 01–06 exit 0. Not run: mutation (floors land in M4), benchmarks.

### 2026-08-25 — Refactoring plan milestone M3 landed

- **F-01 closed — the architecture gate now enforces the Symfony-free core.** `deptrac.yaml` fixes:
  vendor regex corrected to `/^Symfony\\Component\\Console\\/` (previously unmatchable, so vendor
  tokens were invisible and counted as "uncovered", never violations); the `SymfonyConsole` layer
  now owns both that namespace **and** `src/Adapter/CLI/*` (decision D1 option a — namespaces and
  consumer imports unchanged) with allowances Contract/Domain/Port; the dead `Common` layer and all
  its ruleset references were removed; the explanatory comment now describes real enforcement.
- Evidence: JSON report `Uncovered` dropped **24 → 0** with 0 violations; negative probe (scratch
  `Domain` class importing `Symfony\Component\Console\Output\NullOutput`) failed the gate with exit 1
  and 2 violations; clean tree passes again. Note: `debug:unassigned` was *not* a usable regression
  signal (it only lists tokens inside configured paths) — recorded in the plan.
- **F-19 fixed** — six stale `docs/archive/*.md` citations updated to the real `_1` filenames
  (README 1×, USER_GUIDE 5×).
- **Config rot cleared** — `tests/Acceptance` path removed from `phpstan.neon`; dead Acceptance
  scaffolding deleted (`Acceptance.suite.yml`, empty dir, `AcceptanceTester.php` + generated actions,
  actors regenerated); CI lockfile policy made honest: `composer.lock` stays gitignored (library
  convention) and the fictional `locked` matrix leg became `highest`, preserving a truthful
  highest-vs-lowest matrix.
- Verification: composer validate strict OK · phpstan max clean (139 files) · DepTrac 0 violations /
  0 uncovered · full suite 166 tests / 391 assertions green · cs-fixer clean.

### 2026-08-28 — Refactoring plan milestone M4 landed

- **F-10 proven** — added executor-level injection tests with a **bare** reporter
  (no `GuardedExecutionReporter`): a sink that starts throwing on the second
  occurrence call still returns business results intact on success, and on
  handler/middleware failure the **original** (`assertSame`) `Throwable` is
  rethrown while the execution is persisted as FAILED (`command.failed.v1`
  published, the failure-occurrence publication swallowed). Middleware-throws-before-
  `next()` is recorded as FAILED and rethrown, ambient context restored.
- **F-11 mutation floors** — `composer.json` mutation script now runs
  `infection --min-msi=55 --min-covered-msi=65` (was 0/0); CI nightly inherits
  them. Triage of the legacy timed-out-mutant log: 12 executed escapes → **10
  killed** by new assertions (builder immutability/accumulation, signature optional
  arguments, UUID-v4 regex over 64 draws, bool-coercion aliases incl. integer `1`
  and case variants, reporter measurement forwarding + failure-routing, redacting
  `endActivity` forwarding) and **2 accepted as equivalent** (`MatchArmRemoval` of
  the `'1'` boolean arm, and the `RandomIdentifierGenerator` version-nibble integer
  mutants — both preserve the RFC 4122 v4 format).
- **Tooling caveat recorded:** the Infection↔Codeception adapter misclassifies
  most mutants as "time outs" (inflating reported MSI), so the floors are a
  conservative guardrail, not a measured bar. A follow-up note in the plan
  recommends switching Infection to the native `phpunit` test framework (suites are
  plain PHPUnit classes under `tests/Unit`). Manual mutant probes confirmed every
  intended kill.
- Verification: composer validate strict OK · phpstan max clean (139 files) · DepTrac
  0 violations / 0 uncovered · full suite **173 tests** (162 unit / 9 functional /
  2 integration) green · cs-fixer clean.

### 2026-08-28 — Refactoring plan milestone M5 landed

- **F-13 redaction-marker drift fixed** — the classification stage and policy stage
  now each have a single source of truth: `CommandContextInterface::REDACTED = '***'`
  is used by `CommandContext::redacted()`; `RedactionPolicyInterface::DEFAULT_MARKER =
  '***redacted***'` is the default for `RedactionPolicy`. The USER_GUIDE now documents
  the honest two-stage model (`***` always masking secrets in ambient context;
  `***redacted***` added by the safe wrapper on sensitive *keys*), and a static check
  confirms no inline `'***'`/`'***redacted***'` literals remain in `src/` outside these
  constants.
- **F-14 objects bypass redaction fixed** — `RedactionPolicy::redactValue()` now has an
  object branch: `toArray()`-bearing objects are reduced through that method and their
  keys then redacted, `\Stringable` objects become their string form, and any other
  object becomes `marker . '(object)'`. This mirrors `SafeSerializer`'s accepted shapes
  so policy and serializer agree; no object can pass through `redact()` unchanged.
- **F-15 substring over-redaction made transparent** — the substring semantics are
  documented prominently (case-insensitive substring; safe-direction over-redaction,
  `key` ⊆ `monkey`), and an optional `bool $exactKeyMatch = false` constructor flag adds
  whole-key matching (default stays substring for BC). Tests pin both modes.
- **§12 vocabulary (reporting half) constant-backed** — `ExecutionReporterInterface` now
  exposes `CONTEXT_EXECUTION_ID/COMMAND/STATUS/ERROR_CLASS/ERROR_MESSAGE` and
  `MEASUREMENT_DURATION`/`DURATION_UNIT_MS`; the coordinator and executor `buildContext()`
  use them. `GuardedExecutionReporter` exposes `SIGNAL_RECURSION_GUARDED`/`SIGNAL_PUBLICATION_FAILED`
  plus a private `KIND_*` set; `CollectingExecutionReporter` exposes `ACTIVITY_ID_PREFIX = 'act-'`.
  No bare vocabulary literals remain in `src/` logic.
- Verification: composer validate strict OK · phpstan max clean · DepTrac 0 violations /
  0 uncovered · full suite **180 tests** (169 unit / 9 functional / 2 integration) green ·
  cs-fixer clean.

### 2026-08-29 — Refactoring plan milestone M6 landed

- **D4 (enforce) implemented** — `CommandExecution::transition()` now consults a
  private `ALLOWED_TRANSITIONS` matrix (`defined→received`, `received→executing`);
  illegal predecessor pairs throw `CommandExecutionException` with both statuses
  interpolated. `complete()` requires the current status to be `executing` (the only
  legal predecessor of a terminal outcome), so completing a `defined`/`received`
  execution now throws instead of silently producing a terminal copy. `cancelled`
  keeps no incoming edge (reserved until a `CancellationPort`).
- **Invalid states unrepresentable (REVIEW §8.2)** — `CommandExecution::__construct`
  is now `private`; the only public factory is `define()` (always `defined`), and all
  onward states come exclusively from `transition()`/`complete()`, which already
  preserve the terminal-with-result invariant. `complete()` also rejects
  `endedAt < startedAt` (`Execution ended before it started.`).
- **F-16 entry-shape / secret-exposure guidance** — `CommandContext::__construct`
  validates every entry has the `array{value, classification: Classification}` shape
  (else `InvalidCommandException::malformed` for the offending key), preventing the
  deferred fatal in `redacted()`. `entries()` now carries an explicit security
  warning (raw secret values — internal/serialization only) and a new additive
  `redactedEntries(): array<string, mixed>` is the export-safe accessor that mirrors
  `redacted()`; documented as intentional pre-1.0 API growth.
- **ADR 0005** (`docs/adr/0005-execution-transition-matrix.md`) records the
  enforce-the-matrix decision, the unrepresentable-invalid-states design, and the
  rejected permissive-aggregate alternative; linked from `transition()`'s docblock.
- Behavior change: any caller completing a non-`executing` execution now throws —
  no such call existed in `src/` or tests; the shipped coordinator already conformed.
- Verification: composer validate strict OK · phpstan max clean · DepTrac 0 violations /
  0 uncovered · full suite **187 tests** (176 unit / 9 functional / 2 integration) green ·
  cs-fixer clean.

### 2026-08-29 — Refactoring plan milestone M7 landed

- **F-06 (reporter ISP) implemented** — new `Contract/Port/LifecycleReporterInterface`
  carries only `reportOccurrence(string, array=[])` + `reportMeasurement(string, int|float, string)`
  and the stable occurrence/measurement vocabulary constants. `ExecutionReporterInterface
  extends LifecycleReporterInterface` (keeps `reportObservation`/`beginActivity`/`endActivity`);
  existing reporters and consumers compile unchanged. `CommandExecutionCoordinator` now
  depends only on `LifecycleReporterInterface`, and `CommandIt`/`CommandItFactory` still wire
  the full reporter (superset satisfies subset). Proven by a new fixture implementing only
  `LifecycleReporterInterface` that drives a full dispatch (occurrences + duration recorded).
- **F-07 (coordinator LSP) implemented** — `CommandExecutionCoordinatorInterface` signatures
  now name the concrete `Domain\CommandExecution` instead of the `ExecutionArtifactInterface`
  marker; the `toExecution()` downcast and its `LogicException` are gone (zero `instanceof`
  in the coordinator). The `Contract` layer is deliberately permitted to depend on `Domain` in
  `deptrac.yaml` so the contract can describe the concrete RunIt-shaped execution; `Domain`
  stays independent of `Application`/`SDK`/adapters/external standards (the binding AGENTS.md
  rule). Polymorphic artifacts remain a future, deliberate re-introduction.
- **D3 (serializer default) implemented — wire** — `CommandItFactory::create()` now wires
  `SafeSerializer` by default (`$serializer ??= new SafeSerializer()`); the implicit `null`
  = verbatim passthrough is removed. `Adapter/Common/PassthroughSerializer` is the explicit
  opt-out escape hatch. Stored snapshots are primitive-only by default; object payloads with
  `toArray()` are normalized before persistence (covered by a new integration case).
- **ADRs 0006 / 0007** record the segregation + contract-honesty decision and the
  safe-by-default serialization decision (rejected alternatives documented).
- Behavior change (pre-1.0): third-party coordinator/reporter doubles typed against
  `ExecutionArtifactInterface` must adopt `CommandExecution`; factory-assembled pipelines now
  normalize payloads by default.
- Verification: composer validate strict OK · phpstan max clean · DepTrac 0 violations /
  0 uncovered · full suite **189 tests** (178 unit / 9 functional / 2 integration) green ·
  cs-fixer clean · `php examples/05-custom-ports.php` exits 0 · example 01 unaffected.

### 2026-08-29 — Refactoring plan milestone M8 landed

Pure refactor (no observable behavior change); all gates green with **zero** behavioral
test edits.

- **F-09 (input-spec DRY) implemented** — new `Behavior/Domain/InputSpecBehavior` carries the
  shared `required()/defaultValue()/description()/type()` projections (and the derived
  conveniences `isRequired()/defaultValueOrNull()/typeOrString()/describe()`); `Argument`
  and `Option` slim to a promoted constructor + the per-kind primitives `name()`/`kind()`,
  dropping their duplicated getters. The twin `ArgumentInterface`/`OptionInterface` contracts
  are untouched (semantically distinct seams preserved). Domain LOC delta is negative.
- **F-08 (name-format DRY) implemented** — new `Behavior/Domain/IdentifierNameBehavior`
  owns the identifier regex + message once; `ArgumentName`/`OptionName` call it from their
  `assertAdditionalRules()` hook. The duplicated regex is gone (single definition site).
- **Enum label DRY + contracts** — new `Behavior/Domain/LabelableBehavior` provides
  `label(): string => $this->value`; adopted by `CommandStatus`, `CommandResultStatus`,
  `InputKind`, `Classification`, and `OverflowPolicy`. New label interfaces
  (`ClassificationInterface` in Contract/Domain, `OverflowPolicyInterface` in
  Adapter/Reporting) mirror the existing `InputKindInterface` pattern; both enums implement
  them. `ClosedEnumsTest` extended to cover the two newly contracted enums.
- **Coercion micro-typing (§12 register typing half)** — new `Behavior/Domain/InputType`
  with `STRING/INT/FLOAT/BOOL` + `DOUBLE` constants and `normalize('double') => FLOAT`
  (legacy alias now documented in one place); `InputCoercionBehavior` match arms consume the
  constants, the unreachable `'double'` arm disappears, boolean aliases live in
  `BOOL_TRUE_ALIASES`/`BOOL_FALSE_ALIASES`, the unreachable default arm is extracted to
  `rejectUnknownType()`, and `ValidatedStringValueBehavior::MAX_LENGTH = 255` replaces the
  twice-used literal. `names()` is deduplicated into the trait (`protected final`) and the
  two private adapter copies deleted.
- **Typed lazy factory** — `LazyHandlerProxy::$factory` is now a typed `private \Closure`
  (accepts any `callable`, normalized via `Closure::fromCallable`).
- **Exception markers (optional, additive)** — `HandlerNotFoundExceptionInterface`,
  `InvalidCommandExceptionInterface`, `CommandExecutionExceptionInterface` (Contract/Exception)
  and `BufferOverflowExceptionInterface` (Adapter/Reporting) extend `CommandExceptionInterface`;
  the four concrete finals implement them for precise, decoupled catching.
- Verification: composer validate strict OK · phpstan max clean · DepTrac 0 violations /
  0 uncovered (Allowed 544) · full suite **191 tests** (180 unit / 9 functional / 2 integration)
  green · cs-fixer clean · `php examples/01-quickstart.php` and `php examples/05-custom-ports.php`
  exit 0 · targeted mutation on `InputCoercionBehavior` keeps Covered-Code MSI at 83% (floor 65),
  confirming the coercion branches are still killed after the refactor (full-suite mutation
  exceeded the available run timeout in this environment and was not completed end-to-end).
