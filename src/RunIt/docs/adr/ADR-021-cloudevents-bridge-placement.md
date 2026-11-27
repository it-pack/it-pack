# ADR-021: CloudEvents bridge placement

**Status:** Accepted, revised for the official SDK migration

## Context

The original M10 decision kept the CloudEvents bridge in RunIt and implemented
structured JSON without the official SDK. The migration replaces that SDK-free
decision: generic CloudEvents envelope handling belongs in `cloudevents/sdk-php`,
while RunIt retains its occurrence semantics, security controls and public ports.

## Decision

The bridge remains in `src/Adapter/CloudEvents` and `it-pack/run-it` directly
requires `cloudevents/sdk-php` at `^1.2`. This is a package dependency even when an
application does not use the bridge. Domain and application code remain independent
of the CloudEvents SDK; existing RunIt event, mapper, serializer and sink contracts
remain the integration boundary. No global CloudEvents provider is configured.

SDK validation is the default. A transitional opt-in legacy path handles a whole
event when it cannot be represented by the SDK; it does not waive size or security
limits and may emit non-conformant CloudEvents. Independently constructed mappers
and serializers require independent configuration.

## Consequences

- Composer resolution and supply-chain review now include the CloudEvents SDK.
- Strict validation and structured JSON differences are compatibility-sensitive;
  review consumer data and migrations before release.
- Only occurrences are mapped; other artifacts are ignored by the publisher.
- HTTP delivery uses an injected PSR-18 client and factories. The thin RunIt
  transport retains custom serializer support and existing failure semantics;
  it does not provide retries or durable delivery.
