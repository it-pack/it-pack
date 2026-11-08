# ADR-015: Concrete classes are final and interface-backed

**Status:** Accepted

Production concrete classes are `final` and implement focused contracts.
Composition is preferred over inheritance; immutable classes use `readonly`.

## Two lawful categories

AGENTS.md requires "every concrete class implements an appropriate interface".
That rule admits exactly two interpretations, and both are permitted. The
`require-interface` PHPStan rule (M13.2) enforces the *seam-backed* half and
carries an explicit, rationale-backed exemption list for the *private-leaf* half.

### 1. Seam-backed (MUST implement an interface)

Any class consumed across a boundary — by another namespace, by a port, by a
builder, or by injected collaborators — must program against a contract, not a
concrete type. This keeps the hexagon invertible and consumers stable against
implementation changes.

Examples covered by this decision:

- PSR-3 loggers (`ExecutionAwareLogger`, `ObservationRecordingLogger`) satisfy
  `\Psr\Log\LoggerInterface` through `AbstractLogger`, including when the
  interface is not explicitly redeclared on the concrete class.
- OpenTelemetry lookup maps (`SeverityMap`, `SpanKindMap`, `SpanStatusMap`)
  declare `implements SeverityMapInterface` / `SpanKindMapInterface` /
  `SpanStatusMapInterface`, so the mappers that inject them depend on the
  contract rather than the concrete map.

### 2. Private leaf (MAY be exempt)

`final` adapter-private value objects, configuration VOs, builders, lookups,
wrappers, attributes, and the single internal safety-net reporter MAY omit an
interface **provided** they are `final` (and `final readonly` where applicable)
and listed in `RequireInterfaceRule`'s `exemptFiles`. An interface would add no
semantic value because no consumer exists outside their own file/namespace.

Permanent exemptions (one-word category in parentheses):

- `CloudEvent`, `CloudEventsBridgeConfig`, `CloudEventMappingPolicy`,
  `BridgeCapability`, `BridgeConfig`, `InstrumentCache`, `AttributeKeys`,
  `OccurrenceEventWrapper`, `ArtifactRedactionConfig`, `BufferConfiguration`,
  `PublicationRoute`, `SecurityLimits` — value/config/lookup/constants/wrapper.
- `CloudEventsBridgeConfigBuilder`, `BridgeConfigBuilder` — builders of their own
  config VO; the built VO is the contract.
- `ExecutedAs` — PHP attribute; metadata with no consumer contract.
- `FallbackErrorLogReporter` — deliberate safety net. The last-resort sink that
  contains throwing health callbacks must never be swappable by configuration,
  so exposing a contract would only undermine it. It is therefore a permanent
  private-leaf exemption rather than a seam-backed type.

Whole directories `src/Conformance` and `src/Behavior` are exempt (test-harness
verifiers/fixtures and shared behavior helpers).

## Decision

Split the single "implement an interface" rule into the two categories above.
Promote classes with a real external consumer to seam-backed; record the rest as
permanent private-leaf exemptions. Do not introduce an interface merely to
satisfy the rule when no consumer exists.
