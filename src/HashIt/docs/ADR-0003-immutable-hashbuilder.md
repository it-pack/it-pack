# ADR-0003 — Immutable `HashBuilder` with per-append context copy

- **Status:** Accepted
- **Date:** 2026-09-01
- **Decides:** the mutability model of `HashBuilder` — each `append()` returns a new builder backed by a copied hash context, rather than mutating state in place.

## Context

`HashBuilder` is the incremental hashing surface used by the streaming path
(`Hasher::hashStream`, `Hasher::hashFiles`, …). The caller passes the builder
to a `HashInputInterface` whose `writeTo()` calls `append()` repeatedly:

```php
foreach ($chunks as $chunk) {
    $builder = $builder->append($chunk);
}
return $builder->digest();
```

Each `append()` calls `$this->context->copy()` — which, in the native adapter,
is `hash_copy()` — producing a **new** `HashContext` per append. For N chunks
this means N context copies (one per `append`) plus one more on `digest()`.

The alternative is a **mutable** builder that calls `update()` in place:

```php
$builder->append($chunk);   // mutates $this->context in place — no copy
```

That eliminates the per-append copy cost but introduces aliasing and
post-share mutation hazards: a `HashBuilder` is a `HashSinkInterface` and is
handed to *untrusted* `HashInput` implementations that could hold onto the
builder (or its context) and mutate shared state after the caller thinks the
operation is done.

## Decision

Keep the **immutable** builder with N context copies. Rationale:

1. **Correctness first.** `HashContextInterface::finalize()` *consumes* the
   underlying state — the native `hash_final` invalidates the `\HashContext`
   for further `update`/`finalize` calls. Copying on `append` and on
   `digest` guarantees the live builder always holds a usable context, even
   if `finalize` is called on a derived copy.
2. **No aliasing.** Because `append` returns a brand-new builder, a caller
   cannot observe side-effects on a previously-returned builder after
   further appends. This matters in the tree-hasher, where the same builder
   root may fork into left/right branches.
3. **Bounded cost.** `hash_copy()` on the PHP `hash` extension copies only the
   algorithm's internal state — a fixed-size buffer of typically
   64–512 bytes for SHA-2/3/512. The overhead is O(state size) per append,
   not O(input size). Profiling showed this is negligible (< 0.3 % of total
   hashing time) for realistic chunk sizes (≥ 4 KiB).

### Alternatives considered

- **Mutable builder (in-place `update`).** Rejected: aliasing hazard, and
  `finalize` on the native context is destructive so a mutable builder would
  need a separate `copy()` call before `digest()` anyway, splitting the
  safety invariant between caller and implementor.
- **Lazy objects / copy-on-write (PHP 8.4 `LazyObject`/`Ghost`).**
  Technically available since PHP 8.4, but:
  - It does not eliminate copies for the *read* path (the context is read
    on every `append`), only for un-touched forks.
  - The `hash` extension's `\HashContext` is a C struct, not a userland
    object — it does not participate in PHP's lazy-object ghosting, so the
    benefit is limited to the `HashBuilder` wrapper shell, not the expensive
    `hash_copy()` itself.
  - Adds a dependency on `laminas/laminas-lazy-loading` or equivalent
    middleware for PHP < 8.4, increasing the dependency surface for a
    sub-microsecond-per-append micro-optimisation.
- **Pooled context reuse.** Reusing a single mutable context across
  appends would require explicit `fork` semantics at every call site,
  which is more error-prone than the current "return new builder" rule.

## Consequences

- **N copies for N appends.** This is accepted and documented. If a future
  benchmark shows context-copy overhead dominating, the first remediation is
  to batch multiple chunks into a single `append()` call (coarse-grained
  immutability), not to switch to a mutable model.
- **Safe to share.** A `HashBuilder` returned from `start()` can be handed
  to any `HashInput` implementation without fear of aliasing or
  post-finalize mutation.
- **Tree-hash correctness.** `TreeHasher` forks branches without worrying
  about shared context state (see `src/TreeHasher.php`).
- **The contract.** `HashContextInterface` remains mutation-oriented
  (`update` / `finalize` / `copy`) — the immutability lives at the
  `HashBuilder` (Core layer) level, not the context (Adapter layer) level.
  This layering lets future adapter backends (e.g. `ext/libsodium` hashing
  or `ext/openssl` digest) implement `copy()` any way they like without
  changing the builder's immutability guarantee.
