# HashIt — In-Depth Project Review

> Review date: 2026-08-24
> Scope: `/home/quill/tinker/php/project/it-pack/src/HashIt` (the `it-pack/hash-it` package). Sibling packages were consulted **only** as convention baselines, per the review brief.
> Method: full source reading of `src/` (all 15 files: 8 core + 5 adapter + attribute + enum), all 6 test classes, both example scripts, packaging and QA configs (`composer.json`, `codeception.yml`, `phpstan.neon`, `.php-cs-fixer.php`, `extension.neon`), docs (`README.md`, `ROADMAP.md`, `IMPLEMENTATION_PLAN.md`), targeted greps (control flow, magic literals, interface backing, cross-namespace imports), a fresh run of every quality gate on PHP 8.5.8, plus behavioral probes and micro-benchmarks executed against the installed package.
> Finding IDs below are scoped to **this** document. Severity legend: 🔴 blocker · 🟠 major · 🟡 minor · 🟢 informational.

**Assumptions (stated per the review brief):**

- "Every class fully backed by interfaces" is interpreted as *swappable boundaries and external collaborators*. Value objects, enums, marker attributes, and the composition root are exempt — consistent with the precedent set in `DeriveIt/docs/archive/REVIEW_4.md`. The per-class verdict table is in §4.
- "Modern PHP leverage" is assessed for *fit*, not novelty. Features that add nothing to a small synchronous hashing utility (Fibers, lazy objects, weak maps) are marked N/A rather than recommended.
- "Green" means the three package gates (`test:unit`, `cs:check`, `analyse`) pass.
- This review documents findings only — no source files were modified while producing it.

---

## 0. Review Checklist (conceptual)

What this review covers, at a conceptual level:

- **Feature surface & correctness** — what the package promises (string/binary/file/stream/bulk/directory/HMAC hashing) versus what it delivers, verified by running the suite.
- **Architecture & hexagonal fit** — ports/adapters separation, the composition-root factory, dependency direction between core and adapters.
- **Interface backing & SOLID/DRY** — port coverage per class, responsibility placement, remaining duplication.
- **Control-flow hygiene & complexity** — absence of `else`/`elseif`, named constants over magic strings/numbers, method size.
- **Modern PHP leverage** — readonly classes, enums, attributes, property hooks, traits; fibers/lazy objects/weak maps where they would earn their place.
- **Performance & security** — wrapper overhead, memory ceilings, timing-safe comparison, key/algorithm handling.
- **Maintenance & DX** — packaging correctness, documentation currency, CI, architecture-test enforcement relative to monorepo conventions.

---

## 1. Executive Summary

HashIt is a small hexagonal hashing library rebuilt in July 2026 from a defective predecessor
(`docs/ROADMAP.md` §2 records the original blockers: infinitely-recursing property hooks, a
no-op `$binary` flag, a triple-encoded algorithm catalogue). The rebuild largely succeeded:
the algorithm catalogue is now single-source-of-truth with derived HMAC-safety metadata, the
core speaks only to owned ports for its *primary* path, an immutable incremental builder
validates at native-loop speed, and all quality gates are green:

| Check | Command | Result (2026-08-24, PHP 8.5.8) |
|-------|---------|-------------------------------|
| Unit tests | `composer test:unit` | ✅ 37 tests, 168 assertions |
| Coding standard | `composer cs:check` (PSR-12 + `@PHP84Migration`) | ✅ 0 of 22 files to fix |
| Static analysis | `composer analyse` (PHPStan level 5 + strict-rules + dead-code detector) | ✅ 0 errors |

The package is usable today and honest in its README. The two findings that keep it short of
its own "reference architecture" ambition are **F-1**: the core service `Hasher` directly
imports an adapter class (`NativeFilepathFileinfo`) — the single core→adapter edge in the
codebase, unguarded because HashIt ships no deptrac/phparkitect rule unlike six sibling
packages — and **F-2**: `composer.json` declares no `php` constraint at all, while the code
uses readonly classes (a PHP 8.2 feature) and the README claims `>= 8.1`.

| ID | Sev | Area | One-line summary |
|----|-----|------|------------------|
| F-1 | 🟠 | Hexagonal / DIP | Core `Hasher` imports concrete `Adapter\NativeFilepathFileinfo`; no architecture test guards the boundary |
| F-2 | 🟠 | Packaging | Missing `"php"` constraint; README floor (`>= 8.1`) contradicts readonly-class usage (≥ 8.2); monorepo convention is `^8.4`/`^8.5` |
| F-3 | 🟡 | DRY / magic values | Default `'sha256'` duplicated 9× across 5 files although `HashAlgorithm::default()` exists |
| F-4 | 🟡 | Interface backing | `Hasher` (the primary API) and `HashBuilder` have no interfaces; consumers bind to concrete classes |
| F-5 | 🟡 | Performance | `hashFile()` loads whole files into memory (measured: 200 MiB file → +200 MiB peak) despite an existing streaming path |
| F-6 | 🟡 | Contract robustness | Raw `ValueError`/`TypeError` leak through the public API; `HashContextInterface` lifecycle undocumented; non-seekable-stream semantics implicit |
| F-7 | 🟡 | Maintenance | No CI workflow, no deptrac/phparkitect rule, no `license`/`description` fields, `var/` not gitignored, empty `fixtures/` and `tests/_envs/` dirs |
| F-8 | 🟢 | Feature symmetry | HMAC path is hex-only and non-incremental (no keyed context factory port), while the plain path offers binary + stream + bulk |
| F-9 | 🟢 | Modern PHP consistency | Only 2 of 8 classes declare `final readonly`; four others are effectively immutable but undeclared |

---

## 2. Feature Surface

Delivered capabilities, each verified against the running suite (37 tests / 168 assertions):

| Capability | Entry point | Status |
|------------|-------------|--------|
| Text digest (default sha256) | `Hasher::hash()` | ✅ matches native `hash()` |
| Binary digest | `Hasher::hashBinary()` | ✅ |
| File hashing | `Hasher::hashFile()` | ✅ correct; memory concern → F-5 |
| Streamed hashing, chunked | `Hasher::hashStream()` | ✅ chunk-boundary test included; restores stream position (verified by probe: position 4 preserved) |
| Bulk file map | `Hasher::hashFiles()` | ✅ returns `path => Hash` |
| Directory scan (non-recursive) | `Hasher::hashDirectory()` | ✅ regular files only |
| Timing-safe verification | `Hasher::verify()`, `verifyBinary()` | ✅ delegates to `Hash::equals()` → `hash_equals()` |
| Incremental immutable builder | `HashBuilder` | ✅ branching/immutability tested; reuse-after-digest safe |
| Algorithm catalogue | `HashAlgorithm` backed enum, 60 cases | ✅ 44 HMAC-safe cases derived from `#[HmacSafe]` attribute (count verified by probe; README's "44" accurate) |
| HMAC | `HmacHasherInterface` / `NativeHmacHasher` | ✅ rejects non-HMAC-safe algorithms |

Gaps (none blocking): no recursive directory mode; HMAC has no binary output, streaming, or bulk variant (**F-8**) even though PHP natively supports keyed contexts via `hash_init($algo, HASH_HMAC, $key)` — a keyed `ContextFactoryInterface` variant would unlock streaming/bulk HMAC with no new primitives.

---

## 3. Architecture & Hexagonal Fit

The layout follows the monorepo doctrine (ports owned by core; technology isolated under
`Adapter\`; one composition root) and mirrors siblings like CommandIt:

```
Core   (ItPack\HashIt)          ports: ContextFactoryInterface, HashContextInterface,
                                       FilepathInterface, HashInterface, HmacHasherInterface
VO     (ItPack\HashIt)          Hash implements HashInterface
Edge   (ItPack\HashIt\Adapter)  Native{HashContext, ContextFactory, FilepathFileinfo,
                                       HmacHasher} + NativeHashIt (composition root)
```

**What works well:**

- `\HashContext` appears in exactly one file (`Adapter/NativeHashContext.php`) — the ROADMAP's central goal is met. A KMS-backed or fake implementation needs only the five ports plus rebinding in `NativeHashIt`.
- Factory usage is consistent and idiomatic: an injected port factory (`ContextFactoryInterface`) for contexts, private-constructor + static `start()` for the builder, `Hash::create()` / `NativeFilepathFileinfo::of()` conveniences, and `NativeHashIt::{create,hmacHasher}` as the wiring point.
- `HashBuilder`'s immutability (copy-per-append) is a genuinely safe design — measured cost ≈ zero (§8-B) because hash contexts copy cheaply.

**F-1 (🟠) — one core→adapter edge breaks the doctrine it advertises.**
`src/Hasher.php:7` imports `ItPack\HashIt\Adapter\NativeFilepathFileinfo` (used in `hashDirectory()`, line 121) so that directory enumeration can materialize paths. The README states "the core depends only on interfaces it owns"; this import violates that sentence, and nothing mechanically prevents regressions. Siblings enforce exactly this rule with deptrac (six packages per the repo-level `docs/REPORT.md`) or phparkitect (DisenchantIt); HashIt ships neither (see also F-7).

*Recommended fix (smallest first):* replace the concrete construction with a port — e.g. accept an injected `callable(FileInfo-ish): FilepathInterface` path-wrapper factory, or introduce a tiny `DirectoryListingInterface` port returning `list<FilepathInterface>` and move enumeration into the Adapter namespace. Then add a deptrac rule mirroring CommandIt's (`Adapter` layer may not be referenced from core).

---

## 4. Interface Backing (per-class verdict)

| Class | Kind | Backed by interface? | Verdict |
|-------|------|----------------------|---------|
| `Hash` | value object | ✅ `HashInterface` | OK (VO exemption would also apply) |
| `Hasher` | application service | ❌ none | **F-4** — primary public API is concrete-only; `NativeHashIt::create(): Hasher` propagates the concrete type |
| `HashBuilder` | application service | ❌ none | **F-4** (acceptable if deemed internal, but it is used by `Hasher::hashStream()`'s public path and documented in README) |
| `HashAlgorithm` | enum | exempt (enum = closed catalogue by design) | OK |
| `HmacSafe` | attribute | exempt (marker) | OK |
| `NativeHashContext` | adapter | ✅ `HashContextInterface` | OK |
| `NativeContextFactory` | adapter | ✅ `ContextFactoryInterface` | OK |
| `NativeFilepathFileinfo` | adapter | ✅ `FilepathInterface` | OK |
| `NativeHmacHasher` | adapter | ✅ `HmacHasherInterface` | OK |
| `NativeHashIt` | composition root | exempt per house convention | OK |

Net: adapters are 100% port-backed; the gap concentrates in the two core services (**F-4**).
A `HasherInterface` (and optionally a builder interface) would let consumers inject fakes and
would make the future bridge story symmetric with siblings that expose `Contract/` surfaces.

---

## 5. SOLID & DRY

- **SRP** — good overall. Each class has one reason to change; `Hash` holds state only, `Hasher` orchestrates sources, adapters translate. Borderline: `Hasher` spans string/file/stream/bulk/directory concerns; cohesion is acceptable ("produce a digest from a source"), and method-per-source keeps complexity low.
- **OCP** — algorithms extend via enum cases without touching logic ✓. New *sources* require editing `Hasher` (no source abstraction); deliberate simplicity, acceptable at this size, but note it when the API grows.
- **LSP** — trivially satisfied (no inheritance hierarchies).
- **ISP** — exemplary. Every port is 1–3 methods; nothing forces implementers to depend on methods they don't need.
- **DIP** — violated once, by **F-1** (core→adapter import).

**DRY findings:**

- **F-3 (🟡)** — `'sha256'` appears 9× across 5 files as the default algorithm literal
  (`Hasher` ×6, `HashBuilder:22`, `HmacHasherInterface:9`, `NativeHmacHasher:14`) while
  `HashAlgorithm::default()` exists, is tested, and is never used by these defaults.
  Recommended: default parameters to `HashAlgorithm::SHA256` (backed-enum cases are valid
  parameter defaults since 8.1) and narrow signatures to `string|HashAlgorithm` where strings
  must still be honored at runtime.
- `Hasher::{hash,hashBinary}` duplicate a 3-line body differing only in the binary flag; `verify`/`verifyBinary` similarly pair. Minor — consolidation into one private helper per pair would remove the last duplication without indirection cost.
- `RuntimeException('Unable to read file: ' . $file)` thrown identically at `Hasher.php:38` and `:43`. Extract once.
- The algorithm catalogue itself is genuinely single-source-of-truth now (attribute-derived HMAC set, runtime-reflected, memoized per process on immutable data — safe under long-running workers). The old triple-encoding defect is verifiably gone.

**Control flow & magic values:**

- `else`/`elseif`: **zero occurrences** in `src/` (grep-verified) ✓. Guard clauses throughout; `NativeHmacHasher`'s normalization is a clean early-return.
- Complexity: every method ≤ ~25 lines; highest cyclomatic complexity ≈ 5 (`hashStream`). No nesting beyond depth 2. ✓
- Magic numbers: only `65536` (`Hasher.php:64`) — recommend a named constant (e.g. `DEFAULT_CHUNK_SIZE_BYTES`). No other unnamed literals.
- Magic strings: the `'sha256'` cluster above (**F-3**); everything else routes through the enum.

---

## 6. Modern PHP Leverage

| Feature | Used? | Assessment |
|---------|-------|------------|
| Backed enums | ✅ | `HashAlgorithm` is the right tool; attribute metadata on cases avoids parallel lists |
| Attributes | ✅ | `#[HmacSafe]` with reflection derivation is the package's best design move |
| Readonly classes | ◑ | `Hasher`, `NativeFilepathFileinfo` are `final readonly`; `Hash`, `HashBuilder`, `NativeHashContext`, `NativeHmacHasher`, `NativeContextFactory` are effectively immutable but undeclared (**F-9**, 🟢 consistency nit — `final readonly` costs nothing and documents intent; note `Hash` uses promoted non-readonly props) |
| Property hooks / virtual properties | ✖ avoided deliberately | Endorsed: IMPLEMENTATION_PLAN §1.1 documents that get-only hooks caused the original infinite-recursion defect; the method-accessor form was consciously chosen. Revisit hooks only with backed storage |
| Constructor promotion | ✅ | everywhere applicable |
| First-class callables, enums-in-constants | n/a | no natural use site |
| Traits | ✖ none used | correct — nothing to share; no abuse |
| Fibers | N/A | synchronous utility; concurrency belongs to RunIt per ecosystem doctrine |
| Weak maps / weak refs | N/A | no object-graph caching need; enum memoization uses function-static on immutable data (safe) |
| Lazy objects | N/A | no heavy dependency graph to defer; sibling LazeIt owns that concern |

---

## 7. Performance

Measured on PHP 8.5.8 (opcache off, CLI; wall-time micro-benchmarks, `/tmp/kilo/hashit-bench.php`):

| Probe | Result | Interpretation |
|-------|--------|----------------|
| **A.** 100k × `Hasher::hash('…', 'sha256')` vs native `hash()` | 2.40 s vs 0.60 s → **4.0× overhead** (~18 µs/op) | Port indirection + VO allocation cost on tiny payloads; absolute cost negligible for realistic workloads (~55k ops/s single-thread). Document as tradeoff; no action needed |
| **B.** 10k × `HashBuilder->append(4 KiB)` immutable chain vs native `hash_update` loop | 1.89 s vs 2.10 s → **≈ parity (0.9×)** | Context copy is cheap; immutability is essentially free. Design validated |
| **C.** `hashFile` on a 200 MiB file vs `hashStream` | identical digests, similar wall time (disk-bound); **peak memory +200 MiB vs flat** | **F-5**: `hashFile` slurps via `file_get_contents` while the memory-bounded path exists one method away |

**F-5 (🟡) recommendation:** implement `hashFile()` as `fopen` + `hashStream` internally (keeping the readability pre-checks), bounding memory to one chunk regardless of file size. This also removes the current TOCTOU window between `is_file()/is_readable()` and the read — the post-read `false` check already covers failure, making `is_readable()` redundant either way.

Also verified behaviorally: `hashStream` preserves the caller's stream position (probe: position 4 restored after hashing) — good citizen semantics worth adding to the docblock.

---

## 8. Security

- **Timing safety:** `Hash::equals()` compares via `hash_equals()` after gating on algorithm+binary flag ✓. Cross-length comparison safely returns `false` (probe-verified).
- **HMAC guard:** `NativeHmacHasher` refuses algorithms outside the attribute-derived HMAC-safe set (`InvalidArgumentException`) ✓ — prevents silent misuse like `crc32` HMACs.
- **Legacy algorithms:** MD2/MD4/MD5/SHA1 remain selectable with no broken-crypto marking. Acceptable for an interop-focused catalogue (and `verify()` is generic), but consider a docs note or a companion `#[Insecure]`-style attribute so downstream tooling can warn.
- **Error surfaces:** exception messages embed raw file paths (`Hasher.php:38,43`) — minor information-disclosure consideration for shared logs; acceptable for a dev-facing utility.
- **Keys:** HMAC keys are never logged or stringified anywhere ✓.
- **Lifecycle:** see **F-6** below — the finalize-invalidates contract is enforced only by the underlying extension.

**F-6 (🟡) — exception taxonomy and lifecycle contracts leak abstractions.**
Probe results through the public API:

| Stimulus | Observed |
|----------|----------|
| `hash('abc', 'not-an-algo')` | raw `ValueError` ("hash_init(): Argument #1 …") from the adapter |
| `update()` after `finalize()` | raw `TypeError` ("must be a valid, non-finalized HashContext") |
| `HashAlgorithm::from('bogus')` in HMAC path | `ValueError` (while the same class throws `InvalidArgumentException` for CRC32) |
| `hashStream()` on a non-seekable stream | works (graceful degradation), but failed `fseek` restore is silent/undocumented |

None of these violate memory safety, and PHP's native messages are decent — but the package's own taxonomy elsewhere is `RuntimeException`/`InvalidArgumentException`, and behavior now depends on which `ContextFactoryInterface` implementation is bound (a non-native factory would fail differently). Recommendations: document the finalize-invalidates lifecycle on `HashContextInterface` (or model state explicitly), normalize unknown-algorithm input to a package exception (e.g. `tryFrom()` + `InvalidArgumentException` naming the offending value), and document non-seekable-stream semantics on `hashStream()`.

---

## 9. Maintenance & DX

- **Quality gates** run cleanly via composer scripts; Codeception choice matches the ConfigureIt/CommitIt cluster (monorepo split noted in repo-level REPORT.md — fine here). PHPStan runs at level 5 with strict-rules and `shipmonk/dead-code-detector` active via `just-bases/php-qa-base` — README's dead-code claim verified accurate.
- **F-2 (🟠) packaging:** `composer.json` omits `"php"` entirely. Consequences observed during this review: `php-cs-fixer` prints an explicit warning about the missing floor, the README claims `>= 8.1` while readonly classes require ≥ 8.2, and every real sibling declares `^8.4` or `^8.5` (repo-level report flags divergent floors as an ecosystem risk — HashIt is currently the outlier by having none). Recommend `"php": "^8.4"` (aligning with the monorepo) or at minimum `">= 8.2"` matching reality, plus `license` and `description` fields present in most siblings.
- **F-7 (🟡) tooling gaps vs siblings:** no `.github/workflows` CI (CommandIt/DeriveIt ship one), no deptrac/phparkitect architecture test (the direct enabler for keeping F-1 fixed), `var/` missing from `.gitignore` (surfaces as untracked noise in repo status), and empty `fixtures/` + `tests/_envs/` directories.
- **Documentation:** README is high-quality and nearly accurate — the two inaccuracies are the PHP-floor claim (F-2) and the layout table omitting `Hasher`'s adapter import (F-1). `ROADMAP.md`/`IMPLEMENTATION_PLAN.md` honestly record the pre-rebuild defects; worth archiving per DeriveIt's convention once the roadmap phases close.
- **Test posture:** meaningful behavioral assertions against native outputs (not tautologies). Gaps worth closing: stream-position restoration (currently probe-verified only), invalid-algorithm exception mapping (blocked until F-6), `equals()` length-mismatch case, and `HashBuilder` reuse-after-digest. Coverage tooling is configured (`codeception.yml`) but no coverage artifact is produced by any script.
- **Example:** `examples/hash.php` is runnable and representative ✓.

---

## 10. Findings Index & Suggested Order of Action

| Order | ID | Severity | Action |
|-------|----|----------|--------|
| 1 | F-2 | 🟠 | Add `php` constraint (+ license/description); reconcile README floor |
| 2 | F-1 | 🟠 | Remove core→adapter import in `Hasher` (port for path wrapping or directory listing); add deptrac rule |
| 3 | F-3 | 🟡 | Replace 9× `'sha256'` defaults with `HashAlgorithm::SHA256`; name the chunk-size constant; dedupe exception message |
| 4 | F-5 | 🟡 | Route `hashFile` through the stream path |
| 5 | F-6 | 🟡 | Document context lifecycle + stream semantics; normalize ValueError→package exceptions |
| 6 | F-4 | 🟡 | Introduce `HasherInterface` (builder optional) and return it from the composition root |
| 7 | F-7 | 🟡 | CI workflow, gitignore `var/`, drop empty dirs |
| 8 | F-9 | 🟢 | Declare `final readonly` on the four effectively-immutable classes |
| 9 | F-8 | 🟢 | Consider keyed-context port to unlock streaming/bulk/binary HMAC |

Items 1–2 are half-day changes that close the gap between "usable" and the package's stated
"reference architecture" ambition; items 3–6 are mechanical follow-ons; 7–9 are polish.

---

## Appendix — Verification Artifacts

- Gates: `vendor/bin/codecept run Unit` → OK (37 tests, 168 assertions); `vendor/bin/phpstan analyse` → 0 errors (level 5, strict-rules + dead-code detector loaded); `vendor/bin/php-cs-fixer fix --dry-run --diff` → 0 of 22 files.
- Behavioral probes: invalid-algorithm ValueError; non-seekable-stream digest correctness; stream-position restoration (position 4 → 4); update/finalize-after-finalize TypeErrors; `hmacSafeAlgorithms()` count 44/60; `HashAlgorithm::from('bogus')` ValueError; cross-length `equals()` → false.
- Benchmarks: §7 table A–C (wrapper overhead 4.0× @ ~18 µs/op; builder-chain parity 0.9×; 200 MiB `hashFile` peak-memory delta vs flat streaming).
