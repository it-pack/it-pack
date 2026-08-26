# ConfigureIt — In-Depth Architecture & Code Review (Review 4)

**Scope:** `src/ConfigureIt/src/**` and `tests/**` (283 PHP files; 132 classes under `src/`,
75 of them `final readonly`, 36 contracts, 5 enums, 1 trait, 1 abstract class) — features,
architecture, maintenance, performance, maintainability, security, DX, SOLID/DRY,
hexagonal fit (ports/adapters/factories), modern-PHP leverage, interface backing, code
hygiene (branch style, magic literals, Yoda, imports, declaration order, PSR-4, 120-col),
and test-suite health (coverage ≥ 95 %, isolation, mutation testing).

**Method:** mechanical audits over the whole tree plus targeted reading.

- Tokenizer-based structural audit of every `src/` file: declared types per file,
  abstract usage, interfaces implemented, and member order bucketed as
  traits → constants → properties/hooks → constructor → factories → instance methods →
  static methods (public before non-public within methods).
- Repo-wide greps: `else`/`elseif`, Yoda comparisons, lines > 120 columns, inline
  fully-qualified references (comment-stripped), `new` instantiations outside
  factories/builders/wiring/codegen, risky runtime calls (`proc_open`, `curl_*`,
  `file_get_contents`), modern-PHP feature markers (hooks, `WeakMap`, `Fiber`,
  `newLazy*`, traits, `match`, first-class callables).
- Gates re-run this session: `composer analyse` **[OK] no errors** (PHPStan level 9 +
  ShipMonk dead-code), `composer cs:check` **0 of 263 files fixable**, `composer test:unit`
  **OK — 276 tests, 636 assertions**.
- Coverage measured with the pcov driver via `composer coverage`
  (`tests/_output/coverage.xml` parsed; numbers below).
- Deep reads: resolver/pipeline coordination, composition root (`DefaultConfigurationWiring`),
  builder imports, cache stack, decryption chain, `NativeCommandRunner`, `CurlHttpClient`,
  `ConsulClient`, `IncludeProcessor`, enterprise client auth posture, sample unit tests.

**Assumptions**

- The ≥ 95 % requirement is interpreted as statement/element coverage over `src/*`
  (the `codeception.yml` include set), measured with the project's own `coverage` script.
- `mixed` for heterogeneous configuration trees remains a domain necessity, not a defect.
- Examples (`examples/`) are illustrative scripts and are exempted from hygiene counts;
  findings there are noted only where they model bad practice.
- Mutation testing was assessed for feasibility; it was **not** force-run against an
  incompatible harness (see NV19).

**Relationship to prior reviews:** the open items of `docs/archive/REVIEW_3.md` were
re-verified: **R7 resolved** (resolver depends on `ConfigurationPipelineInterface`),
**R8 resolved** (reporter/context/cache-decorator composed exclusively inside
`DefaultConfigurationWiring`), **R9 resolved** (processors return `ProcessorStage->value`;
e.g. `IncludeProcessor::stageName()`), **R11 resolved**, **R12 resolved**
(`CachingResolver` now observes a `Severity::Warning` "Cache write failed" instead of
silently swallowing), **R13 resolved** (zero `else` in `src/`), **R18 resolved**
(Milestone 4 extracted the node-rewrite shape), **R19 resolved** (Milestone 5 replaced the
`CommandDecrypter` inheritance with `CommandDecryption` composition). **R10 resolved**
(four `get_class()` discriminators replaced by `name()` in Milestone 5) and **R17 remains open-by-choice** (`ScalarCaster`,
`RedactingRenderer`, `ConfigurationCompiler`, `Key` stay internal/un-interfaced).
This review introduces a fresh finding series (**NV***).

---

## Verdict

**Architecturally excellent and security-conscious at the port level, but it currently
fails two of its own stated quality bars:** coverage is **82.9 %** (target ≥ 95 %) and
mutation testing is **not leveraged at all**. One abstract class survives, roughly a
quarter of the classes lack interface backing, and the new Enterprise adapters carry
real-but-fixable transport-security sharp edges (redirect header forwarding, plaintext
Consul/etcd, world-writable cache directories). Everything else the checklist demands —
hexagonal discipline, factory/composition-root purity, zero `else`, zero Yoda, PSR-4
one-type-per-file, low complexity, modern-PHP baseline — is met or very nearly met, and
several conventions (declaration order, import completeness) hold at 90 %+ without any
mechanical enforcement. Priority order for the next milestone: close the coverage gap on
the five zero-covered adapters, harden the three transport paths, wire the missing QA
tooling (infection/deptrac/phpmnd/cs-fixer rules) so the manual conventions become gates.

---

## Strengths

- **Hexagonal layering is real, not aspirational.** Vendor isolation holds: zero Nette/
  Aura/Dotenv/Webuni/Internal imports in `Core/` or `Contract/`. Adapters translate vendor
  exceptions into domain exceptions at the boundary. `DefaultConfigurationWiring` is the
  single instantiation point for adapter/bridge concretes behind
  `ConfigurationWiringInterface`; the builder itself stays concretion-free apart from the
  wiring *default* (NV9c).
- **Ports are fine-grained and consistently injected** — reader/loader(+factory)/source/
  processor/schema/resolver/snapshot-factory/cache/reporter/execution-context/command-runner/
  decrypter/http-client/telemetry-sink. Decorators (`CompositeReader`, `DecryptingReader`,
  `CachingResolver`, `OpenTelemetryReporter`, `TypedSnapshot`) compose behavior cleanly.
- **Enterprise auth design is right:** cloud clients (Vault, AWS SSM, GCP, Azure) speak
  `https://` and receive credentials via injected `HttpClient` headers — signing/token
  concerns live in decorating transports, not in the clients (contrast NV6).
- **Code-hygiene conventions are honored far above average without enforcement:**
  zero `else`/`elseif`, zero Yoda conditions, strict comparisons everywhere, PSR-4
  one-type-per-file at 100 % (audited), declaration-order conformance in 125 of 132
  classes, constants/enums extracted for formats, priorities, stages, severities,
  hash-algo, schemes.
- **Immutability by default:** 75 `final readonly` classes; snapshots, nodes, provenance,
  diffs, diagnostics are immutable value objects; builders are the only mutable surfaces.
- **Modern PHP 8.5 baseline:** enums with safe `fromString` sentinels (`Format::UNKNOWN`),
  `match` throughout, lazy-object proxy (`OccurrenceFactory::createLLazy()` via
  `newLazyProxy`), typed constants, first-class callables, property-hooks-ready readonly
  shapes.
- **DX is mature:** 16 runnable `examples/*.php` with README, complete
  `docs/architecture/PORTS.md` port catalog, ADR references inline, CHANGELOG discipline
  per milestone, archived plans/reviews, fast local gates.

---

## Findings

Severity: **H**igh / **M**edium / **L**ow / **I**nfo. All findings are currently **Open**
unless marked otherwise. Findings closed by later work carry an inline
`[✅ Resolved — <milestone>]` marker at the end of their finding text
(`[✅ Partially resolved …]` where residue remains).

| # | Area | Finding | Severity |
|---|------|---------|----------|
| NV1 | Test quality | Line coverage **82.90 %** elements / **84.75 %** statements / **74.08 %** methods — below the required 95 %. 395 uncovered statements; 5 files with **zero** covered statements (`FileSystemDocumentCache` 32, `NativeCommandRunner` 27, `CurlHttpClient` 27, `FeatureFlagSchema` 12, `MessageFactory` 9); worst partially-covered: `ConfigurationDiff` 11 %, `DiagnosticsReport` 12 %, `Schema` adapter 22 %, `ReloadableConfiguration` 72 %, `FsWatcher` 75 %, `CastingProcessor` 77 %, `ScalarCaster` 78 % | **H** [✅ Resolved — Milestone 3] |
| NV2 | Security | `FileSystemCache::__construct` creates cache directories with mode **`0o777`** (world-writable). Artifacts contain the *resolved, decrypted* configuration including secrets. Should be `0o770`/`0o700`; also consider atomic writes (temp file + rename) so readers never observe torn artifacts | **H** [✅ Resolved — Milestone 1] |
| NV3 | Security/robustness | `NativeCommandRunner` passes `implode(' ', array_map('escapeshellarg', …))` to `proc_open` — a **string** command goes through `/bin/sh`, contradicting its own docblock ("no shell is invoked"). Passing the array directly would bypass the shell entirely. Additional robustness gaps: stdin is written fully before stdout is drained (classic pipe-buffer deadlock once output exceeds ~64 KB while input is large), no process timeout, unchecked `fwrite`, and raw stderr is embedded in the thrown message (may echo secret-bearing tool output into logs) | **M** [✅ Resolved — Milestone 1] |
| NV4 | Security/perf | `CurlHttpClient` sets `CURLOPT_FOLLOWLOCATION => true` while callers pass signed/authenticated headers — redirects forward those headers to whatever host is redirected to (credential-leak shape; disable follow-location or strip sensitive headers per hop). Also magic `CURLOPT_TIMEOUT => 10` (violates the no-magic-literals convention; should be a named constant/injectable) and no `CURLOPT_CONNECTTIMEOUT` | **M** [✅ Resolved — Milestone 1] |
| NV5 | Security | `IncludeProcessor::include()` builds `$baseDirectory . '/' . ltrim($relative, '/')` — `..` segments are never resolved, so `"@include ../../outside.yaml"` escapes the base directory. Add realpath-based containment (or explicit allowlist) before reading | **M** [✅ Resolved — Milestone 1] |
| NV6 | Security/DX | `ConsulClient` and `EtcdClient` hardcode `http://` endpoints (no TLS option), unlike every other enterprise client. KV config traffic travels plaintext; accept scheme/port from the URI or add explicit TLS knobs | **M** [✅ Resolved — Milestone 1] |
| NV7 | Standards | Exactly one `abstract` type remains: `Core\AbstractNodeRewritingProcessor` (introduced deliberately in Milestone 4/R18, after this criterion was set). It declares an abstract `rewrite()` hook, i.e. genuine template-method usage — but the stated bar is "no abstract classes; prefer traits/composition". Convert to a composition helper (like `CommandDecryption` was for M5) or a trait | **M** [✅ Resolved — Milestone 5] |
| NV8 | Interface backing | 30 of 132 classes implement no interface. Triage: **should be opened up** (used across layer boundaries): `MessageFactory`, `ConfigurationLoaders`, `ConfigurationBuilder`, `ReloadableConfiguration`, `ConfigurationDiffer`, `ConfigurationDiagnostics`, `CommandDecryption`; **internal helpers, acceptable but track**: `ScalarCaster`, `RedactingRenderer`, `ConfigurationCompiler`, `Key`, `FileModificationProbe`, `TreeBuilder`, `FeatureFlagSchema`, `HttpResponse`, `SemanticConventions`; **value objects / enums-shaped data**: `ConfigurationDiff`, `DiagnosticsReport`, `FlagDefinition`, `Property`, `DtoModel`; **CLI boundary**: `Output`, `ConfigurationInspector`, `GraphFromFile`, `GraphVisualizer`, `ConfigurationCli`; **codegen internals**: `SchemaModelBuilder`, `ConfigCodegen`, `ConfigDtoHydrator`, `PhpRenderer` | **M** [✅ Resolved — Milestone 5] |
| NV9 | DIP (layer direction) | Three directional wrinkles: (a) `Contract/ConfigurationSnapshotFactoryInterface` imports the **concrete** `Core\ConfigurationSnapshot` — move the snapshot contract fully into `Contract/` or accept the VO exception explicitly; (b) `Contract/Wiring/ConfigurationWiringInterface` reaches into `Bridge\OpenTelemetry\Contract` — either promote `TelemetrySinkInterface` into top-level `Contract/` or document the bridge-contract exception; (c) `Core\ConfigurationBuilder` imports the concrete `Wiring\DefaultConfigurationWiring` as its fallback — inject the default from the public entry point instead of the Core | **M** |
| NV10 | Instantiation policy | "Instances are created only in factories/builders" is violated by pervasive **constructor-default instantiation**: `= new OccurrenceFactory()`, `= new ConfigurationDocumentFactory()`, `= new ConfigurationSnapshotFactory()` (resolver), `= new DocumentNormalizer()` / `new ConfigurationResourceFactory()` / `new ExecutionContext()` (include processor), `= new Processor()` / `new ValidationResultFactory()` (schema adapter), `= new RedactingRenderer()` (differ), `= new ConfigurationDiffer()` (drift detector), `= new ConfigurationSnapshotFactory()` (file cache). Domain processors additionally construct value objects inline (`new ConfigurationNode/Provenance/ValidationMessage/Observation/TypedSnapshot`). Pragmatically defensible for immutable domain VOs, but the strict bar requires factories/builders passed via DI — resolve by declaring an intentional carve-out ("VOs exempt") or routing through the existing factories | **M** |
| NV11 | Test isolation | Unit suite contains true integration tests: `ConfigurationResolverTest` boots the whole builder stack with concrete `FileReader/FileSource/JsonLoader/PhpLoader/NativeConfigurationSchema` **and imports `Nette\Schema\Expect` directly**. A JsonLoader regression would fail Core tests — coverage leakage across SUT boundaries. Split an `Integration` (or `Conformance`) suite and keep `Unit/` SUT-local | **M** [✅ Resolved — Milestone 3] |
| NV12 | Declaration order | 7 files deviate from the agreed member order: `ConfigurationSnapshot` (public accessors after privates; `fromArray()` factory at the end), `DocumentNormalizer` (`private const SECRET_SCHEMES` after methods), `Provenance` (`fromArray()` after instance methods), `Output` and `Key` (public methods after statics), `NativeConfigurationSchema` (property declared after the constructor), `IncludeProcessor` (private helpers after a static method) | **M** [✅ Resolved — Milestone 4] |
| NV13 | Vocabulary homes | Constants/enums exist, but **none live in interfaces**: the ten const-defining files are all concrete classes (`VariableProcessor`, `DocumentNormalizer`, `DefaultsProcessor`, `ConfigurationPipeline`, `ConfigurationNode`, `GraphFromFile`, `FsWatcher`, `VolumeReader`, `KubernetesSecretSource`, `DockerSecretsSource`) and the five vocabulary enums live in `ValueObject/`, not `Contract/`. The stated convention ("vocabularies live in interfaces") suggests hoisting shared vocabularies onto their port interfaces or dedicated `Contract` enums | **M** [✅ Resolved — Milestone 5] |
| NV14 | Readability | ~41 source lines exceed 120 columns (excluding generated test support): clusters in `Codegen/PhpRenderer` (up to 161), `Codegen/SchemaModelBuilder` (6 lines), `Core/ConfigurationDriftDetector` (4), `Core/ConfigurationDiagnostics` (154 chars), `ValueObject/ConfigurationDiff` (156), `Adapter/Enterprise/Contract/HttpClient` (158), `Core/IncludeProcessor` (156) | **L** [✅ Resolved — Milestone 4] |
| NV15 | Import completeness | Comment-filtered scan finds **71 distinct global symbols referenced without `use`/`use function` imports** (~130 occurrences): `\InvalidArgumentException` ×15, `\Throwable` ×14, `\RuntimeException` ×14, `\is_string` ×13, `\is_array` ×11, `\is_scalar` ×10, `\sprintf` ×9, `\count` ×8, plus `basename`, `trim`, `error_reporting`, … Class imports are clean (cs-fixer enforces ordering/no-unused); function/global-class imports have no enforcement (`global_namespace_import` fixer + `use function` policy absent) | **L** [✅ Resolved — Milestone 4] |
| NV16 | Magic literals | Residual magic values despite good constant discipline: four `get_class()` identity discriminators (`ConfigurationCompiler:94`, `ConfigurationResolver:231`, `ConfigurationPipeline:107`, `GraphFromFile:60` — prior-review R10, still open); occurrence names as inline strings (`'ConfigurationValidated'`, `'ConfigurationResolved'`, `'SnapshotCreated'`); the `env|tenant|profile` condition vocabulary hardcoded twice inside `IncludeProcessor`; `CURLOPT_TIMEOUT => 10`; `'include:' . $relative` source-prefix string | **L** [✅ Resolved — Milestone 5; closes R10] |
| NV17 | Robustness | `FileSystemCache`: writes are non-atomic (torn reads fall back to re-resolution rather than failing loudly); `path()` performs no key-charset validation (currently safe because keys come from the compiler's sha256 hash, but nothing enforces it); `@include` directive parsing requires a trailing space after the directive keyword | **L** [✅ Partially resolved — Milestone 1 (atomic writes + key validation); directive-spacing nit remains] |
| NV18 | Toolchain | `pdepend`/`phpmd` fatal-error while parsing the PHP 8.5 syntax used by this repo (engine lag) — complexity had to be assessed qualitatively. `phpmnd`, `deptrac`, `phpcs`, `phpbench` ship in the QA base but none are wired into composer gates; cs-fixer lacks `yoda_style`, `global_namespace_import`, `ordered_class_elements`, which would mechanize three of the stated house conventions | **L** [✅ Resolved — Milestone 4] |
| NV19 | Mutation testing | Not leveraged: `vendor/bin/infection` exists (QA base) but there is **no config or gate**, and Infection cannot drive this suite out of the box — its runners speak PHPUnit/Phpspec only, and replacing Codeception with PHPUnit was evaluated and **rejected** as project policy (~73 test files would change for tooling reasons alone). Remediation: adopt the sibling `RunIt` strategy — `infection/codeception-adapter` (published, maintained) lets Infection drive `codecept run Unit` natively; wire `infection.json5` + `mutation` composer scripts per `docs/REFACTORING_PLAN.md` Milestone 2, with MSI floors provisional until a CI baseline exists (local pcov runs do not narrow covering tests — same constraint documented on RunIt) | **M** [✅ Resolved — Milestone 2] |
| NV20 | Modern PHP (info) | Deliberate non-adoptions, consistent with prior reviews: property hooks/virtual properties unused (getter mirrors remain; name-collision rationale documented), WeakMap memoization unused (no profiling signal), Fibers rejected for deterministic synchronous resolution, lazy objects used once (`OccurrenceFactory`). Trait usage minimal (exactly one: `CreatableBehavior`) — composition is doing the work traits otherwise would | **I** |
| NV21 | Edge case | `ConfigurationResolver::resolveResources` treats `$sources === []` as "use defaults" — a generator (or any non-array iterable) is never `=== []`, silently ignoring empty generators | **L** [✅ Resolved — Milestone 4] |
| NV22 | Cosmetic | Double blank line in `IncludeProcessor` (before `include()` docblock); `DefaultConfigurationWiring::composeReporter` places the opening brace on the signature line unlike the rest of the codebase; tests create scratch dirs with `0o777` (harmless in temp, but inconsistent with a hardened posture) | **L** [✅ Resolved — Milestone 4] |

---

## Criteria scorecard

| Stated criterion | Status | Evidence |
|---|---|---|
| Features & architecture coherent | ✅ Met | Dimension notes below; full lifecycle from sources → loaders → pipeline → schema → snapshot → observability → caching/codegen/CLI |
| Hexagonal ports/adapters/factories | ✅ Met (wrinkles) | Layer greps clean of vendor leaks; NV9a-c directional nuances |
| SOLID | ✅ Met (DIP nuances) | Small focused classes; ISP via layered processor contracts; DIP except NV9/NV10 |
| DRY | ✅ Met | R18 extraction merged; `copy()` helper; remaining duplication negligible |
| Every class interface-backed | ✅ Met | Cross-boundary services + value-object contracts now expose interfaces (NV8, Milestone 5); CLI/codegen internals exempt by design |
| No abstract classes | ✅ Met | 0 abstract classes in `src/` (`NoAbstractClassesTest`, NV7, Milestone 5) |
| Prefer traits/composition | ✅ Met | Composition dominant; single helper trait; no inheritance chains left after M5 |
| Low complexity | ✅ Met | Coordinator-style resolver, small private steps, shallow nesting (pdepend unavailable — qualitative; NV18) |
| No `else`/`elseif` | ✅ Met | 0 occurrences in `src/` |
| No magic strings/numbers | ✅ Met | `get_class` discriminators removed; occurrence names enum-backed (NV16, Milestone 5) |
| Vocabularies in interfaces | ⚠️ Partial | Rule encoded in AGENTS.md; occurrence calls enum-backed (NV13, M5); enum homes remain in `ValueObject/` by design |
| No Yoda conditions | ✅ Met | 0 matches (and `strict_comparison` enforced) |
| All references imported at top | ✅ Met | Classes + functions imported via cs-fixer `global_namespace_import`; 0 unimported global symbols (NV15, Milestone 4) |
| Instances only via factories/builders + DI | ⚠️ Partial | Composition root exemplary; constructor-default news pervasive (NV10) |
| Declaration order (traits→consts→props→ctor→factories→methods→statics; public first) | ✅ Met | Enforced by `ordered_class_elements`; the 7 NV12 deviations were auto-fixed (Milestone 4) |
| PSR-4, one type per file | ✅ Met | Audited: 0 multi-type files |
| Max 120 columns | ✅ Met | NV14 — 0 violations under `composer lint:length` (Milestone 4) |
| Coverage ≥ 95 % | ✅ Met | NV1 — now 98.07 % statements / 97.35 % elements (Milestone 3) |
| Tests leak no foreign coverage | ✅ Met | Unit = one SUT + fakes; stack tests relocated to the Integration suite (NV11, Milestone 3) |
| Mutation testing leveraged | ✅ Met | NV19 — Infection × `codeception-adapter` wired; `composer mutation` runs (Milestone 2) |

---

## Dimension notes

### Features
The SDK covers the whole configuration lifecycle: multi-source discovery (files, env,
dotenv, HTTP, programmatic), format loaders (JSON/YAML/TOML/NEON/INI/XML/ENV/FRONTMATTER/
PHP), layered priority merging, a processor pipeline (defaults → include → variables →
casting → validation → freeze), optional schema validation (Nette-backed, exception-
translating), typed snapshots, provenance on every value, execution occurrences/
observations with reporter + telemetry sink, drift detection/diff/diagnostics, hot-reload
watcher, decryption-aware reading, compiled-artifact caching, feature flags, codegen
(DTO/schema rendering), and a CLI (inspect/graph/diff). Feature surface is broad,
coherent, and example-backed.

### Architecture & hexagonal fit
Dependency direction is correct end-to-end and mechanically verifiable: `Contract/`
imports no vendor code; `Core/` imports neither Adapter nor Bridge concretes (builder's
wiring default aside — NV9c); adapters isolate third parties and translate exceptions.
The wiring port keeps the composition root swappable. The residual items are namespace
hygiene (NV9), not behavioral coupling.

### Factories & ports/adapters
Factories (`ConfigurationFactory`, `ConfigurationDocumentFactory`,
`ConfigurationSnapshotFactory`, `OccurrenceFactory` with lazy proxy,
`ConfigurationResourceFactory`, `ValidationResultFactory`) are `final readonly` and
interface-backed — including `MessageFactory` (now `MessageFactoryInterface`, NV8). Named
static constructors (`fromArray`, `for*`) consistently appear where expected. The gap is
not the factories that exist; it is the constructor-default instantiations that route
around them (NV10).

### SOLID
SRP and ISP remain excellent (fine-grained processor/reporter/source contracts; decorator
stacks). OCP holds — new sources/loaders/processors plug in without Core edits. LSP is
untested formally (no conformance suite for port implementations beyond ad-hoc fakes —
worth adopting the sibling-project RunIt verifier pattern). DIP has the NV9/NV10 nuances.

### DRY
Healthy. The M4 extraction removed the processor rewrite-shape duplication; M5 removed
the decryption inheritance duplication; `Provenance::copy()` centralizes mutators. No new
duplication clusters observed during this pass.

### Code hygiene
Zero `else`, zero Yoda, short methods, consistent naming. The gaps are exactly the ones
without mechanical enforcement: column budget (NV14), function-import policy (NV15),
member order (NV12), residual literals (NV16). All four are one-time cleanup plus one
cs-fixer rule away from being permanently closed.

### Complexity
Qualitatively low: coordinators delegate to small private steps; the deepest method
logic lives in normalizers/differ tree-walks, which read linearly. Objective measurement
is currently blocked by the pdepend/phpmd 8.5 parser crash (NV18) — re-run once the QA
base updates.

### Security
Strong foundations: SSRF-shaped remote fetch already removed from cache-key hashing;
decryption isolated behind `DecrypterInterface` with vendor exception translation;
enterprise clients delegate auth to decorated transports over https (except NV6);
fail-fast validation prevents silent type coercion. This pass adds four concrete
transport/storage hardening items: world-writable cache dirs holding decrypted material
(NV2), shell-invoking command runner with deadlock/timeout exposure (NV3),
redirect-following credential forwarding (NV4), and `@include` base-directory escape
(NV5). None undermine the architecture; all are localized fixes.

### Performance
Opt-in compiled-artifact caching with opcache-friendly artifacts; lazy occurrence factory;
polling watcher with mtime probe. No pathological patterns observed; no WeakMap/lazy
memoization need demonstrated. `proc_open` deadlock (NV3) is both a security and a
performance cliff for large payloads.

### Maintainability & DX
Documentation depth (PORTS.md, ADRs, EXECUTION/VISION, CONTRIBUTING, archived plans),
milestone CHANGELOG discipline, runnable examples, and three fast gates make the project
easy to onboard onto. The maintenance risk concentrates in un-enforced conventions
(hygiene regressions can slip past CI) and the coverage/mutation blind spot — both
addressable by wiring the already-installed tools (NV18/NV19).

### Modern PHP leverage
Adopted well: `readonly` classes (75), string enums with safe deserialization, `match`,
lazy proxy, typed constants, first-class callables, constructor promotion everywhere.
Available headroom: property hooks could remove getter boilerplate on `Provenance` /
node views if the interface-naming collision is ever lifted; WeakMap memoization of
derived graph views awaits a profiling signal; fibers remain intentionally out (sync
determinism is a feature). Trait usage is minimal and appropriate.

### Test suite health
276 tests / 636 assertions, green, behavior-oriented, fast (~29 s with coverage). Fakes
(`FakeCommandRunner`, `FakeHttpClient`, recording runners) keep tests hermetic — leakage
is structural (integration-in-unit, NV11) rather than mock-abuse. Coverage gaps cluster
predictably around I/O adapters and newer features (NV1 list): the fastest path to 95 %
is covering the five zero-covered files plus `ConfigurationDiff`/`DiagnosticsReport`
pure logic, which need no new infrastructure. Mutation testing requires the NV19 harness
work first; until then MSI is unknown and unmonitored.

---

## Recommendations (priority order)

1. **Close the coverage gap (NV1).** Target the five 0 %-covered files
   (`FileSystemDocumentCache`, `NativeCommandRunner` — with a fake-safe seam,
   `CurlHttpClient` — against a local capture or by extracting option assembly,
   `FeatureFlagSchema`, `MessageFactory`) and the near-zero pure VOs
   (`ConfigurationDiff`, `DiagnosticsReport`). Then lift `CastingProcessor`,
   `ScalarCaster`, `ReloadableConfiguration`, `FsWatcher` to ≥ 90 %. Reaching ≥ 95 %
   statements is ~395 uncovered lines of mostly straightforward I/O wrapping.
2. **Harden transports (NV2–NV6).** `mkdir` mode 0770 + atomic writes in
   `FileSystemCache`; array-command `proc_open` + select-loop drain + timeout in
   `NativeCommandRunner`; disable redirect following or strip auth headers per hop in
   `CurlHttpClient` (+ named timeout constant); realpath containment in `IncludeProcessor`;
   TLS-capable endpoints for Consul/Etcd clients.
3. **Wire mutation testing (NV19).** Bootstrap for plain-PHPUnit compatibility (or drop
   `Codeception\Test\Unit` from leaf tests), add `infection.json5` with staged MSI
   thresholds, add `composer mutation`.
4. **Mechanize the house style (NV18).** Enable `yoda_style`, `global_namespace_import`
   (with `use function` imports), `ordered_class_elements` in `.php-cs-fixer.php`; add a
   `line-limit` check (or `git diff --check`-style gate) for 120 columns; wire `phpmnd`
   and `deptrac` into composer scripts. Then auto-fix NV12/NV14/NV15 and let the gates
   hold the line.
5. **Remove the last abstract class (NV7)** — convert `AbstractNodeRewritingProcessor`
   to composition (mirroring the M5 `CommandDecryption` precedent), keeping the R18 DRY
   win without inheritance.
6. **Decide the VO/factory carve-out explicitly (NV10)** and either document "immutable
   domain VOs may be constructed inline" in AGENTS.md or route the listed sites through
   the existing factories; same for the interface-backing triage (NV8) — open up the
   cross-boundary candidates (`MessageFactory`, `ConfigurationBuilder`,
   `ConfigurationLoaders`, …), bless the rest as internal.
7. **Namespace hygiene (NV9):** promote `TelemetrySinkInterface` and the snapshot
   contract into `Contract/`, and inject the default wiring from the public entry point.
8. **Split the suites (NV11):** move stack-level tests to an `Integration` suite so Unit
   stays SUT-local; adopt port-conformance verifier tests for adapters (LSP evidence).
9. **Finish literal extraction (NV16):** enum-back occurrence names and the
   `env|tenant|profile` grammar; replace `get_class()` discriminators with explicit
   identity methods/constants (closes prior-review R10).
10. **Vocabulary homes (NV13):** hoist shared vocabularies onto port interfaces or
    `Contract/`-resident enums per the stated convention.

None of the architectural findings require redesign; they are boundary hardening,
tooling activation, and convention completion. The two hard failures against the stated
bar — coverage and mutation testing — are bounded, well-understood work items.
