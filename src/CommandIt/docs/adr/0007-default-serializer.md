# ADR 0007 — Safe-by-default result serialization

- **Status:** Accepted
- **Date:** 2026-08-29
- **Milestone:** M7 (REVIEW decision D3, recommend wire)

## Context

`CommandItFactory::create()` passed `?SerializerInterface $serializer = null` straight
through to the executor, which treated `null` as "store the result payload verbatim."
REVIEW decision D3 recommends the *wire* option: payloads should be normalized to a
recursively-primitive, deterministically-serializable structure by default, because the
storage/observation ports are explicit-serialization boundaries (AGENTS.md §Publication
and §Security). A `null` default meant the safe behavior had to be opted **into**, and
the most common assembly path silently persisted arbitrary PHP objects.

## Decision

- `CommandItFactory::create()` now wires a `SafeSerializer` by default:

  ```php
  $serializer ??= new SafeSerializer();
  ```

  An explicit `SerializerInterface` still overrides the default; passing `null` directly
  to the executor/SDK remains allowed (verbatim storage) but is no longer the factory
  default.

- **Escape hatch.** Add `Adapter\Common\PassthroughSerializer` (`normalize()` returns the
  payload unchanged) for consumers who want the old verbatim behavior through the factory.
  The choice is explicit at the composition root rather than implicit.

## Consequences

- Factory-assembled pipelines store primitive-only snapshots by default; objects with a
  `toArray()` method (or `Stringable`) are reduced before persistence, and resources/
  closures throw `SerializationException` rather than leaking.
- A behavior change for factory consumers: previously-arbitrary object payloads are now
  normalized. Covered by
  `CommandExecutorTest::testDefaultSerializerNormalizesObjectPayloadBeforeStorage`.
- Callers that genuinely need verbatim storage opt in with `PassthroughSerializer` and
  accept the serialization-safety responsibility (documented in the factory and USER_GUIDE).
- The executor's best-effort `normalizePayload()` unchanged: a serializer failure still
  keeps the original payload, so a normalization error never replaces the business result.

## Alternatives considered

- **Keep `null` = verbatim as the default (D3 option "store").** Rejected: it makes the
  unsafe path the default at exactly the boundary where REVIEW requires bounded, classified,
  serialization-safe data.
- **Make `SafeSerializer` the only option (remove the `?SerializerInterface` escape).**
  Rejected: some consumers (e.g. in-process debugging, or a port that already serializes)
  legitimately want verbatim storage; `PassthroughSerializer` keeps that available without
  forcing a `null` sentinel.
