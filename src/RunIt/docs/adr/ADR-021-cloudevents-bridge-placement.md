# ADR-021: CloudEvents bridge placement

**Status:** Accepted

**Context**

Milestone M10 introduces a CloudEvents integration that maps RunIt occurrences to
and from the CloudEvents 1.0 envelope. ADR-016 defines the criteria under which
an integration moves to a dedicated bridge package: it requires a non-core
dependency, has an independently useful release cadence, or imposes a distinct
compatibility surface. ADR-020 placed the OpenTelemetry bridge in the root package
for the same reasons the PSR adapters (M8) live under `src/Adapter/Psr`.

CloudEvents is a specification, not a mandatory library. The structured JSON
representation is a small, stable envelope (a handful of string attributes plus an
open extension map), and `cloudevents/sdk-php` is listed only under `suggest`.
Pulling in that SDK would couple the bridge to a specific library's evolving API
for little benefit, and it would introduce a non-core dependency into the root
package contrary to ADR-010 (core has no mandatory external integration).

**Decision**

The CloudEvents bridge remains in the root `it-pack/run-it` package under
`src/Adapter/CloudEvents` and is intentionally **SDK-free**. It implements the
CloudEvents structured JSON representation directly: a self-contained immutable
`CloudEvent` value object, dedicated mapper, serializer, and sink contracts. The
bridge depends only on `Contract`, `Domain`, `Behavior`, and `Common`; it never
imports `cloudevents/sdk-php` and never configures any global CloudEvents provider.

`cloudevents/sdk-php` stays a `suggest` entry for consumers who already use it and
wish to interoperate; the bridge does not require it. The bridge will be split into
a dedicated `it-pack/run-it-cloudevents` package only if a second, conflicting
CloudEvents implementation appears or if its release cadence diverges from the core.

**Consequences**

- `src/Adapter/CloudEvents` must not introduce a dependency on `cloudevents/sdk-php`
  (or any CloudEvents library) into the domain, application, SDK, or the bridge
  source.
- The bridge must never configure a global CloudEvents provider; delivery targets
  are injected through `CloudEventsBridgeConfig` as `CloudEventSinkInterface`.
- The core remains usable without any CloudEvents dependency; only occurrences are
  mapped, and the `CloudEventPublisher` ignores every other artifact type.
- Consumers who want HTTP delivery supply a PSR-18 client and factory; the bridge
  ships `Psr18CloudEventSink` but imposes no retry or durable-delivery guarantee.
