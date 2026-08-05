# ADR-009: Monotonic time measures duration

**Status:** Accepted

Duration calculations use a monotonic clock. Wall-clock timestamps describe
when artifacts occurred and must not be used to calculate elapsed duration.
