# ConfigureIt — Architecture & Code Review

**Scope:** `src/ConfigureIt/src/**` (184 PHP files) — features, architecture, maintenance,
performance, maintainability, security, DX, SOLID/DRY, hexagonal fit, modern-PHP leverage,
interface coverage, complexity, and magic-string/number hygiene.

**Method:** static reading of Core/Contract/Adapter/ValueObject/Execution/Cache/Codegen/CLI/Bridge
plus `docs/`, `composer.json`, `phpstan.neon`, `.php-cs-fixer.php`. Test/build gates assumed green
(all three companion packages and core pass PHPStan L9 + cs-fixer + PHPUnit — see ADDITIONAL_FEATURES_ROADMAP.md).
Vendor code was out of scope.

**Assumptions**
- The package targets `php: ^8.5` (from `composer.json`); 8.4+ features (property hooks, lazy objects)
  and 8.1+ features (Fibers, WeakMap) are therefore available but optional.
- `mixed` used for heterogeneous configuration trees is accepted as a domain necessity, not a defect.
- Files not read in full were sampled; quantitative claims (e.g. `else` count) come from repo-wide greps.

---

## Verdict

**Strong, mature, well-layered codebase.** Hexagonal discipline is enforced almost everywhere:
Contracts are pure, Core depends on Contracts/ValueObjects (with one documented leak), adapters
isolate third-party libraries and translate vendor exceptions, and the execution/observability spine
is clean. The dominant improvement areas are (1) a small number of DIP/hexagonal leaks in the
composition root, (2) underuse of enums for domain discriminators, (3) a few magic strings/numbers,
and (4) one PHP 8.5-forward-compat deprecation plus one remote-fetch side effect in cache-key hashing.

---

## Strengths

- **Layering is correct by default.** `Contract` has zero imports of Adapter/Bridge (verified). Core
  depends on Contracts + ValueObjects; `NativeConfigurationSchema` cleanly isolates `nette/schema`
  and converts vendor `ValidationException` into a domain `ValidationResult` (adapter rule honored).
- **Interface coverage is high for ports.** Resolver, Factory, Pipeline, Graph, Node, Snapshot,
  Source, Reader, Loader, Processor, Schema, Cache, Watcher, Document, Reporter, Provenance all sit
  behind contracts; factories (`ConfigurationFactory`, `ConfigurationDocumentFactory`,
  `ConfigurationLoaderFactory`, `ConfigurationSnapshotFactory`) are interface-backed and `final readonly`.
- **Modern-PHP baseline is already good.** `readonly` used in ~86 files; 39 `final` classes;
  constructor promotion; `enum Format` (the one enum); `match` instead of `switch`/`else` chains.
- **`else`/`elseif` discipline is excellent.** Only 3 real `else` blocks in the entire `src` tree
  (FileModificationProbe, DiagnosticsReport, FileSystemCache) — all benign, none control-flow smells.
- **Static analysis is strict.** PHPStan **level 9** + ShipMonk dead-code detection; cs-fixer
  `@PSR12` + `@PHP84Migration` + `no_superfluous_elseif`. 67 unit-test files; 20 runnable examples;
  a `bin/configure-it` CLI; rich diagnostics and observability.
- **Security mindset is present.** `Provenance::isSensitive()` + central `RedactingRenderer` mask
  secrets in output/diffs/telemetry; dedicated decryption adapters (`Age`, `Sops`) live in Adapters.
- **Low complexity.** Processors (e.g. `VariableProcessor`) are small, pure, deterministic, and use
  `private const` for patterns rather than inline regex.

---

## Findings (prioritized)

Severity: **H** = architectural/security, **M** = correctness/type-safety, **L** = polish/optional.

| ID | Area | Sev | Location | Issue | Recommendation |
|----|------|-----|----------|-------|----------------|
| F1 | Hexagonal | **H** | `Core/ConfigurationBuilder.php:7-10` | Core imports concrete `Adapter\CompositeReader`, `Adapter\Decryption\DecryptingReader`, `Bridge\OpenTelemetry\OpenTelemetryReporter`. Violates AGENTS "Core may NOT depend on Adapters/Bridges" and the dependency-direction rule. | Move wiring into a dedicated composition root (e.g. `src/Application` or a root-namespace `Boot`/factory) or inject pre-built reader/reporter via their contracts. Keep Core depending only on Contracts + ValueObjects. |
| F2 | DIP | **M** | `Core/ConfigurationBuilder.php:153,205` | `withExecutionContext(ExecutionContext $context)` and `build(?ResolutionReporter $reporter)` type-hint concrete classes although `ExecutionContextInterface`/`ResolutionReporterInterface` exist. | Type-hint the interfaces; construct/accept concrete defaults internally only. |
| F3 | Contracts | **M** | `Contract/ValidationExceptionInterface.php`, `Exception/ValidationException.php` | `ValidationExceptionInterface` is orphaned — no class implements it, yet `ProcessorInterface`/`ConfigurationResolverInterface` document `@throws ValidationExceptionInterface`. `ValidationException` only extends `ConfigurationException`. | Make `ValidationException implements ValidationExceptionInterface` (add `getMessages()`/`getMessageObjects()`), or remove the interface and fix the docblocks. Prefer implementing it. |
| F4 | DIP | **M** | `Contract/Execution/ResolutionReporterInterface.php` | The reporter returns concrete `Occurrence`/`Observation` instead of the `OccurrenceInterface`/`ObservationInterface` the same file imports — contracts tied to concretions. | Return the interfaces; adapt implementations. |
| F5 | Enums | **M** | `Execution/Observation.php:18`, `Execution/Occurrence.php:18`, `ValueObject/Provenance.php` (`sourceKind`, `adapter`, `processorTrail`) | AGENTS: "Prefer enums over strings." Domain discriminators are raw strings: severity `level` (`info`/`warning`/`error`), occurrence `type`, `sourceKind` (`file`/`env`/`programmatic`/`secret`…), stage names. | Introduce `Severity`, `OccurrenceType`, `SourceKind`, `ProcessorStage` enums/constants. |
| F6 | Magic strings | **M** | `Core/ConfigurationCompiler.php:54` (`'sha256'`), `Core/ConfigurationNode.php:73-76` (`Format::PHP`, `'unknown'`, `'programmatic'`), observation/occurrence/stage literals | Stringly-typed algorithm name and sentinel defaults. | Constant for hash algo (`private const HASH_ALGO = 'sha256'`); enums per F5; explicit unknown-sentinel for format. |
| F7 | Magic numbers | **L** | `Core/ConfigurationPipeline.php:43`, `Core/DefaultsProcessor.php:49,55` | Literal `0` for default priority. | Named constant (e.g. `Priority::DEFAULT` / `self::DEFAULT_PRIORITY`). |
| F8 | Forward-compat | **M** | `Adapter/NativeConfigurationSchema.php:128` | `ReflectionProperty::setAccessible(true)` is a deprecated no-op on PHP 8.5 (no-op since 8.1) and emits a deprecation. | Remove the `setAccessible(true)` call; `getValue()` already works on private props in 8.1+. (Already fixed the same way in the companion introspector.) |
| F9 | Correctness | **L** | `ValueObject/Provenance.php:fromArray`, `Core/ConfigurationNode.php:provenance()` | Falls back to `Format::PHP` when format is unknown/missing — a misleading sentinel (uppercase `PHP` used as "unknown"). | Add `Format::UNKNOWN` (or make format nullable with explicit handling) so round-trips and diagnostics are honest. |
| F10 | Type safety | **L** | repo-wide 184 `mixed` (mostly `array<string,mixed>`) | AGENTS: "Avoid mixed." Most are unavoidable tree values; a few are bare `mixed` params/returns in public APIs. | Keep `array<string,mixed>` for tree values; tighten bare `mixed` with generics/precise types where feasible. |
| F11 | Docs | **L** | `docs/architecture/PORTS.md` | Stub (headings only) while ARCHITECTURE.md promises per-port "may depend / may not depend / example" sections. | Flesh out PORTS.md or mark it DRAFT. |
| F12 | Modern PHP — hooks | **L** | `ValueObject/Provenance.php`, `Core/ConfigurationNode.php` | VOs expose both public readonly props **and** getter methods; property hooks (PHP 8.4+) would collapse the boilerplate. | Optional: convert getters to property hooks (`public string $x { get => $this->x; }`). |
| F13 | Modern PHP — lazy/weak | **L** | `Core/ConfigurationSnapshot.php`, `Core/ConfigurationGraph.php` | No `LazyObject`/`WeakMap` usage. Snapshot coercion/normalization and per-node derived views could be deferred/memoized. | Consider for very large configs; not urgent given current perf. |
| F14 | Modern PHP — fibers | **N/A** | — | Fibers unused. | **Do not adopt** — synchronous, deterministic resolution is a deliberate feature (observability, diagnostics, reproducibility). |
| F15 | DRY | **L** | `ValueObject/Provenance.php` `with*`/`as*` methods | Five copy-methods each reconstruct all 12 constructor args verbatim. | Extract a private `copy(...)` helper; reduces drift risk when fields are added. |

---

## Security & Performance notes

- **F16 (Security/Performance — M).** `Core/ConfigurationCompiler.php:54` calls `hash_file('sha256', $uri)`
  for **every** discovered resource while computing the cache key. `hash_file()` opens the URI via a
  stream wrapper, so a non-file URI (e.g. `https://…`, `s3://…`, `vault://…`) will, when
  `allow_url_fopen` is enabled, trigger an **actual network/remote fetch** just to hash it — an
  unintended side effect (SSRF-shaped, latency, and "touching" a secret endpoint during key
  computation). The intended fallback (`$version = $uri`) is already correct for non-files.
  **Fix:** guard with `is_file($uri)` (or check the resource's `Format`/`scheme`) and only call
  `hash_file()` for genuine local files.
- Secrets are well handled: `isSensitive()` provenance + `RedactingRenderer` masking; decryption is
  adapter-isolated. No credential logging observed.
- Performance is otherwise sound: pipeline stages are O(n), the graph copy in `VariableProcessor` is
  bounded, reflection in `NativeConfigurationSchema::scalarTypes()` runs once at schema/freeze time.

---

## SOLID & DRY assessment

- **SRP:** Good. Largest cohesive unit is `ConfigurationBuilder` (307 lines) — acceptable as a
  composition root. `ConfigurationResolver` orchestrates but delegates every step (low method
  complexity).
- **OCP:** Strong. Sources, Loaders, Readers, Processors, Schemas, Caches, Watchers are all
  extendable through interfaces without touching Core.
- **LSP / ISP:** Interfaces are narrow and used polymorphically (`ExecutionContextAwareProcessorInterface`,
  `DeclaresScalarTypesInterface`, `ProvenanceAwareProcessorInterface`, `LayerAwareSourceInterface`).
- **DIP:** Mostly excellent — only F1/F2/F4 leak concretions into Core/Contracts.
- **DRY:** Generally low duplication; F15 is the main repetition spot.

---

## Modern-PHP leverage summary

| Feature | Used? | Assessment |
|---------|-------|------------|
| `readonly` classes/props | ✅ ~86 files | Good — keep. |
| `enum` | ⚠️ 1 (`Format`) | Expand to domain discriminators (F5). |
| Property hooks | ❌ | Optional cleanup (F12). |
| Lazy objects | ❌ | Optional for large configs (F13). |
| WeakMap / WeakReference | ❌ | Optional memoization without leaks (F13). |
| Fibers | ❌ | Not recommended (F14). |
| Traits | ⚠️ companion only | `ConfigurationAssertions` trait is a good pattern; Core needs none. |

---

## Recommended order of work

1. **F16** — guard `hash_file` behind `is_file` (security/perf, small, urgent).
2. **F1 / F2 / F4** — remove Core→Adapter/Bridge leak + concrete type-hints in the composition root.
3. **F3** — implement `ValidationExceptionInterface` (restores contract truth).
4. **F5 / F6 / F8** — introduce domain enums + constants; drop `setAccessible`.
5. **F7 / F9 / F10 / F11 / F12 / F13 / F15** — polish pass.

None of the findings block the current feature set; items 1–4 are cheap and raise the codebase from
"very good" to "exemplary" against its own AGENTS.md rules.
