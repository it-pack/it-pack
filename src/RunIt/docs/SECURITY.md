# Security Policy

RunIt models execution itself, so its artifacts can carry sensitive runtime data.
This document summarizes how the library protects that data and how to report
problems. It complements the engineering rules in `AGENTS.md` and the detailed
analysis in `docs/security/threat-model.md` and `docs/security/supply-chain.md`.

## Reporting a vulnerability

Do **not** open a public issue for a suspected security vulnerability. Report it
privately to the package maintainers through the ItPack security contact, including:

- a description of the affected behavior and the version or commit;
- a minimal reproduction (code or sequence of steps);
- the expected vs. observed behavior;
- any known impact (data exposure, privilege boundary crossing, denial of service).

Maintainers acknowledge reports promptly, coordinate a fix, and credit reporters
unless anonymity is requested. Security fixes take priority over feature work.

## Supported versions

| Version line                   | Supported                                 |
|--------------------------------|-------------------------------------------|
| `1.0.x` (after stable release) | Yes                                       |
| `0.x` / pre-release            | Best effort; upgrade to the latest commit |

Before `1.0.0`, RunIt may introduce breaking changes; treat the `main` branch as
the supported line. After `1.0.0`, RunIt follows semantic versioning and the
upgrade notes in `UPGRADING.md`.

## Security model

RunIt treats context, observations, occurrences, and snapshots as potential
data-exfiltration paths and applies defense-in-depth:

- **Classify.** Every context entry carries a classification (`public`,
  `internal`, `confidential`, `restricted`, `secret`). Classification informs
  export, propagation, and serialization policies. See
  [`docs/concepts/context.md`](docs/concepts/context.md).
- **Classified context.** `Execution::normalize()` applies the default context
  redaction policy. Direct `ExecutionContext::toArray()` calls apply no policy by
  default; pass `DefaultContextRedactionPolicy::default()` explicitly to redact
  before export. Classification does not cover arbitrary messages, throwable
  descriptions, or payloads. Do not put secrets into those fields without
  independent protection.
- **Bound everything.** Keys, values, attribute maps, occurrences, and snapshots
  are size- and cardinality-bounded through `SecurityLimits`.
- **Avoid arbitrary objects.** Built-in payload validation rejects unsupported
  objects, but snapshots recorded by `DefaultSnapshotRecorder` with
  `JsonSnapshotSerializer` can accept `JsonSerializable` objects. The serializer
  calls `jsonSerialize()` during validation and again during JSON encoding, so
  object code runs before the snapshot is normalized. Do not rely on snapshot
  recording to reject arbitrary object inputs before invoking their code.
  Throwable descriptions can contain sensitive messages and, when enabled at
  capture, traces; their primitive representation is not itself a redaction
  guarantee.

### Current publication limitations

Provenance records are owned by the SDK execution runner and released after
terminal publication and context cleanup, not after asynchronous delivery.
The default builder stack verifies and classifies before calling its base
publisher, so a buffer there can retain the resulting projection without retaining
provenance. Custom stacks can instead receive raw business artifacts; deferring
their verification beyond cleanup causes a reported drop, while directly exporting
them bypasses the default projection and its field restrictions. See
[`architecture/publication.md`](architecture/publication.md#provenance-lifetime-and-asynchronous-delivery)
for ownership, bounds, and composition limitations.

The SDK builder installs the default redactor unless explicitly overridden, and
the publication stack applies redaction before sampling. On the default SDK
stack, observations are also classified before sampling: arbitrary messages,
attributes and diagnostic codes are replaced or removed; validated severity,
verified identities, bounded timing and throwable class/code metadata are retained.
Trace observations retain their typed severity on this path. Explicitly configured redactors and custom stack
factories do not acquire this default observation classification automatically.
Default SDK occurrences retain verified IDs and timing, but use a fixed type and
source and omit unclassified data and attributes. This prevents routing by the
original occurrence type. Other artifact types may still carry unclassified fields
to samplers and sinks.
On the default SDK stack, measurements are currently dropped with a payload-free
health report because their required names, units and values are unclassified.
This is a delivery loss, not a numeric-value redaction; explicit redactor
overrides bypass this default guard.
Default snapshot projections omit payloads, hashes and unclassified metadata
before sampling. They retain verified relationships and validated structural
fields, but do not implement `SnapshotInterface`; explicitly configured redactors
are responsible for their own policy.
Default execution and activity projections replace names and kinds, omit
unclassified context and attributes, and retain verified relationships, validated
statuses, timing, and bounded throwable class/code metadata. A sampling resolver
keyed by business execution IDs will not match the export IDs it receives.
The default
redactor examines a top-level `throwable` and a failed execution's
`result.throwable`. It masks throwable messages, replaces traces with null, omits
unknown throwable fields, and limits throwable cause traversal to 16 levels.

A redacted artifact carries a trusted `artifactType()` signal set by the redactor,
so adapters route it by signal instead of inferring kind from user-controlled
normalized fields. `artifactType()` is a routing assertion, not validation or
sanitization of the artifact's contents: a recognized delivery kind does not make
the projection safe to export or free of sensitive data. Adapters must still apply
their own redaction policy before delivery.
OpenTelemetry delivers redacted executions, activities,
occurrences, and snapshots through their redacted mappers, and redacted observations
through the redacted observation mapper (suppressing free-form attributes) or, when
the mapper lacks that capability, reports the drop through the health channel and
drops the artifact fail-open. Redacted measurements are not delivered: they are
reported through the health channel and dropped, since there is no typed redacted
measurement mapper. Third-party redacted projections that carry no trusted type
signal are likewise reported and dropped rather than routed by heuristic.

Configured payload-key masking is deny-list based and the default key lists are
empty. Configured sensitive observation-attribute keys are masked at the top level
only; nested attribute entries are not masked. Message redaction is separately
configurable and enabled by default; setting
`redactObservationMessages` to `false` opts out of masking observation messages.

The current redactor matches configured keys exactly and case-sensitively. It
walks arrays recursively only in occurrence `data` and snapshot `payload`, and
only when the artifact implements the corresponding interface. Configured attribute
keys also mask matching top-level occurrence extension and snapshot attributes, not
nested entries. Unknown fields, identifiers, and values under unconfigured keys are
not covered by that traversal. Snapshot `content_hash` also remains the hash of the
original payload after masking: `SnapshotPayloadPolicy::HashOnly` and `Full` export
that hash, while `None` omits it.

Configured-key payload traversal is limited to 16 levels and 1,024 visited nodes;
when those limits are hit the excess is replaced by the marker and truncation is
reported through the health path as a bounded, payload-free notice. Throwable-shaped
arrays inside payloads are also traversed under the default throwable-masking
policy. The redactor itself neither validates the `NormalizableInterface` contract
nor treats custom artifacts as sanitized: an object and unknown fields pass through
unchanged, including when another field is masked. Such projections can expose data
or exhaust resources; do not rely on the redactor as a validator or sanitizer for
untrusted artifact implementations. Built-in occurrence and snapshot constructors
validate payload values; custom implementations must honor the bounded, recursively
primitive `NormalizableInterface` contract.

Direct execution throwable redaction therefore also protects snapshot payloads under
the default configuration: the redactor traverses array payloads to find
throwable-shaped arrays even without configured sensitive keys.

When a publication health callback fails, the fallback reporter can log both
exception messages and the artifact ID. Those diagnostics may contain sensitive
data; treat the fallback log as sensitive and avoid placing secrets in exception
messages or identifiers.

Do not publish through the same fail-safe publisher from its health reporter.
The recursion guard reports each nested attempt through that reporter, which can
reenter it repeatedly. A bounded test demonstrates repeated reporter invocation;
it does not establish a safe recursion limit for an unbounded callback.

Apply and verify an appropriate independent sanitization policy before data
reaches a sampler or external sink.

## Threat model and supply chain

- [`docs/security/threat-model.md`](docs/security/threat-model.md) enumerates the
  trust boundaries, data-flow assumptions, and recommended controls.
- [`docs/security/supply-chain.md`](docs/security/supply-chain.md) covers
  dependency hygiene, optional dependencies, and release integrity.

## Default limits

The shipped `SecurityLimits` bound context entries, value lengths, array element
counts, attribute depth, and payload sizes. These defaults are explicit and can be
overridden deliberately; see `docs/QA.md` and the `Security` section of
[`README.md`](README.md).

## External adapters

OpenTelemetry, CloudEvents, PSR, Swoole, and OpenSwoole are optional adapters. Core
code never configures global providers or selects an exporter, and it must not
introduce a mandatory adapter dependency (ADR-006, ADR-010). Run
`composer audit` and the project quality checks before release.
