# ADR-0002 — Resumable (pausable) hashing for NF-D2

- **Status:** Accepted (this resolves the "research → likely defer" precondition of NF-D2)
- **Date:** 2026-08-31
- **Decides:** whether HashIt can offer pause/resume of an in-progress stream hash, and how.

## Context

NF-D2 ("Resumable hashing") wants to pause a large stream hash and resume it later
(across requests / processes). The roadmap pre-assumed this was **infeasible**
because "native `\HashContext` is not serializable". The spike (run on PHP 8.4)
shows that assumption is **wrong for plain hashes**:

- `serialize()` of a plain `\HashContext` (from `hash_init($algo)`) **succeeds** and
  does **not** consume the live context — `update()` can continue after a snapshot.
- The serialized blob **resumes in a separate PHP process**: unserialize → `update()`
  → `finalize()` yields the exact digest of the full concatenated input.
- `hash_copy()` only clones in-memory (no persistence) — so resume across processes
  requires the serialization path, which plain contexts support.

**Caveat (hard boundary):** a **keyed / HMAC** `\HashContext` (`hash_init($algo,
HASH_HMAC, $key)`) throws on `serialize()`:

> Exception: HashContext with HASH_HMAC option cannot be serialized

So resumable hashing is feasible for **plain** algorithms only; HMAC state cannot
be checkpointed (it would also embed the secret key, which is undesirable to
persist).

## Decision

**Implement** resumable hashing for plain algorithms (do **not** defer). Design:

- Extend `HashContextInterface` with `checkpoint(): string` — a portable snapshot
  of the live state that does **not** consume it (caller may keep updating). The
  native adapter serializes the `\HashContext`; it throws a clear `RuntimeException`
  for HMAC contexts (not serializable).
- Extend `ContextFactoryInterface` with `restore(string $checkpoint):
  HashContextInterface` — rebuilds a context from a checkpoint, using
  `unserialize(..., ['allowed_classes' => [\HashContext::class]])` (no object
  injection).
- New core port `ResumableHashingInterface` (`start` / `resume`) and
  `ResumableHashInterface` (`update` / `checkpoint` / `finalize`), with a core
  service `ResumableHasher` that wraps the context port. The checkpoint is an
  **envelope** `serialize(['algorithm' => string, 'binary' => bool, 'state' =>
  string])` so resume is self-describing (binary flag + algorithm travel with the
  state); the inner `state` is unserialized only with `allowed_classes` restricted.
- HMAC resumability is intentionally **not** offered (native throws; key would leak).
  `NativeHashIt::resumable()` is built on the plain `ContextFactoryInterface` only.

## Consequences

- Pause/resume of a plain stream hash works across processes — the headline NF-D2
  capability, with no new runtime dependency (pure `hash` extension + serialization).
- Checkpoints for plain hashing contain only internal hash state (no secret) — safe
  to persist. HMAC checkpoints are impossible by construction (native guard).
- `HashContextInterface` / `ContextFactoryInterface` gain one method each (still
  tiny ports); only `NativeHashContext` / `NativeContextFactory` implement them.
- deptrac-clean: the new core types depend only on core ports and `Hash`.
