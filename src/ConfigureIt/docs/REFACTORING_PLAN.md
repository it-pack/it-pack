# ConfigureIt — Refactoring & Optimization Plan

**Companion to:** `docs/REVIEW.md` (Review 4, findings NV1–NV22, plus carried-over R10).
This document turns every **open** finding into an ordered, dependency-aware set of
milestones with file-level tasks, acceptance gates, and documentation duties. Resolved
findings (R7–R9, R11–R19) are not repeated except as traceability.

**Deliberate deviation from the review's recommendation order:** transports come before
coverage because they change behavior (and therefore tests); the mutation harness comes
before coverage growth so that every new test contributes MSI from day one; suite
splitting rides with the coverage milestone because it reorganizes the same directories.

**Target:** `php: ^8.5`. Existing gates are PHPStan **level 9** + ShipMonk dead-code,
cs-fixer (`@PSR12` + `@PHP84Migration` + `no_superfluous_elseif` + strict comparisons),
Codeception Unit suite. New gates introduced along the way: coverage threshold,
mutation threshold, architecture (deptrac), magic-number (phpmnd), line-length (phpcs).

> **Composer scripts** (existing and to-be-added, exact names):
> - `composer analyse`      → PHPStan L9 + ShipMonk dead-code *(exists)*
> - `composer cs:check`     → php-cs-fixer dry-run *(exists)* / `cs:fix` *(exists)*
> - `composer test:unit`    → Codeception Unit suite *(exists)*
> - `composer coverage`     → pcov coverage run *(exists)*
> - `composer test:integration` → Integration suite *(Milestone 3)*
> - `composer coverage:check`   → fail < 95 % statement coverage *(Milestone 3)*
> - `composer mutation`         → Infection run *(Milestone 2)*
> - `composer architecture`     → deptrac layers *(Milestone 4, tightened in 6)*
> - `composer lint:length`      → phpcs 120-column sniff *(Milestone 4)*

**Branch strategy:** one branch per milestone, merged independently:
`refactor/transport-hardening`, `refactor/mutation-harness`, `test/coverage-95`,
`refactor/hygiene-mechanized`, `refactor/contracts-completion`,
`refactor/dip-purity`. Run the full gate set after every step, not just at milestone end.
The maintainer commits after reviewing each milestone (established workflow).

**Ground rules**

- Behavior changes are allowed **only** where a finding calls for one (security defaults,
  error messages); everything else is refactor-with-green-tests.
- Public API changes must be additive unless the finding explicitly retires something
  (the M4-inherited abstract base is the one scheduled removal).
- Every milestone updates `docs/CHANGELOG.md` (`[Unreleased]`) and flips the addressed
  NV findings to "✅ Resolved" in `docs/REVIEW.md` once verified.
- Serialized-cache wire format (`FileSystemCache` artifacts, provenance payloads) must
  stay stable unless the task explicitly notes a one-time invalidation.

---

## Phase 0 — Pre-flight (run once)

```bash
cd src/ConfigureIt
composer install --no-interaction
composer analyse     # baseline
composer cs:check    # baseline
composer test:unit   # baseline
composer coverage    # writes tests/_output/coverage.xml (~30 s, pcov driver)
git log --oneline -3
```

| Gate | Result (expected baseline) |
|------|----------------------------|
| `composer analyse` | **0 errors** |
| `composer cs:check` | **0 files fixable** |
| `composer test:unit` | **276 tests / 636 assertions** |
| Coverage (from XML) | elements **82.90 %**, statements **84.75 %**, methods **74.08 %** |
| Hygiene audit | `else` 0 · Yoda 0 · multi-type files 0 · abstract classes **1** · lines >120 col ≈ **41** · unimported global symbols **71** |

Record these numbers in the PR description of Milestone 1 — they are the "before" column
for every acceptance criterion below.

**Status:** ✅ done (2026-08-26). Baselines re-verified on the committed tree:
`composer analyse` 0 errors · `composer cs:check` 0 files fixable · `composer test:unit`
276 tests / 636 assertions · coverage 82.90 % elements / 84.75 % statements / 74.08 %
methods (pcov, `tests/_output/coverage.xml`).

---

## Milestone 1 — Transport & storage security hardening

**Goal:** close every transport/storage finding: world-writable cache directories holding
decrypted secrets, shell-invoking command runner with deadlock exposure, redirect-
following HTTP client that forwards signed credentials, include-path escape, and
plaintext-only Consul/etcd endpoints.

**Findings:** NV2 (H), NV3 (M), NV4 (M), NV5 (M), NV6 (M), NV17 (L, atomic-write part).
**Branch:** `refactor/transport-hardening`. **Risk:** M — touches process/HTTP/filesystem
behavior; mitigated by new tests around every changed default.

### Status

✅ done (2026-08-26).

- All five tasks implemented as specified, plus one gate-driven addition: the
  `rg "0o777"` acceptance grep exposed that `FileSystemDocumentCache` shared NV2's
  world-writable-directory hole; it received the same mode constant and atomic-write
  treatment.
- New tests: `tests/Unit/Cache/FileSystemCacheTest` (permissions, atomicity, key
  validation, round-trips incl. typed snapshots),
  `tests/Unit/Adapter/CommandRunner/NativeCommandRunnerTest` (large-input/large-output
  drain, EOF-on-empty-stdin, exit-code translation, stderr truncation, deadline
  termination via real subprocesses),
  `tests/Unit/Adapter/Enterprise/Http/CurlHttpClientTest` (redirect policy absent,
  default + injectable timeouts, body attachment, empty-method rejection), traversal +
  symlink-escape + contained-normalization cases in `IncludeProcessorTest`, TLS-upgrade
  and scheme-rejection cases in `RemoteSourceTest`.
- Gates: `composer test:unit` **302 tests / 684 assertions** (was 276/636) ·
  `composer cs:check` **0 of 266 files** · `composer analyse` **[OK] no errors** ·
  acceptance greps clean (`0o777`, `FOLLOWLOCATION`, `escapeshellarg` → 0 hits in src/).
- Docs: CHANGELOG `[Unreleased] → Security`; REVIEW NV2–NV6 resolved + NV17 partial;
  ADR-0011 accepted (+ INDEX); PORTS.md §6 remote-KV TLS/auth recipe.

### Tasks

1. **`src/Cache/FileSystemCache.php` — safe directory + atomic artifact writes**
   - Replace `mkdir($this->directory, 0o777, true)` with
     `mkdir($this->directory, self::DEFAULT_DIRECTORY_MODE, true)` and add
     `private const int DEFAULT_DIRECTORY_MODE = 0o770;` (group-writable max).
   - Atomic write in `set()`: write to `$file . '.tmp-' . bin2hex(random_bytes(4))`
     then `rename($tmp, $file)`; delete the temp file in a `finally` on failure.
   - Validate keys in `path()`: `preg_match('/^[A-Za-z0-9._-]{1,128}$/', $key)` or throw
     `InvalidArgumentException` (defense-in-depth; today keys come from the compiler hash).
   - Tests: artifact written atomically (no `.tmp` residue), unreadable-directory
     best-effort path still returns cleanly, key-validation rejection case, directory
     created with 0770 & umask-tolerant assertion.

2. **`src/Adapter/CommandRunner/NativeCommandRunner.php` — no shell, no deadlock, bounded**
   - Pass the command **array** directly to `proc_open($command, $spec, $pipes)`
     (array form bypasses `/bin/sh` entirely — makes the docblock true).
   - Replace the write-all-then-read sequence with a `stream_select()` drain loop that
     writes stdin chunks while reading stdout/stderr concurrently (removes the >64 KB
     pipe-buffer deadlock).
   - Add `private const int DEFAULT_TIMEOUT_SECONDS = 30;` + constructor param
     `int $timeoutSeconds = self::DEFAULT_TIMEOUT_SECONDS;`; enforce the deadline in the
     select loop; on expiry `proc_terminate()` + throw a `RuntimeException` noting the timeout.
   - Check `fwrite()` return; truncate embedded stderr to
     `MAX_STDERR_BYTES = 2048` in the exception message (log-hygiene: tool stderr may
     quote secret-bearing input).
   - Extract the drain loop into a private method so tests can drive it deterministically.
   - Tests: real subprocess (`php -r 'echo str_repeat("x", 100000);'`) proves large-output
     safety; sleeping subprocess proves timeout path; failing exit-code path; stdin echo
     round-trip. These tests simultaneously retire most of the file's zero coverage.

3. **`src/Adapter/Enterprise/Http/CurlHttpClient.php` — redirects off, timeouts named**
   - Remove `CURLOPT_FOLLOWLOCATION => true` (curl default is off). Redirect following
     re-sends caller headers (signed auth!) to whatever host redirects point at;
     document in the class docblock that callers needing follow-redirects must wrap the
     port in a dedicated decorating transport (note as future adapter work, not here).
   - Replace magic `CURLOPT_TIMEOUT => 10` with
     `public const int REQUEST_TIMEOUT_SECONDS = 10;` +
     `public const int CONNECT_TIMEOUT_SECONDS = 5;` and add
     `CURLOPT_CONNECTTIMEOUT`; make both injectable via optional constructor ints.
   - Extract `private function buildOptions(string $method, array $headers, ?string $body): array`
     so option assembly is unit-testable without network.
   - Tests: `buildOptions()` assertions (method uppercased, header line format, POSTFIELDS
     presence, absence of FOLLOWLOCATION); failure path with a malformed handle is left to
     integration.

4. **`src/Core/IncludeProcessor.php` — contain includes to the base directory**
   - New private helper `containPath(string $relative): string`: resolve
     `$base . '/' . ltrim(...)` with `realpath()`, verify the result is inside
     `realpath($baseDirectory)`, otherwise throw
     `ResolutionException(sprintf('Include "%s" escapes the base directory.', $relative))`.
     Symlink escapes are caught by comparing resolved prefixes.
   - While touching the file: extract the `env|tenant|profile` grammar strings into a
     private class constant map (`CONDITION_ATTRIBUTES`) — full vocabulary hoisting is
     Milestone 5; this is the minimal de-duplication within the file.
   - Tests: traversal attempts (`../outside.yaml`, absolute path, symlink escape fixture)
     rejected with the new exception; legitimate nested include still loads.

5. **`src/Adapter/Enterprise/Client/{ConsulClient,EtcdClient}.php` — TLS-capable endpoints**
   - Accept secure URIs: `consul+tls://host:port/prefix` and `etcd+tls://...` resolve to
     `https://`; plain schemes keep current behavior (BC). Implement via a private
     scheme→scheme map constant; reject unknown schemes explicitly.
   - Docblock recipe: auth tokens ride via a decorating `HttpClient` adding headers
     (mirror Vault/GCP clients' documented pattern) — link from `docs/architecture/PORTS.md`.
   - Tests (FakeHttpClient): assert resulting upstream URL for plain and +tls URIs,
     status-failure translation unchanged.

### Gates

| Gate | Expected |
|------|----------|
| `composer analyse` | 0 errors |
| `composer cs:check` | clean |
| `composer test:unit` | green, ≥ 286 tests (new security tests added) |
| Manual | `grep -rn "0o777" src/` → 0 hits; `grep -n FOLLOWLOCATION src/` → 0 hits |

### Docs
CHANGELOG `[Unreleased] → Fixed/Security`; REVIEW: NV2–NV6, NV17(partial) → Resolved;
PORTS.md: `RemoteKeyValueClient` TLS/auth recipe; ADR-000X “Secure transport defaults”.

---

## Milestone 2 — Test harness modernization → mutation testing online

**Goal:** make the suite runnable by plain PHPUnit so Infection works, configure Infection,
and publish a first measured MSI baseline. This unlocks mutation-driven verification for
every later milestone.

**Findings:** NV19 (M). **Branch:** `refactor/mutation-harness`. **Risk:** L-M — wide but
mechanical; zero behavior code touched.

### Tasks

1. **Decouple leaf tests from Codeception (mechanical, ~70 files)**
   - Audit first: `rg -l "Codeception\\\\Test\\\\Unit" tests/Unit` (70 files);
     confirm no actor usage (`rg -n "\\\$this->tester|\\\$I->" tests/Unit` → 0 today).
   - Per file: `use Codeception\Test\Unit;` → `use PHPUnit\Framework\TestCase;`,
     `_before()` → `setUp(): void` (+ `parent::setUp()`), `_after()` → `tearDown(): void`
     (+ `parent::tearDown()`). `Codeception\Verify` (`verify()->...`) and `Codeception\Stub`
     keep working — they are standalone libraries independent of the runner.
   - Scriptable via targeted `sed`; review the diff per directory rather than blind-replace.
   - Codeception keeps running these files natively (it executes PHPUnit-based tests),
     so `composer test:unit` is unchanged.

2. **Plain-PHPUnit entry point**
   - Add `phpunit.xml.dist` (suite = `tests/Unit`, bootstrap = `vendor/autoload.php`,
     colors, `failOnWarning`). Verify: `vendor/bin/phpunit --testsuite Unit` green.

3. **Infection configuration**
   - Add `infection.json5`:
     ```json5
     {
         "$schema": "vendor/infection/infection/resources/schema.json",
         "source": { "directories": ["src"] },
         "timeout": 20,
         "logs": { "text": "tests/_output/infection.log", "summary": "tests/_output/infection.summary.json" },
         "mutators": { "@default": true }
     }
     ```
   - Add `"mutation": "vendor/bin/infection --threads=max --no-progress"` to composer.
   - First run records the baseline MSI into the PR description (expect a low number —
     that is the point of measuring it; the five 0 %-covered files will dominate).

4. **Guard rails**
   - Do **not** set thresholds yet — thresholds land in Milestone 3 once coverage holes
     stop dominating the score. Note the staged plan in `infection.json5` comments.

### Gates

| Gate | Expected |
|------|----------|
| `vendor/bin/phpunit` | green (same count as `composer test:unit`) |
| `composer test:unit` | green, unchanged 276+ tests |
| `composer mutation` | completes; baseline MSI recorded in docs |
| `composer analyse` / `cs:check` | untouched, green |

### Docs
CHANGELOG (tooling); CONTRIBUTING.md: how to run plain phpunit + infection locally.

---

## Milestone 3 — Coverage ≥ 95 % + suite split

**Goal:** reach the mandated 95 % statement/element coverage with SUT-local tests, split
integration-style tests out of Unit, and turn coverage into an enforced gate.

**Findings:** NV1 (H), NV11 (M). **Branch:** `test/coverage-95`. **Risk:** M — volume
work; mitigated by the per-file task table below (≈395 uncovered statements, itemized).

### Tasks

1. **Split the suites (first, so new tests land in the right home)**
   - Create `tests/Integration.suite.yml` (mirror `Unit.suite.yml`).
   - Move stack-level tests out of `tests/Unit`: anything importing Adapter concretes
     beyond its own SUT or vendor schema objects — today: `ConfigurationResolverTest`
     (imports FileReader/FileSource/JsonLoader/PhpLoader/NativeConfigurationSchema +
     `Nette\Schema\Expect`), `ConfigurationLoadersTest`, `Wiring/*`, bridge reporter
     tests using real sinks. Rule going forward: **Unit = one SUT + fakes only.**
   - Composer: `"test:integration": "vendor/bin/codecept run Integration"`;
     switch `"coverage"` to `codecept run Unit,Integration --coverage ...`.
   - Seed a **conformance-verifier** pattern for LSP evidence:
     `tests/Conformance/CommandRunnerVerifier*` + `DecrypterVerifier*` parameterized over
     implementations (binary-dependent cases guarded behind env checks), following the
     sibling-project RunIt verifier convention.

2. **Coverage:check gate**
   - Add `scripts/coverage_check.php`: parses `tests/_output/coverage.xml`, fails when
     covered-elements ratio < 0.95 or covered-statements ratio < 0.95 (methods reported
     informationally); wire as `"coverage:check"` chained after `coverage`.

3. **Close the zero-covered files** (all under `tests/Unit/<same-path>`):

   | File | Uncov. stmts | What to test |
   |------|--------------|--------------|
   | `Cache/FileSystemDocumentCache` | 32 | get/set round-trip, corrupt-artifact → null fallback, typed-metadata restore path, missing dir creation |
   | `Adapter/CommandRunner/NativeCommandRunner` | 27 | already largely covered by M1 tests — finish edge arms here |
   | `Adapter/Enterprise/Http/CurlHttpClient` | 27 | `buildOptions()` matrix (M1 extraction) + failure-message formatting via header/handle seams |
   | `Adapter/FeatureFlagSchema` | 12 | valid flag sets pass, wrong-type flag values produce expected issues, empty-graph case |
   | `Adapter/MessageFactory` | 9 | message construction variants (path/message/severity combos) |

4. **Lift the worst partial files**

   | File | Now | Task |
   |------|-----|------|
   | `ValueObject/ConfigurationDiff` | 11 % | pure diff/invert/sensitive-flag logic — table-driven cases |
   | `ValueObject/DiagnosticsReport` | 12 % | report assembly + immutability + toArray/fromArray |
   | `Adapter/Schema` | 22 % | validate success/error/warning paths with Expect graphs |
   | `Core/ReloadableConfiguration` | 72 % | reload-after-drift, watcher-callback, stale-artifact paths (temp fixtures) |
   | `Adapter/FsWatcher` | 75 % | tick detection with zero-interval probe injection on temp files |
   | `Core/CastingProcessor` | 77 % | cast matrix incl. failure arms (table-driven) |
   | `Core/ScalarCaster` | 78 % | scalar coercion matrix (int/float/bool/string round-trips + rejects) |
   | `Adapter/Enterprise/Client/{Consul,Etcd}Client` | ~80 % | error paths: bad JSON, non-list, missing keys (FakeHttpClient) |
   | `ValueObject/{Provenance,FlagDefinition}` | ~89 % | remaining mutator/copy/fromArray arms |
   | `CLI/{DiffCommand,GraphCommand}` | 58 % | output-buffer capture on temp fixtures |

5. **Verify the bar**
   - `composer coverage && composer coverage:check` → passes at ≥ 95 % statements AND
     elements; paste the per-family table into the PR.
   - Re-run `composer mutation`: MSI re-measured on the now-complete suite; record the
     delta from Milestone 2's baseline.

### Gates

| Gate | Expected |
|------|----------|
| `composer test:unit` | green (SUT-local only) |
| `composer test:integration` | green (stack tests relocated here) |
| `composer coverage:check` | **≥ 95 %** statements & elements |
| `composer mutation` | MSI ≥ baseline; gaps noted for follow-ups |

### Docs
CHANGELOG (tests); CONTRIBUTING: suite-placement rule; REVIEW: NV1, NV11 → Resolved.

---

## Milestone 4 — Mechanize the house style, then sweep

**Goal:** convert three manually-policed conventions into enforced fixer rules, auto-fix
everything mechanical, hand-wrap the rest, and wire the dormant QA tools into composer
gates so regressions cannot slip.

**Findings:** NV18 (L), NV12 (M, auto-fixed), NV15 (L, auto-fixed), NV14 (L, manual),
NV21 (L), NV22 (L). **Branch:** `refactor/hygiene-mechanized`. **Risk:** L — mostly
whitespace/import churn; big diffs, low semantics.

### Tasks

1. **Extend `.php-cs-fixer.php`**
   ```php
   'yoda_style' => ['equal' => true, 'identical' => true, 'never_use_switch' => true],
   'global_namespace_import' => ['import_classes' => true, 'import_constants' => false, 'import_functions' => true],
   'ordered_class_elements' => [
       'order' => ['use_trait', 'constant', 'property', 'construct',
                   'method_public_static', 'method_public',
                   'method_protected_static', 'method_protected',
                   'method_private_static', 'method_private'],
   ],
   ```
   Note: `ordered_class_elements` cannot express “factories (named constructors) before
   instance methods” separately from other statics; the enforced approximation is
   *public statics first*. Update the declaration-order wording in `AGENTS.md` to match
   the enforceable rule (constructor → public static factories/helpers → instance methods
   → protected → private) — this permanently closes NV12 via `composer cs:fix`.
2. **Run `composer cs:fix`** and eyeball the diff: expect member-order moves in the seven
   flagged files (`ConfigurationSnapshot`, `DocumentNormalizer`, `Provenance`, `Output`,
   `Key`, `NativeConfigurationSchema`, `IncludeProcessor`) and `use function …` imports
   appearing wherever `\count`/`\sprintf`/`\is_string`-style globals were referenced
   (~71 symbols, ~130 sites — the fixer imports them mechanically).
3. **120-column budget (NV14)** — add `phpcs.xml.dist` with
   `Generic.Files.LineLength` (limit 120, warnings excluded from tolerance) limited to
   `src/`; add `"lint:length": "vendor/bin/phpcs --standard=phpcs.xml.dist src"`.
   Then hand-wrap the ≈41 long lines, cluster by cluster (parameters one-per-line where
   readability demands, per house style): `Codegen/PhpRenderer` (161-char worst),
   `SchemaModelBuilder` ×6, `Enterprise/Contract/HttpClient`, `ConfigurationDriftDetector` ×4,
   `ConfigurationDiagnostics`, `ConfigurationDiff`, `IncludeProcessor`, remainder per grep.
4. **Resolver iterable edge (NV21)** — in `resolveResources()`, materialize non-array
   iterables first (`is_array($sources) ? $sources : iterator_to_array($sources, false)`)
   so empty generators correctly fall back to default sources. Test with an empty generator.
5. **Cosmetics (NV22)** — drop the double blank line in `IncludeProcessor`; put
   `DefaultConfigurationWiring::composeReporter()`’s opening brace on its own line;
   replace scratch-dir `0o777` in tests/fixtures with `0o700`.
6. **Wire the dormant tools**
   - `deptrac.yaml` initial layer map (Contract / Core / Wiring / Adapter / Bridge /
     ValueObject / Execution / Cache / CLI / Codegen) with rules matching today’s *actual*
     healthy edges; `"architecture": "vendor/bin/deptrac analyse --fail-on-violation"`.
     Tightening happens in Milestone 6 — here it locks in the current good state.
   - `"lint:magic": "vendor/bin/phpmnd src --non-zero-numbers --ignore-funcs=…"`
     with a curated ignore list (JSON depth 512, hash args, curl option constants are
     already symbolic); triage output — anything real becomes a Milestone 5 candidate,
     the rest whitelisted explicitly.
   - Record the pdepend/phpmd PHP-8.5 parser crash (REVIEW NV18) as an upstream watch-item;
     re-introduce complexity metrics when the QA base ships a compatible engine.

### Gates

| Gate | Expected |
|------|----------|
| `composer cs:check` | clean **with the three new rules active** |
| `composer lint:length` | 0 violations |
| `composer architecture` | 0 violations (baseline map) |
| `composer analyse` / `test:unit` / `test:integration` / `coverage:check` | green |

### Docs
AGENTS.md (declaration-order rule restated; import policy: classes + functions imported),
CHANGELOG, REVIEW: NV12, NV14, NV15, NV18, NV21, NV22 → Resolved.

---

## Milestone 5 — Contracts completion

**Goal:** eliminate the last abstract class, give cross-boundary services their missing
interfaces, hoist shared vocabularies onto contracts, and retire the final magic-value
cluster (including prior-review R10). Everything here is additive API or internal
recomposition.

**Findings:** NV7 (M), NV8 (M), NV13 (M), NV16 (L, incl. R10). **Branch:**
`refactor/contracts-completion`. **Risk:** M — public-surface additions and the base-class
removal; mitigated by interface-conformance tests added alongside.

### Tasks

1. **Remove the last abstract class (NV7)** — mirror the M5 precedent.
   - Replace `Core\AbstractNodeRewritingProcessor` with a composed collaborator:
     ```php
     final readonly class ConfigurationGraphRewriter
     {
         /** @param Closure(ConfigurationNodeInterface): ConfigurationNodeInterface $fn */
         public function rewrite(ConfigurationGraphInterface $graph, Closure $fn): ConfigurationGraphInterface { … }
     }
     ```
     owning the clone-graph → loop-nodes → put loop (verbatim from the base class body).
   - Expose it as a wiring service: add `graphRewriter(): ConfigurationGraphRewriter` to
     `ConfigurationWiringInterface` (+ `DefaultConfigurationWiring` impl) so processors
     receive it via DI instead of constructor-default `new` (keeps NV10 policy intact).
   - Convert each inheriting processor (defaults/casting/variable/validation family) to
     `implements` both processor interfaces and delegate to the injected rewriter.
   - Delete the abstract class; grep `abstract` across `src/` → **0**; add an architecture
     test asserting no `T_ABSTRACT` ever reappears (one-line tokenizer check in
     `tests/Unit/Wiring/NoAbstractClassesTest.php`).
2. **Open up the cross-boundary un-backed services (NV8)** — additive interfaces only:

   | Class | Verdict | Action |
   |-------|---------|--------|
   | `MessageFactory` | open | extract `MessageFactoryInterface` (used across Execution reporting) |
   | `ConfigurationLoaders` | open | extract `ConfigurationLoadersInterface` (loader registry consumed by wiring/tests) |
   | `ConfigurationBuilder` | open | extract `ConfigurationBuilderInterface` (primary façade) |
   | `ReloadableConfiguration` | open | extract interface (hot-reload consumers) |
   | `ConfigurationDiffer` / `ConfigurationDiagnostics` | open | extract interfaces (observability surface) |
   | `CommandDecryption` | bless internal | document in PORTS.md as internal helper |
   | `ScalarCaster`, `RedactingRenderer`, `ConfigurationCompiler`, `Key`, `FileModificationProbe`, `TreeBuilder`, `HttpResponse`, `FeatureFlagSchema`, `SemanticConventions` | bless internal | AGENTS/PORTS note; revisit on demand |
   | `ConfigurationDiff`, `DiagnosticsReport`, `FlagDefinition` | open (VO contracts) | add `Contract/ValueObject/*Interface` mirroring existing VO-contract style |
   | CLI + Codegen internals | bless boundary | exempt from backing requirement, noted in AGENTS |

   Each extracted interface: `final readonly` implementers, contract placed beside its
   siblings in `Contract/`, verified by a conformance-style unit test.
3. **Hoist shared vocabularies onto contracts (NV13)**
   - Move the five vocabulary enums’ *home of truth* forward: keep `ValueObject` classes,
     but have shared vocabularies consumed across layers declared via
     `Contract/…Vocabulary` interfaces hosting the string constants where interfaces are
     the natural home (secret schemes for `DocumentNormalizer`, include-condition
     attributes for `ExecutionContext` consumers, source-prefix labels).
   - Rule encoded in AGENTS.md: **single-use literal → class const; shared/cross-layer
     vocabulary → interface constants or a `Contract` enum.**
   - Occurrence names: replace inline `'ConfigurationValidated'` /
     `'ConfigurationResolved'` / `'SnapshotCreated'` with `OccurrenceType::…->value`
     references (enum already exists — pure call-site change, wire format identical).
4. **Retire `get_class()` discriminators (NV16 / closes R10)**
   - Add `public function name(): string` to `ConfigurationLoaderInterface`
     (`'json-loader'`, `'yaml-loader'`, …) and `ConfigurationSchemaInterface`
     (`'nette-schema'`); replace the four `get_class()` sites
     (`ConfigurationResolver:231`, `ConfigurationCompiler:94`,
     `ConfigurationPipeline:107`, `GraphFromFile:60`).
   - ⚠️ One-time effect: compiled-cache fingerprints change → first run after deploy
     re-resolves (document in CHANGELOG under *Changed*).
   - Source label `'include:' . $relative` → private const `INCLUDE_SOURCE_PREFIX`.

### Gates

| Gate | Expected |
|------|----------|
| `rg -n "abstract (readonly )?class" src/` | **0 hits** |
| `composer architecture` | green (interface moves reflected in the map) |
| All gates (`analyse`, `cs:check`, both suites, `coverage:check`, `mutation`) | green; MSI ≥ Milestone-3 figure minus documented noise |
| Wire-format | occurrence/provenance payload samples byte-identical except loader/schema identity strings |

### Docs
PORTS.md (new ports + blessed-internal list), AGENTS.md (vocabulary-home rule),
ADR for the rewriter decomposition, CHANGELOG, REVIEW: NV7, NV8, NV13, NV16 → Resolved;
archive-side note that R10 is closed.

---

## Milestone 6 — Composition-root & DI-policy purity

**Goal:** repair the three directional wrinkles, make instantiation policy explicit
(carve-out documented, constructor-default injection eliminated for injectable
collaborators), and tighten deptrac from “documents reality” to “enforces intent”.

**Findings:** NV9 (M), NV10 (M), NV11 residual (conformance breadth). **Branch:**
`refactor/dip-purity`. **Risk:** M — constructor signatures change for injectables
(internal BC; the builder façade shields end users).

### Tasks

1. **Promote the telemetry sink contract (NV9b)**
   - Move `Bridge/OpenTelemetry/Contract/TelemetrySinkInterface` →
     `src/Contract/TelemetrySinkInterface.php`; leave the old namespace as a deprecated
     empty extending alias for one minor release (`@deprecated use Contract\…`),
     update wiring/reporter/builder imports; drop the alias next major.
2. **Snapshot factory contract purity (NV9a)**
   - Inspect `ConfigurationSnapshotFactoryInterface`’s concrete
     `Core\ConfigurationSnapshot` references (return/param types + TypedSnapshot interplay);
     retype against `Contract\ConfigurationSnapshotInterface` + a small
     `TypedSnapshotCapable` marker (or equivalent) so `Contract/` imports no `Core\` symbol.
     Verify: `rg "use ItPack\\\\ConfigureIt\\\\Core" src/Contract/` → 0 hits.
3. **Builder stops importing the default wiring (NV9c)**
   - Make `ConfigurationBuilder::__construct(?ConfigurationWiringInterface $wiring = null)`
     store null and resolve lazily via a public factory in the composition namespace:
     `Wiring\Configurations::builder()` (or top-level `ConfigureIt::builder()` if a front
     controller exists) that injects `DefaultConfigurationWiring`. Core loses the
     `use …\Wiring\DefaultConfigurationWiring;` import; README/examples updated to the
     new one-liner (old `new ConfigurationBuilder()` keeps working via null-lazy wiring
     *only if* the resolution lives outside Core — hence the factory indirection above).
4. **Make instantiation policy explicit (NV10)**
   - Write **ADR-000Y “Instantiation policy & the value-object carve-out”**: injectable
     collaborators (services, factories, runners, reporters) must arrive via constructors
     from the composition root; immutable domain value objects (`ConfigurationNode`,
     `Provenance`, `ValidationMessage`, `Observation`, `TypedSnapshot` wrappers,
     graph instances) may be constructed inline — they *are* data, not services.
   - Remove every constructor-default `= new X()` for **injectable** types found in the
     NV10 inventory: resolver (`OccurrenceFactory`, `ConfigurationDocumentFactory`,
     `ConfigurationSnapshotFactory`), `IncludeProcessor` (`DocumentNormalizer`,
     `ConfigurationResourceFactory`, `ExecutionContext`), `NativeConfigurationSchema`
     (`Processor`, `ValidationResultFactory`), `ConfigurationDiffer`
     (`RedactingRenderer`), `ConfigurationDriftDetector` (`ConfigurationDiffer`),
     `FileSystemCache` (`ConfigurationSnapshotFactory`) — parameters become required,
     satisfied by `DefaultConfigurationWiring`/builder defaults so end-user call sites
     are unchanged.
   - Encode the rule in AGENTS.md and add a lightweight architecture test scanning
     constructor defaults for `New_` expressions whose class is not in the blessed-VO
     list (tokenizer-based, mirrors Milestone 5’s no-abstract test).
5. **Tighten deptrac to intent**
   - Forbid: `Contract → Core|Adapter|Bridge|Wiring`, `Core → Adapter|Bridge|Wiring`,
     vendor layers anywhere outside `Adapter/`+dev, `ValueObject → *` upward edges.
   - Allow-list the two documented exceptions (none should remain after 1–3 — if any
     does, it gets an inline `deptrac:skip` with a reason, not a silent allow).
   - `composer architecture` becomes a release-blocking gate.

### Gates

| Gate | Expected |
|------|----------|
| `rg "use ItPack\\\\ConfigureIt\\\\Core" src/Contract/` | 0 hits |
| `rg "use ItPack\\\\ConfigureIt\\\\Wiring" src/Core/` | 0 hits |
| Constructor-default scan test | green |
| `composer architecture` (tightened) | green |
| All other gates | green; public builder/examples snippets still run (`examples/*.php` smoke) |

### Docs
ADR-000Y, PORTS.md (moved sink port), AGENTS.md (instantiation policy), README/examples
if the builder entry point changed, CHANGELOG, REVIEW: NV9, NV10 → Resolved.

---

## Milestone register

| # | Milestone | Findings retired | Risk | Effort | Status |
|---|-----------|------------------|------|--------|--------|
| 1 | Transport & storage security hardening | NV2–NV6, NV17½ | M | M | ✅ Done (2026-08-26) |
| 2 | Mutation harness (PHPUnit decoupling + Infection) | NV19 | L-M | M | ⬜ Pending |
| 3 | Coverage ≥ 95 % + suite split + conformance seeds | NV1, NV11 | M | L | ⬜ Pending |
| 4 | Style mechanization & hygiene sweep | NV12, NV14, NV15, NV18, NV21, NV22 | L | M | ⬜ Pending |
| 5 | Contracts completion (no abstract, interfaces, vocabularies, R10) | NV7, NV8, NV13, NV16 | M | L | ⬜ Pending |
| 6 | Composition-root & DI-policy purity | NV9, NV10 | M | M | ⬜ Pending |

Dependency notes: 2 → 3 (thresholds meaningful only post-split); 4 → 5 (fixer rules hold
the line while contracts churn); 5 → 6 (rewriter-as-service assumes the wiring extension
from 5.1). Milestone 1 is independent — start immediately.

---

## Explicitly out of scope (recorded, not scheduled)

- **NV20 modern-PHP headroom** — property hooks/virtual properties remain blocked by the
  documented getter-name collision; WeakMap memoization waits for a profiling signal;
  fibers stay rejected (deterministic synchronous resolution is a feature). Revisit
  triggers: interface renaming initiative, hot-path profile showing derived-view cost.
- **pdepend/phpmd PHP 8.5 parser crash** — vendor engine lag; re-introduce complexity
  metrics when `just-bases/php-qa-base` ships a compatible release (tracked under NV18
  residue).
- **Performance benchmarking** — `phpbench` is installed but no benchmarks exist;
  scheduling benchmarks is premature until the caching/watcher features show real-world
  load. The M1 timeout work is the only performance-adjacent change.
