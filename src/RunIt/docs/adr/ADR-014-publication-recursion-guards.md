# ADR-014: Self-observation uses recursion guards

**Status:** Accepted

Publication instrumentation is protected by a recursion guard. Its failure
reporter never routes back through the publisher that failed.
