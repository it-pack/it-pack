# it-pack — Global Package Review & Touchpoint Map

> **Date:** 2026-08-31 (supersedes the 2026-08-23 edition)
> **Scope:** All 29 directories under `src/`, plus monorepo-root scaffolding (`docs/`, `tests/`, `examples/`, `lab/`, `var/`).
> **Goal:** A global review of every member package of the it-pack family — purpose, maturity, architecture — and a complete map of their **touchpoints**: edges that exist today, edges that are planned, edges that are *deliberately forbidden*, and the seams (ports) through which future edges should flow.
>
> **Edition note (2026-08-31):** The 2026-08-23 edition described 23 directories and exactly one dependency cluster. Since then **six packages were added** (`AsyncIt`, `ConfigureItRunit`, `DeriveItRunit`, `HashItAsync`, `RunItThirdPartySample`, `CommitItRunit`), and **RunIt became a directly-required sibling** for five of them — so the "single cluster" claim no longer holds. This edition rewrites the inventory (§3), touchpoint map (§5), and recommendations (§8) to reflect the new multi-cluster, bridge-era reality. Prior sections not touched by new facts (RunIt/ConfigureIt/CommandIt/etc. profiles, guarded non-edges) remain accurate.
>
> Related prior art in this repo: [`CODE_REVIEW.md`](./CODE_REVIEW.md) + `FINDINGS_{SOLID_DRY,HEXAGONAL,DEFECTS}.md` (deep-dive on 4 early packages, Jul 2026 — partially outdated: they describe "six sibling mini-packages"; there are now 28). This report supersedes their inventory view and adds the cross-package touchpoint dimension.

---

## 1. Executive summary

1. **The family is 21 real packages + 8 name-reserved placeholders** (29 directories total). Seven directories are completely empty (`AdaptIt`, `CodeIt`, `HookIt`, `PatchIt`, `ProjectIt`, `ShowCaseIt`, `WeakenIt`); `ReflectIt` contains only a README. The 21 real packages span a wide maturity range — from RunIt (~395 source files, 24 ADRs, custom tooling) down to the embryonic `HashItAsync` (one bridge class) and `LazeIt` (one half-built class). **Six packages are new since the 2026-08-23 edition:** `AsyncIt`, `ConfigureItRunit`, `DeriveItRunit`, `HashItAsync`, `RunItThirdPartySample`, `CommitItRunit`.
2. **There are now MULTIPLE hard dependency clusters — the "single cluster" claim of the prior edition is obsolete.** The original ConfigureIt hub-and-five-satellites ring still stands, but **RunIt is now a direct `require` of five siblings** (`AsyncIt`, `ConfigureItRunit`, `DeriveItRunit`, `CommitItRunit`, `RunItThirdPartySample`) and an *indirect* one (`HashItAsync` → `AsyncIt` → `RunIt`). `AsyncIt` is the **canonical implementation of RunIt's `ExecutionEngineInterface`** — the first time a sibling ships real RunIt port implementations rather than vocabulary mirrors. `CommitItRunit` realizes the previously-planned CommitIt→RunIt observer adapter.
3. **The gravitational center of the ecosystem is RunIt**, self-described as "the execution model and runtime backbone for the ItPack ecosystem." Three packages (CommandIt, DisenchantIt, CommitIt) have shaped their domain models around RunIt's execution vocabulary **without importing it**, each exposing dedicated ports awaiting future "bridge" packages (still unbuilt: `it-pack/command-it-run-it`, `it-pack/run-it-disenchant-it`). **Five other packages now consume RunIt directly** (AsyncIt as engine, ConfigureItRunit/DeriveItRunit/CommitItRunit as bridges, RunItThirdPartySample as a conformance reference), so the "ports-not-imports" era has demonstrably ended for the bridge layer. The toolchain consolidation (CONSOLIDATION_PLAN.md Phases 0–4) is substantially complete: Codeception is universal among code-bearing packages, deptrac is fully archived, just-bases baselines are uniform, and root orchestration (composer.json + Makefile + CI matrix) is in place — but the architecture-tool migration (Phase 2.4) is **incomplete** (§6 divergence table), leaving 9 packages without phparkitect and 14 (not 18) covered by CI.
4. **The dominant architectural doctrine is hexagonal (ports & adapters), applied consistently** across all substantial packages (now including the new ones): `Contract/` (ports) + `Adapter/` (technology) + `Core|Application|Domain` + `SDK/` facade + composition-root factory. Architecture tests enforce boundaries (`phparkitect` in 9 of 18 code-bearing packages; deptrac configs archived; 9 packages still pending phparkitect per §7-R12), including rules that *forbid* sibling imports in packages that must NOT couple (e.g., CommandIt and DisenchantIt both forbid `ItPack\RunIt\*`). The new bridge packages are explicitly *permitted* to import RunIt — they are the sanctioned seam.
5. **Main risks are ecosystem-level, not per-package:** divergent PHP floors (satellites declare `^8.3` while the core they depend on requires `^8.5`), version-constraint drift (`*` vs `*@dev` — resolved in Phase 3), a test-framework split (Codeception 17 vs PHPUnit 0 — resolved in Phase 1), duplicated context/clock/serializer port definitions that **now need a convergence strategy because the RunIt bridges already exist** (rule-of-three is effectively met: AsyncIt + ConfigureItRunit + DeriveItRunit each reimplement RunIt port families), and monorepo tooling (root `composer.json` + Makefile + CI matrix added in Phase 4; `monorepo-tool`/subtree-split still pending pre-tag).

---

## 2. Method & evidence base

This review was assembled from converging evidence rather than any single source:

| Evidence | How gathered |
|---|---|
| **Manifests** | All 18 `composer.json` read in full (names, deps, suggests, autoload, scripts, repositories). |
| **Actual code edges** | Repo-wide grep for `ItPack\<Ns>` references per package, excluding `vendor/` and `var/`; every hit manually classified as import / comment / config rule. |
| **Docs & decisions** | READMEs; root `EXECUTION.md` (execution-model manifesto); ADRs (RunIt ×24, ConfigureIt ×10, CommandIt ×3, CommitIt ×5, DeriveIt ×4, DisenchantIt ×6, AsyncIt ×2); ROADMAPs; `AGENTS.md` files. |
| **Prior reviews** | Root `CODE_REVIEW.md`, `FINDINGS_*.md`, and per-package `REVIEW.md`s — re-checked against current tree to classify findings as addressed/open. |
| **Cross-cutting conventions** | Scripted comparison of PHP constraints, test frameworks, QA scripts, dev-dependency sets across all manifests. |

Note on tooling: a full static knowledge-graph build was considered but skipped deliberately — the repo carries 15 in-tree `vendor/` trees which would dominate any automated extraction; the targeted greps above give exact, verifiable edge data instead. Where this report cites numbers (file counts, test counts), they come from scripted counts excluding `vendor/`.

---

## 3. Inventory at a glance

Maturity legend: 🟢 mature/tooling-complete · 🟡 functional but young · 🟠 embryonic · ⚪ placeholder.

| # | Dir | Composer name | One-line purpose | Scale (src .php excl. vendor) | Tests | Status |
|---|-----|---------------|------------------|------------------------------|-------|--------|
| 1 | `RunIt` | `it-pack/run-it` | Execution model & runtime backbone of the ecosystem | ~395 | 136 files, 9 suites (incl. Conformance/Concurrency/Architecture) | 🟢 initial development, heavily tooled |
| 2 | `ConfigureIt` | `it-pack/configure-it` | Framework-agnostic configuration SDK & resolution engine | 191 | 70 files (Codeception) | 🟢 "Phase 8" |
| 3 | `DeriveIt` | `it-pack/derive-it` | Derives clean interfaces/adapters from class hierarchies (anti type-leakage) | 48 | 79 files, 113 unit tests, MSI 86% | 🟢 ready to tag v0.1.0 |
| 4 | `CommitIt` | `it-pack/commit-it` | Vendor-neutral VCS API/SDK (git first) | 37 | 31 files (Acceptance+Functional) | 🟢 milestones M0–M8 complete |
| 5 | `DisenchantIt` | `it-pack/disenchant-it` | Finds magic scalars, promotes them to constants/enums | 131 | 50 files (Acceptance+Functional) | 🟡 maturing, pre-1.0 |
| 6 | `CommandIt` | `it-pack/command-it` | CQRS-style command definition/dispatch library | 98 | 49 files (4 suites) | 🟡 milestones M0–M8 done, bridge pending |
| 7 | `HashIt` | `it-pack/hash-it` | Hexagonal hashing utility (hash/HMAC/stream/bulk) | small (single-digit classes + adapters) | 37 methods / 168 assertions | 🟢 rebuilt as reference architecture |
| 8 | `ConfigureItExplorer` | `it-pack/configure-it-explorer` | Read-only web explorer + graph query API over resolved config | 12 | 2 (Codeception) | 🟡 dev-server scope |
| 9 | `ConfigureItSchemaDocs` | `it-pack/configure-it-schema-docs` | Markdown + JSON-Schema emission from Nette schema definitions | 5 | 5 (Codeception) | 🟡 |
| 10 | `ConfigureItTesting` | `it-pack/configure-it-testing` | Fakes + assertion trait for ConfigureIt consumers | 6 | 6 (Codeception) | 🟡 |
| 11 | `ConfigureItSymfony` | `it-pack/configure-it-symfony` | Symfony bundle/DI extension exposing resolved snapshot | 4 | 2 (Codeception) | 🟡 |
| 12 | `ConfigureItPsr11` | `it-pack/configure-it-psr11` | PSR-11 container view of resolved snapshot | 3 | 1 (Codeception) | 🟡 minimal-but-complete |
| 13 | `LazeIt` | `it-pack/laze-it` | Utility API for PHP native lazy objects | 1 (half-built) | none | 🟠 |
| 14 | `FileIt` | `it-pack/file-it` | File metadata value object(s) | 1 (`FileInfo`) | none | 🟠 anemic |
| 15 | `ExtendIt` | `it-pack/extend-it` | "Experimental" class-inspection toolkit | 1 (no-op stub) | none | 🟠 stub |
| 16 | `AsyncIt` | `it-pack/async-it` | Portable, capability-oriented async execution & scheduling — **canonical `ExecutionEngineInterface` impl of RunIt** (Tier 4 of the semantic stack) | ~98 | 151 files (Unit/Integration/Conformance/Concurrency), 2 ADRs, `vocab/`, phpbench | 🟢 substantial, tooling-complete |
| 17 | `ConfigureItRunit` | `it-pack/configure-it-runit` | Bridge: ConfigureIt resolution → RunIt execution (activities/measurements/observations/occurrences/snapshot) | 2 (`ConfigureItExecutions`, `RunItResolutionReporter`) | 16 (Codeception) | 🟡 bridge, minimal-but-complete |
| 18 | `DeriveItRunit` | `it-pack/derive-it-runit` | Bridge: DeriveIt telemetry → RunIt execution | 2 (`DeriveItExecutions`, `RunItDerivationEventSink`) | 18 (Codeception) | 🟡 bridge |
| 19 | `HashItAsync` | `it-pack/hash-it-async` | Bridge: fans `HashIt::hashFile` across the AsyncIt runtime | 1 | 125 files (Codeception) | 🟡 bridge |
| 20 | `RunItThirdPartySample` | `it-pack/run-it-third-party-sample` | Reference third-party adapter proving RunIt's `ArtifactPublisherInterface`/`ContextStorageInterface` ports pass shipped conformance suites | 4 (2 context + 2 publisher adapters) | 19 (Codeception) | 🟡 reference adapter |
| 21 | `CommitItRunit` | `it-pack/commit-it-runit` | Bridge: CommitIt VCS operations → RunIt execution via `RepositoryObserver` lifecycle port (activities/measurements/observations/occurrences/snapshot) | 4 (`CommitItRunitAdapter` + `Model/Activity`/`Occurrence`/`Snapshot`) | 1 (Codeception) | 🟡 bridge, newly built |
| 22 | `ReflectIt` | — *(none)* | Reflection extension library | 0 (README only) | — | ⚪ reserved |
| 23–30 | `AdaptIt` `CodeIt` `HookIt` `PatchIt` `ProjectIt` `ShowCaseIt` `WeakenIt` | — *(none)* | — | 0 | — | ⚪ reserved |

Shared authorship/branding: all packages authored by "tidal" (`timomichna@yahoo.de`), GitHub org `it-pack`, keywords `it-pack`/`itpack`, MIT license where declared (see §7-R9 for gaps).

---

## 4. Family profiles

The family organizes naturally into six clusters plus the placeholder reserve.

### 4.1 Execution backbone — `RunIt`

**Purpose.** Models *execution itself*, decoupled from any tracing/logging/metrics/events vendor. Canonical model: `Execution → Context, Activity, Measurement, Observation, Occurrence, Snapshot`. Motto: *"Everything happens within an execution."* Semantics are formalized in OWL/SKOS vocabularies (`vocab/run-it.ttl`, `vocab/run-it-taxonomy.ttl`) with stable IRIs under `w3id.org/itpack/runit#`.

**Shape.** Strict layering (Domain → Contract, Application, SDK, Adapter), enforced by `phparkitect.php`. Key abstractions:

- Domain records: `ExecutionInterface`, `ExecutionContextInterface`, `ActivityInterface`, `MeasurementInterface`, `ObservationInterface`, `OccurrenceInterface`, `SnapshotInterface`.
- Application services: `ExecutionRunnerInterface`, `ActivityRunnerInterface`, `ExecutionEngineInterface` (sync/Fiber/coroutine), `ExecutionLifecycleCoordinatorInterface` (state machine CREATED→RUNNING→{SUCCEEDED|FAILED|CANCELLED|TIMED_OUT}), recorder contracts for measurements/observations/occurrences/snapshots.
- **Ports for the rest of the ecosystem** (`src/Contract/Port/`): `MonotonicClockInterface`, `IdentifierGeneratorInterface`, `ContextStorageInterface`+`ContextTokenInterface` (ambient/Fiber/Swoole propagation), `RuntimeContextDetectorInterface`, `SerializerInterface`, `ArtifactPublisherInterface`, `SnapshotStoreInterface`, `SnapshotSignerInterface`, plus PSR-15-shaped `ServerExecutionMiddlewareInterface`.
- Adapters already in-tree: PSR-20 clock, PSR-3 log publisher, PSR-14 occurrence publisher, PSR-7/15/18 HTTP propagation/middleware/client decorator, OpenTelemetry, CloudEvents, Fiber, Swoole, Memory, Null.
- Publication subsystem (~45 classes): pipelines with recursion guards, redaction, sampling/routing, fail-open/fail-closed policies, bounded buffers.

**Ecosystem role.** RunIt is the *vocabulary donor*: siblings mirror its model in their own value objects and occurrence names, and defer persistence/transport/publishing to future `run-it-*` bridge packages (see §5.3). Uniquely among siblings it also ships engineering infrastructure others may reuse as precedent: `tools/api-compat` (BC checker), dogfood runner, custom PHPStan rules, an API baseline workflow.

**External deps.** Runtime: only `just-bases/php-obs-base ^1.0` + `php ^8.4`. Everything else (PSRs, OTel, Swoole) is suggest/dev.

### 4.2 Commands — `CommandIt`

**Purpose.** Multi-purpose command creation & execution (CQRS-style): define once, dispatch programmatically via `CommandBusInterface` or project to CLI (Symfony-Console-shaped bridge, zero Symfony imports in core). Explicit lifecycle `received → executing → succeeded|failed` (`cancelled` reserved).

**Shape.** Zero runtime dependencies (`php:^8.4` only) — the most aggressively decoupled package. `Domain\CommandExecution` is deliberately **"RunIt-shaped"** but self-contained. Its entire integration surface lives in `src/Contract/Port/`: `ExecutionReporterInterface` (versioned occurrence names `command.{received|handled|failed}.v1`), `ExecutionContextProviderInterface`+`ContextTokenInterface`, `CommandExecutionStorageInterface` (the designated persistence seam for the future RunIt bridge), `RuntimeContextDetectorInterface`, `ClockInterface`, `IdentifierGeneratorInterface`, `SerializerInterface`, `RedactionPolicyInterface`. `Contract/Domain/ExecutionArtifactInterface` is a minimal RunIt-alignment marker. In-box adapters cover reporting guards/redaction, ambient+Fiber-bound context storage, argv parsing and a CLI bridge.

**Integration stance (ADR-0001, accepted 2026-08-21).** Integration with RunIt happens *exclusively* through these ports, implemented by a separate future package **`it-pack/command-it-run-it`** (declared in `suggest:`). Deptrac declares a `RunIt` layer that is allowed nowhere — any direct import fails CI. Serialized shapes are not bent to chase a guessed RunIt API; mismatches get absorbed inside the bridge. Supporting ADRs cover lazy ghost-object handler resolution and fiber-bound context (WeakMap-per-Fiber, copy-on-write).

### 4.3 Configuration family — `ConfigureIt` + 5 satellites

**Hub: `ConfigureIt`** — framework-agnostic configuration SDK and resolution engine; treats configuration as first-class data (discover/load/validate/merge/transform/resolve/graph/explain/diff/drift-detect). Highest maturity signals of the whole family alongside RunIt: 191 source files, 70 test files, 10 ADRs, rich `docs/architecture/*` (PORTS, ADAPTERS, GRAPH, PIPELINE, RESOLUTION, OBSERVABILITY), CLI binary `bin/configure-it`, OpenTelemetry bridge, enterprise source adapters (Vault, Consul, etcd, AWS SSM, Azure, GCP, K8s, Docker secrets), SOPS/Age decryption. **Zero runtime dependencies** — even nette/schema, phpdotenv, TOML/FrontMatter support are dev-only (ADR-enforced), making the core universally installable. Its universal seams are ~40 contracts in `src/Contract/`: sources, readers, loaders, loader factory, processors, schema, resolver, immutable snapshot/snapshot factory, graph, cache, decrypter, secret source, watcher, wiring, plus `Contract/Execution/*` observability carriers.

**Satellites (all path-repo-wired via `../ConfigureIt`):**

| Satellite | Adds | Key classes | Own ports |
|---|---|---|---|
| `ConfigureItPsr11` | PSR-11 exposure of snapshot | `ConfigureItContainer` (final readonly), `ConfigureItContainerFactory::fromBuilder()` | implements `Psr\Container\ContainerInterface` |
| `ConfigureItSymfony` | Bundle + DI extension (`configure_it:` YAML → builder → service) | `ConfigureItBundle`, `ConfigureItExtension`, `ConfigureItSnapshotFactory` | exposes `ConfigurationSnapshotInterface` service id |
| `ConfigureItSchemaDocs` | Markdown + JSON-Schema (2020-12) docs from `Nette\Schema\Expect` graphs | `SchemaIntrospector`, `SchemaDocGenerator`, `MarkdownRenderer`, `JsonSchemaRenderer`, CLI binary | none (consumes conventions) |
| `ConfigureItTesting` | Test doubles + assertions for consumers | `FakeConfigurationSource`, `FakeLoader`, `SpyProcessor`, `InMemoryGraph`, `ConfigurationAssertions` trait | implements core ports as fakes (reference implementations) |
| `ConfigureItExplorer` | Read-only web explorer + graph query API (explain/diff/provenance) | `ExplorerController`, `GraphQuery`/`QueryParser`, micro HTTP kernel, `HtmlRenderer`, CLI binary | **`GraphProviderInterface`** (swappable graph backend) |

Dependency direction is clean: satellites depend only on core + their target ecosystem lib; nothing points back. Planned expansion (core `docs/VISION.md`): further format/integration satellites (`configure-it-{env,yaml,toml,neon,frontmatter,schema,di,vault,tableschema}`) and a deferred Laravel bundle.

### 4.4 Code intelligence — `DeriveIt`, `DisenchantIt`

**`DeriveIt`** derives clean, self-contained ports (interfaces) — and optionally delegating adapters — from third-party/legacy classes by walking parent/trait/interface graphs, guaranteeing zero leakage of the adaptee's hierarchy. Facade `DeriveIt::derive()/deriveAdapter()/deriveFamily()…` + `bin/derive` CLI; extension seams: `RecursiveResolverInterface` family, `MethodFilterInterface` chain, `AdapterGeneratorInterface`. Built on `nette/php-generator`; 113 unit tests with golden-file snapshots, MSI 86%, "ready to tag v0.1.0". Historically the family flagship (per root `CODE_REVIEW.md`) and template for the family's QA culture. Known open item: `ClassReflection` doubles as a static resolver registry (service-locator smell, flagged in root FINDINGS docs, still present).

**`DisenchantIt`** finds magic scalar values in PHP source and promotes them to named constants (enums later), via a fully port-based pipeline: AST scan → role classification → candidate filtering/grouping → placement strategy → line-based rewrite → optional semantic-convention aliasing; SARIF recording and lint verification observers included. Hard safety rule: analysed files are never executed/autoloaded. ~40 contract ports; `RewriterInterface` explicitly anticipates swapping in Rector (which is currently a declared runtime dep — see §7-R8). Its `ValueObject/Execution/*` (Context/Observation/Occurrence/Measurement/Snapshot) and occurrence names (`disenchant.it.magic_value.detected.v1`) adopt RunIt vocabulary without importing it; a `phparkitect` rule forbids `ItPack\RunIt\*`; the bridge package `it-pack/run-it-disenchant-it` is planned (RunIt ADR-016 / DisenchantIt ADR-0005). Notably, its `var/test-code/` fixtures contain hand-built code that *mirrors ConfigureIt's public API* (sources/readers/loaders/snapshots/graph) — realistic test input, not a dependency (see §5.2).

### 4.5 Version control — `CommitIt`

Vendor-neutral VCS API/SDK with git (via `czproject/git-php`) as first adapter, isolated in `src/Adapter/Git`. Contracts: `RepositoryFactory`, `Repository`, `WorkingTree`, and the integration-relevant **`RepositoryObserver`** lifecycle port (`onStart/onSuccess/onFailure`) whose PHPDoc explicitly maps to RunIt's Activity/Occurrence/Observation triad without coupling. Static facade `CommitIt::open/init/clone(?RepositoryObserver)` composes `ObservedRepository` decorators. Milestones M0–M8 marked complete (2026-08-22); remaining direction is ecosystem adapters (a `commit-it-runit` observer adapter, PSR-3 logging observer) rather than core features. The **CommitItRunit** bridge (`it-pack/commit-it-runit`, §4.8) realizes the RunIt observer adapter — it implements `RepositoryObserver` to surface VCS operations as RunIt activities/occurrences/snapshots. Root `tests/Unit/SDK/CommitItTest.php` targets its SDK facade but is currently broken (see §7-R5).

### 4.6 Micro-libraries — `HashIt`, `LazeIt`, `FileIt`, `ExtendIt`

- **`HashIt`** — rebuilt (per root FINDINGS docs) into the family's *reference implementation* of the target architecture: owned ports (`HashContextInterface`, `ContextFactoryInterface`, `FilepathInterface`, `HashInterface`, `HmacHasherInterface`), native adapters confined to `src/Adapter/`, `NativeHashIt` composition root, immutable `Hash`/`HashBuilder`, stream/bulk helpers, timing-safe equality, and an elegant **attribute-driven algorithm catalogue** (`#[HmacSafe]` on enum cases, consumed reflectively — replacing a thrice-encoded list). 37 tests/168 assertions, phpstan-clean. Gap: composer manifest lacks a `php` floor (only `ext-hash`) despite using modern PHP.
- **`LazeIt`** — intended convenience SDK for PHP native lazy objects (`newLazyGhost`/`newLazyProxy` appear in `var/lab/` experiments ⇒ de facto PHP ≥ 8.4). Reality: one half-built `InitializerValidator` (dead private method, unreachable branches), no tests, **no `php` constraint declared**.
- **`FileIt`** — a single anemic `FileInfo` value object (9 untyped public properties, `int|false` lies). Autoload maps PSR-4 `"ItPack\\FileIt\\": ""` to the package *root* while the class declares namespace `ItPack\FileIt` — mapping and layout disagree.
- **`ExtendIt`** — README says "Experimental Status"; sole class `ClassInspector::inspect()` computes methods and discards them (documented no-op). Rest is `var/lab/` scratch work incl. Dagger experiments. No `php` floor, no license field.

### 4.7 Async runtime — `AsyncIt`

**Purpose.** The Tier-4 "Scheduling" layer of the ItPack semantic stack (`Spindle` intent → `AExO` orchestration → `RunIt` execution → `AsyncIt` scheduling → engine/OS). Rather than wrapping a vendor async engine, AsyncIt is **implementation-neutral and capability-driven**: a runtime capability descriptor (`Supported`/`Emulated`/`Adapter-Only`/`Unavailable`) selects scheduling policy independent of the underlying multiplexer. It is the **canonical, in-tree implementation of RunIt's `ExecutionEngineInterface`** — it consumes RunIt's identity/context/artifact model and never redefines it, making it the first sibling that *implements* RunIt ports rather than mirroring them.

**Shape.** Full hexagonal layout: `Contract/Port` (clock, context, publisher, scheduler, poller, scope, artifact), `Application` (Scheduler, Context, Clock, Discovery, Poller, Publisher, Scope, Factory, Artifact), `Adapter` (interop `ReactPhp`/`Amp`, native `Swoole`/`Swow`/`Revolt`), `Conformance` (verifier + fixtures + result types), `Domain` (Channel, Exception), `SDK` facade + factory. `phparkitect.php` enforces layering; carries its own `vocab/` (RunIt-aligned ontology) and `phpbench.json`. Distinctive invariants documented in its README: fiber-safe context *detach/reattach* across suspension, **observed cooperative cancellation** (no preemptive kill; `ConflictingCompletionException` on double terminal transition), and a strict split of *polling ≠ scheduling ≠ execution semantics*.

**Ecosystem role.** AsyncIt is the load-bearing bridge between RunIt (the model) and actual work progression. By implementing `ExecutionEngineInterface` it turns RunIt from a vocabulary into a runnable substrate; `HashItAsync` already rides on it (§4.8). Its capability-descriptor approach is also the natural convergence point for the duplicated clock/context/serializer ports flagged in §7-R3.

**External deps.** Runtime: `php ^8.4` + `it-pack/run-it *@dev` only. Engines (Amp `^3.1`, React `^1.0`, OpenTelemetry SDK, PSR http/clock/log/event-dispatcher, Symfony HttpClient, Nyholm PSR-7) are `require-dev` — the core stays engine-free, consistent with the "ports-not-imports until you're a bridge" doctrine.

### 4.8 Bridge & integration packages (new since 2026-08-23)

**Six** small packages were added that formalize the edges the prior edition only *predicted*:

- **`ConfigureItRunit`** (`it-pack/configure-it-runit`) — 2 classes (`ConfigureItExecutions`, `RunItResolutionReporter`); `require`s **both** `configure-it *@dev` and `run-it *@dev`. Bridges a ConfigureIt resolution onto a RunIt execution so resolution surfaces as a first-class execution (activities/measurements/observations/occurrences/terminal snapshot). Notably this is the first bridge touching the *hub* package — the prior edition assumed only CommandIt/CommitIt/DisenchantIt would bridge, never ConfigureIt. Codeception.
- **`DeriveItRunit`** (`it-pack/derive-it-runit`) — 2 classes (`DeriveItExecutions`, `RunItDerivationEventSink`); `require`s `derive-it *@dev` + `run-it *@dev`. Streams DeriveIt derivation events into a RunIt execution. Codeception.
- **`HashItAsync`** (`it-pack/hash-it-async`) — 1 class that fans `HashIt::hashFile` across the `AsyncIt` runtime; `require`s `hash-it *@dev` + `async-it *@dev` (and thus RunIt transitively). Codeception, 125 test files. Demonstrates a *second* bridging shape: consumer→engine rather than consumer→RunIt directly.
- **`RunItThirdPartySample`** (`it-pack/run-it-third-party-sample`) — 4 classes implementing RunIt's `ContextStorageInterface` (ambient static-stack storage + scope token) and `ArtifactPublisherInterface` (stream + buffered-file publishers). Explicitly a **third-party conformance reference**: proves an external consumer depending on `run-it` purely as a vendor can satisfy the shipped conformance suites. Codeception.
- **`CommitItRunit`** (`it-pack/commit-it-runit`) — 4 classes (`CommitItRunitAdapter` + `Model/Activity`/`Occurrence`/`Snapshot`); `require`s both `it-pack/commit-it *@dev` and `it-pack/run-it *@dev`. Implements CommitIt's `RepositoryObserver` lifecycle port to surface VCS operations (commit/clone/init) as RunIt activities, observations, occurrences, and a terminal snapshot. This realizes the CommitIt→RunIt observer adapter that was planned in the 2026-08-23 edition (§5.3). Codeception.

All six confirm the bridge era has begun. Their naming diverges from the prior edition's recommendation (§8-R3): the built ones use a **`<consumer>-run-it` / `<consumer>-async` suffix** (`configure-it-runit`, `derive-it-runit`, `hash-it-async`), while `CommitItRunit` omits the hyphen (`commit-it-runit`) — *not* the `run-it-<consumer>` prefix the 2026-08-23 edition proposed for sortability.

### 4.9 Placeholder reserve — 8 reserved names

`ReflectIt` (README only: *"Extends native PHP Reflection with additional features"*), and empty `AdaptIt`, `CodeIt`, `HookIt`, `PatchIt`, `ProjectIt`, `ShowCaseIt`, `WeakenIt`. The names teleograph intent (adapter utilities, code generation, hooks, patching, project scaffolding, showcase, weakening/mocking?) but nothing documents scope, priority, or owner. They cost nothing but also communicate nothing — see §8-R6.

---

## 5. Touchpoint map

This is the heart of the review. Four categories: hard edges (today), soft edges (today, non-code), planned edges (documented future), and guarded non-edges (deliberately forbidden).

### 5.1 Hard edges — actual dependencies today

> **Changed since 2026-08-23:** the prior edition reported *exactly one* hard dependency cluster (the ConfigureIt ring). That is **no longer true** — RunIt is now a directly-required sibling of **five** packages, and a sixth reaches it transitively. The "ports-not-imports" doctrine still holds for the *non-bridge* packages (CommandIt, DisenchantIt, ConfigureIt core), but the bridge layer now imports RunIt by design. CommitIt's observer adapter is now realized via `CommitItRunit` (a hard edge, not planned).

```mermaid
graph TD
    subgraph "Configuration family (hub + 5 satellites)"
      CI[it-pack/configure-it]
      PS11[configure-it-psr11] --> CI
      SY[configure-it-symfony] --> CI
      SD[configure-it-schema-docs] --> CI
      TE[configure-it-testing] --> CI
      EX[configure-it-explorer] --> CI
    end
    subgraph "RunIt consumer cluster (NEW)"
      RI[it-pack/run-it]
      AS[it-pack/async-it] --> RI
      CR[configure-it-runit] --> CI
      CR --> RI
      DR[derive-it-runit] --> DI2[it-pack/derive-it]
      DR --> RI
      RT[run-it-third-party-sample] --> RI
      HA[hash-it-async] --> HI[it-pack/hash-it]
      HA --> AS
      CT[commit-it-runit] --> CM2[it-pack/commit-it]
      CT --> RI
    end
    CO[it-pack/command-it -.->|"planned bridge<br/>command-it-run-it (not built)"| RI
    DIS[it-pack/disenchant-it -.->|"planned bridge<br/>run-it-disenchant-it (not built)"| RI
    ```

The complete list of inter-package `composer` requirements:

| Dependent | Dependency | Constraint | Wiring |
|---|---|---|---|
| ConfigureItPsr11 | it-pack/configure-it | `*@dev` | path repo `../ConfigureIt` |
| ConfigureItSymfony | it-pack/configure-it | `*@dev` | path repo `../ConfigureIt` |
| ConfigureItSchemaDocs | it-pack/configure-it | `*@dev` | symlinked path repo |
| ConfigureItTesting | it-pack/configure-it | `*@dev` | symlinked path repo |
| ConfigureItExplorer | it-pack/configure-it | `*@dev` | symlinked path repo |
| AsyncIt | it-pack/run-it | `*@dev` | symlinked path repo `../RunIt` |
| ConfigureItRunit | it-pack/configure-it | `*@dev` | path repo `../ConfigureIt` |
| ConfigureItRunit | it-pack/run-it | `*@dev` | path repo `../RunIt` |
| DeriveItRunit | it-pack/derive-it | `*@dev` | path repo `../DeriveIt` |
| DeriveItRunit | it-pack/run-it | `*@dev` | path repo `../RunIt` |
| HashItAsync | it-pack/hash-it | `*@dev` | symlinked path repo `../HashIt` |
| HashItAsync | it-pack/async-it | `*@dev` | symlinked path repo `../AsyncIt` |
| RunItThirdPartySample | it-pack/run-it | `*@dev` | path repo `../RunIt` |
| CommitItRunit | it-pack/commit-it | `*@dev` | path repo `../CommitIt` |
| CommitItRunit | it-pack/run-it | `*@dev` | path repo `../RunIt` |

So there are now **two primary dependency clusters** (ConfigureIt hub-and-satellites, and RunIt-with-consumers) plus the smaller HashIt→AsyncIt→RunIt chain. Verified by exhaustive sibling `require` + `use ItPack\…` grep across all packages (excluding `vendor/`, `var/`): the explicit `use` statements now exist for `AsyncIt`→`RunIt`, `ConfigureItRunit`→`{ConfigureIt,RunIt}`, `DeriveItRunit`→`{DeriveIt,RunIt}`, `HashItAsync`→`{HashIt,AsyncIt}`, `RunItThirdPartySample`→`RunIt`, `CommitItRunit`→`{CommitIt,RunIt}`. The three vocabulary-mirror packages still do **not** import RunIt: RunIt contains **zero** mentions of CommandIt/CommitIt/DisenchantIt; CommandIt references RunIt only in PHPDoc vocabulary IRIs and phparkitect forbid-rules; DeriveIt/HashIt/LazeIt/FileIt/ExtendIt reference nobody.

### 5.2 Soft edges — present today, not dependencies

1. **Vocabulary adoption (RunIt → CommandIt, DisenchantIt, CommitIt).** All three model executions with RunIt's noun set and versioned occurrence names (`command.received.v1`, `disenchant.it.magic_value.detected.v1`) and carry RunIt-aligned marker types (`ExecutionArtifactInterface`, `ExecutionRecorderInterface` + `Execution/*` VOs, `RepositoryObserver`). These compile-time-independent mirrors are the *contract* the future bridges will bind to.
2. **Fixture mirroring (DisenchantIt ↔ ConfigureIt).** `src/DisenchantIt/var/test-code/` contains realistic sample code written against ConfigureIt-style APIs (sources, readers, loaders, snapshots, graphs) used as scanner input. Not a dependency — but a maintenance coupling: ConfigureIt API renames silently stale these fixtures (they're outside CI compile paths since they're never autoloaded by Disenchanter itself).
3. **Monorepo-root test targeting CommitIt.** `tests/Unit/SDK/CommitItTest.php` exercises CommitIt's SDK facade from the repo root — evidence a root-level integration-test layer was started, though it's currently broken (§7-R5).
4. **Documentation cross-references.** Root `EXECUTION.md` walks a ConfigureIt resolution end-to-end as its worked example and announces the `run-it-*` family; per-package EXECUTION/USER_GUIDE docs repeatedly reference the shared model. Docs are currently the densest connective tissue in the repo.

### 5.3 Planned edges — the documented future graph

Aggregated from ADRs, ROADMAPs, VISION docs, `suggest:` sections, and root `EXECUTION.md`. **Since the 2026-08-23 edition several of these have been realized**, so the table is now split.

#### Realized since 2026-08-23 (built)

| Package | Connects | Evidence |
|---|---|---|
| `it-pack/async-it` | RunIt `ExecutionEngineInterface` → runnable scheduling substrate (Tier 4) | `src/AsyncIt/composer.json` (`require it-pack/run-it`), `src/AsyncIt/README.md` §1 five-tier stack |
| `it-pack/configure-it-runit` | ConfigureIt resolution → RunIt execution | `src/ConfigureItRunit/composer.json` (requires both cores) |
| `it-pack/derive-it-runit` | DeriveIt telemetry → RunIt execution | `src/DeriveItRunit/composer.json` (requires both cores) |
| `it-pack/hash-it-async` | HashIt `hashFile` → AsyncIt runtime (consumer→engine, not consumer→RunIt) | `src/HashItAsync/composer.json` |
| `it-pack/run-it-third-party-sample` | External vendor → RunIt `ArtifactPublisherInterface`/`ContextStorageInterface` (conformance proof) | `src/RunItThirdPartySample/src/Publisher`, `…/Context` |
| `it-pack/commit-it-runit` | CommitIt `RepositoryObserver` lifecycle → RunIt execution (activities/occurrences/snapshot) | `src/CommitItRunit/src/CommitItRunitAdapter.php` + `src/CommitItRunit/src/Model/` |

#### Still planned (documented, not yet built)

| Planned package | Connects | Evidence |
|---|---|---|
| `it-pack/command-it-run-it` | CommandIt ports → RunIt pipeline (persist/transport executions) | CommandIt `composer.suggest`, ADR-0001, MODEL.md — **directory does not yet exist** |
| `it-pack/run-it-disenchant-it` | DisenchantIt recorder → RunIt | DisenchantIt ADR-0005 + archive plan; RunIt ADR-016 — **directory does not yet exist** |
| `run-it-context`, `run-it-otel`, `run-it-cloudevents`, `run-it-psr`, `run-it-logger`, `run-it-metrics`, `run-it-tracing` | RunIt splits per concern | Root EXECUTION.md:436–452, ConfigureIt EXECUTION.md, CommitIt docs — **none built** |
| `configure-it-{env,yaml,toml,neon,frontmatter,schema,di,vault,tableschema}` | Format/integration satellites off ConfigureIt | ConfigureIt VISION.md "Optional Satellite Packages", AGENTS.md |
| `configure-it-laravel` | Laravel bundle | ConfigureIt archive roadmap T8.1.3, CHANGELOG (deferred) |
| External consumers: *ChaosCockpit, AExO, HoppaDB, Spindle* | consume the shared execution vocabulary | Root EXECUTION.md |

Naming has now **diverged further** from the 2026-08-23 recommendation (§8-R3): realized bridges use a **`<consumer>-run-it` / `<consumer>-async` suffix** (`configure-it-runit`, `derive-it-runit`, `hash-it-async`) while `commit-it-runit` and `run-it-third-party-sample` follow a slightly different convention. `AsyncIt` is itself `async-it`. The still-unbuilt ones are styled `command-it-run-it` and `run-it-disenchant-it`. A single convention is now overdue (see §8-R3).

### 5.4 Port inventories — the integration seams

Every substantial package publishes a deliberate set of seams. These are the touchpoints third parties (and sibling bridge packages) are meant to program against:

| Package | Primary integration seams (non-exhaustive) |
|---|---|
| **RunIt** | `Contract/Port/*`: MonotonicClock, IdentifierGenerator, ContextStorage+ContextToken, RuntimeContextDetector, Serializer, ArtifactPublisher, SnapshotStore/Signer · Runner/Engine/LifecycleCoordinator · Measurement/Observation/Occurrence/Snapshot recorders · ServerExecutionMiddleware (PSR-15 shape) |
| **AsyncIt** | `Contract/Port/*`: Scheduler, Poller, Scope, Clock, Context, Publisher, Artifact · `ExecutionEngineInterface` (implements RunIt's engine contract) · CapabilityDescriptor (runtime capability probe) · Conformance verifier/fixtures |
| **CommandIt** | `Contract/Port/*`: ExecutionReporter, ExecutionContextProvider+ContextToken, CommandExecutionStorage, RuntimeContextDetector, Clock, IdentifierGenerator, Serializer, RedactionPolicy · `ExecutionArtifactInterface` (alignment marker) · `CommandBusInterface`, `CommandMiddlewareInterface` |
| **ConfigureIt** | `Contract/*`: Source/Reader/Loader(+Factory)/Processor/Schema/Resolver/Snapshot(+Factory)/Graph/Cache/Decrypter/SecretSource/Watcher · `Wiring/ConfigurationWiringInterface` · `Contract/Execution/*` |
| **CommitIt** | `RepositoryFactory`, `Repository`, `WorkingTree`, `RepositoryObserver` (lifecycle → RunIt triad) |
| **DisenchantIt** | Detector/MagicLiteralVisitor/CandidateFilter/Grouper · ConstantPlacementStrategy(+Factories) · `RewriterInterface` (Rector swap anticipated) · SemConvCollector/Store/AliasRewriter · NamePolicy/DictionaryNameResolver · `ExecutionRecorderInterface`, VerificationObserver, SarifRecorder |
| **DeriveIt** | `RecursiveResolverInterface` (+Class/Interface/Trait variants), `DeriverInterface`, `AdapterGeneratorInterface`, `FamilyDeriverInterface`, `MethodFilterInterface` |
| **HashIt** | `HashContextInterface`, `ContextFactoryInterface`, `FilepathInterface`, `HmacHasherInterface`, `HashInterface` |
| **ConfigureItExplorer** | `GraphProviderInterface` |

Two observations:

- **Duplication is now a live liability, not a future worry.** Clock, identifier-generator, serializer, context-storage/token, and runtime-context-detector ports exist independently in RunIt *and* CommandIt (near-isomorphic signatures), and **the rule-of-three trigger has fired**: `AsyncIt` (implements RunIt's `ExecutionEngineInterface` + carries its own clock/context/publisher ports), `ConfigureItRunit`, `DeriveItRunit`, and `CommitItRunit` each reimplement the same RunIt port families from the consuming side. ADR-0001 explicitly rejected a premature shared base package, which was defensible *before* any bridge shipped — it is no longer. The natural convergence point (a tiny `it-pack/port-it` contract package, or RunIt promoting `Contract/Port` interfaces to canonical re-exported definitions) should be scheduled now (see §8-R4).
- **PSR posture varies by design.** RunIt ships PSR adapters in-tree; CommandIt and ConfigureIt keep PSRs behind owned ports (bring-your-own via `suggest:`). Both are coherent; document the policy per package so integrators know what to expect.

### 5.5 Guarded non-edges — couplings deliberately forbidden

The strongest signal of architectural intent is what's enforced *against*:

- **CommandIt → RunIt:** `phparkitect.php` forbids `ItPack\RunIt\*` imports (no allowed dependency) — any import fails the `architecture` task. (32 textual RunIt references in CommandIt are vocabulary IRIs/comments only.)
- **DisenchantIt → RunIt:** `phparkitect.php:238` — `NotDependsOnTheseNamespaces(['ItPack\RunIt\*'])` on Core.
- **ConfigureIt core → everything:** zero runtime deps by ADR; even format libraries are dev-only.
- **CommandIt core → Symfony:** CLI projection shaped like Symfony Console but with zero symfony imports; bridging classes live in `Adapter/CLI`.

This "ports-not-imports" doctrine, enforced in CI, is the reason the *non-bridge* packages remain decoupled despite pervasive conceptual relatedness. **Important refinement (2026-08-31):** the new bridge/integration packages (`AsyncIt`, `ConfigureItRunit`, `DeriveItRunit`, `RunItThirdPartySample`, `HashItAsync`, `CommitItRunit`) are *sanctioned* importers — they exist precisely to consume RunIt (and/or ConfigureIt/DeriveIt/HashIt/AsyncIt) directly, so their sibling `require`s are intentional and not guarded against. The forbid-rules above apply only to the mirror packages (CommandIt, DisenchantIt) and ConfigureIt core, which must stay vendor-neutral. **Gap: the sanctioned-importer boundary must be enforced explicitly via phparkitect** — currently 9 packages (including all new bridges) lack phparkitect rules entirely, so the boundary is documented but not verified in those packages (see §7-R12).

### 5.6 Shared vocabulary — the invisible touchpoint

Root `docs/EXECUTION.md` (mirrored per-package) defines the ecosystem lingua franca: Execution/Context/Activity/Measurement/Observation/Occurrence/Snapshot, with OTel/CloudEvents/PSR positioned strictly as *adapters*. Concrete manifestations: RunIt's TTL ontologies + `w3id.org` IRIs; CommandIt's MODEL.md vocabulary mapping table (Command→`runit:ExecutionDefinition`, Handler→`runit:Activity`, …); versioned occurrence names across packages. Any new family member should start from this vocabulary — it's the cheapest touchpoint and the one already proven **four** times across the live bridges (ConfigureItRunit, DeriveItRunit, CommitItRunit, HashItAsync→AsyncIt).

---

## 6. Cross-cutting conventions & divergences

### What's consistent (strengths)

- **Hexagonal layout everywhere:** `Contract/` + `Adapter/` + `Domain|Core|Application` + `SDK/` facade + factory composition roots, in all seven substantial packages.
- **Immutability-first value objects; enums for closed sets; versioned occurrence names (`*.v1`).**
- **QA culture:** phpstan (often max level / strict) + cs-fixer + architecture tests (deptrac/phparkitect) + infection + phpbench where performance matters (RunIt, CommandIt, CommitIt, DeriveIt).
- **Docs discipline:** README + USER_GUIDE + ADRs + ROADMAP + archived plans; plan→execute→review git history shows the discipline is practiced, not decorative.
- **Path-repo monorepo wiring** for the ConfigureIt family works cleanly today.

### What diverges (liabilities)

| Dimension | Values in play | Packages |
|---|---|---|
| **PHP floor** | `^8.3` / `^8.4` / `>=8.4` / `^8.5` / *undeclared* | ^8.3: Explorer, SchemaDocs, Testing · ^8.4: RunIt, CommandIt, CommitIt, DisenchantIt, **AsyncIt, DeriveItRunit, HashItAsync, RunItThirdPartySample** · >=8.4: DeriveIt · ^8.5: **ConfigureIt core, Psr11, Symfony, ConfigureItRunit** · undeclared: HashIt, LazeIt, FileIt, ExtendIt |
| **Test framework** | Codeception (18) vs PHPUnit (0) | Codeception: all 18 code-bearing packages (all consume Codeception transitively via `just-bases/php-unit-test-base`) · PHPUnit: 0 as direct dep · **Resolved (Phase 1)** |
| **Architecture testing** | phparkitect (9) vs deptrac-archived (8) vs none (5)** | phparkitect: RunIt, CommandIt, CommitIt, ConfigureIt, DeriveIt, HashIt, AsyncIt, HashItAsync, DisenchantIt · deptrac configs archived as `deptrac.archive.yaml` (8 files, no active deptrac) · **none**: ConfigureItExplorer, ConfigureItPsr11, ConfigureItRunit, ConfigureItSchemaDocs, ConfigureItSymfony, ConfigureItTesting, DeriveItRunit, RunItThirdPartySample, CommitItRunit (9 packages) + 3 micro-libs · **Phase 2 INCOMPLETE** — Phase 2.4 (add phparkitect to migrated packages) was not finished (see §7-R12) |
| **Version constraints on sibling deps** | `*@dev` (uniform) | All sibling constraints normalized to `*@dev` · `minimum_stability: stable` · **Resolved (Phase 3)** |
| **Dev-dep baselines** | `just-bases/*` (18) vs ad-hoc (0) | All 18 code-bearing packages on `just-bases/php-unit-test-base` + `just-bases/php-qa-base` (+ `php-obs-dev-base` where applicable) · no ad-hoc `phpstan`/`php-cs-fixer`/`infection` pins · **Resolved (Phase 3)** |
| **QA lane contract** | partial (varies by package) | All packages expose `cs:check`, `cs:fix`, `analyse`. But: **6 packages lack `quality`** (ConfigureIt, DeriveIt, HashItAsync, LazeIt, FileIt, ExtendIt); **9 packages lack `architecture`** (ConfigureItExplorer, ConfigureItPsr11, ConfigureItRunit, ConfigureItSchemaDocs, ConfigureItSymfony, ConfigureItTesting, DeriveItRunit, RunItThirdPartySample, CommitItRunit); **2 packages use `infection` instead of `mutation`** (DeriveIt, HashIt — though both have infection in require-dev); **3 micro-libs have no scripts**. Not fully standardized (§7-R12). |
| **Composer metadata quality** | full (support/scripts/keywords) vs minimal | FileIt description is literally "ItPack File Package"; DeriveIt has no `license` field; HashIt/LazeIt/FileIt/ExtendIt lack `php` floors; LazeIt/ExtendIt/FileIt lack descriptions/license |
| **Autoload layout** | `src/` PSR-4 (standard) vs package-root (`FileIt`) vs undeclared-ish | FileIt is the outlier |

---

## 7. Findings & risks

Severity: 🔴 blocks work · 🟠 will hurt as ecosystem grows · 🟡 hygiene.

- **🔴 R1 — Impossible PHP constraints in the Configuration family.** `configure-it-psr11` and `configure-it-symfony` require `php ^8.5` **and** `it-pack/configure-it *@dev`; Explorer/SchemaDocs/Testing declare `php ^8.3` while depending on a core that requires `^8.5`. As published, the former pair can't combine with stable-only consumers, and the latter trio advertises a floor its dependency invalidates. Needs a single policy (see §8-R2).
- **🟠 R2 — No monorepo tooling.** ~~No root `composer.json`, no workspace script, no split/subtree automation, no shared QA entrypoint. Every cross-package operation is manual; per-package `vendor/` trees (15×) multiply install time and blur "what does the repo depend on". The root `examples/`, `lab/`, `var/` are empty husks.~~ **Resolved (Phase 4):** root `composer.json` (scripts-only) + `Makefile` (`make quality` runs `composer quality` across all 14 packages with `quality`) + `.github/workflows/quality.yml` matrix CI (14 packages × PHP 8.4/8.5). Per-package `vendor/` trees remain; `monorepo-tool`/subtree-split still to be evaluated before first tag. **Gap:** CI matrix excludes 4 code-bearing packages (ConfigureIt, DeriveIt, HashItAsync, CommitItRunit) — see R12.
- **🟠 R3 — Port duplication trigger has FIRED.** Clock/ids/serializer/context ports are independently defined in RunIt and CommandIt (by design, ADR-0001), and the "first shipped bridge" convergence trigger is now met: `AsyncIt`, `ConfigureItRunit`, and `DeriveItRunit` each reimplement the same RunIt port families from the consuming side, while CommandIt/DisenchantIt/CommitIt still carry their own mirrors. The window ADR-0001 bought is closed — schedule the shared-contract extraction (§8-R4) before more bridges copy the interfaces.
- **🟠 R4 — Release-coordination hazard.** Satellites pin core with `*@dev` (resolved in Phase 3). First tagged releases will need a coordinated version dance (core API still evolving — DisenchantIt README openly warns of signature changes until 1.0). Root orchestration (R2, resolved in Phase 4) mitigates; evaluate `monorepo-tool`/subtree-split before first tag.
- **🟠 R5 — Broken root test.** `tests/Unit/SDK/CommitItTest.php` imports `Tests\Unit\Application\{FakeFactory, FakeRepository, RecordingObserver}`, which exist **in `src/CommitIt/tests/Unit/Application/`** but are not autoloaded from the root `tests/` directory. The suite cannot run as committed; worse, it silently suggests root-level testing exists when it doesn't.
- **🟡 R6 — Placeholder limbo.** Eight reserved names with zero documentation of intent (only ReflectIt has a README). Newcomers can't tell roadmaps from accidents.
- **🟡 R7 — Prior-review open items remain open.** From root FINDINGS docs: ExtendIt `ClassInspector` no-op (🟠), FileIt `FileInfo` anemia (🟡), DeriveIt `ClassReflection` static-resolver-registry smell (🟠) — all still present; HashIt items verified fixed.
- **🟡 R8 — `DisenchantIt` carries `rector/rector ^2.0` as a runtime requirement** while its own docs say rewriting is pluggable and Rector is an anticipated *alternative* engine. If Rector isn't exercised by the default pipeline, it belongs in require-dev/suggest — as published it taxes every consumer with a very large transitive tree.
- **🟡 R9 — Metadata gaps** (see divergence table): missing licenses/php-floors/descriptions undermine packagist presentation and the family's evident ambition to publish.
- **🟡 R10 — Doc-path drift.** Older docs reference `packages/<Name>/…` paths; the tree uses `src/<Name>/…`. Cosmetic but confusing for tooling and newcomers.
- **🟠 R11 — "Single cluster" assumption is obsolete; dependency graph has branched.** The 2026-08-23 edition's headline claim (exactly one hard dependency cluster) no longer holds: RunIt is now a direct `require` of `AsyncIt`, `ConfigureItRunit`, `DeriveItRunit`, `CommitItRunit`, and `RunItThirdPartySample`, and an indirect one of `HashItAsync` (via `AsyncIt`). The monorepo now has two primary clusters (ConfigureIt ring; RunIt-with-consumers) plus the HashIt→AsyncIt→RunIt chain. Any tooling, CI matrix, or architecture visualization built on the old single-cluster assumption must be revisited — and the new bridges must be added to the phparkitect verification scope so the sanctioned-importer boundary stays explicit (see §5.5).
- **🟠 R12 — Architecture-tool migration incomplete (Phase 2.4 not done).** 9 of 18 code-bearing packages lack phparkitect: all 8 satellite/bridge packages migrated from PHPUnit in Phase 1 (ConfigureItExplorer, ConfigureItPsr11, ConfigureItRunit, ConfigureItSchemaDocs, ConfigureItSymfony, ConfigureItTesting, DeriveItRunit, RunItThirdPartySample) plus the new CommitItRunit. CONVERSION_NOTES.md `tools/qa/CONVERSION_NOTES.md` explicitly records "Phase 2.4 was NOT completed." These 9 packages have no `architecture` composer script and no `phparkitect.php`, so the hexagonal-layer and ports-not-imports doctrines are **documented but not enforced** in those packages. Additionally, CI quality.yml covers only 14 packages (missing ConfigureIt, DeriveIt, HashItAsync, CommitItRunit — the latter two lack `quality` scripts; the former has `mutation:changed` instead of `mutation`). The `forbidSibling()` helper exists in `tools/qa/phparkitect/forbid-sibling.php` but has not been wired into the satellite/bridge packages.

---

## 8. Recommendations (prioritized)

1. **Fix R1 immediately:** pick one supported range story — suggested policy: cores (`ConfigureIt` `^8.5`, `RunIt` `^8.4`) define the floor; satellites inherit `>=` their core's floor; every package declares a `php` constraint, no exceptions.
2. **Monorepo glue — DONE (Phase 4):** root `composer.json` (scripts-only) + `Makefile` (`make quality`) + `.github/workflows/quality.yml` (14×2 matrix). **Next:** expand CI to cover all packages that define a `quality` script (add ConfigureIt, CommitItRunit), then evaluate `monorepo-builder`/`subtree-split` before first Packagist tag.
3. **Standardize bridge-package naming now.** The 2026-08-23 edition recommended a `run-it-<consumer>` prefix, but the packages actually built use a **`<consumer>-run-it` / `<consumer>-async` suffix** (`configure-it-runit`, `derive-it-runit`, `hash-it-async`) while `commit-it-runit` and `run-it-third-party-sample` follow a slightly different convention. `AsyncIt` is itself `async-it`. The still-unbuilt ones are styled `command-it-run-it` and `run-it-disenchant-it`. Pick one convention — recommended: **`<consumer>-run-it`** for RunIt bridges (matches what shipped and reads naturally as "X's RunIt bridge") and **`<consumer>-async`** for engine bridges — and rename/align the not-yet-built ones before they're published. Also record occurrence-name registries centrally under RunIt's `vocab/` to prevent drift.
4. **Schedule the port-convergence extraction NOW (R3/R11).** The trigger has fired with **four** bridges: `AsyncIt`, `ConfigureItRunit`, `DeriveItRunit`, and `CommitItRunit` each reimplement RunIt's clock/context/serializer/storage port families, joining the CommandIt/DisenchantIt mirrors. Candidate: a tiny `it-pack/port-it`-style shared-contract package, or — more conservatively — RunIt promoting its `Contract/Port` interfaces to canonical definitions that siblings re-export. Do this before more bridges copy the interfaces verbatim.
5. **Complete the architecture-tool migration (R12, new).** Wire phparkitect into the 9 packages migrated from PHPUnit but never given an architecture verifier (ConfigureItExplorer, ConfigureItPsr11, ConfigureItRunit, ConfigureItSchemaDocs, ConfigureItSymfony, ConfigureItTesting, DeriveItRunit, RunItThirdPartySample, CommitItRunit). The `forbidSibling()` helper at `tools/qa/phparkitect/forbid-sibling.php` is ready; each package needs a `phparkitect.php` that calls `layeredArchitecture()` + `forbidSibling()` per §5.5, plus an `architecture` composer script.
6. **Repair or remove the root test** (R5); if kept, it becomes the seed of a root integration suite — give it its own fakes directory and CI lane.
7. **Give every placeholder a one-paragraph README** stating intent/status/owner, or delete the directory (R6). Reserved names without prose are indistinguishable from cruft.
8. **Close out legacy findings** (R7): ExtendIt and FileIt need either a weekend of work or explicit shelving notes; consider whether FileIt/ExtendIt/ReflectIt should merge into one "reflection & inspection" package rather than three micro-repos.
9. **Move `rector/rector` out of DisenchantIt's runtime requires** unless code proves otherwise (R8).
10. **Metadata sweep** (R9): add license/php/description to all 18 code-bearing manifests (was 20); sibling constraint normalization **done (Phase 3)**; align `ConfigureItPsr11` naming (`-psr11` vs `-psr-11` style used elsewhere) — cheap now, expensive after publication.
11. **Document the per-package PSR posture** (in-tree adapters vs bring-your-own) in one place — likely root `EXECUTION.md` — so integrators form correct expectations.
12. **Expand CI coverage.** Add ConfigureIt and CommitItRunit to `.github/workflows/quality.yml`; ensure all packages with `quality` scripts are in the matrix. DeriveIt and HashItAsync lack `quality` scripts — standardize them first (DeriveIt uses `infection` not `mutation`; HashItAsync lacks `mutation` and `security:audit`).

---

## Appendix A — Touchpoint matrix (who touches whom)

Rows reference columns; ● hard dep · ○ planned/soft · ⊘ forbidden · blank none.

| ↓ uses → | RunIt | CommandIt | ConfigureIt | CommitIt | DisenchantIt | DeriveIt | HashIt | AsyncIt |
|---|---|---|---|---|---|---|---|---|
| **RunIt** | — | | | | | | | |
| **CommandIt** | ○ (bridge planned) ⊘ imports | — | | | | | | |
| **ConfigureIt** | | | — | | | | | |
| **ConfigureItPsr11** | | | ● | | | | | |
| **ConfigureItSymfony** | | | ● | | | | | |
| **ConfigureItSchemaDocs** | | | ● | | | | | |
| **ConfigureItTesting** | | | ● | | | | | |
| **ConfigureItExplorer** | | | ● | | | | | |
| **CommitIt** | ○ (observer adapter, now built as CommitItRunit) | | | — | | | | |
| **DisenchantIt** | ○ (bridge planned) ⊘ imports | | ○ (fixtures mirror API) | | — | | | |
| **DeriveIt** | | | | | | — | | |
| **HashIt** | | | | | | | — | |
| **AsyncIt** | ● (canonical ExecutionEngineInterface impl) | | | | | | | — |
| **ConfigureItRunit** | ● | | ● | | | | | |
| **DeriveItRunit** | ● | | | | | ● | | |
| **HashItAsync** | | | | | | | ● | ● |
| **RunItThirdPartySample** | ● (conformance reference) | | | | | | | |
| **CommitItRunit** | ● (observer adapter bridge) | | | ● | | | | |
| **LazeIt / FileIt / ExtendIt** | | | | | | | | |

Reading: there are now **two** ● columns — `ConfigureIt` (its satellite ring) and `RunIt` (its consumer/bridge cluster: `AsyncIt`, `ConfigureItRunit`, `DeriveItRunit`, `RunItThirdPartySample`, plus `HashItAsync` transitively via `AsyncIt`). The ecosystem's designed end-state — a star topology around RunIt (bridges) with ConfigureIt's satellite ring as the second, independent star — is now **partially realized**, not merely planned: ConfigureItRunit/DeriveItRunit/AsyncIt/RunItThirdPartySample are live edges, while CommandIt/DisenchantIt/CommitIt bridges remain ○ (planned, not yet built).

## Appendix B — Evidence index

Key files backing each major claim:

- **Dependency graph (re-run 2026-08-31):** exhaustive sibling `require` + `use ItPack\…` grep across `src/*/composer.json` and `src/*/src/**` (excluding vendor/var) → ConfigureIt-satellite hits (5) **plus** RunIt-consumer hits: `AsyncIt`→`run-it`, `ConfigureItRunit`→`{configure-it,run-it}`, `DeriveItRunit`→`{derive-it,run-it}`, `HashItAsync`→`{hash-it,async-it}`, `RunItThirdPartySample`→`run-it`, `CommitItRunit`→`{commit-it,run-it}`. The "single cluster" claim of the 2026-08-23 edition is obsolete (see R11).
- CommandIt↔RunIt plan: `src/CommandIt/docs/adr/0001-run-it-integration-via-ports.md`, `src/CommandIt/composer.json` (suggest), `src/CommandIt/deptrac.archive.yaml:45-48,83-85` (archived), `src/CommandIt/phparkitect.php` (active), `src/CommandIt/docs/architecture/MODEL.md` (vocab table).
- DisenchantIt guard + plan: `src/DisenchantIt/phparkitect.php:238`, `src/DisenchantIt/docs/architecture/decisions/ADR-0005-runit-exclusion.md`, `src/DisenchantIt/docs/EXECUTION.md`.
- CommitIt observer seam: `src/CommitIt/src/Contract/RepositoryObserver.php`, `src/CommitIt/AGENTS.md:404-410`, `src/CommitIt/README.md`.
- RunIt backbone + vocab: `src/RunIt/README.md`, `src/RunIt/vocab/*.ttl`, `src/RunIt/src/Contract/Port/*`, `docs/EXECUTION.md` (root). **AsyncIt as `ExecutionEngineInterface` impl:** `src/AsyncIt/README.md` (§1 five-tier stack), `src/AsyncIt/src/Contract/…`, `src/AsyncIt/src/Adapter/{Interop,Swoole,Swow,Revolt}`.
- ConfigureIt hub + satellite plan: `src/ConfigureIt/README.md`, `docs/VISION.md` (satellite table), satellite `composer.json` path-repos. **ConfigureItRunit bridge:** `src/ConfigureItRunit/src/{ConfigureItExecutions,RunItResolutionReporter}.php`.
- DeriveItRunit bridge: `src/DeriveItRunit/src/{DeriveItExecutions,RunItDerivationEventSink}.php`.
- HashItAsync bridge: `src/HashItAsync/src/*.php` (1 class) + 125 Codeception tests; depends on `HashIt` + `AsyncIt`.
- RunItThirdPartySample conformance reference: `src/RunItThirdPartySample/src/Context/{ScopeToken,StaticStackContextStorage}.php`, `src/RunItThirdPartySample/src/Publisher/{StreamLinePublisher,BufferedFilePublisher}.php`.
- CommitItRunit bridge: `src/CommitItRunit/src/CommitItRunitAdapter.php` + `src/CommitItRunit/src/Model/{Activity,Occurrence,Snapshot}.php`; requires `it-pack/commit-it` + `it-pack/run-it`; test `src/CommitItRunit/tests/Unit/CommitItRunitAdapterTest.php`.
- HashIt rebuild evidence: `src/HashIt/src/HashAlgorithm.php` (`#[HmacSafe]` catalogue), `src/HashIt/docs/ROADMAP.md` (all phases ✅).
- Broken root test: `tests/Unit/SDK/CommitItTest.php` (imports `Tests\Unit\Application\*` which live in `src/CommitIt/tests/Unit/Application/` but are not autoloaded from root).
- Architecture-tool migration status: `tools/qa/CONVERSION_NOTES.md` (Phase 2.4 not completed; 9 packages still lack phparkitect; 8 deptrac configs archived).
- Legacy findings status: root `docs/FINDINGS_{DEFECTS,SOLID_DRY,HEXAGONAL}.md` vs current tree (HashIt fixed; ExtendIt/FileIt/DeriveIt items open).
