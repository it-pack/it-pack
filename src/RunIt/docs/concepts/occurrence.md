# Occurrence

An **occurrence** is an immutable fact that happened during an execution. It is a
stable, versioned, named event with structured data. The concept is defined by
[`runit:Occurrence`](https://w3id.org/itpack/runit#Occurrence). CloudEvents are
one possible representation of an occurrence, not its domain definition.

> Occurrences record work.

## What an occurrence represents

An occurrence answers "what happened?" — a discrete, nameable fact the system can
record and later react to. Occurrences are facts; observations (see
[`observation.md`](observation.md)) are qualitative notes.

Examples:

```
Configuration loaded     Snapshot created      Schema validated
Secrets retrieved        Pipeline completed     Deployment applied
```

## Types

| Contract | Value object | Notes |
|---|---|---|
| `OccurrenceInterface` | `ItPack\RunIt\Domain\Occurrence\Occurrence` | Immutable fact |
| `OccurrenceIdInterface` | `ItPack\RunIt\Domain\Occurrence\OccurrenceId` | Opaque stable identifier |
| `OccurrenceTypeInterface` | `ItPack\RunIt\Domain\Occurrence\OccurrenceType` | Validated, versioned type |

An `OccurrenceType` is a validated string of the form
`^[a-z0-9]+(?:[._-][a-z0-9]+)*\.v[0-9]+$` — lowercase, dot/underscore/dash
separated, suffixed with a `.vN` version. `version()` returns the numeric part
(`"1"` for `config.loaded.v1`). Type names are independent of PHP class names.

## Ownership

- Every occurrence belongs to exactly one execution (`executionId()`).
- An occurrence may be bound to an activity (`activityId()`), or be execution-level
  (`null`).

## Normalization

`Occurrence::normalize()` returns a deterministic, recursively-primitive array:

```text
id                    string   occurrence identifier
execution_id          string   owning execution identifier
activity_id           string|null
type                  string   occurrence type (e.g. config.loaded.v1)
version               string   type version (e.g. 1)
source                string|null
subject               string|null
time                  int      Unix epoch seconds
data                  mixed    recursively-primitive payload
data_schema           string|null
content_type          string|null
extension_attributes  array    preserved unknown extension attributes
sequence              int      monotonic sequence within the execution
```

## Invariants

- Occurrences are immutable facts.
- Occurrence types are stable, lowercase, and versioned.
- Occurrence types are independent of PHP class names.
- `data` is recursively primitive; arbitrary PHP objects are never serialized.
- CloudEvents is a representation of occurrences, not their domain definition.
  When reading CloudEvents, unknown extension attributes are preserved.
- Attributes and payloads are bounded.

## Public API usage

```php
declare(strict_types=1);

use ItPack\RunIt\Domain\Occurrence\Occurrence;
use ItPack\RunIt\Domain\Occurrence\OccurrenceType;

$occurrence = Occurrence::raise(
    $executionId,
    OccurrenceType::fromString('config.loaded.v1'),
    data: ['source' => 'app/config.yaml'],
);
```

Consumers normally raise occurrences through `OccurrenceRecorderInterface`, which
attributes the occurrence to the active scope and applies redaction, sampling, and
routing before adapter publication.

## Related decisions

- ADR-002 — Domain artifacts are immutable.
- ADR-006 — External standards are adapters (CloudEvents maps occurrences).
- ADR-011 — Versioned occurrence names.
- ADR-018 — Extensible string names and kinds.

See also: [`execution.md`](execution.md), [`observation.md`](observation.md).
