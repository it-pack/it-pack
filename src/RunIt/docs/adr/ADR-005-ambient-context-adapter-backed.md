# ADR-005: Ambient context is adapter-backed

**Status:** Accepted

Fiber, coroutine, and process-specific context storage belongs in adapters.
This prevents global mutable execution state from entering the core model.
