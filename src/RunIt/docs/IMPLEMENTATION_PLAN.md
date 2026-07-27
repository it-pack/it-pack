# RunIt Implementation Plan

## 1. Executive Summary

**RunIt** (`it-pack/run-it`) will be the semantic, architectural, and operational backbone of the ItPack Execution Model.

The package will provide:

- A vendor-neutral execution domain model.
- Immutable artifacts for context, activities, measurements, observations, occurrences, and snapshots.
- A lifecycle-safe execution runner.
- Explicit and ambient context propagation.
- Programmatic and SDK-level APIs.
- Non-intrusive decorators, middleware, and adapters.
- Streaming artifact publication without requiring in-memory retention.
- Optional OpenTelemetry, CloudEvents, PSR, Fiber, and Swoole integrations.
- Framework-independent contracts suitable for ItPack packages and third-party libraries.
- Strong architectural governance, observability, testing, security, and performance controls.
- Self-instrumentation so RunIt itself adheres to the ItPack Execution Model without creating recursive telemetry.

The core rule is:

> **RunIt owns execution semantics. External standards only represent or transport those semantics.**

---

## 2. Required Decisions and Missing Inputs

The following business constraints were not provided and must be ratified before a stable `1.0.0` release:

| Missing input | Why it matters | Proposed working assumption |
|---|---|---|
| Delivery start date | Required for calendar milestones | Use the relative 12-week plan below |
| Available team | Controls parallel delivery | Two senior PHP engineers plus one part-time architect |
| Supported PHP versions | Determines language/API design | PHP `^8.4` |
| Framework compatibility | Determines official bridge priorities | Framework-agnostic core; PSR first |
| Latency budget | Determines hot-path design | Provisional targets in §5 |
| Export delivery guarantees | Determines buffering/outbox design | At-most-once default; durable delivery delegated to adapters |
| Snapshot persistence requirements | Determines storage contracts | Core defines ports only |
| Backward-compatibility policy | Determines release governance | Semantic Versioning with strict public API control |
| Data classification policy | Determines redaction defaults | Deny sensitive context export by default |
| Required OpenTelemetry exporters | Determines integration matrix | OTLP delegated to the OpenTelemetry SDK |
| Required CloudEvents bindings | Determines transport adapters | Structured JSON and HTTP mapping first |

No vendor-specific infrastructure, queue, database, framework, or telemetry backend should be assumed by the core package.

---

## 3. Business Goals

### 3.1 Primary goals

1. **Shared execution vocabulary**
    - All ItPack packages model runtime work with the same semantics.
    - Execution artifacts can be correlated across package boundaries.

2. **Low-friction adoption**
    - Consumers can instrument one callable without changing domain code.
    - Adoption can begin with a null or in-memory runtime.
    - Existing PSR, OpenTelemetry, and event infrastructure remains usable.

3. **Vendor neutrality**
    - Domain code must not depend on OpenTelemetry, CloudEvents, PSR-3, Swoole, or a framework.
    - External standards are implemented behind adapters.

4. **Operational consistency**
    - Activities, measurements, observations, occurrences, failures, and snapshots follow one lifecycle.
    - Correlation identifiers and context propagate consistently.

5. **Library-grade stability**
    - Public contracts remain small and intentional.
    - No service locator, global mutable registry, mandatory container, or framework bootstrapping is introduced.

6. **Safe observability**
    - Instrumentation must not accidentally expose credentials, secrets, personal data, or unbounded values.
    - Telemetry failures must follow an explicit failure policy.

### 3.2 Non-goals

RunIt will not become:

- A workflow engine.
- A distributed scheduler.
- A queue implementation.
- A logging implementation.
- A metrics database.
- A tracing backend.
- A dependency injection container.
- A general-purpose promise library.
- An event-sourcing framework.
- An OpenTelemetry abstraction that merely renames spans.
- A mandatory base class or trait consumers must inherit from.

---

## 4. Architectural Principles

1. **Execution is the aggregate boundary.**
2. **Context is immutable.**
3. **Artifacts are immutable facts or descriptions.**
4. **Runtime sessions coordinate lifecycle but do not redefine the domain.**
5. **Ownership is expressed through identity and relationships, not mandatory in-memory collections.**
6. **Explicit context propagation is canonical; ambient context is an ergonomic adapter.**
7. **Business behavior must remain independent of telemetry success.**
8. **Adapters depend inward; the domain never depends outward.**
9. **Composition is preferred over inheritance.**
10. **Every concrete class is `final` and backed by a public or internal interface.**
11. **Classes are `readonly` whenever lifecycle coordination does not require controlled mutation.**
12. **SDK conveniences delegate to the same application ports used by advanced consumers.**
13. **Core behavior must work without network, filesystem, extension, or framework dependencies.**
14. **RunIt must dogfood its own execution model without recursively instrumenting artifact emission.**

---

## 5. Measurable Success Criteria

These are provisional acceptance targets pending production workloads and benchmark hardware.

### 5.1 Functional criteria

- 100% of execution artifacts contain an `ExecutionId`.
- 100% of activity-bound artifacts contain an `ActivityId`.
- Nested activities preserve parent-child ordering.
- Every completed execution has exactly one terminal status.
- Every terminal execution can produce an `ExecutionSnapshot`.
- Every thrown `Throwable` results in a failed activity or execution unless explicitly mapped to cancellation.
- An adapter failure cannot replace the original business exception.
- All public domain records support deterministic normalization.
- OpenTelemetry and CloudEvents adapters pass their respective contract tests.

### 5.2 Performance criteria

Measure using warmed PHP 8.4 processes, JIT disabled and enabled, with both short-lived and persistent workers.

| Operation | Provisional target |
|---|---:|
| Disabled/null execution wrapper overhead | p95 ≤ 3 μs |
| In-memory execution start and completion | p95 ≤ 20 μs |
| Activity start and completion | p95 ≤ 12 μs |
| Measurement/observation creation | p95 ≤ 8 μs |
| Null runtime allocation growth | ≤ 2 KB per execution |
| Sustained in-memory artifact publication | ≥ 50,000 artifacts/second/process |
| SDK facade overhead over direct application API | ≤ 10% |
| Memory retained after persistent-worker execution | No statistically significant linear growth |

Exporter network latency is excluded because exporters are adapters outside the core hot path.

### 5.3 Reliability criteria

- Core execution completion availability: effectively process-local, with no external availability dependency.
- No artifact publisher may block indefinitely.
- Adapter timeouts and capacity limits are configurable.
- Fail-open publication is the default.
- Fail-closed publication requires explicit configuration.
- Persistent workers must demonstrate stable memory across at least 100,000 sequential executions.
- Concurrent Fiber/coroutine tests must demonstrate zero context leakage.

### 5.4 Quality criteria

- PHPStan level `max`/highest available level, with no baseline for first-party source.
- Zero unapproved DepTrac violations.
- Minimum line coverage: 90%.
- Minimum branch coverage: 85%.
- Minimum covered-code mutation score: 85%.
- Public contract mutation score: 90%.
- Zero high or critical dependency vulnerabilities at release.
- All examples compile and execute in CI.

PHPStan currently supports levels through 10 and provides `max` as the moving highest-level alias. New first-party packages should avoid introducing a baseline because the baseline is principally an incremental adoption mechanism for existing violations. ([phpstan.org](https://phpstan.org/user-guide/baseline?utm_source=openai))

---

## 6. Semantic Model

## 6.1 Execution

An execution represents one logical runtime operation.

Required fields:

- `ExecutionId`
- `ExecutionName`
- `ExecutionKind`
- `ExecutionStatus`
- `ExecutionContext`
- Start time
- Optional end time
- Optional parent execution reference
- Optional distributed correlation reference
- Monotonic artifact sequence
- Optional result classification
- Optional failure description

Suggested execution kinds:

```text
http.request
cli.command
queue.job
configuration.resolution
workflow
worker.iteration
scheduled.task
build
migration
event.processing
llm.pipeline
custom
```

Execution kinds must be extensible strings represented by a validated value object, not a closed enum.

### Execution state machine

```text
CREATED
   │
   ▼
RUNNING ───────────────► CANCELLED
   │                         │
   ├────────► SUCCEEDED      │
   ├────────► FAILED         │
   └────────► TIMED_OUT      │
                             ▼
                         TERMINAL
```

Rules:

- `CREATED` can transition only to `RUNNING` or `CANCELLED`.
- `RUNNING` can transition only to a terminal state.
- Terminal states are immutable.
- Completion is idempotent at the coordinator boundary.
- Conflicting repeated completion attempts raise an internal lifecycle violation.
- Lifecycle violations must not corrupt the original result or exception.

## 6.2 Execution Context

`ExecutionContextInterface` represents immutable execution-scoped state.

Recommended standard keys:

```text
environment
tenant.id
workspace.id
locale
principal.id
principal.type
correlation.id
trace.id
feature_flags
configuration.snapshot_id
service.name
service.version
deployment.environment
```

Context values must include metadata:

- Key.
- Value.
- Value type.
- Classification.
- Propagation policy.
- Serialization policy.
- Optional expiry.
- Optional source.

Classifications:

```text
PUBLIC
INTERNAL
CONFIDENTIAL
RESTRICTED
SECRET
```

Default export rules:

- `PUBLIC`, `INTERNAL`: exportable.
- `CONFIDENTIAL`: exportable only through policy.
- `RESTRICTED`: redacted by default.
- `SECRET`: never exported.

Context mutation is implemented as copy-on-write:

```php
$childContext = $context
    ->with(ContextEntry::internal('tenant.id', $tenantId))
    ->without('legacy.flag');
```

## 6.3 Activity

An activity is a bounded unit of work inside an execution.

Required fields:

- `ActivityId`
- `ExecutionId`
- `ActivityName`
- `ActivityKind`
- Optional parent `ActivityId`
- Start and optional end time
- Status
- Sequence
- Attributes
- Optional failure description

Activities are nested by identity, not object ownership. This permits streaming, remote propagation, and low-memory execution.

Activity lifecycle mirrors execution lifecycle but does not include `CREATED` as a publicly observable state:

```text
RUNNING → SUCCEEDED | FAILED | CANCELLED | TIMED_OUT
```

## 6.4 Measurement

A measurement is quantitative.

Required fields:

- Name.
- Numeric value.
- Unit.
- Measurement kind.
- Execution identity.
- Optional activity identity.
- Timestamp.
- Attributes.

Measurement kinds:

```text
counter
up_down_counter
gauge
histogram_sample
duration
size
ratio
custom
```

Examples:

```text
files.loaded = 14 {file}
duration = 18.7 {ms}
memory.peak = 12_582_912 {By}
graph.nodes = 184 {node}
validation.errors = 3 {error}
```

Units should follow OpenTelemetry-compatible conventions where practical, but RunIt retains its own domain type.

## 6.5 Observation

An observation is qualitative information noticed during execution.

Required fields:

- Observation ID.
- Severity.
- Message template or diagnostic code.
- Structured attributes.
- Execution/activity identity.
- Timestamp.
- Optional `ThrowableDescription`.

Severities:

```text
TRACE
DEBUG
INFO
NOTICE
WARNING
ERROR
CRITICAL
ALERT
EMERGENCY
```

Observation messages should remain stable templates, with variable information held in attributes. This aligns cleanly with PSR-3’s message/context model. ([php-fig.org](https://www.php-fig.org/psr/psr-3/?utm_source=openai))

## 6.6 Occurrence

An occurrence is an immutable fact.

Required fields:

- Occurrence ID.
- Occurrence type.
- Source.
- Subject.
- Time.
- Data.
- Data schema identifier.
- Content type.
- Execution/activity identity.
- Sequence.
- Extension attributes.

Examples:

```text
itpack.configuration.loaded.v1
itpack.schema.validated.v1
itpack.snapshot.created.v1
itpack.pipeline.completed.v1
```

Occurrence type names must be:

- Globally stable.
- Lowercase.
- Versioned.
- Independent of PHP class names.
- Suitable for CloudEvents `type` mapping.

CloudEvents defines common context attributes independently of event data, making it appropriate as an external representation of RunIt occurrences rather than as the occurrence domain itself. ([github.com](https://github.com/cloudevents/spec?utm_source=openai))

## 6.7 Snapshot

A snapshot is immutable resulting state.

Snapshot fields:

- Snapshot ID.
- Snapshot type.
- Schema version.
- Execution identity.
- Optional activity identity.
- Creation time.
- State payload.
- Content type.
- Hash.
- Optional predecessor.
- `checkpoint` or `terminal` classification.
- Metadata.

Snapshots must not require arbitrary PHP object serialization. Payloads must be normalized through an injected serializer.

---

## 7. Hexagonal Architecture

```text
┌──────────────────────────────────────────────────────────┐
│ SDK / Consumer Adapters                                  │
│ Callable, PSR-15, CLI, Queue, Framework, Attributes      │
├──────────────────────────────────────────────────────────┤
│ Application Layer                                        │
│ Runner, Scope, Lifecycle, Policies, Publication           │
├──────────────────────────────────────────────────────────┤
│ Domain Layer                                             │
│ Execution, Context, Activity, Artifact value objects      │
├──────────────────────────────────────────────────────────┤
│ Ports                                                    │
│ Clock, IDs, Context Storage, Publisher, Serializer,       │
│ Redactor, Sampler, Snapshot Store, Execution Engine       │
├──────────────────────────────────────────────────────────┤
│ Infrastructure Adapters                                  │
│ In-memory, Null, OTel, CloudEvents, PSR, Fiber, Swoole    │
└──────────────────────────────────────────────────────────┘
```

### Dependency direction

```text
Adapter → Application → Domain
   │           │
   └──────────► Ports ◄──────── Infrastructure implementation
```

The domain layer must have no references to:

- OpenTelemetry.
- CloudEvents.
- PSR interfaces.
- Swoole/OpenSwoole.
- Framework classes.
- Filesystems.
- Network clients.
- Containers.

---

## 8. Package and Namespace Structure

```text
it-pack/run-it
├── src
│   ├── Contract
│   │   ├── Domain
│   │   ├── Application
│   │   ├── Port
│   │   └── Exception
│   ├── Domain
│   │   ├── Execution
│   │   ├── Context
│   │   ├── Activity
│   │   ├── Measurement
│   │   ├── Observation
│   │   ├── Occurrence
│   │   ├── Snapshot
│   │   └── Shared
│   ├── Application
│   │   ├── Runner
│   │   ├── Scope
│   │   ├── Lifecycle
│   │   ├── Publication
│   │   ├── Propagation
│   │   └── Policy
│   ├── Adapter
│   │   ├── Common
│   │   ├── Memory
│   │   ├── Null
│   │   ├── Callback
│   │   ├── Fiber
│   │   ├── OpenTelemetry
│   │   ├── CloudEvents
│   │   ├── Psr
│   │   └── Swoole
│   └── SDK
│       ├── Builder
│       ├── Decorator
│       ├── Middleware
│       └── Attribute
├── tests
│   ├── Unit
│   ├── Contract
│   ├── Integration
│   ├── Concurrency
│   ├── Compatibility
│   ├── Architecture
│   └── Performance
├── examples
├── benchmarks
├── docs
│   ├── architecture
│   ├── concepts
│   ├── integrations
│   ├── recipes
│   └── adr
├── composer.json
├── deptrac.yaml
├── phpstan.neon.dist
├── infection.json5.dist
└── .php-cs-fixer.dist.php
```

Namespace root:

```php
ItPack\RunIt
```

Composer should use package type `library` and PSR-4 autoloading, both of which are standard Composer mechanisms for reusable packages. ([getcomposer.org](https://getcomposer.org/doc/01-basic-usage.md?utm_source=openai))

---

## 9. Core Contracts

## 9.1 Domain contracts

```php
interface ExecutionInterface extends ExecutionArtifactOwnerInterface {}
interface ExecutionContextInterface {}
interface ContextEntryInterface {}
interface ActivityInterface extends ExecutionArtifactInterface {}
interface MeasurementInterface extends ExecutionArtifactInterface {}
interface ObservationInterface extends ExecutionArtifactInterface {}
interface OccurrenceInterface extends ExecutionArtifactInterface {}
interface SnapshotInterface extends ExecutionArtifactInterface {}
interface ExecutionArtifactInterface extends NormalizableInterface {}
interface ExecutionSnapshotInterface extends SnapshotInterface {}
```

Identifiers should expose dedicated interfaces:

```php
interface ExecutionIdInterface extends StringIdentifierInterface {}
interface ActivityIdInterface extends StringIdentifierInterface {}
interface ObservationIdInterface extends StringIdentifierInterface {}
interface OccurrenceIdInterface extends StringIdentifierInterface {}
interface SnapshotIdInterface extends StringIdentifierInterface {}
```

Concrete examples:

```php
final readonly class ExecutionId implements ExecutionIdInterface {}
final readonly class ExecutionContext implements ExecutionContextInterface {}
final readonly class Activity implements ActivityInterface {}
final readonly class Measurement implements MeasurementInterface {}
final readonly class Observation implements ObservationInterface {}
final readonly class Occurrence implements OccurrenceInterface {}
final readonly class ExecutionSnapshot implements ExecutionSnapshotInterface {}
```

Enums may implement semantic interfaces:

```php
enum ExecutionStatus: string implements ExecutionStatusInterface
{
    case Created = 'created';
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case TimedOut = 'timed_out';
}
```

## 9.2 Application contracts

```php
interface ExecutionRunnerInterface
{
    /**
     * @template TResult
     * @param callable(ExecutionScopeInterface): TResult $operation
     * @return TResult
     */
    public function run(
        ExecutionDefinitionInterface $definition,
        callable $operation,
    ): mixed;
}
```

```php
interface ExecutionScopeInterface
{
    public function execution(): ExecutionInterface;

    public function context(): ExecutionContextInterface;

    public function activities(): ActivityRunnerInterface;

    public function measurements(): MeasurementRecorderInterface;

    public function observations(): ObservationRecorderInterface;

    public function occurrences(): OccurrenceRecorderInterface;

    public function snapshots(): SnapshotRecorderInterface;
}
```

```php
interface ActivityRunnerInterface
{
    /**
     * @template TResult
     * @param callable(ActivityScopeInterface): TResult $operation
     * @return TResult
     */
    public function run(
        ActivityDefinitionInterface $definition,
        callable $operation,
    ): mixed;
}
```

Keep recorders segregated to honor interface-segregation:

```php
interface MeasurementRecorderInterface {}
interface ObservationRecorderInterface {}
interface OccurrenceRecorderInterface {}
interface SnapshotRecorderInterface {}
```

## 9.3 Infrastructure ports

```php
interface ArtifactPublisherInterface
{
    public function publish(ExecutionArtifactInterface $artifact): void;
}

interface ContextStorageInterface
{
    public function current(): ?ExecutionScopeInterface;

    public function attach(ExecutionScopeInterface $scope): ContextTokenInterface;

    public function detach(ContextTokenInterface $token): void;
}

interface IdGeneratorInterface {}
interface ClockInterface {}
interface MonotonicClockInterface {}
interface SerializerInterface {}
interface RedactorInterface {}
interface SamplingPolicyInterface {}
interface PublicationFailurePolicyInterface {}
interface SnapshotStoreInterface {}
interface ExecutionEngineInterface {}
interface ThrowableMapperInterface {}
interface ResourceDetectorInterface {}
```

Prefer `Psr\Clock\ClockInterface` through a bridge or alias adapter rather than coupling domain value objects directly to PSR-20.

---

## 10. Runtime Coordination

## 10.1 `ExecutionRunner`

`DefaultExecutionRunner` is the primary application service.

Execution algorithm:

1. Validate the definition.
2. Obtain execution, correlation, and parent identities.
3. Create immutable effective context.
4. Create the running execution record.
5. Attach an `ExecutionScope` to context storage.
6. Publish execution-started lifecycle artifact.
7. Invoke the operation.
8. On success:
    - Record result metadata.
    - Close open activities according to policy.
    - Produce terminal snapshot.
    - Publish success.
9. On `Throwable`:
    - Map the throwable to a serializable failure description.
    - Record an error observation.
    - Close active activities as failed.
    - Produce failed terminal snapshot.
    - Publish failure.
    - Rethrow the original throwable.
10. In `finally`:
    - Detach the exact context token.
    - Flush only when policy requires it.
    - Never replace the original exception with a publication exception.

## 10.2 Reentrancy and nested executions

Nested `run()` calls create child executions by default.

Configuration options:

- `ChildExecution`: new execution with parent reference.
- `ActivityOnly`: represent nested invocation as an activity.
- `DetachedExecution`: new root with a link to the current execution.
- `RejectNested`: throw a lifecycle exception.

Default: `ChildExecution`.

## 10.3 Open activity handling

At execution completion, open activities are handled by policy:

```text
FAIL_EXECUTION
AUTO_FAIL_ACTIVITIES
AUTO_CANCEL_ACTIVITIES
IGNORE_WITH_WARNING
```

Default: `AUTO_FAIL_ACTIVITIES`, plus an internal warning observation.

---

## 11. Programmatic and SDK API

## 11.1 Canonical API

```php
$result = $runner->run(
    ExecutionDefinition::named('configuration.resolve')
        ->ofKind('configuration.resolution')
        ->withContext($context),
    static function (ExecutionScopeInterface $execution) use ($resolver): Config {
        return $execution->activities()->run(
            ActivityDefinition::named('configuration.load-yaml'),
            static function (ActivityScopeInterface $activity) use ($resolver): Config {
                $config = $resolver->resolve();

                $activity->measurements()->record(
                    Measurement::counter('files.loaded', $config->fileCount(), 'file'),
                );

                $activity->occurrences()->record(
                    Occurrence::named('itpack.configuration.loaded.v1')
                        ->withData(['snapshot_id' => $config->snapshotId()]),
                );

                return $config;
            },
        );
    },
);
```

## 11.2 Fluent immutable SDK

```php
$runIt = RunItBuilder::create()
    ->withClock($clock)
    ->withIdGenerator($ids)
    ->withPublisher($publisher)
    ->withContextStorage($contextStorage)
    ->withRedactionPolicy($redactionPolicy)
    ->build();
```

Builders must:

- Be immutable.
- Return `static`/`self` from `with*` methods.
- Validate only at `build()` unless a value is intrinsically invalid.
- Produce interfaces.
- Avoid container assumptions.

## 11.3 Non-intrusive callable decorator

```php
$instrumented = $runIt->decorateCallable(
    name: 'configuration.resolve',
    operation: $resolver->resolve(...),
);

$config = $instrumented();
```

## 11.4 Object decorator

Provide focused decorators around consumer-defined interfaces:

```php
final readonly class InstrumentedConfigurationResolver
    implements ConfigurationResolverInterface
{
    public function __construct(
        private ConfigurationResolverInterface $inner,
        private ExecutionRunnerInterface $runner,
    ) {}
}
```

RunIt must not create generic reflection proxies as its primary design. Generated proxies may be offered later as tooling.

## 11.5 Attribute metadata

Optional metadata attributes:

```php
#[ExecutedAs(
    name: 'configuration.resolve',
    kind: 'configuration.resolution',
)]
```

Attributes contain metadata only. Invocation remains the responsibility of a decorator, proxy factory, framework bridge, or SDK invoker.

---

## 12. Context Propagation

## 12.1 Explicit propagation

Explicit `ExecutionScopeInterface` arguments are the canonical mechanism.

Advantages:

- Visible dependencies.
- Straightforward testing.
- No runtime lookup.
- Safe across execution models.
- No hidden coupling.

## 12.2 Ambient propagation

Ambient access is provided through injected `CurrentExecutionInterface`:

```php
interface CurrentExecutionInterface
{
    public function get(): ?ExecutionScopeInterface;

    public function require(): ExecutionScopeInterface;
}
```

Do not expose a mutable static facade.

## 12.3 Context storage adapters

Implement:

1. `StackContextStorage`
    - Default for conventional synchronous PHP.
2. `FiberContextStorage`
    - Fiber-local execution scope.
3. `SwooleCoroutineContextStorage`
    - Coroutine-local scope.
4. `CompositeContextStorage`
    - Selects storage based on the active runtime.
5. `NullContextStorage`
    - For disabled instrumentation.

OpenTelemetry PHP supports Fiber-aware context storage, while enabling it may require FFI and preload configuration in some environments. RunIt must therefore keep Fiber integration optional and independently testable. ([opentelemetry.io](https://opentelemetry.io/docs/languages/php/?utm_source=openai))

## 12.4 `WeakMap` and weak references

Use `WeakMap` for associating:

- Fiber objects with context stacks.
- Consumer objects with generated decorators.
- Runtime handles with lifecycle metadata.

Use `WeakReference` only for non-owning caches or parent runtime references. Execution correctness must never depend on a weakly referenced object remaining alive.

---

## 13. Artifact Publication

## 13.1 Publisher pipeline

```text
Domain artifact
   ↓
Validation
   ↓
Enrichment
   ↓
Classification
   ↓
Redaction
   ↓
Sampling
   ↓
Routing
   ↓
Adapter publisher(s)
```

Composable publishers:

```text
NullArtifactPublisher
InMemoryArtifactPublisher
CallbackArtifactPublisher
CompositeArtifactPublisher
FilteringArtifactPublisher
SamplingArtifactPublisher
RedactingArtifactPublisher
BufferedArtifactPublisher
FailSafeArtifactPublisher
RecordingArtifactPublisher
```

Every publisher is `final`, interface-backed, and composed rather than inherited.

## 13.2 Failure policies

```text
FAIL_OPEN
FAIL_CLOSED
COLLECT_AND_CONTINUE
REPORT_TO_FALLBACK
```

Default: `REPORT_TO_FALLBACK`, falling back to `FAIL_OPEN`.

Rules:

- The business result or original exception has priority.
- Publication exceptions are captured separately.
- Recursive publication is prohibited.
- Fallback reporting must use a recursion guard.
- Security policy failures may be configured as fail-closed.

## 13.3 Backpressure

`BufferedArtifactPublisher` must support:

- Maximum item count.
- Maximum estimated byte count.
- Maximum wait duration.
- Overflow strategy.
- Batch size.
- Flush interval.
- Shutdown flush budget.

Overflow strategies:

```text
DROP_NEWEST
DROP_OLDEST
DROP_LOW_PRIORITY
BLOCK_WITH_TIMEOUT
FAIL
```

Dropped artifacts must be counted through an internal, recursion-safe health counter.

---

## 14. OpenTelemetry Adapter

OpenTelemetry remains an adapter rather than the core model. The PHP implementation provides stable traces, metrics, and logs and recommends that libraries depend on API contracts while applications install and configure SDK/exporter components. ([opentelemetry.io](https://opentelemetry.io/docs/languages/php/?utm_source=openai))

### 14.1 Mapping

| RunIt | OpenTelemetry |
|---|---|
| Execution | Root span or trace boundary |
| Execution context | OTel context, baggage, resource attributes |
| Activity | Span |
| Measurement | Metric instrument recording |
| Observation | Log record and optionally span event |
| Occurrence | Span event, log record, or linked event |
| Failure | Exception recording and span status |
| Snapshot | Reference/hash only; payload export is policy-controlled |

### 14.2 Adapter components

```text
OpenTelemetryArtifactPublisher
OpenTelemetryExecutionMapper
OpenTelemetryActivityMapper
OpenTelemetryMeasurementMapper
OpenTelemetryObservationMapper
OpenTelemetryOccurrenceMapper
OpenTelemetryContextPropagator
OpenTelemetryResourceMapper
OpenTelemetryAttributeSanitizer
```

### 14.3 Dependency policy

The core package must not require an OpenTelemetry SDK.

Options:

- `suggest` the required OpenTelemetry API packages.
- Load bridge classes only when explicitly requested.
- Test the bridge with installed API and SDK dependencies in a separate CI matrix.
- Never configure global OTel providers from library code.
- Never force an exporter or collector endpoint.

### 14.4 Cardinality controls

- Context keys require allow-listing before metric-label mapping.
- Arbitrary tenant, user, file, URL, error-message, or snapshot values must not become metric attributes.
- High-cardinality values may remain trace/log attributes under redaction policy.
- Measurements must define instrument name, unit, and aggregation intent.

---

## 15. CloudEvents Adapter

Target `cloudevents/sdk-php` while retaining an internal mapper contract so SDK replacement does not affect domain code. The PHP SDK is distributed through Composer, and the CloudEvents specification remains the source of semantic conformance. ([packagist.org](https://packagist.org/packages/cloudevents/?utm_source=openai))

### 15.1 Mapping

| RunIt occurrence | CloudEvent |
|---|---|
| Occurrence ID | `id` |
| Occurrence type | `type` |
| Source | `source` |
| Subject | `subject` |
| Time | `time` |
| Content type | `datacontenttype` |
| Data schema | `dataschema` |
| Data | `data` |
| Execution ID | `executionid` extension |
| Activity ID | `activityid` extension |
| Correlation ID | `correlationid` extension |
| Sequence | `executionsequence` extension |

### 15.2 Adapter components

```text
CloudEventFactoryInterface
CloudEventsOccurrenceMapper
CloudEventsArtifactPublisher
CloudEventsSerializer
CloudEventsHttpBindingAdapter
CloudEventsDeserializer
OccurrenceFactoryFromCloudEvent
```

### 15.3 Requirements

- Support structured JSON first.
- Validate required CloudEvents fields.
- Preserve unknown extension attributes.
- Reject conflicting duplicate context attributes.
- Make data serialization explicit.
- Avoid embedding unrestricted context.
- Include schema versions in occurrence types or `dataschema`.
- Provide bidirectional contract tests.

---

## 16. PSR Adapters

## 16.1 PSR-3

Provide:

- `ObservationLogger`: converts RunIt observations to PSR-3 records.
- `ExecutionAwareLogger`: decorates a PSR-3 logger and adds execution correlation.
- `PsrLoggerObservationRecorder`: permits existing logging calls to produce observations.
- `ObservationLevelMapper`.

Do not turn every log record into an occurrence.

## 16.2 PSR-14

Provide:

- `OccurrenceEventDispatcherPublisher`.
- `PsrEventOccurrenceAdapter`.
- `OccurrenceFromPsrEventFactory`.

PSR-14 dispatch is synchronous and listener exceptions propagate, so the adapter must apply RunIt’s configured publication failure policy around dispatch. ([php-fig.org](https://www.php-fig.org/psr/psr-14/?utm_source=openai))

## 16.3 Additional PSR bridges

Prioritize:

- PSR-20 clock adapter.
- PSR-15 HTTP middleware.
- PSR-7 context extraction.
- PSR-18 propagation-aware HTTP client decorator.
- PSR-11 factory integration in a separate bridge, not the core domain.

---

## 17. Swoole/OpenSwoole and Fiber Execution

## 17.1 Scope

RunIt should not become a coroutine scheduler. It should expose execution-engine and context-storage ports that permit coroutine-aware operation.

## 17.2 Swoole adapters

```text
SwooleExecutionEngine
SwooleCoroutineContextStorage
SwooleTaskDispatcher
SwooleExecutionServerMiddleware
SwooleWorkerLifecycleObserver
```

The adapter must:

- Detect active coroutine context.
- Store scope by coroutine identity.
- Clear context after every callback.
- Avoid static request state.
- Reset buffered publishers between requests where required.
- Prevent execution data from leaking between persistent requests.
- Support worker-start and worker-stop executions separately from request executions.

OpenSwoole coroutine execution occurs within coroutine contexts created explicitly or by server callbacks; therefore context attachment must be coroutine-local rather than process-global. ([openswoole.com](https://openswoole.com/docs/modules/swoole-coroutine-run?utm_source=openai))

## 17.3 Fiber adapter

Provide a minimal `FiberExecutionEngine` only if a compelling use case exists.

Responsibilities:

- Start and resume Fibers.
- Propagate execution context.
- Record suspension/resumption measurements when enabled.
- Restore prior context after Fiber completion.
- Preserve the original exception.

Do not introduce a proprietary Promise API in `1.0.0`.

---

## 18. PHP 8.4 Language Usage

PHP 8.4 adds property hooks and lazy objects, among other capabilities. Use these features where they simplify contracts without obscuring domain behavior. ([php.net](https://www.php.net/releases/8.4/en.php?utm_source=openai))

### 18.1 Property hooks and virtual properties

Appropriate uses:

- Validated SDK builder properties.
- Computed normalized names.
- Read-only derived durations.
- Interface properties for lightweight projections.

Avoid property hooks when:

- A named constructor communicates invariants more clearly.
- Mutation could compromise immutability.
- A method better represents an operation.

### 18.2 Lazy objects

Potential uses:

- Lazy optional adapter initialization.
- Expensive resource detection.
- Snapshot serializers.
- Exporter factories.

Do not expose lazy proxies as domain artifacts.

### 18.3 Fibers

Use only through runtime adapters. Domain and application contracts must remain scheduler-neutral.

### 18.4 `readonly`

Use `readonly` for:

- Identifiers.
- Definitions.
- Context entries.
- Artifacts.
- Policies.
- Adapter configuration.
- Builders that return copied instances.

Runtime coordinators may be non-readonly but must encapsulate mutation behind lifecycle interfaces.

### 18.5 Traits

Traits may provide harmonized, stateless behavior such as:

```text
StringIdentifierBehavior
NormalizableArtifactBehavior
HasExecutionIdentityBehavior
HasActivityIdentityBehavior
```

Traits must:

- Be paired with interfaces.
- Avoid mutable properties.
- Avoid hidden dependencies.
- Remain internal unless third-party reuse is intentional.

---

## 19. Self-Instrumentation

RunIt must itself adhere to the execution model.

### 19.1 Internal execution coverage

Instrument:

- Adapter initialization.
- Context extraction.
- Artifact routing.
- Batch flush.
- Snapshot serialization.
- Propagation injection/extraction.
- Publisher failure handling.
- Swoole worker lifecycle.

### 19.2 Recursion prevention

Artifact publication must not publish ordinary artifacts about publishing those same artifacts.

Implement:

```text
PublicationGuardInterface
PublicationGuardTokenInterface
InternalHealthReporterInterface
```

Rules:

- Enter a publication guard before adapter publication.
- Nested publication goes to a recursion-safe health reporter.
- The health reporter may use counters, `error_log`, or a dedicated callback.
- Health reporting must never route back through the guarded publisher.
- Self-instrumentation of an exporter must occur at the batch/flush boundary, not once per artifact.

### 19.3 Dogfooding

CI examples and RunIt’s own command-line development tasks should execute through RunIt:

```text
run-it.tests
run-it.static-analysis
run-it.architecture-analysis
run-it.mutation-testing
run-it.benchmark
```

The bootstrap kernel used to construct RunIt remains intentionally uninstrumented.

---

## 20. Security and Privacy

### 20.1 Threat model

Address:

- Secret leakage through context.
- Personal data in observations.
- Arbitrary object serialization.
- Log injection.
- Metric cardinality attacks.
- Unbounded payloads.
- Malicious occurrence types or attribute keys.
- Tenant context leakage in persistent workers.
- Snapshot tampering.
- Exporter SSRF or endpoint misuse.
- Denial of service through artifact volume.

### 20.2 Required controls

1. Context classification.
2. Attribute key validation.
3. Maximum string and payload sizes.
4. Maximum context entry count.
5. Maximum activity depth.
6. Maximum artifact count per execution.
7. Redaction before sampling and export.
8. Safe throwable normalization.
9. Stack-trace export policy.
10. Snapshot hashing.
11. Optional snapshot signing.
12. Export endpoint configuration outside library code.
13. No automatic environment-variable capture without allow-listing.
14. No `serialize()` of arbitrary consumer objects.
15. Coroutine/Fiber context cleanup assertions.

### 20.3 Recommended defaults

```text
max_context_entries: 64
max_attribute_entries: 64
max_attribute_string_bytes: 4096
max_occurrence_data_bytes: 262144
max_snapshot_bytes: adapter-defined, default 1 MiB
max_activity_depth: 64
max_artifacts_per_execution: 10000
export_stack_traces: errors-only
export_secret_values: false
```

---

## 21. Performance and Resource Management

### 21.1 Hot-path rules

- No reflection during ordinary execution.
- No backtrace collection unless explicitly enabled.
- No eager normalization before an artifact reaches an enabled publisher.
- No default in-memory retention of all artifacts.
- No network calls from core application services.
- Reuse immutable policy and mapper objects.
- Cache normalized static names.
- Use monotonic time for duration calculation.
- Use wall-clock time only for timestamps.
- Prefer integer nanoseconds internally.
- Defer stack-trace and payload serialization.

### 21.2 Persistent-worker controls

- Explicit runtime reset hook.
- Weakly keyed Fiber/coroutine associations.
- Publisher flush budget.
- Maximum buffer age.
- Leak-detection integration tests.
- No request-specific state in static properties.
- Avoid capturing execution scopes in long-lived closures.

### 21.3 Benchmarks

Create benchmarks for:

- Null runner.
- In-memory runner.
- Nested activity depth.
- Measurement throughput.
- Observation with and without throwable.
- Composite publisher fan-out.
- Redaction.
- Sampling.
- Fiber context switching.
- Swoole coroutine context switching.
- OpenTelemetry mapping.
- CloudEvents mapping.
- Snapshot normalization.

Store benchmark baselines and fail CI only on statistically meaningful regressions, initially greater than 15%.

---

## 22. Test-Framework-Agnostic Testing Strategy

## 22.1 Testing layers

### Domain tests

Validate:

- Value-object invariants.
- Equality semantics.
- Identifier validation.
- Context immutability.
- State transition legality.
- Artifact normalization.
- Sequence ordering.
- Unit validation.
- Snapshot hashing.

### Application tests

Validate:

- Successful execution.
- Failed execution.
- Cancellation.
- Timeout mapping.
- Nested execution.
- Nested activities.
- Open activity completion policy.
- Publisher failures.
- Original exception preservation.
- Context attach/detach symmetry.
- Recursion prevention.

### Contract test suites

Ship reusable abstract specifications or behavior harnesses for third-party implementations:

```text
ArtifactPublisherContract
ContextStorageContract
IdGeneratorContract
SerializerContract
RedactorContract
SamplingPolicyContract
SnapshotStoreContract
ExecutionEngineContract
```

Each harness should accept factories rather than depend on a particular test framework’s base class.

Example:

```php
interface ArtifactPublisherFixtureInterface
{
    public function createPublisher(): ArtifactPublisherInterface;

    public function publishedArtifacts(): iterable;
}
```

Assertions can be expressed as framework-neutral verifier objects returning violation collections.

### Adapter integration tests

- OpenTelemetry in-memory exporters.
- CloudEvents SDK conversion.
- PSR-3 recording logger.
- PSR-14 recording dispatcher.
- Fiber propagation.
- Swoole/OpenSwoole runtime when extension is available.

### Compatibility tests

Test against:

- Lowest supported dependency versions.
- Highest supported dependency versions.
- `--prefer-lowest`.
- Stable dependency set.
- Upcoming dependency versions where feasible.
- PHP 8.4 patch releases.
- Linux and Windows for core behavior.
- CLI and persistent-worker environments.

### Property-based tests

Generate:

- Valid and invalid identifiers.
- Context combinations.
- Arbitrary nesting.
- Lifecycle command sequences.
- Attribute payloads.
- Serialization round trips.
- CloudEvents extension mappings.

State-machine properties:

- Terminal states never transition.
- Every started activity terminates at most once.
- Detaching a context token restores the previous scope.
- Artifact sequence numbers are strictly increasing per execution.
- No artifact is associated with two executions.

### Concurrency tests

- Parallel Fibers.
- Nested Fibers.
- Fiber suspension inside activities.
- Throwable across Fiber boundary.
- Coroutine reuse.
- Publisher buffering under concurrency.
- Context cleanup after cancellation.

### Failure-injection tests

Inject failures from:

- Clock.
- ID generator.
- Serializer.
- Redactor.
- Publisher.
- Snapshot store.
- OpenTelemetry API.
- CloudEvents mapper.
- Context detach.
- Buffer flush.

Confirm the documented failure policy in every case.

## 22.2 Mutation testing

Use Infection against:

- Lifecycle transitions.
- Context token restoration.
- Failure policy branches.
- Sampling decisions.
- Redaction conditions.
- Identifier validators.
- Adapter mappings.

Mutation testing measures whether the suite detects seeded changes rather than relying solely on line coverage. Infection exposes MSI and covered-code MSI for this purpose. ([infection.github.io](https://infection.github.io/guide/?utm_source=openai))

CI policy:

- Changed-line mutation testing on pull requests.
- Full mutation suite nightly.
- Minimum covered-code MSI of 85%.
- No ignored mutation without documented rationale.

---

## 23. QA and Architectural Governance

## 23.1 PHPStan

Configuration:

- Highest supported level.
- Strict rules.
- Bleeding-edge rules in a non-blocking preview job initially.
- Check uninitialized properties.
- Check missing generic parameters.
- Check exception declarations.
- Disallow implicit `mixed`.
- Custom rules for:
    - Concrete classes not marked `final`.
    - Eligible immutable classes not marked `readonly`.
    - Concrete classes lacking interfaces.
    - Domain references to adapter namespaces.
    - Static mutable state.
    - Direct system time access.
    - Direct random ID generation.
    - Arbitrary object serialization.

## 23.2 DepTrac

DepTrac groups classes into layers and enforces allowed dependencies, with undeclared dependencies forbidden by default. ([deptrac.github.io](https://deptrac.github.io/deptrac/concepts/?utm_source=openai))

Required layers:

```text
Contract
Domain
Application
Port
SDK
CommonAdapter
ExternalAdapter
TestSupport
```

Rules:

```text
Domain          → Contract
Application     → Contract, Domain, Port
SDK             → Contract, Domain, Application, Port
CommonAdapter   → Contract, Domain, Port
ExternalAdapter → Contract, Domain, Port, external dependency
TestSupport     → all public layers
```

Forbidden:

```text
Domain          → Application
Domain          → Adapter
Application     → SDK
Application     → ExternalAdapter
Core Adapter    → Framework
```

## 23.3 Formatting and coding standards

Use PHP-CS-Fixer with:

- PER-CS-based rules.
- Strict types.
- Ordered imports.
- Native function invocation policy where measured useful.
- No risky modernization rule without review.

PHP-CS-Fixer provides PER-compatible and modernization-oriented rule sets, including risky rules that must be enabled deliberately. ([github.com](https://github.com/PHP-CS-Fixer/PHP-CS-Fixer?utm_source=openai))

## 23.4 Additional tooling

- Composer validation.
- Composer normalized metadata.
- `composer audit`.
- Roave Backward Compatibility Check.
- API surface diffing.
- Rector dry-run for controlled modernization.
- Markdown lint.
- JSON/YAML schema validation.
- Shell lint for CI scripts.
- License validation.
- Secret scanning.
- SBOM generation.
- Dependency review.
- Benchmark regression reporting.

---

## 24. Composer Design

Illustrative `composer.json`:

```json
{
  "name": "it-pack/run-it",
  "description": "Vendor-neutral execution model and runtime backbone for the ItPack ecosystem.",
  "type": "library",
  "license": "MIT",
  "require": {
    "php": "^8.4"
  },
  "suggest": {
    "psr/clock": "PSR-20 clock integration",
    "psr/log": "PSR-3 observation and logger integration",
    "psr/event-dispatcher": "PSR-14 occurrence integration",
    "psr/http-message": "HTTP context propagation",
    "psr/http-server-handler": "PSR-15 execution middleware",
    "open-telemetry/api": "OpenTelemetry mapping",
    "open-telemetry/sdk": "OpenTelemetry SDK/export support",
    "cloudevents/sdk-php": "CloudEvents occurrence mapping",
    "ext-openswoole": "OpenSwoole coroutine execution adapter",
    "ext-swoole": "Swoole coroutine execution adapter"
  },
  "autoload": {
    "psr-4": {
      "ItPack\\RunIt\\": "src/"
    }
  },
  "autoload-dev": {
    "psr-4": {
      "ItPack\\RunIt\\Tests\\": "tests/"
    }
  }
}
```

Before `1.0.0`, evaluate splitting heavy bridges into:

```text
it-pack/run-it
it-pack/run-it-otel
it-pack/run-it-cloudevents
it-pack/run-it-psr
it-pack/run-it-swoole
```

Split when any bridge:

- Introduces dependency conflicts.
- Requires extension-specific CI.
- Has an independent release cadence.
- Adds substantial source or transitive dependencies.
- Creates confusing optional-class loading behavior.

The root `it-pack/run-it` package must remain the stable semantic core.

---

## 25. Documentation Deliverables

Create:

1. `README.md`
    - Installation.
    - Five-minute example.
    - Design philosophy.
    - Stability policy.

2. `docs/concepts/execution.md`
3. `docs/concepts/context.md`
4. `docs/concepts/activity.md`
5. `docs/concepts/artifacts.md`
6. `docs/architecture/hexagonal-architecture.md`
7. `docs/architecture/lifecycle.md`
8. `docs/architecture/context-propagation.md`
9. `docs/integrations/opentelemetry.md`
10. `docs/integrations/cloudevents.md`
11. `docs/integrations/psr.md`
12. `docs/integrations/swoole.md`
13. `docs/recipes/http.md`
14. `docs/recipes/cli.md`
15. `docs/recipes/queue-worker.md`
16. `docs/recipes/configure-it.md`
17. `docs/security.md`
18. `docs/performance.md`
19. `UPGRADING.md`
20. `CONTRIBUTING.md`

Every public interface and artifact field must have:

- Semantic meaning.
- Lifecycle.
- Cardinality expectations.
- Security classification behavior.
- Normalization representation.
- Adapter mapping.

---

## 26. Implementation Phases

## Phase 0 — Architectural Ratification, Week 1

### Deliverables

- Ratify terminology.
- Confirm package split strategy.
- Confirm PHP `^8.4`.
- Ratify provisional performance targets.
- Ratify default failure policy.
- Define public API stability rules.
- Create initial ADRs.
- Establish CI and repository protections.

### Exit criteria

- No unresolved contradiction between `EXECUTION.md` and implementation semantics.
- Execution and activity state machines approved.
- Public namespaces approved.
- Security classifications approved.

---

## Phase 1 — Domain Kernel, Weeks 2–3

### Implement

- Identifier contracts and value objects.
- Execution definitions and records.
- Execution context and entries.
- Activity records.
- Measurements.
- Observations.
- Occurrences.
- Snapshots.
- Status and policy enums.
- Normalization contracts.
- Throwable description.
- Unit and property-based tests.

### Exit criteria

- Domain has no outward dependencies.
- All artifacts are immutable.
- PHPStan and DepTrac pass.
- Deterministic normalization is demonstrated.

---

## Phase 2 — Execution Runtime, Weeks 4–5

### Implement

- `ExecutionRunnerInterface`.
- `DefaultExecutionRunner`.
- `ExecutionScope`.
- `ActivityRunner`.
- Recorder services.
- Lifecycle coordinator.
- Sequence allocator.
- Context token attach/detach.
- Nested execution policy.
- Completion policy.
- Failure mapping.
- Terminal snapshot creation.

### Exit criteria

- Success, failure, cancellation, timeout, and nesting tests pass.
- Original exceptions are preserved.
- No context leakage in synchronous execution.
- RunIt’s own examples execute through the runtime.

---

## Phase 3 — Publication Pipeline, Week 6

### Implement

- Null, callback, memory, composite, filtering, redacting, sampling, and fail-safe publishers.
- Recursion guard.
- Internal health reporting.
- Buffering and backpressure.
- Publication failure policies.
- Artifact routing.

### Exit criteria

- Publisher contract suite complete.
- Adapter exceptions obey policy.
- Recursive publication is impossible.
- Performance baseline established.

---

## Phase 4 — SDK and Non-Intrusive Adoption, Week 7

### Implement

- Immutable `RunItBuilder`.
- Callable decorators.
- Interface decorator examples.
- Current-execution accessor.
- Attribute metadata.
- PSR-15 middleware skeleton.
- CLI and queue recipes.

### Exit criteria

- A consumer can instrument an existing callable in no more than five lines.
- Domain code needs no RunIt inheritance.
- SDK adds no hidden global state.

---

## Phase 5 — OpenTelemetry and PSR Adapters, Weeks 8–9

### Implement

- OTel trace/activity mapping.
- Measurement mapping.
- Observation/log mapping.
- Context propagation.
- PSR-3 bridge.
- PSR-14 bridge.
- PSR-20 bridge.
- OTel in-memory integration tests.

### Exit criteria

- Core remains installable without OTel or PSR bridges.
- Trace and activity hierarchy maps correctly.
- Metric cardinality guards are active.
- PSR listener failures obey publication policy.

---

## Phase 6 — CloudEvents and Runtime Adapters, Week 10

### Implement

- Occurrence-to-CloudEvent mapping.
- Structured JSON mapping.
- Reverse mapping.
- Fiber context storage.
- Swoole/OpenSwoole context adapter spike.
- Persistent-worker reset behavior.

### Exit criteria

- CloudEvent round trips preserve required semantics.
- Fiber tests demonstrate isolation.
- Swoole adapter feasibility is documented.
- No persistent-worker memory growth is detected.

---

## Phase 7 — Hardening, Week 11

### Execute

- Full mutation testing.
- Security review.
- Dependency audit.
- API compatibility review.
- Fuzz/property testing.
- Performance regression analysis.
- Documentation review.
- `--prefer-lowest` and high-dependency matrix.
- Long-running worker tests.

### Exit criteria

- Quality targets in §5 are met.
- No critical architectural violations.
- No unresolved high-risk security findings.
- Benchmarks satisfy ratified targets.

---

## Phase 8 — Release Candidate and Adoption, Week 12

### Deliver

- `1.0.0-rc.1`.
- ConfigureIt integration.
- At least one HTTP or queue-worker integration.
- Migration/adoption guide for other ItPack packages.
- Public extension contract documentation.
- Release checklist and support policy.

### Final `1.0.0` gate

Release only after:

- ConfigureIt successfully uses RunIt without domain-layer coupling.
- One third-party-style sample adapter passes contract tests.
- Public API review finds no unnecessary interfaces.
- OpenTelemetry and CloudEvents mappings are validated.
- Persistent-worker isolation is proven.
- All success criteria have measured evidence.

---

## 27. Initial ADR Set

Create and approve:

| ADR | Decision |
|---|---|
| ADR-001 | Execution is the root aggregate |
| ADR-002 | Domain artifacts are immutable |
| ADR-003 | Artifact ownership uses identity, not mandatory collections |
| ADR-004 | Explicit context propagation is canonical |
| ADR-005 | Ambient context is adapter-backed and non-global |
| ADR-006 | External standards are adapters |
| ADR-007 | Publication is fail-open by default |
| ADR-008 | Original business exceptions have priority |
| ADR-009 | Monotonic time measures duration |
| ADR-010 | Core has no mandatory external dependencies |
| ADR-011 | Occurrence names are stable and versioned |
| ADR-012 | Sensitive values are deny-by-default |
| ADR-013 | Swoole/Fiber support is adapter-based |
| ADR-014 | RunIt self-observation uses recursion guards |
| ADR-015 | Public concrete classes are final and interface-backed |
| ADR-016 | Bridge package split criteria |

---

## 28. Definition of Done

RunIt is complete when:

- [ ] `it-pack/run-it` installs on PHP 8.4 without framework or extension dependencies.
- [ ] Execution, context, activities, measurements, observations, occurrences, and snapshots have stable contracts.
- [ ] All domain artifacts are immutable.
- [ ] Execution and activity state transitions are enforced.
- [ ] Explicit and ambient propagation are available.
- [ ] Fiber and persistent-worker context isolation are tested.
- [ ] Callables and interface implementations can be instrumented non-intrusively.
- [ ] Artifact publication supports redaction, sampling, routing, buffering, and failure policies.
- [ ] OpenTelemetry maps the model without defining it.
- [ ] CloudEvents represents occurrences without defining them.
- [ ] PSR-3 and PSR-14 bridges are optional.
- [ ] RunIt instruments its own operational behavior without recursion.
- [ ] PHPStan, DepTrac, coding standards, mutation tests, security checks, and compatibility checks pass.
- [ ] Performance targets are measured and published.
- [ ] ConfigureIt demonstrates the first production integration.
- [ ] Third-party adapter contracts and test harnesses are documented.
- [ ] Semantic Versioning and backward-compatibility checks protect the public API.

> **Everything happens within an execution. RunIt makes that execution explicit, composable, observable, portable, and safe.**