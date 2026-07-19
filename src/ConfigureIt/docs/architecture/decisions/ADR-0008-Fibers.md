ADR-0008

Fibers for Asynchronous Sources

Status

Proposed

Context

Today `ConfigurationSourceInterface::discover()` and
`ConfigurationReaderInterface::read()` are synchronous
(src/Contract/ConfigurationSourceInterface.php, ConfigurationReaderInterface.php).
VISION.md and MODEL.md list inherently asynchronous sources for later phases: HTTP,
S3, Vault, Redis, Consul, Etcd. A blocking resolver that fetches these sequentially
will stall the host runtime and forgo valuable concurrency (sources are independent
and can be fetched in parallel while preserving deterministic priority-merge).

Decision

Introduce async port variants — `AsyncConfigurationSourceInterface` and
`AsyncConfigurationReaderInterface` — whose `discover()`/`read()` suspend a
**Fiber** and resume when the I/O completes. The resolver awaits all source Fibers
concurrently, then performs the existing ordered priority-merge (merge order is
deterministic and independent of fetch order). Sync ports remain the default; async
ports are adapters (ADR-0002).

Consequences

- Concurrent fetching of independent remote sources without blocking the runtime.
- Priority-merge semantics are unchanged because merging is positional, not time-based.
- Adds an async execution model the test-suite must cover (Fibers suspend/resume).
- Larger surface area; not needed until Phase 5/8 remote sources.

Alternatives

- Keep everything synchronous (rejected for remote sources: stalls host).
- A full async framework / React loop (rejected: out of scope for a config SDK; a
  Fiber boundary keeps the dependency surface minimal and framework-agnostic per
  AGENTS.md).

References

- src/Contract/ConfigurationSourceInterface.php
- src/Contract/ConfigurationReaderInterface.php
- docs/REFACTORING_PLAN.md §9
- VISION.md, MODEL.md (future source list)
