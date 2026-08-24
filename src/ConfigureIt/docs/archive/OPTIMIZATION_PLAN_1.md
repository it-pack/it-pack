# ConfigureIt — Optimization Plan

**Source:** Derived from `docs/REVIEW.md` (16 findings: F1–F16).
**Goal:** Raise the codebase from "very good" to "exemplary" against its own
`AGENTS.md` rules (hexagonal purity, DIP, enums over strings, no magic
strings/numbers, PHP 8.5 forward-compat) with minimal, low-risk changes.

**Legend:** `H` = architectural/security · `M` = correctness/type-safety · `L` = polish/optional.

---

## Execution order

1. **F16** — security/perf fix (small, urgent).
2. **F1 / F2 / F4** — remove the Core→Adapter/Bridge leak + concrete type-hints.
3. **F3** — restore contract truth for `ValidationExceptionInterface`.
4. **F5 / F6 / F8** — domain enums + constants; drop `setAccessible`.
5. **F7 / F9 / F10 / F11 / F12 / F13 / F15** — polish pass.

Items 1–4 are cheap and self-contained; none block shipping.

---

## Phase 1 — Security & Performance (urgent)

### F16 — Guard `hash_file` behind `is_file`  ·  **M / Security+Perf**
- **File:** `src/Core/ConfigurationCompiler.php:54`
- **Problem:** `hash_file('sha256', $uri)` is called for every discovered resource
  while computing the cache key. For non-file URIs (`https://`, `s3://`, `vault://`)
  with `allow_url_fopen` on, this opens a stream wrapper → actual remote fetch
  (SSRF-shaped side effect, latency, and "touching" secret endpoints during key
  computation).
- **Fix:**
  ```php
  $version = is_file($uri)
      ? hash_file(self::HASH_ALGO, $uri)
      : $uri;
  ```
- **Verify:** unit test with a non-file `vault://` URI asserts `hash_file` is never
  invoked (e.g. expect `file_get_contents`/stream wrapper call count == 0).

---

## Phase 2 — Hexagonal & DIP leaks (composition root)

### F1 — Move Core→Adapter/Bridge wiring out of Core  ·  **H**
- **File:** `src/Core/ConfigurationBuilder.php:7-10`
- **Problem:** Core imports concrete `Adapter\CompositeReader`,
  `Adapter\Decryption\DecryptingReader`, `Bridge\OpenTelemetry\OpenTelemetryReporter`
  — violates AGENTS "Core may NOT depend on Adapters/Bridges".
- **Fix:**
  - Introduce a composition root namespace (e.g. `src/Application` or a root-level
    `Boot`/factory) responsible for constructing concrete readers/reporters.
  - `ConfigurationBuilder` receives a pre-built reader and reporter via their
    contracts (`ConfigurationReaderInterface`, `ResolutionReporterInterface`).
  - Core depends only on Contracts + ValueObjects afterward.
- **Verify:** grep `src/Core` for `Adapter\` / `Bridge\` imports → zero matches.

### F2 — Type-hint interfaces, not concretions  ·  **M**
- **File:** `src/Core/ConfigurationBuilder.php:153,205`
- **Problem:** `withExecutionContext(ExecutionContext $context)` and
  `build(?ResolutionReporter $reporter)` use concrete classes although
  `ExecutionContextInterface` / `ResolutionReporterInterface` exist.
- **Fix:** type-hint the interfaces; keep concrete defaults constructed internally
  or injected via the composition root (see F1).
- **Verify:** PHPStan L9 green; no concrete imports remain in Core signatures.

### F4 — Contracts return their interfaces  ·  **M**
- **File:** `src/Contract/Execution/ResolutionReporterInterface.php`
- **Problem:** returns concrete `Occurrence` / `Observation` instead of the
  `OccurrenceInterface` / `ObservationInterface` the same file imports.
- **Fix:** change return types to the interfaces; adapt implementations accordingly.
- **Verify:** PHPStan + consumer code compiles against interfaces.

---

## Phase 3 — Contract truth

### F3 — Implement `ValidationExceptionInterface`  ·  **M**
- **File:** `src/Contract/ValidationExceptionInterface.php`, `src/Exception/ValidationException.php`
- **Problem:** `ValidationExceptionInterface` is orphaned (no implementer) yet
  `ProcessorInterface` / `ConfigurationResolverInterface` docblock `@throws` it;
  `ValidationException` only extends `ConfigurationException`.
- **Fix (preferred):** make `ValidationException implements ValidationExceptionInterface`
  and add `getMessages()` / `getMessageObjects()`; align docblocks.
  (Alternative: remove the interface and fix docblocks.)
- **Verify:** PHPStan L9; a test asserts `ValidationException instanceof ValidationExceptionInterface`.

---

## Phase 4 — Enums, constants & forward-compat

### F5 — Replace raw-string discriminators with enums  ·  **M**
- **Files:** `src/Execution/Observation.php:18`, `src/Execution/Occurrence.php:18`,
  `src/ValueObject/Provenance.php` (`sourceKind`, `adapter`, `processorTrail`)
- **Problem:** AGENTS prefers enums over strings, but severity `level`
  (`info`/`warning`/`error`), occurrence `type`, `sourceKind` (`file`/`env`/
  `programmatic`/`secret`…), and stage names are raw strings.
- **Fix:** introduce enums/constants:
  - `enum Severity { case Info; case Warning; case Error; }`
  - `enum OccurrenceType { … }`
  - `enum SourceKind { case File; case Env; case Programmatic; case Secret; … }`
  - `enum ProcessorStage { … }`
- **Verify:** PHPStan L9; existing call sites migrated; behavior unchanged (assert
  on `->value` for any serialized output).

### F6 — Extract magic strings to constants  ·  **M**
- **Files:** `src/Core/ConfigurationCompiler.php:54` (`'sha256'`),
  `src/Core/ConfigurationNode.php:73-76` (`Format::PHP`, `'unknown'`, `'programmatic'`)
- **Problem:** stringly-typed hash algorithm + sentinel defaults.
- **Fix:**
  - `private const HASH_ALGO = 'sha256';` in `ConfigurationCompiler` (use in F16).
  - Enum-ize per F5; explicit unknown sentinel for format (see F9).
- **Verify:** no literal `'sha256'`/`'unknown'` remain outside the constant def.

### F8 — Remove deprecated `setAccessible`  ·  **M**
- **File:** `src/Adapter/NativeConfigurationSchema.php:128`
- **Problem:** `ReflectionProperty::setAccessible(true)` is a deprecated no-op on PHP
  8.5 (no-op since 8.1) and emits a deprecation.
- **Fix:** delete the call; `getValue()` already works on private props in 8.1+.
  (Mirror the companion introspector fix.)
- **Verify:** run on PHP 8.5 with `E_DEPRECATED` visible → no deprecation; tests green.

---

## Phase 5 — Polish

### F7 — Named priority constant  ·  **L**
- **Files:** `src/Core/ConfigurationPipeline.php:43`, `src/Core/DefaultsProcessor.php:49,55`
- **Fix:** `private const DEFAULT_PRIORITY = 0;` (or a `Priority` helper).

### F9 — Honest "unknown" format sentinel  ·  **L**
- **Files:** `src/ValueObject/Provenance.php:fromArray`, `src/Core/ConfigurationNode.php:provenance()`
- **Fix:** add `Format::UNKNOWN` (or make format nullable) instead of misusing
  `Format::PHP` as "unknown" during round-trips/diagnostics.

### F10 — Tighten bare `mixed`  ·  **L**
- **Scope:** 184 `mixed` (mostly `array<string,mixed>` — keep).
- **Fix:** replace bare `mixed` params/returns in public APIs with precise/generic types.

### F11 — Flesh out `PORTS.md`  ·  **L**
- **File:** `src/docs/architecture/PORTS.md`
- **Fix:** fill "may depend / may not depend / example" per-port sections, or mark DRAFT.

### F12 — Property hooks (optional)  ·  **L**
- **Files:** `src/ValueObject/Provenance.php`, `src/Core/ConfigurationNode.php`
- **Fix:** collapse duplicate getters onto readonly props with PHP 8.4 property hooks.

### F13 — Lazy / WeakMap (optional)  ·  **L**
- **Files:** `src/Core/ConfigurationSnapshot.php`, `src/Core/ConfigurationGraph.php`
- **Fix:** defer/memoize per-node derived views for very large configs. Not urgent.

### F14 — Fibers  ·  **N/A**
- **Decision:** **do not adopt.** Synchronous, deterministic resolution is a deliberate
  feature (observability, diagnostics, reproducibility).

### F15 — DRY the `Provenance` copy methods  ·  **L**
- **File:** `src/ValueObject/Provenance.php` (`with*`/`as*`)
- **Fix:** extract a private `copy(...)` helper reconstructing all 12 args once, used
  by every mutator — reduces drift when fields are added.

---

## Acceptance checklist (per change)

- [ ] `composer phpstan` (level 9) green
- [ ] `composer cs-fixer` (`@PSR12` + `@PHP84Migration` + `no_superfluous_elseif`) clean
- [ ] `composer test` (PHPUnit) green
- [ ] F1: `grep 'Adapter\\|Bridge\\' src/Core` → empty
- [ ] F16: non-file URI test asserts no stream open
- [ ] F3/F4/F5: type contracts honored and behavior-preserving

## Risk & effort

| Phase | Risk | Est. effort | Blocks release? |
|-------|------|-------------|-----------------|
| 1 (F16) | Low | S | No |
| 2 (F1/F2/F4) | Med | M | No |
| 3 (F3) | Low | S | No |
| 4 (F5/F6/F8) | Med | M | No |
| 5 (F7–F15) | Low | M | No |

All changes are additive/isolated; each phase can ship independently behind green gates.
