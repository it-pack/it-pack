# HashIt — Additional Features Plan (next-level brainstorming)

> Companion to `docs/REVIEW.md`, `docs/REFACTORING_PLAN.md` (M0–M8 ✅) and the
> archived roadmap `docs/archive/ROADMAP_1.md`. Purpose: brainstorm and reason
> about features that take HashIt beyond the M0–M8 refactor — split into
> **vertical** (deeper in the hashing domain) and **horizontal** (broader reach
> while staying a single-purpose hashing library).
>
> Status: proposal / not yet scheduled. Nothing here is committed.

---

## 1. Baseline (post-M8) — what we already have

- **Ports (Core):** `HashContextInterface`, `ContextFactoryInterface`,
  `KeyedContextFactoryInterface`, `FilepathInterface`, `DirectoryListingInterface`,
  `HasherInterface`, `KeyedHasherInterface`, `HmacHasherInterface`.
- **Core services:** `Hasher` (implements `HasherInterface`), `KeyedHasher`
  (implements `KeyedHasherInterface`), `HashBuilder` (immutable, `start`/`fromContext`/`append`/`digest`).
- **Value objects:** `Hash` (`value`/`algorithm`/`binary`/`equals()`), `HashAlgorithm`
  enum (with `#[HmacSafe]` + `hmacSafeAlgorithms()`/`isHmacSafe()`/`default()`).
- **Adapters (CompositionRoot `NativeHashIt`):** `NativeHashContext`,
  `NativeContextFactory`, `NativeFilepathFileinfo`, `NativeDirectoryListing`,
  `NativeHmacHasher`, `NativeKeyedContextFactory`.
- **Surfaces:** one-shot / binary / file / stream / bulk (`hashFiles`+`hashDirectory`) /
  timing-safe verify — for both plain and keyed (HMAC) paths. Default `SHA256`,
  `DEFAULT_CHUNK_SIZE_BYTES = 65536`, all classes `final readonly`.

## 2. Constraints we must honour (from `docs/archive/ROADMAP_1.md`)

- **§5 Feature principles:** stay *within* hashing (strings, files, streams,
  keyed/HMAC, verification) — **no encoding/transport/conversion**; reuse existing
  ports and the `Hash` value object; add *minimal* public surface (prefer DX sugar
  and real capability gaps over new concepts).
- **§7 Out of scope (still excluded):** arbitrary encoding / base64 / serialization
  helpers unrelated to hashing; "hash every algorithm at once"; full URN /
  content-addressing stores (only a tiny `Hash::toUrn()` sliver is *borderline-allowed*);
  exotic non-string/non-stream inputs.

→ Any suggestion that touches serialization/encoding is flagged **[SCOPE]** and
recommended to live in a clearly separated formatter or a sibling package, keeping
HashIt core logic-only.

## 3. How these were derived

- **Vertical** = close gaps *inside* the hashing domain the refactor exposed but
  deliberately left open: cryptographic keyed work beyond HMAC (KDF), machine-readable
  algorithm strength, pluggable algorithms, deterministic structured input, and the
  two research-grade depths (tree/parallel, resumable).
- **Horizontal** = widen *who can use* the existing primitives without bloating the
  core: progress/observability, PSR stream/HTTP bodies, framework DI, async orchestration
  (sibling `AsyncIt`), and manifest/SRI verification workflows.
- Each item is scored on **Alignment** (to §5), **Effort**, **Risk**, **Impact**, and
  a suggested **Phase**.

---

## 4. Vertical features (deeper in the hashing domain)

### V1 — Key Derivation Functions (KDF)  ·  *high impact, on-theme*
- **Problem:** HMAC is a MAC, not a KDF. Real apps need key stretching / extraction
  (password hashing, deriving subkeys from a master secret). Today callers must reach
  for raw `hash_hmac`/`hash_pbkdf2`/`password_hash` outside HashIt.
- **Proposal:** a `KeyDerivationInterface` built *on top of the existing keyed path*.
  - **HKDF** (RFC 5869) implemented as a core service depending only on
    `KeyedHasherInterface` (extract = `hmac(salt ?? \0-block, ikm)`; expand = iterative
    `hmac`). No new adapter needed.
  - **PBKDF2** via `hash_pbkdf2` → `NativePbkdf2` adapter.
  - **Argon2** via `password_hash`/`password_verify` → `NativeArgon2` adapter
    (clearly the "slow" KDF; separate from stream hashing).
- **API sketch:**
  ```php
  interface KeyDerivationInterface
  {
      // HKDF-Expand: derive $length bytes from $ikm, salted with $salt, labelled by $info
      public function derive(
          string $inputKeyingMaterial,
          string $salt,
          int $length,
          string $info = '',
          string|HashAlgorithm $algorithm = HashAlgorithm::SHA256,
      ): string;
  }
  ```
  Composition root: `NativeHashIt::kdf(): KeyDerivationInterface`.
- **Fit:** squarely within hashing/crypto; reuses `KeyedHasher`; no port churn.
- **Effort:** medium (HKDF core-only; PBKDF2/Argon2 are thin adapters).
- **Risk:** low. **Impact:** high (kills the #1 reason to leave HashIt).

### V2 — Algorithm strength metadata (resurrect deferred `#[Insecure]`)  ·  *quick win*
- **Problem:** `HashAlgorithm` knows only `isHmacSafe()`. The M8 `#[Insecure]` attribute
  was deferred; there is no machine-readable notion of "broken/legacy/recommended".
- **Proposal:** replace the single marker with a richer
  `#[AlgorithmMetadata(outputBits, blockBits, status)]` where
  `AlgorithmStatus: Recommended|Legacy|Broken|Insecure`, plus an `AlgorithmInfo`
  value object and `HashAlgorithm::info(): AlgorithmInfo` (`isBroken()`, `isRecommended()`,
  `outputBits()`). `KeyedHasher` may *advise* (or optionally reject) `Broken` for HMAC.
- **Fit:** directly closes the deferred M8 item; powers safer defaults, advisory
  logging, and downstream URN/manifest annotations. Pure enum metadata — zero runtime cost.
- **Effort:** low–medium. **Risk:** low. **Impact:** medium–high (safety + unblocks others).

### V3 — Extensible algorithm catalogue (`AlgorithmSource` port)  ·  *depth / openness*
- **Problem:** the enum is the only source of truth. Custom / hardware-backed / KMS-backed
  or not-yet-in-PHP hashes (e.g. BLAKE3 ext, a pure-PHP fallback) cannot be added without
  forking the enum. (The ROADMAP_1 review already noted a "KMS-backed or fake
  implementation needs only the five ports".)
- **Key insight:** `ContextFactoryInterface::build(string $algorithm)` already takes a
  *string*, so a custom `ContextFactoryInterface`+`KeyedContextFactoryInterface` can already
  support non-enum identifiers. The **missing piece is discovery** (`hmacSafeAlgorithms()`
  is enum-only).
- **Proposal:** add `AlgorithmSourceInterface { algorithms(): list<HashAlgorithm>; supports(string): bool; resolve(string): HashAlgorithm; }`
  with `NativeAlgorithmSource` (from the enum) and `CompositeAlgorithmSource`
  (merges native + registered). `NativeHashIt` accepts an optional source in its factory
  methods. Custom algos stay string-based; the source only governs *discovery/metadata*.
- **Fit:** reuses ports; deepens without widening the public surface much.
- **Effort:** medium. **Risk:** medium (must keep `HashAlgorithm` enum as the canonical
  native identifier; custom algos are strings, not enum cases). **Impact:** medium.

### V4 — Tree / parallel chunk hashing  ·  *research-grade differentiator*
- **Problem:** very large inputs hashed by one core on one thread. No way to split work.
- **Proposal:** a `TreeHasher`/`ChunkedHashBuilder` that splits input into chunks,
  hashes each (optionally in parallel via fibers/runtime), and combines with a
  **defined, stable combiner** (e.g. length-prefixed concatenation of chunk digests,
  THEX-style) into a *tree hash* — a distinct digest from the plain one.
- **Fit:** within hashing, but introduces a *new concept* (tree hash mode) and a
  *format spec*. **Not** a drop-in replacement for `hash()`.
- **Effort:** high. **Risk:** high (must spec a stable, interoperable combiner;
  PHP parallelism is limited — `parallel` ext or fibers, not threads).
- **Impact:** high *if* a spec is chosen; otherwise niche. **Recommend:** research spike
  first; adopt an existing standard (BLAKE3 chunking / THEX) rather than inventing one.

### V5 — Resumable / persistable hashing  ·  *likely defer*
- **Problem:** pause a huge stream hash and resume later (e.g. across requests).
- **Caveat:** PHP's `\HashContext` is **not serializable**; `hash_copy` only clones
  in-memory. A resumable scheme is only feasible with a *self-implemented* incremental
  engine (not the native ext).
- **Recommend:** **research spike, likely out of scope** for the native-backed design.
  Note honestly so we don't over-promise.

### V6 — Canonical / structured-input hashing (`HashInput`)  ·  *real gap closer*
- **Problem:** hashing a *structured* payload (several strings/streams/files in a
  deterministic order) is done ad-hoc by callers; ordering/framing bugs change the digest.
- **Proposal:** a `HashInputInterface` composed of allowed inputs (`StringInput`,
  `StreamInput`, `FileInput`, `CompositeInput`) with **length-prefix framing** so the
  same logical content hashes identically regardless of how it was assembled.
  `HasherInterface::hashInput(HashInputInterface, …): HashInterface`.
- **Fit:** built only from strings/streams/files → respects §7 "no exotic inputs";
  closes a genuine capability gap (signing/fingerprinting structured data).
- **Effort:** medium. **Risk:** low–medium (must specify framing precisely for stability).
  **Impact:** medium–high.

---

## 5. Horizontal features (broader reach, core stays single-purpose)

### H1 — Progress & observability port  ·  *enabler, quick win*
- **Proposal:** `HashProgressInterface { onBytes(int $processed, ?int $total): void; }`
  (or a `?callable` on the stream/bulk methods). Emitted by `hashStream`/`hashFiles`/
  `hashDirectory`. Pairs with sibling `DeriveIt` telemetry conventions.
- **Fit:** observation *of hashing* — within scope; a new Core port (deptrac-clean).
- **Effort:** low. **Risk:** low. **Impact:** medium (unblocks H2/H3 UX + monitoring).

### H2 — Manifest generation & verification workflows  ·  *high value, [SCOPE]*
- **Proposal (logic, in-scope):** `HashManifest` value object (`algorithm` + `path→Hash`
  map) + `ManifestVerifier::verify(HashManifest $expected, HasherInterface $hasher): VerificationReport`.
- **[SCOPE] serialization:** turning a manifest into / from text (`sha256sum` lines,
  BSD style, `sfv`) is *serialization* (§7). **Recommendation:** keep the *logic* in
  HashIt; put *parsers/serializers* in a clearly separated `ManifestFormatInterface`
  (e.g. `GnuSha256Format`) — ideally a sibling or an opt-in formatter package, so the
  core stays encoding-free. Needs an explicit scope decision before building.
- **Effort:** medium (logic) / low–medium (formatters). **Risk:** low (logic), medium
  (format interoperability). **Impact:** high (the most-requested dev workflow).

### H3 — Async / concurrent hashing (sibling `AsyncIt` bridge)  ·  *reach*
- **Proposal:** hash many files concurrently using the monorepo `AsyncIt` scheduler
  (fibers/runtimes), reusing per-file `hashFile` logic; aggregate into the same
  `path→Hash` map. Ship as a **bridge package** (`HashItAsync`) or an `AsyncIt` adapter,
  *not* inside HashIt core, to avoid coupling.
- **Fit:** hashing stays in HashIt; concurrency is orchestration → belongs to the bridge.
- **Effort:** medium (depends on `AsyncIt` API). **Risk:** medium (cross-package dep;
  keep it an optional `suggest` to avoid hard coupling). **Impact:** medium–high.

### H4 — PSR stream / HTTP body hashing  ·  *quick win*
- **Proposal:** `PsrStreamHasher` adapter hashing a `Psr\Http\Message\StreamInterface`
  by reading chunks via `read()` into a `HashBuilder` (no `detach()`/full load). Natural
  fit for hashing request/response bodies.
- **[SCOPE] SRI:** generating `<script integrity="sha384-…">` strings needs base64 —
  §7 excludes encoding. **Recommend:** keep `PsrStreamHasher` (in-scope) in HashIt;
  treat the SRI *string* as a thin formatter living with H2's formatters (flagged).
- **Effort:** low. **Risk:** low. **Impact:** medium.

### H5 — Framework DI / service-provider wiring  ·  *quick win, zero core change*
- **Proposal:** ship `config/` with PSR-11 / Symfony service definitions (and optionally
  a Laravel provider) exposing `HasherInterface`, `KeyedHasherInterface`,
  `HmacHasherInterface`, `HmacHasherInterface` as services bound to `NativeHashIt`.
  No API change — pure wiring.
- **Fit:** perfect (single-purpose preserved). **Effort:** low. **Risk:** low. **Impact:** medium.

### H6 — Content-addressing sliver (`Hash::toUrn()`)  ·  *borderline-allowed quick win*
- **Proposal (per §7's explicit allowance):** `Hash::toUrn(): string` →
  `urn:hashit:<algorithm>:<hex>` (or a `hashid()`-style id). **Only** this sliver;
  full CAS storage stays a separate package / out of scope.
- **Fit:** explicitly the §7 borderline case. **Effort:** trivial. **Risk:** low.
  **Impact:** low–medium (enables content ids without a storage engine).

---

## 6. Cross-cutting enablers (build these first; they unblock several items)

| Enabler | Unblocks | Phase |
|---|---|---|
| **E1 `AlgorithmInfo`/metadata (V2)** | safer defaults, advisory logging, URN annotations, manifest metadata | A |
| **E2 `HashProgressInterface` (H1)** | CLI progress (H2), async progress (H3), monitoring | A |
| **E3 `AlgorithmSourceInterface` (V3)** | custom/KMS-backed algos, discovery beyond the enum | B |

---

## 7. Prioritization matrix

| ID | Feature | Type | Align §5 | Effort | Risk | Impact | Phase |
|---|---|---|---|---|---|---|---|
| V2 | Algorithm strength metadata | Vert | ✅ | Low | Low | Med–High | **A** |
| H6 | `Hash::toUrn()` sliver | Horiz | ✅(borderline) | Trivial | Low | Low–Med | **A** |
| H5 | Framework DI config | Horiz | ✅ | Low | Low | Med | **A** |
| H1/E2 | Progress & observability port | Horiz | ✅ | Low | Low | Med | **A** |
| H4 | PSR stream/body hashing | Horiz | ✅ | Low | Low | Med | **A** |
| V1 | KDF (HKDF/PBKDF2/Argon2) | Vert | ✅ | Med | Low | High | **B** |
| V3/E3 | `AlgorithmSource` port | Vert | ✅ | Med | Med | Med | **B** |
| V6 | Canonical `HashInput` | Vert | ✅ | Med | Low–Med | Med–High | **B** |
| H2 | Manifest verify (logic) + [SCOPE] formatters | Horiz | ⚠️(SCOPE) | Med | Med | High | **C** |
| H3 | Async bridge (sibling `AsyncIt`) | Horiz | ✅(bridge) | Med | Med | Med–High | **C** |
| V4 | Tree / parallel chunk hashing | Vert | ⚠️(new concept) | High | High | High* | **D (spike)** |
| V5 | Resumable hashing | Vert | ⚠️ | High | High | Low* | **D (defer)** |

Legend: ✅ fully aligned · ⚠️ needs a scope/design decision · *conditional on spec choice.

---

## 8. Proposed phased roadmap ("next level")

- **Phase A — Polish & quick wins (low risk, high alignment):** V2, H6, H5, H1/E2, H4.
  Establishes metadata + observability + framework readiness + PSR reach. Green gates
  throughout; no scope expansion needed.
- **Phase B — Vertical depth (crypto & extensibility):** V1 KDF (HKDF built on
  `KeyedHasher`, then PBKDF2/Argon2 adapters), V3 `AlgorithmSource`, V6 `HashInput`.
  This is where HashIt becomes a *complete* hashing toolkit.
- **Phase C — Horizontal reach (ecosystem):** H2 manifest verify + [SCOPE] formatters
  (requires the §7 scope decision), H3 async bridge to `AsyncIt`.
- **Phase D — Research / speculative:** V4 tree hashing (adopt an existing spec), V5
  resumable (likely defer unless a custom engine is justified).

## 9. Scope-boundary recommendation (keep HashIt honest)

**Keep in HashIt core (logic-only, encoding-free):**
all hashing primitives, V1–V3/V6, H1/H4/H5/H6, and the *logic* of H2 (manifest value
object + verification).

**Push out (sibling package or clearly-separated formatter) — [SCOPE]:**
text (de)serialization of manifests, SRI/base64 strings, full content-addressable
storage. These are serialization/transport concerns excluded by §7; isolating them
protects the single-purpose boundary while still delivering the workflows.

**Do NOT add (per §7):** base64/encoding helper libs, "hash all algos at once",
exotic non-string/non-stream inputs.

## 10. Open questions / research spikes

1. **V5 resumability** — confirm infeasibility with the native ext; only pursue if a
   self-implemented engine is separately justified.
2. **V4 tree hash** — pick a stable, interoperable combiner (BLAKE3 chunking / THEX)
   rather than inventing one; decide if it is a new `TreeHashingInterface` or an option
   on `HasherInterface`.
3. **Cross-package policy** — can HashIt optionally `suggest` `AsyncIt`/`DeriveIt`?
   Keep bridges as separate packages with optional deps to avoid hard coupling.
4. **H2 serialization home** — decide whether manifest/SRI formatters live in a new
   `HashIt\Format` namespace (clearly separated) or a sibling package, before building.

## 11. References

- `docs/REVIEW.md` (F-1…F-9 closure log; §2/§3/§4 catalogues)
- `docs/REFACTORING_PLAN.md` (M0–M8 ✅; M8 deferred `#[Insecure]` note)
- `docs/archive/ROADMAP_1.md` §5 Feature principles, §7 Out of scope
- Current ports: `src/HasherInterface.php`, `src/KeyedHasherInterface.php`,
  `src/HashBuilder.php`, `src/HashAlgorithm.php`, `src/Adapter/NativeHashIt.php`
- Sibling monorepo packages for bridges: `AsyncIt` (concurrency), `DeriveIt`
  (telemetry), `CommandIt` (CLI conventions), `ConfigureIt` (DI/wiring)
