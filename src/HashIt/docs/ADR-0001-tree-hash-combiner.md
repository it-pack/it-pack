# ADR-0001 — Tree (chunked) hash combiner for NF-D1

- **Status:** Accepted (this resolves the "gated on spike" precondition of NF-D1)
- **Date:** 2026-08-31
- **Decides:** the chunking strategy and the chunk-digest combiner for `TreeHashingInterface`.

## Context

NF-D1 ("Tree / parallel chunk hashing") needs a digest for large inputs that is
computed by splitting the input into chunks, hashing each chunk independently,
and then combining the chunk digests into a *single* root digest — a *tree hash*.
The root digest must be **distinct** from the plain `hash()` of the input (so the
two are never confused) yet **stable and specified**, so independent
implementations agree.

Two combiners were on the table:

1. **BLAKE3 chunking** — BLAKE3 defines a fixed 1 KiB chunk size and a SIMD
   chunk compression function, then a Merkle tree over those chunks. It is
   attractive but couples the scheme to the BLAKE3 *compression function*, which
   is **not** exposed by any algorithm the PHP `hash` extension offers. Shipping
   it would mean vendoring/hand-rolling BLAKE3 — out of scope for HashIt and
   algorithm-specific.
2. **THEX-style length-prefixed concatenation (RFC 6962 / Tree Hash Exchange)**
   — combine two node digests as `H(left || right)`, promoting an odd trailing
   leaf unchanged. Algorithm-agnostic: it works over *any* `HashAlgorithm` HashIt
   already supports (SHA-2, SHA-3, …).

We want a combiner that works generically over every `HashAlgorithm` HashIt
supports, not one tied to a single algorithm.

## Decision

- **Chunking:** fixed-size, caller-supplied `chunkSize` (default
  `HasherInterface::DEFAULT_CHUNK_SIZE_BYTES` = 64 KiB). No BLAKE3-style SIMD
  chunking. The tree hash is a pure function of `(input bytes, chunkSize,
  algorithm)`; callers MUST record `chunkSize` alongside the digest.
- **Combiner:** recursive binary reduction.
  - Each **leaf** = `H(chunk)` — the *raw binary* digest (length `outputBits/8`).
  - Each **internal node** combines two children as
    `H( frame(left) || frame(right) )`, where
    `frame(x) = LE64(length(x)) || x` and `x` is a raw binary digest.
  - The **trailing odd node** at any level is promoted unchanged (THEX-style).
  - Recurse until a single root remains; the root raw digest is surfaced as the
    (hex) tree hash. This reuses `HashInputInterface::FRAME_LENGTH_BYTES = 8` for
    the length prefix, keeping one framing constant of truth.
- **Single-chunk shortcut:** when the whole input fits in one chunk the recursion
  returns that single leaf, surfaced as `hash()` — so `treeHash` is a strict
  generalization of `hash()` (NF-D1 acceptance: single-chunk input ⇒ equals
  `hash()`).
- **Canonical chunk size:** intentionally **not** fixed to one value. Different
  `chunkSize` ⇒ different tree hash (documented, not a defect).

## Consequences

- Output length == base algorithm digest length (every node is a full hash
  output), so a tree hash composes with `Hash` / `HashInterface::equals`.
- Deterministic and canonical given `(input, chunkSize, algorithm)`.
- Distinct from `hash()` for multi-chunk inputs, by design.
- No runtime dependency on BLAKE3 or any external library — implemented only with
  `HashBuilder` and `HashInput` framing (NF-B3). Core stays deptrac-clean.
- **Interop note:** the on-wire root is *not* wire-compatible with THEX or
  BLAKE3. It is defined as HashIt's own stable scheme; if cross-implementation
  interop with a named external spec is later required, that belongs in a separate
  interop package that wraps that named spec, not in this core API.
