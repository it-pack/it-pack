# M12.1 Data-Protection Threat Model

This document records the data-classification and exfiltration review performed for
Milestone M12. It is the security companion to `docs/ROADMAP.md` (Milestone M12) and to
`docs/SECURITY.md` (the security policy).

## Scope

RunIt is a library. The data-exfiltration surface is every place where caller-supplied or
runtime-derived data leaves the process through an adapter: artifact publication, snapshot
serialization, context propagation, and telemetry/event mapping.

## Classified assets

| Asset | Classification | Default handling |
|-------|----------------|------------------|
| Context values marked `secret` | Secret | Never exported; redacted at the boundary |
| Context values marked `restricted` | Restricted | Redacted before external publication |
| Observation/occurrence messages | Potentially sensitive | Redacted by default (`redactThrowableMessages`) |
| Occurrence `data` | Potentially sensitive | Redacted when `redactOccurrenceData` is set |
| Snapshot `payload` | Potentially sensitive | Redacted when `redactSnapshotPayloads` is set |
| Environment variables | Secret by default | Never captured automatically |
| Stack traces | Restricted | Safely normalized, bounded, not exported by default |
| Arbitrary PHP objects | Non-serializable | Rejected by the serializer |

## Artifact field review

Every artifact field was reviewed:

- **Execution / Activity**: identifiers and timing only. No caller payloads. Bounded by
  `ArtifactSequence` and by the activity-depth limit (`SecurityLimits::maxActivityDepth`).
- **Measurement**: scalar value, unit, bounded attributes. No object serialization.
- **Observation**: message (string), severity, bounded attributes, normalized throwable.
- **Occurrence**: typed, versioned type; source; bounded `extensionAttributes`; recursively
  primitive `data`. Objects and resources are rejected by `ContextValueValidator`.
- **Snapshot**: deterministic, bounded payload; content hash; no PHP object survives the
  `JsonSnapshotSerializer` round trip.

## Adapter mapping review

- **OpenTelemetry bridge** maps executions/activities to spans; occurrences to logs/events.
  Only normalized projections are handed over; secret context is redacted first.
- **CloudEvents bridge** maps occurrences to events, preserving `extensionAttributes` and
  rejecting unknown object payloads.
- **PSR-3 bridge** maps observations to log messages with stable templates.
- **PSR-14 bridge** maps occurrences to events without mutating the immutable source.
- **Swoole / OpenSwoole** adapter handles request-local and coroutine-local context, clearing
  all state in `finally`.

## Controls implemented

- `SecurityLimits` (in `src/Application/Security`) centralizes every bound.
- `AttributeValidator` enforces attribute count, key length, scalar value length, and nesting
  depth, configurable through `SecurityLimits`.
- `JsonSnapshotSerializer` rejects resources, closures, and non-finite floats; enforces a
  maximum normalization depth and maximum serialized byte size; rejects invalid UTF-8.
- `BoundedArtifactPublisher` drops oversized occurrence `data`, oversized snapshot payloads,
  and executions that exceed the per-execution artifact count, reporting drops through a
  callback rather than aborting the business result.
- Tenant isolation is proven by regression tests across the synchronous stack, Fibers, and
  (where available) Swoole coroutines.

## Negative tests

Every bound has a corresponding negative test in `tests/Unit` (see `AttributeValidatorTest`,
`JsonSnapshotSerializerTest`, `BoundedArtifactPublisherTest`) so that a relaxed or removed
limit fails CI rather than silently widening the attack surface.
