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

- [ ] `composer.json` present; package installs and autoloads.
- [ ] No 🔴 defects remain (`Hash`, `HashFinalizer`, packaging).
- [ ] Algorithm list defined exactly once.
- [ ] Core (`src/HashIt/` outside `Adapter/`) contains **no** `\HashContext` reference.
- [ ] A composition root wires adapters; `Hasher` produces `Hash` for strings **and** files.
- [ ] Unit tests cover every public type; `phpstan` + `php-cs-fixer` clean.
- [ ] `README.md` + links to the review docs.

## 5. Out of scope (for now)

- HMAC helpers (`hash_hmac` wrappers) — the `#[HmacSafe]` attribute already marks candidates; a later phase can add `HmacHasher`.
- Streaming / incremental hashing over resources (the context already supports it; no public API yet).
- `CodeIt` / `ShowCaseIt` — empty, untouched.
