# ADR-019: Time and sequencing use immutable value objects with monotonic duration

**Status:** Accepted

RunIt models a point in time as an immutable `Instant` (Unix epoch seconds, no
timezone) and an elapsed time as an immutable `Duration` represented internally
as integer nanoseconds. Artifact ordering uses an immutable `ArtifactSequence`,
and time is supplied through the `MonotonicClockInterface` port, which keeps
wall-clock seconds and monotonic nanoseconds strictly separate.

Durations use monotonic time, never wall-clock time. Wall-clock time can jump
backwards during an NTP correction or a manual change, which would corrupt any
elapsed-time measurement; monotonic time cannot, so it is the only correct
source for a `Duration`. The two are therefore distinct methods on the clock and
are never mixed.

A duration is represented as integer nanoseconds rather than a float. Floating
point silently loses sub-second precision and accumulates rounding error across
many measurements; integer nanoseconds stay exact and are cheap to add and
compare. The value is bounded (a little over 292 years per duration), which is
more than enough for runtime measurement and keeps the representation a single
native integer.

Sequences are values, not counters: `ArtifactSequence::next()` returns a new
sequence and never mutates the receiver, so a sequence can be passed around and
stored without aliasing bugs. Allocation is a port so the storage strategy
(in-memory, persisted, distributed) is an adapter concern and the domain never
depends on a particular one.

Equality and immutability make these primitives safe to use on hot paths and to
reconstruct from transport without copying external mutable state.
