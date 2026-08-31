# Review — `it-pack/hash-it`

- **Package:** `it-pack/hash-it` (hexagonal hashing library on top of PHP's `ext-hash`)
- **Reviewer:** automated in-depth review (Kilo)
- **Date:** 2026-08-31
- **Scope:** `src/HashIt/src` (56 PHP files) + `src/HashIt/tests` (24 test files) + tooling (`composer.json`, `deptrac.yaml`, `.php-cs-fixer.php`, `infection`)
- **PHP target:** `^8.4` (so enums w/ attributes, `readonly`, typed constants, property hooks are all available)

## Assumptions

1. The review criteria were taken verbatim from the request (SOLID/DRY, hexagonal purity, no `else`/`elseif`, no magic literals, imports-at-top, declaration ordering, one-class-per-file, ≤120 columns, factory-only instantiation, ≥95% coverage, no cross-test coverage leakage, mutation testing). Where the repository's own tooling is **weaker** than the requested bar (e.g. php-cs-fixer does not enforce import-at-top or column width), findings are still reported against the requested bar, not the repo's weaker lint.
2. `src/HashIt/tests` coverage was produced by running the project's own `composer coverage` (Codeception Unit suite, 174 tests / 487 assertions, all green) and parsing `tests/_output/coverage.xml`.
3. Value objects (DDD-style immutable data holders such as `Hash`, `HashManifest`, `VerificationReport`, `HashObservation`, `HashMismatch`, `HashProgress`) are **excluded** from the strict "every class backed by an interface" judgement, since interfaces are a port/contract concern and not every data holder needs one. Services and adapters are judged strictly.
4. Mutation testing was assessed by inspecting for an `infection` configuration and `require-dev`/composer-script wiring; no mutation run was possible because no config exists.

## Executive scorecard

| Dimension | Status | Notes |
|---|---|---|
| Architecture / hexagonal | PASS (minor gaps) | Layered Core/Format/Adapter/CompositionRoot; one adapter lacks a port (`PsrStreamHasher`). |
| Ports & adapters / factories | PASS (minor) | `NativeHashIt` is a clean composition root; a few core classes still `new` value objects directly. |
| SOLID | PASS | Strong ISP/OCP/LSP/DIP; duplicated instrumentation helper methods are the only DRY slip. |
| DRY | MEDIUM | `Hasher` and `KeyedHasher` duplicate the entire observation/progress/file-size helper set. |
| Modern PHP leverage | MEDIUM | Excellent enums/attributes/`readonly`; property hooks and traits under-leveraged; fibers/weak-maps/lazy objects not applicable. |
| No abstract classes | PASS | Zero abstract classes found. |
| One class per file (PSR-4) | PASS | Every file declares exactly one type. |
| No `else`/`elseif` | FAIL | 3 files use `else`/`elseif`. |
| No magic literals | MEDIUM | Vocabulary constants mostly good (`DEFAULT_CHUNK_SIZE_BYTES`, `FRAME_LENGTH_BYTES` live in interfaces), but `Hkdf` has bare `255`/`32`/`"\0"`. |
| Imports at top of file | MEDIUM | ~27 files use inline FQCN instead of `use` imports (inconsistent, even within a file). |
| Declaration ordering | PASS | No private/protected method precedes a public one (excluding intentional private constructors). |
| ≤120 columns | MEDIUM | 10 files exceed 120 cols, almost all due to inline-FQCN return types. |
| Security | PASS | `hash_equals`, `@unserialize(allowed_classes:false)`, `random_bytes` IDs — strong. |
| Performance | PASS (note) | Streaming/chunked; immutable `HashBuilder` copies the context per `append` (documented trade-off). |
| DX / maintainability | PASS | Thorough README, 31 examples, ADRs, deptrac guardrails. |
| Test coverage ≥95% | FAIL | **89.41% statements / 81.32% methods.** Core `Hasher` (81%) and `KeyedHasher` (81.1%) are the largest gaps. |
| No coverage leakage | UNVERIFIED | Per-class tests exist; precise cross-test leakage not measured (needs per-test coverage assertions). |
| Mutation testing | FAIL | `infection` not in `require-dev`, no `infection.json`, not wired into scripts. |

---

## 1. Checklist (conceptual) — status

- **Architecture & hexagonal purity** — mostly strong; `PsrStreamHasher` lacks a port and a few core classes reach for concretes. *(PASS w/ gaps)*
- **Code quality & modern PHP** — enums/attributes/`readonly` used well; `else`/`elseif` in 3 files, inline-FQCN imports in ~27 files, >120-col lines in 10 files. *(MEDIUM)*
- **Leverage of modern PHP** — good baseline; property hooks / traits for DRY are the missed opportunities; fibers/weak-maps/lazy-objects are not applicable here. *(MEDIUM)*
- **Security, performance & DX** — strong security posture and DX; performance is sound with one documented immutable-builder trade-off. *(PASS)*
- **Test maturity** — green suite but coverage 89.4% (<95%); mutation testing not configured. *(FAIL)*

---

## 2. Detailed findings

### F-1 (HIGH) — Test coverage is below the 95% requirement
**Evidence:** `coverage.xml` → **statements 1418/1586 = 89.41%**, **methods 296/364 = 81.32%**. Classes under 95% statement coverage include `Hasher` (81.0%), `KeyedHasher` (81.1%), `HashInput/StreamInput` (76.5%), `HashInput/FileInput` (77.8%), `Adapter/PsrStreamHasher` (78.6%), `Adapter/NativeKeyedContextFactory` (83.3%), `VerificationReport` (84.6%), `HashInput/TempSink` (87.5%), `Format/SfvFormat` (90.5%), `Format/BsdFormat` (91.3%), `Adapter/Observability/CloudEventsHashingObserver` (92.1%), `HashAlgorithm` (93.9%), `Format/GnuSha256Format` (94.4%).
**Impact:** The requested ≥95% gate is not met; several public and private paths in the two primary entry-point classes are unexercised.
**Recommendation:** Add targeted tests for the uncovered paths (e.g. `Hasher::hashDirectory`/`hashFiles`/`hashStream` with progress, `KeyedHasher` symmetric methods, error branches in `FileInput`/`StreamInput`/`TempSink`, `VerificationReport` miss-path, `NativeKeyedContextFactory`). Re-run `composer coverage` and gate CI on ≥95%.

### F-2 (HIGH) — Mutation testing is not leveraged
**Evidence:** `infection` binary exists in `vendor` (from the QA base) but is **not** in `require-dev`, there is **no `infection.json`/`.dist`**, and no composer script (`composer.json` `scripts` has only `test:unit`, `coverage`, `cs:*`, `analyse`, `architecture`).
**Impact:** No mutation score; surviving mutants could mask weak assertions even where line coverage looks adequate.
**Recommendation:** Add `infection/infection` to `require-dev`, add `infection.json.dist` (source=`src`, tests=`tests`, `bootstrap=...`, `coverage=phpunit`), and a `composer mutation` script; target a mutation score (e.g. ≥80%) in CI.

### F-3 (HIGH) — `else` / `elseif` used in 3 files (rule: avoid `else`/`elseif`)
**Evidence:**
- `src/TreeHasher.php:80` — `} else {` inside the reduction loop (promote trailing odd node).
- `src/Format/BsdFormat.php:45` — `} elseif ($manifest->algorithm() !== $algorithm) {`.
- `src/ManifestVerifier.php:34` — `} else {` after `$actual->equals($expectedHash)`.
**Impact:** Violates the requested control-flow style; the `else` branches are all eliminable (`continue`/`early-return`/invert). Note `Hasher`/`KeyedHasher` correctly avoid `else` everywhere — these three are the outliers.
**Recommendation:** Rewrite without `else`/`elseif` (e.g. `ManifestVerifier` `if (equals) { matched[...] = ...; continue; } mismatched[] = ...;`). Add php-cs-fixer `no_superfluous_elseif` (already on) **and** a custom/rector rule or CI grep to ban bare `else`.

### F-4 (MEDIUM) — DRY: observability/progress/file-size helpers duplicated between `Hasher` and `KeyedHasher`
**Evidence:** Both classes contain near-identical private methods: `observationId()` (`Hasher.php:315`, `KeyedHasher.php:263`), `completed()` (`:300` / `:251`), `observe()` (`:307` / `:258`), `algorithmName()` (`:320` / `:268`), `streamTotalBytes()` (`:335` / `:283`), `iterableTotalBytes()` (`:357` / `:305`), `fileBytes()` (`:289` / `:240`), `unreadable()` (`:325` / `:273`).
**Impact:** Maintenance hazard — a change to observation semantics must be made in two places; drift risk.
**Recommendation:** Extract a `HashingInstrumentation` trait (or an injected `ObservationEmitter`/`ProgressReporter` collaborator) holding `observationId/observe/completed/algorithmName/streamTotalBytes/iterableTotalBytes/fileBytes/unreadable`. This also satisfies "prefer traits/composition".

### F-5 (MEDIUM) — Inconsistent imports: inline FQCN instead of `use`
**Evidence:** ~27 files reference project types via leading-backslash FQCN (e.g. `\ItPack\HashIt\Contract\HashInterface` in `implements`/type-hints/returns) while others correctly `use` them. Highest counts: `Hasher.php` (18), `KeyedHasher.php` (15), `TreeHasher.php` (10), `ResumableHasher.php` (7), `HashBuilder.php` (6), `CompositeInput.php` (4). Some files mix both styles (e.g. `Hasher` imports `HashAlgorithm` yet FQCNs the `Contract` types).
**Impact:** Violates "all referenced classes/interfaces/enums imported at the top"; harms readability and static-analysis consistency. (Functionally valid PHP.)
**Recommendation:** Normalize to `use` imports project-wide. php-cs-fixer's `no_unused_imports`/`ordered_imports` are on but do not *force* imports; add `rector` with `ImportNamespacedName`/FQCN-to-import or a CI grep for `\ItPack\HashIt`.

### F-6 (MEDIUM) — Lines exceeding 120 columns
**Evidence:** 10 files: `KeyedHasher.php`, `Adapter/NativeHashIt.php`, `Adapter/PsrStreamHasher.php`, `HashObservation.php`, `AlgorithmMetadata.php`, `Contract/ResumableHashingInterface.php`, `Adapter/NativeHmacHasher.php`, `Hasher.php`, `ResumableHasher.php`, `Contract/HmacHasherInterface.php`. The great majority are long method signatures whose **return types are inline FQCN** (see F-5) — the two findings are correlated.
**Impact:** Violates the ≤120-column rule and harms diff readability.
**Recommendation:** Fix F-5 (imports) and wrap long signatures one-parameter-per-line (the repo already does this for some methods, e.g. `Hkdf::derive`). Add a column-width check to CI.

### F-7 (MEDIUM) — `new` outside factories/builders for value objects
**Evidence:** `Hasher.php:286` (`new Hash`), `Hasher.php:277` (`new NativeAlgorithmSource`), `KeyedHasher.php:237` (`new Hash`), `KeyedHasher.php:228` (`new NativeAlgorithmSource`), `TreeHasher.php:44` (`new TempSink`), `TreeHasher.php:58` (`Hash::create`), `ManifestVerifier.php:26/35/39` (`new Filepath` / `new HashMismatch` / `new VerificationReport`), `Format/BsdFormat.php:44/49`, `Format/SfvFormat.php`, `Format/GnuSha256Format.php`, `ResumableHasher.php:29/36` (`new ResumableHash`).
**Impact:** Violates "new instances only in factories/builders, injected via DI". `HashBuilder` (a builder) and `Hash`/`HashObservation` (self-factories via `create()`) are fine; the above are not factories yet still construct collaborators.
**Recommendation:** Introduce small dedicated factories (`HashFactory`, `ManifestFactory`, `FilepathFactory`, `ResumableHashFactory`) and pass them where needed; keep `NativeHashIt` as the only place that assembles concretes. (Note: `NativeAlgorithmSource` is in **Core**, not Adapter, so `Hasher`/`KeyedHasher` referencing it is deptrac-clean — but injecting it still improves DIP.)

### F-8 (MEDIUM) — `PsrStreamHasher` adapter has no port interface
**Evidence:** `src/Adapter/PsrStreamHasher.php:24` — `final class PsrStreamHasher` with **no `implements`**; there is no `StreamHasherInterface` in `Contract/`. Consumers (e.g. `examples/vertical/29-psr7-streams.php`) depend on the concrete class.
**Impact:** Breaks hexagonal symmetry (every other adapter implements a `Contract` port); reduces testability/substitutability and ties callers to a concrete.
**Recommendation:** Add `Contract/StreamHasherInterface` (`hash(StreamInterface, algorithm, chunkSize): HashInterface`) and have `PsrStreamHasher implements StreamHasherInterface`; optionally expose it from `NativeHashIt`.

### F-9 (MEDIUM) — Optional-adapter SDKs are in `vendor` but not in `require-dev`
**Evidence:** `vendor/open-telemetry` and `vendor/cloudevents` are **present**, but `composer.json` lists them only under `suggest`, not `require-dev`. The observer tests (`OtelHashingObserverTest`, `CloudEventsHashingObserverTest`) import those SDKs directly, so they would fail to even load on a clean `composer install`.
**Impact:** Non-reproducible CI — the green suite observed here depends on packages absent from the declared dev-dependencies.
**Recommendation:** Move `open-telemetry/api` and `cloudevents/sdk-php` (and any test-only clients) into `require-dev`, or guard the test files with `@requires`/skip-when-missing-class so they are explicitly skipped rather than broken.

### F-10 (LOW) — Magic numbers in `Hkdf`
**Evidence:** `src/KeyDerivation/Hkdf.php:45` (`255 * $hashLen` RFC 5869 cap), `:34` (`int $length = 32` default derived-key length), `:57` (`str_repeat("\0", $hashLen)`). Contrast with `HasherInterface::DEFAULT_CHUNK_SIZE_BYTES` and `HashInputInterface::FRAME_LENGTH_BYTES`, which are correctly hoisted into interfaces.
**Impact:** Violates "avoid magic literals; vocabularies live in interfaces". Minor, but inconsistent with the rest of the codebase.
**Recommendation:** Introduce named constants (e.g. `private const int RFC5869_MAX_BLOCKS = 255;`, `private const int DEFAULT_DERIVED_KEY_LENGTH = 32;`) — or, if the vocabulary is cross-cutting, a `KeyDerivationConstants` interface.

### F-11 (LOW) — Value-object/`kind` vocabularies
**Evidence:** `HashObservation` uses class constants `STARTED/PROGRESS/COMPLETED/FAILED` (`HashObservation.php:17-20`); `HashOperation` is a proper `enum` (`HashOperation.php`). The `kind` strings are well-constant-ized. `AlgorithmStatus` and `HashAlgorithm` are enums — good.
**Impact:** Mostly good. The `string $kind` field is still stringly-typed; an `enum HashObservationKind` would be stricter.
**Recommendation (optional):** Promote `HashObservation::$kind` to a backed enum for exhaustiveness in the `switch` statements of the observers.

### F-12 (LOW) — Immutable builder copies the hash context on every `append`
**Evidence:** `HashBuilder::append` (`HashBuilder.php:45-51`) does `$next = $this->context->copy(); $next->update($data);` and returns a new builder. Hashing an N-chunk stream therefore performs N `hash_copy` operations.
**Impact:** Intentional safety/immutability trade-off (documented in the class docblock). For very large streams with small chunks this is O(N) extra copies vs a single mutable context.
**Recommendation:** Acceptable as-is; if profiling shows cost, consider a lazily-materialized single context or a `LazyObject` for the context. Document the trade-off in the ADR.

---

## 3. What is done well (preserve)

- **Hexagonal layering is real and enforced.** `deptrac.yaml` defines Core / Format (walled) / Adapter / CompositionRoot with explicit ruleset edges; the "core depends on nothing but itself" invariant (guardrail #7) is structurally guaranteed. `NativeHashIt` is a textbook composition root — the only place that knows every concrete adapter.
- **Vocabulary constants live in interfaces** where they cross boundaries: `HasherInterface::DEFAULT_CHUNK_SIZE_BYTES` (`HasherInterface.php:13`), `HashInputInterface::FRAME_LENGTH_BYTES` (`HashInputInterface.php:22`).
- **Modern PHP used appropriately:** `enum HashAlgorithm: string` with `#[HmacSafe]`/`#[AlgorithmMetadata]` attributes and memoized reflection (`HashAlgorithm.php`); `enum AlgorithmStatus`, `enum HashOperation`; pervasive `final readonly` classes; typed constants (`public const int …`); named-argument attribute usage.
- **Security-conscious:** `Hash::equals` uses `hash_equals` (constant-time) (`Hash.php:52`); `ResumableHasher::decode` uses `@unserialize(..., ['allowed_classes' => false])` (no object injection) (`ResumableHasher.php:44`); observation IDs via `random_bytes`.
- **No abstract classes; one type per file; declaration ordering correct** (no private/protected method precedes a public one except intentional private constructors).
- **DI via ports:** `Hkdf` depends only on `KeyedHasherInterface` (not the native extension) — core stays extension-agnostic.
- **No Yoda conditions** anywhere (scan found only variable-on-left comparisons).
- **Strong DX:** 19 KB README, 31 runnable examples, ADRs referenced, deptrac + phpstan + cs-fixer + codeception wired.

---

## 4. Modern-PHP leverage matrix

| Feature | Used? | Assessment |
|---|---|---|
| Enums (backed) | YES | `HashAlgorithm`, `AlgorithmStatus`, `HashOperation` — exemplary. |
| Attributes | YES | `#[HmacSafe]`, `#[AlgorithmMetadata]` drive the catalogue. |
| `readonly` classes/props | YES | Default for value objects & adapters. |
| Typed/`const` expressions | YES | `public const int DEFAULT_CHUNK_SIZE_BYTES`. |
| Property hooks (`get`/`set`) | NO | Opportunity only (e.g. virtual `length()`); low value given readonly VO semantics. |
| Traits / composition | PARTIAL | None used; the F-4 duplication is the natural place for a trait. |
| Fibers | NO | Not applicable — hashing is synchronous and CPU-bound. |
| WeakMap / WeakReference | NO | Not needed; enum/reflection caches use `static` (enums are singletons). |
| Lazy objects | NO | Not needed for short-lived value objects. |
| `final` everywhere | YES | Good — closes extension points to the port hierarchy. |

---

## 5. Hexagonal / ports-adapters assessment

- **Ports (`Contract/*`):** 21 interfaces covering hashing, keyed hashing, tree hashing, resumable hashing, KDF, argon2, pbkdf2, context factories, algorithm source, observers, manifest formats, hash/filepath/input/sink/progress. Granular (good ISP).
- **Adapters (`Adapter/*`):** `NativeHashContext`, `NativeContextFactory`, `NativeKeyedContextFactory`, `NativeHmacHasher`, `NativeArgon2`, `NativePbkdf2`, `NativeDirectoryListing`, `NativeFilepathFileinfo`, `PsrStreamHasher`, and the two observers — all implement a port **except `PsrStreamHasher`** (F-8).
- **Composition root:** `NativeHashIt` exposes `create()`, `hmacHasher()`, `keyedHasher()`, `kdf()`, `pbkdf2()`, `argon2()`, `treeHasher()`, `resumable()` — each returns the port interface. This is the correct single point of concrete wiring.
- **Walled `Format` namespace:** deptrac forbids Core→Format; `ManifestVerifier` and the hashers deliberately delegate (de)serialization to `Format/*` (guardrail #7). Well executed.
- **Gap:** `PsrStreamHasher` (F-8) and the duplicated instrumentation (F-4) are the only structural blemishes.

---

## 6. SOLID / DRY summary

- **SRP:** Classes are tightly focused; `Hasher`/`KeyedHasher` mix orchestration + observation but cohesively.
- **OCP:** New algorithms = new enum case; new format = new class implementing `ManifestFormatInterface`; no modification of existing code.
- **LSP:** Every adapter is substitutable for its port.
- **ISP:** Ports are small and role-specific.
- **DIP:** Core depends on `Contract` interfaces; the only concrete reach is `NativeAlgorithmSource` (Core, not Adapter) and a few value-object `new`s (F-7) — both minor.
- **DRY:** One real violation — the `Hasher`/`KeyedHasher` helper duplication (F-4).

---

## 7. Prioritized recommendations

**Must-fix (gates the stated criteria)**
1. Raise statement coverage to ≥95% (F-1) — add tests for the 13 listed classes, prioritizing `Hasher`/`KeyedHasher`.
2. Configure and run mutation testing via `infection` (F-2); add to CI.
3. Remove the 3 `else`/`elseif` sites (F-3).

**Should-fix (quality / consistency)**
4. Extract the duplicated instrumentation into a trait/collaborator (F-4).
5. Normalize all references to `use` imports and enforce ≤120 cols (F-5, F-6).
6. Centralize value-object creation in factories injected via DI (F-7).
7. Give `PsrStreamHasher` a `StreamHasherInterface` port (F-8).
8. Move optional SDKs into `require-dev` (or guard their tests) (F-9).

**Nice-to-have**
9. Hoist `Hkdf` magic numbers into named constants (F-10).
10. Consider `enum HashObservationKind` and property hooks where they add value (F-11).
11. Document the immutable-builder copy trade-off in an ADR (F-12).

---

## Appendix A — Mechanical scan results

- `else`/`elseif` (code): `TreeHasher.php:80`, `Format/BsdFormat.php:45`, `ManifestVerifier.php:34`. (Two further hits were doc-comment-only in `Hasher.php:331` / `KeyedHasher.php:279`.)
- `abstract class` / `abstract`: **none**.
- Multiple type declarations per file: **none** (PSR-4 clean).
- Lines >120 cols: 10 files (see F-6).
- Inline FQCN (missing `use`): ~27 files (see F-5).
- Yoda conditions: **none** found.
- Private/protected method before first public (excl. constructors): **none**.
- `new` outside factory/builder: see F-7 list.

## Appendix B — Coverage detail (from `tests/_output/coverage.xml`)

- Statements: 1418 / 1586 = **89.41%**
- Methods: 296 / 364 = **81.32%**
- Lowest statement-coverage classes: `HashInput/StreamInput` 76.5%, `HashInput/FileInput` 77.8%, `Adapter/PsrStreamHasher` 78.6%, `Hasher` 81.0%, `KeyedHasher` 81.1%, `Adapter/NativeKeyedContextFactory` 83.3%, `VerificationReport` 84.6%, `HashInput/TempSink` 87.5%, `Format/SfvFormat` 90.5%, `Format/BsdFormat` 91.3%, `Adapter/Observability/CloudEventsHashingObserver` 92.1%, `HashAlgorithm` 93.9%, `Format/GnuSha256Format` 94.4%.
- Suite: 174 tests, 487 assertions, all passing.
