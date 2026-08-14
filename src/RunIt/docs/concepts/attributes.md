# Attributes

RunIt attaches structured metadata to observations, occurrences, measurements,
and activities through an immutable, bounded attribute collection. Attributes
are key-value pairs where keys are validated strings and values are limited to
scalars, `null`, and lists or maps of scalars. This keeps attributes safe to
normalize, serialize, and export without leaking objects, resources, or
unbounded high-cardinality data. The semantics align with
[`runit:Observation`](https://w3id.org/itpack/runit#Observation); the supporting
value objects live in `ItPack\RunIt\Domain\Shared`.

## Attribute types

| Contract | Value object | Notes |
|---|---|---|
| `AttributeKeyInterface` | `ItPack\RunIt\Domain\Shared\AttributeKey` | Validated attribute key |
| `AttributeCollectionInterface` | `ItPack\RunIt\Domain\Shared\AttributeCollection` | Immutable, bounded collection |
| `InvalidAttributeException` | `ItPack\RunIt\Contract\Exception\InvalidAttributeException` | Typed rejection |

A dedicated `AttributeValueInterface` was intentionally **not** introduced.
Attribute values are a closed set of PHP types (scalar, `null`, and
scalar-only lists/maps), so a union type documents them directly and the
roadmap's "if required" clause does not apply yet.

## Validation rules

Keys follow the attribute-key rules, enforced identically by
`AttributeKey` and `AttributeCollection` through the shared
`AttributeValidator`:

- Non-empty.
- At most **256 bytes**.
- Built only from letters, digits, and the separators `.`, `-`, `_`, so
  dotted namespacing such as `http.status` is allowed.

Values are recursively primitive:

- `int`, `float`, `string`, `bool`, `null`, or
- a list/map whose members are themselves one of the above.

Objects and resources are rejected at any depth. A collection holds at most
**64** attributes. All rules are checked on construction; a violation raises
`InvalidAttributeException`, which never echoes the offending value.

## Immutability and normalization

`AttributeCollection` is immutable. `with()` and `without()` return a new
collection and never mutate the receiver; removing an absent key returns the
same instance. `toArray()` returns a deterministic map with keys sorted
lexicographically, suitable for serialization and for use as an artifact's
attributes. Iteration yields the same sorted, primitive map.

## Usage

```php
<?php

declare(strict_types=1);

use ItPack\RunIt\Domain\Shared\AttributeCollection;
use ItPack\RunIt\Domain\Shared\AttributeKey;

$key = AttributeKey::fromString('http.status');
$attributes = AttributeCollection::empty()
    ->with('http.status', 200)
    ->with('duration_ms', 12.5)
    ->with('cached', true)
    ->with('tags', ['a', 'b']);

$attributes->get('http.status'); // 200
$key->toString();                // 'http.status'
$attributes->toArray();          // deterministic, keys sorted
```

Construction validates eagerly, so an invalid key or value fails before the
collection exists:

```php
<?php

declare(strict_types=1);

use ItPack\RunIt\Domain\Shared\AttributeCollection;
use ItPack\RunIt\Contract\Exception\InvalidAttributeException;

try {
    AttributeCollection::fromArray(['bad key!' => 'value']);
} catch (InvalidAttributeException $error) {
    // key uses unsupported characters
}
```

## Security posture

Attributes are a potential data-exfiltration path, so the collection is
deliberately narrow: no objects, no resources, bounded key length, bounded
count, and a deterministic, primitive-only projection. Secret redaction is a
concern of the publication pipeline (see the context and redaction
documentation), but the attribute model itself cannot carry non-scalar or
unbounded payloads.
