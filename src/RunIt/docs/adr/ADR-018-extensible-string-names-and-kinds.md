# ADR-018: Names and kinds are extensible validated strings

**Status:** Accepted

RunIt names (`ExecutionName`, `ActivityName`, `MeasurementName`), kinds
(`ExecutionKind`, `ActivityKind`), and `SnapshotType` are open, validated
strings. `OccurrenceType` is a validated string with a stricter format: it is
lowercase, dot-separated, and ends with a version such as `.v1`.

A closed enum was rejected for every one of these. New execution kinds appear
as the ecosystem grows (for example `llm.pipeline` after `configuration.resolve`),
and the domain must not change to accept them. An enum would either force a
release for every new kind or push consumers toward unsafe stringly-typed
escape hatches; an extensible string avoids both.

Validation is structural, not a vocabulary: a value must be non-empty, bounded
(128 bytes), and made of letters, digits, and the separators `.`, `-`, `_`.
Whitespace, control characters, and other symbols are rejected rather than
trimmed, because names and kinds travel into log fields, span attributes, and
CloudEvents `type` values. Occurrence types add a format rule on top because
they become CloudEvents `type` values and must stay low-cardinality and
independent of any PHP class name.

Equality is type-aware: two values are equal only when they are the same
concrete type and wrap the same string, so an execution kind never equals an
activity kind that happens to share the text.
