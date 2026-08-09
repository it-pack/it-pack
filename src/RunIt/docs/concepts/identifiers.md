# Identifiers

RunIt identifies executions, activities, observations, occurrences, snapshots,
and correlated work through immutable value objects. Every identifier wraps a
single validated string and carries no behavior beyond comparison and
conversion. This document describes the validation rules, generation policy,
and equality semantics shared by all identifier types. The design decision is
recorded in [ADR-017](adr/ADR-017-opaque-identifiers-pluggable-generation.md).

## Identifier types

| Contract | Value object | Vocabulary term |
|---|---|---|
| `ExecutionIdInterface` | `ItPack\RunIt\Domain\Execution\ExecutionId` | `runit:executionId` |
| `ActivityIdInterface` | `ItPack\RunIt\Domain\Activity\ActivityId` | `runit:activityId` |
| `ObservationIdInterface` | `ItPack\RunIt\Domain\Observation\ObservationId` | `runit:observationId` |
| `OccurrenceIdInterface` | `ItPack\RunIt\Domain\Occurrence\OccurrenceId` | `runit:occurrenceId` |
| `SnapshotIdInterface` | `ItPack\RunIt\Domain\Snapshot\SnapshotId` | `runit:snapshotId` |
| `CorrelationIdInterface` | `ItPack\RunIt\Domain\Shared\CorrelationId` | `runit:correlationId` |

Each value object is `final`, `readonly`, and implements both its dedicated
contract and the shared `StringIdentifierInterface`. They are constructed
through the `fromString()` named constructor and therefore need no reflection:
`ExecutionId::fromString('018f3a…')`, `CorrelationId::fromString('req-7')`, and
so on.

## Representation decision

Identifiers are **opaque validated strings**, not a fixed format. UUIDs, ULIDs,
W3C trace identifiers, and caller-supplied correlation values are all accepted.
The domain does not depend on any generation library: identity is an external
effect provided through the `IdentifierGeneratorInterface` port, and
`StringIdentifierFactory` binds a generator to one identifier's constructor.

This keeps the core vendor-neutral and lets consumers choose a sortable or
deterministic scheme without coupling the domain to it. A `CorrelationId` may
be supplied by a remote caller and is shared across executions; every other
identifier is minted for exactly one execution or artifact.

## Validation rules

`StringIdentifierValidator` applies structural rules once, on construction. The
rules are intentionally format-agnostic:

- The value must not be empty.
- The value must not exceed `StringIdentifierValidator::MAX_LENGTH` bytes (128).
- The value must consist solely of visible ASCII characters (`0x21`–`0x7E`).

Whitespace, control characters, and non-ASCII bytes are rejected rather than
trimmed or transcoded. An identifier travels through logs, HTTP headers, metric
contexts, and event extension attributes, so a value that cannot inject a line
break or a delimiter is safer than one that has been repaired. The regular
expression is anchored with `\z` rather than `$` so a trailing newline, which
`$` also matches, is rejected.

A consumer that requires a stricter format — for example, requiring a UUID —
implements `StringIdentifierValidatorInterface` at the boundary where untrusted
identifiers enter the system. The default rules remain the domain guarantee.

## Equality

Two identifiers are equal when they are the **same concrete type** and wrap the
same value. An `ExecutionId` never equals an `ActivityId` that happens to wrap
the same string, and object identity is never consulted. Equality is therefore
safe to use as a map key or a correlation check.

```php
$execution = ExecutionId::fromString('018f3a…');
$lookalike = ActivityId::fromString('018f3a…');

$execution->equals($lookalike); // false — different identifier kind
```

## Exceptions

An invalid value raises `InvalidIdentifierException`, which implements both
`InvalidValueExceptionInterface` and the root `RunItExceptionInterface`. The
rejected value is never echoed into the message: it is untrusted, may carry a
secret, and may itself contain the control characters the validation exists to
keep out of logs.

## Security notes

- Identifiers are not used as metric dimensions by default, to avoid
  high-cardinality exports from per-request UUIDs.
- A `CorrelationId` is attacker-controllable and is validated like any other
  identifier; do not place it in a span or metric attribute without an explicit
  allow list.
- The maximum length bounds memory and transport size wherever an identifier is
  exported.

## Tests

- `tests/Unit/Domain/IdentifierTest.php` covers every type, string conversion,
  value equality, type-aware equality, and rejection of empty, oversized, and
  whitespace values.
- `tests/Unit/Domain/Shared/StringIdentifierValidatorTest.php` covers the
  accepted and rejected alphabets, boundary behavior, secret-free messages, and
  a stricter replacement validator.
- `tests/Unit/Domain/Factory/StringIdentifierFactoryTest.php` covers generation
  binding through `StringIdentifierFactory`.
- `tests/Unit/Domain/IdentifierPropertyTest.php` checks the invariants over a
  deterministic randomized sample, including the guarantee that no forbidden
  byte (including a newline) passes validation.
