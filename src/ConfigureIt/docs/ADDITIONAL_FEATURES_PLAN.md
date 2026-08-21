# ConfigureIt — Additional Features Plan (Next-Level)

> Status: brainstorm / proposal. ConfigureIt has delivered Phases 0–9 (green gate:
> 149 tests, PHPStan level 9, cs-fixer clean). This document reasons about the
> features that would take it from "a coherent, well-architected configuration
> SDK" to a **configuration platform** — production-grade, ergonomic, and adopted.
>
> Lens: everything below must honour the ratified principles — Ports & Adapters
> (ADR-0002), Immutable Snapshots (ADR-0003), Strong Typing, Explicit over Magic,
> No Framework Bias, Extensibility (AGENTS.md). New capability = new contract +
> adapter, never Core contamination.

---

## 1. Where we are vs. where "next level" is

Today ConfigureIt excels at **loading, merging, validating, resolving and
observing** configuration. The remaining roadmap deferrals (NEON, CSV/TSV,
Vault/Consul/Redis/HTTP/S3 sources, the `configure-it-opentelemetry` adapter, live
integration tests, RDF/PlantUML export) are mostly **more of the same shape** —
additional adapters behind existing ports. Valuable, but incremental.

"Next level" means differentiating capabilities that change *how teams use
configuration*:

| Dimension | Today | Next level |
|---|---|---|
| Consumption | `Snapshot::get()` returns `mixed` | **Typed, schema-bound access** |
| Composition | implicit priority-merge by source order | **Explicit layers, profiles, conditional includes** |
| Lifetime | resolved once, frozen | **Live: watch, reload, drift detection** |
| Secrets | enterprise parameter stores (plain) | **Secret sources + decryption + redaction** |
| Distribution | local/file/enterprise volume | **Remote config with cache + watch + fallback** |
| Performance | resolve on every boot | **Compiled artifacts / hash caches** |
| Intelligence | validation (hard fail) | **Severity, feature flags, drift/anomaly** |
| Adoption | framework-neutral Core | **Framework bundles + web explorer + query** |

The rest of the document expands each row with reasoning and a design sketch.

---

## 2. Type-Safe Consumption (highest leverage)

**Problem.** `ConfigurationSnapshotInterface::get(string $path): mixed` forces every
consumer to cast and assume. AGENTS.md demands strong typing, but the *consumption*
side is untyped. This is the single biggest ergonomics/ergonomics gap and the most
common source of production bugs (string `"0"` vs `false`, missing key → `null`).

**Why next level.** Turns "data you hope is shaped right" into "data the type
system guarantees." Directly fulfils the project's own stated value
("Snapshots power Applications") and the Strong-Typing principle.

**Design.**
- Add typed accessors on the snapshot (non-breaking, additive):
  `int(string)`, `bool(string)`, `string(string)`, `float(string)`,
  `array(string)`, `exists(string)` — each throws a domain `TypeException` on
  mismatch instead of silent wrong-type bugs.
- Add a **schema-bound snapshot**: when a `ConfigurationSchemaInterface` was used,
  the resolver can mint a `TypedSnapshot` whose `get()` is validated against the
  schema's expected types at freeze time (one check, zero per-read cost).
- Add **optional code generation**: a `configure-it-codegen` companion that reads
  a `Nette\Schema\Expect` definition and emits a readonly `AppConfig` DTO with
  typed getters + PHPDoc, giving IDE autocompletion and PHPStan level-max safety.
  Generation is a build-time adapter; the runtime stays schema-port-agnostic.

**Effort:** medium (accessors small; codegen is a separate package).
**Risk:** low — purely additive, no Core behaviour change.

---

## 3. Layered Composition & Environments

**Problem.** Merge precedence is currently "last source wins by discovery order."
Real systems need **base → environment → local → override** layering, named
**profiles** (`dev`/`test`/`prod`), and **conditional inclusion**
(`@include db.yaml @if env=prod`).

**Why next level.** Explicit, declarative layering is what makes config
**explainable and safe** — you can answer "why is this value X in prod but Y in
dev?" The provenance graph we already capture is the perfect substrate.

**Design.**
- Introduce a `Layer` concept (port `ConfigurationLayerInterface`): each layer
  contributes sources + a numeric `precedence` + optional `match` predicate
  (env/tenant/profile). The builder gains `addLayer()`; resolution orders layers
  by precedence before feeding the pipeline.
- Extend `IncludeProcessor` with an `@if <condition>` directive (conditions
  evaluate against the `ExecutionContext`). Keeps the single-pass processor model;
  multi-pass handled by adding the processor twice (already documented).
- Provenance already records `priority` and `sourceKind`; layer id can be added to
  `ExecutionContext`-derived attributes so the inspector/CLI can show the layer
  that won.

**Effort:** medium. **Risk:** medium — changes how precedence is reasoned about;
must remain backward-compatible (no layers = current behaviour).

---

## 4. Live Configuration (Watch / Reload / Drift)

**Problem.** A frozen snapshot is correct for a single point in time. Long-running
workers, CLIs and daemons need config that can **change without restart**, and
operators need to know when **runtime config has drifted** from a known baseline.

**Why next level.** Moves ConfigureIt from "bootstrap utility" to "runtime
configuration substrate." Pairs naturally with OpenTelemetry (emit a
`config.reloaded` / `config.drift` event).

**Design.**
- A `Watcher` port (`ConfigurationWatcherInterface`) backed by an adapter
  (`FsWatcher` via `inotify`/`react/fs`, or a polling adapter for portability).
  Emits `ConfigurationChanged` occurrences through the existing reporter spine.
- A `ReloadableConfiguration` facade wraps a `ConfigurationBuilder` + `Watcher`:
  holds the current immutable snapshot, subscribes to change events, re-resolves,
  and swaps the snapshot atomically (old snapshot stays valid until GC — immutability
  preserved).
- **Drift detection**: a `ConfigurationDriftDetector` reusing `ConfigurationDiffer`
  compares the live graph against a pinned baseline (hash or stored snapshot) on an
  interval and raises `Observation`s / OTel events on divergence.

**Effort:** medium–high. **Risk:** medium — concurrency/atomicity; keep the swap
lock-free by swapping an immutable reference.

---

## 5. Secrets & Security

**Problem.** Enterprise sources (Phase 8) read parameter stores as plain key/value.
Real secrets need **decryption** (SOPS, age, KMS/Cloud KMS) and **redaction** so a
secret value is never leaked into provenance dumps, telemetry attributes, or CLI
output.

**Why next level.** Configuration security is a top adoption blocker for
enterprise users; the provenance/observability spine already makes accidental
leakage likely unless we redact deliberately.

**Design.**
- A `SecretSource` port + adapters: `VaultSource`, `AwsSecretsManagerSource`,
  `GcpSecretManagerSource`, `AzureKeyVaultSource` (the cloud clients already exist
  as fakes in Phase 8 — reuse the `RemoteKeyValueClient` plumbing).
- A `DecryptingLoader`/`DecryptingReader` adapter that transparently decrypts
  `enc:...` / SOPS-annotated values via an injectable `DecrypterInterface`.
- **Redaction policy**: `Provenance`/`Snapshot` gain a `sensitive` flag; a
  `RedactingRenderer` (used by inspector, CLI, OTel event attributes) masks
  `sensitive` values (`****`). Secrets are marked sensitive at load time via a
  `secret:` source-kind or a schema annotation.

**Effort:** medium. **Risk:** low–medium — must guarantee redaction cannot be
bypassed (single render path).

---

## 6. Remote & Distributed Configuration

**Problem.** Beyond files/volumes, teams use Consul, etcd, Apollo, Nacos, or a
custom HTTP endpoint. These need **caching, polling/watch, and fallback** — not
just a one-shot fetch.

**Why next level.** Completes the "any source" promise (AGENTS.md) for the dynamic
world, and is the natural home for the `Cache/` layer the package structure
reserves.

**Design.**
- `HttpSource` + `ConsulSource`/`EtcdSource` adapters reusing the existing
  `Contract\HttpClient` + `RemoteKeyValueClient` ports (Phase 8 fakes apply).
- A `CachingReader` decorator (adapter) keyed by content hash + TTL; on miss/stale
  it re-fetches and, on failure, serves the last-good cached document (fallback =
  resilience). This is the canonical "cache processor" the roadmap anticipated.
- Combine with §4 `Watcher` for push-based reload when the backend supports watch.

**Effort:** medium. **Risk:** low (additive adapters + decorator).

---

## 7. Performance — Compiled / Cached Artifacts

**Problem.** Resolving many sources + processors + schema on every boot is
wasteful for stable config (the common case).

**Why next level.** Production performance is table stakes; a compiled artifact
also makes deployments deterministic and auditable.

**Design.**
- A `ConfigurationCompiler` that, given a source set, computes a composite hash
  (paths + mtimes + schema hash) and, if unchanged, loads a pre-frozen PHP artifact
  from `Cache/` (opcache-friendly, `var_export`/OPcached include). On hash change,
  re-resolves and rewrites the artifact.
- Fully additive; the immutable snapshot contract is unchanged. Opt-in via the
  builder (`->withCache($cache)`).

**Effort:** low–medium. **Risk:** low.

---

## 8. Runtime Intelligence

**Problem.** Validation is currently a hard fail; there is no notion of
**warning vs error**, no **feature-flag evaluation**, and no **anomaly** signal.

**Why next level.** This is where configuration becomes *operable* rather than
*static*.

**Design.**
- **Severity model**: `ValidationResult` gains `severity` (error|warning); the
  resolver can be configured to throw only on errors and emit warnings as
  `Observation`s (reusing the spine + OTel `config.observation` events).
- **Feature flags**: a `FlagEvaluator` port built on top of the resolved graph
  (percentage rollout, user/tenant targeting, env gating) — a thin, schema-driven
  evaluation layer, not a new source of truth. Could be a `configure-it-flags`
  companion.
- **Drift/anomaly**: see §4; extends to detecting unexpected values vs. historical
  baselines.

**Effort:** low–medium. **Risk:** low.

---

## 9. Tooling & Adoption

**Problem.** A great Core with weak touchpoints limits adoption.

**Why next level.** Turns an SDK into a *product*.

**Design.**
- **Framework integrations** (separate companion packages, per "No Framework
  Bias"): `configure-it-symfony` (bundle + env var wiring), `configure-it-laravel`
  (service provider), `configure-it-psr11` (PSR-11 container). Each only adapts
  the public builder/snapshot ports.
- **Web Explorer**: a small read-only UI over the existing inspector/CLI services
  (`explain`, `diff`, `graph`) — visualize provenance, overrides, and the OTel
  trace. Reuses `GraphVisualizer` (DOT/ASCII) and the reporter. The ROADMAP's
  "IDE plugins / RDF / PlantUML" becomes a concrete explorer.
- **Schema → documentation**: generate Markdown / JSON-Schema from a
  `Nette\Schema\Expect` definition (human docs + machine docs for tooling).
- **Graph query**: a tiny, safe query API over the provenance graph
  ("all keys overridden by `local.json`", "keys sourced from `aws-ssm`") — built
  on `ConfigurationGraph` + `ConfigurationDiffer` already present.
- **Testing utilities**: a `configure-it-testing` package with fakes + a
  `ConfigurationAssertions` trait so consumers test their config easily.

**Effort:** varies (integrations medium each; explorer medium; docs/query low).
**Risk:** low (all additive, separate packages).

---

## 10. Prioritization

| Feature | Impact | Effort | Risk | Suggested phase |
|---|---|---|---|---|
| Typed consumption (§2) | **Very high** | Med | Low | Next |
| Layered composition (§3) | High | Med | Med | Next |
| Compiled cache (§7) | High | Low–Med | Low | Next |
| Secrets + redaction (§5) | High | Med | Med | Next |
| Remote + caching (§6) | Med–High | Med | Low | +1 |
| Live watch/reload (§4) | High | Med–High | Med | +1 |
| Severity / flags (§8) | Med | Low–Med | Low | +1 |
| Framework bundles (§9) | High (adoption) | Med each | Low | +1 |
| Web explorer / query (§9) | Med–High | Med | Low | +2 |
| Schema→docs / testing pkg (§9) | Med | Low | Low | +2 |

**Recommended MVP slice (the "next level" jump):**
1. **Typed consumption** (§2) — biggest ergonomics win, additive, on-strategy.
2. **Layered composition + conditional includes** (§3) — makes config explainable.
3. **Compiled/cached artifact** (§7) — production performance, cheap.
4. **Secrets + redaction** (§5) — enterprise-ready, reuses Phase 8 plumbing.

These four share a theme: they make configuration **safe, typed, layered, and
secret-aware** without touching Core semantics, and they all build directly on
assets we already have (schema port, provenance graph, Execution spine,
`RemoteKeyValueClient` fakes, OpenTelemetry bridge).

---

## 11. Risks & Open Questions

- **Scope discipline.** Every item should enter as a contract + adapter. Guard
  against "god classes" creeping into Core (ADR-0002/SOLID).
- **Immutability vs liveness (§4).** Live reload must never mutate a shipped
  snapshot; only swap the reference atomically.
- **Redaction cannot be optional-by-accident (§5).** All value render paths must
  route through the redacting renderer.
- **Backward compatibility.** Layers (§3), severity (§8), typed accessors (§2) must
  be opt-in so existing callers are unaffected.
- **Adapter dependencies.** Remote/secret sources need real SDKs (AWS/GCP/Azure/
  Vault). Keep them in companion packages; the Core stays SDK-free (proven by the
  Phase 9 OpenTelemetry boundary).
- **Naming.** Prefer domain language (`TypedSnapshot`, `ConfigurationLayer`,
  `ReloadableConfiguration`, `SecretSource`) over `Manager`/`Helper`/`Util`
  (AGENTS.md naming rules).

---

## 12. Closing

ConfigureIt's foundation (ports/adapters, immutable snapshots, provenance graph,
Execution spine, OpenTelemetry bridge) is unusually solid for its age. The "next
level" is not more loaders — it is **making configuration typed, layered, live,
secret-aware, and operable**, then **meeting teams where they are** (frameworks,
UI, tests). The MVP slice in §10 delivers the highest leverage with the lowest
risk and stays fully consistent with the project's ratified architecture.
