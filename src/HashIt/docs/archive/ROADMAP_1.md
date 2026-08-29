# HashIt — Roadmap

> Living strategy document for the `ItPack\HashIt` package.
> Companion to [`IMPLEMENTATION_PLAN.md`](./IMPLEMENTATION_PLAN.md) (tactical, file-level).
> Findings that motivate this roadmap are in the repo-level review:
> `docs/FINDINGS_DEFECTS.md`, `docs/FINDINGS_SOLID_DRY.md`, `docs/FINDINGS_HEXAGONAL.md`.

## 1. Vision

`HashIt` should be a **small, correct, hexagonal hashing library**:

- The **core** depends only on *ports it owns* (interfaces, value objects).
- **Adapters** wrap PHP's `hash_*` extension behind those ports, bound in a composition root.
- The algorithm catalogue is defined **once** and derived everywhere else.
- Every public type is **installable, type-checked, and tested**.

Today it is none of these (see §2). The roadmap turns it into a reference-quality package
that `DeriveIt`/`ExtendIt` can depend on without leaking `HashContext` into their cores.

## 2. Current state (why this roadmap exists)

| Area | State | Severity |
|------|-------|----------|
| `Hash` value object | 🔴 Non-functional — `get` accessors recurse infinitely, never assigned | blocker |
| `HashFinalizer` | 🔴 Constructor `bool $binary` ignored; always returns text | blocker |
| Algorithm catalogue | 🔴 Encoded **3×** (`HashAlgorithm`, `HashAlgorithmEnum`, `HashAlgorithms::HMAC_SAFE`) | DRY |
| Ports leak `HashContext` | 🟠 `finalize(HashContext)`, `build(): HashContext` couple core to stdlib | hexagonal |
| No composition root | 🔴 Ports exist but nothing wires `Factory→Updater→Finalizer→Hash` | DIP |
| `HashIt` packaging | 🔴 No `composer.json`, no autoload, no tests | blocker |
| `HashValue`, `HashAlgorithmEnum` | 🟡 Dead/duplicate types | DRY |
| `FilepathFileinfo` | 🟡 `SPLFileInfo` mis-cased; adapter unused | hygiene |
| `ClassInspector`-style stubs | n/a (belongs to ExtendIt) | — |

## 3. Phases

### Phase 0 — Foundation & packaging
Make the package real and measurable.
- Add `composer.json` (PSR-4 `ItPack\HashIt\` → `src/`), `phpstan.neon`, `.php-cs-fixer.php`.
- Create `src/HashIt/docs/` (this file + implementation plan).
- Establish a CI-able baseline: `composer validate`, `phpstan`, `php-cs-fixer --dry-run`.

**Exit criteria:** `composer install` autoloads the namespace; static analysis runs.

### Phase 1 — Repair core value objects
- Rewrite `Hash` as an immutable value object with a real constructor (kills the infinite-recursion defect).
- Fix `HashFinalizer` (honour `$binary`) **or** delete its misleading parameter and rename to `TextHashFinalizer`.
- Delete `HashValue` (superseded by `Hash`).

**Exit criteria:** a `Hash` can be constructed and serialised; `HashFinalizer` returns binary when asked.

### Phase 2 — Single source of truth for algorithms (DRY)
- Keep **one** authoritative definition: the `HashAlgorithm` backed enum with `#[HmacSafe]`.
- Derive `HMAC_SAFE` from the attribute at runtime (`cases()` + attribute reflection).
- Delete `HashAlgorithmEnum.php` and the hand-maintained `HMAC_SAFE` array in `HashAlgorithms.php`.
- Keep `HashAlgorithms` only if still needed; otherwise fold its `default()`/helper intent into `HashAlgorithm`.

**Exit criteria:** exactly one algorithm list; `HashAlgorithm::hmacSafeAlgorithms()` returns the same set without a duplicated constant array.

### Phase 3 — Hexagonal ports (stop leaking `HashContext`)
- Introduce an **owned** `HashContextInterface` port; the core never references PHP's `\HashContext`.
- Provide `NativeHashContext` adapter wrapping `\HashContext` (`hash_init`/`hash_update`/`hash_final`).
- Repoint `ContextFactoryInterface::build()` to return `HashContextInterface`.
- Fold `HashUpdaterInterface`/`HashFinalizerInterface` into `HashContextInterface` (DRY + fewer leaks), or keep them as thin delegates — see plan §4 for the decision.
- `FilepathInterface` + `FilepathFileinfo` kept; fix the `SplFileInfo` casing.

**Exit criteria:** grep of `src/` shows **zero** references to `\HashContext` outside the `Adapter/` namespace.

### Phase 4 — Composition root & use-cases
- Add a core service `Hasher` depending only on the ports, producing a `Hash`.
- Add a `hashFile(FilepathInterface): Hash` use-case (consumes the orphaned `FilepathInterface`).
- Add a `HashFactory`/`Container` (composition root) binding the native adapters.

**Exit criteria:** `Hasher` yields correct digests for `md5`/`sha256`/etc. and binary/text modes; file hashing works end-to-end.

### Phase 5 — Tests & quality gates
- Unit tests: `Hash` (construction, `__toString`, `length`), `HashAlgorithm` (HMAC-safe derivation), `NativeHashContext` (update→finalize parity with `hash()`), `Hasher` (string + file, binary flag).
- `phpstan` at level 8+ clean; `php-cs-fixer` clean.
- A tiny `examples/hash.php` smoke script.

**Exit criteria:** all tests green; static analysis and style clean.

### Phase 6 — Docs
- Package `README.md` (purpose, install, usage sketch, "why ports").
- Link from repo-level `docs/CODE_REVIEW.md`.

## 4. Definition of Done

- [x] `composer.json` present; package installs and autoloads.
- [x] No 🔴 defects remain (`Hash`, `HashFinalizer`, packaging).
- [x] Algorithm list defined exactly once.
- [x] Core (`src/HashIt/` outside `Adapter/`) contains **no** `\HashContext` reference.
- [x] A composition root wires adapters; `Hasher` produces `Hash` for strings **and** files.
- [x] Unit tests cover every public type; `phpstan` + `php-cs-fixer` clean.
- [x] `README.md` + links to the review docs.

### Future phases (7–11) Definition of Done

- [x] `Hash::equals()` + `Hasher::verify()`/`verifyBinary()` — constant-time, phpstan clean, unit-tested.
- [x] `HashBuilder` immutable incremental API (`start`/`append`/`digest`); `NativeHashContext::copy()` wraps `hash_copy`.
- [x] `Hasher::hashStream()` chunked stream hashing; non-stream inputs rejected.
- [x] `Hasher::hashFiles()` + `hashDirectory()` bulk hashing; `NativeFilepathFileinfo::of()` factory.
- [x] `HmacHasherInterface` + `NativeHmacHasher` (HMAC-safe gated); `NativeHashIt::hmacHasher()` exposed.
- [x] Full suite green: `phpstan` 0 errors, 37 tests / 168 assertions, `php-cs-fixer` 0.

## 5. Feature principles (for future work)

`HashIt` stays a **single-purpose hashing library**. Future features must:

- Remain within hashing (strings, files, streams, keyed/HMAC, verification) — no encoding/transport/conversion concerns.
- Reuse the existing ports (`HashContextInterface`, `ContextFactoryInterface`, `FilepathInterface`) and the `Hash` value object.
- Add minimal public surface — prefer DX sugar and closing real capability gaps over new concepts.

## 6. Future phases (implemented)

> All completed. Each phase keeps the hexagonal boundary: core depends only on ports; adapters live in `Adapter/`; wiring happens in the composition root.

### Phase 7 — Timing-safe verification (DX / security) ✅
- `Hash::equals(HashInterface $other): bool` implemented with `hash_equals($this->value(), $other->value())` (constant-time; avoids `==` leakage). Rejects mismatched algorithm/binary flag.
- `Hasher::verify()` / `verifyBinary()` wire it through.
- No new ports. Unit tested.

**Exit criteria:** `Hash::equals()` returns true only for equal digests; verified constant-time primitive is used. ✅

### Phase 8 — Immutable incremental `HashBuilder` ✅
- New core `HashBuilder` depending only on `ContextFactoryInterface` + `HashContextInterface`.
- `HashBuilder::start(string|HashAlgorithm $algo = 'sha256', bool $binary = false): self`.
- `append(string $data): self` returns a **new** builder whose context is a copy (`hash_copy`) — API immutability.
- `digest(): HashInterface` copies before `hash_final` so it is callable repeatedly.
- `NativeHashContext` gains `copy()` (wraps `hash_copy`); composition root unchanged (builder takes a factory).

**Exit criteria:** chained `append()` calls across cloned contexts yield the same digest as a single `Hasher::hash()`; original builder is unaffected by later appends. ✅

### Phase 9 — Stream / resource hashing ✅
- `Hasher::hashStream($stream, string|HashAlgorithm $algo = 'sha256', int $chunkSize = 65536): HashInterface` reads in fixed chunks and appends via the Phase 8 builder — large payloads without full in-memory load. Restores stream position; rejects non-stream resources.

**Exit criteria:** `hashStream()` over a file resource equals `hashFile()` over the same file; chunked large inputs verified. ✅

### Phase 10 — Bulk file hashing (DX convenience) ✅
- `Hasher::hashFiles(iterable<FilepathInterface> $paths, string|HashAlgorithm $algo = 'sha256'): array<string, HashInterface>` keyed by path string.
- `Hasher::hashDirectory(string $dir, …): array` — non-recursive, regular files only.
- DX sugar: `NativeFilepathFileinfo::of(string $path): self` static factory so callers skip `new \SplFileInfo(...)`.

**Exit criteria:** `hashFiles([...])` returns a path→`Hash` map matching per-file `hashFile()` results. ✅

### Phase 11 — HMAC hasher (keyed hashing) ✅
- `HmacHasherInterface` port + `NativeHmacHasher` adapter built on `hash_hmac`.
- Restricted to `#[HmacSafe]` algos — throws `InvalidArgumentException` if `!$algo->isHmacSafe()`.
- Composition root gains `NativeHashIt::hmacHasher()`.

**Exit criteria:** `hmac()` output equals `hash_hmac()`; non-HMAC-safe algos are rejected. ✅

## 7. Out of scope (still excluded)

- `CodeIt` / `ShowCaseIt` — separate empty packages, untouched.
- Arbitrary encoding / base64 / serialization helpers unrelated to hashing.
- "Hash every algorithm at once" convenience — call `hash()` per algorithm instead (avoids surface bloat).
- URN / content-addressing string builders (borderline; could be a tiny `Hash::toUrn()` later only if actually demanded).
- Hashing exotic non-string / non-stream inputs.

