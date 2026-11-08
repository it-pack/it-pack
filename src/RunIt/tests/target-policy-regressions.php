<?php

declare(strict_types=1);

// Run separately: php tests/target-policy-regressions.php
// This target-policy harness intentionally fails until the approved policies are implemented.
require __DIR__ . '/../vendor/autoload.php';

use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Application\Factory\PublisherStackFactory;
use ItPack\RunIt\Application\Publication\ArtifactRedactionConfig;
use ItPack\RunIt\Application\Publication\DefaultArtifactRedactor;
use ItPack\RunIt\Application\Publication\ErrorOverrideSamplePolicy;
use ItPack\RunIt\Application\Publication\NeverSamplePolicy;
use ItPack\RunIt\Application\Publication\RedactingArtifactPublisher;
use ItPack\RunIt\Application\Publication\SeveritySamplePolicy;
use ItPack\RunIt\Contract\Application\Publication\SamplingPolicyInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
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
    $throwable = $name === 'execution' ? ($published['result']['throwable'] ?? null) : ($published['throwable'] ?? null);
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

foreach ($failures as $failure) {
    fwrite(STDERR, "FAIL: $failure\n");
}

if ($failures !== []) {
    fwrite(STDERR, count($failures) . " target-policy checks failed.\n");
    exit(1);
}

fwrite(STDOUT, "Target-policy checks passed.\n");
