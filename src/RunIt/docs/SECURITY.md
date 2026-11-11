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

The SDK builder installs `DefaultArtifactRedactor` unless explicitly overridden,
but it does not currently protect every sensitive path. The default redactor
examines a top-level `throwable` and a failed execution's `result.throwable`.
It masks recognized string messages and traces, rather than omitting traces.
Configured payload-key
masking is deny-list based and the default key lists are empty. The default stack
also invokes a configured sampler before the redactor, so samplers can see the
original artifact. A changed artifact is represented by a generic projection,
which not all typed adapter paths accept. Directly configured publishers and
`withRedactor(null)` can bypass redaction altogether.

Configured sensitive observation-attribute keys are only masked at the top
level. A composed PSR-3 logger test confirms that the same key inside a nested
attribute reaches the logger unchanged. Do not put secrets in nested attributes
and rely on these key settings to remove them. A separate composed PSR-3 test
shows that the observation's own message reaches the logger unchanged even
when a configured attribute and its throwable message and trace are masked.
Message redaction is separately configurable and disabled by default.

The current redactor matches configured keys exactly and case-sensitively. It
walks arrays recursively only in occurrence `data` and snapshot `payload`, and
only when the artifact implements the corresponding interface. Configured
attribute keys also mask matching top-level occurrence extension and snapshot
attributes, not nested entries. Unknown fields, identifiers, and values under
unconfigured keys are not covered by that traversal. Snapshot `content_hash`
also remains the hash of the original payload after masking.

Built-in occurrence and snapshot constructors normally validate payload values;
custom implementations must honor the bounded, recursively primitive
`NormalizableInterface` contract. The redactor itself neither validates that
contract nor bounds recursion or detects cycles in custom projections. A test
using a deliberately nonconforming custom artifact confirms that an object and
unknown fields pass through unchanged, including when another field is masked.
Such projections can expose data or exhaust resources; do not treat the redactor
as a validator or sanitizer for untrusted artifact implementations.
Even a finite, primitive custom projection can use an array-valued throwable
trace: the current redactor masks string traces but leaves that array unchanged.
The built-in throwable shape is not a validation guarantee for custom artifacts.

A composed OpenTelemetry test checks that classified markers in a failed
execution's throwable do not reach the tested span event under default redaction.
This does not establish that other fields, custom projections, or exporters are safe.

A separate OpenTelemetry characterization shows that an observation changed by
the default redactor is silently dropped by the tested log and span-event paths,
while an unredacted control reaches both. This is an observability loss, not a
guarantee that other redacted projections are dropped or safe to export.

The OpenTelemetry redacted snapshot mapper omits the content hash under
`SnapshotPayloadPolicy::None` for logs and span events. `HashOnly` and `Full`
still export the original content hash, which can disclose information about its
inputs even when the payload was redacted. Redacted occurrences do not export
extension attributes in the default mapper.

When a publication health callback fails, the fallback reporter can log both
exception messages and the artifact ID. Those diagnostics may contain sensitive
data; treat the fallback log as sensitive and avoid placing secrets in exception
messages or identifiers.

Do not publish through the same fail-safe publisher from its health reporter.
The recursion guard reports each nested attempt through that reporter, which can
reenter it repeatedly. A bounded test demonstrates repeated reporter invocation;
it does not establish a safe recursion limit for an unbounded callback.

Until the Phase 2 work in `docs/REFACTORING_PLAN.md` is complete, do not rely on
the built-in publisher stack to remove secrets from all artifact fields or from
sampler inputs. Apply and verify an appropriate independent sanitization policy
before data reaches a sampler or external sink.

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
