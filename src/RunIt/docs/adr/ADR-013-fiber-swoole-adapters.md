# ADR-013: Fiber and Swoole support is adapter-based

**Status:** Accepted

Fiber and coroutine execution contexts are runtime adapters. They must restore
or clear context in `finally` and must not leak it between worker requests.
