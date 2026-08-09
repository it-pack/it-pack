# ADR-017: Identifiers are validated opaque strings with pluggable generation

**Status:** Accepted

RunIt identifiers (`ExecutionId`, `ActivityId`, `ObservationId`, `OccurrenceId`,
`SnapshotId`, `CorrelationId`) are immutable value objects wrapping a validated
opaque string. The core does not mandate UUID, ULID, or any other encoding: a
remote caller may supply a `CorrelationId` in a foreign format, and consumers
may prefer sortable identifiers without RunIt taking a dependency on a
generation library.

Validation is therefore structural, not syntactic. A value must be non-empty,
must not exceed `StringIdentifierValidator::MAX_LENGTH` bytes, and must consist
only of visible ASCII characters (`0x21`–`0x7E`). Whitespace, control
characters, and non-ASCII bytes are rejected rather than trimmed or transcoded,
so a value round-trips verbatim and cannot inject line breaks into logs,
headers, or event extension attributes.

Generation is a port. `IdentifierGeneratorInterface` supplies raw identifier
strings, and `StringIdentifierFactory` binds a generator to one identifier's
named constructor. Consumers choose UUIDv4, UUIDv7, ULID, or a deterministic
test generator without the domain depending on a randomness source. Identifiers
remain constructible directly from a string through `fromString()` so
reconstruction from transport never requires a factory.

Equality is type-aware: two identifiers are equal when they are the same
concrete identifier type and carry the same value. An `ExecutionId` never
equals an `ActivityId`, even when both wrap the same string.
