# ADR-0011: Secure Transport Defaults

## Status

Accepted (REVIEW-4 NV2–NV6 hardening, refactoring-plan Milestone 1).

## Context

Review 4 found four transport/storage sharp edges in adapters that handle secret-bearing
configuration:

1. Both filesystem caches created their directories world-writable (`0o777`) although the
   artifacts embed resolved *and decrypted* configuration (`FileSystemCache`) or raw
   fetched documents (`FileSystemDocumentCache`), and writes were non-atomic.
2. `NativeCommandRunner` passed `implode(' ', array_map('escapeshellarg', …))` to
   `proc_open` — a string command goes through `/bin/sh`, contradicting its own "no shell"
   docblock — and wrote all stdin before draining stdout, deadlocking once pipe buffers
   (~64 KiB) filled; there was no process deadline and stderr was embedded untruncated.
3. `CurlHttpClient` followed redirects automatically while callers pass signed/authenticated
   headers — a redirect would forward those credentials to whatever host it points at;
   timeouts were magic literals with no connect timeout.
4. `ConsulClient` / `EtcdClient` hardcoded plaintext `http://` upstreams, unlike every other
   enterprise client.

## Decision

- **Cache storage:** directories are created at most group-writable (`0o770`); artifact
  writes are atomic (temp file + `rename`, temp cleaned in `finally`); compiled-artifact
  cache keys are validated against `[A-Za-z0-9._-]{1,128}` as defense-in-depth.
- **Command execution:** the argument **array** is handed to `proc_open` directly — no shell
  exists anywhere on that path. stdin writes and stdout/stderr drains interleave via
  `stream_select`, so arbitrary payload sizes cannot deadlock. A wall-clock deadline
  (default 30 s, injectable) terminates runaway processes. stderr embedded in exception
  messages is truncated to 2048 bytes so tool output cannot leak bulk secrets into logs.
- **HTTP transport:** redirect following is removed (curl default). Any future need for
  redirects must be implemented as an explicit decorating transport with a documented
  header policy. Timeouts are named, injectable constants (`REQUEST_TIMEOUT_SECONDS = 10`,
  `CONNECT_TIMEOUT_SECONDS = 5`); option assembly is extracted for unit testing.
- **Include containment:** `@include` targets are resolved with `realpath()` and rejected
  unless they remain inside the processor's base directory — `..` traversal and symlink
  escapes raise `ResolutionException`.
- **Remote KV TLS:** URI schemes select the upstream scheme via an explicit map
  (`consul+tls://`/`etcd+tls://` → `https://`, plain schemes → `http://`, unknown → error);
  credentials ride on a decorated `HttpClient`, never inside the clients.

## Consequences

- Secrets-bearing artifacts are no longer readable by other system users; torn reads are
  structurally impossible.
- Command execution is deadlock-free for large payloads and bounded in time; logs cannot
  receive unbounded stderr from failing decrypt tools.
- A compromised/misconfigured redirect chain can no longer exfiltrate signed headers.
- Configuration authors can no longer pull files from outside the include base directory,
  including through symlinks.
- Behavior changes are intentional and security-motivated: HTTP responses that previously
  "worked" via cross-host redirects now fail loudly; includes escaping the base directory
  now fail loudly; command runners without a deadline now terminate after 30 s by default.

## Alternatives considered

- Per-hop header stripping while following redirects — rejected: policy is implicit,
  hard to audit, and still leaks on multi-hop chains.
- Sanitizing instead of validating cache keys — rejected for `FileSystemCache`: keys are
  compiler-generated hashes; strict validation is cheaper to reason about than rewriting.
- Opt-in shell mode for command templates — rejected outright: no current use case justifies
  reintroducing a shell onto the execution path.

## References

- `docs/REVIEW.md` findings NV2–NV6, NV17
- `docs/REFACTORING_PLAN.md` Milestone 1
- `docs/architecture/PORTS.md` §6 (remote KV TLS/auth recipe)
