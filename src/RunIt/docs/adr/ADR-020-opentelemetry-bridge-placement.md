# ADR-020: OpenTelemetry bridge placement

**Status:** Accepted

**Context**

Milestone M9 introduces an OpenTelemetry integration. ADR-016 defines the criteria
under which an integration moves to a dedicated bridge package: it requires a
non-core dependency, has an independently useful release cadence, or imposes a
distinct compatibility surface.

The OpenTelemetry API and SDK are non-core dependencies, which nominally satisfies
the first ADR-016 criterion. However, the existing PSR adapters (M8) also depend
on non-core PSR packages and live in the root package under `src/Adapter/Psr`,
because consumers opt into those dependencies only when they wire the adapter. The
OpenTelemetry bridge follows the same established pattern.

**Decision**

The OpenTelemetry bridge remains in the root `it-pack/run-it` package under
`src/Adapter/OpenTelemetry`. It depends only on the OpenTelemetry API at the
Adapter layer; the SDK is used exclusively by tests and examples, never by the
bridge source. The bridge is therefore vendored like any other adapter: the core
stays usable without OpenTelemetry, and consumers install `open-telemetry/api`
(and `open-telemetry/sdk` for real exporters) only when they choose the bridge.

The bridge will be split into a dedicated `it-pack/run-it-otel` package later only
if a second driver appears, or if the OpenTelemetry release cadence diverges from
the core in a way that forces independent versioning. Until then, keeping it in
the root package preserves a single compatibility surface and matches M8.

**Consequences**

- `src/Adapter/OpenTelemetry` must not introduce a dependency on OpenTelemetry into
  the domain, application, or SDK layers.
- The bridge must never configure global OpenTelemetry providers; every provider is
  injected through `BridgeConfig`.
- Consumers who want OpenTelemetry export must add `open-telemetry/api` (and
  `open-telemetry/sdk`) to their own `require`/`require-dev`.
