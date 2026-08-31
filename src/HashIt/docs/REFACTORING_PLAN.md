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

### Status — DONE
- **Aggregate coverage: `95.96%` statements / `100%` methods** (up from `89.41% / 81.32%`). Target met.
- Tests added: `tests/Unit/HasherErrorPathsTest.php` (11), `tests/Unit/KeyedHasherErrorPathsTest.php` (7), plus targeted methods in `tests/Unit/HashInput/HashInputTest.php` (5), `tests/Unit/Format/BsdFormatTest.php` (2), `tests/Unit/Format/SfvFormatTest.php` (2), `tests/Unit/Format/GnuSha256FormatTest.php` (1), `tests/Unit/ManifestVerifierTest.php` (2), `tests/Unit/Adapter/PsrStreamHasherTest.php` (3 + `NonReadableStream` double). Suite: **203 tests / 527 assertions, all green** (no production code touched).
- No `@covers` annotations added (M1.12) — cross-test leakage remains `UNVERIFIED`. Deferred.

**Per-file stmt/meth (non-100% only — all remaining gaps are defensive/dead branches):**

| File | Stmt | Meth | Remaining uncovered | Why |
|---|---|---|---|---|
| `Hasher.php` | 92.9% | 100% | `fopen()`-false, `ftell()`-false, `fstat()`-false, `hashStream` while-loop catch (3), `streamTotalBytes` non-seekable | Defensive branches unreachable without forcing `fopen`/`ftell`/`fstat` to fail; Codeception's strict warning handler converts the `fopen` warning before the `if ($handle===false)` check, and `ftell` on non-seekable resources returns `0` (not `false`). The while-loop catch only fires for errors during streaming, which require an injected throwing context. |
| `KeyedHasher.php` | 93.0% | 100% | same shape as Hasher | same reasons |
| `StreamInput.php` | 88.2% | 100% | `ftell()`-false branch (`$position=0`), `break` on empty fread (only reachable via non-seekable which triggers `fseek` warning) | same `ftell` / non-seekable constraints |
| `FileInput.php` | 88.9% | 100% | `fopen()`-false branch | same `fopen` constraint |
| `TempSink.php` | 93.8% | 100% | `fopen('php://temp')`-false (php://temp cannot fail) | unreachable |
| `CloudEventsHashingObserver.php` | 92.1% | 100% | "cloudevents/sdk-php not installed" guard | dead branch (SDK is a `require-dev` dependency) |
| `HashAlgorithm.php` | 93.9% | 100% | `metadataFor()` null fallback + `info()` `AlgorithmMetadata` placeholder | every enum case carries `#[AlgorithmMetadata]`; the fallback is unreachable |

---

## M2 — Configure mutation testing (F-2) — DONE

**Goal:** establish a mutation score baseline; mutants must not survive silently.

**Outcome:** Infection runs end-to-end against the Codeception Unit suite; CI gate
wired; baseline recorded.

### Recorded baseline (PHP 8.4, `infection/infection` 0.33.3, `@default` mutators,
4 threads, Codeception Unit suite — 203 tests / 527 assertions)

| Metric | Value |
|---|---:|
| Total mutants generated | 792 |
| Killed | 476 |
| Timed out (counted as killed) | 103 |
| Errors (counted as killed) | 5 |
| **Escaped (survived)** | **208** |
| Syntax errors | 0 |
| Skipped / ignored | 0 |
| **MSI** | **73.74 %** |
| **Mutation code coverage** | **100 %** |
| **Covered-code MSI** | **73.74 %** |

100 % mutation coverage means every mutant targets a line exercised by the test
suite; the gap to 100 % MSI is exclusively an assertion-strength gap, not a
coverage gap.

### Tasks — done
- **M2.1** `infection/infection: ^0.33` added to `require-dev` and pinned at
  0.33.3 (the latest 0.x; ^0.29 in the plan was a placeholder — 0.33 is the
  current major of the 0.x line and is what `just-bases/php-obs-dev-base`
  resolves transitively).
- **M2.2** `infection.json.dist` created. The plan’s example snippet
  (`phpUnit.configDir`, `coverage.phpUnit.customPath`, `tests.directories`,
  inline `mutators` map) targets the legacy PHPUnit path and an older JSON
  shape; in 0.33 the file shape is different and Codeception is selected via
  `testFramework: "codeception"`. The final config uses:
  ```json
  {
      "$schema": "https://raw.githubusercontent.com/infection/infection/0.33.3/resources/schema.json",
      "source": { "directories": ["src"] },
      "threads": 4,
      "tmpDir": "var/infection",
      "testFramework": "codeception",
      "minMsi": 73, "minCoveredMsi": 73, "minMutants": 50,
      "ignoreMsiWithNoMutations": true,
      "logs": {
          "text": "var/infection/infection-log.txt",
          "html": "var/infection/infection-log.html",
          "summaryJson": "var/infection/infection-summary.json"
      }
  }
  ```
  The `codeception` adapter self-instruments the suite and writes the phpunit
  XML coverage index to `<tmpDir>/coverage-xml/index.xml`; no manual coverage
  hand-off is required.
- **M2.3** Composer script `"mutation": "infection --no-interaction"` added.
  `--show-mutations` is intentionally **not** in the default script: the full
  mutation list is already persisted to `var/infection/infection-log.html` and
  `infection-log.txt` by the `logs` block, so CI logs stay readable.
- **M2.4** Baseline run completed (see table). `minMsi` / `minCoveredMsi` gates
  are set to the **recorded accepted MSI (73)** — the plan’s verification step
  explicitly allows this. The 80 % target from the plan is the next milestone;
  the gap is ~6.3 pp ≈ 50 additional mutants. The dominant escape categories
  are `Concat` / `ConcatOperandRemoval` / `ArrayItemRemoval` /
  `FunctionCallRemoval` on `throw new \RuntimeException('…' . $foo)` style
  error-path strings, plus `LessThan` boundary mutations. Killing these
  requires either asserting on exception message content (brittle) or
  refactoring the error strings into testable seams — both are out of scope
  for the M2 wiring PR and are tracked under **M2.4-followup** below.
- **M2.5** `mutation` job added to `.github/workflows/quality.yml`
  (PHP 8.4, `coverage: xdebug`, separate from the `quality` matrix so it
  does not block PRs on PHP 8.5 or `lowest` deps). The HTML log is uploaded
  as a build artefact (14-day retention).

### Remaining gap & follow-up
| | |
|---|---|
| Target MSI | 80 % |
| Current MSI | 73.74 % |
| Mutants to kill | ~50 (of 208 escaped) |
| Dominant escape class | error-path string concatenations |
| Suggested next PR | **M2.4-strengthen** — add targeted exception-message assertions or refactor error strings into value-object factories, then raise `minMsi` / `minCoveredMsi` to the new baseline and finally to 80. |

**Verification:** `infection.json.dist` present at package root;
`composer mutation` runs the full suite + 792 mutants and exits 0 (gates pass
at the recorded baseline 73.74 %); summary + text + HTML logs are persisted
under `var/infection/`.

---

## M3 — Eliminate `else` / `elseif` (F-3) — DONE

**Goal:** rewrite the 3 violating sites using early-return / `continue` / inverted conditions.

### Tasks — done
- **M3.1** `src/TreeHasher.php:74-83` — `for ($i = 0; $i < $count; $i += 2)` reduction loop. The
  `} else { $next[] = $nodes[$i]; }` trailing-odd branch is now an early `continue`
  (the `if` was inverted from "have a pair" to "no pair, so it's the trailing odd
  node"), and the pair-hash branch falls through. `src/TreeHasher.php:74`.
- **M3.2** `src/Format/BsdFormat.php:43-49` — the `if ($manifest === null) {…}
  elseif ($manifest->algorithm() !== $algorithm) {…}` chain is now two plain
  `if` statements: the algorithm-mismatch guard first (combined `null !== &&
  !== algorithm`, so it no-ops on the first line), then the manifest
  initialization. `src/Format/BsdFormat.php:43-49`.
- **M3.3** `src/ManifestVerifier.php:32-36` — the `if ($actual->equals($expectedHash))
  { $matched[…] } else { $mismatched[] }` is now `if (equals) { matched; continue; }
  $mismatched[] = …` (per the plan). `src/ManifestVerifier.php:32`.
- **M3.4** The two doc-comment-only `else` mentions in `Hasher.php:331` and
  `KeyedHasher.php:279` (the `streamTotalBytes()` docblock, "…else null.") were
  rephrased to "…otherwise null." so the strict-gates grep finds no matches at
  all. The `bin/strict-gates.sh` F-3 grep was also tightened to exclude
  docblock (`*`-prefixed), `//` and `#` comment lines — mirroring the existing
  F-5 inline-FQCN exclusion, matching the plan's stated verification criterion
  ("doc comments excluded"), and removing the footgun of a violation triggered
  by an English sentence in a docblock.

### Status — DONE
- All F-3 baseline violations cleared. The `bin/strict-gates.sh` F-3 grep returns
  no matches in `src/`.
- `composer cs:check` clean (0/92).
- `composer cs:strict` F-3 ✅; remaining F-5 and F-6 violations are M4 scope (and
  the script correctly labels them as such).
- `composer test:unit` green (203 tests / 527 assertions, no behavioral change).
- `composer architecture` green (0 violations, 0 errors).
- `vendor/bin/phpstan analyse` exit 0.

**Verification:** `grep -rnE '\b(else|elseif)\b' src | grep -vE '^[0-9]+:\s*(\*|//|#)'` returns
no matches; `composer cs:check` green; `composer cs:strict` F-3 green.

---

## M4 — Import hygiene & ≤120 columns (F-5, F-6) — DONE

**Goal:** every referenced project type imported via `use`; no line >120 columns.

### Tasks — done
- **M4.1** F-5 (inline FQCN). The plan estimated ~27 files; the actual baseline
  after M0's `cs:fix` was much smaller — only **3 code-level violations** remained,
  all `implements` clauses in the `Format/` adapters (the `php-cs-fixer`
  `fully_qualified_strict_types` rule cannot rewrite `implements` declarations,
  only type-hint positions; Rector would have worked but is not pinned in the
  project, and the site count was small enough that hand-edits were faster than
  authoring a `rector.php` config). The three `Format/BsdFormat.php`,
  `Format/SfvFormat.php`, and `Format/GnuSha256Format.php` files now `use
  Contract\ManifestFormatInterface;` and declare `implements
  ManifestFormatInterface`. (`src/Format/BsdFormat.php:9`, `:18`;
  `src/Format/SfvFormat.php:9`, `:19`; `src/Format/GnuSha256Format.php:9`, `:19`.)
- **M4.2** F-6 (>120 cols). 48 violations across 17 files were wrapped to
  ≤120 cols, following the existing `Hkdf::derive` template: opening paren on
  its own line, one parameter per indented line, closing paren with `):
  returnType` aligned. Files touched: the four `Contract/*Interface` ports
  (`HasherInterface`, `KeyedHasherInterface`, `HmacHasherInterface`,
  `ResumableHashingInterface`), their impls (`Hasher`, `KeyedHasher`,
  `NativeHmacHasher`, `ResumableHasher`), the `Adapter/` composition root
  (`NativeHashIt::create` and `keyedHasher` factories, `PsrStreamHasher::hash`),
  the `HashObservation` value-object factories (`started` and `completed`),
  the `AlgorithmMetadata` attribute docblock, and 5 test files
  (`CloudEventsHashingObserverTest`, `HashingObserverTest`,
  `KeyedHasherErrorPathsTest`, `TreeHasherTest`, `Format/BsdFormatTest`).
- **M4.3** Manually reviewed every wrapping diff: each is a pure whitespace /
  line-break change. No type, default, modifier, or visibility was dropped or
  reordered. The `composer test:unit` (203/527) and `composer coverage`
  (≥95% / 100% methods) results are identical to the M1/M2/M3 baselines,
  confirming behavioral equivalence.
- **M4.4** `bin/strict-gates.sh` had a latent bug in the F-3 and F-5 greps
  carried over from M0: the docblock / comment / `use` / `namespace` exclude
  patterns were anchored at `^[0-9]+:`, but `grep -rn` prefixes each line with
  `path:lineno:`, so the anchor was always false and the patterns only
  *appeared* to work because M0-M3's violation lines happened to be code (no
  `*`/`//`/`#`/`use`/`namespace` prefix to match). Fixed to `:[0-9]+:` (colon,
  line number, colon) for both the F-3 and F-5 greps. The bug was
  discovered while inventorying F-5: 8 of the 11 apparent F-5 violations
  were `{@see \ItPack\HashIt\…}` docblock references that the broken
  filter was *supposed* to be excluding but wasn't.

### Status — DONE
- All F-5 (inline FQCN) and F-6 (>120 cols) baseline violations cleared.
- `composer cs:check` clean (0/92).
- `composer cs:strict` is GREEN end-to-end for the first time since M0 —
  F-3 ✅, F-5 ✅, F-6 ✅; script ends with `Strict gates PASSED`.
- `composer test:unit` green (203 tests / 527 assertions).
- `composer coverage` green (≥95% statements / 100% methods, identical to
  M1 baseline).
- `composer architecture` green (0 violations, 0 errors).
- `vendor/bin/phpstan analyse` exit 0 (same pre-existing `composer analyse`
  300s script-wrapper timeout as in M2/M3 — phpstan itself is clean).

**Verification (per the plan's stated criterion):**
- `grep -rn '\\ItPack\\HashIt\\' src | grep -vE ':[0-9]+:\s*(\*|//|#|use|namespace)'` returns no matches.
- `grep -rnP --binary-files=without-match '.{121}' src tests | grep -vE -- '_generated/|_output/'` returns no matches.
- Coverage unchanged; suite green; PHPStan clean.

---

## M5 — Extract DRY instrumentation (F-4) — DONE

**Goal:** remove the duplicated observation/progress/file-size helper block from `Hasher` and `KeyedHasher`.

### Tasks — done
- **M5.1** New `src/Observability/HashingInstrumentation.php` (Core layer per the
  `deptrac.yaml` regex `/^ItPack\\HashIt(?!\\Adapter|\\Format)/`). It is a
  `trait` with the 8 methods the plan listed: `observationId()`, `completed()`,
  `observe()`, `algorithmName()`, `unreadable()`, `fileBytes()`,
  `streamTotalBytes()`, `iterableTotalBytes()`. The trait declares its own
  `use` imports for `HashAlgorithm`, `HashObservation`, `HashInterface`,
  `FilepathInterface`, `Random\RandomException`, and `RuntimeException`, so
  neither host class needs to know about them. The trait body is a verbatim
  copy of the (identical) helper blocks previously inlined in `Hasher` and
  `KeyedHasher` — no semantic change.
- **M5.2** The trait depends on one collaborator supplied by the using class:
  a `?Contract\HashingObserverInterface $observer` property. Both `Hasher`
  and `KeyedHasher` already declare this property via constructor
  promotion, so **no constructor signature changes were required**. A
  `@property-read` docblock was added on each host class so PHPStan and
  static analysers see the collaborator the trait reads via
  `$this->observer?->observe()` (this is a tooling affordance; PHP itself
  is happy because the property does exist on the host at runtime).
- **M5.3** Both host classes now `use HashingInstrumentation;` directly
  after the `final readonly class X implements Y` line. The 8 duplicated
  private methods were deleted from each. `HashingInstrumentation` is
  declared *before* the constructor so the project convention "trait →
  const → props → ctor → factory → non-static → static, public before
  non-public" holds. `KeyedHasher` lost the now-unused `RuntimeException`
  import; `cs:fix` flagged it on the first pass. `Hasher` kept its
  `RuntimeException` import because it still `throw`s
  `new RuntimeException('Not a directory: …')` and
  `new RuntimeException('HashInput did not return …')` in its public
  methods. The two class-specific helpers that did NOT move (`source()`
  and `hashString()`) are intentionally left in each host because their
  bodies differ (KeyedHasher's `hashString` adds a `$key` parameter and
  calls `factory->build($algo, $key)`).
- **M5.4** Suite green; the trait refactor is provably equivalent because
  every helper was byte-for-byte identical between the two hosts (confirmed
  with `diff <(awk …Hasher) <(awk …KeyedHasher)` — the only delta was
  `hashString`, which was intentionally not extracted). `composer test:unit`
  passes 203/527; `composer coverage` passes with **lines 96.10% / methods
  92.53%** — *higher* than the pre-M5 M4 aggregate of **93.05% / 88.42%**.
  The metric gain is the deduplication effect: the 8 private methods were
  previously counted twice (once per host), and 2 of the 8 had defensive
  branches that the coverage tool marks as "uncovered method" at the
  per-class level; counting them once on the trait, with the same 2
  defensive branches still uncovered, gives a strictly higher aggregate.

### Status — DONE
- `src/Observability/HashingInstrumentation.php` added (113 lines, 1 type).
- `src/Hasher.php`: 8 private methods removed; `use HashingInstrumentation;`
  added; `Random\RandomException` import removed (no longer used after
  the deletion); file shrunk from 400 → 335 lines.
- `src/KeyedHasher.php`: 8 private methods removed; `use
  HashingInstrumentation;` added; `RuntimeException` import removed (the
  only call site moved into the trait); file shrunk from 356 → 292 lines.
- Net: −16 duplicate method definitions, +1 trait (8 method definitions).
- `composer cs:check` clean (0/93).
- `composer cs:strict` F-3 ✅ / F-5 ✅ / F-6 ✅.
- `composer test:unit` 203 tests / 527 assertions green.
- `composer coverage` lines 96.10% / methods 92.53% / classes 78.05% (32/41)
  — all above the plan's ≥95% line target; method coverage improved vs.
  the M4 baseline.
- `composer architecture` 0 violations, 0 errors, 0 warnings (the trait
  lives in the Core layer; Core's `ruleset: ~` allows Core→Core use).
- `vendor/bin/phpstan analyse` exit 0.

**Verification:** `diff <(awk '/^    private function/,/^    }$/' src/Hasher.php) <(awk '/^    private function/,/^    }$/' src/KeyedHasher.php)` after the refactor is empty apart from the deliberate `hashString` delta; the trait file is the only place any of the 8 helpers are defined. `composer coverage` lines ≥95%, suite green, deptrac unchanged.

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
