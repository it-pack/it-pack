<?php

declare(strict_types=1);

/**
 * RunIt port conformance runner.
 *
 * Exercises every framework-agnostic port verifier against the bundled adapters.
 * This is the project-runner integration example for M13.1: wire it into CI or a
 * `composer conformance` script so a non-conformant adapter fails the build.
 *
 * A third-party adapter author replaces the bundled adapters below with their own
 * implementation and supplies an equivalent fixture (see third-party-adapter.php).
 *
 * Run from the package root: `php examples/conformance/run-conformance.php`
 */

require __DIR__ . '/../../vendor/autoload.php';

use ItPack\RunIt\Adapter\Common\JsonSnapshotSerializer;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Adapter\Memory\InMemorySnapshotStore;
use ItPack\RunIt\Application\Engine\SynchronousExecutionEngine;
use ItPack\RunIt\Application\Propagation\StackContextStorage;
use ItPack\RunIt\Application\Publication\AlwaysSamplePolicy;
use ItPack\RunIt\Application\Publication\ArtifactRedactionConfig;
use ItPack\RunIt\Application\Publication\DefaultArtifactRedactor;
use ItPack\RunIt\Conformance\Fixture\RunItPortFixture;
use ItPack\RunIt\Conformance\Result\ConformanceResult;
use ItPack\RunIt\Conformance\Verifier\ArtifactPublisherVerifier;
use ItPack\RunIt\Conformance\Verifier\ContextStorageVerifier;
use ItPack\RunIt\Conformance\Verifier\ExecutionEngineVerifier;
use ItPack\RunIt\Conformance\Verifier\IdentifierGeneratorVerifier;
use ItPack\RunIt\Conformance\Verifier\MonotonicClockVerifier;
use ItPack\RunIt\Conformance\Verifier\RedactorVerifier;
use ItPack\RunIt\Conformance\Verifier\SamplingPolicyVerifier;
use ItPack\RunIt\Conformance\Verifier\SerializerVerifier;
use ItPack\RunIt\Conformance\Verifier\SnapshotStoreVerifier;
use ItPack\RunIt\Domain\Context\ExecutionContext;
use ItPack\RunIt\Domain\Execution\Execution;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\Domain\Shared\Instant;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use Tests\Support\Fixture\RunItTestKit;

$fixture = new RunItPortFixture();
$kit = new RunItTestKit();

$scope = static function (string $id) use ($kit): ExecutionScopeInterface {
    return $kit->buildScope(
        Execution::start(
            ExecutionId::fromString($id),
            ExecutionDefinition::create(ExecutionName::fromString('op'), ExecutionKind::fromString('task')),
            Instant::of(1),
        ),
        ExecutionContext::empty(),
    );
};

/**
 * @var array<string, ConformanceResult> $results
 */
$results = [
    'ArtifactPublisherInterface' => new ArtifactPublisherVerifier()->verify(new InMemoryArtifactPublisher(), $fixture),
    'ContextStorageInterface' => new ContextStorageVerifier()->verify(new StackContextStorage(), $scope('exec-1')),
    'IdentifierGeneratorInterface' => new IdentifierGeneratorVerifier()->verify(new RandomIdentifierGenerator(), $fixture),
    'MonotonicClockInterface' => new MonotonicClockVerifier()->verify(new SystemMonotonicClock(), $fixture),
    'SerializerInterface' => new SerializerVerifier()->verify(new JsonSnapshotSerializer(), $fixture),
    'SnapshotStoreInterface' => new SnapshotStoreVerifier()->verify(new InMemorySnapshotStore(), $fixture),
    'RedactorInterface' => new RedactorVerifier()->verify(new DefaultArtifactRedactor(new ArtifactRedactionConfig(sensitiveAttributeKeys: ['password'])), $fixture),
    'SamplingPolicyInterface' => new SamplingPolicyVerifier()->verify(new AlwaysSamplePolicy(), $fixture),
    'ExecutionEngineInterface' => new ExecutionEngineVerifier()->verify(new SynchronousExecutionEngine(), $scope('exec-1')),
];

$failed = 0;
foreach ($results as $port => $result) {
    printf("[%s] %s\n", $result->isConformant() ? 'PASS' : 'FAIL', $port);
    if (!$result->isConformant()) {
        $failed++;
        foreach ($result->violations as $violation) {
            printf("    - %s\n", $violation);
        }
    }
}

echo $failed === 0
    ? "All bundled adapters are conformant.\n"
    : sprintf("%d port(s) failed conformance.\n", $failed);

exit($failed === 0 ? 0 : 1);
