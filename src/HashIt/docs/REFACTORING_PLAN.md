# Refactoring Plan — `it-pack/hash-it`

> Companion to [`REVIEW.md`](./REVIEW.md). This plan turns each review finding (F-1 … F-12) into ordered, independently-shippable milestones. Every milestone ends with a **verification gate** that must be green before the next begins.
>
> **Guiding principles**
> 1. *Tests first, structure second* — never refactor a path that lacks coverage; add the test, then change the code.
> 2. *One concern per commit* — each task is its own commit/PR so `git bisect` and review stay cheap.
> 3. *Keep the suite green* — every milestone runs `composer coverage` + `composer architecture` + `composer analyse` + `composer cs:check` before completion.
> 4. *No behavior change unless explicitly noted* — pure reshaping (imports, traits, factories, `else`→`early-return`) must be provably equivalent.

## Current baseline (from REVIEW.md)

| Metric | Value |
|---|---|
| Tests | 174 tests / 487 assertions, all green |
| Coverage (statements) | 89.41% |
| Coverage (methods) | 81.32% |
| `else`/`elseif` sites | 3 (`TreeHasher.php:80`, `BsdFormat.php:45`, `ManifestVerifier.php:34`) |
| Inline-FQCN files | ~27 |
| Files >120 cols | 10 |
| `new` outside factories | 13 sites (F-7) |
| Mutation testing | not configured |

## Milestone map (priority order)

| # | Milestone | Findings | Risk | Gate |
|---|---|---|---|---|
| M0 | Harden tooling & CI gates | — | low | lint/checks pass |
| M1 | Coverage ≥95% | F-1 | high | `composer coverage` ≥95% |
| M2 | Mutation testing | F-2 | med | `infection` runs, score recorded |
| M3 | Eliminate `else`/`elseif` | F-3 | low | suite + cs green |
| M4 | Import hygiene + ≤120 cols | F-5, F-6 | low | `cs:check` + `architecture` |
| M5 | DRY instrumentation trait | F-4 | med | coverage held |
| M6 | Factory-only instantiation | F-7 | med | deptrac + coverage |
| M7 | `PsrStreamHasher` port | F-8 | med | deptrac + contract test |
| M8 | Optional SDKs in `require-dev` | F-9 | low | clean `composer install` |
| M9 | `Hkdf` magic-number constants | F-10 | low | coverage held |
| M10 | Optional polish | F-11, F-12 | low | docs/lint |

---

## M0 — Harden tooling & CI gates

**Goal:** make the stricter review bar (imports-at-top, ≤120 cols, no `else`) *enforced*, not just aspirational, so later milestones can rely on the linter.

### Tasks
- **M0.1** Update `.php-cs-fixer.php`:
  - ✅ Enable `no_superfluous_elseif` (already on) — bare `else` is *not* a valid php-cs-fixer fixer, so it is enforced by `composer cs:strict` instead (see M0.2).
  - ✅ Enable `fully_qualified_strict_types` (shortens inline-FQCN type hints to relative names — drives F-5; rector adds the `use` import in M4).
  - ✅ Enable `method_argument_space` → `on_multiline: ensure_fully_multiline` (standardises long signatures; does not auto-wrap single-line ones — F-6 line-wrapping is finished in M4).
  - ✅ Confirmed `no_unused_imports`, `ordered_imports`, `declare_strict_types`, `visibility_required` already present/effective.
- **M0.2** ✅ Add `bin/strict-gates.sh` (grep safety net) gating F-3 / F-5 / F-6, wired as `composer cs:strict`. It fails the build on any bare `else`/`elseif`, on any inline `\ItPack\HashIt\…` FQCN in code, and on any line > 120 cols (generated `_generated` / `_output` paths excluded, mirroring the cs-fixer finder).
- **M0.3** ✅ Document the strict bar in new `AGENTS.md` and in `README.md` "Testing & quality gates"; reference `composer cs:strict` + the `strict-gates` CI job.
- **M0.4** ✅ Ran `composer cs:check` (clean, 0/90 files) and applied `composer cs:fix` (safe cosmetic reformat of 25 files: FQCN type-hint shortening + multiline arg standardisation). `composer test:unit` still green (174 tests / 487 assertions, no behavioral change).

### Status — DONE
- `.php-cs-fixer.php`, `composer.json` (`cs:strict` script), `.github/workflows/quality.yml` (`strict-gates` job), `AGENTS.md`, `README.md`, `bin/strict-gates.sh` added/updated.
- `composer cs:check` passes; suite green.
- `composer cs:strict` is intentionally **RED** until M3/M4 clear the baseline violations below:

| Gate | Baseline after M0 | Cleared by |
|---|---|---|
| F-3 `else`/`elseif` | 5 lines (3 code: `TreeHasher.php:80`, `BsdFormat.php:45`, `ManifestVerifier.php:34`; 2 doc-comment-only: `Hasher.php:331`, `KeyedHasher.php:279`) | M3 |
| F-5 inline FQCN (code) | 12 lines | M4 (rector imports) |
| F-6 > 120 cols | 42 lines in `src` + a few in `tests` | M4 (wrapping) |

**Verification:** `composer cs:check` ✅; `composer test:unit` ✅ (174/487); `composer cs:strict` ✅ script present (gates red by design); `composer architecture` / `composer analyse` unchanged.

---

## M1 — Raise coverage to ≥95% (F-1)

**Goal:** statements ≥95% and methods ≥95% (the stricter stated bar), concentrated on the 13 classes REVIEW listed.

### Tasks (add tests **only**, no production change yet)
- **M1.1** `Hasher` (81.0% → 100%): cover `hashDirectory`, `hashFiles` (empty/glob/no-match), `hashStream` with progress callback, `hashIterable`, error branch (`unreadable`), observation emission on each path.
- **M1.2** `KeyedHasher` (81.1% → 100%): symmetric methods of M1.1 plus keyed-context error path.
- **M1.3** `HashInput/StreamInput` (76.5%): non-seekable stream, `getSize()` null, read-chunk boundary, `rewind()` on unseekable.
- **M1.4** `HashInput/FileInput` (77.8%): missing file, unreadable file, `getSize()` via `FilepathFileinfo` miss.
- **M1.5** `Adapter/PsrStreamHasher` (78.6%): PSR-7 stream read boundaries + chunked iteration.
- **M1.6** `Adapter/NativeKeyedContextFactory` (83.3%): HMAC vs non-HMAC algorithm branch, `create()` failure.
- **M1.7** `VerificationReport` (84.6%): `match()`/`mismatch()` accumulators, `hasMismatches()`, `jsonSerialize()` / `toArray()`.
- **M1.8** `HashInput/TempSink` (87.5%): write/flush/read, size tracking, detach.
- **M1.9** `Format/SfvFormat` (90.5%) & `Format/BsdFormat` (91.3%) & `Format/GnuSha256Format` (94.4%): round-trip digests for each, parse errors, algorithm-mismatch branch (`BsdFormat:45` is also F-3).
- **M1.10** `Adapter/Observability/CloudEventsHashingObserver` (92.1%): started/progress/completed/failed events, missing-attribute path.
- **M1.11** `HashAlgorithm` (93.9%): `metadata()`/`hmacSafe()` reflection cache, unknown algorithm.
- **M1.12** Add per-test coverage assertions where cross-test leakage matters (see M1.13) — e.g. a `@covers` annotation on each test method to catch leakage (REVIEW "No coverage leakage — UNVERIFIED").
- **M1.13** Run `vendor/bin/codecept run Unit --coverage --coverage-xml --coverage-html` and confirm `coverage.xml`: **statements ≥95%, methods ≥95%**.

**Verification:** `composer coverage` ≥95% statements & methods; `coverage.xml` committed as evidence. No production code touched.

---

## M2 — Configure mutation testing (F-2)

**Goal:** establish a mutation score baseline; mutants must not survive silently.

### Tasks
- **M2.1** Add `"infection/infection": "^0.29"` to `require-dev` and `composer update --dev`.
- **M2.2** Create `infection.json.dist`:
  ```json
  {
    "source": { "directories": ["src"] },
    "tests": { "directories": ["tests"] },
    "phpUnit": { "configDir": "tests" },
    "mutators": { "@default": true },
    "coverage": { "phpUnit": { "customPath": "tests/_output/coverage" } },
    "minMsI": 80, "minCoveredMsI": 80, "minMutants": 80
  }
  ```
- **M2.3** Add composer script `"mutation": "infection --show-mutations --no-interaction"`.
- **M2.4** Run `composer mutation`; record the initial Mutation Score Indicator (MSI). Strengthen any test that lets mutants survive (loop back to M1 tests as needed).
- **M2.5** Wire `composer mutation` into CI after `composer coverage`.

**Verification:** `infection.json.dist` present; `composer mutation` exits 0 (or with a recorded, accepted MSI) and produces an MSI number in CI logs.

---

## M3 — Eliminate `else` / `elseif` (F-3)

**Goal:** rewrite the 3 violating sites using early-return / `continue` / inverted conditions.

### Tasks
- **M3.1** `src/TreeHasher.php:80` — convert the trailing `} else {` in the reduction loop to an early `continue` when the node is not the trailing odd one.
- **M3.2** `src/Format/BsdFormat.php:44-49` — replace `if (…) {…} elseif ($manifest->algorithm() !== $algorithm) {…}` with early `return`/inverted branches (also improves the F-1 test for the mismatch path).
- **M3.3** `src/ManifestVerifier.php:34` — after `if ($actual->equals($expectedHash)) { $matched[] = …; continue; }`, the `else` body becomes the fall-through (no `else` needed).
- **M3.4** Re-run suite + `cs:check` (the `no_superfluous_else` rule from M0.1 will now enforce this).

**Verification:** `rg '\belse\b|elseif' src` returns 0 matches in code (doc comments excluded); `composer cs:check` green.

---

## M4 — Import hygiene & ≤120 columns (F-5, F-6)

**Goal:** every referenced project type imported via `use`; no line >120 columns.

### Tasks
- **M4.1** Run Rector with `ImportNamespacedName` / FQCN-to-import (or `php-cs-fixer` `fully_qualified_strict_types` + `no_superfluous_phpdoc_tags`) across `src/` to convert the ~27 inline-FQCN files (top offenders: `Hasher.php:18`, `KeyedHasher.php:15`, `TreeHasher.php:10`, `ResumableHasher.php:7`, `HashBuilder.php:6`, `CompositeInput.php:4`).
- **M4.2** For the 10 files over 120 cols (see F-6 list), wrap long signatures one-parameter-per-line (the repo already does this for `Hkdf::derive`).
- **M4.3** Manually review the diff — confirm no type was silently dropped/changed by the auto-import.
- **M4.4** Run `composer cs:check`, `composer analyse`, `composer architecture`.

**Verification:** `rg '\bItPack\\HashIt\\' src` returns 0 inline-FQCN occurrences; `composer cs:check` reports 0 lines >120; coverage unchanged.

---

## M5 — Extract DRY instrumentation (F-4)

**Goal:** remove the duplicated observation/progress/file-size helper block from `Hasher` and `KeyedHasher`.

### Tasks
- **M5.1** Define `src/Observability/HashingInstrumentation.php` (a `trait`, per "prefer traits/composition"): methods `observationId()`, `completed()`, `observe()`, `algorithmName()`, `streamTotalBytes()`, `iterableTotalBytes()`, `fileBytes()`, `unreadable()`.
- **M5.2** Ensure the trait depends only on the same collaborators the two classes already hold (the observer + `NativeAlgorithmSource`/`HashAlgorithm`), so no constructor signature changes.
- **M5.3** `use HashingInstrumentation;` in `Hasher` (`Hasher.php:300-359` region) and `KeyedHasher` (`KeyedHasher.php:240-308` region); delete the duplicated private methods.
- **M5.4** Confirm both classes still compile and the identical public behavior holds; run `composer coverage` (must remain ≥95% — M1 already covers both).

**Verification:** no duplicate helper definitions remain in the two classes; full suite green; deptrac unchanged (trait lives in Core, used by Core).

---

## M6 — Factory-only instantiation (F-7)

**Goal:** value-object/`new` sites inside non-factory classes are replaced by injected factories, keeping `NativeHashIt` the sole composition root.

### Tasks
- **M6.1** Create small factories: `HashFactory`, `ManifestFactory` (`VerificationReport`/`HashMismatch`/`Filepath`), `ResumableHashFactory`. Each is a `final` class with a single creation method (one-type-per-file).
- **M6.2** Refactor call sites:
  - `Hasher.php:286` (`new Hash`), `:277` (`new NativeAlgorithmSource`) → inject `HashFactory` + `AlgorithmSourceInterface` (the latter is already a port — `NativeAlgorithmSource` lives in Core, so this is deptrac-clean).
  - `KeyedHasher.php:237` (`new Hash`), `:228` (`new NativeAlgorithmSource`) → same.
  - `TreeHasher.php:44` (`new TempSink`), `:58` (`Hash::create`) → inject `TempSinkFactory` / `HashFactory`.
  - `ManifestVerifier.php:26/35/39` → inject `FilepathFactory`/`ManifestFactory`.
  - `Format/BsdFormat.php`, `Format/SfvFormat.php`, `Format/GnuSha256Format.php` → inject `ManifestFactory`/`FilepathFactory`.
  - `ResumableHasher.php:29/36` (`new ResumableHash`) → inject `ResumableHashFactory`.
- **M6.3** Wire the new factories in `NativeHashIt` (the only place that may `new` concretes).
- **M6.4** Run `composer architecture` (deptrac) — confirm Core still only depends on `Contract` (+ Core's own `NativeAlgorithmSource`); `composer coverage` ≥95%.

**Verification:** `rg 'new (Hash|HashMismatch|Filepath|VerificationReport|ResumableHash|TempSink)\b' src` only matches inside the respective factories and `NativeHashIt`.

---

## M7 — Give `PsrStreamHasher` a port (F-8)

**Goal:** hexagonal symmetry — every adapter implements a `Contract` port.

### Tasks
- **M7.1** Add `src/Contract/StreamHasherInterface.php` with `hash(StreamInterface $stream, HashAlgorithm $algo, int $chunkSize = HasherInterface::DEFAULT_CHUNK_SIZE_BYTES): HashInterface`.
- **M7.2** `final class PsrStreamHasher implements StreamHasherInterface` (`src/Adapter/PsrStreamHasher.php:24`).
- **M7.3** Expose it from the composition root: add `NativeHashIt::streamHasher(): StreamHasherInterface` (mirrors the existing `hmacHasher()`/`keyedHasher()` pattern).
- **M7.4** Update consumer examples (`examples/vertical/29-psr7-streams.php`) to depend on the interface.
- **M7.5** Add a contract test asserting `PsrStreamHasher` produces identical digests to `Hasher` for the same input stream.

**Verification:** deptrac shows `PsrStreamHasher` behind a port; `rg 'PsrStreamHasher' src` references the interface; new contract test passes; coverage ≥95%.

---

## M8 — Optional SDKs in `require-dev` (F-9)

**Goal:** clean `composer install` reproduces the green observer-test suite.

### Tasks
- **M8.1** Move `open-telemetry/api` and `cloudevents/sdk-php` from `suggest` → `require-dev` in `composer.json`; run `composer update --dev`.
- **M8.2** (Fallback if SDKs must stay optional) instead guard `OtelHashingObserverTest` / `CloudEventsHashingObserverTest` with `@requires` / a `class_exists` skip so they self-skip on a clean install rather than fatal.
- **M8.3** Verify on a fresh checkout: `rm -rf vendor && composer install --no-dev` then `composer install` then `composer coverage` is green end-to-end.

**Verification:** observer tests pass on a clean install; CI uses the same `require-dev` set.

---

## M9 — `Hkdf` magic-number constants (F-10)

**Goal:** hoist bare literals into named constants, consistent with `DEFAULT_CHUNK_SIZE_BYTES` / `FRAME_LENGTH_BYTES`.

### Tasks
- **M9.1** In `src/KeyDerivation/Hkdf.php`:
  - `private const int RFC5869_MAX_BLOCKS = 255;` (`:45`)
  - `private const int DEFAULT_DERIVED_KEY_LENGTH = 32;` (`:34` default)
  - `private const string BLOCK_COUNTER_PREFIX = "\0";` (`:57`)
- **M9.2** Replace usages; add a tiny doc note citing RFC 5869.
- **M9.3** Keep the existing `Hkdf` tests green; add a boundary test at `length = 255 * hashLen` (the max block count) if not already present.

**Verification:** `rg '255|"\0"' src/KeyDerivation/Hkdf.php` only matches the constant definitions; coverage held.

---

## M10 — Optional polish (F-11, F-12)

**Goal:** strictness & documentation improvements with no behavioral risk.

### Tasks
- **M10.1** (F-11) Introduce `enum HashObservationKind: string { case STARTED; case PROGRESS; case COMPLETED; case FAILED; }`; change `HashObservation::$kind` from `string` to the enum; update the two observer `switch` statements for exhaustiveness.
- **M10.2** (F-12) Add an ADR (`docs/adr/`) documenting the immutable-`HashBuilder` context-copy trade-off (F-12) — why N copies are accepted for safety, and the Lazy-Object alternative considered.
- **M10.3** Final full gate: `composer coverage` ≥95%, `composer mutation` MSI recorded, `composer architecture`, `composer analyse`, `composer cs:check` all green.

**Verification:** enum compiles; ADR committed; all gates green.

---

## Cross-cutting verification (run after every milestone)

```bash
composer test:unit          # 174+ tests green
composer coverage           # statements & methods ≥95%
composer architecture       # deptrac: no layer violations
composer analyse             # phpstan: no errors
composer cs:check           # php-cs-fixer: import/column/no-else rules
composer mutation           # (after M2) MSI recorded
```

## Suggested sequencing for PRs

1. **PR-1:** M0 (tooling) — safe, unblocks everything.
2. **PR-2:** M1 (coverage) — biggest risk, do before reshaping.
3. **PR-3:** M2 (infection) — can follow M1.
4. **PR-4:** M3 + M4 (mechanical cleanups) — low risk, lint-enforced.
5. **PR-5:** M5 (trait) — refactor, coverage protects it.
6. **PR-6:** M6 (factories) — structural, deptrac-checked.
7. **PR-7:** M7 (PsrStreamHasher port) — structural.
8. **PR-8:** M8 (require-dev) — reproducibility.
9. **PR-9:** M9 + M10 (constants, enum, ADR) — polish.

Each PR must pass the **Cross-cutting verification** block before merge.
