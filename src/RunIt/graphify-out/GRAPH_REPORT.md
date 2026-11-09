# Graph Report - RunIt  (2026-11-09)

## Corpus Check
- 817 files · ~364,126 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 5621 nodes · 13208 edges · 400 communities (306 shown, 94 thin omitted)
- Extraction: 91% EXTRACTED · 9% INFERRED · 0% AMBIGUOUS · INFERRED: 1198 edges (avg confidence: 0.8)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `228a7ff9`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- ItPack\RunIt\Contract\Port\ArtifactPublisherInterface
- ItPack\RunIt\Contract\Domain\ExecutionInterface
- ItPack\RunIt\Contract\Domain\ExecutionIdInterface
- ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface
- ItPack\RunIt\Contract\Domain\OccurrenceInterface
- ConformanceResult
- ConformanceViolations
- ItPack\RunIt\Contract\Domain\SnapshotInterface
- RunItBuilder
- ItPack\RunIt\Contract\Port\ContextStorageInterface
- ExecutionDefinition
- RunIt Implementation Plan
- ROADMAP.md
- README.md
- ItPack\RunIt\Contract\Domain\ObservationInterface
- ItPack\RunIt\Contract\Domain\ActivityIdInterface
- ExecutionId.php
- ItPack\RunIt\Contract\Domain\AttributeCollectionInterface
- Throwable
- RunItArtifactFactory
- ItPack\RunIt\Contract\Application\ExecutionScopeInterface
- README.md
- ExecutionKind.php
- getting-started.md
- Codeception\Test\Unit
- FilteringPublisherTest.php
- BufferingTest
- Closure
- RunIt-authored rules
- ExecutionAwareLoggerTest.php
- ItPack\RunIt\Contract\Domain\StringIdentifierInterface
- ItPack\RunIt\Contract\Domain\ActivityInterface
- OccurrenceType
- CloudEvent
- SnapshotType
- ActivityDefinition
- README.md
- 8. Findings register
- ItPack\RunIt\Contract\Domain\ExecutionKindInterface
- ExecutionId
- ItPack\RunIt\Contract\Application\ExecutionRunnerInterface
- BufferedArtifactPublisher
- RuntimeException
- ExecutionContext
- RunIt — In-Depth Review
- HttpAdaptersTest.php
- ThrowableDescription
- ExecutionContext.php
- Phase 0 — P0 correctness & security (do first, blocks release)
- PHPUnit\Framework\TestCase
- AttributeCollection
- ExecutedAsMetadataReaderTest
- ItPack\RunIt\Contract\Domain\ObservationSeverityInterface
- ApiCompat
- ResilienceTest
- scripts
- RunIt — Refactoring Plan
- TelemetryPublisher.php
- ExecutionMiddleware.php
- ItPack\RunIt\Contract\Domain\ExecutionResultInterface
- RunIt
- OpenActivityPolicyTest.php
- ObservationId
- RunIt Documentation Plan — Reasoning & Suggestions
- CloudEventMappingPolicy
- BridgeConfigBuilder
- IdentifierTest.php
- ItPack\RunIt\Contract\Domain\ExecutionStatusInterface
- RunItBuilderInterface.php
- RunIt — Refactoring Plan
- 3. Detailed findings
- RunItTestKit
- ContextKey
- ItPack\RunIt\Contract\Domain\ActivityStatusInterface
- TelemetryPublisher
- AttributeValidatorTest
- 3. Detailed findings
- 3. Detailed findings
- M14.8 — Third-Party Usability Validation: Reasoning and Plan
- Phase 3 — P3: polish, hygiene, and measured modernization
- RunIt — Refactoring Plan
- EXECUTION.md
- ItPack\RunIt\Contract\Application\NestedExecutionPolicyInterface
- ObservationRecordingLoggerTest
- ItPack\RunIt\Contract\Exception\InvalidValueExceptionInterface
- BridgeConfig
- RunIt — Refactoring Plan
- Milestone M12 — Security, Privacy, Performance, and Resilience
- InvalidCloudEventException
- StringValueEquality
- require-dev
- CloudEventJsonSerializerTest
- ContextClassification.php
- composer.json
- 3. Findings
- ContextEntry.php
- CloudEventPublisher.php
- ItPack\RunIt\Contract\Domain\ExecutionContextInterface
- PhpParser\Node
- CompositeContextStorageTest
- OtelTestKit
- RunIt Architecture Performance
- Milestone M14 — Documentation, Dogfooding, and ItPack Adoption
- ItPack\RunIt\Contract\Application\ExecutionEngineInterface
- InvalidAttributeException
- ContextEntry
- PHPStan\Analyser\Scope
- ExecutionSuccess
- SecurityLimits
- RedactingPublisherTest
- SamplingTest
- Codeception\Actor
- Prioritized findings
- InvalidStringValueException
- OpenTelemetry\API\Trace\SpanContextInterface
- DefaultMeasurementInstrumentMapper
- PHPStan\Rules\Rule
- ActivityInterface.php
- ExecutionInterface.php
- HttpAdaptersTest
- Backward compatibility
- Per-adapter behaviour
- PSR interoperability
- InvalidArgumentException
- ItPack\RunIt\Contract\Domain\ContextClassificationInterface
- ItPack\RunIt\Contract\Domain\DurationInterface
- ProcessResult
- ExecutionContextInterface.php
- Duration
- JsonSnapshotSerializerTest
- FailureHandlingTest
- DefaultExecutionRunnerTest
- ReflectionFunctionAbstract
- Milestone M11 — Fiber and Swoole/OpenSwoole Execution
- Milestone M13 — Conformance Tooling and QA Completion
- Milestone M1 — Shared Domain Primitives
- Milestone M4 — Execution Lifecycle Runtime
- WorkerLifecycleReporter
- AttributeKey
- ContextValueValidator.php
- ExecutionScopeInterface.php
- OccurrenceInterface.php
- ActivityId
- RunItPortConformanceTest
- FilteringPublisherTest
- DefaultRunItTest
- Recorded baselines (2026-08-23)
- RunIt Refactoring Plan
- Milestone M6 — Artifact Publication Pipeline
- DefaultExecutedAsMetadataReader
- ContextEntryInterface.php
- SnapshotInterface.php
- FiberContextStorageTest
- RunIt Agent Guide
- ExecutionBench
- suggest
- RunIt Architecture Security
- Changelog
- ContextSource
- HmacSnapshotSigner
- DefaultArtifactRedactor
- .runExecution
- MeasurementUnit.php
- InvalidContextValueException
- ActivitySpanMapperTest
- BasicPublisherTest
- NamesAndKindsTest
- ExecutedCallableTest
- AbstractSrcRule
- By theme
- RunIt Context Propagation
- RunIt Self-Instrumentation
- Activity
- CloudEvents integration
- OpenTelemetry integration
- Milestone M10 — CloudEvents Integration
- Milestone M2 — Immutable Execution Context
- Milestone M5 — Activity Runtime and Artifact Recorders
- Milestone M7 — Programmatic SDK and Non-Intrusive Adoption
- Milestone M8 — PSR Interoperability
- ExecutionDefinitionInterface.php
- MeasurementInterface.php
- DeterministicMonotonicClock
- RunIt Context Propagation
- .snapshot
- DefaultThrowableMapperTest
- TimeTest
- DogfoodRunner.php
- DevTool
- quality
- 22.1 Testing layers
- PublicationGuard
- Execution
- Snapshot
- Time and sequencing
- RunIt dogfooding
- Milestone M0 — Repository and Architecture Foundation
- M12.8 Supply-Chain Security
- StringIdentifierFactoryTest
- CloudEventPublisher
- .run
- ObservationInterface.php
- TimeoutInterface.php
- PublicationPipelineTest
- RoutingArtifactPublisherTest
- ComplexityGuardRule
- allow-plugins
- keywords
- RunIt Hexagonal Architecture
- CloudEventJsonSerializerTest
- Execution context
- ObservationRecordingLoggerTest
- Measurement
- Psr\Http\Client\ClientInterface
- Occurrence
- Port Conformance Verification
- RunIt Ecosystem
- RunIt Documentation
- RunIt 1.0.0-rc.1 — Release-candidate preparation (M15.1)
- RunIt Project Review
- Security Policy
- RunIt Use Cases
- RunIt Vocabulary
- Psr20Clock
- FiberRuntimeDetector
- SnapshotIntegrityException
- BoundedPrimitiveValueWalker
- RunItPortFixture
- DefaultActivityRunnerTest
- ThrowableDescriptionInterface.php
- MonotonicClockTest
- PublicationPipelineTest
- IdentifierPropertyTest
- .definition
- .scope
- RedactorVerifier.php
- ExecutionContextRedactionTest
- DogfoodReport
- StringValueValidatorTest
- DogfoodReport
- ExecutionMiddleware
- psr-4
- DefaultContextRedactionPolicy.php
- Attributes
- ContextValueValidatorTest
- Optional dependencies
- RunIt + Swoole / OpenSwoole
- M12.1 Data-Protection Threat Model
- Tutorial 7 — Publish & integrate
- Upgrading RunIt
- RunItPortFixture
- ActivityDefinitionInterface.php
- create
- FiberExecutionEngineTest
- ContextSource
- DefaultActivityRunnerTest
- ForbiddenMixedTypeRule
- 18. PHP 8.4 Language Usage
- Throwable normalization
- NoMutableStaticPropertyRule
- REFACTORING_PLAN.md
- Getting Started with RunIt
- CancellationInterface.php
- Duration.php
- DogfoodRunnerTest
- SnapshotClassificationInterface.php
- .definition
- AttributionTarget
- SynchronousExecutionEngineTest
- test
- Contributing to RunIt
- RunIt Architecture Dependency Graph
- 14. OpenTelemetry Adapter
- 5. Measurable Success Criteria
- RequireInterfaceRule
- MeasurementKind.php
- Recipe: Snapshot Integrity and Authenticity
- SerializerVerifier
- bug_report.md
- ValidatorParityTest
- ExecutionEngineInterface.php
- PublicationGuardInterface.php
- ActivityStatusInterface.php
- ExecutionResultInterface.php
- ExecutionStatusInterface.php
- DogfoodRunner
- RunIt + Fibers
- Tutorial 5 — Context & classification
- Tutorial 6 — Nesting, flow & errors
- ForbidReflectionOnHotPathRule
- RequireFinalRule
- WorkerLifecycleInterface.php
- Getting Started with RunIt
- Tutorial 1 — Hello, execution
- 2. Roadmap Rules
- 15. CloudEvents Adapter
- 19. Self-Instrumentation
- 20. Security and Privacy
- 21. Performance and Resource Management
- Measure composite context storage ownership overhead
- Resolve context refactoring quality gate failures
- Complete context ownership and runtime validation
- adapter_proposal.md
- architecture_decision.md
- feature_proposal.md
- SerializerInterface.php
- BridgeCapability
- SystemMonotonicClock
- DefaultObservationToPsr3LevelMapper.php
- SwooleRuntimeDetector
- ArtifactSequenceInterface.php
- .observation
- allocate
- BoundedPrimitiveValueWalker
- Psr7RequestContextExtractor
- LineLengthLint
- 3. Business Goals
- Phase 8 — Release Candidate and Adoption, Week 12
- Phase 0 — Architectural Ratification, Week 1
- Phase 7 — Hardening, Week 11
- Phase 1 — Domain Kernel, Weeks 2–3
- Phase 2 — Execution Runtime, Weeks 4–5
- Phase 3 — Publication Pipeline, Week 6
- Phase 4 — SDK and Non-Intrusive Adoption, Week 7
- Phase 5 — OpenTelemetry and PSR Adapters, Weeks 8–9
- Phase 6 — CloudEvents and Runtime Adapters, Week 10
- pull_request_template.md
- phpbench.json
- examples.sh
- isPropagatable
- redact
- isSerializable
- 6.1 Execution
- 7. Hexagonal Architecture
- Cross-Cutting Verification Matrix
- ForbidReflectionOnHotPathRule
- CurrentExecutionInterface.php
- ContextValueValidatorTest

## God Nodes (most connected - your core abstractions)
1. `ExecutionId` - 162 edges
2. `ExecutionDefinition` - 106 edges
3. `ExecutionKind` - 94 edges
4. `ExecutionName` - 92 edges
5. `AttributeCollection` - 77 edges
6. `RunIt Implementation Plan` - 75 edges
7. `RunItBuilder` - 74 edges
8. `SecurityLimits` - 64 edges
9. `Snapshot` - 59 edges
10. `RunItArtifactFactory` - 59 edges

## Surprising Connections (you probably didn't know these)
- `BufferedAdmissionBench` --references--> `BufferedArtifactPublisher`  [EXTRACTED]
  benchmarks/BufferedAdmissionBench.php → src/Application/Publication/BufferedArtifactPublisher.php
- `BufferedFlushBench` --references--> `BufferedArtifactPublisher`  [EXTRACTED]
  benchmarks/BufferedFlushBench.php → src/Application/Publication/BufferedArtifactPublisher.php
- `CloudEventBench` --references--> `CloudEvent`  [EXTRACTED]
  benchmarks/CloudEventBench.php → src/Adapter/CloudEvents/CloudEvent.php
- `CloudEventBench` --references--> `DefaultOccurrenceToCloudEventMapper`  [EXTRACTED]
  benchmarks/CloudEventBench.php → src/Adapter/CloudEvents/Mapping/DefaultOccurrenceToCloudEventMapper.php
- `CloudEventBench` --references--> `Occurrence`  [EXTRACTED]
  benchmarks/CloudEventBench.php → src/Domain/Occurrence/Occurrence.php

## Import Cycles
- None detected.

## Communities (400 total, 94 thin omitted)

### Community 0 - "ItPack\RunIt\Contract\Port\ArtifactPublisherInterface"
Cohesion: 0.12
Nodes (6): ItPack\RunIt\Contract\Domain\ContextCollisionPolicy, ItPack\RunIt\Contract\Domain\ContextEntryInterface, ExecutionContext, ContextCollisionPolicy, self, ExecutionContextTest

### Community 1 - "ItPack\RunIt\Contract\Domain\ExecutionInterface"
Cohesion: 0.07
Nodes (15): ItPack\RunIt\Contract\Application\Publication\PublicationFailurePolicyInterface, CollectAndContinuePolicy, FailClosedPolicy, FailOpenPolicy, self, RecursivePublicationException, ReportToFallbackPolicy, report() (+7 more)

### Community 2 - "ItPack\RunIt\Contract\Domain\ExecutionIdInterface"
Cohesion: 0.06
Nodes (9): ItPack\RunIt\SDK\Builder\RunItBuilderInterface, RunItBuilderInterface, RunItBuilder, MissingBuilderDependencyException, self, RunItFactories, RunItBuilderInterface, RedactedTagArtifact (+1 more)

### Community 3 - "ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface"
Cohesion: 0.14
Nodes (10): ItPack\RunIt\Adapter\Psr\Contract\HttpContextExtractorInterface, ItPack\RunIt\Adapter\Psr\Contract\ResponseCorrelationEnricherInterface, ItPack\RunIt\Contract\Shared\HttpExecutionContextInterface, Psr\Http\Message\ServerRequestInterface, Psr\Http\Server\MiddlewareInterface, extract(), ExecutionMiddleware, Psr7RequestContextExtractor (+2 more)

### Community 4 - "ItPack\RunIt\Contract\Domain\OccurrenceInterface"
Cohesion: 0.07
Nodes (10): ItPack\RunIt\Behavior\Domain\SequencedBehavior, ItPack\RunIt\Contract\Domain\ContextValueValidatorInterface, ItPack\RunIt\Contract\Domain\OccurrenceIdInterface, ItPack\RunIt\Contract\Domain\OccurrenceTypeInterface, ItPack\RunIt\Contract\Domain\SequencedInterface, ItPack\RunIt\Contract\Domain\StringIdentifierInterface, equals(), record() (+2 more)

### Community 5 - "ConformanceResult"
Cohesion: 0.05
Nodes (27): ItPack\RunIt\Adapter\Swoole\Contract\CoroutineIdProviderInterface, ItPack\RunIt\Contract\Application\ExecutionScopeInterface, ItPack\RunIt\Contract\Port\ContextStorageInterface, ItPack\RunIt\Contract\Port\ContextTokenInterface, FiberContextStorage, FiberContextToken, CoroutineContextStorage, CoroutineContextToken (+19 more)

### Community 6 - "ConformanceViolations"
Cohesion: 0.05
Nodes (10): ArtifactPublisherVerifierNegativeTest, ContextStorageVerifierNegativeTest, ExecutionEngineVerifierNegativeTest, IdentifierGeneratorVerifierNegativeTest, MonotonicClockVerifierNegativeTest, RedactorVerifierNegativeTest, SamplingPolicyVerifierNegativeTest, SerializerVerifierNegativeTest (+2 more)

### Community 7 - "ItPack\RunIt\Contract\Domain\SnapshotInterface"
Cohesion: 0.09
Nodes (7): ItPack\RunIt\Contract\Domain\SnapshotClassificationInterface, ItPack\RunIt\Contract\Domain\SnapshotIdInterface, ItPack\RunIt\Contract\Domain\SnapshotTypeInterface, record(), static, ImpureNormalizeSnapshot, NoopSnapshotRecorder

### Community 8 - "RunItBuilder"
Cohesion: 0.12
Nodes (7): ItPack\RunIt\Adapter\CloudEvents\Contract\CloudEventToOccurrenceMapperInterface, ItPack\RunIt\Adapter\Psr\Contract\PsrEventToOccurrenceMapperInterface, ItPack\RunIt\Contract\Domain\OccurrenceInterface, OpenTelemetry\SDK\Trace\TracerProvider, DefaultCloudEventToOccurrenceMapper, map(), DefaultPsrEventToOccurrenceMapper

### Community 9 - "ItPack\RunIt\Contract\Port\ContextStorageInterface"
Cohesion: 0.12
Nodes (10): ItPack\RunIt\Adapter\Swoole\Contract\RequestContextExtractorInterface, ItPack\RunIt\Contract\Application\ServerExecutionMiddlewareInterface, ItPack\RunIt\Contract\Domain\ExecutionKindInterface, ItPack\RunIt\Contract\Domain\ExecutionNameInterface, HttpExecutionContext, ServerExecutionMiddleware, getContext(), getKind() (+2 more)

### Community 10 - "ExecutionDefinition"
Cohesion: 0.09
Nodes (12): ExecutionDefinition, ExecutionKind, ExecutionName, InterleavedFiberContextIsolationTest, PersistentWorkerReuseTest, DefaultMeasurementRecorderTest, DefaultObservationRecorderTest, DefaultOccurrenceRecorderTest (+4 more)

### Community 11 - "RunIt Implementation Plan"
Cohesion: 0.04
Nodes (55): 10.1 `ExecutionRunner`, 10.2 Reentrancy and nested executions, 10.3 Open activity handling, 10. Runtime Coordination, 11.1 Canonical API, 11.2 Fluent immutable SDK, 11.3 Non-intrusive callable decorator, 11.4 Object decorator (+47 more)

### Community 12 - "ROADMAP.md"
Cohesion: 0.08
Nodes (11): ItPack\RunIt\Contract\Application\CurrentExecutionInterface, ItPack\RunIt\Domain\Observation\ObservationSeverity, LoggerCollisionPolicy, Psr\Log\AbstractLogger, Psr\Log\LoggerInterface, ExecutionAwareLogger, ObservationRecordingLogger, ObservationSeverity (+3 more)

### Community 13 - "README.md"
Cohesion: 0.04
Nodes (28): ADR-001: Execution is the root aggregate, ADR-002: Domain artifacts are immutable, ADR-003: Artifact ownership uses identity relationships, ADR-004: Explicit context propagation is canonical, ADR-005: Ambient context is adapter-backed, ADR-006: External standards are adapters, ADR-007: Publication fails open by default, ADR-008: Original business exceptions have priority (+20 more)

### Community 14 - "ItPack\RunIt\Contract\Domain\ObservationInterface"
Cohesion: 0.05
Nodes (20): IteratorAggregate, ItPack\RunIt\Conformance\Contract\VerifierInterface, ItPack\RunIt\Contract\Port\IdentifierGeneratorInterface, RandomIdentifierGenerator, ConformanceResult, self, ConformanceViolation, IdentifierGeneratorVerifier (+12 more)

### Community 15 - "ItPack\RunIt\Contract\Domain\ActivityIdInterface"
Cohesion: 0.09
Nodes (10): InvalidArgumentException, SerializationExceptionInterface, ConfigurableHashCalculator, fromString(), static, SerializationException, InvalidIdentifierException, self (+2 more)

### Community 16 - "ExecutionId.php"
Cohesion: 0.13
Nodes (20): ItPack\RunIt\Behavior\Application\InstantClockBehavior, ItPack\RunIt\Contract\Application\ExecutionSnapshotFactoryInterface, ItPack\RunIt\Contract\Application\SnapshotRecorderInterface, ItPack\RunIt\Contract\Domain\Factory\StringIdentifierFactoryInterface, ItPack\RunIt\Contract\Domain\HashCalculatorInterface, ItPack\RunIt\Contract\Domain\SequenceAllocatorInterface, ItPack\RunIt\Contract\Domain\ThrowableMapperInterface, ItPack\RunIt\Contract\Port\MonotonicClockInterface (+12 more)

### Community 17 - "ItPack\RunIt\Contract\Domain\AttributeCollectionInterface"
Cohesion: 0.15
Nodes (14): ItPack\RunIt\Contract\Domain\AttributeCollectionInterface, ItPack\RunIt\Contract\Domain\MeasurementInterface, ItPack\RunIt\Contract\Domain\MeasurementKindInterface, ItPack\RunIt\Contract\Domain\MeasurementNameInterface, ItPack\RunIt\Contract\Domain\MeasurementUnitInterface, record(), DefaultMeasurementRecorder, counter() (+6 more)

### Community 19 - "RunItArtifactFactory"
Cohesion: 0.06
Nodes (8): TelemetryPublisher, RunItArtifactFactory, CloudEventPublisherTest, OccurrenceToCloudEventMapperTest, FiberAwareIntegrationTest, OccurrenceLogMapperTest, ContextPropagationTest, TelemetryPublisherTest

### Community 21 - "README.md"
Cohesion: 0.25
Nodes (8): Classification, Collection and merging, Entries and values, Execution context, Redaction, Security posture, Standard vocabulary, Types

### Community 22 - "ExecutionKind.php"
Cohesion: 0.07
Nodes (15): ItPack\RunIt\Contract\Domain\ObservationInterface, ItPack\RunIt\Contract\Domain\ObservationSeverityInterface, OpenTelemetry\API\Logs\Severity, map(), toNumber(), toSeverity(), toText(), toLevel() (+7 more)

### Community 23 - "getting-started.md"
Cohesion: 0.33
Nodes (6): Nesting activities, Next, Running an activity, Tutorial 2 — Activities, What you learned, Why activities?

### Community 24 - "Codeception\Test\Unit"
Cohesion: 0.11
Nodes (5): ItPack\RunIt\Contract\Domain\ActivityDefinitionInterface, ItPack\RunIt\Contract\Domain\ActivityStatusInterface, run(), Activity, self

### Community 25 - "FilteringPublisherTest.php"
Cohesion: 0.38
Nodes (3): CompositeContextStorageTest, FakeStorage, ExecutionScopeInterface

### Community 26 - "BufferingTest"
Cohesion: 0.22
Nodes (3): ItPack\RunIt\Application\Publication\OverflowStrategy, BufferingTest, OverflowStrategy

### Community 27 - "Closure"
Cohesion: 0.06
Nodes (12): ProcessRunnerInterface, DogfoodRunnerTest, FakeProcessRunner, Tools\Dogfood\Process\ProcessRunnerInterface, ProcessResult, run(), ShellProcessRunner, DogfoodReport (+4 more)

### Community 28 - "RunIt-authored rules"
Cohesion: 0.05
Nodes (37): API compatibility (M13.6), Bleeding-edge analysis (non-blocking), Blocker fixed to enable mutation testing, Coding style (M13.4), Complexity guard (Phase 3, T3.2), Contract enums without interfaces (deliberate), Dogfood teardown abort fixed (REVIEW §3 P1-2, plan T1.1), Exception checks (+29 more)

### Community 29 - "ExecutionAwareLoggerTest.php"
Cohesion: 0.14
Nodes (11): ItPack\RunIt\Adapter\OpenTelemetry\Contract\ActivitySpanMapperInterface, ItPack\RunIt\Adapter\OpenTelemetry\Contract\ExecutionSpanMapperInterface, ItPack\RunIt\Adapter\OpenTelemetry\Contract\MeasurementInstrumentMapperInterface, ItPack\RunIt\Adapter\OpenTelemetry\Contract\ObservationLogMapperInterface, ItPack\RunIt\Adapter\OpenTelemetry\Contract\OccurrenceLogMapperInterface, ItPack\RunIt\Adapter\OpenTelemetry\Contract\SeverityMapInterface, DefaultObservationLogMapper, Severity (+3 more)

### Community 30 - "ItPack\RunIt\Contract\Domain\StringIdentifierInterface"
Cohesion: 0.17
Nodes (10): ItPack\RunIt\Contract\Application\NestedExecutionMode, ItPack\RunIt\Contract\Application\NestedExecutionPolicyInterface, ActivityOnlyPolicy, NestedExecutionMode, ChildExecutionPolicy, NestedExecutionMode, DetachedExecutionPolicy, NestedExecutionMode (+2 more)

### Community 31 - "ItPack\RunIt\Contract\Domain\ActivityInterface"
Cohesion: 0.06
Nodes (16): ExampleVendorPublisher, ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface, ItPack\RunIt\Contract\Port\ArtifactByteEstimatorInterface, DefaultArtifactByteEstimator, BoundedArtifactPublisher, RedactedArtifact, accepts(), test() (+8 more)

### Community 32 - "OccurrenceType"
Cohesion: 0.11
Nodes (4): NestedSnapshotBench, ArtifactRedactionConfig, self, DefaultArtifactRedactor

### Community 33 - "CloudEvent"
Cohesion: 0.09
Nodes (9): CloudEvent, self, create(), deserialize(), serialize(), send(), map(), CloudEventSinkTest (+1 more)

### Community 34 - "SnapshotType"
Cohesion: 0.07
Nodes (11): BufferedAdmissionBench, ItPack\RunIt\Contract\Domain\ExecutionSnapshotInterface, terminal(), ExecutionSnapshot, Snapshot, SnapshotId, self, SnapshotType (+3 more)

### Community 35 - "ActivityDefinition"
Cohesion: 0.10
Nodes (7): ActivityKind, ActivityName, OpenActivityPolicyTest, ObservationInterface, ObservationSeverity, NestedBoundariesTest, NamesAndKindsTest

### Community 36 - "README.md"
Cohesion: 0.09
Nodes (9): RunIt Coding and Architecture Standards, RunIt-specific supplements, Recipe: configuration resolution, Recipe: nested activity, Recipe: queue job, Recipe: scheduler, Recipe: testing, Recipe: third-party adapter (+1 more)

### Community 37 - "8. Findings register"
Cohesion: 0.06
Nodes (32): 10. Performance assessment, 11. Maintainability & DX, 12. Verification, 13. Recommended follow-ups (ordered), 1. Assumptions, 2. Method, 3. Executive summary, 4. Feature assessment (+24 more)

### Community 38 - "ItPack\RunIt\Contract\Domain\ExecutionKindInterface"
Cohesion: 0.27
Nodes (4): IdentifierFactoryMarkerInterface, ItPack\RunIt\Common\Contract\IdentifierFactoryMarkerInterface, build(), StringIdentifierFactoryTest

### Community 39 - "ExecutionId"
Cohesion: 0.07
Nodes (7): ExecutionFailure, ExecutionSuccess, self, ThrowableDescription, ExecutionLifecycleCoordinatorTest, ExecutionTest, ThrowableDescriptionTest

### Community 40 - "ItPack\RunIt\Contract\Application\ExecutionRunnerInterface"
Cohesion: 0.08
Nodes (14): ItPack\RunIt\Contract\Application\ActivityRunnerInterface, ItPack\RunIt\Contract\Application\ActivityScopeInterface, ItPack\RunIt\Contract\Application\ExecutionLifecycleCoordinatorInterface, ItPack\RunIt\Contract\Application\MeasurementRecorderInterface, ItPack\RunIt\Contract\Application\ObservationRecorderInterface, ItPack\RunIt\Contract\Application\OccurrenceRecorderInterface, ItPack\RunIt\Contract\Domain\ExecutionInterface, map() (+6 more)

### Community 41 - "BufferedArtifactPublisher"
Cohesion: 0.13
Nodes (8): ItPack\RunIt\Contract\Exception\LifecycleExceptionInterface, canTransitionTo(), label(), ConflictingActivityCompletionException, self, InvalidActivityTransitionException, self, ExceptionMarkerContractTest

### Community 42 - "RuntimeException"
Cohesion: 0.07
Nodes (20): ItPack\RunIt\Contract\Exception\RunItExceptionInterface, LogicException, RuntimeException, BoundDropException, self, BufferOverflowException, self, SamplingDropException (+12 more)

### Community 43 - "ExecutionContext"
Cohesion: 0.07
Nodes (29): ExecutedCallableFactoryInterface, ExecutedCallableInterface, ItPack\RunIt\Contract\Application\ExecutionRunnerInterface, ItPack\RunIt\Contract\Domain\ExecutionDefinitionInterface, ItPack\RunIt\SDK\Attribute\ExecutedAsMetadataReaderInterface, ItPack\RunIt\SDK\Decorator\ExecutedCallableFactoryInterface, ItPack\RunIt\SDK\Decorator\ExecutedCallableInterface, ItPack\RunIt\SDK\Execution\MetadataExecutionDefinitionFactoryInterface (+21 more)

### Community 44 - "RunIt — In-Depth Review"
Cohesion: 0.07
Nodes (28): 10.1 Already correct, 10.2 Opportunities — [DERIVED], 10. PHP 8.4 feature leverage, 11. Security — [VERIFIED for S1–S6], 12. Performance — [DERIVED/VERIFIED], 13. DX, 14. Maintainability, 15. Testing gaps (+20 more)

### Community 45 - "HttpAdaptersTest.php"
Cohesion: 0.22
Nodes (8): ItPack\RunIt\Adapter\OpenTelemetry\Contract\ExecutionSpanStoreInterface, OpenTelemetry\API\Trace\SpanContextInterface, activityContext(), executionContext(), recordActivity(), recordExecution(), InMemoryExecutionSpanStore, self

### Community 47 - "ExecutionContext.php"
Cohesion: 0.22
Nodes (3): RunItPortFixture, Instant, self

### Community 48 - "Phase 0 — P0 correctness & security (do first, blocks release)"
Cohesion: 0.07
Nodes (27): Ordering & dependencies, Phase 0 — P0 correctness & security (do first, blocks release), Phase 1 — P1 invariants & contracts (magic values, clocks), Phase 2 — P2 mechanical DRY, factories, interfaces, feature leverage, Phase 3 — P3 guardrails & hygiene, RunIt — Refactoring Plan, T0.1 — S1: wire secret context redaction into normalization — **DONE 2026-08-21**, T0.2 — S2: bound serializer recursion for objects — **DONE 2026-08-21** (+19 more)

### Community 50 - "AttributeCollection"
Cohesion: 0.11
Nodes (3): AttributeCollection, self, AttributeCollectionTest

### Community 55 - "scripts"
Cohesion: 0.08
Nodes (26): scripts, analyse, analyse:bleeding-edge, api:baseline, api:check, architecture, benchmark, coverage (+18 more)

### Community 56 - "RunIt — Refactoring Plan"
Cohesion: 0.08
Nodes (25): Cross-cutting rules for all phases, Milestone ordering & exit criteria, Out of scope (recorded, not planned), Phase 1 — Mechanical normalization (behavior-free), Phase 2 — Guards: make the categories non-regrowable, Phase 3 — Structural decisions: interface backing & vocabulary homes, Phase 4 — Mutation testing: enforce, don't admire, Phase 5 — Opportunistic modernization (each task independently skippable) (+17 more)

### Community 57 - "TelemetryPublisher.php"
Cohesion: 0.12
Nodes (5): AttributionCollaborator, AttributionTarget, AllowedTest, AttributionTest, NoMetadataTest

### Community 58 - "ExecutionMiddleware.php"
Cohesion: 0.11
Nodes (8): ArrayObject, OpenTelemetry\SDK\Logs\Exporter\InMemoryExporter, OpenTelemetry\SDK\Logs\ReadableLogRecord, OpenTelemetry\SDK\Metrics\Data\Metric, OpenTelemetry\SDK\Metrics\MetricExporter\InMemoryExporter, OpenTelemetry\SDK\Metrics\MetricReader\ExportingReader, OtelTestKit, ObservationLogMapperTest

### Community 59 - "ItPack\RunIt\Contract\Domain\ExecutionResultInterface"
Cohesion: 0.07
Nodes (13): Closure, CallbackHealthReporter, ErrorLogHealthReporter, ExecutionKindPredicate, FallbackErrorLogReporter, RatioSamplePolicy, executionId(), ExecutionIdInterface (+5 more)

### Community 60 - "RunIt"
Cohesion: 0.08
Nodes (26): Architecture, Coding standards, Contributing, Core Concepts, Dependency architecture, Design Philosophy, Development, Documentation (+18 more)

### Community 61 - "OpenActivityPolicyTest.php"
Cohesion: 0.13
Nodes (8): ItPack\RunIt\Contract\Application\ExecutionCompletionPolicyInterface, AutoCancelOpenActivitiesPolicy, AutoFailOpenActivitiesPolicy, FailExecutionOnOpenActivitiesPolicy, WarnAndIgnoreOpenActivitiesPolicy, isError(), level(), ThrowingCompletionPolicy

### Community 63 - "RunIt Documentation Plan — Reasoning & Suggestions"
Cohesion: 0.08
Nodes (24): 10. Open questions for the maintainer, 11. References (existing files this plan builds on), 1. Thesis, 2.1 What already exists (strengths), 2.2 Key weaknesses, 2. Current documentation inventory & assessment, 3. Identified gaps & inconsistencies (consolidation targets), 4. Proposed information architecture (sitemap) (+16 more)

### Community 64 - "CloudEventMappingPolicy"
Cohesion: 0.08
Nodes (17): ItPack\RunIt\Adapter\OpenTelemetry\Behavior\TraceBehavior, ItPack\RunIt\Contract\Domain\ActivityInterface, OpenTelemetry\API\Trace\SpanInterface, OpenTelemetry\Context\ContextInterface, resolveParentContext(), startSpan(), map(), extractContext() (+9 more)

### Community 65 - "BridgeConfigBuilder"
Cohesion: 0.08
Nodes (17): ItPack\RunIt\Adapter\Psr\Contract\ObservationToPsr3LevelMapperInterface, ItPack\RunIt\Conformance\Contract\PortFixtureInterface, ItPack\RunIt\Contract\Application\Publication\PublicationGuardInterface, ItPack\RunIt\Contract\Application\Publication\RedactorInterface, ItPack\RunIt\Contract\Port\PublisherStackFactoryInterface, PublicationPipelineInterface, DefaultObservationToPsr3LevelMapper, ObservationLoggerPublisher (+9 more)

### Community 66 - "IdentifierTest.php"
Cohesion: 0.15
Nodes (4): CloudEventBench, DefaultCloudEventJsonSerializer, InvalidCloudEventException, self

### Community 67 - "ItPack\RunIt\Contract\Domain\ExecutionStatusInterface"
Cohesion: 0.10
Nodes (19): ItPack\RunIt\Adapter\OpenTelemetry\Contract\ContextToOtelMapperInterface, ItPack\RunIt\Contract\Domain\ExecutionContextInterface, OpenTelemetry\API\Baggage\BaggageInterface, injectIntoCarrier(), toBaggage(), DefaultContextToOtelMapper, extract(), activities() (+11 more)

### Community 68 - "RunItBuilderInterface.php"
Cohesion: 0.13
Nodes (26): ItPack\RunIt\Contract\Application\ExecutionFactoryInterface, ExecutionFactory, build(), create(), RunItBuilderInterface, withClock(), withCompletionPolicy(), withContextStorage() (+18 more)

### Community 69 - "RunIt — Refactoring Plan"
Cohesion: 0.08
Nodes (23): Cross-cutting rules for all phases, Milestone ordering & exit criteria, Out of scope (recorded, not planned), Phase 0 — Verification gap: raise statement coverage to ≥95% (REVIEW §3 P1-1), Phase 1 — Unblock local mutation testing; set an MSI floor (REVIEW §3 P1-2), Phase 2 — Conformance kit self-exercise (REVIEW §3 P1-3), Phase 3 — Mechanical hygiene passes (REVIEW §3 P2-1..P2-3), Phase 4 — Cosmetic residues (REVIEW §3 P3-1..P3-3) (+15 more)

### Community 70 - "3. Detailed findings"
Cohesion: 0.08
Nodes (23): 1. Conceptual review checklist, 2. Summary scorecard, 3.10 Developer experience & maintainability, 3.11 Quality gates executed in this environment, 3.1 Architecture & hexagonal boundaries, 3.2 SOLID, 3.3 DRY, 3.4 Interface coverage (+15 more)

### Community 71 - "RunItTestKit"
Cohesion: 0.21
Nodes (3): self, SecurityLimits, SecurityLimitsTest

### Community 72 - "ContextKey"
Cohesion: 0.20
Nodes (4): ItPack\RunIt\Contract\Application\WorkerLifecycleInterface, self, WorkerLifecycleReporter, WorkerLifecycleReporterTest

### Community 73 - "ItPack\RunIt\Contract\Domain\ActivityStatusInterface"
Cohesion: 0.07
Nodes (21): ItPack\RunIt\Contract\Application\CancellationMapperInterface, ItPack\RunIt\Contract\Application\TimeoutMapperInterface, ItPack\RunIt\Contract\Domain\CancellationInterface, ItPack\RunIt\Contract\Domain\ExecutionResultInterface, ItPack\RunIt\Contract\Domain\ExecutionStatusInterface, ItPack\RunIt\Contract\Domain\TimeoutInterface, ItPack\RunIt\Contract\Exception\InvalidValueExceptionInterface, map() (+13 more)

### Community 75 - "AttributeValidatorTest"
Cohesion: 0.20
Nodes (10): M5.1 Activity runtime contracts, M5.2 Activity runner, M5.3 Open activity policies, M5.4 Measurement recorder, M5.5 Observation recorder, M5.6 Occurrence recorder, M5.7 Snapshot recorder, M5 acceptance gate (+2 more)

### Community 76 - "3. Detailed findings"
Cohesion: 0.09
Nodes (21): 1. Conceptual review checklist, 2. Summary scorecard, 3.10 Quality gates, 3.1 Architecture & hexagonal boundaries, 3.2 SOLID, 3.3 DRY, 3.4 Interface coverage, 3.5 Control-flow, complexity & magic values (+13 more)

### Community 77 - "3. Detailed findings"
Cohesion: 0.09
Nodes (21): 1. Conceptual review checklist, 2. Summary scorecard, 3.1 Architecture & hexagonal boundaries, 3.2 SOLID & interface coverage, 3.3 DRY, 3.4 Control flow, complexity & magic values, 3.5 Lifecycle, publication & the exception-preservation hole, 3.6 Security (+13 more)

### Community 78 - "M14.8 — Third-Party Usability Validation: Reasoning and Plan"
Cohesion: 0.09
Nodes (21): 10. Implementation status (delivered), 1. TL;DR, 2.1 Conformance toolkit — already shipped and exported, 2.2 First-party examples and docs, 2.3 The real gap, 2. Current state (what already exists), 3. Defining "third party" for this milestone, 4. Proposed deliverable structure (+13 more)

### Community 79 - "Phase 3 — P3: polish, hygiene, and measured modernization"
Cohesion: 0.10
Nodes (20): Cross-cutting notes, Phase 0 — P0: gate health & exception-preservation guarantee, Phase 1 — P1: structural lifecycle invariants & real exception markers, Phase 2 — P2: DRY consolidation, bounded caches, hot-path cost, Phase 3 — P3: polish, hygiene, and measured modernization, RunIt — Refactoring Plan, T0.1 — Add `withSecurityLimits()` and health-callback seams to `RunItBuilder` (REVIEW.md §3.10, §3.7, §4 P0-1), T0.2 — Guarantee original-exception preservation in `completeFailure()` (REVIEW.md §3.7, §4 P0-2) (+12 more)

### Community 80 - "RunIt — Refactoring Plan"
Cohesion: 0.10
Nodes (19): Cross-cutting notes, Phase 0 — P0: exception-preservation & observability holes, Phase 1 — P1: real security default & bounded-state defects, Phase 2 — P2: DRY residue & contract hygiene, Phase 3 — P3: decisions, defensive edges, polish, RunIt — Refactoring Plan, T0.1 — Guard all health-callback invocations (REVIEW.md §3.5.1, §3.6, §4 P0-1), T0.2 — Report pre-try-block faults (REVIEW.md §3.5.2, §4 P0-2) (+11 more)

### Community 81 - "EXECUTION.md"
Cohesion: 0.10
Nodes (18): Activity, Adapter Mapping, Concept reference, ConfigureIt Example, Design Principles, Execution, Execution Context, Future Packages (+10 more)

### Community 83 - "ObservationRecordingLoggerTest"
Cohesion: 0.10
Nodes (9): ItPack\RunIt\Contract\Domain\ContextKeyInterface, ItPack\RunIt\Contract\Domain\ContextSourceInterface, ContextEntry, self, ContextKey, self, ContextEntryTest, ContextKeyTest (+1 more)

### Community 84 - "ItPack\RunIt\Contract\Exception\InvalidValueExceptionInterface"
Cohesion: 0.12
Nodes (3): ExecutionContextInterface, DeterministicHashCalculator, CurrentExecutionTest

### Community 85 - "BridgeConfig"
Cohesion: 0.15
Nodes (5): ItPack\RunIt\Contract\Application\Publication\BatchFlushObserverInterface, BufferConfiguration, OverflowStrategy, self, BufferedArtifactPublisher

### Community 86 - "RunIt — Refactoring Plan"
Cohesion: 0.11
Nodes (18): Cross-cutting notes, Phase 0 — P0: make `composer analyse` pass (release-blocking), Phase 1 — P1: close the DIP leak & sweep dead code, Phase 2 — P2: DRY, assembly & modern-PHP leverage, Phase 3 — P3: polish, RunIt — Refactoring Plan, T0.1 — Add a contract for `HttpExecutionContext` (REVIEW.md §3.4, §4 P0-1) — **DONE**, T0.2 — Type the redactor iterables (REVIEW.md §3.7, §4 P0-2) (+10 more)

### Community 87 - "Milestone M12 — Security, Privacy, Performance, and Resilience"
Cohesion: 0.11
Nodes (19): Benchmarks (M12.5 / M12.6), Data protection (M12.1), M12.1 Data protection, M12.2 Tenant isolation, M12.3 Serialization security, M12.4 Snapshot integrity, M12.5 Performance benchmarks, M12.6 Performance gates (+11 more)

### Community 88 - "InvalidCloudEventException"
Cohesion: 0.09
Nodes (5): OccurrenceId, OccurrenceType, OccurrenceEventTest, OccurrenceTest, OccurrenceTypeTest

### Community 89 - "StringValueEquality"
Cohesion: 0.09
Nodes (12): ItPack\RunIt\Adapter\OpenTelemetry\Telemetry\SnapshotPayloadPolicy, OpenTelemetry\API\Logs\LoggerProviderInterface, OpenTelemetry\API\Metrics\MeterProviderInterface, OpenTelemetry\API\Trace\TracerProviderInterface, OpenTelemetry\Context\Propagation\TextMapPropagatorInterface, BridgeCapability, self, BridgeConfig (+4 more)

### Community 90 - "require-dev"
Cohesion: 0.11
Nodes (18): require-dev, codeception/module-asserts, codeception/module-phpbrowser, infection/codeception-adapter, just-bases/php-obs-dev-base, just-bases/php-qa-base, just-bases/php-unit-test-base, nyholm/psr7 (+10 more)

### Community 91 - "CloudEventJsonSerializerTest"
Cohesion: 0.04
Nodes (55): 1. Purpose, 2.1 Checklist semantics, 2.2 Definition of implemented, 2.3 Definition of verified, 2.4 Scheduling assumptions, 2. Roadmap Rules, 3. Release Overview, Architecture (+47 more)

### Community 93 - "composer.json"
Cohesion: 0.12
Nodes (16): authors, autoload, psr-4, description, license, minimum-stability, name, prefer-stable (+8 more)

### Community 94 - "3. Findings"
Cohesion: 0.12
Nodes (16): 1. Conceptual review checklist, 2. Summary scorecard, 3. Findings, 4. Verified strengths (evidence-backed), 5. Assumptions, 6. Recommended order of work, P1-1 — Statement coverage is 90.50%, below the 95% requirement, P1-2 — Mutation testing cannot complete locally (+8 more)

### Community 96 - "CloudEventPublisher.php"
Cohesion: 0.09
Nodes (15): ItPack\RunIt\Adapter\CloudEvents\Contract\CloudEventFactoryInterface, ItPack\RunIt\Adapter\CloudEvents\Contract\CloudEventSinkInterface, ItPack\RunIt\Adapter\CloudEvents\Contract\OccurrenceToCloudEventMapperInterface, ItPack\RunIt\Contract\Domain\RedactedArtifactInterface, CloudEventPublisher, self, CloudEventsBridgeConfig, CloudEventsBridgeConfigBuilder (+7 more)

### Community 97 - "ItPack\RunIt\Contract\Domain\ExecutionContextInterface"
Cohesion: 0.08
Nodes (19): ItPack\RunIt\Behavior\Application\Publication\DelegatesPublisherLifecycleBehavior, ItPack\RunIt\Conformance\Result\ViolationSeverity, ItPack\RunIt\Contract\Application\Publication\InternalHealthReporterInterface, ItPack\RunIt\Contract\Port\ArtifactPublisherInterface, ItPack\RunIt\Contract\Port\CloseablePublisherInterface, ItPack\RunIt\Contract\Port\FlushablePublisherInterface, NullArtifactPublisher, CallbackArtifactPublisher (+11 more)

### Community 98 - "PhpParser\Node"
Cohesion: 0.17
Nodes (12): M9.1 Integration boundaries, M9.2 Execution and trace mapping, M9.3 Activity and span mapping, M9.4 Context and propagation, M9.5 Measurements and metrics, M9.6 Observations and logs, M9.7 Occurrences and snapshots, M9.8 Fiber-aware OTel integration (+4 more)

### Community 99 - "CompositeContextStorageTest"
Cohesion: 0.25
Nodes (4): PHPStan\Reflection\ReflectionProvider, PHPStan\Testing\RuleTestCase, RequireInterfaceRuleTest, RequireInterfaceRule

### Community 102 - "Milestone M14 — Documentation, Dogfooding, and ItPack Adoption"
Cohesion: 0.12
Nodes (16): M14.1 Core documentation, M14.1 status audit — August 21, 2026, M14.2 Architecture documentation, M14.2 status audit — August 21, 2026, M14.3 Integration documentation, M14.3 status audit — August 21, 2026, M14.4 Recipes, M14.4 status audit — August 21, 2026 (+8 more)

### Community 105 - "ContextEntry"
Cohesion: 0.20
Nodes (10): Activity, Invariants, Lifecycle, Nesting, Normalization, Ownership, Public API usage, Related decisions (+2 more)

### Community 107 - "ExecutionSuccess"
Cohesion: 0.20
Nodes (6): ItPack\RunIt\Contract\Domain\ContextClassificationInterface, ItPack\RunIt\Contract\Domain\ContextPropagationPolicyInterface, ItPack\RunIt\Contract\Domain\ContextSerializationPolicyInterface, DefaultContextPropagationPolicy, DefaultContextSerializationPolicy, DefaultContextPoliciesTest

### Community 108 - "SecurityLimits"
Cohesion: 0.19
Nodes (7): ItPack\RunIt\Contract\Domain\SnapshotInterface, mapOccurrence(), mapSnapshot(), verifyChain(), verifyIntegrity(), store(), ThrowingSnapshotStore

### Community 109 - "RedactingPublisherTest"
Cohesion: 0.20
Nodes (4): ItPack\RunIt\Contract\Domain\DurationInterface, measure(), self, Timeout

### Community 110 - "SamplingTest"
Cohesion: 0.17
Nodes (5): PhpParser\Node, ForbiddenUnsafeTypeRule, Param, MemberOrderRule, TypeNode

### Community 111 - "Codeception\Actor"
Cohesion: 0.22
Nodes (10): Codeception\Actor, ConcurrencyTester, ConformanceTester, FunctionalTester, Tests\Support\_generated\ConcurrencyTesterActions, Tests\Support\_generated\ConformanceTesterActions, Tests\Support\_generated\FunctionalTesterActions, Tests\Support\_generated\UnitTesterActions (+2 more)

### Community 112 - "Prioritized findings"
Cohesion: 0.13
Nodes (15): High: Buffer byte limit is not a hard admission limit, High: Default publication failures can be silent, High: Redaction occurs after the sampling decision, High: Runtime-specific context storage is required for concurrency, High: The frozen interface baseline detects a breaking change, High: Throwable redaction misses nested failure details, High: Unit coverage is below the required floor, Medium: Architecture gate covers less than the architecture prose implies (+7 more)

### Community 114 - "OpenTelemetry\API\Trace\SpanContextInterface"
Cohesion: 0.27
Nodes (6): ItPack\RunIt\Contract\Domain\ExecutionDefinitionFactoryInterface, ItPack\RunIt\SDK\Attribute\ExecutedAsInterface, MetadataExecutionDefinitionFactoryInterface, ExecutionDefinitionFactory, MetadataExecutionDefinitionFactory, create()

### Community 115 - "DefaultMeasurementInstrumentMapper"
Cohesion: 0.19
Nodes (3): OpenTelemetry\API\Metrics\MeterInterface, DefaultMeasurementInstrumentMapper, InstrumentCache

### Community 116 - "PHPStan\Rules\Rule"
Cohesion: 0.19
Nodes (5): ItPack\RunIt\Behavior\Domain\ValidatedStringIdentifierBehavior, ItPack\RunIt\Contract\Domain\CorrelationIdInterface, ActivityId, CorrelationId, ActivityTest

### Community 117 - "ActivityInterface.php"
Cohesion: 0.17
Nodes (14): complete(), definition(), endedAt(), id(), ActivityDefinitionInterface, ActivityIdInterface, ActivityInterface, ActivityStatusInterface (+6 more)

### Community 118 - "ExecutionInterface.php"
Cohesion: 0.17
Nodes (14): beginRunning(), complete(), definition(), endedAt(), id(), ExecutionDefinitionInterface, ExecutionIdInterface, ExecutionInterface (+6 more)

### Community 120 - "Backward compatibility"
Cohesion: 0.14
Nodes (14): Backward compatibility, Baseline policy, CI, Phase 0 (P0) public API additions, Public API scope, Refactoring plan (2026-08-23) public API additions, Refactoring plan Phase 0 (2026-08-24) additions, Refactoring plan Phase 1 (2026-08-23) lifecycle changes (+6 more)

### Community 121 - "Per-adapter behaviour"
Cohesion: 0.14
Nodes (14): Adapter failure policies, Choosing a policy, CloudEvents, Domain failure policies, Fiber, OpenTelemetry, Per-adapter behaviour, PSR-14 (occurrence dispatch) (+6 more)

### Community 122 - "PSR interoperability"
Cohesion: 0.14
Nodes (14): Behaviour summary, Failure behaviour, HTTP server example, Level mapping, Outbound HTTP client example, PSR-14 occurrence bridge, PSR-15 middleware, PSR-18 propagation client (+6 more)

### Community 123 - "InvalidArgumentException"
Cohesion: 0.29
Nodes (3): ItPack\RunIt\Contract\Domain\ContextRedactionPolicyInterface, DefaultContextRedactionPolicy, self

### Community 124 - "ItPack\RunIt\Contract\Domain\ContextClassificationInterface"
Cohesion: 0.29
Nodes (3): attributeWrappedOperation(), DefaultRunItTest, RunItInterface

### Community 125 - "ItPack\RunIt\Contract\Domain\DurationInterface"
Cohesion: 0.22
Nodes (4): ItPack\RunIt\Contract\Domain\AttributeValidatorInterface, AttributeValidator, InvalidAttributeException, self

### Community 126 - "ProcessResult"
Cohesion: 0.15
Nodes (8): ItPack\RunIt\Contract\Domain\StringIdentifierValidatorInterface, measurement(), occurrence(), safeObservation(), sensitiveObservation(), snapshot(), snapshotPatcher(), stringIdentifierValidator()

### Community 127 - "ExecutionContextInterface.php"
Cohesion: 0.09
Nodes (15): Countable, self, with(), without(), find(), get(), merge(), ContextCollisionPolicy (+7 more)

### Community 128 - "Duration"
Cohesion: 0.39
Nodes (7): Contract, Direct, Inherited, Intermediate, Missing, ParentWithInterface, ParentWithoutInterface

### Community 132 - "ReflectionFunctionAbstract"
Cohesion: 0.33
Nodes (4): CallableReflectionResolverInterface, ReflectionFunctionAbstract, CallableReflectionResolver, resolve()

### Community 133 - "Milestone M11 — Fiber and Swoole/OpenSwoole Execution"
Cohesion: 0.15
Nodes (13): M11.1 Fiber context storage, M11.2 Composite context storage, M11.3 Execution engine port, M11.4 Swoole/OpenSwoole feasibility, M11.5 Coroutine context storage, M11.6 HTTP server adapter, M11.7 Worker lifecycle, M11 acceptance gate (+5 more)

### Community 134 - "Milestone M13 — Conformance Tooling and QA Completion"
Cohesion: 0.15
Nodes (13): M13.1 Test-framework-agnostic contract suites, M13.2 PHPStan completion, M13.3 DepTrac completion, M13.3 status audit — August 21, 2026, M13.4 Coding standards, M13.4 status audit — August 21, 2026, M13.5 Mutation testing, M13.6 Backward compatibility (+5 more)

### Community 135 - "Milestone M1 — Shared Domain Primitives"
Cohesion: 0.15
Nodes (13): M1.1 Base contracts, M1.2 Identifiers, M1.2 status audit, M1.3 Names and kinds, M1.3 status audit, M1.4 status audit, M1.4 Time and sequencing, M1.5 Attributes (+5 more)

### Community 136 - "Milestone M4 — Execution Lifecycle Runtime"
Cohesion: 0.15
Nodes (13): M4.1 Application contracts, M4.2 Execution state machine, M4.3 Default execution runner, M4.4 Nested execution policies, M4.5 Execution scope, M4.6 Context storage, M4.7 Cancellation and timeout, M4 acceptance gate (+5 more)

### Community 139 - "ContextValueValidator.php"
Cohesion: 0.10
Nodes (5): ObservationId, ObservationLoggerPublisherTest, GuardedPublisherTest, SamplingTest, ObservationTest

### Community 140 - "ExecutionScopeInterface.php"
Cohesion: 0.12
Nodes (6): ItPack\RunIt\Contract\Domain\StringValueValidatorInterface, StringValueValidator, __construct(), fromString(), static, StringValueValidatorTest

### Community 141 - "OccurrenceInterface.php"
Cohesion: 0.15
Nodes (6): id(), InstantInterface, OccurrenceIdInterface, OccurrenceTypeInterface, time(), type()

### Community 142 - "ActivityId"
Cohesion: 0.25
Nodes (8): Invariants, Normalization, Occurrence, Ownership, Public API usage, Related decisions, Types, What an occurrence represents

### Community 146 - "Recorded baselines (2026-08-23)"
Cohesion: 0.09
Nodes (21): Buffer drain and close baseline (2026-11-06), Buffer overflow and priority baseline (2026-11-08), Buffered admission baseline (2026-11-09), Buffered batch flush baseline (2026-11-09), Buffered drain and close baseline (2026-11-09), Byte-bound buffer overflow baseline, CloudEvents mapping and serialization baseline (2026-11-09), Covered scenarios (+13 more)

### Community 147 - "RunIt Refactoring Plan"
Cohesion: 0.17
Nodes (12): Decisions required before dependent changes, Phase 0: Baseline and safety net, Phase 1: Compatibility and context ownership, Phase 2: Publication security and artifact routing, Phase 3: Bounded buffering and performance, Phase 4: Failure handling and lifecycle outcomes, Phase 5: OpenTelemetry retention, Phase 6: Coverage, QA, architecture, and dependency maintenance (+4 more)

### Community 148 - "Milestone M6 — Artifact Publication Pipeline"
Cohesion: 0.22
Nodes (9): Base Contracts, Contracts, Exception markers, ExecutionArtifactInterface, NormalizableInterface, Ownership contracts, StringIdentifierInterface, StringProviderInterface and StringValueInterface (+1 more)

### Community 149 - "DefaultExecutedAsMetadataReader"
Cohesion: 0.27
Nodes (5): ExecutedAsInterface, ExecutedAsMetadataReaderInterface, DefaultExecutedAsMetadataReader, CallableReflectionResolverInterface, ExecutedAs

### Community 150 - "ContextEntryInterface.php"
Cohesion: 0.18
Nodes (9): classification(), expiresAt(), isExpired(), key(), ContextClassificationInterface, ContextKeyInterface, ContextSourceInterface, InstantInterface (+1 more)

### Community 151 - "SnapshotInterface.php"
Cohesion: 0.18
Nodes (7): classification(), id(), SnapshotClassificationInterface, SnapshotIdInterface, SnapshotTypeInterface, predecessorId(), type()

### Community 153 - "RunIt Agent Guide"
Cohesion: 0.18
Nodes (10): Architecture And Domain, Find The Right Context, Finish, graphify, Package And Release, PHP And Design, Public API And Compatibility, RunIt Agent Guide (+2 more)

### Community 154 - "ExecutionBench"
Cohesion: 0.33
Nodes (6): Adapters and bridges, Capability summary, Constraints and guarantees, Integration compatibility matrix, Related ADRs, Runtime

### Community 155 - "suggest"
Cohesion: 0.18
Nodes (11): suggest, cloudevents/sdk-php, ext-openswoole, ext-swoole, open-telemetry/api, open-telemetry/sdk, psr/clock, psr/event-dispatcher (+3 more)

### Community 156 - "RunIt Architecture Security"
Cohesion: 0.18
Nodes (11): Bounds everywhere (no unbounded exfiltration), Classification and redaction (deny-by-default), Defense-in-depth layers, Fail-open by default, fail-closed available, No global mutable execution state, Ports isolate external effects, Recursive publication is contained, RunIt Architecture Security (+3 more)

### Community 157 - "Changelog"
Cohesion: 0.18
Nodes (10): 1.0.0-rc.1, Changelog, Unreleased, Unreleased — M1.2 identifiers, Unreleased — M1.3 names and kinds, Unreleased — M1.4 time and sequencing, Unreleased — Phase 0 (P0), Unreleased — Phase 1 (P1) (+2 more)

### Community 158 - "ContextSource"
Cohesion: 0.33
Nodes (6): Components, Composite (runtime-aware) storage, Context cleanup, Fiber context storage, Fiber execution engine, RunIt + Fibers

### Community 159 - "HmacSnapshotSigner"
Cohesion: 0.22
Nodes (3): ItPack\RunIt\Contract\Port\SnapshotSignerInterface, HmacSnapshotSigner, HmacSnapshotSignerTest

### Community 161 - ".runExecution"
Cohesion: 0.29
Nodes (7): A taste: CloudEvents, A taste: OpenTelemetry, Choosing an integration, Inspecting what was produced, Publication guarantees, Tutorial 7 — Publish & integrate, Where to go now

### Community 162 - "MeasurementUnit.php"
Cohesion: 0.22
Nodes (9): Benchmarked changes, Bounded buffers and backpressure, Cost model, Determinism and hashing, Fiber and coroutine switching, Hot-path design rules, RunIt Architecture Performance, Vocabulary (+1 more)

### Community 164 - "ActivitySpanMapperTest"
Cohesion: 0.22
Nodes (3): Cancellation, self, CancellationTimeoutTest

### Community 165 - "BasicPublisherTest"
Cohesion: 0.33
Nodes (6): Attaching context to an execution, Building context, Next, Tutorial 5 — Context & classification, What you learned, Why deny-by-default?

### Community 166 - "NamesAndKindsTest"
Cohesion: 0.29
Nodes (6): Attribute types, Attributes, Immutability and normalization, Security posture, Usage, Validation rules

### Community 167 - "ExecutedCallableTest"
Cohesion: 0.20
Nodes (10): M3.1 Execution model, M3.2 Activity model, M3.3 Measurement model, M3.4 Observation model, M3.5 Occurrence model, M3.6 Snapshot model, M3.7 Artifact relationships, M3 acceptance gate (+2 more)

### Community 169 - "By theme"
Cohesion: 0.20
Nodes (10): Architecture & boundaries, By theme, Context & propagation, Domain model, Integrations & packaging, Principles every reader should know, Publication, security & resilience, RunIt Architecture Decision Records (+2 more)

### Community 170 - "RunIt Context Propagation"
Cohesion: 0.22
Nodes (9): Execution, Identity, ownership, and relationships, Invariants, Lifecycle, Normalization, Public API usage, Related decisions, Types (+1 more)

### Community 171 - "RunIt Self-Instrumentation"
Cohesion: 0.20
Nodes (10): Dogfooding without recursion, Guard in the default stack, Health callbacks are contained, Instrument batches, not every method, Intentionally uninstrumented bootstrap path, Recursion guard, RunIt Self-Instrumentation, The recursion problem (+2 more)

### Community 172 - "Activity"
Cohesion: 0.08
Nodes (10): ItPack\RunIt\Contract\Application\Publication\ArtifactFilterInterface, ArtifactTypeFilter, CompositeArtifactFilter, ExecutionKindFilter, ObservationSeverityFilter, OccurrenceTypeFilter, PublicationRoute, RecordingArtifactPublisher (+2 more)

### Community 173 - "CloudEvents integration"
Cohesion: 0.20
Nodes (10): CloudEvents integration, Conceptual mapping, Delivery guarantees, Event size limits, HTTP delivery, Installation, Mapping an occurrence to a CloudEvent, Required optional packages (+2 more)

### Community 174 - "OpenTelemetry integration"
Cohesion: 0.20
Nodes (10): Cardiality and payload controls, Conceptual mappings, Example, Fiber considerations, Installation, OpenTelemetry integration, Provider responsibility, Redaction before export (+2 more)

### Community 175 - "Milestone M10 — CloudEvents Integration"
Cohesion: 0.20
Nodes (10): M10.1 Integration boundaries, M10.2 Occurrence to CloudEvent, M10.3 CloudEvent to occurrence, M10.4 Serialization and bindings, M10.5 Delivery adapters, M10 acceptance gate, M10 documentation, M10 status audit — August 20, 2026 (+2 more)

### Community 177 - "Milestone M5 — Activity Runtime and Artifact Recorders"
Cohesion: 0.25
Nodes (8): Equality, Exceptions, Identifier types, Identifiers, Representation decision, Security notes, Tests, Validation rules

### Community 178 - "Milestone M7 — Programmatic SDK and Non-Intrusive Adoption"
Cohesion: 0.20
Nodes (10): M7.1 Runtime builder, M7.2 Runtime facade, M7.3 Callable decorator, M7.4 Service decorators, M7.5 Attribute metadata, M7.6 Developer experience, M7 acceptance gate, M7 status audit — August 19, 2026 (+2 more)

### Community 179 - "Milestone M8 — PSR Interoperability"
Cohesion: 0.20
Nodes (10): M8.1 PSR-20 clock, M8.2 PSR-3 observation export, M8.3 Execution-aware PSR-3 decorator, M8.4 PSR-3 to observation bridge, M8.5 PSR-14 occurrence bridge, M8.6 HTTP PSRs, M8 acceptance gate, M8 documentation (+2 more)

### Community 180 - "ExecutionDefinitionInterface.php"
Cohesion: 0.20
Nodes (8): context(), kind(), name(), parentExecutionId(), ExecutionContextInterface, ExecutionIdInterface, ExecutionKindInterface, ExecutionNameInterface

### Community 181 - "MeasurementInterface.php"
Cohesion: 0.20
Nodes (8): activityId(), kind(), name(), MeasurementKindInterface, MeasurementNameInterface, MeasurementUnitInterface, StringIdentifierInterface, unit()

### Community 182 - "DeterministicMonotonicClock"
Cohesion: 0.15
Nodes (8): ItPack\RunIt\Adapter\OpenTelemetry\Contract\SpanKindMapInterface, ItPack\RunIt\Adapter\OpenTelemetry\Contract\SpanStatusMapInterface, OpenTelemetry\SDK\Trace\SpanDataInterface, OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter, __construct(), SpanKindMap, SpanStatusMap, SpanKindMapTest

### Community 184 - ".snapshot"
Cohesion: 0.40
Nodes (5): Artifact catalog, Classification and redaction, Normalization schema, Ownership, sequencing, and timing invariants, Semantic model (artifacts)

### Community 187 - "DogfoodRunner.php"
Cohesion: 0.29
Nodes (7): Dev and example dependencies, Installation patterns, No global provider configuration, Optional dependencies, Related, Suggested (opt-in) packages, What the core always requires

### Community 188 - "DevTool"
Cohesion: 0.33
Nodes (6): Errors preserve the original exception, Linking executions, Next, Tutorial 6 — Nesting, flow & errors, What you learned, Why this matters

### Community 189 - "quality"
Cohesion: 0.20
Nodes (10): quality, @analyse, @architecture, @composer validate --strict, @cs:check, @lint:length, @refactor:dry, @security:audit (+2 more)

### Community 190 - "22.1 Testing layers"
Cohesion: 0.22
Nodes (9): 22.1 Testing layers, Adapter integration tests, Application tests, Compatibility tests, Concurrency tests, Contract test suites, Domain tests, Failure-injection tests (+1 more)

### Community 191 - "PublicationGuard"
Cohesion: 0.08
Nodes (8): ItPack\RunIt\Contract\Port\RuntimeContextDetectorInterface, FiberRuntimeDetector, SwooleRuntimeDetector, CompositeContextStorage, FiberRuntimeDetectorTest, SwooleRuntimeDetectorTest, FakeDetector, WeakMap

### Community 192 - "Execution"
Cohesion: 0.25
Nodes (8): Activity nesting, Cancellation and timeout, Exception preservation, Nested execution, RunIt Execution Lifecycle, Runner flow, State machine, Vocabulary

### Community 193 - "Snapshot"
Cohesion: 0.22
Nodes (9): Hashing and integrity, Invariants, Normalization, Ownership, Public API usage, Related decisions, Snapshot, Types (+1 more)

### Community 194 - "Time and sequencing"
Cohesion: 0.22
Nodes (8): ArtifactSequence, Duration, Instant, MonotonicClock (port), SequenceAllocator (port), Tests, Time and sequencing, Vocabulary

### Community 195 - "RunIt dogfooding"
Cohesion: 0.22
Nodes (8): Instrumenting adapter batch flushes, RunIt dogfooding, Running it, The intentionally uninstrumented bootstrap path, The six represented runs, Verify dogfooding does not distort benchmarks, Verify dogfooding does not recurse, Why dogfood at all

### Community 196 - "Milestone M0 — Repository and Architecture Foundation"
Cohesion: 0.22
Nodes (9): M0.1 Repository initialization, M0.2 Composer package, M0.3 Directory structure, M0.4 Coding and architectural standards, M0.5 Initial ADRs, M0.6 Tooling bootstrap, M0 acceptance gate, M0 status audit — August 5, 2026 (+1 more)

### Community 197 - "M12.8 Supply-Chain Security"
Cohesion: 0.22
Nodes (8): CI action pinning, Dependency audit, Dependency review, M12.8 Supply-Chain Security, Provenance, SBOM, Secret scanning, Vulnerability reporting

### Community 198 - "StringIdentifierFactoryTest"
Cohesion: 0.29
Nodes (7): Equality, Exceptions, Names and kinds, Tests, Validation rules, Value objects, Why extensible strings, not enums

### Community 199 - "CloudEventPublisher"
Cohesion: 0.08
Nodes (14): ItPack\RunIt\Contract\Application\Publication\ArtifactPredicateInterface, ItPack\RunIt\Contract\Application\Publication\SamplingPolicyInterface, PublisherStackFactory, AlwaysSamplePolicy, ErrorOverrideSamplePolicy, ExecutionKindSamplePolicy, HeadBasedSamplingPolicy, NeverSamplePolicy (+6 more)

### Community 200 - ".run"
Cohesion: 0.14
Nodes (5): BufferedFlushBench, ItPack\RunIt\Contract\Application\Publication\PublicationGuardTokenInterface, InMemoryArtifactPublisher, PublicationGuard, PublicationGuardToken

### Community 201 - "ObservationInterface.php"
Cohesion: 0.22
Nodes (6): id(), ObservationIdInterface, ObservationSeverityInterface, ThrowableDescriptionInterface, severity(), throwableDescription()

### Community 202 - "TimeoutInterface.php"
Cohesion: 0.25
Nodes (7): elapsed(), labels(), limit(), AttributeCollectionInterface, DurationInterface, ThrowableDescriptionInterface, throwableDescription()

### Community 203 - "PublicationPipelineTest"
Cohesion: 0.08
Nodes (19): Architecture, Cross-cutting, Guides, RunIt integration documentation, Components, Coroutine context storage, HTTP server middleware, Persistent-worker requirements (+11 more)

### Community 204 - "RoutingArtifactPublisherTest"
Cohesion: 0.11
Nodes (8): ItPack\RunIt\Behavior\Domain\StringProviderBehavior, ItPack\RunIt\Common\Contract\StringableInterface, ItPack\RunIt\Contract\Domain\StringValueInterface, StringValueEquality, equals(), ContextSource, self, ContextSourceTest

### Community 206 - "allow-plugins"
Cohesion: 0.25
Nodes (8): dantleech/what-changed, ergebnis/composer-normalize, infection/extension-installer, php-http/discovery, phpstan/extension-installer, tbachert/spi, config, allow-plugins

### Community 207 - "keywords"
Cohesion: 0.25
Nodes (8): keywords, context, execution, it-pack, itpack, observability, runtime, telemetry

### Community 208 - "RunIt Hexagonal Architecture"
Cohesion: 0.25
Nodes (8): Adapters, Boundaries at a glance, Consumers and SDK, Dependency direction, Layered diagram, Ports, RunIt Hexagonal Architecture, Vocabulary

### Community 210 - "Execution context"
Cohesion: 0.17
Nodes (3): ExtendedSampleService, SampleInvokable, SampleService

### Community 213 - "Psr\Http\Client\ClientInterface"
Cohesion: 0.25
Nodes (8): Invariants, Normalization, Observation, Ownership, Public API usage, Related decisions, Types, What an observation represents

### Community 214 - "Occurrence"
Cohesion: 0.24
Nodes (3): AttributeKey, self, AttributeKeyTest

### Community 215 - "Port Conformance Verification"
Cohesion: 0.25
Nodes (8): Authoring a new verifier, Port Conformance Verification, Reference, Run it as a plain script (no test framework), Run it inside a test framework, Self-exercised in CI, The fixture contract, Verifiers

### Community 216 - "RunIt Ecosystem"
Cohesion: 0.25
Nodes (8): Bridges and future packages, Core package — `it-pack/run-it`, How the pieces fit, Next, Reference consumer — ConfigureIt, RunIt Ecosystem, Validation — `it-pack/run-it-third-party-sample`, Vocabulary packages

### Community 217 - "RunIt Documentation"
Cohesion: 0.25
Nodes (8): Choose your path, Full map, I'm building an adapter or third-party library (~half day), I'm evaluating RunIt (~15 min), I'm integrating RunIt into my app (~1–2 h), New here?, Quick orientation by question, RunIt Documentation

### Community 218 - "RunIt 1.0.0-rc.1 — Release-candidate preparation (M15.1)"
Cohesion: 0.25
Nodes (8): Backward-compatibility baseline, Changelog and upgrade guide, Explicit internal namespaces, Public API freeze, Publishing the release candidate, Removed / abandoned experimental APIs, Review of public contracts, RunIt 1.0.0-rc.1 — Release-candidate preparation (M15.1)

### Community 219 - "RunIt Project Review"
Cohesion: 0.25
Nodes (8): Assumptions and limits, Executive assessment, Maintainability and design, Metrics, Phase 0 additions (2026-11-05), Phase 0 baseline refresh, RunIt Project Review, Verification record

### Community 220 - "Security Policy"
Cohesion: 0.25
Nodes (8): Current publication limitations, Default limits, External adapters, Reporting a vulnerability, Security model, Security Policy, Supported versions, Threat model and supply chain

### Community 221 - "RunIt Use Cases"
Cohesion: 0.25
Nodes (8): Common deployment shapes, Next, Pick your integration, RunIt Use Cases, RunIt vs. a logging / APM library, What RunIt is (and is not), When a lighter approach is enough, When RunIt is a good fit

### Community 222 - "RunIt Vocabulary"
Cohesion: 0.25
Nodes (8): Editing the vocabulary, How the code references the vocabulary, Namespaces, Reuse by sibling packages, RunIt Vocabulary, Two artifacts, What the ontology defines, What the taxonomy defines

### Community 224 - "FiberRuntimeDetector"
Cohesion: 0.40
Nodes (5): Getting Started with RunIt, Install, What just happened?, Where to go next, Your first execution

### Community 225 - "SnapshotIntegrityException"
Cohesion: 0.09
Nodes (18): ItPack\RunIt\Contract\Domain\ActivityIdInterface, ItPack\RunIt\Contract\Domain\ArtifactSequenceInterface, ItPack\RunIt\Contract\Domain\ExecutionIdInterface, ItPack\RunIt\Contract\Domain\InstantInterface, ItPack\RunIt\Contract\Domain\ObservationIdInterface, ItPack\RunIt\Contract\Domain\ThrowableDescriptionInterface, now(), create() (+10 more)

### Community 226 - "BoundedPrimitiveValueWalker"
Cohesion: 0.13
Nodes (6): Psr\EventDispatcher\EventDispatcherInterface, Psr\EventDispatcher\StoppableEventInterface, OccurrenceEvent, OccurrenceEventWrapper, OccurrencePublisher, RecordingEventDispatcher

### Community 228 - "DefaultActivityRunnerTest"
Cohesion: 0.13
Nodes (6): ExecutionId, MeasurementName, SequenceAllocatorTest, MeasurementInstrumentMapperTest, IdentifierPropertyTest, MeasurementTest

### Community 232 - "IdentifierPropertyTest"
Cohesion: 0.20
Nodes (10): Coroutine (Swoole / OpenSwoole), Fiber, Immutable context, Propagation diagrams, RunIt Context Propagation, Scope lifetime, Security posture, Storage and the ambient scope (+2 more)

### Community 238 - "StringValueValidatorTest"
Cohesion: 0.05
Nodes (19): Codeception\Test\Unit, ItPack\RunIt\Adapter\Psr\Log\LoggerCollisionPolicy, ItPack\RunIt\Behavior\Domain\ValidatedStringValueBehavior, ItPack\RunIt\Contract\Domain\ActivityKindInterface, ItPack\RunIt\Contract\Domain\ActivityNameInterface, equals(), self, ActivityDefinition (+11 more)

### Community 239 - "DogfoodReport"
Cohesion: 0.23
Nodes (4): InvalidValueExceptionInterface, InvalidStringValueException, self, static

### Community 240 - "ExecutionMiddleware"
Cohesion: 0.29
Nodes (7): Artifact Publication Pipeline, Buffering and backpressure, Composition example, Failure policies, Ordering, Recursion prevention, Redaction happens before external publication

### Community 241 - "psr-4"
Cohesion: 0.29
Nodes (7): autoload-dev, psr-4, Benchmarks\\, Tests\\, Tools\\ApiCompat\\, Tools\\Dogfood\\, Tools\\PhpStan\\RunitRules\\

### Community 242 - "DefaultContextRedactionPolicy.php"
Cohesion: 0.40
Nodes (5): Next, Run an execution, The runtime, Tutorial 1 — Hello, execution, What you learned

### Community 243 - "Attributes"
Cohesion: 0.15
Nodes (4): PHPStan\Rules\Rule, ForbidArbitraryObjectSerializationRule, ForbidDirectWallClockRule, RequireReadonlyForArtifactRule

### Community 245 - "Optional dependencies"
Cohesion: 0.40
Nodes (5): Measurements (quantitative), Next, Observations (qualitative), Tutorial 3 — Measurements & observations, What you learned

### Community 247 - "M12.1 Data-Protection Threat Model"
Cohesion: 0.29
Nodes (7): Adapter mapping review, Artifact field review, Classified assets, Controls implemented, M12.1 Data-Protection Threat Model, Negative tests, Scope

### Community 249 - "Upgrading RunIt"
Cohesion: 0.29
Nodes (7): Automated compatibility checking, Current release status, Deprecations, Optional dependencies, Upgrade steps, Upgrading RunIt, What is compatibility-sensitive

### Community 250 - "RunItPortFixture"
Cohesion: 0.40
Nodes (5): Architecture & runtime, Core model, Identifiers, names, and time, Related reading, RunIt Glossary

### Community 251 - "ActivityDefinitionInterface.php"
Cohesion: 0.29
Nodes (6): kind(), name(), parentActivityId(), ActivityIdInterface, ActivityKindInterface, ActivityNameInterface

### Community 252 - "create"
Cohesion: 0.29
Nodes (6): create(), ExecutionContextInterface, ExecutionDefinitionInterface, ExecutionIdInterface, ExecutionKindInterface, ExecutionNameInterface

### Community 254 - "ContextSource"
Cohesion: 0.40
Nodes (5): Next, Occurrences (immutable facts), Snapshots (state projections), Tutorial 4 — Occurrences & snapshots, What you learned

### Community 258 - "18. PHP 8.4 Language Usage"
Cohesion: 0.33
Nodes (6): 18.1 Property hooks and virtual properties, 18.2 Lazy objects, 18.3 Fibers, 18.4 `readonly`, 18.5 Traits, 18. PHP 8.4 Language Usage

### Community 259 - "Throwable normalization"
Cohesion: 0.33
Nodes (5): Captured fields, Safety policy, Throwable normalization, Types, Usage

### Community 261 - "REFACTORING_PLAN.md"
Cohesion: 0.33
Nodes (3): Assess AsyncIt context-token cleanup cost, Follow-up, Problem

### Community 262 - "Getting Started with RunIt"
Cohesion: 0.67
Nodes (3): security:audit:full, @composer audit, @putenv COMPOSER_AUDIT_ABANDONED=report

### Community 263 - "CancellationInterface.php"
Cohesion: 0.33
Nodes (4): labels(), AttributeCollectionInterface, ThrowableDescriptionInterface, throwableDescription()

### Community 264 - "Duration.php"
Cohesion: 0.12
Nodes (6): PHPStan\Analyser\Scope, ExplicitForeachByRefRule, ForbidSplObjectHashRule, NoEmptyCatchRule, NoMutableStaticPropertyRule, AbstractSrcRule

### Community 267 - ".definition"
Cohesion: 0.18
Nodes (7): DefaultInvoiceService, InstrumentedInvoiceService, InvoiceService, ItPack\RunIt\SDK\RunItInterface, JsonSnapshotSerializer, __construct(), RuntimeException

### Community 270 - "test"
Cohesion: 0.40
Nodes (5): test, @test:concurrency, @test:conformance, @test:integration, @test:unit

### Community 271 - "Contributing to RunIt"
Cohesion: 0.40
Nodes (5): Before submitting a change, Contributing to RunIt, Development checks, Reporting issues, Scope and status

### Community 272 - "RunIt Architecture Dependency Graph"
Cohesion: 0.40
Nodes (5): Enforced dependency rules, Layers, Prohibitions (architecture boundaries), RunIt Architecture Dependency Graph, Uncovered dependencies (reviewed)

### Community 273 - "14. OpenTelemetry Adapter"
Cohesion: 0.40
Nodes (5): 14.1 Mapping, 14.2 Adapter components, 14.3 Dependency policy, 14.4 Cardinality controls, 14. OpenTelemetry Adapter

### Community 274 - "5. Measurable Success Criteria"
Cohesion: 0.40
Nodes (5): 5.1 Functional criteria, 5.2 Performance criteria, 5.3 Reliability criteria, 5.4 Quality criteria, 5. Measurable Success Criteria

### Community 277 - "Recipe: Snapshot Integrity and Authenticity"
Cohesion: 0.40
Nodes (4): Chain verification, Recipe: Snapshot Integrity and Authenticity, Sign snapshots for authenticity, Verify integrity of a stored snapshot

### Community 279 - "bug_report.md"
Cohesion: 0.40
Nodes (4): Actual behavior, Expected behavior, Reproduction, Version and environment

### Community 281 - "ExecutionEngineInterface.php"
Cohesion: 0.40
Nodes (4): capability(), EngineCapabilityInterface, ExecutionScopeInterface, run()

### Community 282 - "PublicationGuardInterface.php"
Cohesion: 0.50
Nodes (3): enter(), leave(), PublicationGuardTokenInterface

### Community 284 - "ExecutionResultInterface.php"
Cohesion: 0.40
Nodes (4): ExecutionStatusInterface, ThrowableDescriptionInterface, status(), throwableDescription()

### Community 295 - "Tutorial 1 — Hello, execution"
Cohesion: 0.25
Nodes (8): Invariants, Measurement, Normalization, Ownership, Public API usage, Related decisions, Types, What a measurement represents

### Community 297 - "15. CloudEvents Adapter"
Cohesion: 0.50
Nodes (4): 15.1 Mapping, 15.2 Adapter components, 15.3 Requirements, 15. CloudEvents Adapter

### Community 298 - "19. Self-Instrumentation"
Cohesion: 0.50
Nodes (4): 19.1 Internal execution coverage, 19.2 Recursion prevention, 19.3 Dogfooding, 19. Self-Instrumentation

### Community 299 - "20. Security and Privacy"
Cohesion: 0.50
Nodes (4): 20.1 Threat model, 20.2 Required controls, 20.3 Recommended defaults, 20. Security and Privacy

### Community 300 - "21. Performance and Resource Management"
Cohesion: 0.50
Nodes (4): 21.1 Hot-path rules, 21.2 Persistent-worker controls, 21.3 Benchmarks, 21. Performance and Resource Management

### Community 301 - "Measure composite context storage ownership overhead"
Cohesion: 0.50
Nodes (3): Follow-up, Measure composite context storage ownership overhead, Problem

### Community 302 - "Resolve context refactoring quality gate failures"
Cohesion: 0.50
Nodes (3): Follow-up, Problem, Resolve context refactoring quality gate failures

### Community 303 - "Complete context ownership and runtime validation"
Cohesion: 0.50
Nodes (3): Complete context ownership and runtime validation, Follow-up, Problem

### Community 304 - "adapter_proposal.md"
Cohesion: 0.50
Nodes (3): External system, Optional dependency and failure policy, RunIt mapping

### Community 305 - "architecture_decision.md"
Cohesion: 0.50
Nodes (3): Consequences, Context, Decision

### Community 306 - "feature_proposal.md"
Cohesion: 0.50
Nodes (3): Domain and compatibility impact, Problem, Proposed behavior

### Community 315 - "allocate"
Cohesion: 0.50
Nodes (3): allocate(), ArtifactSequenceInterface, ExecutionIdInterface

### Community 316 - "BoundedPrimitiveValueWalker"
Cohesion: 0.38
Nodes (3): BoundedPrimitiveValueWalker, count(), sequence()

### Community 317 - "Psr7RequestContextExtractor"
Cohesion: 0.12
Nodes (14): sendRequest(), ItPack\RunIt\Adapter\CloudEvents\Contract\CloudEventSerializerInterface, ItPack\RunIt\Adapter\Psr\Contract\HeaderPropagatorInterface, Psr\Http\Client\ClientInterface, Psr\Http\Message\RequestFactoryInterface, Psr\Http\Message\RequestInterface, Psr\Http\Message\ResponseInterface, Psr\Http\Message\StreamFactoryInterface (+6 more)

### Community 319 - "3. Business Goals"
Cohesion: 0.67
Nodes (3): 3.1 Primary goals, 3.2 Non-goals, 3. Business Goals

### Community 320 - "Phase 8 — Release Candidate and Adoption, Week 12"
Cohesion: 0.67
Nodes (3): Deliver, Final `1.0.0` gate, Phase 8 — Release Candidate and Adoption, Week 12

### Community 321 - "Phase 0 — Architectural Ratification, Week 1"
Cohesion: 0.67
Nodes (3): Deliverables, Exit criteria, Phase 0 — Architectural Ratification, Week 1

### Community 322 - "Phase 7 — Hardening, Week 11"
Cohesion: 0.67
Nodes (3): Execute, Exit criteria, Phase 7 — Hardening, Week 11

### Community 323 - "Phase 1 — Domain Kernel, Weeks 2–3"
Cohesion: 0.67
Nodes (3): Exit criteria, Implement, Phase 1 — Domain Kernel, Weeks 2–3

### Community 324 - "Phase 2 — Execution Runtime, Weeks 4–5"
Cohesion: 0.67
Nodes (3): Exit criteria, Implement, Phase 2 — Execution Runtime, Weeks 4–5

### Community 325 - "Phase 3 — Publication Pipeline, Week 6"
Cohesion: 0.67
Nodes (3): Exit criteria, Implement, Phase 3 — Publication Pipeline, Week 6

### Community 326 - "Phase 4 — SDK and Non-Intrusive Adoption, Week 7"
Cohesion: 0.67
Nodes (3): Exit criteria, Implement, Phase 4 — SDK and Non-Intrusive Adoption, Week 7

### Community 327 - "Phase 5 — OpenTelemetry and PSR Adapters, Weeks 8–9"
Cohesion: 0.67
Nodes (3): Exit criteria, Implement, Phase 5 — OpenTelemetry and PSR Adapters, Weeks 8–9

### Community 328 - "Phase 6 — CloudEvents and Runtime Adapters, Week 10"
Cohesion: 0.67
Nodes (3): Exit criteria, Implement, Phase 6 — CloudEvents and Runtime Adapters, Week 10

### Community 395 - "Cross-Cutting Verification Matrix"
Cohesion: 0.16
Nodes (8): EngineCapabilityInterface, ItPack\RunIt\Contract\Application\EngineCapabilityInterface, ItPack\RunIt\Contract\Application\ExecutionEngineInterface, FiberExecutionEngine, SynchronousExecutionEngine, ExecutionEngineVerifier, ExecutionEngineInterface, EngineCapability

### Community 417 - "CurrentExecutionInterface.php"
Cohesion: 0.67
Nodes (3): get(), ExecutionScopeInterface, require()

## Knowledge Gaps
- **1201 isolated node(s):** `name`, `description`, `license`, `type`, `runtime` (+1196 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **94 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `ExecutionId` connect `DefaultActivityRunnerTest` to `ItPack\RunIt\Contract\Domain\ExecutionIdInterface`, `FailureHandlingTest`, `ConformanceViolations`, `ExecutionDefinition`, `ContextValueValidator.php`, `RunItPortConformanceTest`, `ExecutionId.php`, `FilteringPublisherTest`, `RunItArtifactFactory`, `ExecutionKind.php`, `FiberContextStorageTest`, `BufferingTest`, `OccurrenceType`, `SnapshotType`, `ForbidReflectionOnHotPathRule`, `ActivityDefinition`, `ItPack\RunIt\Contract\Domain\ExecutionKindInterface`, `ExecutionId`, `ExecutionContext`, `ThrowableDescription`, `ExecutionContext.php`, `Milestone M2 — Immutable Execution Context`, `RunIt Context Propagation`, `ExecutionMiddleware.php`, `.observation`, `ObservationId`, `ItPack\RunIt\Contract\Exception\InvalidValueExceptionInterface`, `InvalidCloudEventException`, `SnapshotIntegrityException`, `PHPStan\Analyser\Scope`, `.scope`, `RedactorVerifier.php`, `DogfoodReport`, `ExecutionContextRedactionTest`, `PHPStan\Rules\Rule`, `HttpAdaptersTest`?**
  _High betweenness centrality (0.027) - this node is a cross-community bridge._
- **Why does `SecurityLimits` connect `RunItTestKit` to `ItPack\RunIt\Contract\Domain\ExecutionIdInterface`, `ItPack\RunIt\Contract\Domain\OccurrenceInterface`, `.definition`, `ExecutionId.php`, `ItPack\RunIt\Contract\Application\ExecutionScopeInterface`, `ItPack\RunIt\Contract\Domain\StringIdentifierInterface`, `ItPack\RunIt\Contract\Domain\ActivityInterface`, `Tutorial 6 — Nesting, flow & errors`, `AttributeCollection`, `SystemMonotonicClock`, `BoundedPrimitiveValueWalker`, `IdentifierTest.php`, `RunItBuilderInterface.php`, `CloudEventPublisher`, `Measurement`, `InvalidCloudEventException`, `Psr20Clock`, `ItPack\RunIt\Contract\Domain\ExecutionContextInterface`, `StringValueValidatorTest`, `ItPack\RunIt\Contract\Domain\DurationInterface`?**
  _High betweenness centrality (0.026) - this node is a cross-community bridge._
- **Why does `CloudEvent` connect `CloudEvent` to `CloudEventPublisher.php`, `IdentifierTest.php`, `OtelTestKit`, `RunItBuilder`, `CloudEventJsonSerializerTest`, `InvalidCloudEventException`, `Psr7RequestContextExtractor`?**
  _High betweenness centrality (0.023) - this node is a cross-community bridge._
- **Are the 159 inferred relationships involving `ExecutionId` (e.g. with `.setUp()` and `.setUp()`) actually correct?**
  _`ExecutionId` has 159 INFERRED edges - model-reasoned connections that need verification._
- **What connects `name`, `description`, `license` to the rest of the system?**
  _1201 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `ItPack\RunIt\Contract\Port\ArtifactPublisherInterface` be split into smaller, more focused modules?**
  _Cohesion score 0.11965811965811966 - nodes in this community are weakly interconnected._
- **Should `ItPack\RunIt\Contract\Domain\ExecutionInterface` be split into smaller, more focused modules?**
  _Cohesion score 0.0696969696969697 - nodes in this community are weakly interconnected._