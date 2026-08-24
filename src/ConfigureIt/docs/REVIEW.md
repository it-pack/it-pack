# ConfigureIt — In-Depth Architecture & Code Review

**Scope:** `src/ConfigureIt/src/**` (191 PHP files: 133 classes, 54 interfaces, 5 enums,
1 trait, 75 `readonly` classes) — features, architecture, maintenance, performance,
maintainability, security, DX, SOLID/DRY, hexagonal fit (ports/adapters/factories), and
modern-PHP leverage.

**Method:** static reading of `Core/`, `Contract/`, `Adapter/`, `Bridge/`, `ValueObject/`,
`Execution/`, `Cache/`, `Codegen/`, `CLI/`, `Wiring/`, plus `docs/` and the composer/phpstan/
cs-fixer config. Build gates assumed green (the optimization roadmap phases 0–5 are merged:
`composer analyse` 0 errors, `composer cs:check` 0/260, `composer test:unit` 267 tests /
620 assertions). Vendor code out of scope.

**Relationship to prior reviews:** the archived reviews (`docs/archive/REVIEW_1.md`,
`REVIEW_2.md`) raised four thematic issues — (1) DIP/hexagonal leaks in the composition
root, (2) underuse of enums for domain discriminators, (3) a few magic strings/numbers, and
(4) a PHP 8.5 `setAccessible` deprecation plus a remote-fetch side effect in cache-key
hashing. **All four are now resolved** (Phases 1–5: F1/F2/F4, F5/F6/F8/F9, F7, F15). This
review re-confirms those fixes and evaluates the codebase against the full requested
checklist, including several areas the earlier reviews did not deeply cover (security posture,
performance, modern-PHP opportunities, interface coverage, complexity).

**Assumptions**
- Targets `php: ^8.5`; 8.4+ (property hooks, lazy objects) and 8.1+ (Fibers, WeakMap) are
  available but optional.
- `mixed` for heterogeneous configuration trees is a domain necessity, not a defect.
- Quantitative claims (`else` count, readonly/enum counts) come from repo-wide greps.

---

## Verdict

**Exemplary, production-grade, and now fully aligned with its own `AGENTS.md` standards.**
Hexagonal discipline is enforced; the composition root is clean; domain discriminators are
enum-backed; magic values are extracted to constants; and the one security/forward-compat
risk (remote fetch during hash, deprecated `setAccessible`) is gone. Remaining items are
minor, mostly stylistic or optional-modernization opportunities with low risk, and none block
the feature set.

---

## Strengths

- **Hexagonal layering is correct and enforced.** `Contract/` imports zero Adapter/Bridge
  types (verified). Core depends only on Contracts + ValueObjects; `Adapter/` isolates
  third-party libraries (e.g. `NativeConfigurationSchema` translates `nette/schema` vendor
  exceptions into a domain `ValidationResult`) and `Bridge/` depends only on Core contracts.
- **Composition root is isolated.** `ConfigurationBuilder` depends on
  `ConfigurationWiringInterface`; `Wiring/DefaultConfigurationWiring` is the *single* place
  that `new`s `CompositeReader`, `DecryptingReader`, `OpenTelemetryReporter`. This resolved
  the earlier composition-root leak.
- **Ports are interface-backed.** Resolver, Factory, Pipeline, Graph, Node, Snapshot, Source,
  Reader, Loader (+ factory), Processor, Schema, Cache, Watcher, Document, Reporter,
  Provenance, ValidationResult, Resource, OccurrenceFactory, Wiring — all behind contracts.
  Factories are `final readonly` and interface-backed.
- **Domain discriminators are enums** (`Format`, `Severity`, `OccurrenceType`, `SourceKind`,
  `ProcessorStage`), each mirroring `Format::fromString` so deserialization stays safe and
  `->value` preserves the serialized-cache wire format. `Format::UNKNOWN` honestly reports a
  missing format instead of mislabeling it `PHP`.
- **Immutability by default.** 75 `readonly` classes; snapshots/nodes/provenance/VOs are
  immutable; builders are mutable by design only.
- **Security-hardened cache key.** `ConfigurationCompiler::hash()` guards `hash_file()` behind
  `is_file()` (F16), so `https://`/`vault://`/`s3://` URIs are no longer transparently fetched
  through stream wrappers during key computation — an SSRF-shaped side effect removed.
- **Modern-PHP baseline is strong.** `readonly`, enums, `match`, first-class `iterable`/`list`,
  lazy proxy (`OccurrenceFactory::createLLazy()`), and `array_any` are used appropriately.
- **Documentation is mature.** `PORTS.md` now documents every port with the
  Purpose/Responsibilities/May-depend-on/May-not-depend-on/Example template; ADRs are
  referenced throughout; tests are broad (267) and behavior-focused.

---

## Findings

Severity: **H**igh / **M**edium / **L**ow. "Resolved" items were fixed during the optimization
roadmap and are listed for traceability.

| # | Area | Finding | Severity | State |
|---|-------|---------|----------|-------|
| R1 | Security / Perf | `hash_file()` fetched remote URIs during cache-key computation | H | ✅ Resolved (F16 `is_file` guard) |
| R2 | Forward-compat | `ReflectionProperty::setAccessible(true)` deprecated no-op on 8.5 | M | ✅ Resolved (F8) |
| R3 | Hexagonal / DIP | Core built adapters/bridges directly | M | ✅ Resolved (F1/F2/F4 → `DefaultConfigurationWiring`) |
| R4 | Discriminators | raw `string` level/type/sourceKind/stage | M | ✅ Resolved (F5/F6/F9 enums + `Format::UNKNOWN`) |
| R5 | Magic strings/numbers | `'unknown'`, `'programmatic'`, `0` priority, `sha256` | L | ✅ Resolved (F6/F7/F9 constants) |
| R6 | DRY | `Provenance` mutators duplicated 12-arg constructors | L | ✅ Resolved (F15 `copy()` helper) |
| R7 | DIP (intra-Core) | `ConfigurationResolver` depends on concrete `ConfigurationPipeline`, not `ConfigurationPipelineInterface` | L | Open |
| R8 | DIP (composition root) | `ConfigurationBuilder::build()` directly `new`s `ResolutionReporter`, `ExecutionContext`, `CachingResolver`, `ConfigurationCompiler`, `LayeredSource` | L | Open |
| R9 | Magic strings | Processor `stageName()` literals (`'ValidationProcessor'`, `'CastingProcessor'`, `'IncludeProcessor'`, `'VariableProcessor'`, …) not enum-backed (only `DefaultsProcessor` uses `ProcessorStage`) | L | Open |
| R10 | Magic strings | `get_class($loader)` / `get_class($schema)` used as stage/identity discriminators | L | Open |
| R11 | Code hygiene | `CastingProcessor::cast()` has a stray duplicate `/** @return */` docblock preceding its real docblock | L | Open |
| R12 | Error handling | `CachingResolver` swallows `catch (\Throwable)` on best-effort cache write (silent) | L | Open |
| R13 | `else` usage | 3 `else` blocks (`FileModificationProbe`, `DiagnosticsReport`, `FileSystemCache`); guideline prefers branchless/early-return | L | Open |
| R14 | Modern PHP | Property hooks / virtual properties unused; `Provenance`/`ConfigurationNode` getters mirror properties (interface name collision documented in roadmap F12 — intentionally skipped) | L | Intentional |
| R15 | Modern PHP | WeakMap / lazy memoization of derived node views unused (roadmap F13 scoped to "only if profiling shows cost" — no signal yet) | L | Intentional |
| R16 | Modern PHP | Fibers intentionally not adopted (deterministic, synchronous resolution is a deliberate feature — roadmap F14) | — | Intentional |
| R17 | SOLID/extensibility | A few internal Core utilities (`ScalarCaster`, `RedactingRenderer`, `ConfigurationCompiler`) are not interface-backed; acceptable as internal helpers but could be opened up if external customization is desired | L | Open (optional) |
| R18 | DRY | Per-processor "build new graph + loop nodes + `withProvenance`" shape repeats; a shared `AbstractNodeRewritingProcessor` base would remove the duplication | L | Open (optional) |

---

## Dimension notes

### Features
The SDK covers the full configuration lifecycle: multi-source discovery, format loaders
(JSON/YAML/TOML/NEON/INI/XML/ENV/DOTENV/FRONTMATTER/PHP), layered composition, a processor
pipeline (defaults, casting, includes, variables, validation, freeze), schema validation
(Nette-backed), execution observability (Occurrences/Observations/Reporter), drift detection,
hot-reload, secret/sensitive redaction, codegen, and compiled-artifact caching. Feature
surface is broad and coherent.

### Architecture & hexagonal fit
Excellent. Dependency direction is correct end-to-end: `Adapter`/`Bridge` → `Contract`
(never the reverse); Core → `Contract`/`ValueObject`. The only intra-Core concrete dependency
(R7) and the composition-root concretions (R8) are low-risk because they stay inside the
composition layer; they are noted for purity, not correctness.

### Factories & ports/adapters
Factories (`ConfigurationFactory`, `ConfigurationDocumentFactory`, `ConfigurationLoaderFactory`,
`ConfigurationSnapshotFactory`, `OccurrenceFactory`, `ConfigurationResourceFactory`,
`TempFileFactory`, `MessageFactory`) are consistently `final readonly`, interface-backed, and
used as injection points — textbook ports/adapters. The `CompositeReader` (first-match by
`supports()`) and `CachingResolver` (decorator) are exemplary patterns.

### SOLID
- **SRP:** classes are small and single-purpose (e.g. `CastingProcessor` only casts; `DefaultsProcessor`
  only fills missing keys).
- **OCP:** the pipeline is open to new `ConfigurationProcessorInterface` implementations without
  touching Core.
- **LSP:** implementations are substitutable behind their contracts.
- **ISP:** interfaces are fine-grained (e.g. `ProvenanceAwareProcessorInterface` layered on top of
  `ConfigurationProcessorInterface`).
- **DIP:** satisfied at the port boundary; the two minor leaks (R7, R8) are internal.

### DRY
Good. The `copy()` helper (R6 resolved) and `CreatableBehavior` trait remove the worst
duplication. Remaining repetition is the processor node-rewrite shape (R18, optional).

### Code-quality invariants
- **`else`/`elseif`:** only 3 `else` blocks in the whole source tree (R13) — well within the
  "avoid else" guideline; all benign string/type assembly.
- **Magic strings/numbers:** only the processor `stageName()` literals (R9) and `get_class`
  discriminators (R10) remain; constants were already extracted for format/priority/hash-algo/
  unknown-adapter (R5 resolved). Numbers like `json_decode(..., 512, …)`, `intervalMs = 1000`,
  and `MaxResults => 100` are standard serialization/API constants, not architectural magic
  numbers.
- **Complexity:** methods are short; the resolver is a coordinator of small private methods; no
  "god classes" observed. Cyclomatic complexity is low across Core/Adapters/Bridges.

### Security
- Remote-fetch side effect removed (R1).
- Vendor exceptions are translated at the adapter boundary; domain exceptions (`ValidationException`
  implementing `ValidationExceptionInterface`, `CastException`, etc.) are thrown instead.
- Schema/cast validation fails fast, preventing silently wrong-typed values from shipping.
- Decryption is isolated in the adapter layer (`Decryption/*`) and wired only when opted in.
- Minor: `CachingResolver` swallows cache-write exceptions silently (R12) — acceptable as
  best-effort, but a logger/telemetry signal would improve operability.

### Performance
- Opt-in compiled-artifact caching (`CachingResolver` + `FileSystemCache`) avoids re-resolution
  when inputs are unchanged.
- Lazy proxy for `OccurrenceFactory` defers construction.
- No current need for WeakMap/lazy memoization (R15) — added only if profiling shows a hot path.

### Maintainability & DX
- Strong docblocks, ADR references, and the now-complete `PORTS.md`.
- cs-fixer (`@PSR12` + `@PHP84Migration` + `no_superfluous_elseif`) and PHPStan L9 + ShipMonk
  dead-code keep the surface consistent.
- Tests are broad and behavior-focused; the optimizer work added round-trip tests for enums and
  the unknown-format sentinel.

### Modern PHP leverage
- **Adopted well:** `readonly`, enums, `match`, lazy proxy, `array_any`, typed constants
  (`private const string HASH_ALGO`).
- **Intentional non-adoption:** property hooks (interface name collision, R14), WeakMap/lazy
  (no profiling need, R15), fibers (determinism is a feature, R16). These are defensible.
- **Opportunity:** extending `ProcessorStage` to cover the remaining processor names (R9) would
  complete the enum migration for stage discriminators.

---

## Recommendations (priority order)

1. **R9 — enum-back processor stage names.** Extend `ProcessorStage` with
   `Validation`, `Casting`, `Include`, `Variable`, `Freeze`, … and have each processor's
   `stageName()` return the `->value`. Removes the last cluster of magic stage strings and keeps
   the processor-trail format stable.
2. **R7 — depend on `ConfigurationPipelineInterface`** in `ConfigurationResolver` for stricter DIP
   (purely internal, low risk).
3. **R8 — move the `new ResolutionReporter()` / `new ExecutionContext()` instantiations** into
   `DefaultConfigurationWiring::composeReporter()` so the builder holds no Execution concretions.
4. **R11 — delete the duplicate `/** @return */` docblock** in `CastingProcessor::cast()`.
5. **R12 — surface cache-write failures** via the reporter/telemetry instead of a silent
   `catch (\Throwable)`.
6. **R13 — convert the 3 `else` blocks** to early-return/branchless form to fully honor the
   no-`else` guideline (optional, cosmetic).
7. **R18 (optional) — extract `AbstractNodeRewritingProcessor`** if more processors accumulate
   the same "clone graph + loop nodes" shape.
8. **R17 (optional) — consider interfaces for `ScalarCaster`/`RedactingRenderer`** only if
   external customization is anticipated; otherwise leave as internal helpers.

None of the open items are blocking; the codebase already meets the "exemplary against
`AGENTS.md`" bar the optimization roadmap targeted.
