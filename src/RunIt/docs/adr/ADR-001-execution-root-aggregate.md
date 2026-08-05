# ADR-001: Execution is the root aggregate

**Status:** Accepted

Execution is the aggregate that owns the identity and lifecycle relationship of
all RunIt artifacts. Artifacts reference an execution identity rather than
requiring in-memory ownership collections.
