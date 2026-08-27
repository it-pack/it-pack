# RunIt

> **The execution model and runtime backbone for the ItPack ecosystem.**

[![PHP](https://img.shields.io/badge/PHP-%3E%3D%208.4-777BB4.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![Status](https://img.shields.io/badge/status-initial%20development-orange.svg)](#project-status)

RunIt provides composable, reusable, and non-intrusive execution components for PHP applications and libraries.

It models **execution itself** rather than coupling application code directly to tracing, logging, metrics, event, or runtime technologies.

```text
Execution
├── Context
├── Activity
├── Measurement
├── Observation
├── Occurrence
└── Snapshot
```

OpenTelemetry, CloudEvents, PSR interfaces, Fibers, and Swoole are integrations around this model—not the model itself.

> **Everything happens within an execution.**

The canonical semantic resources are the
[RunIt OWL vocabulary](vocab/run-it.ttl) and its companion
[SKOS taxonomy](vocab/run-it-taxonomy.ttl). Public contracts and documentation
refer to the relevant terms as they are implemented.

---

## Table of Contents

- [Purpose](#purpose)
- [Project Status](#project-status)
- [Requirements](#requirements)
- [Installation](#installation)
- [Core Concepts](#core-concepts)
- [Quick Start](#quick-start)
- [Activities](#activities)
- [Measurements](#measurements)
- [Observations](#observations)
- [Occurrences](#occurrences)
- [Snapshots](#snapshots)
- [Execution Context](#execution-context)
- [Non-Intrusive Adoption](#non-intrusive-adoption)
- [Context Propagation](#context-propagation)
- [Error Handling](#error-handling)
- [External Integrations](#external-integrations)
- [Architecture](#architecture)
- [Security and Privacy](#security-and-privacy)
- [Performance](#performance)
- [Testing](#testing)
- [Development](#development)
- [Roadmap](#roadmap)
- [Versioning](#versioning)
- [Contributing](#contributing)
- [License](#license)

---

## Purpose

Runtime behavior is commonly represented through unrelated systems:

- Traces and spans.
- Logs.
- Metrics.
- Events.
- Configuration snapshots.
- Queue metadata.
- Request context.
- Diagnostic reports.

These systems frequently describe the same operation using incompatible vocabularies.

RunIt establishes a shared semantic foundation:

```text
Execution

    owns Context

    contains Activities

    records Measurements

    captures Observations

    publishes Occurrences

    produces Snapshots
```

This allows ItPack packages, applications, and third-party libraries to cooperate without depending on a specific telemetry vendor, framework, event bus, logger, or execution runtime.

### Example execution kinds

RunIt can represent:

- HTTP requests.
- CLI commands.
- Queue jobs.
- Configuration resolution.
- Workflows.
- Event processing.
- Scheduled tasks.
- Background workers.
- Build processes.
- Database migrations.
- LLM pipelines.
- Custom operations.

---

## Project Status

RunIt is currently in **initial development**.

The public API shown in this README represents the intended direction of the package but may change before the first stable `1.0.0` release.

Do not rely on backward compatibility before `1.0.0` unless a specific pre-release version documents otherwise.

Initial development priorities are:

1. Stable execution semantics.
2. Immutable domain artifacts.
3. Lifecycle-safe execution and activity runners.
4. Context propagation.
5. Composable artifact publication.
6. OpenTelemetry integration.
7. CloudEvents integration.
8. PSR integration.
9. Fiber and Swoole runtime adapters.
10. Adoption by other ItPack packages.

---

## Requirements

- PHP 8.4 or later.
- Composer 2.
- No framework requirement.
- No mandatory PHP extension beyond those required by PHP itself.

Optional integrations may require additional packages or extensions:

- OpenTelemetry PHP API and SDK.
- CloudEvents PHP SDK.
- PSR-3 logger interfaces.
- PSR-14 event dispatcher interfaces.
- PSR-15 HTTP server interfaces.
- PSR-20 clock interfaces.
- Swoole or OpenSwoole.
- FFI for selected Fiber-aware OpenTelemetry configurations.

---

## Installation

Install RunIt through Composer:

```bash
composer require it-pack/run-it
```

During initial development, a pre-release constraint may be required:

```bash
composer require it-pack/run-it:^0.1
```

RunIt is designed to remain usable without installing telemetry exporters, framework bridges, or runtime extensions.

---

## Core Concepts

### Execution

An execution represents one logical runtime operation.

Examples:

```text
configuration.resolve
http.request
queue.job
workflow.execute
migration.run
```

An execution has:

- A stable identity.
- A name and kind.
- Immutable context.
- A lifecycle.
- Zero or more nested activities.
- Measurements, observations, and occurrences.
- A terminal result or failure.
- Optional snapshots.

See [`docs/concepts/execution.md`](docs/concepts/execution.md) for the full model.

### Context

Context answers:

> **Where and under which conditions am I executing?**

Examples:

- Environment.
- Tenant.
- Workspace.
- Locale.
- Principal.
- Feature flags.
- Correlation ID.
- Trace ID.
- Configuration snapshot ID.

See [`docs/concepts/context.md`](docs/concepts/context.md) for classifications, redaction, and the standard vocabulary.

### Activity

Activities answer:

> **What is currently happening?**

Examples:

- Discover files.
- Read resources.
- Load YAML.
- Merge documents.
- Validate schema.
- Resolve variables.

Activities may be nested.

See [`docs/concepts/activity.md`](docs/concepts/activity.md) for the activity lifecycle and nesting rules.

### Measurement

Measurements answer:

> **How much?**

Examples:

- Duration.
- Memory.
- Files loaded.
- Documents parsed.
- Graph nodes.
- Validation errors.

See [`docs/concepts/measurement.md`](docs/concepts/measurement.md) for kinds, units, and cardinality rules.

### Observation

Observations answer:

> **What did we notice?**

Examples:

- Diagnostic messages.
- Warnings.
- Debug information.
- Validation failures.
- Errors.

See [`docs/concepts/observation.md`](docs/concepts/observation.md) for severities and normalization.

### Occurrence

Occurrences answer:

> **What happened?**

Examples:

- Configuration loaded.
- Schema validated.
- Snapshot created.
- Pipeline completed.

Occurrences are immutable facts and can be represented as CloudEvents.

See [`docs/concepts/occurrence.md`](docs/concepts/occurrence.md) for versioned types and CloudEvents mapping.

### Snapshot

Snapshots answer:

> **What is the resulting state?**

Examples:

- Configuration snapshot.
- Execution snapshot.
- Workflow snapshot.
- Pipeline snapshot.

See [`docs/concepts/snapshot.md`](docs/concepts/snapshot.md) for snapshot integrity and persistence.

---

## Quick Start

The canonical API executes a callable within an explicit execution scope:

```php
<?php

declare(strict_types=1);

use ItPack\RunIt\Application\Runner\ExecutionRunnerInterface;
use ItPack\RunIt\Application\Scope\ExecutionScopeInterface;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;

/** @var ExecutionRunnerInterface $runner */
$result = $runner->run(
    ExecutionDefinition::named('configuration.resolve')
        ->ofKind('configuration.resolution'),
    static function (ExecutionScopeInterface $execution): array {
        return [
            'environment' => 'production',
            'debug' => false,
        ];
    },
);
```

The runner is responsible for:

1. Creating the execution.
2. Attaching its context.
3. Publishing lifecycle artifacts.
4. Invoking the operation.
5. Recording success or failure.
6. Producing the terminal execution snapshot.
7. Restoring the previous execution context.
8. Returning the result or rethrowing the original exception.

---

## Activities

Use activities to describe bounded units of work inside an execution:

```php
<?php

declare(strict_types=1);

use ItPack\RunIt\Application\Scope\ActivityScopeInterface;
use ItPack\RunIt\Application\Scope\ExecutionScopeInterface;
use ItPack\RunIt\Domain\Activity\ActivityDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;

$config = $runner->run(
    ExecutionDefinition::named('configuration.resolve'),
    static function (ExecutionScopeInterface $execution) use ($loader): array {
        return $execution->activities()->run(
            ActivityDefinition::named('configuration.load'),
            static function (ActivityScopeInterface $activity) use ($loader): array {
                return $loader->load();
            },
        );
    },
);
```

Activities may be nested:

```php
$result = $execution->activities()->run(
    ActivityDefinition::named('configuration.resolve'),
    static function (ActivityScopeInterface $resolution) use ($loader): array {
        $documents = $resolution->activities()->run(
            ActivityDefinition::named('configuration.load-documents'),
            static fn (ActivityScopeInterface $activity): array => $loader->load(),
        );

        return $resolution->activities()->run(
            ActivityDefinition::named('configuration.merge-documents'),
            static fn (ActivityScopeInterface $activity): array => array_replace_recursive(
                ...$documents,
            ),
        );
    },
);
```

RunIt automatically associates nested activities with their execution and parent activity.

---

## Measurements

Measurements capture quantitative information:

```php
use ItPack\RunIt\Domain\Measurement\Measurement;

$activity->measurements()->record(
    Measurement::counter(
        name: 'files.loaded',
        value: 14,
        unit: 'file',
    ),
);

$activity->measurements()->record(
    Measurement::duration(
        name: 'configuration.load.duration',
        nanoseconds: 18_700_000,
    ),
);
```

Measurements are structured artifacts, not preformatted metric strings.

A measurement includes:

- Name.
- Numeric value.
- Unit.
- Kind.
- Timestamp.
- Execution identity.
- Optional activity identity.
- Structured attributes.

Metric exporters are responsible for mapping measurements to their destination’s instrument and aggregation model.

---

## Observations

Observations represent qualitative information noticed during execution:

```php
use ItPack\RunIt\Domain\Observation\Observation;

$execution->observations()->record(
    Observation::warning(
        message: 'Configuration source {source} is deprecated.',
        attributes: [
            'source' => 'legacy.yaml',
        ],
    ),
);
```

Errors may include a throwable:

```php
try {
    $schema->validate($document);
} catch (Throwable $throwable) {
    $activity->observations()->record(
        Observation::error(
            message: 'Schema validation failed.',
            attributes: [
                'schema' => $schema->name(),
            ],
            throwable: $throwable,
        ),
    );

    throw $throwable;
}
```

RunIt normalizes throwables into safe, serializable descriptions. It does not require arbitrary exception objects to cross process or service boundaries.

### Observation severities

```text
trace
debug
info
notice
warning
error
critical
alert
emergency
```

PSR-3 is supported as an adapter around observations.

---

## Occurrences

Occurrences are immutable facts:

```php
use ItPack\RunIt\Domain\Occurrence\Occurrence;

$activity->occurrences()->record(
    Occurrence::named('itpack.configuration.loaded.v1')
        ->withSubject('configuration/production')
        ->withData([
            'files_loaded' => 14,
            'snapshot_id' => 'snapshot-01H...',
        ]),
);
```

Occurrence names should be:

- Stable.
- Lowercase.
- Versioned.
- Independent of PHP class names.
- Meaningful outside the producing process.

Recommended naming pattern:

```text
<organization>.<domain>.<fact>.<version>
```

Examples:

```text
itpack.configuration.loaded.v1
itpack.schema.validated.v1
itpack.snapshot.created.v1
itpack.workflow.completed.v1
```

Occurrences can be mapped to:

- CloudEvents.
- PSR-14 events.
- Message brokers.
- Audit streams.
- Event stores.
- OpenTelemetry span events.

---

## Snapshots

Snapshots preserve immutable resulting state:

```php
use ItPack\RunIt\Domain\Snapshot\Snapshot;

$execution->snapshots()->record(
    Snapshot::named('configuration.snapshot')
        ->withSchemaVersion('1.0')
        ->withState([
            'environment' => 'production',
            'debug' => false,
        ]),
);
```

RunIt distinguishes between:

- **Checkpoint snapshots** created during execution.
- **Terminal snapshots** representing final execution state.

Snapshot persistence is delegated to a port:

```php
use ItPack\RunIt\Contract\Port\SnapshotStoreInterface;

interface SnapshotStoreInterface
{
    public function store(SnapshotInterface $snapshot): void;
}
```

The core package does not require a database, filesystem, cache, or object store.

---

## Execution Context

Execution context is immutable and copied when changed:

```php
use ItPack\RunIt\Domain\Context\ContextEntry;
use ItPack\RunIt\Domain\Context\ExecutionContext;

$context = ExecutionContext::empty()
    ->with(ContextEntry::internal('environment', 'production'))
    ->with(ContextEntry::confidential('tenant.id', 'tenant-123'))
    ->with(ContextEntry::internal('locale', 'en-US'));
```

Create a derived context without modifying the original:

```php
$workspaceContext = $context
    ->with(ContextEntry::internal('workspace.id', 'workspace-456'))
    ->without('legacy.flag');
```

### Context classifications

RunIt supports data classification:

```text
public
internal
confidential
restricted
secret
```

Default behavior:

| Classification | Default export behavior |
|---|---|
| Public | Exportable |
| Internal | Exportable |
| Confidential | Policy-controlled |
| Restricted | Redacted |
| Secret | Never exported |

Context classification exists to make data-handling intent explicit. It does not replace application-specific authorization or regulatory controls.

---

## Non-Intrusive Adoption

RunIt does not require domain classes to:

- Extend a base class.
- Use a framework service provider.
- Import OpenTelemetry APIs.
- Implement logging interfaces.
- Dispatch framework events.
- Use static facades.
- Use RunIt-specific traits.

### Callable decoration

Existing callables can be decorated:

```php
$resolve = $runIt->decorateCallable(
    name: 'configuration.resolve',
    kind: 'configuration.resolution',
    operation: $resolver->resolve(...),
);

$config = $resolve();
```

### Interface decoration

Existing services can be wrapped through ordinary composition:

```php
<?php

declare(strict_types=1);

use ItPack\RunIt\Application\Runner\ExecutionRunnerInterface;
use ItPack\RunIt\Application\Scope\ExecutionScopeInterface;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;

final readonly class InstrumentedConfigurationResolver
    implements ConfigurationResolverInterface
{
    public function __construct(
        private ConfigurationResolverInterface $inner,
        private ExecutionRunnerInterface $runner,
    ) {}

    public function resolve(): ConfigurationInterface
    {
        return $this->runner->run(
            ExecutionDefinition::named('configuration.resolve')
                ->ofKind('configuration.resolution'),
            fn (ExecutionScopeInterface $execution): ConfigurationInterface =>
                $this->inner->resolve(),
        );
    }
}
```

This keeps the wrapped service independent of RunIt.

### Metadata attributes

Optional attributes may describe execution metadata:

```php
use ItPack\RunIt\SDK\Attribute\ExecutedAs;

#[ExecutedAs(
    name: 'configuration.resolve',
    kind: 'configuration.resolution',
)]
final readonly class ConfigurationResolver implements ConfigurationResolverInterface
{
    // ...
}
```

Attributes are metadata only. An SDK invoker, decorator factory, or framework adapter must interpret them.

---

## Context Propagation

RunIt supports explicit and ambient context propagation.

### Explicit propagation

Explicit scope arguments are preferred:

```php
function loadConfiguration(
    ExecutionScopeInterface $execution,
): ConfigurationInterface {
    // ...
}
```

Explicit propagation provides:

- Visible dependencies.
- Straightforward testing.
- Runtime independence.
- No implicit global lookup.

### Ambient propagation

Code that cannot accept an explicit scope may use an injected accessor:

```php
use ItPack\RunIt\Contract\Application\CurrentExecutionInterface;

final readonly class DiagnosticReporter implements DiagnosticReporterInterface
{
    public function __construct(
        private CurrentExecutionInterface $currentExecution,
    ) {}

    public function warning(string $message): void
    {
        $execution = $this->currentExecution->get();

        if ($execution === null) {
            return;
        }

        $execution->observations()->warning($message);
    }
}
```

RunIt does not expose a mutable global execution singleton.

### Supported storage strategies

The planned storage adapters are:

- Stack-based synchronous storage.
- Fiber-local storage.
- Swoole/OpenSwoole coroutine-local storage.
- Runtime-selecting composite storage.
- Null storage.

Every attached scope returns a token. Only that token may detach the scope:

```php
$token = $storage->attach($scope);

try {
    return $operation();
} finally {
    $storage->detach($token);
}
```

This prevents accidental restoration of the wrong execution during nesting or concurrency.

---

## Error Handling

RunIt preserves the original business result and exception.

```php
try {
    $result = $runner->run($definition, $operation);
} catch (Throwable $throwable) {
    // This is the original throwable raised by the operation.
}
```

When an operation throws:

1. The active activity is marked as failed.
2. The execution is marked as failed.
3. A structured failure observation is recorded.
4. A terminal snapshot is produced when configured.
5. The original throwable is rethrown.

An adapter or telemetry failure must not replace the original throwable.

### Publication failure policies

RunIt supports explicit publication policies:

```text
fail-open
fail-closed
collect-and-continue
report-to-fallback
```

The default is:

```text
report-to-fallback → fail-open
```

Fail-closed behavior must be explicitly enabled and is intended for security, audit, or compliance use cases where missing publication must prevent the operation.

---

## External Integrations

External standards implement portions of the RunIt model.

| RunIt model | OpenTelemetry | CloudEvents | PSR |
|---|---|---|---|
| Execution | Trace/root span | Event stream | — |
| Context | OTel context/baggage | Extensions | — |
| Activity | Span | — | — |
| Measurement | Metric | Event data | — |
| Observation | Log record/span event | Event data | PSR-3 |
| Occurrence | Span event | CloudEvent | PSR-14 |
| Snapshot | Reference or payload policy | Event payload | — |

### OpenTelemetry

The OpenTelemetry adapter is expected to provide:

- Execution-to-trace mapping.
- Activity-to-span mapping.
- Measurement-to-metric mapping.
- Observation-to-log mapping.
- Occurrence-to-span-event mapping.
- Context propagation.
- Resource and attribute mapping.
- Cardinality protection.
- Exception recording.

RunIt will not:

- Configure a global OpenTelemetry provider from library code.
- Require a specific exporter.
- Require a collector.
- Treat an OpenTelemetry span as the execution domain object.

### CloudEvents

The CloudEvents adapter maps occurrences to CloudEvents:

```text
Occurrence ID       → id
Occurrence type     → type
Source              → source
Subject             → subject
Time                → time
Content type        → datacontenttype
Data schema         → dataschema
Data                → data
Execution ID        → executionid extension
Activity ID         → activityid extension
Correlation ID      → correlationid extension
Sequence            → executionsequence extension
```

Structured JSON will be the initial format.

### PSR-3

Planned PSR-3 support includes:

- Publishing observations through a logger.
- Decorating loggers with execution correlation.
- Converting existing PSR-3 calls into observations.
- Mapping RunIt severities to PSR-3 levels.

### PSR-14

Planned PSR-14 support includes:

- Dispatching occurrences as PSR-14 events.
- Converting supported PSR events into occurrences.
- Applying publication failure policies around synchronous listeners.

### HTTP

Planned HTTP support includes:

- PSR-15 server middleware.
- PSR-7 request context extraction.
- PSR-18 client propagation.
- Correlation header extraction and injection.
- Parent-child execution relationships.

### Swoole and OpenSwoole

Swoole support will remain optional and adapter-based.

Planned capabilities:

- Coroutine-local execution context.
- HTTP server execution middleware.
- Worker lifecycle executions.
- Task dispatcher integration.
- Context cleanup between persistent requests.
- Publisher flushing with bounded shutdown budgets.

RunIt will not require consumer code to use Swoole-specific APIs.

---

## Architecture

RunIt follows hexagonal ports-and-adapters architecture:

```text
┌───────────────────────────────────────────────────────┐
│ SDK and Consumer Adapters                             │
│ Callables, Middleware, Attributes, Framework Bridges  │
├───────────────────────────────────────────────────────┤
│ Application                                           │
│ Runner, Scopes, Lifecycle, Policies, Publication       │
├───────────────────────────────────────────────────────┤
│ Domain                                                │
│ Execution, Context, Activity, Artifacts                │
├───────────────────────────────────────────────────────┤
│ Ports                                                 │
│ Clock, IDs, Storage, Publisher, Serializer, Redactor   │
├───────────────────────────────────────────────────────┤
│ Infrastructure Adapters                               │
│ Memory, OTel, CloudEvents, PSR, Fiber, Swoole          │
└───────────────────────────────────────────────────────┘
```

### Dependency direction

```text
Adapter → Application → Domain
             │
             ▼
            Ports
             ▲
             │
    Infrastructure adapters
```

The domain does not depend on:

- OpenTelemetry.
- CloudEvents.
- PSR interfaces.
- Frameworks.
- Swoole.
- Filesystems.
- Network clients.
- Containers.

### Package structure

```text
src/
├── Contract/
│   ├── Domain/
│   ├── Application/
│   ├── Port/
│   └── Exception/
├── Domain/
│   ├── Execution/
│   ├── Context/
│   ├── Activity/
│   ├── Measurement/
│   ├── Observation/
│   ├── Occurrence/
│   └── Snapshot/
├── Application/
│   ├── Runner/
│   ├── Scope/
│   ├── Lifecycle/
│   ├── Publication/
│   └── Propagation/
├── Adapter/
│   ├── Memory/
│   ├── Null/
│   ├── Callback/
│   ├── Fiber/
│   ├── OpenTelemetry/
│   ├── CloudEvents/
│   ├── Psr/
│   └── Swoole/
└── SDK/
    ├── Builder/
    ├── Decorator/
    ├── Middleware/
    └── Attribute/
```

### Design standards

RunIt follows these standards:

- SOLID.
- DRY.
- Composition over inheritance.
- Immutable domain artifacts.
- `final` concrete classes.
- `readonly` concrete classes wherever practical.
- Interface-backed implementations.
- Small, focused ports.
- Framework-neutral application services.
- Fluent, immutable builders where useful.
- No hidden service locator.

---

## Artifact Publication

RunIt artifacts are published through a composable pipeline:

```text
Artifact
   │
   ▼
Validation
   │
   ▼
Enrichment
   │
   ▼
Classification
   │
   ▼
Redaction
   │
   ▼
Sampling
   │
   ▼
Routing
   │
   ▼
Adapter
```

Planned publishers include:

```text
NullArtifactPublisher
InMemoryArtifactPublisher
CallbackArtifactPublisher
CompositeArtifactPublisher
FilteringArtifactPublisher
RedactingArtifactPublisher
SamplingArtifactPublisher
BufferedArtifactPublisher
FailSafeArtifactPublisher
```

Example composition:

```php
$publisher = new FailSafeArtifactPublisher(
    publisher: new SamplingArtifactPublisher(
        policy: $samplingPolicy,
        publisher: new RedactingArtifactPublisher(
            redactor: $redactor,
            publisher: new CompositeArtifactPublisher([
                $openTelemetryPublisher,
                $cloudEventsPublisher,
            ]),
        ),
    ),
    fallback: $fallbackReporter,
);
```

Artifact ownership does not require an execution to retain every artifact in memory. Publishers may stream artifacts as they are created.

---

## Self-Instrumentation

RunIt uses its own execution model to describe operational work such as:

- Adapter initialization.
- Artifact routing.
- Batch publication.
- Snapshot serialization.
- Context extraction.
- Context propagation.
- Publisher failures.
- Worker lifecycle.

Publication itself is protected by a recursion guard:

```text
Application artifact
    │
    ▼
Publisher
    │
    ├── success
    │
    └── failure
          │
          ▼
Internal health reporter
```

The internal health reporter does not publish through the failing publisher.

This prevents infinite loops such as:

```text
Publisher failed
→ publish observation
→ publisher failed
→ publish observation
→ ...
```

The minimal bootstrap required to construct RunIt remains intentionally uninstrumented.

---

## Security and Privacy

Execution context and telemetry frequently contain sensitive information.

RunIt applies a deny-by-default approach to:

- Credentials.
- API keys.
- Authentication tokens.
- Secrets.
- Restricted personal data.
- Unbounded payloads.
- Arbitrary object serialization.

### Recommended practices

- Classify every sensitive context value.
- Use stable observation templates.
- Store variable values in structured attributes.
- Do not place secrets in occurrence payloads.
- Do not turn tenant, user, URL, or error-message values into metric dimensions.
- Configure maximum payload and attribute sizes.
- Redact before export.
- Apply explicit allow lists to environment-variable capture.
- Keep snapshot serialization deterministic and bounded.
- Clear execution context after every persistent-worker request.

### Default limits

Initial defaults are expected to include:

```text
Context entries per execution:       64
Attributes per artifact:             64
Attribute string size:               4 KiB
Occurrence data size:                256 KiB
Snapshot size:                       1 MiB
Activity nesting depth:              64
Artifacts per execution:             10,000
Secret export:                       disabled
Stack-trace export:                  errors only
```

Adapters may define stricter limits.

---

## Performance

RunIt is intended for use on request, command, job, worker, and library hot paths.

The core runtime therefore avoids:

- Network access.
- Filesystem access.
- Reflection in ordinary execution.
- Eager stack-trace collection.
- Mandatory in-memory artifact retention.
- Eager payload serialization.
- Global mutable state.
- Unbounded queues.

Duration calculation uses monotonic time. Wall-clock time is used for external timestamps.

Performance-sensitive consumers can use:

- `NullArtifactPublisher`.
- Sampling.
- Deferred normalization.
- Bounded buffering.
- Batch exporters.
- Disabled stack traces.
- Activity filtering.

Performance baselines and regression benchmarks will be published as the implementation stabilizes.

---

## Testing

RunIt uses a test-framework-agnostic strategy.

Test categories include:

- Domain invariant tests.
- Lifecycle state-machine tests.
- Application service tests.
- Adapter contract tests.
- Integration tests.
- Property-based tests.
- Failure-injection tests.
- Fiber and coroutine isolation tests.
- Persistent-worker leak tests.
- Serialization round-trip tests.
- Mutation tests.
- Performance benchmarks.

Reusable contract suites are provided for third-party implementations of:

```text
ArtifactPublisherInterface
ContextStorageInterface
IdGeneratorInterface
SerializerInterface
RedactorInterface
SamplingPolicyInterface
SnapshotStoreInterface
ExecutionEngineInterface
```

Third-party adapters should be able to verify compatibility without adopting RunIt’s internal test framework.

---

## Development

Clone the repository and install dependencies:

```bash
git clone <repository>
cd run-it
composer install
```

### Quality checks

The final Composer scripts may evolve during initial development, but the intended commands are:

```bash
composer validate
composer test
composer analyse
composer architecture
composer coding-style
composer mutation
composer benchmark
composer audit
composer quality
```

Expected aggregate behavior:

```bash
composer quality
```

should run:

1. Composer validation.
2. Coding-standard checks.
3. PHPStan.
4. DepTrac.
5. Unit and integration tests.
6. Security audit.
7. Public API compatibility checks where applicable.

### Static analysis

RunIt targets the highest practical PHPStan level with:

- Strict rules.
- No first-party baseline.
- No implicit `mixed`.
- Generic type validation.
- Exception declaration checks.
- Custom architectural rules.

### Dependency architecture

DepTrac protects the intended dependency direction:

```text
Domain       → Contracts
Application  → Contracts, Domain, Ports
SDK          → Contracts, Domain, Application, Ports
Adapters     → Contracts, Domain, Ports, external APIs
```

The domain must never depend on application services or adapters.

### Mutation testing

Mutation testing focuses on:

- Lifecycle transitions.
- Context restoration.
- Failure policies.
- Redaction.
- Sampling.
- Identifier validation.
- Adapter mappings.
- Exception preservation.

### Coding standards

All PHP files must:

- Declare strict types.
- Follow the project coding standard.
- Use explicit parameter and return types.
- Avoid unnecessary inheritance.
- Avoid mutable static state.
- Keep concrete classes `final`.
- Use `readonly` where practical.

---

## Roadmap

### Initial domain kernel

- [ ] Execution identifiers and definitions.
- [ ] Immutable execution context.
- [ ] Activity model.
- [ ] Measurement model.
- [ ] Observation model.
- [ ] Occurrence model.
- [ ] Snapshot model.
- [ ] Deterministic normalization.

### Runtime

- [ ] Execution runner.
- [ ] Activity runner.
- [ ] Explicit execution scopes.
- [ ] Ambient context storage.
- [ ] Nested execution policies.
- [ ] Failure mapping.
- [ ] Terminal execution snapshots.

### Publication

- [ ] Null publisher.
- [ ] In-memory publisher.
- [ ] Callback publisher.
- [ ] Composite publisher.
- [ ] Redaction.
- [ ] Sampling.
- [ ] Buffering.
- [ ] Backpressure.
- [ ] Failure policies.
- [ ] Recursion protection.

### SDK

- [ ] Immutable builder.
- [ ] Callable decorator.
- [ ] Interface decorator examples.
- [ ] Attribute metadata.
- [ ] PSR-15 middleware.
- [ ] CLI integration recipe.
- [ ] Queue-worker integration recipe.

### Integrations

- [ ] OpenTelemetry.
- [ ] CloudEvents.
- [ ] PSR-3.
- [ ] PSR-14.
- [ ] PSR-20.
- [ ] PSR-7/15/18.
- [ ] Fibers.
- [ ] Swoole.
- [ ] OpenSwoole.

### Adoption

- [ ] ConfigureIt.
- [ ] ChaosCockpit.
- [ ] AExO.
- [ ] HoppaDB.
- [ ] Spindle.
- [ ] Third-party adapter example.

---

## Versioning

RunIt follows Semantic Versioning after `1.0.0`.

Public API includes:

- Public interfaces.
- Public classes.
- Public enums.
- Public attributes.
- Public method signatures.
- Normalized artifact field names.
- Occurrence naming rules.
- Serialized schema formats explicitly marked stable.

Internal namespaces and APIs marked experimental are not covered by backward-compatibility guarantees.

Before `1.0.0`:

- Minor versions may contain breaking changes.
- Breaking changes must be documented.
- Migration notes should accompany each pre-release.
- Deprecated APIs should be retained where practical until the next minor release.

---

## Contributing

Contributions are welcome once the initial contribution process is published.

Before submitting a change:

1. Open or reference an issue.
2. Explain the execution-model use case.
3. Preserve dependency direction.
4. Add or update tests.
5. Add contract tests for new extension points.
6. Update documentation.
7. Run the complete quality pipeline.
8. Document backward-compatibility impact.
9. Include an ADR for significant architectural decisions.

New features should be built from small, focused components rather than by expanding existing interfaces with unrelated responsibilities.

---

## Design Philosophy

RunIt is intentionally not defined in terms of an external standard.

```text
OpenTelemetry is an adapter.

CloudEvents is an adapter.

PSR-3 is an adapter.

PSR-14 is an adapter.

Swoole is an adapter.
```

The semantic model remains:

> **Execution carries context.**  
> **Activities perform work.**  
> **Measurements quantify work.**  
> **Observations describe work.**  
> **Occurrences record work.**  
> **Snapshots preserve work.**

---

## License

RunIt is released under the [MIT License](LICENSE).
