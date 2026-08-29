# HashIt — Additional Features Roadmap (next-level)

- **Source plan:** [`docs/ADDITIONAL_FEATURES_PLAN.md`](./ADDITIONAL_FEATURES_PLAN.md) (vertical V1–V6, horizontal H1–H6, enablers E1–E3, phased A→D).
- **Baseline:** post-M8 state — all `REFACTORING_PLAN.md` milestones M0–M8 ✅ done; gates green (61 tests / 207 assertions · PHPStan 0 · php-cs-fixer 0 · deptrac 0). Every new milestone must keep all gates green.
- **Scope:** `src/HashIt` (`it-pack/hash-it`, pre-1.0). New public surface is allowed here (unlike the refactor, this is feature work, not pure refactor) **but must obey §5/§7 of `docs/archive/ROADMAP_1.md`** and the scope boundary in the plan (serialization/encoding stays out of core).
- **How to use this document:** work top-down through the execution order. Every milestone lists Goal, Affected files, Tasks, Tests, Verification, Dependencies, Acceptance. A milestone is done only when all quality gates are green **and** its acceptance criteria hold. Land one commit per milestone following the repository convention (`Complete additional features - Milestone NF-x: <title>`).

**Assumptions (stated up front):**

- The package is still pre-release, so adding new ports/methods is acceptable and called out explicitly (unlike the refactor's "no new surface" bias).
- New ports stay in the core namespace (`src/`), 1–3 methods each, implemented only under `Adapter/` (or a clearly-named sub-namespace for aggregates). Bridges to sibling packages (H3) are **separate packages**, not core changes.
- `[SCOPE]` items (manifest/SRI text serialization) touch §7's "no encoding/serialization" exclusion. Each such task carries an explicit scope decision as its first sub-task; the recommended home is a **separate `Format` namespace or sibling package**, keeping HashIt core logic-only.
- Sibling integrations reference the monorepo packages already present (`AsyncIt` for concurrency, `DeriveIt` for telemetry conventions, `CommandIt` for CLI conventions). They are `suggest`-only to avoid hard coupling.

---

## 0. Guardrails (must hold for every milestone)

Carried from `REFACTORING_PLAN.md`, plus two new rules for this phase.

1. **Dependency direction is non-negotiable.** Core must never reference `ItPack\HashIt\Adapter\*`; `deptrac` enforces this at 0 violations / 0 skips. New aggregates live in core or a core sub-namespace.
2. **Preserve the original `Throwable` as `previous`** wherever a native error is normalized into a package exception.
3. **No `else`/`elseif`, no magic scalars.** Guard clauses only; repeated literals become enum cases or named constants.
4. **Ports stay tiny and core-owned** (1–3 methods); bulk services (KDF, manifest verify) may aggregate ports but must not import concrete adapters.
5. **Tests come with the change**; negative cases assert the *package* exception taxonomy, never raw `ValueError`/`TypeError`.
6. **Verification per milestone:** `composer test:unit`, `composer analyse`, `composer cs:check`, `composer architecture` must pass before moving on.
7. **§7 serialization boundary.** Anything that turns a `Hash`/manifest into *text* (base64/SRI lines, `sha256sum` formatting, URN strings beyond the `Hash::toUrn()` sliver) lives in a separated `Format` namespace or a sibling package — never mixed into the hashing core.
8. **Bridges are separate packages.** Concurrency (H3) and optional framework integrations are their own packages depending on HashIt via `suggest`; they do not alter HashIt's gates.

---

## 1. Execution order & traceability

```
Phase A — quick wins (low risk, high alignment)
  NF-A1  V2  Algorithm strength metadata (E1)            [core enum metadata]
  NF-A2  H6  Hash::toUrn() sliver                        [borderline-allowed]
  NF-A3  H5  Framework DI / service config               [wiring only, no core]
  NF-A4  H1  Progress & observability port (E2)          [core port + wiring]
  NF-A5  H4  PSR stream / HTTP-body hashing              [adapter, optional dep]

Phase B — vertical depth (crypto & extensibility)
  NF-B1  V1  Key Derivation (HKDF / PBKDF2 / Argon2)      [core + adapters]
  NF-B2  V3  AlgorithmSource port (E3)                   [discovery]
  NF-B3  V6  Canonical HashInput (structured hashing)    [core framing]

Phase C — horizontal reach (ecosystem)
  NF-C1  H2  Manifest verify (logic) + [SCOPE] formatters [logic in core, text out]
  NF-C2  H3  Async bridge (sibling AsyncIt)              [separate package]

Phase D — research / speculative (gated on spike approval)
  NF-D1  V4  Tree / parallel chunk hashing               [spike → implement]
  NF-D2  V5  Resumable hashing                           [spike → likely defer]
```

| Plan item | Milestone | Type | Phase | Scope flag |
|-----------|-----------|------|-------|------------|
| V2 (E1) | **NF-A1** | Vert | A | — |
| H6 | **NF-A2** | Horiz | A | borderline §7 |
| H5 | **NF-A3** | Horiz | A | — |
| H1 (E2) | **NF-A4** | Horiz | A | — |
| H4 | **NF-A5** | Horiz | A | SRI → [SCOPE] |
| V1 | **NF-B1** | Vert | B | — |
| V3 (E3) | **NF-B2** | Vert | B | — |
| V6 | **NF-B3** | Vert | B | — |
| H2 | **NF-C1** | Horiz | C | [SCOPE] text |
| H3 | **NF-C2** | Horiz | C | separate pkg |
| V4 | **NF-D1** | Vert | D | new concept |
| V5 | **NF-D2** | Vert | D | likely defer |

---

## NF-A1 — Algorithm strength metadata (V2 / E1) — ⬜ TODO

**Goal:** Add machine-readable algorithm strength so callers (and later milestones V1/H2/H6) can make safer choices. Resurrects the M8-deferred `#[Insecure]` as a richer attribute. Pure enum metadata — zero runtime cost, no behavior change yet.

**Affected files:**
- `src/AlgorithmStatus.php` (NEW enum: `Recommended | Legacy | Weak | Broken`)
- `src/AlgorithmMetadata.php` (NEW attribute: `#[AlgorithmMetadata(outputBits, blockBits, status)]`)
- `src/AlgorithmInfo.php` (NEW value object)
- `src/HashAlgorithm.php` (add attributes to cases; add `info()`, `recommendedAlgorithms()`, `brokenAlgorithms()`)

**Tasks:**
1. Add `AlgorithmStatus` enum (`Recommended`, `Legacy`, `Weak`, `Broken`).
2. Add `#[AlgorithmMetadata(int $outputBits, int $blockBits, AlgorithmStatus $status)]` attribute class.
3. Annotate every `HashAlgorithm` case with `#[AlgorithmMetadata(...)]`. Mark collision-broken algos (`MD2`,`MD4`,`MD5`,`SHA1`) as `Broken`/`Weak` **independently of HMAC-safety** (they remain `#[HmacSafe]` — HMAC-safety and collision-strength are orthogonal). Mark modern SHA-2/SHA-3 as `Recommended`.
4. Add `AlgorithmInfo` value object exposing `outputBits()`, `blockBits()`, `status()`, `isBroken()`, `isRecommended()`.
5. Add `HashAlgorithm::info(): AlgorithmInfo` (reflect the attribute, memoized) and convenience `HashAlgorithm::recommendedAlgorithms(): list<self>` / `::brokenAlgorithms(): list<self>`.
6. Do **not** change any hashing behavior in this milestone; the metadata is read-only for now (later milestones consume it).

**Tests to add:** `AlgorithmInfoTest` — assert `SHA256` → 256 bits / `Recommended`; `MD5` → `Broken` while still `isHmacSafe()`; `recommendedAlgorithms()` excludes `MD5`; `brokenAlgorithms()` includes `MD5`/`SHA1`.

**Verification:**
```bash
composer test:unit && composer analyse && composer cs:check && composer architecture
```
**Dependencies:** none.
**Acceptance:** every case annotated; `info()`/`recommendedAlgorithms()`/`brokenAlgorithms()` correct; gates green; no hashing behavior changed.

---

## NF-A2 — Content-addressing sliver `Hash::toUrn()` (H6) — ⬜ TODO

**Goal:** The only §7-allowed sliver of content-addressing: a deterministic URN from a `Hash`. Full CAS storage stays a separate package (out of scope).

**Affected files:**
- `src/HashInterface.php` (add `toUrn(): string`)
- `src/Hash.php` (implement)

**Tasks:**
1. Add `HashInterface::toUrn(): string`.
2. Implement as `urn:hashit:<algorithm>:<hex>` where `<hex>` is `bin2hex($this->value)` (always hex, regardless of `$this->binary`, so binary and non-binary hashes of the same digest URN-identically).
3. Keep it pure string-building; no external encoding library (still within the sliver allowance).

**Tests to add:** `HashTest::testToUrn` — format matches `urn:hashit:sha256:<hex>`; two equal `Hash`es produce identical URN; different algorithm → different URN; binary vs non-binary of same digest → identical URN.

**Verification:** gates (guardrail #6).
**Dependencies:** none.
**Acceptance:** URN stable/deterministic; gates green.

---

## NF-A3 — Framework DI / service config (H5) — ⬜ TODO

**Goal:** Ship wiring that exposes the hashers as services, with **no change to core logic**.

**Affected files:**
- `config/services.php` (NEW — PSR-11 / Symfony definition array)
- `src/Integration/` (optional: `Symfony/HashItBundle.php` if a Bundle is wanted)
- `composer.json` (optional autoload mapping for `config/` or `Integration/`)

**Tasks:**
1. Add `config/services.php` returning a definition array mapping:
   - `ItPack\HashIt\HasherInterface` → `ItPack\HashIt\Adapter\NativeHashIt::create()`
   - `ItPack\HashIt\KeyedHasherInterface` → `NativeHashIt::keyedHasher()`
   - `ItPack\HashIt\HmacHasherInterface` → `NativeHashIt::hmacHasher()`
   (Symfony/PHP-DI both consume such arrays; keep it framework-agnostic.)
2. Optional: a thin `HashItBundle` for Symfony that loads `config/services.php`.
3. Documentation only — no core API change.

**Tests to add:** `tests/Unit/Integration/ServiceConfigTest.php` — require `config/services.php`, assert each factory returns an instance of the declared interface (sanity smoke test; does not boot a container).

**Verification:** gates.
**Dependencies:** none.
**Acceptance:** `config/services.php` present and well-formed; smoke test passes; gates green; no `src/` hashing logic touched.

---

## NF-A4 — Progress & observability port (H1 / E2) — ⬜ TODO

**Goal:** Let streaming/bulk operations report progress without coupling to a logging lib. This port is an **enabler** for NF-C1 (CLI progress) and NF-C2 (async progress).

**Affected files:**
- `src/HashProgressInterface.php` (NEW core port)
- `src/HasherInterface.php` + `src/Hasher.php` (`hashStream`, `hashFiles`, `hashDirectory` gain `?HashProgressInterface $progress = null`)
- `src/KeyedHasherInterface.php` + `src/KeyedHasher.php` (`hmacStream`, `hmacFiles` gain the same)
- `README.md` (note the new optional param)

**Tasks:**
1. Define `HashProgressInterface::onBytes(int $processed, ?int $total): void` (core port, deptrac-clean). Optionally provide `HashProgress::fromCallable()` convenience in the same file.
2. Add `?HashProgressInterface $progress = null` to `HasherInterface::hashStream`/`hashFiles`/`hashDirectory` (default null → BC-safe) and implement: in `hashStream` call `onBytes` after each chunk; in `hashFiles`/`hashDirectory` call after each file (with a running total when known).
3. Mirror the same change on the keyed path (`KeyedHasherInterface`/`KeyedHasher`).
4. No adapter changes; `NativeHashIt` unchanged.

**Tests to add:** `HashProgressTest` — a spy recording `(processed, total)` tuples; assert monotonic non-decreasing `processed`, final `processed === total` for a known-size file/stream, and that passing `null` leaves behavior unchanged (existing suite still green).

**Verification:** gates.
**Dependencies:** none.
**Acceptance:** progress fires on chunk/file boundaries; BC preserved (default null); gates green; port lives in core.

---

## NF-A5 — PSR stream / HTTP-body hashing (H4) — ⬜ TODO

**Goal:** Hash a `Psr\Http\Message\StreamInterface` in chunks without full in-memory load. The *hashing* is in-scope; the SRI **string** formatter is [SCOPE] and deferred to NF-C1.

**Affected files:**
- `src/Adapter/PsrStreamHasher.php` (NEW adapter; depends on `psr/http-message`)
- `composer.json` (`suggest` `psr/http-message`; `require-dev` for the test fake)
- `README.md` (note the adapter + that SRI formatting is a separate formatter)

**Tasks:**
1. Add `psr/http-message` as `suggest` (and `require-dev` so tests can construct a stream).
2. Implement `PsrStreamHasher::hash(StreamInterface $body, string|HashAlgorithm $algorithm = SHA256, int $chunkSize = HasherInterface::DEFAULT_CHUNK_SIZE_BYTES): HashInterface` — read via `StreamInterface::read($chunkSize)` into a `HashBuilder` (`start`/`append`/`digest`); never `detach()` (keeps the stream usable).
3. Add a `PsrStreamHashingInterface` port? Keep it as a concrete adapter only for now; if a port is wanted, add `src/PsrStreamHashingInterface.php` (1 method) — note the choice in the completion note.
4. **[SCOPE] SRI string:** do **not** add `base64`/SRI formatting here. Record that SRI generation belongs with NF-C1 formatters (separated `Format` namespace / sibling).

**Tests to add:** `PsrStreamHasherTest` — hash a `php://memory`/`psr7` stream of known content equals `Hasher::hash()` of that content; chunked reading verified (spy on `read` call count).

**Verification:** gates (guardrail #6). `composer architecture` must still pass (adapter may depend on `psr/http-message` only, not on core internals).
**Dependencies:** none (NF-A4 progress optional — could wire progress later).
**Acceptance:** PSR-stream hashing matches string hashing; gates green; SRI explicitly deferred to NF-C1.

---

## NF-B1 — Key Derivation Functions (V1) — ⬜ TODO

**Goal:** Provide HKDF (built purely on the existing `KeyedHasherInterface`), plus PBKDF2 and Argon2 adapters. This is the headline "complete the toolkit" feature and the strongest reason to stay in HashIt.

**Affected files:**
- `src/KeyDerivationInterface.php` (NEW core port: HKDF/PBKDF2-shaped `derive`)
- `src/KeyDerivation/Hkdf.php` (NEW core service, depends only on `KeyedHasherInterface`)
- `src/Adapter/NativePbkdf2.php` (NEW adapter: `hash_pbkdf2`)
- `src/Argon2Interface.php` + `src/Adapter/NativeArgon2.php` (NEW — separate small port because Argon2's `password_hash`/`password_verify` semantics differ from `derive`)
- `src/Adapter/NativeHashIt.php` (add `kdf()`, `pbkdf2()`, `argon2()`)

**Tasks:**
1. `KeyDerivationInterface::derive(string $ikm, string $salt, int $length, string $info = '', string|HashAlgorithm $algorithm = SHA256): string` (HKDF/PBKDF2 shape).
2. `Hkdf` core service (no adapter): `extract = keyedHasher->hmac($salt ?? str_repeat("\0", blockBits/8), $ikm)`; `expand` loop `T(0)=''`, `T(i)=hmac(T(i-1) . $info . chr(i), $prk)` concatenating until `≥ $length` bytes. Block size from `AlgorithmInfo::blockBits()` (NF-A1); fallback to a tiny known map if NF-A1 not yet landed.
3. `NativePbkdf2` adapter: `hash_pbkdf2($algorithm->value, $ikm, $salt, $iterations, $length, true)`; add an `$iterations` parameter to its method (or a `Pbkdf2Interface` with iterations).
4. `Argon2Interface::hash(string $password, array $options): string` + `verify(string $password, string $hash): bool`; `NativeArgon2` via `password_hash(..., PASSWORD_ARGON2ID, $options)` / `password_verify`. Document that Argon2 is verify-oriented, not `derive`-shaped.
5. Composition root: `NativeHashIt::kdf(): KeyDerivationInterface` (HKDF), `::pbkdf2(): Pbkdf2Interface`, `::argon2(): Argon2Interface`.

**Tests to add:** `KeyDerivationTest` — RFC 5869 HKDF-SHA256 test vector (ikm/salt/info → expected 42-byte output); `NativePbkdf2` parity with raw `hash_pbkdf2`; `NativeArgon2` round-trip `verify(hash(...), password) === true` and a wrong password `=== false`.

**Verification:** gates.
**Dependencies:** NF-A1 (for `blockBits()`; soft — has fallback).
**Acceptance:** RFC 5869 vector passes; PBKDF2/Argon2 parity; gates green; HKDF depends only on `KeyedHasherInterface`.

---

## NF-B2 — AlgorithmSource port (V3 / E3) — ⬜ TODO

**Goal:** Make algorithm *discovery* extensible (custom/KMS-backed algos) without forking the enum. Note: `ContextFactoryInterface::build(string)` already accepts arbitrary string identifiers, so this only adds the *catalogue/discovery* layer.

**Affected files:**
- `src/AlgorithmSourceInterface.php` (NEW core port)
- `src/AlgorithmSource/NativeAlgorithmSource.php` (NEW — enumerates `HashAlgorithm::cases()`)
- `src/AlgorithmSource/CompositeAlgorithmSource.php` (NEW — merges sources, dedups)
- `src/Adapter/NativeHashIt.php` (factory methods accept optional `?AlgorithmSourceInterface`)

**Tasks:**
1. `AlgorithmSourceInterface { algorithms(): list<HashAlgorithm>; supports(string $identifier): bool; resolve(string $identifier): HashAlgorithm; }` (throws `InvalidArgumentException` on unknown in `resolve`).
2. `NativeAlgorithmSource` returns `HashAlgorithm::cases()` and resolves by `->value`/name.
3. `CompositeAlgorithmSource` merges multiple sources, dedup by value, delegates `resolve`/`supports`.
4. `NativeHashIt::create(?AlgorithmSourceInterface $source = null)` (and `keyedHasher`/`hmacHasher`) accept an optional source; default to `NativeAlgorithmSource`. (No behavior change when omitted.)

**Tests to add:** `AlgorithmSourceTest` — Native returns all enum cases; Composite dedups and merges; `resolve` throws `InvalidArgumentException` for unknown; `supports` returns false for unknown.

**Verification:** gates.
**Dependencies:** none (pairs well with NF-A1 for metadata-rich sources).
**Acceptance:** sources work; default behavior unchanged; gates green.

---

## NF-B3 — Canonical HashInput (V6) — ⬜ TODO

**Goal:** Deterministically hash *structured* content (strings/streams/files composed in a fixed order) so the same logical content digests identically regardless of assembly. Built only from allowed input kinds (respects §7 "no exotic inputs").

**Affected files:**
- `src/HashInputInterface.php` (NEW core port)
- `src/HashInput/StringInput.php`, `src/HashInput/StreamInput.php`, `src/HashInput/FileInput.php`, `src/HashInput/CompositeInput.php` (NEW core, `HashInput/` namespace)
- `src/HasherInterface.php` + `src/Hasher.php` (add `hashInput(HashInputInterface, algo): HashInterface`)

**Tasks:**
1. Define `HashInputInterface::writeTo(HashBuilder $builder): void` (framing is internal to each type).
2. Implement `StringInput` (`writeTo` appends the bytes), `StreamInput` (reads in `DEFAULT_CHUNK_SIZE_BYTES` chunks via `append`), `FileInput` (hashes the file path via the existing file path — composes a `StreamInput` over `fopen`).
3. Implement `CompositeInput` holding `list<HashInputInterface>`; `writeTo` applies **length-prefix framing**: for each child, write an 8-byte little-endian length then the child's framed bytes, so ordering and boundaries are canonical. (Define the exact framing constant once, e.g. `HashInputInterface::FRAME_LENGTH_BYTES = 8`.)
4. `Hasher::hashInput(HashInputInterface $input, string|HashAlgorithm $algorithm = SHA256): HashInterface` — `HashBuilder::start(...)` then `input->writeTo($builder)` then `digest()`.
5. Document the framing format in `README.md` (needed for cross-implementation parity).

**Tests to add:** `HashInputTest` — (a) single `StringInput('x')` equals `Hasher::hash('x')`; (b) two equivalent composites built in different code order but same logical sequence digest identically; (c) different order → different digest; (d) `StreamInput` over a file equals `hashFile`.

**Verification:** gates.
**Dependencies:** none.
**Acceptance:** deterministic framing; gates green; uses only string/stream/file inputs.

---

## NF-C1 — Manifest verify (logic) + [SCOPE] formatters (H2) — ⬜ TODO

**Goal:** `HashManifest` value object + `ManifestVerifier` (logic, in core) for generate/verify workflows. **[SCOPE]** the *text* (de)serialization (GNU/BSD/SFV, SRI) is serialization → separated per guardrail #7.

**Affected files:**
- `src/HashManifest.php` (NEW core value object)
- `src/ManifestVerifier.php` (NEW core service)
- `src/Format/ManifestFormatInterface.php`, `src/Format/GnuSha256Format.php`, `src/Format/BsdFormat.php`, `src/Format/SfvFormat.php` (NEW — [SCOPE] serialization, separated namespace)
- `README.md` (note the §7 boundary: logic in core, text formatters in `Format\`)

**Tasks:**
1. **[SCOPE] Scope decision (required first):** choose the formatter home. RECOMMEND a clearly walled `ItPack\HashIt\Format\*` namespace (imports only `Hash`/`HashManifest`, no hashing internals) **or** a separate sibling package `hash-it-format`. Record the decision in the completion note; if the sibling is chosen, only `HashManifest`+`ManifestVerifier` ship in this milestone and formatters become a follow-up package.
2. `HashManifest` value object: `algorithm(): HashAlgorithm`, `entries(): array<string,HashInterface>`, `with(string $path, HashInterface $hash): self` (immutable).
3. `ManifestVerifier::verify(HashManifest $expected, HasherInterface $hasher): VerificationReport` — for each expected `path→Hash`, recompute via `hasher->hashFile(path)`; return a report with `matched` / `mismatched` (path + expected vs actual) / `missing` (path unreadable). Use `equals()` (constant-time) for comparison.
4. **[SCOPE]** `ManifestFormatInterface { serialize(HashManifest): string; parse(string): HashManifest; }` with `GnuSha256Format` (`<hash>  <path>` / `<hash> *<path>`), `BsdFormat` (`SHA256 (<path>) = <hash>`), `SfvFormat` (`<path> <hash>`). Parsing infers algorithm from the manifest (store algorithm in a header line or default `SHA256`); be strict and reject malformed lines.

**Tests to add:** `ManifestVerifierTest` — matched/mismatched/missing cases; `Format/*FormatTest` — serialize→parse round-trip equality for each format; cross-check against a `sha256sum`-style fixture file.

**Verification:** gates (guardrail #6) + `composer architecture` (verify `Format\` does not reach into core hashing internals — if it does, that violates guardrail #7 and must be fixed).
**Dependencies:** NF-A4 (progress optional), NF-A2 (URN optional).
**Acceptance:** verify produces correct report; formatters round-trip; scope decision recorded; gates green.

---

## NF-C2 — Async bridge (H3, sibling AsyncIt) — ⬜ TODO

**Goal:** Hash many files concurrently using the monorepo `AsyncIt` scheduler. Shipped as a **separate package** so HashIt core is untouched (guardrail #8).

**Affected files (in a NEW bridge package, e.g. `hash-it-async`):**
- `composer.json` (`require` `it-pack/hash-it` + `it-pack/async-it`; `suggest` each other)
- `src/ConcurrentFileHasher.php` (maps `hashFile` across an `AsyncIt` runtime)
- `tests/Unit/ConcurrentFileHasherTest.php`

**Tasks:**
1. Scaffold the bridge package (separate repo/package; do not modify HashIt).
2. Implement `ConcurrentFileHasher::hashFiles(iterable<FilepathInterface> $paths, HasherInterface $hasher, AsyncRuntime $runtime): array<string,HashInterface>` — fan out per-file `hashFile` across `AsyncIt` runtimes, gather results preserving the input key order.
3. Reuse HashIt's `HasherInterface::hashFile` exactly (no HashIt changes).
4. Optionally wire NF-A4 progress across the async boundary.

**Tests to add:** same result map as sequential `Hasher::hashFiles`; key order preserved; failure of one file surfaces as the same `RuntimeException` HashIt would throw.

**Verification:** the bridge package's own gates (its `composer test:unit` etc.). **Not** part of HashIt's gates.
**Dependencies:** `AsyncIt` runtime API (sibling), HashIt `hashFile`/`hashFiles`.
**Acceptance:** bridge yields identical map to sequential hashing; HashIt core unchanged.

---

## NF-D1 — Tree / parallel chunk hashing (V4) — ⬜ TODO (gated on spike)

**Goal:** Split large inputs into chunks, hash each, combine with a **stable, specified combiner** into a *tree hash* (a distinct digest from plain `hash()`). Differentiator, but introduces a new concept → requires an approved spec first.

**Affected files (if approved):**
- `src/TreeHashingInterface.php` (NEW core port)
- `src/TreeHasher.php` (NEW core service using `HashBuilder` + `HashInput` framing from NF-B3)
- `README.md` (document the tree-hash format & that it differs from `hash()`)

**Tasks:**
1. **Spike (mandatory before code):** evaluate BLAKE3 chunking vs THEX; choose a combiner (recommended: length-prefixed concatenation of chunk digests, recursively if depth > 1, finalized with the base algorithm). Write a short ADR recording the decision and the canonical chunk size.
2. Implement `TreeHashingInterface::treeHash(HashInputInterface|stream $input, int $chunkSize, string|HashAlgorithm $algorithm): HashInterface` using per-chunk `HashBuilder` + the chosen combiner.
3. Document that the output is a *tree hash*, not interchangeable with `hash()`.

**Tests to add (post-spike):** deterministic; single-chunk input (chunkSize ≥ size) equals plain `hash()`; different canonical chunk sizes documented as yielding different tree hashes (or fix a canonical size); matches an independent reference implementation of the chosen spec.

**Verification:** gates.
**Dependencies:** NF-B3 (framing), NF-A4 (progress optional).
**Acceptance:** ADR approved; implementation matches chosen spec; gates green. **Conditional** — only after the spike is approved; otherwise defer.

---

## NF-D2 — Resumable hashing (V5) — ⬜ TODO (research → likely defer)

**Goal:** Pause/resume a large stream hash across requests. Native `\HashContext` is **not serializable**, so this is likely infeasible with the native-backed design.

**Affected files:** none until a decision is made.

**Tasks:**
1. **Spike:** confirm `hash_copy` only clones in-memory (no serialize); evaluate a self-implemented incremental engine (only feasible for a from-scratch algorithm, not the PHP ext). Document findings + effort.
2. **Decision:** implement (only if a custom engine is separately justified) or **defer** with written rationale. If deferred, record why in `docs/` and close the milestone as "deferred".

**Verification:** n/a (research).
**Dependencies:** none.
**Acceptance:** documented, reviewed decision. **Likely deferred** — do not promise a runtime feature without the spike.

---

## 2. Definition of Done (whole next-level effort)

- All non-deferred milestones (NF-A1…NF-C2, NF-D1 if approved) implemented with green gates:
  `composer test:unit` · `composer analyse` · `composer cs:check` · `composer architecture`.
- Each milestone has a completion note in this file (mirroring `REFACTORING_PLAN.md`).
- `[SCOPE]` items (NF-A5 SRI, NF-C1 formatters) resolved per guardrail #7 with a recorded scope decision and no serialization logic inside the hashing core.
- Bridges (NF-C2) live in separate packages and do not alter HashIt's gates.
- `docs/ADDITIONAL_FEATURES_PLAN.md` findings (V1–V6, H1–H6) each map to a milestone above; any deferred item (NF-D2) has a written rationale.

## 3. Open questions / research gates (carry from the plan)

1. **NF-D2 resumability** — confirm infeasibility with native ext; only pursue if a self-implemented engine is justified.
2. **NF-D1 tree hash** — pick a stable, interoperable combiner (BLAKE3 chunking / THEX) before coding.
3. **Cross-package policy** — confirm HashIt may `suggest` `AsyncIt`/`DeriveIt`; keep bridges optional to avoid hard coupling.
4. **NF-C1 serialization home** — finalize whether formatters live in `ItPack\HashIt\Format\*` or a sibling `hash-it-format` package before building them.
