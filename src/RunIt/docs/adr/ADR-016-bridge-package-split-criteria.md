# ADR-016: Define bridge package split criteria

**Status:** Accepted

An integration moves to a bridge package only when it requires a non-core
dependency, has an independently useful release cadence, or imposes a distinct
compatibility surface. The core remains vendor-neutral.
