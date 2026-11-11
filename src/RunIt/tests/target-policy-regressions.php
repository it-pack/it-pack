<?php

declare(strict_types=1);

// Run separately: php tests/target-policy-regressions.php
// This target-policy harness intentionally fails until the approved policies are implemented.
require __DIR__ . '/../vendor/autoload.php';

use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Application\Factory\PublisherStackFactory;
use ItPack\RunIt\Application\Publication\ArtifactRedactionConfig;
use ItPack\RunIt\Application\Publication\BufferConfiguration;
use ItPack\RunIt\Application\Publication\BufferedArtifactPublisher;
use ItPack\RunIt\Application\Publication\BufferOverflowException;
use ItPack\RunIt\Application\Publication\CallbackArtifactPublisher;
use ItPack\RunIt\Application\Publication\CallbackHealthReporter;
use ItPack\RunIt\Application\Publication\DefaultArtifactRedactor;
use ItPack\RunIt\Application\Publication\ErrorOverrideSamplePolicy;
use ItPack\RunIt\Application\Publication\ExecutionKindSamplePolicy;
use ItPack\RunIt\Application\Publication\FailOpenPolicy;
use ItPack\RunIt\Application\Publication\FailSafeArtifactPublisher;
use ItPack\RunIt\Application\Publication\NeverSamplePolicy;
use ItPack\RunIt\Application\Publication\OverflowStrategy;
use ItPack\RunIt\Application\Publication\RedactingArtifactPublisher;
use ItPack\RunIt\Application\Publication\SeveritySamplePolicy;
use ItPack\RunIt\Contract\Application\Publication\SamplingPolicyInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use ItPack\RunIt\Domain\Execution\Execution;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionFailure;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\Domain\Execution\ExecutionStatus;
use ItPack\RunIt\Domain\Observation\Observation;
use ItPack\RunIt\Domain\Observation\ObservationId;
use ItPack\RunIt\Domain\Observation\ObservationSeverity;
use ItPack\RunIt\Domain\Shared\AttributeCollection;
use ItPack\RunIt\Domain\Shared\Instant;
use ItPack\RunIt\Domain\Shared\ThrowableDescription;
use Tests\Support\Fixture\DeterministicArtifactByteEstimator;
use Tests\Support\Fixture\DeterministicMonotonicClock;

$failures = [];
$check = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

// These labels are deliberately synthetic, not credentials or credential-shaped values.
$chain = ThrowableDescription::create(
    'OuterException',
    'synthetic outer message',
    2,
    ThrowableDescription::create(
        'MiddleException',
        'synthetic middle message',
        1,
        ThrowableDescription::create('InnerException', 'synthetic inner message', 0, null, 'synthetic inner trace'),
        'synthetic middle trace'
    ),
    'synthetic outer trace'
);
$id = ExecutionId::fromString('target-policy-execution');
$execution = Execution::start(
    $id,
    ExecutionDefinition::create(ExecutionName::fromString('target'), ExecutionKind::fromString('batch')),
    Instant::of(1000),
)->complete(ExecutionFailure::of(ExecutionStatus::Failed, $chain), Instant::of(1001));
$observation = Observation::error(ObservationId::fromString('target'), $id, 'failed', throwableDescription: $chain);

foreach (['execution' => $execution, 'observation' => $observation] as $name => $artifact) {
    $before = $artifact->normalize();
    $sink = new InMemoryArtifactPublisher();
    new RedactingArtifactPublisher(new DefaultArtifactRedactor(), $sink)->publish($artifact);
    $published = $sink->published()[0]->normalize();
    $throwable = $name === 'execution'
        ? ($published['result']['throwable'] ?? null)
        : ($published['throwable'] ?? null);
    foreach ([['OuterException', 2], ['MiddleException', 1], ['InnerException', 0]] as $depth => [$class, $code]) {
        $check(is_array($throwable), "$name cause $depth is present");
        if (!is_array($throwable)) {
            break;
        }

        $check(
            ($throwable['class'] ?? null) === $class && ($throwable['code'] ?? null) === $code,
            "$name cause $depth preserves class and code"
        );
        $check(($throwable['message'] ?? null) === '[redacted]', "$name cause $depth masks its message");
        $check(!isset($throwable['trace']), "$name cause $depth omits its trace");
        $throwable = $throwable['previous'] ?? null;
    }

    $check($throwable === null, "$name has no unexpected additional causes");
    $check($artifact->normalize() === $before, "$name source remains unchanged");
}

$spy = new class () implements SamplingPolicyInterface {
    /** @var list<array<string, mixed>> */
    public array $seen = [];

    public function sample(ExecutionArtifactInterface $artifact): bool
    {
        $this->seen[] = $artifact->normalize();

        return true;
    }
};
$redactor = new DefaultArtifactRedactor(new ArtifactRedactionConfig(sensitiveAttributeKeys: ['private']));
$attributes = AttributeCollection::fromArray(['private' => 'synthetic private value']);
$factory = new PublisherStackFactory();
$sink = new InMemoryArtifactPublisher();
$factory->compose($sink, $redactor, $spy)->publish(
    Observation::info(ObservationId::fromString('spy'), $id, 'sample', attributes: $attributes),
);
$check(count($spy->seen) === 1, 'sampler receives the artifact');
$check(
    ($spy->seen[0]['attributes']['private'] ?? null) === '[redacted]',
    'default sampler receives redacted attributes'
);
$check(count($sink->published()) === 1, 'accepted artifact reaches the sink');

foreach ([
    'severity' => new SeveritySamplePolicy(ObservationSeverity::Warning->level()),
    'error override' => new ErrorOverrideSamplePolicy(new NeverSamplePolicy()),
] as $name => $policy) {
    $sink = new InMemoryArtifactPublisher();
    $publisher = $factory->compose($sink, $redactor, $policy);
    $publisher->publish(Observation::info(ObservationId::fromString('low'), $id, 'low', attributes: $attributes));
    $publisher->publish(Observation::error(ObservationId::fromString('high'), $id, 'high', attributes: $attributes));
    $published = $sink->published();
    $check(
        count($published) === 1 && ($published[0]->normalize()['message'] ?? null) === 'high',
        "$name preserves typed decisions with redaction"
    );
    $check(
        count($published) === 1 && ($published[0]->normalize()['attributes']['private'] ?? null) === '[redacted]',
        "$name publishes the redacted artifact"
    );
}

$kindPolicy = new ExecutionKindSamplePolicy(
    static fn (ExecutionIdInterface $executionId): ?ExecutionKind => match ($executionId->toString()) {
        'kind-allowed' => ExecutionKind::fromString('http.request'),
        'kind-denied' => ExecutionKind::fromString('cli.command'),
        'kind-unconfigured' => ExecutionKind::fromString('batch'),
        default => null,
    },
    ['http.request' => true, 'cli.command' => false],
);
$kindSpy = new class ($kindPolicy) implements SamplingPolicyInterface {
    /** @var list<array<string, mixed>> */
    public array $seen = [];

    /** @var list<bool> */
    public array $decisions = [];

    public function __construct(private readonly SamplingPolicyInterface $inner)
    {
    }

    public function sample(ExecutionArtifactInterface $artifact): bool
    {
        $this->seen[] = $artifact->normalize();

        $decision = $this->inner->sample($artifact);
        $this->decisions[] = $decision;

        return $decision;
    }
};
$kindSink = new InMemoryArtifactPublisher();
$kindPublisher = $factory->compose($kindSink, $redactor, $kindSpy);
$kindInputs = [];
foreach (['allowed', 'denied', 'unknown', 'unconfigured', 'unchanged'] as $case) {
    $executionId = $case === 'unchanged' ? 'kind-allowed' : "kind-$case";
    $kindInputs[$case] = Observation::info(
        ObservationId::fromString("kind-$case"),
        ExecutionId::fromString($executionId),
        "kind-$case",
        attributes: $case === 'unchanged' ? AttributeCollection::fromArray([]) : $attributes,
    );
    $before = $kindInputs[$case]->normalize();
    $kindPublisher->publish($kindInputs[$case]);
    $check($kindInputs[$case]->normalize() === $before, "execution-kind $case source remains unchanged");
}

$check(count($kindSpy->seen) === 5, 'execution-kind sampler receives every input');
foreach (['allowed', 'denied', 'unknown', 'unconfigured', 'unchanged'] as $index => $case) {
    $expected = $case === 'allowed' || $case === 'unchanged';
    $check(($kindSpy->decisions[$index] ?? null) === $expected, "execution-kind $case decision is preserved");
}

foreach (['allowed', 'denied', 'unknown', 'unconfigured'] as $index => $case) {
    $check(
        ($kindSpy->seen[$index]['attributes']['private'] ?? null) === '[redacted]',
        "execution-kind $case sampler receives redacted attributes"
    );
}

$kindPublished = $kindSink->published();
$check(count($kindPublished) === 2, 'execution-kind retains only allowed and unchanged inputs');
if (count($kindPublished) === 2) {
    $check(
        $kindPublished[0]->normalize() === $kindSpy->seen[0],
        'execution-kind allowed sink receives the sampled redacted artifact'
    );
    $check(
        $kindPublished[1]->normalize() === $kindSpy->seen[4],
        'execution-kind unchanged allowed input reaches the sink'
    );
}

$calls = 0;
$reports = 0;
$publisher = null;
$health = new CallbackHealthReporter(
    static function (Throwable $error, ExecutionArtifactInterface $artifact) use (&$reports, &$publisher): void {
        ++$reports;
        if ($reports < 4 && $publisher instanceof FailSafeArtifactPublisher) {
            $publisher->publish($artifact);
        }
    },
);
$sink = new CallbackArtifactPublisher(static function () use (&$calls): never {
    ++$calls;
    throw new RuntimeException('synthetic transport failure');
});
$publisher = new FailSafeArtifactPublisher($sink, new FailOpenPolicy(), $health);
$publisher->publish($observation);
$check($reports === 1, 'recursive health publication does not reenter the health reporter');
$check($calls === 1, 'recursive health publication does not reenter the failing sink');
$previousReports = $reports;
$publisher->publish($observation);
$check($calls === 2, 'publication guard resets after recursive health reporting');
$check($reports === $previousReports + 1, 'health reporting resumes for a subsequent independent failure');

foreach (OverflowStrategy::cases() as $strategy) {
    foreach (['oversized', 'projected'] as $scenario) {
        $buffer = new BufferedArtifactPublisher(
            new BufferConfiguration(
                maxItems: 10,
                maxEstimatedBytes: 100,
                maxAgeSeconds: 60,
                batchSize: 10,
                flushIntervalSeconds: 5,
                shutdownFlushBudget: 10,
                overflow: $strategy,
            ),
            new InMemoryArtifactPublisher(),
            new DeterministicMonotonicClock(1000, 0),
            new DeterministicArtifactByteEstimator(60, ['oversized' => 101]),
        );
        $ids = $scenario === 'oversized' ? ['oversized'] : ['first', 'second'];
        foreach ($ids as $itemId) {
            try {
                $buffer->publish(Observation::info(ObservationId::fromString($itemId), $id, 'synthetic'));
            } catch (BufferOverflowException) {
                // Rejection is permitted; the invariant is independent of the disposition.
            }

            $check(
                $buffer->estimatedBytes() <= 100,
                "{$strategy->name} $scenario after $itemId stays within the estimated-byte limit"
            );
        }

        $buffer->flush();
        $check($buffer->estimatedBytes() === 0, "{$strategy->name} $scenario accounting clears after flush");
    }
}

foreach ([OverflowStrategy::DropOldest, OverflowStrategy::DropLowPriority] as $strategy) {
    $buffer = new BufferedArtifactPublisher(
        new BufferConfiguration(
            maxItems: 10,
            maxEstimatedBytes: 100,
            maxAgeSeconds: 60,
            batchSize: 10,
            flushIntervalSeconds: 5,
            shutdownFlushBudget: 10,
            overflow: $strategy,
        ),
        new InMemoryArtifactPublisher(),
        new DeterministicMonotonicClock(1000, 0),
        new DeterministicArtifactByteEstimator(30, ['large' => 80]),
    );
    foreach (['first', 'second', 'large'] as $itemId) {
        try {
            $buffer->publish(Observation::info(ObservationId::fromString($itemId), $id, 'synthetic'));
        } catch (BufferOverflowException) {
            // Rejection or dropping is permitted as long as the bound holds.
        }

        $check($buffer->estimatedBytes() <= 100, "{$strategy->name} multiple-eviction bound after $itemId");
    }

    $buffer->flush();
    $check($buffer->bufferedCount() === 0, "{$strategy->name} multiple-eviction buffer clears after flush");
    $check($buffer->estimatedBytes() === 0, "{$strategy->name} multiple-eviction accounting clears after flush");
}

foreach (OverflowStrategy::cases() as $strategy) {
    $sink = new InMemoryArtifactPublisher();
    $buffer = new BufferedArtifactPublisher(
        new BufferConfiguration(
            maxItems: 10,
            maxEstimatedBytes: PHP_INT_MAX,
            maxAgeSeconds: 60,
            batchSize: 10,
            flushIntervalSeconds: 5,
            shutdownFlushBudget: 10,
            overflow: $strategy,
        ),
        $sink,
        new DeterministicMonotonicClock(1000, 0),
        new DeterministicArtifactByteEstimator(10, ['near-integer-limit' => PHP_INT_MAX - 5]),
    );
    $buffer->publish(Observation::info(ObservationId::fromString('near-integer-limit'), $id, 'synthetic'));
    $check($buffer->estimatedBytes() === PHP_INT_MAX - 5, "{$strategy->name} integer boundary initial accounting");

    try {
        $buffer->publish(Observation::info(ObservationId::fromString('integer-overflow'), $id, 'synthetic'));
    } catch (BufferOverflowException) {
        // Explicit rejection is permitted, but must not corrupt the buffer.
    } catch (Throwable $error) {
        $check(false, "{$strategy->name} integer boundary unexpected " . $error::class);
    }

    $check($buffer->bufferedCount() <= 1, "{$strategy->name} integer boundary does not retain both items");
    $check($buffer->estimatedBytes() >= 0, "{$strategy->name} integer boundary accounting is nonnegative");
    $buffer->flush();
    $check($buffer->bufferedCount() === 0, "{$strategy->name} integer boundary buffer clears after flush");
    $check($buffer->estimatedBytes() === 0, "{$strategy->name} integer boundary accounting clears after flush");
}

foreach ($failures as $failure) {
    fwrite(STDERR, "FAIL: $failure\n");
}

if ($failures !== []) {
    fwrite(STDERR, count($failures) . " target-policy checks failed.\n");
    exit(1);
}

fwrite(STDOUT, "Target-policy checks passed.\n");
