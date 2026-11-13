# Changelog

All notable changes to RunIt are documented in this file.

RunIt is pre-release software. Until `1.0.0`, public APIs may change between
pre-release versions.

## 1.0.0-rc.1

First release candidate for the stable `1.0.0` API (milestone M15.1).

- Freezes the public API surface at **372 public symbols** (147 interfaces,
  206 classes, 13 enums) under `src/Domain`, `src/Application`, `src/Contract`,
  `src/Adapter`, `src/SDK`, and `src/Common`.
- Freezes the stable normalization schema (`config/normalization-schema.json`) at
  8 artifact types and 76 fields.
- Regenerates the backward-compatibility baseline in `tools/api-compat/`; this
  baseline is the stability contract for `1.0.0` and is enforced by
  `composer api:check` in CI.
- Explicitly marks internal namespaces: `ItPack\RunIt\Behavior\` and
  `ItPack\RunIt\Conformance\` are excluded from the backward-compatibility
  guarantee (the conformance harness is shipped and reachable, but consumers must
  pin the exact RunIt version).
- Confirms no abandoned or experimental APIs remain in the public tree.
- The detailed change history accumulated during pre-`1.0.0` development is
  recorded in the `Unreleased` sections below; this entry marks the point at
  which that surface was frozen for the release candidate.

## Unreleased

- A failing health callback now produces fixed payload-free fallback diagnostics
  without logging exception messages or artifact identifiers. Configured callbacks
  still receive the original arguments.
- The default SDK publication stack classifies observations before sampling,
  removing arbitrary attributes and diagnostic codes and masking messages while
  retaining validated severity and bounded throwable class/code metadata. Explicitly
  configured redactors and custom stacks retain their configured behavior.
- Default SDK publication reports and drops measurements before sampling until
  their required names, units and values have an approved classification; custom
  redactor configurations bypass this default guard. This can suppress all
  measurements on the default path.
- Default SDK occurrences now use the fixed exported type `runit.redacted.v1`
  and source `runit:redacted`, with unclassified payload and attributes omitted.
  Consumers cannot route these projections by the original occurrence type.
- Default SDK execution and activity projections replace names and kinds and
  omit unclassified context, result and attribute content. Execution-kind
  samplers resolve using export execution IDs; resolvers keyed only by business
  IDs must be migrated or they fail closed.
- The default SDK path withholds snapshot payloads, hashes and unclassified
  metadata before sampling. Default snapshot exports are generic projections;
  samplers and consumers requiring `SnapshotInterface` or those fields need an
  explicit alternative policy. Explicit redactor overrides retain their existing
  behavior.
- Provenance retention is capped at 100,000 live records across each SDK
  authority as well as per execution. At capacity new bindings are dropped and
  reported through the configured health path; releasing executions frees capacity.
  Dropped artifacts have no trusted provenance and are not published as verified.
- Phase 2 introduces the public `TrustedArtifactProjectionInterface` for routing
  projections; implementing it does not establish provenance. Trust requires an
  upstream provenance check. The API baseline has not been updated for this or
  the internal `ClassifiedRedactedOccurrence` addition.
- `ProvenanceAuthority::bindActivityRelationship()` now accepts verified export
  IDs (nullable, backward-compatible defaults) instead of business ID claims.
  Callers resolve activity business IDs to export IDs via `exportIdForBusinessId()`
  before binding, eliminating the business-ID-claim weakness. Old business-ID
  calls are silently no-ops (export IDs are not business IDs).
- `TelemetryPublisher` dispatches verified/classified projections (including
  `VerifiedArtifactProjection` and `ClassifiedArtifact`) through redacted mapper
  methods based on the trusted `artifactType()`, producing an observable
  payload-free delivery rather than silent loss.
- Terminal snapshots omit `payload` and `content_hash` by default; they are
  published as classified projections until an explicit opt-in rewrites them
  as verified exports.
- `DefaultArtifactRedactor` omits throwable trace values by default; trace is
  no longer preserved as a null field in the normalized projection.
- The default sampler receives redacted projections before classification, which
  can be generic rather than the original domain type, even when values are
  unchanged. Custom sampling policies relying on domain interfaces must account
  for projections. Resolving execution kinds from projected child export IDs to
  business IDs remains an unresolved compatibility issue.
- The API checker recognizes built-in interface methods already implied by a
  baseline's inherited interfaces without reporting them as new obligations;
  genuinely added requirements remain compatibility breaks. The API baseline
  records the internal `VerifiedArtifactProjection` class and
  `ProvenanceAuthority::bindActivityRelationship()` method. No public contract
  interface or normalization schema changed for these additions.
- Publisher stack factories retain the three-argument `compose()` contract.
  Factories can implement `HealthAwarePublisherStackFactoryInterface` to receive
  the builder's health reporter; existing implementations need no changes. The
  previously baselined optional fourth `health` parameter has been removed from
  the interface: consumers passing it directly must use the health-aware interface
  instead. The API baseline update was approved; downstream migration is pending.
- A snapshot whose payload is redacted no longer exposes the original content
  hash. Classification can publish the redacted payload without a hash, but drops
  and reports a snapshot if it cannot preserve an accompanying hash and payload
  unchanged. Consumers must tolerate hashless redacted snapshots.
- Explicit redacted-observation drop reporting now suppresses recursive reports
  from the same publisher and resets its guard after reporter failures. The
  publisher is no longer declared `readonly`; reflection-based consumers may
  observe this change. Concurrent interleaving can suppress an independent
  report while another report is in progress.
- Provenance publication now binds artifact-specific issuance evidence to exact
  object instances through a `WeakMap`, replacing ID-only lookups that accepted
  forged copies. `ProvenanceArtifactPublisher` guards the publication boundary,
  dropping unverifiable artifacts with a payload-free health report while
  preserving the business outcome. Relationship edges are registered from
  attested execution and activity objects, not business-ID lookups. Duplicate
  live execution IDs are handled fail-open: telemetry is suppressed but the
  business operation still runs.
- Provenance records are bounded per execution; edges and identifier bytes have
  their own limits. Multiple live executions can exceed the per-execution record
  limit in aggregate. Exceeding a limit drops telemetry with a payload-free notice and
  preserves the business result. Release prunes incoming relationship edges from
  live records.
- `DefaultExecutionRunner` setup complexity was reduced by extracting
  `setupExecutionScope()` and `registerExecutionEdges()` helpers, bringing
  control-flow complexity below the architecture threshold.
- Typed publication now reconstructs classified executions, activities,
  measurements, occurrences, observations (including redacted observations via
  `ClassifiedRedactedObservation`), and snapshots solely from sanitized
  projected values. Typed reconstruction requires exact normalized equality;
  incomplete or unverifiable projections fall back to generic `ClassifiedArtifact`
  delivery. Activity duration (`duration_ns`) is preserved through
  classification. Unknown artifact kinds are dropped with a payload-free health
  report rather than bypassing classification bounds.
- `ProvenanceRetentionPolicy` was folded into `ProvenanceAuthority` as capacity
  limits with fail-open drop-and-report behavior. No standalone policy interface
  was added.
- Sampling-drop health reports now contain a fixed, payload-free placeholder
  rather than the rejected artifact; reporters relying on its identity or fields
  must migrate. Recursive and throwing reporters are contained. The sampling
  publisher is also no longer `readonly`, and its per-instance guard has the
  same concurrent-interleaving limitation.
- `TelemetryPublisher` now delivers redacted measurements through
  the optional `RedactedMeasurementInstrumentMapperInterface`. Custom mappers
  without this capability drop redacted measurements with a payload-free health
  report; their existing `MeasurementInstrumentMapperInterface` remains unchanged.
  The classified publisher stack reconstructs typed executions, activities,
  occurrences, measurements, observations, and snapshots when their projected
  fields can be represented without change. Other artifacts and incomplete
  projections remain generic; consumers relying on typed dispatch must account
  for those cases.
- `ExecutionSnapshotFactory` no longer constructs a default
  `ProvenanceAuthority`. The SDK shares its own authority with the factory;
  custom factories constructed without an authority never accumulate
  unreleased snapshot records. The runner validates and attests terminal
  snapshots from factories supplied via `withSnapshotFactory()` before
  publication; invalid ownership or identity is dropped and reported without
  affecting the business result.
- `ProvenanceAuthority::releaseExecution()` removes each record from the
  `byBusinessExecution` index directly via the record's business ID/execution
  ID key instead of scanning every bucket.
- Releasing provenance records also removes incoming relationship edges held by
  live executions.
- `DefaultExecutionRunner` rejects a repeated execution ID before any
  provenance record is issued via `hasLiveRecords()`, so two live executions
  can never share an ID and releasing one cannot drop the other's export
  bindings.
- `ClassifyingArtifactPublisher` truncates multibyte strings at a UTF-8
  character boundary using `mb_strcut()`, preventing invalid UTF-8 in projected
  values that would cause JSON encoders to throw.
- Sampling wraps the classifier in the default stack, so classification runs
  after sampling. Redaction wraps both and runs first.
- `HeadBasedSamplingPolicy` docblock aligned with the key derivation: the
  execution key comes from `ExecutionOwnedInterface::executionId()` when
  available, otherwise from the trusted `execution_id` field of
  `TrustedArtifactProjectionInterface` instances, and from any
  `RedactedArtifactInterface` instance as a fallback. User-supplied redacted
  artifacts that report another execution's `execution_id` can occupy that
  execution's decision cache.
- Fixed `tools/api-compat/ApiCompat::buildApiMap()` parent-skip filter: it
  previously skipped all inherited methods from non-internal declaring
  classes/interfaces, causing `api:check` to falsely report inherited interface
  methods (e.g. `ExtendedRunItBuilderInterface::build()`) as "Removed". It now
  skips methods inherited from internal PHP classes while retaining inherited
  built-in interface requirements such as `Countable::count()`. The corrected
  discovery can report requirements absent from an older API baseline; those
  differences require review before a baseline update.

### Provenance and Safe Publication (Phase 2)

- Introduces `ProvenanceAuthority` (SDK-internal, not a public contract) that
  mints opaque export IDs from controlled randomness and binds them to artifact
  kind, role, owning execution, and recorded relationships. Business-facing IDs
  from caller-supplied generators are preserved but are never treated as
  provenance; verification against the authority is needed to establish trust.
  The SDK publication boundary verifies issuance and drops unverified artifacts.
- Wires the authority through `RunItBuilder`, `DefaultExecutionRunner`,
  `ExecutionScope`, and all recorders. Execution parent/link edges, activity
  edges, observation/occurrence/measurement/snapshot edges, and terminal
  snapshot edges are registered through the authority. Unverified parent IDs in
  activity definitions are not trusted without a recorded edge.
- Adds `ClassifyingArtifactPublisher` after sampling in the publisher stack,
  creating immutable, bounded classified projections
  using an explicit per-kind field allowlist. Budgets: depth 16, 1,024 nodes,
  4,096 bytes per string, 1 MiB total, nested attribute map counts capped by
  `SecurityLimits::maxAttributeCount`. Objects, resources, and unknown fields
  are rejected; on exhaustion, optional fields are omitted, required fields
  cause drop-and-report. The classifier runs even when the deny-list redactor
  returns the artifact unchanged.
- Adds `TrustedArtifactProjectionInterface` (extends `RedactedArtifactInterface`)
  as a marker for SDK-created classified projections. Sampling predicates also
  accept unmarked `RedactedArtifactInterface` instances and may use their
  supplied type and normalized fields; the marker is not an admission boundary.
- `HeadBasedSamplingPolicy` derives its default execution key from
  `ExecutionOwnedInterface::executionId()` or the normalized `execution_id` of
  trusted projections and any redacted artifact. User-supplied redacted artifacts
  can therefore occupy another execution's decision cache. Artifacts without a
  nonempty key are sampled independently and do not occupy the cache.
- Measurements now receive an SDK-controlled artifact-specific export ID bound
  to the owning execution.
- `RedactedArtifact` does not implement `TrustedArtifactProjectionInterface`;
  user-created instances can specify their own artifact type.
- Health-aware publisher stack factories can receive an internal health reporter
  through a separate optional interface.
- Documents disclosure-path audit: publisher stack classification is covered by
  Phase 2; `DefaultSnapshotRecorder` store pass-through, HTTP middleware
  headers, CloudEvents import/export, and PSR-14 paths are out of scope for
  this phase.
- The maintainer approved the recent API changes and regenerated the API baseline
  on 2026-11-12. This includes the redacted-artifact type contract and redacted
  OpenTelemetry span mapper interface methods. Third-party implementations still
  need migration; approval does not establish downstream compatibility.
- Redaction truncation, explicit observation drops and OpenTelemetry unsafe-drop
  health notices no longer include artifact identifiers. OpenTelemetry unsafe
  drops also emit a fixed error-log message when no health reporter is configured.
  Health consumers can no longer correlate these notices using the original ID.
- Redacted artifacts now carry a trusted `artifactType()` signal set by the
  redactor (`RedactedArtifactInterface::artifactType()`), and the OpenTelemetry
  publisher routes redacted artifacts by that signal instead of inferring kind from
  user-controlled normalized fields. This is a deliberate contract addition; the
  regenerated API baseline records it.
- **Breaking:** `RedactedArtifactInterface` now requires `artifactType(): string`.
  Third-party implementations of the interface must add the method before upgrading
  or they will fatal-error. Adapters must route redacted artifacts by this trusted
  signal rather than inferring type from user-controlled normalized fields. Custom
  redactors constructing `RedactedArtifact` must pass an explicit artifact type:
  one-argument construction defaults to `'unknown'`, and OpenTelemetry reports and
  drops redacted artifacts whose type is not one of the recognized kinds rather
  than delivering them. Trusted-type dispatch is preserved; field-based inference
  is not reintroduced.
- Redacted measurements can be handled by a mapper implementing the optional
  `RedactedMeasurementInstrumentMapperInterface`. When the configured mapper
  lacks that interface, they are reported through the health path and dropped.
- Redaction traversal limits (16 levels, 1,024 nodes) that exceed the budget now
  report truncation through the health path as a bounded, payload-free notice.
- `ArtifactRedactionConfig::$redactObservationMessages` defaults to `true` (deny-by-default
  redaction: observation messages are masked unless explicitly opted out). Set
  `redactObservationMessages: false` as the explicit opt-in to publish raw observation
  messages; this is a breaking default change for consumers that previously relied on
  raw messages being exposed, since observation messages can carry secrets or PII.
- The default publication stack applies redaction before sampling and classification.
  Redactors can leave fields unchanged, so samplers must still treat their input as
  potentially sensitive.

- Bound redacted throwable cause traversal to 16 levels, replace traces with
  null, and omit unrecognized throwable fields and invalid class or code values.
  Consumers of redacted projections may observe these changed values. This does
  not yet secure arbitrary snapshot payloads or establish Phase 2 completion.
- Limit configured-key payload masking to 16 levels and 1,024 visited nodes;
  deeper or excess data is replaced or omitted, and truncation is reported through
  the health path as a bounded payload-free notice.
- Use existing normalized artifact field constants in redacted OpenTelemetry
  occurrence and snapshot mapping; normalized keys and exported schemas are unchanged.
- Correct redacted OpenTelemetry snapshot log timestamps to use the snapshot's
  `created_at` rather than the publication time when it is available as an integer.
- OpenTelemetry redacted snapshot logs and span events now honor
  `SnapshotPayloadPolicy::None` and omit the original content hash. Consumers
  relying on the previously leaked hash must explicitly choose a hash-enabled policy.
- Publish the completed execution before creating its terminal snapshot, so a
  snapshot factory failure no longer suppresses the terminal execution. Only an
  error escaping execution publication prevents the terminal snapshot attempt;
  an inner publisher error under the default `FailOpenPolicy` does not.
  Consumers may observe execution publication before the snapshot factory is
  invoked.
- Swoole HTTP request reporting passes observation arguments positionally, so
  custom recorders may use different parameter names without breaking requests.
- `CallbackHealthReporter` contains failures while extracting an artifact ID or
  invoking its fallback, and attempts a bounded, payload-free system-log notice
  before returning. A failed last-resort log cannot replace the business outcome.
- Corrected attribute collection PHPDoc to reflect existing support for bounded,
  recursively primitive arrays. Nested array members are represented as `mixed`
  in the static types; consumers must not assume every member is a scalar. This
  may require changes to downstream static-analysis assumptions. Runtime
  validation and native signatures are unchanged.
- Cloned composite context storages maintain separate token ownership; clones
  still share their configured child storages and detector.
- Composite context storage rejects foreign, forged, and previously detached
  wrapper tokens by identity while preserving retry after an out-of-order detach.
  Callers must detach using the exact token issued by the same composite instance.
- **Breaking:** `ContextTokenInterface` requires `id(): int`. Implementations
  must provide a stable storage-defined integer for the token's lifetime.
  The value need not be unique; storage implementations must continue to
  validate token ownership and detachment order independently. Consumers with
  custom tokens must implement the method before upgrading. This intentionally
  updates the `1.0.0-rc.1` API baseline, including the existing `id()` methods
  on the four first-party token classes.
- Established the M0 repository, quality-tooling, documentation, and CI
  foundation.
- Added the M1.1 base contracts under `src/Contract/Domain/`:
  - `NormalizableInterface` for deterministic, bounded normalization.
  - `StringIdentifierInterface` for immutable string-backed identifiers.
  - `ExecutionArtifactInterface` as the root marker for all semantic artifacts.
  - `ExecutionOwnedInterface` and `ActivityOwnedInterface` for ownership.
  - `TimestampedInterface`, `SequencedInterface`, and `AttributedInterface`.
  - Public exception-category marker interfaces under `src/Contract/Exception/`:
    `RunItExceptionInterface`, `InvalidValueExceptionInterface`,
    `LifecycleExceptionInterface`, and `UnsupportedOperationExceptionInterface`.
  - Contract-level unit tests validate determinism, value equality, ownership,
    primitive projections, and marker hierarchies.
  - Vocabulary: added the abstract `runit:Artifact` class to `vocab/run-it.ttl`
    as the common supertype of `Activity`, `Measurement`, `Observation`,
    `Occurrence`, and `Snapshot`, and corrected docblock `@see` links that pointed
    at undefined IRIs (`#artifact` → `#Artifact`, `#measurement` → `#Measurement`)
    or at external standards inside the RunIt namespace (`#CloudEvent` →
    <https://cloudevents.io/>, `#OpenTelemetry` → <https://opentelemetry.io/>).
    The kind/context-key value objects now also link to their SKOS schemes
    (`runit-tax:ActivityKinds`, `runit-tax:ExecutionKinds`,
    `runit-tax:ContextKeys`, `runit-tax:OccurrenceTypes`).

## Unreleased — Phase 0 (P0)

- Made `composer analyse` (PHPStan level max) pass by clearing the 5 genuine
  errors; the 15 `shipmonk.deadMethod` notices remain accepted noise and are
  triaged in Phase 1 (T1.2).
- Added `ItPack\RunIt\Contract\Shared\HttpExecutionContextInterface`, a public
  contract for `Adapter\Psr\Http\HttpExecutionContext` (closes the one real DIP
  leak flagged by `runit.requireInterface`). The concrete DTO keeps its public
  `readonly` properties for backward compatibility and implements the interface
  through matching accessors (`getName()`, `getKind()`, `getCorrelationId()`,
  `getContext()`, `getAttributes()`). `HttpContextExtractorInterface::extract()`
  and `Psr7RequestContextExtractor::extract()` now return the contract.
- Tightened `DefaultArtifactRedactor::maskKeys()` and `maskThrowable()` iterable
  types to `array<array-key, mixed>` so the security/redaction component satisfies
  level-max without `missingType.iterableValue`. Internal-only change.
- Regenerated `tools/api-compat/api-baseline.json` and
  `normalization-schema-baseline.json`; the additive public symbols and the
  covariant return widening on `Psr7RequestContextExtractor::extract()` introduce
  no backward-compatibility break.

## Unreleased — Phase 1 (P1)

- Documented `Contract\Shared\HttpExecutionContextInterface` as a first-class
  public contract: it is marked `@api` with a `@see https://w3id.org/itpack/runit#Execution`
  vocabulary link, and `docs/compatibility.md` records the addition and the
  backward-safe covariant `extract()` return.
- Triaged the 15 `shipmonk.deadMethod` notices. Every one is a deliberate public
  surface — 6 public interface/port contract methods and 9 symmetric fluent
  `with*` withers on the `readonly` `SecurityLimits` value object — so none were
  deleted (that would be a backward-compatibility break) and none are suppressed
  with `@phpstan-ignore`. They are enumerated as a keep-list in `docs/qa.md`;
  `composer analyse` therefore still reports exactly 15 errors, matching the
  documented kept set.
- Added `SDK\Builder\ExtendedRunItBuilderInterface`, an optional opt-in seam extending
  `RunItBuilderInterface` with `withConcurrencyValidator(?Closure)`. `RunItBuilder`
  implements it, so custom builder implementations are not required to add the method.
  The validator receives the engine capability and resolved context storage during
  `build()` and may throw to reject the pairing. `RunItBuilderInterface` itself does
  not declare `withConcurrencyValidator`, so external implementations are unaffected.
- Added built-in concurrency validation at `RunItBuilder::build()`: a shared
  `StackContextStorage` paired with an engine that declares `concurrent: true`
  now throws `IncompatibleContextStorageException`, identifying the pairing and
  directing the consumer to runtime-isolated storage (`FiberContextStorage`,
  `CoroutineContextStorage`, or another isolated storage). The built-in check
  runs before the opt-in validator and cannot be overridden.
- Added `Domain\Exception\IncompatibleContextStorageException`, a `@api`
  `LogicException` raised when a context storage is incompatible with the
  declared concurrency model. Token ownership semantics are unchanged: all
  `detach()` implementations use exact token reference, not `id()` alone.
- Added interface-backed execution definition factories and callable-wrapping
  components (additive, no backward-compatibility impact):
  - `Contract\Domain\ExecutionDefinitionFactoryInterface` and
    `Domain\Execution\ExecutionDefinitionFactory`: create domain executions
    from name, kind, optional parent, linked IDs, and context.
  - `SDK\Execution\MetadataExecutionDefinitionFactoryInterface` and
    `SDK\Execution\MetadataExecutionDefinitionFactory`: build an execution
    definition from an `ExecutedAsInterface` attribute, delegating to the
    domain factory (default-constructed when not injected).
  - `SDK\Attribute\CallableReflectionResolverInterface` and
    `SDK\Attribute\CallableReflectionResolver`: resolve a callable to its
    `ReflectionFunctionAbstract` for metadata reading; used by the default
    attribute reader, never on the invocation hot path.
  - `SDK\Attribute\ExecutedAsInterface`: interface-backed metadata carrier
    with `name` and `kind` read-only properties and a `DEFAULT_KIND`
    constant.
  - `SDK\Decorator\ExecutedCallableFactoryInterface` and
    `SDK\Decorator\ExecutedCallableFactory`: build `ExecutedCallableInterface`
    instances wrapping an operation with an optional definition and factory.
  - `SDK\Attribute\DefaultExecutedAsMetadataReader` now accepts an optional
    `CallableReflectionResolverInterface` constructor argument (defaults to
    `CallableReflectionResolver`); `SDK\DefaultRunIt` now accepts optional
    `ExecutedAsMetadataReaderInterface`, `ExecutedCallableFactoryInterface`,
    and `MetadataExecutionDefinitionFactoryInterface` constructor arguments
    (all defaulted).
- `CompositeContextStorage` now declares `__clone()`: cloning resets the
  issued-token WeakMap so token ownership stays local while the configured
  synchronous/Fiber/coroutine storages are shared.

## Unreleased — Phase 2 (P2)

 Phase 2 was analysed; the design was retained where unification would be unsafe, and the
 composition-root factory extraction was executed. Rationale per task:

 - **T2.1 (VO validation unification):** `ValidatedStringValueBehavior` validates with
   `StringValueValidator` and exposes `equals(StringValueInterface)`, so it only fits VOs
   implementing `StringValueInterface` (the existing 7 name/kind/type VOs). The candidates
   are not safely unifiable: `ContextKey`/`AttributeKey` use the **stricter**
   `ContextValueValidator`/`AttributeValidator` (adopting the trait would weaken validation —
   a security regression); `ContextSource` implements a different interface so the trait would
   change its `equals` contract; `ContextEntry`/`Occurrence`/`Snapshot` are complex non-string
   VOs; `ExecutionSnapshot` already injects its validator. No code change was made.
 - **T2.3 (factory extraction) — executed:** added `Contract/Port/PublisherStackFactoryInterface`
   and `Application/Factory/PublisherStackFactory` that compose redaction (closest to the base)
   then sampling around the configured base publisher, mirroring the previous inlined
   `RunItBuilder::build()` wiring. The builder now delegates to the factory; its `with*` seams
   and copy-on-write behavior are unchanged. `composer architecture`, `composer analyse`,
   `composer api:check`, and `composer test` (704 tests) are green.
 - **T2.2 (lazy objects):** deferred by analysis, not by the test gate (which runs green). It
   is a speculative optimization with no benchmark evidence of a construction-cost problem; the
   genuinely-expensive OpenTelemetry bridge is injected into the builder/runner, never constructed
   there, so lazy-init in the builder would defer no heavy work. Revisit only if a benchmark
   shows eager construction of rarely-used heavy infrastructure.
 - **T2.4 (property hooks):** deferred by analysis. The candidate projection getters
   (`isExportable`/`isPropagatable`/`isSerializable`, `isError`, `isTerminal`, `has`, `get`,
   `isEmpty`, `getIterator`) are **all declared in public interfaces**, so converting them to
   property hooks would break the contract (a hook is read as a property, not called as a method).
   Direct values already use `public readonly` properties, so there is no getter boilerplate to
   replace; no safe, non-BC-breaking conversion target exists.

All static gates remain green except reported `composer analyse` offset-access errors, `lint:length`
violations in new tests, and PHPStan errors already flagged prior to this change; none of the
new test files introduce additional violations beyond pre-existing ones.

- **Security redaction coverage extended:** `DefaultArtifactRedactor` now optionally masks
  `Observation::message` (`redactObservationMessages`, opt-in, default `false`),
  `Measurement::value` (`redactMeasurementValues`, opt-in, default `false`),
  `Occurrence::extension_attributes` by `sensitiveAttributeKeys`, and
  `Snapshot::attributes` by `sensitiveAttributeKeys` (additive; existing defaults unchanged).
  `ArtifactRedactionConfig` gained `redactObservationMessages` and `redactMeasurementValues`
  constructor arguments, both defaulting to `false` to preserve existing behavior.
  Configurations that previously relied on `extension_attributes` or snapshot `attributes`
  values being exported unchanged must now configure `DefaultArtifactRedactor` with
  `sensitiveAttributeKeys` naming those keys. `sensitiveAttributeKeys` defaults to empty,
  so behavior only changes for configurations that set it; keys named there are now also
  applied to `Occurrence::extension_attributes` and `Snapshot::attributes`. The OpenTelemetry
  `occurrenceLogAttributeAllowList` on `DefaultOccurrenceLogMapper` controls which keys
  are exported for unredacted occurrences; it does not restore extension attributes omitted
  by `mapRedactedOccurrence()`.
- **Redacted occurrence extension attributes no longer exported:** `DefaultOccurrenceLogMapper::mapRedactedOccurrence()`
  no longer exports `extension_attributes` from redacted occurrences to OTel span/log
  attributes, preventing secrets a custom redactor did not mask from reaching telemetry sinks.
  Consumers that depended on those fields should understand that `occurrenceLogAttributeAllowList`
  on `DefaultOccurrenceLogMapper` applies to unredacted occurrences only and does not restore
  extension attributes omitted by `mapRedactedOccurrence()`; redaction must be configured
  through `DefaultArtifactRedactor` instead.

- **Redacted occurrence extension attributes no longer exported:** `DefaultOccurrenceLogMapper::mapRedactedOccurrence()`
  no longer exports `extension_attributes` from redacted occurrences to OTel span/log
  attributes, preventing secrets a custom redactor did not mask from reaching telemetry sinks.
  Consumers that depended on those fields should understand that `occurrenceLogAttributeAllowList`
  on `DefaultOccurrenceLogMapper` applies to unredacted occurrences only and does not restore
  extension attributes omitted by `mapRedactedOccurrence()`; redaction must be configured
  through `DefaultArtifactRedactor` instead.

- **Redacted occurrence subject no longer exported:** `DefaultOccurrenceLogMapper::mapRedactedOccurrence()`
  no longer exports the unclassified `subject` (`ArtifactField::SUBJECT`) from redacted
  occurrences to OTel log records or span events. The subject is a caller-controlled,
  unclassified string and is deliberately omitted from the suppressed delivery.
  Consumers that depended on `runit.occurrence.subject` must not rely on it for redacted
  occurrences.

- **Redacted observations restricted to classified fields:** `DefaultObservationLogMapper::mapRedactedObservation()`
  now exports only identity and correlation fields (observation ID, execution ID, activity ID,
  diagnostic code) plus severity/message/throwable class. Free-form attributes are suppressed
  entirely, as they cannot be classified safe without redaction config. Consumers that
  depended on custom attributes in redacted observations will find them absent.

- **Drop-and-report for redacted observations:** `RedactingArtifactPublisher` gained an optional
  `InternalHealthReporterInterface` and a `dropRedactedObservations` constructor flag (default
  `false`). When enabled and redaction produces a `RedactedArtifactInterface` from an observation,
  the redacted observation is dropped and a bounded, payload-free identity notice is reported
  through the health channel instead of being published. A health reporter is required when
  dropping is enabled; the constructor throws otherwise. `ArtifactRedactionConfig` no longer
  carries a `dropRedactedObservations` property; the publisher's own constructor parameter
  is the control. `PublisherStackFactory` composition is unchanged.

- **Health reporter injection in TelemetryPublisher:** `TelemetryPublisher` and
  `TelemetryPublisher::fromConfig()` gained an optional `InternalHealthReporterInterface`
  parameter (default `null`, preserving compatible construction). When a redacted observation
  reaches an observation mapper without `RedactedObservationLogMapperInterface`, the
  observation is reported through the health channel and dropped (fail-open) instead of
  throwing `RuntimeException`. Only a bounded identity artifact (redacted `id` + `type`) is
  sent to the health reporter, capped at `StringIdentifierValidator::MAX_LENGTH` bytes, so
   no unredacted attribute payload leaks through the health channel. Health reporter failures
  are contained so the drop path stays fail-open. When no health reporter is supplied, the
  observation is dropped without a health report.

- **OpenTelemetry mapper interfaces extended (breaking):** `ExecutionSpanMapperInterface`
  and `ActivitySpanMapperInterface` now declare `mapRedactedExecution(array)` and
  `mapRedactedActivity(array)` respectively. External implementations of either interface
  must add these methods. `RunIt\Adapter\OpenTelemetry\Trace\DefaultExecutionSpanMapper`
  and `DefaultActivitySpanMapper` already implement them; custom implementations should
  delegate to those implementations or provide equivalent redacted-artifact mapping.

## Unreleased — Phase 3 (P3)

Phase 3 (polish) completed. All gates green (`composer cs:check`, `composer architecture`,
`composer api:check`, `composer analyse` at 15 accepted `shipmonk.deadMethod`, `composer test`
704 tests).

- **T3.1 (centralize redaction default):** added `DefaultContextRedactionPolicy::default()`
  (additive `@api` named constructor) and replaced both `new DefaultContextRedactionPolicy()`
  sites (in `ExecutionDefinition` and `Execution`) with it, so the default policy choice lives
  in one place. `composer api:baseline` regenerated.
- **T3.2 (complexity guard):** added `Tools\PhpStan\RunitRules\Rule\ComplexityGuardRule`, a
  `src/`-scoped rule counting branch-bearing nodes per method as a cognitive-complexity proxy,
  registered in `tools/phpstan/runit-rules/extension.neon` with threshold 19 (the current worst
  offender). It is a regression guard: nothing is flagged today, but newly-added over-complex
  methods are caught. Documented in `docs/qa.md`.
- **T3.3 (named constructors):** verified already satisfied. All five wide-constructor VOs
  already expose ergonomic named constructors (`CloudEvent::create()`, `Measurement::{counter,
  gauge, duration, size, ratio}()`, `Observation::{debug,info,notice,warning,error,critical,
  alert,emergency}()`, `Timeout::of()`, `SecurityLimits::default()` + `with*` withers), so no
  new builders were needed.
- **T3.4 (recorder `$clock`):** confirmed not a false positive in practice — PHPStan does not
  flag any recorder `$clock` (resolved via `InstantClockBehavior::now()`), and every recorder
  reads it when timestamping. No suppression or removal; documented in `docs/qa.md`.

## Unreleased — M1.2 identifiers

- Implemented the shared domain identifier primitives (milestone M1.2):
  - Six identifier contracts and immutable value objects: `ExecutionId`,
    `ActivityId`, `ObservationId`, `OccurrenceId`, `SnapshotId`, and
    `CorrelationId`, each `final`, `readonly`, and interface-backed.
  - `StringIdentifierValidator` enforcing structural rules: non-empty, bounded
    to 128 bytes, visible-ASCII only (no whitespace, control, or non-ASCII
    bytes), anchored so a trailing newline is rejected.
  - `InvalidIdentifierException` implementing `InvalidValueExceptionInterface`
    without echoing the rejected value.
  - `IdentifierGeneratorInterface` port and `StringIdentifierFactory` for
    pluggable, reflection-free generation bound to a specific identifier type.
  - Added vocabulary terms `runit:observationId` and `runit:correlationId`, and
    ADR-017 recording the opaque-string-with-pluggable-generation decision.
  - Added unit, validator, factory, and deterministic property-based tests.
  - Renamed the misspelled `ExecutionIdInterfacee` to `ExecutionIdInterface` and
    removed the speculative reflection-based `ImmutableStringIdentifierFactory`.

## Unreleased — M1.3 names and kinds

- Implemented the shared domain name and kind primitives (milestone M1.3):
  - Seven `final`, `readonly` value objects: `ExecutionName`, `ExecutionKind`,
    `ActivityName`, `ActivityKind`, `MeasurementName`, `OccurrenceType`, and
    `SnapshotType`, each interface-backed.
  - Shared `StringProviderInterface` / `StringValueInterface` contracts, the
    `StringValueValidator` (non-empty, ≤128 bytes, letters/digits/`.`/`-`/`_`
    only), `InvalidStringValueException`, and the `ValidatedStringValueBehavior`
    / `StringProviderBehavior` traits.
  - `OccurrenceType` enforces a stricter lowercase, dot-separated, versioned
    format (e.g. `itpack.configuration.loaded.v1`) and exposes `version()`.
  - ADR-018 records the decision to use extensible validated strings rather
    than closed enums; occurrence types stay low-cardinality and independent of
    PHP class names.
  - Added unit tests for every value object, occurrence-type format and version
    extraction, and validator boundaries.
## Unreleased — M1.4 time and sequencing

- Implemented the time and sequencing primitives (milestone M1.4):
  - Immutable `Instant` (Unix epoch seconds, no timezone) and `Duration`
    (integer nanoseconds, never negative) value objects in `Domain/Shared`.
  - Immutable `ArtifactSequence` and the `SequenceAllocatorInterface` port, with
    `InMemorySequenceAllocator` (per-execution monotonic counter, first
    allocation returns zero).
  - `MonotonicClockInterface` port keeping wall-clock seconds and monotonic
    nanoseconds separate, with `SystemMonotonicClock` (`time()` + `hrtime()`)
    and a `DeterministicMonotonicClock` test double.
  - ADR-019 records the monotonic-time / integer-nanosecond / immutable-sequence
    decision; `runit:instant` was added to the vocabulary.
  - Added unit tests for instant/duration conversion and rejection of negatives,
    increasing per-execution sequence allocation, and wall/monotonic separation.
