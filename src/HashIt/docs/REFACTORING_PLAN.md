# HashIt — Refactoring Plan

- **Source review:** [`docs/REVIEW.md`](./REVIEW.md) (findings F-1…F-9, interface-backing audit §4, verification artifacts in the appendix).
- **Scope:** `src/HashIt` (`it-pack/hash-it`, unreleased / pre-1.0). All changes are internal refactors plus two deliberate, documented API-shape changes (M3 constructor, M5 return-type widening) that are safe because no release has shipped.
- **How to use this document:** work top-down through M0 → M8. Every milestone lists Goal, Affected files, Tasks, Tests, Verification, Dependencies, and Acceptance criteria. A milestone is done only when all quality gates are green **and** its acceptance criteria hold. Land one commit per milestone following the repository's convention (`Complete refactoring plan - Milestone Mx`).

**Assumptions (stated up front):**

- The PHP floor moves to `^8.4` (monorepo convention, already implied by the `@PHP84Migration` fixer rule and the QA toolchain running on 8.5). Backed-enum parameter defaults (used throughout) are legal since 8.1, so this costs nothing.
- The package is pre-release: constructor/return-type changes in M3/M5 are treated as acceptable and are called out explicitly rather than papered over with BC shims.
- Where CommitIt's guardrails inspire a rule (e.g. preserve the original `Throwable` as `previous`), it is adopted verbatim as house convention.
- M8 is explicitly **optional** and starts with a design decision, not code.

---

## 0. Guardrails (must hold for every milestone)

These come from the review and the monorepo doctrine; violating them silently re-introduces the problems being fixed.

1. **Dependency direction is non-negotiable.** After M3 the core namespace must never reference `ItPack\HashIt\Adapter\*`; `deptrac` enforces this at 0 violations / 0 skips.
2. **Preserve the original `Throwable` as `previous`** wherever a native error (`ValueError`, `TypeError`) is normalized into a package exception.
3. **No `else`/`elseif`, no magic scalars.** Guard clauses only; every repeated literal becomes an enum case or a named constant (review §5).
4. **Ports stay tiny and core-owned.** New ports go in the core namespace (`src/`), 1–3 methods each, implemented only under `Adapter/` (review §5-ISP).
5. **Tests come with the change**; negative cases assert the *package* exception taxonomy, never raw `ValueError`/`TypeError`.
6. **Verification per milestone:** `composer test:unit`, `composer analyse`, `composer cs:check` (and `composer architecture` once M3 lands) must pass before moving on.

---

## 1. Execution order & traceability

```
M0  Packaging baseline          (F-2, F-7 hygiene)      <- everything depends on a real floor
M1  DRY & constants             (F-3)                   <- pure internal, zero risk
M2  Contracts & exceptions      (F-6, security note)    <- taxonomy locked before refactor waves
M3  Hexagonal repair + deptrac  (F-1, F-7 enforcement)  <- boundary closed & mechanically guarded
M4  Memory-bounded hashFile     (F-5)                   <- builds on M1 helpers + M2 taxonomy
M5  Interface backing           (F-4)                   <- after surface stabilizes in M3/M4
M6  final readonly consistency  (F-9)                   <- mechanical polish, floor guaranteed by M0
M7  CI                          (rest of F-7)           <- last, so it runs the full gate set incl. architecture
M8  OPTIONAL keyed HMAC         (F-8)                   <- stretch; design decision first
```

| Finding | Milestone | Finding | Milestone |
|---------|-----------|---------|-----------|
| F-1 🟠 core→adapter import | M3 | F-6 🟡 contracts/exceptions | M2 |
| F-2 🟠 packaging/floor | M0 | F-7 🟡 maintenance/tooling | M0, M3, M7 |
| F-3 🟡 DRY/magic values | M1 | F-8 🟢 HMAC symmetry | M8 (optional) |
| F-4 🟡 interface backing | M5 | F-9 🟢 readonly consistency | M6 |
| F-5 🟡 memory ceiling | M4 | | |

---

## M0 — Packaging baseline (F-2, F-7 hygiene)

**Goal:** Declare a truthful platform floor and clean repo noise so later milestones can rely on PHP ≥ 8.4 semantics and a quiet working tree.

**Affected files:**
- `composer.json`
- `.gitignore`
- `codeception.yml`
- `README.md`
- deletions: `fixtures/`, `tests/_envs/`

**Tasks:**
1. `composer.json`: add `"php": "^8.4"` to `require`; add `"license": "MIT"` and a one-line `"description"` (align wording with the README's opening paragraph). This also silences the php-cs-fixer "No PHP version requirement" warning observed in review §9.
2. `.gitignore`: add `var/` (currently surfaces as untracked noise in `git status`).
3. Delete the empty `fixtures/` and `tests/_envs/` directories **and** remove the `envs: tests/_envs` line from `codeception.yml` (the path is referenced by config; deleting the folder alone risks Codeception warnings). Re-run the suite to confirm.
4. `README.md` line 13: change "Requires PHP `>= 8.1`" to "`^8.4`" — the current claim contradicts readonly-class usage (≥ 8.2) and the new constraint.

**Tests to add:** none (packaging only).

**Verification:**
```bash
composer validate
vendor/bin/codecept run Unit && composer cs:check && composer analyse
git status --porcelain   # var/ noise gone
```

**Dependencies:** none.
**Acceptance:** `composer validate` clean; fixer warning gone; README floor matches manifest; gates green; no empty directories tracked.

---

## M1 — DRY & constants (F-3)

**Goal:** Collapse the eleven-fold `'sha256'` default duplication onto the enum, name the chunk-size literal, and deduplicate the paired-method bodies and error message.

**Affected files:**
- `src/Hasher.php` (lines 15, 24, 33, 38, 43, 49, 54, 64, 96, 112)
- `src/HashBuilder.php` (line 22)
- `src/HmacHasherInterface.php` (line 9)
- `src/Adapter/NativeHmacHasher.php` (line 14)

**Tasks:**
1. Change every default parameter from `'sha256'` to `HashAlgorithm::SHA256` — eight signatures in `Hasher` (`hash`, `hashBinary`, `hashFile`, `verify`, `verifyBinary`, `hashStream`, `hashFiles`, `hashDirectory`), plus `HashBuilder::start()`, `HmacHasherInterface::hmac()`, `NativeHmacHasher::hmac()`. Runtime normalization (`$algorithm instanceof HashAlgorithm ? … : …`) is untouched, so behavior is identical for callers who omit or explicitly pass strings.
2. In `Hasher`, add `private const DEFAULT_CHUNK_SIZE_BYTES = 65536;` and use it as the `hashStream()` default.
3. Deduplicate the twice-thrown `'Unable to read file: '` construction (lines 38/43) behind one private helper, e.g. `private function unreadable(string $file): \RuntimeException` returning the configured exception.
4. Consolidate the near-duplicate bodies: private `hashString(string $data, string|HashAlgorithm $algorithm, bool $binary): HashInterface` backing both `hash()`/`hashBinary()`; same treatment for `verify()`/`verifyBinary()`.
5. Grep gate: `grep -rn "'sha256'" src/` must afterwards match only the `HashAlgorithm::SHA256` case definition.

**BC note:** changing a parameter *default value* is invisible to callers (defaults are not part of prototype compatibility; signature types are unchanged). No consumer impact.

**Tests to add:** none required — the existing suite pins behavior for default, enum, and string arguments.

**Verification:** gates (guardrail #6) + the grep above.
**Dependencies:** none (can run parallel to M0 conceptually, sequenced after it for clean commits).
**Acceptance:** `'sha256'` literal exists exactly once (the enum case); `65536` appears only as the named constant; gates green.

---

## M2 — Contract documentation & exception taxonomy (F-6)

**Goal:** Make the ports specify what the native adapter actually does, and route predictable native failures into the package's `InvalidArgumentException` taxonomy instead of leaking `ValueError`.

**Affected files:**
- `src/HashContextInterface.php`
- `src/ContextFactoryInterface.php`
- `src/Adapter/NativeContextFactory.php`
- `src/Adapter/NativeHmacHasher.php`
- `src/Hasher.php` (`hashStream()`)
- `README.md` (algorithms section)

**Tasks:**
1. First extend the review probe with a `copy()`-after-`finalize()` case, then document the verified lifecycle on `HashContextInterface`: `finalize()` consumes the context; subsequent `update()`/`copy()`/`finalize()` calls fail (native engines raise an `\Error`). State that implementations backed by other technologies must behave equivalently or document their deviation.
2. `ContextFactoryInterface::build()`: add `@throws \InvalidArgumentException` when the algorithm is unknown — making the taxonomy part of the *port contract*, so any future non-native factory behaves consistently.
3. `NativeContextFactory::build()`: wrap `new NativeHashContext($algorithm)` — catch the `ValueError` raised by `hash_init()` and rethrow `InvalidArgumentException('Unknown hashing algorithm: ' . $algorithm, previous: $e)` (guardrail #2). Implementation detail: do this inside the factory, keeping `NativeHashContext` a dumb wrapper.
4. `NativeHmacHasher::hmac()`: replace `HashAlgorithm::from($algorithm)` with `tryFrom()` + `InvalidArgumentException('Unknown hash algorithm: ' . $algorithm)` so the unknown-string path matches the existing CRC32-guard exception style instead of a bare `ValueError`.
5. `Hasher::hashStream()`: guard `chunkSize >= 1` (`InvalidArgumentException`); document seek semantics — rewinds to position 0 when seekable, hashes from the current position otherwise, restores the prior position when seekable (probe-verified in review §7).
6. `README.md` algorithms section: add a short legacy-algorithm caution (MD2/MD4/MD5/SHA1 remain selectable for interop; prefer SHA-256+ for security-sensitive use).

**Tests to add:**
- `tests/Unit/NativeContextFactoryTest.php` (or extend `NativeHashContextTest`): unknown algorithm string throws `InvalidArgumentException` with the offending value in the message, `getPrevious()` is the original `ValueError`.
- Extend `HmacHasherTest`: `hmac(…, 'bogus')` → `InvalidArgumentException`.
- Extend `HasherTest`: `chunkSize = 0` → `InvalidArgumentException`.

**Verification:** gates; re-run the review probes (`§8` table) — every stimulus must now yield a package-taxonomy exception or documented behavior.
**Dependencies:** M1 (exception-message helper).
**Acceptance:** no public code path lets `ValueError`/`TypeError` escape un-normalized where the cause is caller input; lifecycle and stream semantics written down on the ports.

---

## M3 — Hexagonal repair + architectural enforcement (F-1, F-7 enforcement)

**Goal:** Remove the single core→adapter import (`Hasher.php:7`) behind a new owned port, and make the boundary mechanically enforced with deptrac so it cannot regress.

**Affected files:**
- new: `src/DirectoryListingInterface.php`, `src/Adapter/NativeDirectoryListing.php`, `deptrac.yaml`
- `src/Hasher.php` (constructor + `hashDirectory()`)
- `src/Adapter/NativeHashIt.php` (wiring)
- `composer.json` (require-dev + `architecture` script)
- `README.md` (layout table + doctrine sentence)
- tests

**Tasks:**
1. Add the port (core-owned, ISP-tiny):
   ```php
   interface DirectoryListingInterface
   {
       /** @return list<FilepathInterface> regular files directly inside $directory, sorted by path */
       public function regularFiles(string $directory): array;
   }
   ```
2. Implement `NativeDirectoryListing` in the Adapter namespace: `DirectoryIterator` → filter `isFile()` → `NativeFilepathFileinfo::of($item->getPathname())`, sorted by `(string) $path` for deterministic maps (a small improvement over the unspecified iterator order; document it).
3. `Hasher`: promote a second constructor dependency `private DirectoryListingInterface $listing`; rewrite `hashDirectory()` to validate `is_dir()` (keeping today's `RuntimeException('Not a directory: …')`) and delegate enumeration; delete the `use ItPack\HashIt\Adapter\NativeFilepathFileinfo;` import.
4. `NativeHashIt::create()` wires `new Hasher(new NativeContextFactory(), new NativeDirectoryListing())` — it remains the only place that knows concrete adapters.
5. Add `deptrac.yaml` adapted from `../CommandIt/deptrac.yaml` to this flat layout — layers: `Core` (`src/[A-Z]*.php` top level), `Adapter` (`src/Adapter/(?!NativeHashIt)`), `CompositionRoot` (`src/Adapter/NativeHashIt.php`); rules: Core must not depend on Adapter or CompositionRoot; Adapter may depend on Core; CompositionRoot may depend on all. Add `"architecture": "vendor/bin/deptrac analyse"` to composer scripts and the deptrac dev requirement exactly as CommandIt declares it.
6. Update `README.md`: layout table gains `DirectoryListingInterface` + `NativeDirectoryListing`; the "why ports" doctrine sentence is now literally true again.

**BC note (deliberate):** `Hasher`'s constructor gains a required second parameter. Package is unreleased; the only production call site is the composition root. Recorded here per the CommitIt guardrail that public-surface changes get written down before code.

**Tests to add:**
- First genuinely isolated core test: a fake `DirectoryListingInterface` + fake `ContextFactoryInterface` driving `Hasher::hashDirectory()` end-to-end without the native extension — proving the port seam M5 will formalize.
- Determinism: two consecutive `hashDirectory()` runs return identical key order with the native listing.
- Existing `testHashDirectory*` cases keep passing through `NativeHashIt::create()`.

**Verification:** gates **plus** `composer architecture` (0 violations) and `grep -rn "Adapter\\\\" src/*.php` → no matches.
**Dependencies:** M0 (floor for dev-tool install), M1 (helpers).
**Acceptance:** zero `Adapter\` references from core; deptrac green and wired as a composer script; README doctrine accurate.

---

## M4 — Memory-bounded hashFile (F-5)

**Goal:** Bound `hashFile()` memory to one chunk regardless of file size by routing it through the already-proven streaming path.

**Affected files:**
- `src/Hasher.php` (`hashFile()`)
- `tests/Unit/HasherTest.php`

**Tasks:**
1. Rewrite `hashFile()`: keep the cheap `is_file()` pre-check (clear early error, avoids a PHP warning from `fopen` on missing paths); drop the redundant `is_readable()` (TOCTOU-prone and superseded by the open-failure check — review §9).
2. Open with `fopen($file, 'rb')`; on `false` throw the shared unreadable exception (M1). Then:
   ```php
   try {
       return $this->hashStream($handle, $algorithm);
   } finally {
       fclose($handle);
   }
   ```
   `hashStream()`'s rewind/restore logic is harmless on a fresh handle and its chunk guard/taxonomy come from M2 for free.
3. Do **not** add an automated peak-memory assertion — `memory_get_peak_usage(true)` is process-global and monotonic, which makes such a test flaky in suites. Verification is behavioral tests + a benchmark run instead.

**Tests to add:**
- Equivalence: payload spanning >2 chunks hashed via `hashFile` vs native `hash()` (mirror the stream test's `str_repeat` pattern).
- Missing path still throws `RuntimeException` (existing test keeps passing).

**Verification:** gates; re-run review benchmark C (`/tmp/kilo/hashit-bench.php` pattern) — peak-memory delta for `hashFile` must be ≈ chunk-sized rather than file-sized, digests unchanged.
**Dependencies:** M1, M2.
**Acceptance:** 200 MiB file hashes with ~64 KiB-scale memory; identical digest; gates green.

---

## M5 — Interface backing for the core services (F-4)

**Goal:** Close the review's §4 audit gap: the primary public API becomes port-backed, so consumers can inject fakes and the composition root stops propagating a concrete type.

**Affected files:**
- new: `src/HasherInterface.php`
- `src/Hasher.php` (implements)
- `src/Adapter/NativeHashIt.php` (return type)
- tests

**Tasks:**
1. Introduce `HasherInterface` declaring the seven public operations (`hash`, `hashBinary`, `hashFile`, `verify`, `verifyBinary`, `hashStream`, `hashFiles`, `hashDirectory`) with the docblocks migrated from `Hasher`; `final readonly class Hasher implements HasherInterface`.
2. Widen `NativeHashIt::create(): HasherInterface`.
3. **Deferred by decision:** a `HashBuilderInterface` is *not* added now — the builder is constructed and consumed entirely inside core (`Hasher::hashStream()`), has no external consumer, and an interface would mirror one implementation 1:1. Revisit when an outside caller first injects a builder; recorded here so the decision is findable.

**BC note (deliberate):** return-type widening concrete→interface is safe for callers invoking methods; it only affects consumers type-hinting the concrete `Hasher` (none in-tree besides tests). Pre-release window makes this the right moment.

**Tests to add:**
- `assertInstanceOf(HasherInterface::class, NativeHashIt::create())` and same for `hmacHasher()` (already interface-typed).
- M3's fake-driven `hashDirectory` test retro-fitted to depend on `HasherInterface` only.

**Verification:** gates; review §4 verdict table re-audited — every swappable collaborator port-backed, exemptions limited to enum/attribute/composition-root.
**Dependencies:** M3, M4 (surface stable before extracting the interface).
**Acceptance:** no consumer-facing concrete class in the primary API path; gates green.

---

## M6 — `final readonly` consistency (F-9)

**Goal:** Declare intent on the four effectively-immutable classes that lack the class modifier, so the whole package reads uniformly.

**Affected files:**
- `src/Hash.php`, `src/HashBuilder.php`, `src/Adapter/NativeHashContext.php`, `src/Adapter/NativeHmacHasher.php`, `src/Adapter/NativeContextFactory.php`

**Tasks:**
1. Convert each to `final readonly class` (all properties are single-assignment today — verified during the review; `HashBuilder` already declares per-property readonly, which the class modifier subsumes).
2. Run the full suite — the builder-immutability and branching tests double as regression nets for accidental mutation.

**Tests to add:** none (behavior-preserving; existing immutability tests cover it).
**Verification:** gates + `grep -rn "^final class" src/` → empty.
**Dependencies:** M0 (readonly classes require ≥ 8.2; floor guarantees 8.4).
**Acceptance:** every instantiable class in `src/` is `final readonly` except the composition root (which holds no state — convert it too if trivially possible, else leave documented).

---

## M7 — CI workflow (remainder of F-7)

**Goal:** Run the complete gate set automatically on every push/PR, matching sibling packages.

**Affected files:**
- new: `.github/workflows/quality.yml`

**Tasks:**
1. Adapt `../CommandIt/.github/workflows/quality.yml`: PHP `^8.4` setup, `composer install`, then the four gates — `test:unit`, `analyse`, `cs:check`, `architecture` (the last only meaningful after M3; include it now since M7 lands last).
2. Trigger on push + pull_request against `main`, mirroring CommandIt's triggers.

**Tests to add:** none.
**Verification:** local run of all four scripts green; YAML parses (`php -l` is not applicable — eyeball against the sibling file or `actionlint` if available).
**Dependencies:** M3 (so the architecture gate exists), M0 (floor).
**Acceptance:** workflow file present, mirrors the sibling's job structure, executes the full gate set.

---

## M8 — OPTIONAL: keyed contexts → symmetric HMAC (F-8)

**Goal:** Unlock streaming/incremental, binary, and bulk HMAC using the existing primitives, restoring feature symmetry with the plain-hashing path (review §2 gap).

> **Start with a design decision, not code.** Two open questions must be answered in writing (plan comment or ADR) before implementation:
> 1. Where do keyed variants live? Widening `HmacHasherInterface` strains ISP (review §5 praises its minimality); recommended shape: a new tiny port `KeyedContextFactoryInterface::build(string|HashAlgorithm $algorithm, string $key): HashContextInterface` implemented via `hash_init($algo, HASH_HMAC, $key)`, letting `HashBuilder::start()` do incremental HMAC unchanged, plus a small dedicated hasher interface for the convenience methods.
> 2. Should a `#[Insecure]` companion attribute land here (machine-readable broken-crypto marking extending the M2 README note)?

**Tasks (after the decision):**
1. Port + `NativeKeyedContextFactory` adapter + composition-root wiring.
2. Convenience surface per decision: `hmacBinary()`, `hmacStream()`, `hmacFiles()` on the agreed interface.
3. Tests: streamed HMAC equals `hash_hmac` over the full payload; non-HMAC-safe algorithm guard fires through the keyed factory path; binary flag honored.

**Verification:** gates + `composer architecture` (new adapter respects boundaries).
**Dependencies:** M2 (taxonomy), M3 (deptrac guards the new adapter).
**Acceptance:** incremental HMAC parity with native one-shot `hash_hmac()`; gates green.

---

## Definition of done (whole plan)

- All findings F-1…F-9 closed or explicitly deferred with rationale in this document (expected: F-8 → M8 decision point).
- Review §10 "Suggested order of action" fully traced: items 1–2 → M0/M3, 3–6 → M1/M4/M2/M5, 7 → M0+M7, 8 → M6, 9 → M8.
- Gates green at every commit; `docs/REVIEW.md` receives a follow-up note per milestone ID as findings close (mirroring DeriveIt's REVIEW_4 "verified closed" convention).
- `ROADMAP.md`/`IMPLEMENTATION_PLAN.md` move to `docs/archive/` once M5 closes, per the sibling convention noted in review §9.
