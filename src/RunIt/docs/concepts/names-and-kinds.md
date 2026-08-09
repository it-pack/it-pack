# Names and kinds

RunIt describes work with a small set of validated string values: execution and
activity names, execution and activity kinds, measurement names, occurrence
types, and snapshot types. Every one is an immutable, `final` value object that
validates its content on construction and compares by type and value. The
design decision is recorded in
[ADR-018](adr/ADR-018-extensible-string-names-and-kinds.md).

## Value objects

| Contract | Value object | Vocabulary term |
|---|---|---|
| `ExecutionNameInterface` | `ItPack\RunIt\Domain\Execution\ExecutionName` | `runit:executionName` |
| `ExecutionKindInterface` | `ItPack\RunIt\Domain\Execution\ExecutionKind` | `runit:executionKind` |
| `ActivityNameInterface` | `ItPack\RunIt\Domain\Activity\ActivityName` | `runit:activityName` |
| `ActivityKindInterface` | `ItPack\RunIt\Domain\Activity\ActivityKind` | `runit:activityKind` |
| `MeasurementNameInterface` | `ItPack\RunIt\Domain\Measurement\MeasurementName` | `runit:measurementName` |
| `OccurrenceTypeInterface` | `ItPack\RunIt\Domain\Occurrence\OccurrenceType` | `runit:occurrenceType` |
| `SnapshotTypeInterface` | `ItPack\RunIt\Domain\Snapshot\SnapshotType` | `runit:snapshotType` |

All seven implement `StringValueInterface`, which extends
`StringProviderInterface` (a stable `toString()` projection) and adds type-aware
`equals()`. They are created through the `fromString()` named constructor:

```php
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\Domain\Occurrence\OccurrenceType;

$name = ExecutionName::fromString('configuration.resolve');
$type = OccurrenceType::fromString('itpack.configuration.loaded.v1');
```

## Why extensible strings, not enums

Kinds and names are deliberately open. A new execution kind (`llm.pipeline`) or
measurement name should not require a code change in the domain or a new
release. A closed enum would either force a release per new kind or push
consumers toward unsafe raw strings; an extensible validated string avoids both.

Occurrence types are validated strings with a stricter format, because they
become CloudEvents `type` values and must stay low-cardinality and independent
of any PHP class name.

## Validation rules

`StringValueValidator` applies structural rules once, on construction:

- The value must not be empty.
- The value must not exceed 128 bytes.
- The value must consist solely of letters, digits, and the separators `.`,
  `-`, `_` (no whitespace, control characters, or other symbols).

Values are stored verbatim: nothing trims, lowercases, or transcodes them, so
`fromString()` round-trips exactly and a value cannot inject a delimiter or line
break into an exported field.

`OccurrenceType` adds a format rule on top of these: a valid type matches
`^[a-z0-9]+(?:[._-][a-z0-9]+)*\.v[0-9]+$`. It must be lowercase, dot-separated,
and end with a `v`-prefixed version. `OccurrenceType::version()` returns that
version suffix. Namespaces are recommended so types from different packages do
not collide.

## Equality

Two values are equal when they are the **same concrete type** and wrap the same
string. An `ExecutionName` never equals an `ActivityName` that wraps the same
text, and object identity is never consulted. This keeps equality safe as a
map key or comparison.

## Exceptions

An invalid value raises `InvalidStringValueException`, which implements both
`InvalidValueExceptionInterface` and the root `RunItExceptionInterface`. The
rejected value is never echoed into the message.

## Tests

- `tests/Unit/Domain/NamesAndKindsTest.php` covers every type, deterministic
  string conversion, value equality, type-aware equality, and rejection of
  empty, oversized, and symbol-containing values.
- `tests/Unit/Domain/Occurrence/OccurrenceTypeTest.php` covers the strict
  occurrence-type format, version extraction, and rejection of uppercase,
  unversioned, and malformed types.
- `tests/Unit/Domain/Shared/StringValueValidatorTest.php` covers the accepted
  and rejected alphabets and boundary behavior.
