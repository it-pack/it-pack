# ConfigureIt — Additional Features Roadmap

> Companion to `docs/ADDITIONAL_FEATURES_PLAN.md`. This document turns the
> proposed features into an **in-depth, step-by-step roadmap**: milestones →
> epics → individual tasks, with dependencies, owners of responsibility
> (Core vs. companion package), and exit criteria.
>
> Guiding constraints (from AGENTS.md / ADRs): every feature enters as a
> **contract + adapter**; Core never depends on infrastructure; snapshots stay
> **immutable**; typing stays **strong**; behaviour is **opt-in** (no breaking
> changes to existing callers).

---

## 0. How to read this roadmap

- **Milestone (Mx)** — a shippable, independently valuable increment with a green
  quality gate. Sequenced by the prioritization matrix in the Plan (§10).
- **Epic (Ex.y)** — a coherent slice of one feature.
- **Task (Tx.y.z)** — a single, reviewable unit of work (contract, adapter,
  wiring, test, doc). Effort: **S** (<0.5d), **M** (~1d), **L** (>2d).
- **Surface** — `Core` (this package) or `Companion` (separate `configure-it-*`
  package).

**Cross-cutting Definition of Done (every task):** PHPStan level 9 clean,
`php-cs-fixer` clean, unit tests (offline, no SDK/cloud) green, CHANGELOG entry,
ROADMAP/ADDITIONAL_FEATURES_PLAN cross-references updated.

---

## M1 — Typed Consumption  *(Plan §2 — highest leverage)* ✅ DONE

**Status:** implemented 2026-08-21. `TypeException`, `ScalarCaster`, typed accessors
on `ConfigurationSnapshotInterface`/`ConfigurationSnapshot`, `TypedSnapshot` freeze-time
validator, `DeclaresScalarTypesInterface`, and `NativeConfigurationSchema` walker.
Suite green: 165 tests / 380 assertions, PHPStan level 9, php-cs-fixer clean.

**Objective:** eliminate `mixed` at the consumption edge; make mis-typed config a
thrown `TypeException`, not a silent bug.

### Epic E2.1 — Typed accessors (Core, additive) ✅ DONE
- [x] **T1.1.1 (S)** Add `TypeException` (`src/Exception/TypeException.php`); translate failures, never leak vendor types.
- [x] **T1.1.2 (M)** Extend `ConfigurationSnapshotInterface` with `int/bool/string/float/array(string)` + `exists(string)`. Default impl throws `TypeException` on mismatch or missing key.
- [x] **T1.1.3 (M)** Implement accessors in the concrete `ConfigurationSnapshot` (wrap `get()` + `is_scalar`/`is_array` coercion guards).
- [x] **T1.1.4 (M)** Tests: `TypedAccessorTest` (success paths + each failure mode + missing key).

### Epic E2.2 — Schema-bound TypedSnapshot (Core, additive) ✅ DONE
- [x] **T1.2.1 (M)** Add `TypedSnapshot` decorator implementing `ConfigurationSnapshotInterface`, constructed by the resolver when a `ConfigurationSchemaInterface` was supplied; validates reads against schema-expected scalar types at freeze time (one pass).
- [x] **T1.2.2 (M)** `ConfigurationFactory`/`ConfigurationResolver` mint `TypedSnapshot` instead of base snapshot **only when** `schema !== null` (opt-in; unchanged behaviour otherwise).
- [x] **T1.2.3 (M)** Tests: `TypedSnapshotTest` (schema-present enforces types; schema-absent falls back to base behaviour).

### Epic E2.3 — Codegen DTO (Companion: `configure-it-codegen`) ✅ DONE ✅ DONE
- [x] **T1.3.1 (L)** `configure-it-codegen`: read a `Nette\Schema\Expect` definition, emit a readonly `AppConfig` DTO with typed getters + PHPDoc.
- [x] **T1.3.2 (M)** Map `Expect::string/int/bool/float/structure/array` → PHP types; nested structures → nested readonly DTOs.
- [x] **T1.3.3 (M)** Adapter that hydrates the DTO from a `ConfigurationSnapshotInterface`.
- [x] **T1.3.4 (M)** Tests + an example `examples/typed-config.php` showing generated-DTO consumption.

**M1 Exit:** `Snapshot::int('port')` works; schema-backed snapshots enforce types; codegen produces a compilable DTO; gates green.

---

## M2 — Layered Composition & Environments  *(Plan §3)* ✅ DONE

**Objective:** replace implicit "last source wins" with explicit, explainable
layering + conditional includes.

### Epic E3.1 — Layer port + builder (Core) ✅ DONE
- [x] **T2.1.1 (M)** `Contract/ConfigurationLayerInterface` (`sources()`, `precedence(): int`, `match(ExecutionContextInterface): bool`).
- [x] **T2.1.2 (M)** `Core/ConfigurationLayer` default impl (sources list + precedence + optional `match` predicate; `null` predicate = always match).
- [x] **T2.1.3 (M)** `ConfigurationBuilder::addLayer()`; resolution orders layers by precedence *before* the pipeline (no layers ⇒ identical to today). When layers are registered, flat `addSource()` sources are replaced by the layer-derived set.
- [x] **T2.1.4 (M)** Tests: `LayerOrderTest` (precedence regardless of insertion order, env-gated layer skipped when `match` false, env-gated layer applied when `match` true, backward-compat with no layers).

### Epic E3.2 — Conditional includes (Core) ✅ DONE
- [x] **T2.2.1 (M)** Extend `IncludeProcessor` directive to `@include <path> @if <condition>`; condition grammar = `env=prod | tenant=acme | profile=canary` evaluated against `ExecutionContext` (`@if` is optional — no suffix keeps today's behaviour).
- [x] **T2.2.2 (M)** Parser for the directive suffix (`IncludeProcessor::parseDirective`); reuses `ExecutionContext::environment()` / `attribute()` for matching; unrecognized conditions are a `ResolutionException`.
- [x] **T2.2.3 (M)** Tests: `ConditionalIncludeTest` (included/excluded per context for env/tenant/profile, invalid condition, and multi-pass via double-add); plus a builder integration test verifying the execution context is injected into the processor.

### Epic E3.3 — Layer attribution in provenance (Core) ✅ DONE
- [x] **T2.3.1 (M)** Stamp layer id onto nodes during normalization (extended `DocumentNormalizer` to accept a layer id; `Provenance` and `ConfigurationDocument` carry `layerId`; the builder wraps each layer's sources in `LayeredSource` so the resolver can stamp it).
- [x] **T2.3.2 (M)** Inspector/CLI (`explain`) shows the winning layer (added a `layer` line to `explainKey` and a `LAYER` column to the `renderTable` of `ConfigurationInspector`).
- [x] **T2.3.3 (M)** Tests: `LayerProvenanceTest` (layer id stamped onto node provenance, no layer id when no layers registered, winning layer visible in inspector).

**M2 Exit:** `addLayer()` ordering + `@if` includes work and are visible in provenance/inspector; no behaviour change when unused. Verified end-to-end by `tests/Unit/Core/M2ExitTest.php` (ordering + conditional include + layer attribution compose; included nodes inherit the directive's layer id; behaviour unchanged without layers).

---

## M3 — Compiled / Cached Artifacts  *(Plan §7 — cheap, high value)* ✅ DONE

**Objective:** skip re-resolution for stable config; deterministic, opcache-friendly
deployments.

### Epic E7.1 — Compiler + cache (Core, opt-in) ✅ DONE
- [x] **T3.1.1 (M)** `Core/ConfigurationCompiler`: compute composite hash (source paths + content hashes + schema hash + context + pipeline fingerprint).
- [x] **T3.1.2 (M)** `Contract/ConfigurationCacheInterface` + `FileSystemCache` adapter writing/reading a frozen PHP artifact (under `Cache/`).
- [x] **T3.1.3 (M)** `ConfigurationBuilder::withCache($cache)`: on resolve, hit cache by hash; miss/stale ⇒ resolve normally, then write artifact (via `Core/CachingResolver`).
- [x] **T3.1.4 (M)** Guard: artifact load reconstructs an immutable snapshot (reuse `ConfigurationSnapshotFactory::buildFromArray`, with `TypedSnapshot` preserved).
- [x] **T3.1.5 (M)** Tests: `CompilerCacheTest` (cache hit serves equal snapshot, content edit invalidates, corrupt/missing artifact + throwing write fall back to live resolve, typed snapshot still validates from cache).

**M3 Exit:** stable config resolves once and is served from cache; gates green (192 tests / 446 assertions, PHPStan level 9, php-cs-fixer clean); example `examples/cached.php`.

---

## M4 — Secrets & Redaction  *(Plan §5 — enterprise-ready)* ✅ DONE

**Objective:** decrypt secret sources and guarantee secrets never leak into
provenance/telemetry/CLI output.

### Epic E5.1 — Secret sources (Core + adapters reuse Phase 8 plumbing) ✅ DONE
- [x] **T4.1.1 (M)** `Contract/SecretSourceInterface` (port). The existing
  `GcpSecretManagerSource`, `AwsParameterStoreSource`, `KubernetesSecretSource`
  and `DockerSecretsSource` now implement it, and a new `VaultSource` (HashiCorp
  Vault KV v2, `Adapter/Enterprise/Client/VaultClient`) reuses the Phase 8
  `RemoteKeyValueClient` + `HttpClient` plumbing. (The AWS/Azure secret-bearing
  roles are served by the existing Parameter Store / App Config adapters to avoid
  duplicate near-identical classes.)
- [x] **T4.1.2 (M)** `Contract/DecrypterInterface` + `Exception/DecryptionException` +
  `Adapter/Decryption/{AgeDecrypter,SopsDecrypter,DecryptingReader}`. The
  decrypters shell out through a new `Contract/CommandRunnerInterface`
  (`Adapter/CommandRunner/{NativeCommandRunner,FakeCommandRunner}`) so they are
  unit-testable offline. `DecryptingReader` transparently decrypts `enc:` /
  `sops:`-annotated resources before the loader parses them; unannotated
  resources pass through untouched.
- [x] **T4.1.3 (M)** `DocumentNormalizer` detects secret schemes
  (`k8s-secret://`, `docker-secret://`, `aws-ssm://`, `gcp-secretmanager://`,
  `vault://`) and flags every resolved node `sensitive` (and keeps the specific
  `sourceKind` for provenance richness — the `secret:` designation in the plan
  maps to this `sensitive` flag).

### Epic E5.2 — Mandatory redaction (Core) ✅ DONE
- [x] **T4.2.1 (M)** `Provenance`/`Snapshot` carry the `sensitive` flag
  (`ProvenanceInterface::isSensitive()`, `ConfigurationSnapshotInterface::isSensitive()`);
  a single `Core/RedactingRenderer` masks sensitive values as `****`.
- [x] **T4.2.2 (M)** All value render paths route through it:
  `ConfigurationInspector` (explain + table), `GraphVisualizer` (DOT + ASCII),
  `ConfigurationDiffer::summarize()` (the `bin/configure-it` `diff`/`explain`/
  `graph` commands consume these), and `OpenTelemetryReporter` event attributes
  (defensive: it emits only metadata today, never resolved values).
- [x] **T4.2.3 (M)** Tests: `RedactionTest` (secret masked everywhere; non-secret
  untouched; redaction cannot be bypassed via any render path), `VaultSourceTest`,
  `DecryptionTest`. Example: `examples/secrets.php`.

**M4 Exit:** secrets decrypt and are redacted in every output surface; tests
prove no leakage path; gates green (208 tests / 479 assertions, PHPStan level 9,
php-cs-fixer clean).

---

## M5 — Remote & Distributed + Caching  *(Plan §6)* ✅ DONE

**Objective:** first-class dynamic backends with resilience.

### Epic E6.1 — Remote sources (Core adapters) ✅ DONE
- [x] **T5.1.1 (M)** `HttpSource` + `ConsulSource`/`EtcdSource` adapters on `Contract/HttpClient` + `Contract/RemoteKeyValueClient` (fakes exist from Phase 8). `HttpReader` performs the GET via `HttpClient`; `ConsulClient`/`EtcdClient` implement `RemoteKeyValueClient` and translate the vendor KV API into nested JSON (re-rooting under the requested prefix, mirroring the AWS/GCP adapters); `RemoteJsonReader` carries the transport, exactly as for the secret sources.
- [x] **T5.1.2 (M)** `CachingReader` decorator: hash+TTL cache, serve last-good on fetch failure (fallback). Adds a focused `RemoteDocumentCacheInterface` (raw-document, TTL-aware, with a `getLastGood()` for stale fallback) implemented by `FileSystemDocumentCache`, mirroring the M3 `ConfigurationCacheInterface`/`FileSystemCache` storage mechanism — the snapshot cache was not force-fitted because it stores *snapshots*, not raw documents.
- [x] **T5.1.3 (M)** Tests: `RemoteSourceTest` (HTTP/Consul/etcd fetch + resolution with correct `sourceKind`; `CachingReader` fresh-serve, stale-serve, and failure-fallback via `FakeHttpClient`).

**M5 Exit:** remote sources resolve with caching + fallback; offline tests green (216 tests / 494 assertions, PHPStan level 9, php-cs-fixer clean).

---

## M6 — Live Configuration  *(Plan §4)* ✅ DONE

**Objective:** config that evolves at runtime without restart; drift detection. 

### Epic E4.1 — Watcher + reloadable facade (Core) ✅ DONE
- [x] **T6.1.1 (M)** `Contract/ConfigurationWatcherInterface`; `FsWatcher` (inotify) + `PollingWatcher` adapters. Emits `ConfigurationChanged` `Occurrence` via the reporter spine. `PollingWatcher` polls a pluggable signature (default `FileModificationProbe`, content-hash based) on an interval; `FsWatcher` opens an `inotify` watch in the constructor and dispatches on modify/attrib/close-write/create/delete.
- [x] **T6.1.2 (M)** `Core/ReloadableConfiguration` facade: holds the current immutable snapshot, subscribes to change events, re-resolves, and **atomically swaps** the reference via a single property assignment (old snapshots stay valid → ADR-0003 preserved). A failed reload keeps the last-good snapshot and records an `error` `Observation` instead of throwing.
- [x] **T6.1.3 (M)** Tests: `PollingWatcherTest`, `FsWatcherTest`, `ReloadableConfigurationTest` (change → new snapshot, old captured reference unchanged, watcher-driven reload, failed reload keeps last-good + observed).

### Epic E4.2 — Drift detection (Core, reuses `ConfigurationDiffer`) ✅ DONE
- [x] **T6.2.1 (M)** `Contract/ConfigurationDriftDetectorInterface` + `Core/ConfigurationDriftDetector`: compares a live graph (injectable provider) to a pinned baseline on demand (`check()`) or on an interval (`watch()`). Reuses `ConfigurationDiffer` to explain the divergence.
- [x] **T6.2.2 (M)** Emits an `Observation` (warning on drift, info on resolve) and a `ConfigurationDrift` `Occurrence`; `SemanticConventions` maps the occurrence to the OTel `config.drift` span + event, forwarded by the reporter spine.
- [x] **T6.2.3 (M)** Tests: `ConfigurationDriftDetectorTest` (baseline match → no drift/no telemetry, divergence detected + reported + listener invoked, drift resolved when live returns to baseline, watch loop stops on drift without re-emitting) plus an `OpenTelemetryReporter` test asserting the `config.drift` event/span.

**M6 Exit:** live reload swaps atomically (E4.1, old snapshots stay valid); watcher emits `ConfigurationChanged` + `ConfigurationReloaded`; a failed reload keeps the last-good snapshot and records an `error` observation. Drift detection (E4.2) compares the live graph to a pinned baseline via `ConfigurationDriftDetector`, emits `config.drift` OTel events/observations on transition, and is reported on a telemetry-friendly (non-spamming) transition basis. All gates green.

---

## M7 — Runtime Intelligence  *(Plan §8)* ✅ DONE

**Objective:** warnings vs errors, feature-flag evaluation.

### Epic E8.1 — Validation severity (Core) ✅ DONE
- [x] **T7.1.1 (M)** `ValidationResultInterface`/`ValidationResult` gain `severity` (`error`|`warning`); `NativeConfigurationSchema` maps Nette error messages to `error` and (deprecation/soft) warnings via `Processor::getWarnings()` to `warning`.
- [x] **T7.1.2 (M)** `ConfigurationBuilder::failOnWarnings(bool)` (default `false`); resolver throws only on errors, emits each issue as an `Observation` (`warning`/`error` level), and only fails on warnings when `failOnWarnings` is set.
- [x] **T7.1.3 (M)** Tests: `SeverityTest` (warning-only resolves + observed; error throws; `failOnWarnings` makes a warning fatal) plus updated `ValidationResultTest`.

### Epic E8.2 — Feature flags (Companion: `configure-it-flags`) ✅ DONE
- [x] **T7.2.1 (M)** `FlagEvaluatorInterface`/`FlagEvaluator` over the resolved snapshot: env gating, tenant/user targeting, attribute match, and percentage rollout (stable per-bucket hashing).
- [x] **T7.2.2 (M)** `FlagDefinition` (value object) parses flag definitions from config; `FeatureFlagSchema` (adapter) provides a reusable Nette Schema fragment so definitions are schema-validated. Evaluation reads the snapshot — no new source of truth.
- [x] **T7.2.3 (M)** Tests (`FlagDefinitionTest`, `FlagEvaluatorTest`) + `examples/feature-flags.php`.

**M7 Exit (E8.1 + E8.2 done):** validation warnings surface as `warning` observations without crashing (errors still throw unless `failOnWarnings` flips them); feature flags evaluate from the resolved config via `FlagEvaluator` (env/tenant/user/attribute gating + percentage rollout). Gates green.

---

## M8 — Framework Adoption  *(Plan §9)* ✅ DONE

**Objective:** meet teams in their stack.

### Epic E9.1 — Framework bundles (Companion packages) ✅ DONE (PSR-11 + Symfony)
- [x] **T8.1.1 (M)** `configure-it-psr11`: PSR-11 container (`ConfigureItContainer` + `ConfigureItContainerFactory`) exposing the resolved `ConfigurationSnapshotInterface` under a single, stable entry id; unknown ids throw `NotFoundExceptionInterface`.
- [x] **T8.1.2 (L)** `configure-it-symfony`: `ConfigureItBundle` (extends `Symfony\HttpKernel\Bundle\Bundle`) + `ConfigureItExtension` wiring `ConfigurationBuilder` from bundle config (`sources` / `environment` / `schema` / `cache`), exposing the resolved `ConfigurationSnapshotInterface` **and** the configured `ConfigurationBuilder` as services. Verified by booting a `ContainerBuilder` via both array config and a real YAML bundle config.
- [ ] **T8.1.3 (L)** `configure-it-laravel`: service provider + facade — **deferred** (not required for M8 Exit; tracked as a follow-up companion). The PSR-11 + Symfony bundles already satisfy the exit criterion.
- [x] **T8.1.4 (M)** Each shipped companion has an integration test booting its container and asserting a resolved, typed snapshot (PSR-11: `ConfigureItContainerTest`; Symfony: `ConfigureItExtensionTest` + `ConfigureItBundleTest`).

**M8 Exit (PSR-11 + Symfony bundle done):** at least PSR-11 + one full framework bundle boot and resolve; gates green in each companion. `configure-it-psr11` and `configure-it-symfony` each pass PHPStan level 9, php-cs-fixer, and PHPUnit (4 tests each). Core `ConfigureIt` gates remain green (255 tests). Laravel deferred per plan §9 (companion, no framework bias).

---

## M9 — Tooling & Operability  *(Plan §9)*

**Objective:** turn the SDK into a product surface.

### Epic E9.2 — Web Explorer + graph query (Companion: `configure-it-explorer`) ✅ DONE
- [x] **T9.2.1 (M)** Read-only UI (`Http\ExplorerController` + `View\HtmlRenderer`, served by `public/index.php` + `bin/configure-it-explorer`) over `ConfigurationInspector`/`GraphVisualizer`/`ConfigurationDiffer`: `/explain`, `/diff`, `/graph` (DOT/ASCII). Every route also answers `?format=json`.
- [x] **T9.2.2 (M)** Graph query API over `ConfigurationGraph` + `ConfigurationDiffer` (`Query\GraphQuery` + `QueryParser`/`QueryCriterion`/`QueryPredicate`): `source`, `overridden-by`, `sensitive`, `adapter`, `layer`, `format`, `trail`, plus `changed` (two-graph diff). Powers `/query` and the `GraphQuery` service for consumers.
- [x] **T9.2.3 (M)** Tests: `GraphQueryTest` (9 predicates + cross-graph `changes`) and `ExplorerControllerTest` (HTML + JSON for explain/diff/graph/query, graceful 400s, end-to-end over real fixture files).

### Epic E9.3 — Schema → docs & testing utilities (Companion) ✅ DONE
- [x] **T9.3.1 (M)** `configure-it-schema-docs`: emit Markdown + JSON-Schema (draft 2020-12) from a `Nette\Schema\Expect` definition.
- [x] **T9.3.2 (M)** `configure-it-testing`: fakes (`FakeConfigurationSource`, `FakeLoader`, `SpyProcessor`, `InMemoryGraph`, `FakeNode`) + `ConfigurationAssertions` trait for consumers.
- [x] **T9.3.3 (M)** Tests for both companions (schema introspection/Markdown/JSON-Schema/CLI; fakes + assertions trait).

**M9 Exit (E9.2 + E9.3 both done):** explorer serves explain/diff/graph + query (HTML + JSON); `configure-it-schema-docs` and `configure-it-testing` are delivered as independent companions. All three companions pass PHPStan level 9, php-cs-fixer clean, and PHPUnit:
- `configure-it-explorer`: 22 tests / 42 assertions
- `configure-it-schema-docs`: 25 tests / 84 assertions
- `configure-it-testing`: 20 tests / 63 assertions

The core `ConfigureIt` package is unchanged by M9 and remains green (**255 tests / 593 assertions**, PHPStan level 9, php-cs-fixer clean).

---

## Sequencing & Dependencies

```
M1 Typed ───────┐
M2 Layered ─────┤
M3 Compiled ────┼──► M5 Remote (needs M3 cache) ──► M6 Live (needs M3 + reporter)
M4 Secrets ─────┤                                         │
                └──► M7 Severity/Flags (independent) ──────┤
                                                          ▼
                                                M8 Bundles ─► M9 Explorer/docs
```

| Wave | Milestones | Theme |
|---|---|---|
| 1 (Next-level jump) | M1, M2, M3, M4 | typed, layered, fast, secret-aware |
| 2 (+1) | M5, M6, M7, M8 | dynamic, live, intelligent, adopted |
| 3 (+2) | M9 | operable product surface |

**Critical path:** M1 → (M3 → M5 → M6); M4 is independent of M1–M3.

---

## Suggested First Delivery (the "next level" promise)

Ship **M1 + M2 + M3 + M4** as one coherent release:
- M1 makes config **typed** (ergonomics).
- M2 makes it **layered/explainable** (safety).
- M3 makes it **fast** (production).
- M4 makes it **secret-aware** (enterprise).

Together they deliver the highest impact at the lowest risk, entirely within the
existing ports/adapters/immutability boundaries — exactly the MVP slice proposed
in `ADDITIONAL_FEATURES_PLAN.md` §10.
