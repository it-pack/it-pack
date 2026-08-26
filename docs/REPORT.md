# it-pack — Global Package Review & Touchpoint Map

> **Date:** 2026-08-23
> **Scope:** All 23 directories under `src/`, plus monorepo-root scaffolding (`docs/`, `tests/`, `examples/`, `lab/`, `var/`).
> **Goal:** A global review of every member package of the it-pack family — purpose, maturity, architecture — and a complete map of their **touchpoints**: edges that exist today, edges that are planned, edges that are *deliberately forbidden*, and the seams (ports) through which future edges should flow.
>
> Related prior art in this repo: [`CODE_REVIEW.md`](./CODE_REVIEW.md) + `FINDINGS_{SOLID_DRY,HEXAGONAL,DEFECTS}.md` (deep-dive on 4 early packages, Jul 2026 — partially outdated: they describe "six sibling mini-packages"; there are now 23). This report supersedes their inventory view and adds the cross-package touchpoint dimension.

---

## 1. Executive summary

1. **The family is 15 real packages + 8 name-reserved placeholders.** Seven directories are completely empty (`AdaptIt`, `CodeIt`, `HookIt`, `PatchIt`, `ProjectIt`, `ShowCaseIt`, `WeakenIt`); `ReflectIt` contains only a README. The 15 real packages span a wide maturity range — from RunIt (~409 source files, 22 ADRs, custom tooling) down to LazeIt (one half-built class).
2. **Today there is exactly ONE hard dependency cluster:** the ConfigureIt hub with five satellites (`configure-it-psr11`, `-symfony`, `-schema-docs`, `-testing`, `-explorer` all `require` `it-pack/configure-it`). No other package imports another sibling. Every other relationship in the monorepo is either *planned* or *explicitly forbidden by architecture tests*.
3. **The gravitational center of the ecosystem is RunIt**, self-described as "the execution model and runtime backbone for the ItPack ecosystem." Three packages (CommandIt, DisenchantIt, CommitIt) have already shaped their domain models around RunIt's execution vocabulary (Execution → Context / Activity / Measurement / Observation / Occurrence / Snapshot) **without importing it**, each exposing dedicated ports awaiting future "bridge" packages (`it-pack/command-it-run-it`, `it-pack/run-it-disenchant-it`, a CommitIt observer adapter, plus a planned `run-it-{context,otel,cloudevents,psr,logger,metrics,tracing}` split family).
4. **The dominant architectural doctrine is hexagonal (ports & adapters), applied consistently** across all seven substantial packages: `Contract/` (ports) + `Adapter/` (technology) + `Core|Application|Domain` + `SDK/` facade + composition-root factory. Architecture tests enforce boundaries (`deptrac` in 6 packages, `phparkitect` in DisenchantIt), including rules that *forbid* sibling imports (e.g., CommandIt and DisenchantIt both forbid `ItPack\RunIt\*`).
5. **Main risks are ecosystem-level, not per-package:** divergent PHP floors (satellites declare `^8.3` while the core they depend on requires `^8.5`), version-constraint drift (`*` vs `*@dev`), a test-framework split (Codeception vs PHPUnit), duplicated context/clock/serializer port definitions that will need a convergence strategy once the RunIt bridges land, and missing monorepo tooling (no root `composer.json`, no release/split automation).

---

## 2. Method & evidence base

This review was assembled from converging evidence rather than any single source:

| Evidence | How gathered |
|---|---|
| **Manifests** | All 15 `composer.json` read in full (names, deps, suggests, autoload, scripts, repositories). |
| **Actual code edges** | Repo-wide grep for `ItPack\<Ns>` references per package, excluding `vendor/` and `var/`; every hit manually classified as import / comment / config rule. |
| **Docs & decisions** | READMEs; root `EXECUTION.md` (execution-model manifesto); ADRs (RunIt ×22, ConfigureIt ×10, CommandIt ×3, CommitIt ×5, DeriveIt ×4, DisenchantIt ×6); ROADMAPs; `AGENTS.md` files. |
| **Prior reviews** | Root `CODE_REVIEW.md`, `FINDINGS_*.md`, and per-package `REVIEW.md`s — re-checked against current tree to classify findings as addressed/open. |
| **Cross-cutting conventions** | Scripted comparison of PHP constraints, test frameworks, QA scripts, dev-dependency sets across all manifests. |

Note on tooling: a full static knowledge-graph build was considered but skipped deliberately — the repo carries 15 in-tree `vendor/` trees which would dominate any automated extraction; the targeted greps above give exact, verifiable edge data instead. Where this report cites numbers (file counts, test counts), they come from scripted counts excluding `vendor/`.

---

## 3. Inventory at a glance

Maturity legend: 🟢 mature/tooling-complete · 🟡 functional but young · 🟠 embryonic · ⚪ placeholder.

| # | Dir | Composer name | One-line purpose | Scale (src .php excl. vendor) | Tests | Status |
|---|-----|---------------|------------------|------------------------------|-------|--------|
| 1 | `RunIt` | `it-pack/run-it` | Execution model & runtime backbone of the ecosystem | ~409 | 136 files, 9 suites (incl. Conformance/Concurrency/Architecture) | 🟢 initial development, heavily tooled |
| 2 | `ConfigureIt` | `it-pack/configure-it` | Framework-agnostic configuration SDK & resolution engine | 191 | 70 files (Codeception) | 🟢 "Phase 8" |
| 3 | `DeriveIt` | `it-pack/derive-it` | Derives clean interfaces/adapters from class hierarchies (anti type-leakage) | 48 | 79 files, 113 unit tests, MSI 86% | 🟢 ready to tag v0.1.0 |
| 4 | `CommitIt` | `it-pack/commit-it` | Vendor-neutral VCS API/SDK (git first) | 37 | 31 files (Acceptance+Functional) | 🟢 milestones M0–M8 complete |
| 5 | `DisenchantIt` | `it-pack/disenchant-it` | Finds magic scalars, promotes them to constants/enums | 131 | 50 files (Acceptance+Functional) | 🟡 maturing, pre-1.0 |
| 6 | `CommandIt` | `it-pack/command-it` | CQRS-style command definition/dispatch library | 98 | 49 files (4 suites) | 🟡 milestones M0–M8 done, bridge pending |
| 7 | `HashIt` | `it-pack/hash-it` | Hexagonal hashing utility (hash/HMAC/stream/bulk) | small (single-digit classes + adapters) | 37 methods / 168 assertions | 🟢 rebuilt as reference architecture |
| 8 | `ConfigureItExplorer` | `it-pack/configure-it-explorer` | Read-only web explorer + graph query API over resolved config | 12 | 2 (PHPUnit) | 🟡 dev-server scope |
| 9 | `ConfigureItSchemaDocs` | `it-pack/configure-it-schema-docs` | Markdown + JSON-Schema emission from Nette schema definitions | 5 | 5 (PHPUnit) | 🟡 |
| 10 | `ConfigureItTesting` | `it-pack/configure-it-testing` | Fakes + assertion trait for ConfigureIt consumers | 6 | 6 (PHPUnit) | 🟡 |
| 11 | `ConfigureItSymfony` | `it-pack/configure-it-symfony` | Symfony bundle/DI extension exposing resolved snapshot | 4 | 2 (PHPUnit) | 🟡 |
| 12 | `ConfigureItPsr11` | `it-pack/configure-it-psr11` | PSR-11 container view of resolved snapshot | 3 | 1 (PHPUnit) | 🟡 minimal-but-complete |
| 13 | `LazeIt` | `it-pack/laze-it` | Utility API for PHP native lazy objects | 1 (half-built) | none | 🟠 |
| 14 | `FileIt` | `it-pack/file-it` | File metadata value object(s) | 1 (`FileInfo`) | none | 🟠 anemic |
| 15 | `ExtendIt` | `it-pack/extend-it` | "Experimental" class-inspection toolkit | 1 (no-op stub) | none | 🟠 stub |
| 16 | `ReflectIt` | — *(none)* | Reflection extension library | 0 (README only) | — | ⚪ reserved |
| 17–23 | `AdaptIt` `CodeIt` `HookIt` `PatchIt` `ProjectIt` `ShowCaseIt` `WeakenIt` | — *(none)* | — | 0 | — | ⚪ reserved |

Shared authorship/branding: all packages authored by "tidal" (`timomichna@yahoo.de`), GitHub org `it-pack`, keywords `it-pack`/`itpack`, MIT license where declared (see §7-R9 for gaps).

---

## 4. Family profiles

The family organizes naturally into six clusters plus the placeholder reserve.

### 4.1 Execution backbone — `RunIt`

**Purpose.** Models *execution itself*, decoupled from any tracing/logging/metrics/events vendor. Canonical model: `Execution → Context, Activity, Measurement, Observation, Occurrence, Snapshot`. Motto: *"Everything happens within an execution."* Semantics are formalized in OWL/SKOS vocabularies (`vocab/run-it.ttl`, `vocab/run-it-taxonomy.ttl`) with stable IRIs under `w3id.org/itpack/runit#`.

**Shape.** Strict layering (Domain → Contract, Application, SDK, Adapter), enforced by `deptrac.yaml`. Key abstractions:

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

Vendor-neutral VCS API/SDK with git (via `czproject/git-php`) as first adapter, isolated in `src/Adapter/Git`. Contracts: `RepositoryFactory`, `Repository`, `WorkingTree`, and the integration-relevant **`RepositoryObserver`** lifecycle port (`onStart/onSuccess/onFailure`) whose PHPDoc explicitly maps to RunIt's Activity/Occurrence/Observation triad without coupling. Static facade `CommitIt::open/init/clone(?RepositoryObserver)` composes `ObservedRepository` decorators. Milestones M0–M8 marked complete (2026-08-22); remaining direction is ecosystem adapters (a `commit-it-runit` observer adapter, PSR-3 logging observer) rather than core features. Root `tests/Unit/SDK/CommitItTest.php` targets its SDK facade but is currently broken (see §7-R5).

### 4.6 Micro-libraries — `HashIt`, `LazeIt`, `FileIt`, `ExtendIt`

- **`HashIt`** — rebuilt (per root FINDINGS docs) into the family's *reference implementation* of the target architecture: owned ports (`HashContextInterface`, `ContextFactoryInterface`, `FilepathInterface`, `HashInterface`, `HmacHasherInterface`), native adapters confined to `src/Adapter/`, `NativeHashIt` composition root, immutable `Hash`/`HashBuilder`, stream/bulk helpers, timing-safe equality, and an elegant **attribute-driven algorithm catalogue** (`#[HmacSafe]` on enum cases, consumed reflectively — replacing a thrice-encoded list). 37 tests/168 assertions, phpstan-clean. Gap: composer manifest lacks a `php` floor (only `ext-hash`) despite using modern PHP.
- **`LazeIt`** — intended convenience SDK for PHP native lazy objects (`newLazyGhost`/`newLazyProxy` appear in `var/lab/` experiments ⇒ de facto PHP ≥ 8.4). Reality: one half-built `InitializerValidator` (dead private method, unreachable branches), no tests, **no `php` constraint declared**.
- **`FileIt`** — a single anemic `FileInfo` value object (9 untyped public properties, `int|false` lies). Autoload maps PSR-4 `"ItPack\\FileIt\\": ""` to the package *root* while the class declares namespace `ItPack\FileIt` — mapping and layout disagree.
- **`ExtendIt`** — README says "Experimental Status"; sole class `ClassInspector::inspect()` computes methods and discards them (documented no-op). Rest is `var/lab/` scratch work incl. Dagger experiments. No `php` floor, no license field.

### 4.7 Placeholder reserve — 8 reserved names

`ReflectIt` (README only: *"Extends native PHP Reflection with additional features"*), and empty `AdaptIt`, `CodeIt`, `HookIt`, `PatchIt`, `ProjectIt`, `ShowCaseIt`, `WeakenIt`. The names teleograph intent (adapter utilities, code generation, hooks, patching, project scaffolding, showcase, weakening/mocking?) but nothing documents scope, priority, or owner. They cost nothing but also communicate nothing — see §8-R6.

---

## 5. Touchpoint map

This is the heart of the review. Four categories: hard edges (today), soft edges (today, non-code), planned edges (documented future), and guarded non-edges (deliberately forbidden).

### 5.1 Hard edges — actual dependencies today

```mermaid
graph TD
    subgraph "Configuration family"
      CI[it-pack/configure-it]
      PS11[configure-it-psr11] --> CI
      SY[configure-it-symfony] --> CI
      SD[configure-it-schema-docs] --> CI
      TE[configure-it-testing] --> CI
      EX[configure-it-explorer] --> CI
    end
    RI[it-pack/run-it]
    CO[it-pack/command-it -.->|"planned bridge<br/>command-it-run-it"| RI
    DI[it-pack/disenchant-it -.->|"planned bridge<br/>run-it-disenchant-it"| RI
    CM[it-pack/commit-it -.->|"planned observer adapter"| RI
```

The complete list of inter-package `composer` requirements:

| Dependent | Dependency | Constraint | Wiring |
|---|---|---|---|
| ConfigureItPsr11 | it-pack/configure-it | `*@dev` | path repo `../ConfigureIt` |
| ConfigureItSymfony | it-pack/configure-it | `*@dev` | path repo `../ConfigureIt` |
| ConfigureItSchemaDocs | it-pack/configure-it | `*` | symlinked path repo |
| ConfigureItTesting | it-pack/configure-it | `*` | symlinked path repo |
| ConfigureItExplorer | it-pack/configure-it | `*` | symlinked path repo |

That's all. Verified by exhaustive `ItPack\…` import grep across all packages (excluding `vendor/`, `var/`): no other sibling-to-sibling `use` statement exists anywhere. In particular: RunIt contains **zero** mentions of CommandIt/CommitIt/DisenchantIt; CommandIt references RunIt only in PHPDoc vocabulary IRIs and deptrac forbid-rules; DeriveIt/HashIt/LazeIt/FileIt/ExtendIt reference nobody.

### 5.2 Soft edges — present today, not dependencies

1. **Vocabulary adoption (RunIt → CommandIt, DisenchantIt, CommitIt).** All three model executions with RunIt's noun set and versioned occurrence names (`command.received.v1`, `disenchant.it.magic_value.detected.v1`) and carry RunIt-aligned marker types (`ExecutionArtifactInterface`, `ExecutionRecorderInterface` + `Execution/*` VOs, `RepositoryObserver`). These compile-time-independent mirrors are the *contract* the future bridges will bind to.
2. **Fixture mirroring (DisenchantIt ↔ ConfigureIt).** `src/DisenchantIt/var/test-code/` contains realistic sample code written against ConfigureIt-style APIs (sources, readers, loaders, snapshots, graphs) used as scanner input. Not a dependency — but a maintenance coupling: ConfigureIt API renames silently stale these fixtures (they're outside CI compile paths since they're never autoloaded by Disenchanter itself).
3. **Monorepo-root test targeting CommitIt.** `tests/Unit/SDK/CommitItTest.php` exercises CommitIt's SDK facade from the repo root — evidence a root-level integration-test layer was started, though it's currently broken (§7-R5).
4. **Documentation cross-references.** Root `EXECUTION.md` walks a ConfigureIt resolution end-to-end as its worked example and announces the `run-it-*` family; per-package EXECUTION/USER_GUIDE docs repeatedly reference the shared model. Docs are currently the densest connective tissue in the repo.

### 5.3 Planned edges — the documented future graph

Aggregated from ADRs, ROADMAPs, VISION docs, `suggest:` sections, and root `EXECUTION.md`:

| Planned package | Connects | Evidence |
|---|---|---|
| `it-pack/command-it-run-it` | CommandIt ports → RunIt pipeline (persist/transport executions) | CommandIt `composer.suggest`, ADR-0001, MODEL.md |
| `it-pack/run-it-disenchant-it` | DisenchantIt recorder → RunIt | DisenchantIt ADR-0005 + archive plan; RunIt ADR-016 |
| CommitIt RunIt observer adapter (name TBD, cf. `commit-it-runit`) | `RepositoryObserver` → RunIt occurrences | CommitIt README/AGENTS/EXECUTION docs |
| `run-it-context`, `run-it-otel`, `run-it-cloudevents`, `run-it-psr`, `run-it-logger`, `run-it-metrics`, `run-it-tracing` | RunIt splits per concern | Root EXECUTION.md:436–452, ConfigureIt EXECUTION.md, CommitIt docs |
| `configure-it-{env,yaml,toml,neon,frontmatter,schema,di,vault,tableschema}` | Format/integration satellites off ConfigureIt | ConfigureIt VISION.md "Optional Satellite Packages", AGENTS.md |
| `configure-it-laravel` | Laravel bundle | ConfigureIt archive roadmap T8.1.3, CHANGELOG (deferred) |
| External consumers: *ChaosCockpit, AExO, HoppaDB, Spindle* | consume the shared execution vocabulary | Root EXECUTION.md |

Naming inconsistency worth fixing before publication: bridge packages are variously styled `command-it-run-it`, `run-it-disenchant-it`, `runit` (CommitIt docs) — pick one convention (recommendation §8-R3).

### 5.4 Port inventories — the integration seams

Every substantial package publishes a deliberate set of seams. These are the touchpoints third parties (and sibling bridge packages) are meant to program against:

| Package | Primary integration seams (non-exhaustive) |
|---|---|
| **RunIt** | `Contract/Port/*`: MonotonicClock, IdentifierGenerator, ContextStorage+ContextToken, RuntimeContextDetector, Serializer, ArtifactPublisher, SnapshotStore/Signer · Runner/Engine/LifecycleCoordinator · Measurement/Observation/Occurrence/Snapshot recorders · ServerExecutionMiddleware (PSR-15 shape) |
| **CommandIt** | `Contract/Port/*`: ExecutionReporter, ExecutionContextProvider+ContextToken, CommandExecutionStorage, RuntimeContextDetector, Clock, IdentifierGenerator, Serializer, RedactionPolicy · `ExecutionArtifactInterface` (alignment marker) · `CommandBusInterface`, `CommandMiddlewareInterface` |
| **ConfigureIt** | `Contract/*`: Source/Reader/Loader(+Factory)/Processor/Schema/Resolver/Snapshot(+Factory)/Graph/Cache/Decrypter/SecretSource/Watcher · `Wiring/ConfigurationWiringInterface` · `Contract/Execution/*` |
| **CommitIt** | `RepositoryFactory`, `Repository`, `WorkingTree`, `RepositoryObserver` (lifecycle → RunIt triad) |
| **DisenchantIt** | Detector/MagicLiteralVisitor/CandidateFilter/Grouper · ConstantPlacementStrategy(+Factories) · `RewriterInterface` (Rector swap anticipated) · SemConvCollector/Store/AliasRewriter · NamePolicy/DictionaryNameResolver · `ExecutionRecorderInterface`, VerificationObserver, SarifRecorder |
| **DeriveIt** | `RecursiveResolverInterface` (+Class/Interface/Trait variants), `DeriverInterface`, `AdapterGeneratorInterface`, `FamilyDeriverInterface`, `MethodFilterInterface` |
| **HashIt** | `HashContextInterface`, `ContextFactoryInterface`, `FilepathInterface`, `HmacHasherInterface`, `HashInterface` |
| **ConfigureItExplorer** | `GraphProviderInterface` |

Two observations:

- **Duplication is intentional — for now.** Clock, identifier-generator, serializer, context-storage/token, and runtime-context-detector ports exist independently in both RunIt and CommandIt (near-isomorphic signatures). ADR-0001 explicitly rejected a premature shared base package. This is defensible until the first bridge exists, but the moment `command-it-run-it` is written, someone must map two nearly identical port families onto one runtime — that's the natural trigger point to extract a shared contract (rule of three: CommandIt + CommitIt observer + DisenchantIt recorder would make it three independent reimplementations of the same idea).
- **PSR posture varies by design.** RunIt ships PSR adapters in-tree; CommandIt and ConfigureIt keep PSRs behind owned ports (bring-your-own via `suggest:`). Both are coherent; document the policy per package so integrators know what to expect.

### 5.5 Guarded non-edges — couplings deliberately forbidden

The strongest signal of architectural intent is what's enforced *against*:

- **CommandIt → RunIt:** `deptrac.yaml` defines a `RunIt` layer (`/^ItPack\\RunIt/`) that appears in no "allowed" list — any import fails the `architecture` task. (32 textual RunIt references in CommandIt are vocabulary IRIs/comments only.)
- **DisenchantIt → RunIt:** `phparkitect.php:238` — `NotDependsOnTheseNamespaces(['ItPack\RunIt\*'])` on Core.
- **ConfigureIt core → everything:** zero runtime deps by ADR; even format libraries are dev-only.
- **CommandIt core → Symfony:** CLI projection shaped like Symfony Console but with zero symfony imports; bridging classes live in `Adapter/CLI`.

This "ports-not-imports" doctrine, enforced in CI, is the reason the monorepo has exactly one dependency cluster today despite pervasive conceptual relatedness.

### 5.6 Shared vocabulary — the invisible touchpoint

Root `docs/EXECUTION.md` (mirrored per-package) defines the ecosystem lingua franca: Execution/Context/Activity/Measurement/Observation/Occurrence/Snapshot, with OTel/CloudEvents/PSR positioned strictly as *adapters*. Concrete manifestations: RunIt's TTL ontologies + `w3id.org` IRIs; CommandIt's MODEL.md vocabulary mapping table (Command→`runit:ExecutionDefinition`, Handler→`runit:Activity`, …); versioned occurrence names across packages. Any new family member should start from this vocabulary — it's the cheapest touchpoint and the one already proven three times.

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
| **PHP floor** | `^8.3` / `^8.4` / `>=8.4` / `^8.5` / *undeclared* | ^8.3: Explorer, SchemaDocs, Testing · ^8.4: RunIt, CommandIt, CommitIt, DisenchantIt · >=8.4: DeriveIt · ^8.5: **ConfigureIt core, Psr11, Symfony** · undeclared: HashIt, LazeIt, FileIt, ExtendIt |
| **Test framework** | Codeception (7) vs PHPUnit (5) | Codeception: RunIt, CommandIt, CommitIt, ConfigureIt, DeriveIt, DisenchantIt, HashIt · PHPUnit: all 5 satellites |
| **Architecture testing** | deptrac (6) vs phparkitect (1) vs none (satellites, HashIt) | — |
| **Version constraints on sibling deps** | `*` vs `*@dev` | Psr11/Symfony use `*@dev`; Explorer/SchemaDocs/Testing use `*` |
| **Dev-dep baselines** | `just-bases/{php-unit-test-base,php-qa-base,…}` (8 pkgs) vs ad-hoc phpunit/phpstan/cs-fixer (satellites, DisenchantIt) | — |
| **Composer metadata quality** | full (support/scripts/keywords) vs minimal | FileIt description is literally "ItPack File Package"; DeriveIt has no `license` field; HashIt/LazeIt/FileIt/ExtendIt lack `php` floors; LazeIt/ExtendIt/FileIt lack descriptions/license |
| **Autoload layout** | `src/` PSR-4 (standard) vs package-root (`FileIt`) vs undeclared-ish | FileIt is the outlier |

---

## 7. Findings & risks

Severity: 🔴 blocks work · 🟠 will hurt as ecosystem grows · 🟡 hygiene.

- **🔴 R1 — Impossible PHP constraints in the Configuration family.** `configure-it-psr11` and `configure-it-symfony` require `php ^8.5` **and** `it-pack/configure-it *@dev`; Explorer/SchemaDocs/Testing declare `php ^8.3` while depending on a core that requires `^8.5`. As published, the former pair can't combine with stable-only consumers, and the latter trio advertises a floor its dependency invalidates. Needs a single policy (see §8-R2).
- **🟠 R2 — No monorepo tooling.** No root `composer.json`, no workspace script, no split/subtree automation, no shared QA entrypoint. Every cross-package operation is manual; per-package `vendor/` trees (15×) multiply install time and blur "what does the repo depend on". The root `examples/`, `lab/`, `var/` are empty husks.
- **🟠 R3 — Port duplication has a shelf life.** Clock/ids/serializer/context ports are independently defined in RunIt and CommandIt today (by design, ADR-0001). With CommitIt's observer and DisenchantIt's recorder also modeling the same concepts, the ecosystem is one bridge away from maintaining 3–4 parallel copies. Define the convergence trigger *now* (first shipped bridge) rather than debating it mid-integration.
- **🟠 R4 — Release-coordination hazard.** Satellites pin core with `*` / `*@dev` and `minimum-stability: dev`. First tagged releases will need a coordinated version dance (core API still evolving — DisenchantIt README openly warns of signature changes until 1.0). Without tooling (R2), expect broken satellite releases.
- **🔴 R5 — Broken root test.** `tests/Unit/SDK/CommitItTest.php` imports `Tests\Unit\Application\{FakeFactory, FakeRepository, RecordingObserver}`, which exist nowhere. The suite cannot run as committed; worse, it silently suggests root-level testing exists when it doesn't.
- **🟡 R6 — Placeholder limbo.** Eight reserved names with zero documentation of intent (only ReflectIt has a README). Newcomers can't tell roadmaps from accidents.
- **🟡 R7 — Prior-review open items remain open.** From root FINDINGS docs: ExtendIt `ClassInspector` no-op (🟠), FileIt `FileInfo` anemia (🟡), DeriveIt `ClassReflection` static-resolver-registry smell (🟠) — all still present; HashIt items verified fixed.
- **🟡 R8 — `DisenchantIt` carries `rector/rector ^2.0` as a runtime requirement** while its own docs say rewriting is pluggable and Rector is an anticipated *alternative* engine. If Rector isn't exercised by the default pipeline, it belongs in require-dev/suggest — as published it taxes every consumer with a very large transitive tree.
- **🟡 R9 — Metadata gaps** (see divergence table): missing licenses/php-floors/descriptions undermine packagist presentation and the family's evident ambition to publish.
- **🟡 R10 — Doc-path drift.** Older docs reference `packages/<Name>/…` paths; the tree uses `src/<Name>/…`. Cosmetic but confusing for tooling and newcomers.

---

## 8. Recommendations (prioritized)

1. **Fix R1 immediately:** pick one supported range story — suggested policy: cores (`ConfigureIt`, `RunIt`) define the floor (`^8.5` / `^8.4` respectively); satellites inherit `>=` their core's floor; every package declares a `php` constraint, no exceptions.
2. **Add minimal monorepo glue:** root `composer.json` (even scripts-only) + a Makefile/script that runs per-package `quality` lanes; evaluate `monorepo-tool`/subtree-split before the first Packagist tag (closes most of R2/R4).
3. **Standardize bridge-package naming now** (`<consumer>-run-it` vs `run-it-<consumer>` — recommend `run-it-command-it`, `run-it-disenchant-it`, `run-it-commit-it` for sortability) and record occurrence-name registries centrally under RunIt's `vocab/` to prevent drift.
4. **Schedule the port-convergence review** for the moment `command-it-run-it` starts (R3): candidate extraction is a tiny `it-pack/port-it`-style contract package or — more conservatively — RunIt promoting its `Contract/Port` interfaces to the canonical definitions siblings re-export.
5. **Repair or remove the root test** (R5); if kept, it becomes the seed of a root integration suite — give it its own fakes directory and CI lane.
6. **Give every placeholder a one-paragraph README** stating intent/status/owner, or delete the directory (R6). Reserved names without prose are indistinguishable from cruft.
7. **Close out legacy findings** (R7): ExtendIt and FileIt need either a weekend of work or explicit shelving notes; consider whether FileIt/ExtendIt/ReflectIt should merge into one "reflection & inspection" package rather than three micro-repos.
8. **Move `rector/rector` out of DisenchantIt's runtime requires** unless code proves otherwise (R8).
9. **Metadata sweep** (R9): add license/php/description to all 15 manifests; normalize sibling constraints to one style; align `ConfigureItPsr11` naming (`-psr11` vs `-psr-11` style used elsewhere) — cheap now, expensive after publication.
10. **Document the per-package PSR posture** (in-tree adapters vs bring-your-own) in one place — likely root `EXECUTION.md` — so integrators form correct expectations.

---

## Appendix A — Touchpoint matrix (who touches whom)

Rows reference columns; ● hard dep · ○ planned/soft · ⊘ forbidden · blank none.

| ↓ uses → | RunIt | CommandIt | ConfigureIt | CommitIt | DisenchantIt | DeriveIt | HashIt |
|---|---|---|---|---|---|---|---|
| **RunIt** | — | | | | | | |
| **CommandIt** | ○ (bridge planned) ⊘ imports | — | | | | | |
| **ConfigureIt** | | | — | | | | |
| **ConfigureItPsr11** | | | ● | | | | |
| **ConfigureItSymfony** | | | ● | | | | |
| **ConfigureItSchemaDocs** | | | ● | | | | |
| **ConfigureItTesting** | | | ● | | | | |
| **ConfigureItExplorer** | | | ● | | | | |
| **CommitIt** | ○ (observer adapter) | | | — | | | |
| **DisenchantIt** | ○ (bridge planned) ⊘ imports | | ○ (fixtures mirror API) | | — | | |
| **DeriveIt** | | | | | | — | |
| **HashIt** | | | | | | | — |
| **LazeIt / FileIt / ExtendIt** | | | | | | | |

Reading: the only ● column is ConfigureIt. The ecosystem's designed end-state is a star topology around RunIt (bridges) with ConfigureIt's satellite ring as the second, independent star.

## Appendix B — Evidence index

Key files backing each major claim:

- Single-cluster dependency proof: `grep -rE 'use ItPack\\' src/*/ --include='*.php'` (excluding vendor/var) → only satellite→core hits.
- CommandIt↔RunIt plan: `src/CommandIt/docs/adr/0001-run-it-integration-via-ports.md`, `src/CommandIt/composer.json` (suggest), `src/CommandIt/deptrac.yaml:45-48,83-85`, `src/CommandIt/docs/architecture/MODEL.md` (vocab table).
- DisenchantIt guard + plan: `src/DisenchantIt/phparkitect.php:238`, `src/DisenchantIt/docs/architecture/decisions/ADR-0005-runit-exclusion.md`, `src/DisenchantIt/docs/EXECUTION.md`.
- CommitIt observer seam: `src/CommitIt/src/Contract/RepositoryObserver.php`, `src/CommitIt/AGENTS.md:404-410`, `src/CommitIt/README.md`.
- RunIt backbone + vocab: `src/RunIt/README.md`, `src/RunIt/vocab/*.ttl`, `src/RunIt/src/Contract/Port/*`, `docs/EXECUTION.md` (root).
- ConfigureIt hub + satellite plan: `src/ConfigureIt/README.md`, `docs/VISION.md` (satellite table), satellite `composer.json` path-repos.
- HashIt rebuild evidence: `src/HashIt/src/HashAlgorithm.php` (`#[HmacSafe]` catalogue), `src/HashIt/docs/ROADMAP.md` (all phases ✅).
- Broken root test: `tests/Unit/SDK/CommitItTest.php` (imports of nonexistent `Tests\Unit\Application\*`).
- Legacy findings status: root `docs/FINDINGS_{DEFECTS,SOLID_DRY,HEXAGONAL}.md` vs current tree (HashIt fixed; ExtendIt/FileIt/DeriveIt items open).
