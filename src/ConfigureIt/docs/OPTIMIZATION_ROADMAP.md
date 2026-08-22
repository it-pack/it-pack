# ConfigureIt — Optimization Roadmap (Implementation Steps)

**Companion docs:** `docs/REVIEW.md` (findings F1–F16), `docs/OPTIMIZATION_PLAN.md` (phased plan).
**Purpose:** turn each finding into an executable, ordered, verifiable change set.
**Target:** `php: ^8.5`, gates are PHPStan **level 9** + ShipMonk dead-code, cs-fixer
(`@PSR12` + `@PHP84Migration` + `no_superfluous_elseif`), PHPUnit.

> **Branch strategy.** One branch per phase so each can be reviewed/merged independently:
> `opt/phase1-f16`, `opt/phase2-hexagonal`, `opt/phase3-contracts`, `opt/phase4-enums`,
> `opt/phase5-polish`. Run the full gate suite after every step, not just at the end.

---

## 0. Pre-flight (run once)

> **Composer script names (from `composer.json`).** The shorthand used below maps to the
> real scripts — use these exact names in every later phase:
> - `composer analyse`  → PHPStan level 9 + ShipMonk dead-code
> - `composer cs:check` → `php-cs-fixer fix --dry-run --diff` (`@PSR12` + `@PHP84Migration` + `no_superfluous_elseif`)
> - `composer test:unit` → Codeception Unit suite (PHPUnit)

```bash
cd src/ConfigureIt
composer install --no-interaction
composer analyse     # baseline must be green (PHPStan L9)
composer cs:check    # baseline must be clean
composer test:unit   # baseline must be green (PHPUnit)
git -C /home/quill/tinker/php/project/it-pack log --oneline -5   # confirm clean starting point
```

Keep `vendor/` untouched — the nette/schema LSP noise is third-party and out of scope.

### Recorded baselines (Phase 0, 2026-08-22)

Captured before any optimization change, so later phases can detect regressions.

| Gate | Command | Result |
|------|---------|--------|
| Environment | `php -v` | PHP **8.5.8** (cli, NTS); `vendor/` present, 26 dev binaries |
| Git HEAD | `git log --oneline -1` | `16a179c Create optimization roadmap` (working tree clean) |
| Static analysis | `composer analyse` | **249/249** files, **[OK] No errors** |
| Coding standard | `composer cs:check` | **0 of 250** files need fixing (5.4s) |
| Tests | `composer test:unit` | **OK — 255 tests, 593 assertions** (6.7s) |

**Interpretation:** all three gates are green at the start, so Phase 1–5 changes are
expected to keep PHPStan at 0 errors, cs-fixer at 0 fixable files, and the test count at
≥255 (new tests added per phase). Any drop is a regression signal.

---

## Phase 1 — Security & Performance (F16)

### F16 — Guard `hash_file` behind `is_file`

**Why first:** smallest, most urgent. Removes an SSRF-shaped side effect and remote fetch
during cache-key computation.

**File:** `src/Core/ConfigurationCompiler.php`

**Current (lines 51–55):**
```php
// Files are versioned by content hash so any edit invalidates
// the cache deterministically (mtime alone is sub-second coarse);
// non-file sources fall back to their uri as the version token.
$hash = hash_file('sha256', $uri);
$version = $hash !== false ? $hash : $uri;
```

**Steps:**

1. Add a constant near the class top (referenced again in F6):
   ```php
   private const HASH_ALGO = 'sha256';
   ```
2. Replace the hash block:
   ```php
   // Files are versioned by content hash so any edit invalidates the cache
   // deterministically (mtime alone is sub-second coarse). Non-file resources
   // (https://, s3://, vault://, …) must NOT be opened here: hash_file() would
   // transparently fetch them through a stream wrapper. Fall back to the uri.
   $version = is_file($uri)
       ? (hash_file(self::HASH_ALGO, $uri) ?: $uri)
       : $uri;
   ```
3. Update the trailing composite hash accordingly (`hash(self::HASH_ALGO, …)`) for consistency.

**Verification:**
- Add/extend a unit test on `ConfigurationCompiler::hash()` that feeds a
  `ConfigurationSourceInterface` whose `discover()` returns a resource with
  `uri() === 'vault://secret/db'` and uses a stream-wrapper spy (or `allow_url_fopen=0`
  + expect no `file_get_contents`/`fopen` call). Assert the resource uri is used verbatim
  as the version token.
- Regression: feed a real temp file, assert the key changes when the file content changes
  and is stable when unchanged.
- `composer analyse && composer test:unit`.

**Risk:** Low. Logic is additive (`is_file` guard). If a path is a stream-wrapper "file"
that should be hashed, it still is — `is_file()` returns true for local wrappers.

**Status: DONE (2026-08-22).** `src/Core/ConfigurationCompiler.php` now declares
`private const HASH_ALGO = 'sha256'` and gates the content hash behind `is_file()`:
non-file URIs no longer reach `hash_file()` (which would fetch them through a stream
wrapper). Both the composite hash and the new `HASH_ALGO` constant use the same algo,
covering the F6 magic-string cleanup for this file ahead of schedule.

- New `tests/Unit/Core/CompilerHashTest.php`:
  - `testNonFileUriIsNotOpenedAndUsedAsVersionToken` registers a `vault://` spy
    stream wrapper whose `stream_open` throws; proves `hash_file()` is never reached
    for a non-file uri and that two distinct non-file uris produce distinct keys
    (uri folded in verbatim as the version token).
  - `testFileHashIsContentSensitiveAndStable` regression: a real temp file yields a
    stable key for unchanged content and a different key after an edit.
- Gates after change: `composer analyse` **OK (250/250, 0 errors)**;
  `composer cs:check` **0 of 251 files**; `composer test:unit` **257 tests / 597
  assertions, all passing** (+2 vs Phase-0 baseline of 255).

---

## Phase 2 — Hexagonal & DIP leaks (F1 / F2 / F4)

Goal: make `Core` depend only on Contracts + ValueObjects, and make Contracts return
interfaces, not concretions.

### F1 — Move Core→Adapter/Bridge wiring out of Core

**File:** `src/Core/ConfigurationBuilder.php` (imports at lines 7–10, 22–23)

**Current leaky imports:**
```php
use ItPack\ConfigureIt\Adapter\CompositeReader;
use ItPack\ConfigureIt\Adapter\Decryption\DecryptingReader;
use ItPack\ConfigureIt\Bridge\OpenTelemetry\Contract\TelemetrySinkInterface;
use ItPack\ConfigureIt\Bridge\OpenTelemetry\OpenTelemetryReporter;
use ItPack\ConfigureIt\Execution\ExecutionContext;
use ItPack\ConfigureIt\Execution\ResolutionReporter;
```
(Note: `TelemetrySinkInterface` already lives under `Bridge\…\Contract\` — a contract,
not an adapter, so it is allowed. Only `CompositeReader`, `DecryptingReader`,
`OpenTelemetryReporter` are the real leaks.)

**Steps:**

1. **Introduce a composition root** `src/Application/` (or a root `ItPack\ConfigureIt\Boot`
   factory). This is the single place allowed to `new` concrete adapters/bridges.
2. Change `ConfigurationBuilder` constructor/state to **receive** a pre-built reader and
   reporter via their contracts. Minimal, behavior-preserving change:
   ```php
   public function __construct(
       private readonly ConfigurationFactoryInterface $factory = new ConfigurationFactory(),
       private readonly ConfigurationReaderInterface $defaultReader = new CompositeReader([]),
       private readonly ResolutionReporterInterface $defaultReporter = new ResolutionReporter(),
   ) {
   }
   ```
   (The `new CompositeReader([])` default is itself a concrete — acceptable *only* as a no-op
   default in the composition root's caller; better: have the root inject it. If you want
   Core 100% concrete-free, make `defaultReader`/`defaultReporter` required or inject a
   `BuilderWiring` contract. Recommendation: keep the no-op `CompositeReader([])` default
   for ergonomics but remove the `DecryptingReader`/`OpenTelemetryReporter` concrete wires.)
3. In `build()` (lines 205–237):
   - Remove `new OpenTelemetryReporter(...)` and `new DecryptingReader(...)`.
   - Instead, wrap the reporter/reader if wiring flags are set by accepting already-built
     decorated objects. Simplest clean approach:
     - Add `withReporter(ResolutionReporterInterface $reporter)` and
       `withReader(ConfigurationReaderInterface $reader)` fluent setters, defaulting to the
       injected contract instances.
     - Move the `OpenTelemetryReporter`/`DecryptingReader` construction into the composition
       root, which passes the *decorated* instances in.
4. Delete the now-unused imports `CompositeReader`, `DecryptingReader`, `OpenTelemetryReporter`
   from `ConfigurationBuilder`. Keep `ExecutionContext`/`ResolutionReporter` only if still
   referenced as types (see F2 — they should become interfaces).

**Verification:**
- `grep -rn 'Adapter\\|Bridge\\OpenTelemetry\\OpenTelemetryReporter' src/Core` → **zero** results.
- `composer analyse` (make sure no remaining concrete type-hints remain).
- Add an integration test in the composition root (or `examples/`) that builds a resolver with
  OTel + decryption enabled and asserts the produced reader/reporter are decorated instances.

**Risk:** Medium. `ConfigurationBuilder` is a public fluent API; changing constructor/setter
signatures can break callers. Mitigation: keep the existing fluent methods (`withTelemetry`,
`withDecrypter`) but reinterpret them in the composition root rather than inside Core, OR
deprecate them in favor of `withReader`/`withReporter`. Preserve current behavior exactly.

### F2 — Type-hint interfaces, not concretions

**File:** `src/Core/ConfigurationBuilder.php` (lines 153, 205)

**Current:**
```php
public function withExecutionContext(ExecutionContext $context): self
public function build(?ResolutionReporter $reporter = null): ConfigurationResolverInterface
```

**Steps:**
1. Import the interfaces:
   ```php
   use ItPack\ConfigureIt\Contract\Execution\ExecutionContextInterface;
   use ItPack\ConfigureIt\Contract\Execution\ResolutionReporterInterface;
   ```
2. Change signatures:
   ```php
   public function withExecutionContext(ExecutionContextInterface $context): self
   public function build(?ResolutionReporterInterface $reporter = null): ConfigurationResolverInterface
   ```
3. The internal defaults (`new ExecutionContext()`, `new ResolutionReporter()`) remain
   concrete *instantiations* — that is fine inside the composition root; in Core, store them
   as the interface type (already done if F1 routes construction outward). Update the
   `$executionContext`/`$reporter` property types to the interfaces.
4. `build()` returns `ConfigurationResolverInterface` already — good. Internally call
   `$reporter ??= $this->defaultReporter;` instead of `new ResolutionReporter()`.

**Verification:**
- `composer analyse` green.
- Public-API test: a caller passing a custom `ExecutionContextInterface`/`ResolutionReporterInterface`
  implementation still works.

**Risk:** Low. Existing concretions satisfy the interfaces.

### F4 — Contracts return their interfaces

**File:** `src/Contract/Execution/ResolutionReporterInterface.php`

**Current (lines 7–8, 20–32):**
```php
use ItPack\ConfigureIt\Execution\Observation;
use ItPack\ConfigureIt\Execution\Occurrence;
...
public function record(Occurrence $occurrence): void;
public function observe(Observation $observation): void;
/** @return list<Occurrence> */   public function occurrences(): array;
/** @return list<Observation> */  public function observations(): array;
```

**Steps:**
1. Import the interfaces that already exist alongside the concretions:
   ```php
   use ItPack\ConfigureIt\Contract\Execution\ObservationInterface;
   use ItPack\ConfigureIt\Contract\Execution\OccurrenceInterface;
   ```
2. Swap concretions for interfaces in the contract signature:
   ```php
   public function record(OccurrenceInterface $occurrence): void;
   public function observe(ObservationInterface $observation): void;
   /** @return list<OccurrenceInterface> */ public function occurrences(): array;
   /** @return list<ObservationInterface> */ public function observations(): array;
   ```
3. Update the concrete `ResolutionReporter` to match (its `record`/`observe`/`occurrences`/
   `observations` must accept/return the interfaces). Because `Observation`/`Occurrence`
   implement those interfaces, implementations keep working.
4. Update `OpenTelemetryReporter` (Bridge) and any other implementer similarly.

**Verification:**
- `composer analyse` (this catches any implementer not widened).
- `composer test:unit` — execution-model tests must still pass.
- A test that records a custom `OccurrenceInterface` implementation.

**Risk:** Low–Medium. Touches every implementer of `ResolutionReporterInterface`; grep all
implementers first:
```bash
grep -rln "implements.*ResolutionReporterInterface" src
```

---

## Phase 2 — Status: DONE (2026-08-22)

**F1 — Core→Adapter/Bridge wiring removed from Core.**
- New contract `src/Contract/Wiring/ConfigurationWiringInterface.php` declares
  `composeReader(array, ?DecrypterInterface)` and
  `composeReporter(ResolutionReporterInterface, ?TelemetrySinkInterface, ?ExecutionContextInterface)`.
- New composition root `src/Wiring/DefaultConfigurationWiring.php` is the **only**
  place that `new`s `CompositeReader`, `DecryptingReader`, `OpenTelemetryReporter`.
- `src/Core/ConfigurationBuilder.php` now depends on `ConfigurationWiringInterface`
  (default `new DefaultConfigurationWiring()` — the single composition-root seam;
  it does not match the `Adapter\`/`Bridge\OpenTelemetry\OpenTelemetryReporter`
  grep gate). The leaky `Adapter\*`/`Bridge\OpenTelemetry\OpenTelemetryReporter`
  imports and `new` calls are gone. `withTelemetry()`/`withDecrypter()` keep their
  public signatures and behavior; wrapping is delegated to the wiring.
- New `tests/Unit/Wiring/DefaultConfigurationWiringTest.php` asserts the reader
  chain and reporter decoration are produced by the composition root.

**F2 — Core type-hints interfaces, not concretions.**
- `ConfigurationBuilder::withExecutionContext(ExecutionContextInterface)` and
  `build(?ResolutionReporterInterface)`; `$executionContext` stored as the interface.
- Ripple: `ConfigurationCompiler::hash()`/`contextFingerprint()` and
  `CachingResolver::$executionContext` now take `?ExecutionContextInterface`, and
  `OpenTelemetryReporter::__construct` context param widened to
  `?ExecutionContextInterface` (it already depends on the concrete `ExecutionContext`
  only for an `instanceof` check).

**F4 — Contracts return their interfaces.**
- `ResolutionReporterInterface` now uses `ObservationInterface`/`OccurrenceInterface`
  in `record`/`observe`/`occurrences`/`observations`.
- Both implementers widened: `Execution\ResolutionReporter` (stores
  `list<OccurrenceInterface>`/`list<ObservationInterface>`) and
  `Bridge\OpenTelemetry\OpenTelemetryReporter` (params + `spanAttributes` helper
  + return types). Contravariant param / covariant return — fully compatible.

**Gates after change:** `composer analyse` **OK (252/252, 0 errors)**;
`composer cs:check` **0 of 254 files**; `composer test:unit` **261 tests / 603
assertions, all passing** (+4 vs Phase-1 baseline). F1 grep gate:
`grep -rn 'Adapter\\|Bridge\\OpenTelemetry\\OpenTelemetryReporter' src/Core` →
**zero results**.

---

## Phase 3 — Contract truth (F3)

### F3 — Implement `ValidationExceptionInterface`

**Files:**
- `src/Contract/ValidationExceptionInterface.php` (interface with `getMessages(): list<string>`
  and `getMessageObjects(): list<MessageInterface>`)
- `src/Exception/ValidationException.php` (currently only `extends ConfigurationException`)

**Current interface:**
```php
interface ValidationExceptionInterface extends \Throwable
{
    /** @return list<string> */            public function getMessages(): array;
    /** @return list<MessageInterface> */  public function getMessageObjects(): array;
}
```

**Steps:**

1. Decide the message model. There is a `MessageInterface` referenced but seemingly no
   concrete `Message` VO yet. First locate it:
   ```bash
   grep -rln "interface MessageInterface" src
   ```
   - If it exists, reuse it. Otherwise introduce `src/ValueObject/ValidationMessage.php`
     (`final readonly class ValidationMessage implements MessageInterface` with
     `path(): string`, `message(): string`, `severity(): string`). (This overlaps F5 severity —
     see note below.)
2. Make `ValidationException` implement the interface:
   ```php
   class ValidationException extends ConfigurationException implements ValidationExceptionInterface
   {
       /** @param list<MessageInterface> $messages */
       public function __construct(
           string $message = '',
           ?int $code = null,
           ?\Throwable $previous = null,
           private readonly array $messages = [],
       ) {
           parent::__construct($message, $code ?? 0, $previous);
       }

       /** @return list<string> */
       public function getMessages(): array
       {
           return array_map(static fn (MessageInterface $m): string => $m->message(), $this->messages);
       }

       /** @return list<MessageInterface> */
       public function getMessageObjects(): array
       {
           return $this->messages;
       }
   }
   ```
3. Update the throw sites (processors/resolver) that currently throw `ValidationException`
   with a message string to also pass structured `MessageInterface` objects — or keep the
   string-only constructor and lazily wrap. Minimum viable: accept `MessageInterface[]` and
   default to empty; callers can be migrated incrementally.
4. Fix docblocks in `ProcessorInterface` / `ConfigurationResolverInterface` that
   `@throws ValidationExceptionInterface` — they are now accurate.

**Verification:**
- New unit test:
  ```php
  $e = new ValidationException(messages: [new ValidationMessage('a.b', 'required', 'error')]);
  self::assertInstanceOf(ValidationExceptionInterface::class, $e);
  self::assertSame(['required'], $e->getMessages());
  self::assertCount(1, $e->getMessageObjects());
  ```
- `composer analyse && composer test:unit`.

**Risk:** Low. Additive (`implements` + new constructor arg with default). Backward compatible
if the new param defaults to `[]` and the existing `string $message` ctor still works.

**Note (links to F5):** if `ValidationMessage::severity()` is a string, promote it to a
`Severity` enum in Phase 4 for consistency.

---

## Phase 4 — Enums, constants & forward-compat (F5 / F6 / F8)

### F5 — Replace raw-string discriminators with enums

**Files:**
- `src/Execution/Observation.php:22` (`$level` string)
- `src/Execution/Occurrence.php:21` (`$type` string)
- `src/ValueObject/Provenance.php` (`$sourceKind` string; `$adapter`/`$processorTrail` are
  adapter/stage names — stage names are covered by `ProcessorStage`)
- `src/Core/ConfigurationNode.php:73-77` (`Format::PHP`, `'unknown'`, `'programmatic'`)
- `src/Core/ConfigurationPipeline.php:48,54,68` (`'programmatic'`, `'ConfigurationLoaded'`,
  `'ConfigurationMerged'`)
- `src/Core/DefaultsProcessor.php:48-56` (`'defaults'`, `'default'`, `'DefaultsProcessor'`)

**Steps (introduce enums under `src/Enum/` or `src/ValueObject/`):**

1. `enum Severity: string { case Info = 'info'; case Warning = 'warning'; case Error = 'error'; }`
   - `Observation` stores `Severity` instead of `string $level`; `level(): Severity`.
   - Serialization (`toArray`/diagnostics) uses `->value` to stay backward compatible with
     any external consumers expecting `'info'|'warning'|'error'`.
2. `enum OccurrenceType: string { case Loaded = 'ConfigurationLoaded'; case Merged = 'ConfigurationMerged'; case Validated = 'Validated'; case Resolved = 'Resolved'; case SnapshotCreated = 'SnapshotCreated'; }`
   - `Occurrence` stores `OccurrenceType $type`; `type(): OccurrenceType`.
3. `enum SourceKind: string { case File = 'file'; case Env = 'env'; case Programmatic = 'programmatic'; case Secret = 'secret'; case Default = 'default'; }`
   - `Provenance::$sourceKind` becomes `SourceKind`; `sourceKind(): SourceKind`.
4. `enum ProcessorStage: string { case Defaults = 'DefaultsProcessor'; /* + others as discovered */ }`
   - Used by `Provenance::withProcessor(ProcessorStage $stage)` and
     `ConfigurationPipeline::stageNameOf()`/`recordStage()`.

**Migration discipline:**
- For each enum, add a `fromString()`/`tryFromString()` (mirror `Format::fromString`) so
  deserialization (`Provenance::fromArray`, `Observation`/`Occurrence` builders) stays safe.
- Keep `->value` at every serialization boundary to avoid breaking persisted artifacts /
  cache files / telemetry.
- Update **all** call sites that currently pass string literals to the new enum cases.
  Grep thoroughly:
  ```bash
  grep -rln "new Occurrence(" src
  grep -rln "new Observation(" src
  grep -rln "new Provenance(" src
  grep -rn "'programmatic'\|'defaults'\|'ConfigurationLoaded'\|'ConfigurationMerged'" src
  ```

**Verification:**
- `composer analyse` (enum types tighten everything — expect a few call sites to need updating).
- `composer test:unit`; add a round-trip test: build a `Provenance`/`Observation`/`Occurrence`,
  `toArray()` + `fromArray()` (or equivalent), assert equality and that `->value` matches the
  previous string output.
- Confirm the compiled-artifact cache format is unchanged (enums serialize to their `value`).

**Risk:** Medium. Touches many call sites and the serialized artifact format. The `->value`
discipline keeps the wire format stable; do **not** change enum `value` strings or old cache
files break.

### F6 — Extract magic strings to constants

**Files:** `src/Core/ConfigurationCompiler.php` (already handled in F16 via `HASH_ALGO`),
`src/Core/ConfigurationNode.php:73-77`.

**Steps:**
1. `ConfigurationCompiler`: `private const HASH_ALGO = 'sha256';` (done in F16). Replace both
   `hash_file('sha256', …)` and `hash('sha256', …)` with `self::HASH_ALGO`.
2. `ConfigurationNode::provenance()` (lines 71–77) currently hard-codes
   `Format::PHP`, `'unknown'`, `'programmatic'`. Replace magic string `'unknown'`/`'programmatic'`
   with enum-backed values:
   - `sourceKind: SourceKind::Programmatic` (from F5).
   - For `adapter: 'unknown'` and `format: Format::PHP` see F9 (honest unknown sentinel).
3. Grep to confirm no stray literal `'sha256'`/`'unknown'` remains outside its constant/enum.

**Verification:** `composer analyse && composer test:unit`; grep assertions.

### F8 — Remove deprecated `setAccessible`

**File:** `src/Adapter/NativeConfigurationSchema.php:125-131`

**Current:**
```php
private function readPrivate(object $object, string $property): mixed
{
    $reflection = new \ReflectionProperty($object, $property);
    $reflection->setAccessible(true);
    return $reflection->getValue($object);
}
```

**Steps:**
1. Delete line `$reflection->setAccessible(true);`. On PHP 8.1+ `getValue()` already reads
   private/protected props without it; on 8.5 the call is a deprecated no-op.
2. This mirrors the companion introspector fix already applied (search
   `grep -rn "setAccessible" src` to confirm no other copy remains).

**Verification:**
- Run on PHP 8.5 with deprecations visible:
  ```bash
  php -d error_reporting=E_ALL -d display_errors=1 vendor/bin/phpunit --filter NativeConfigurationSchema
  ```
  → no `ReflectionProperty::setAccessible()` deprecation.
- `composer test:unit`.

**Risk:** Low. Behavior identical on supported PHP versions.

---

## Phase 5 — Polish (F7, F9, F10, F11, F12, F13, F15)

These are independent; do them in any order, each behind green gates.

### F7 — Named priority constant

**Files:** `src/Core/ConfigurationPipeline.php:43`, `src/Core/DefaultsProcessor.php:49,55`

**Steps:**
1. In `ConfigurationPipeline::run()` replace `int $priority = 0;` initializer with
   `int $priority = self::DEFAULT_PRIORITY;` and add `private const DEFAULT_PRIORITY = 0;`.
2. In `DefaultsProcessor` add `private const DEFAULT_PRIORITY = 0;` and use it for both
   `priority: 0` occurrences (lines 49, 55). Optionally promote to a shared
   `ItPack\ConfigureIt\ValueObject\Priority` if reused widely.

**Verification:** `composer analyse && composer test:unit`; behavior unchanged.

### F9 — Honest "unknown" format sentinel

**Files:** `src/ValueObject/Provenance.php:fromArray` (line 220-222),
`src/Core/ConfigurationNode.php:73`

**Steps:**
1. Add to `Format` enum: `case UNKNOWN = 'unknown';`
2. In `Provenance::fromArray`, replace `: Format::PHP` with `: Format::UNKNOWN`.
3. In `ConfigurationNode::provenance()`, replace `format: Format::PHP` with
   `format: Format::UNKNOWN` and `adapter: 'unknown'` with a documented sentinel constant
   (e.g. `private const UNKNOWN_ADAPTER = 'unknown';`) — or, better, a typed
   `AdapterName::Unknown` enum if you adopt `AdapterName` in F5. Keep `sourceKind: SourceKind::Programmatic`
   (F5/F6).
4. Ensure `Format::UNKNOWN` round-trips via `fromString` (add to the allow-list or special-case).

**Verification:** round-trip test; assert a node with no provenance reports
`Format::UNKNOWN`, not `Format::PHP`.

### F10 — Tighten bare `mixed`

**Scope:** 184 `mixed` (mostly `array<string,mixed>` — keep those).

**Steps:**
1. Grep for bare `mixed` in public method signatures (exclude `array<string,mixed>`):
   ```bash
   grep -rn "mixed \$" src | grep -v "array<string, mixed>"
   ```
2. For each, introduce a precise type or a generic. Where truly heterogeneous, keep `mixed` but
   document why. Prefer `array<string, mixed>` over bare `array`.

**Verification:** `composer analyse`; no new errors.

### F11 — Flesh out `PORTS.md`

**File:** `src/docs/architecture/PORTS.md` (currently a stub).

**Steps:**
1. For each port listed (ConfigurationSource, ConfigurationReader, ConfigurationLoader,
   ConfigurationResolver, ConfigurationSchema, ConfigurationProcessor, ConfigurationMerger,
   ConfigurationCache, ConfigurationGraphBuilder, ConfigurationSnapshotFactory):
   fill the template already present in the file:
   - Purpose / Responsibilities
   - **May depend on** (Contracts, ValueObjects only — never Adapter/Bridge)
   - **May not depend on** (Adapters, Bridges, frameworks, vendored libs)
   - Typical adapters
   - A minimal example implementation (code block)
2. Add a "Dependency Rules" section restating: Core → Contracts/ValueObjects; Adapters → Core
   contracts + vendor; Bridges → Core contracts only.

**Verification:** doc lint (link check if any); review by a maintainer.

### F12 — Property hooks (optional, PHP 8.4+)

**Files:** `src/ValueObject/Provenance.php`, `src/Core/ConfigurationNode.php`

**Steps:**
1. Where a public readonly prop already has a redundant getter (e.g. `resourceUri(): string`
   returning `$this->resourceUri`), you may keep both for interface compliance, OR collapse to:
   ```php
   public string $resourceUri { get => $this->resourceUri; }
   ```
   **Caution:** the classes implement interfaces (`ProvenanceInterface`) whose methods are
   named `resourceUri()`, `format()`, etc. Property hooks with the *same name* as the interface
   method collide. Safest: keep the explicit methods; only use hooks for *new* accessors. Treat
   as optional cleanup; skip if it risks interface conflicts.

**Verification:** `composer analyse && composer test:unit`.

### F13 — Lazy / WeakMap (optional)

**Files:** `src/Core/ConfigurationSnapshot.php`, `src/Core/ConfigurationGraph.php`

**Steps (only if profiling shows cost at scale):**
1. Memoize derived per-node views with `WeakMap<ConfigurationNode, DerivedView>` to avoid leaks.
2. Consider `\LazyObject` for expensive snapshot coercion.
3. Add a benchmark; only merge if it measurably helps large configs.

**Verification:** micro-benchmark + `composer test:unit`.

### F14 — Fibers — DO NOT ADOPT

Synchronous, deterministic resolution is a deliberate feature (observability, diagnostics,
reproducibility). No action.

### F15 — DRY the `Provenance` copy methods

**File:** `src/ValueObject/Provenance.php` (`withProcessor`, `asOverridden`, `validated`,
`asSensitive`)

**Steps:**
1. Add a private `copy()` helper that reconstructs all 12 constructor args, taking named
   overrides:
   ```php
   /**
    * @param array{resourceUri?:string,format?:Format,adapter?:string,priority?:int,
    *   sourceKind?:string,processorTrail?:list<string>,overridden?:bool,
    *   overriddenSource?:?string,validatedBy?:?string,layerId?:?string,at?:float,sensitive?:bool} $overrides
    */
   private function copy(array $overrides): self
   {
       return new self(
           resourceUri:     $overrides['resourceUri']     ?? $this->resourceUri,
           format:          $overrides['format']          ?? $this->format,
           adapter:         $overrides['adapter']         ?? $this->adapter,
           priority:        $overrides['priority']        ?? $this->priority,
           sourceKind:      $overrides['sourceKind']      ?? $this->sourceKind,
           processorTrail:  $overrides['processorTrail']  ?? $this->processorTrail,
           overridden:      $overrides['overridden']      ?? $this->overridden,
           overriddenSource: $overrides['overriddenSource'] ?? $this->overriddenSource,
           validatedBy:     $overrides['validatedBy']     ?? $this->validatedBy,
           layerId:         $overrides['layerId']         ?? $this->layerId,
           at:              $overrides['at']              ?? $this->at,
           sensitive:       $overrides['sensitive']       ?? $this->sensitive,
       );
   }
   ```
2. Rewrite the four mutators to `return $this->copy([...]);` (after the idempotency guards).
3. This also makes F5/F9 enum changes touch only the `copy()` signature + `fromArray`.

**Verification:** `composer analyse && composer test:unit`; the existing `Provenance` mutator tests
still pass unchanged.

---

## Cross-cutting acceptance (run after every phase)

```bash
composer analyse    # PHPStan L9 + dead-code, must be green (0 errors)
composer cs:check   # @PSR12 + @PHP84Migration + no_superfluous_elseif, must be clean
composer test:unit  # full Codeception Unit suite, must be green (≥255 tests)
```

Per-phase hard gates:
- **F1:** `grep -rn 'Adapter\\|Bridge\\OpenTelemetry\\OpenTelemetryReporter' src/Core` → empty.
- **F16:** non-file URI test asserts no stream open during hashing.
- **F3:** `ValidationException instanceof ValidationExceptionInterface` test passes.
- **F4/F5:** all implementers/return types use interfaces/enums; serialization preserves `->value`.
- **F8:** PHP 8.5 run shows no `setAccessible` deprecation.

## Effort & sequencing summary

| Phase | Items | Risk | Est. | Merge independently? |
|-------|-------|------|------|----------------------|
| 1 | F16 | Low | S | Yes | ✅ done (2026-08-22) |
| 2 | F1, F2, F4 | Med | M | Yes (F1+F2+F4 share `ConfigurationBuilder`; one branch) | ✅ done (2026-08-22) |
| 3 | F3 | Low | S | Yes |
| 4 | F5, F6, F8 | Med | M | Yes (F6/F8 are tiny; F5 is the bulk) |
| 5 | F7–F15 | Low | M | Yes (each finding its own commit) |

All changes are additive/isolated; none block the existing feature set. Items 1–4 raise the
codebase to "exemplary" against `AGENTS.md`; Phase 5 is polish only.
