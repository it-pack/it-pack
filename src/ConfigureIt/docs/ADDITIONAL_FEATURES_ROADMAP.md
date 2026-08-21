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

## M2 — Layered Composition & Environments  *(Plan §3)*

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

### Epic E3.3 — Layer attribution in provenance (Core)
- [ ] **T2.3.1 (M)** Stamp layer id onto nodes during normalization (extend `DocumentNormalizer` to accept a layer context); surface in `Provenance`.
- [ ] **T2.3.2 (M)** Inspector/CLI (`explain`) shows the winning layer (reuse `ConfigurationInspector`).
- [ ] **T2.3.3 (M)** Tests: `LayerProvenanceTest`.

**M2 Exit:** `addLayer()` ordering + `@if` includes work and are visible in provenance/inspector; no behaviour change when unused.

---

## M3 — Compiled / Cached Artifacts  *(Plan §7 — cheap, high value)*

**Objective:** skip re-resolution for stable config; deterministic, opcache-friendly
deployments.

### Epic E7.1 — Compiler + cache (Core, opt-in)
- [ ] **T3.1.1 (M)** `Core/ConfigurationCompiler`: compute composite hash (source paths + mtimes + schema hash + builder fingerprint).
- [ ] **T3.1.2 (M)** `Contract/ConfigurationCacheInterface` + `FileSystemCache` adapter writing/reading a frozen PHP artifact (under `Cache/`).
- [ ] **T3.1.3 (M)** `ConfigurationBuilder::withCache($cache)`: on resolve, hit cache by hash; miss/stale ⇒ resolve normally, then write artifact.
- [ ] **T3.1.4 (M)** Guard: artifact load must reconstruct an immutable snapshot (reuse `ConfigurationSnapshotFactory`).
- [ ] **T3.1.5 (M)** Tests: `CompilerCacheTest` (cache hit serves equal snapshot, hash change invalidates, failure falls back to live resolve).

**M3 Exit:** stable config resolves once and is served from cache; gates green; example `examples/cached.php`.

---

## M4 — Secrets & Redaction  *(Plan §5 — enterprise-ready)*

**Objective:** decrypt secret sources and guarantee secrets never leak into
provenance/telemetry/CLI output.

### Epic E5.1 — Secret sources (Companion adapters reuse Phase 8 plumbing)
- [ ] **T4.1.1 (M)** `Contract/SecretSourceInterface` (port). `VaultSource`, `AwsSecretsManagerSource`, `GcpSecretManagerSource`, `AzureKeyVaultSource` adapters reusing `Contract/RemoteKeyValueClient` + `Http/CurlHttpClient` fakes.
- [ ] **T4.1.2 (M)** `Contract/DecrypterInterface` + `AgeDecrypter`/`SopsDecrypter` adapters; `DecryptingReader` decorator transparently decrypts `enc:`/`SOPS`-annotated values.
- [ ] **T4.1.3 (M)** Mark loaded secrets `sensitive` via a `secret:` source-kind in `DocumentNormalizer`.

### Epic E5.2 — Mandatory redaction (Core)
- [ ] **T4.2.1 (M)** `Provenance`/`Snapshot` carry `sensitive` flag; single `RedactingRenderer` masks `sensitive` values as `****`.
- [ ] **T4.2.2 (M)** Route **all** value render paths through it: `ConfigurationInspector`, `bin/configure-it`, `OpenTelemetryReporter` event attributes, `ConfigurationDiffer` summary.
- [ ] **T4.2.3 (M)** Tests: `RedactionTest` (secret masked everywhere; non-secret untouched; redaction cannot be bypassed).

**M4 Exit:** secrets decrypt and are redacted in every output surface; tests prove no leakage path; gates green.

---

## M5 — Remote & Distributed + Caching  *(Plan §6)*

**Objective:** first-class dynamic backends with resilience.

### Epic E6.1 — Remote sources (Core adapters)
- [ ] **T5.1.1 (M)** `HttpSource` + `ConsulSource`/`EtcdSource` adapters on `Contract/HttpClient` + `Contract/RemoteKeyValueClient` (fakes exist from Phase 8).
- [ ] **T5.1.2 (M)** `CachingReader` decorator: hash+TTL cache, serve last-good on fetch failure (fallback). Reuses `ConfigurationCacheInterface` (M3).
- [ ] **T5.1.3 (M)** Tests: `RemoteSourceTest` (fetch, stale-serve, failure-fallback via `FakeHttpClient`).

**M5 Exit:** remote sources resolve with caching + fallback; offline tests green.

---

## M6 — Live Configuration  *(Plan §4)*

**Objective:** config that evolves at runtime without restart; drift detection.

### Epic E4.1 — Watcher + reloadable facade (Core)
- [ ] **T6.1.1 (M)** `Contract/ConfigurationWatcherInterface`; `FsWatcher` (inotify/react) + `PollingWatcher` adapters. Emits `ConfigurationChanged` `Occurrence` via the reporter spine.
- [ ] **T6.1.2 (M)** `Core/ReloadableConfiguration` facade: holds current immutable snapshot, subscribes to change events, re-resolves, **atomically swaps** the reference (old snapshot remains valid → immutability preserved).
- [ ] **T6.1.3 (M)** Tests: `ReloadableConfigurationTest` (change → new snapshot, old reference unchanged, concurrent-read safety).

### Epic E4.2 — Drift detection (Core, reuses `ConfigurationDiffer`)
- [ ] **T6.2.1 (M)** `Core/ConfigurationDriftDetector`: compares live graph to a pinned baseline (hash or stored snapshot) on interval.
- [ ] **T6.2.2 (M)** Emit `Observation` + OTel `config.drift` event (`OpenTelemetryReporter` already forwards observations).
- [ ] **T6.2.3 (M)** Tests: `DriftDetectorTest` (baseline match, divergence detected + reported).

**M6 Exit:** live reload swaps atomically; drift raises observations/OTel events; gates green.

---

## M7 — Runtime Intelligence  *(Plan §8)*

**Objective:** warnings vs errors, feature-flag evaluation.

### Epic E8.1 — Validation severity (Core)
- [ ] **T7.1.1 (M)** `ValidationResult` gains `severity` (error|warning); `NativeConfigurationSchema` maps Nette messages to severity.
- [ ] **T7.1.2 (M)** `ConfigurationBuilder::failOnWarnings(bool)`; resolver throws only on errors, emits warnings as `Observation`s.
- [ ] **T7.1.3 (M)** Tests: `SeverityTest` (warning-only resolves + observed; error throws).

### Epic E8.2 — Feature flags (Companion: `configure-it-flags`)
- [ ] **T7.2.1 (M)** `FlagEvaluator` port over the resolved graph (percentage rollout, tenant/user targeting, env gating).
- [ ] **T7.2.2 (M)** Schema-driven flag definitions; evaluation reads the snapshot (no new source of truth).
- [ ] **T7.2.3 (M)** Tests + `examples/feature-flags.php`.

**M7 Exit:** warnings don't crash; flags evaluate from config; gates green.

---

## M8 — Framework Adoption  *(Plan §9)*

**Objective:** meet teams in their stack.

### Epic E9.1 — Framework bundles (Companion packages)
- [ ] **T8.1.1 (M)** `configure-it-psr11`: PSR-11 container entry exposing the snapshot.
- [ ] **T8.1.2 (L)** `configure-it-symfony`: bundle wiring `ConfigurationBuilder` from bundle config + env.
- [ ] **T8.1.3 (L)** `configure-it-laravel`: service provider + facade.
- [ ] **T8.1.4 (M)** Each: integration test booting the container and asserting a resolved, typed snapshot.

**M8 Exit:** at least PSR-11 + one full framework bundle boot and resolve; gates green in each companion.

---

## M9 — Tooling & Operability  *(Plan §9)*

**Objective:** turn the SDK into a product surface.

### Epic E9.2 — Web Explorer + graph query (Companion: `configure-it-explorer`)
- [ ] **T9.2.1 (M)** Read-only UI over `ConfigurationInspector`/`GraphVisualizer`/`bin/configure-it` services: explain, diff, graph (DOT/ASCII).
- [ ] **T9.2.2 (M)** Graph query API over `ConfigurationGraph` + `ConfigurationDiffer` (e.g. "keys overridden by `local.json`", "keys from `aws-ssm`").
- [ ] **T9.2.3 (M)** Tests: query returns expected node sets.

### Epic E9.3 — Schema → docs & testing utilities (Companion)
- [ ] **T9.3.1 (M)** `configure-it-schema-docs`: emit Markdown + JSON-Schema from a `Nette\Schema\Expect` definition.
- [ ] **T9.3.2 (M)** `configure-it-testing`: fakes + `ConfigurationAssertions` trait for consumers.
- [ ] **T9.3.3 (M)** Tests for both companions.

**M9 Exit:** explorer serves explain/diff/graph + query; schema→docs and testing pkg usable; gates green.

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
