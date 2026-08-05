# ADR-008: Original business exceptions have priority

**Status:** Accepted

When an operation and instrumentation both fail, the original operation
throwable is rethrown. Publication failures use a recursion-safe health path.
