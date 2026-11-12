<?php

declare(strict_types=1);

// Run separately: php tests/target-policy-otel-regressions.php
// The default-policy checks intentionally fail until failed executions are masked.
require __DIR__ . '/../vendor/autoload.php';

use ItPack\RunIt\Adapter\OpenTelemetry\BridgeConfigBuilder;
use ItPack\RunIt\Adapter\OpenTelemetry\Telemetry\AttributeKeys;
use ItPack\RunIt\Adapter\OpenTelemetry\TelemetryPublisher;
use ItPack\RunIt\Application\Factory\PublisherStackFactory;
use ItPack\RunIt\Application\Publication\ArtifactRedactionConfig;
use ItPack\RunIt\Application\Publication\DefaultArtifactRedactor;
use ItPack\RunIt\Domain\Execution\Execution;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionFailure;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\Domain\Execution\ExecutionStatus;
use ItPack\RunIt\Domain\Shared\Instant;
use OpenTelemetry\API\Trace\StatusCode;
use Tests\Support\Fixture\OpenTelemetry\OtelTestKit;
use Tests\Support\Fixture\SensitiveExceptionChain;

$failures = [];
$check = static function (bool $condition, string $label) use (&$failures): void {
    if (!$condition) {
        $failures[] = $label;
    }
};

$execution = Execution::start(
    ExecutionId::fromString('otel-target'),
    ExecutionDefinition::create(ExecutionName::fromString('import'), ExecutionKind::fromString('batch')),
    Instant::of(1000),
)->complete(
    ExecutionFailure::of(ExecutionStatus::Failed, SensitiveExceptionChain::classifiedChain()),
    Instant::of(1001),
);
$before = $execution->normalize();
$secrets = SensitiveExceptionChain::secrets();

foreach (['default', 'opt-out'] as $mode) {
    $kit = new OtelTestKit();
    $config = new BridgeConfigBuilder()->withTracerProvider($kit->tracerProvider)->build();
    $redaction = $mode === 'default'
        ? new ArtifactRedactionConfig()
        : new ArtifactRedactionConfig(redactThrowableMessages: false);
    new PublisherStackFactory()->compose(
        TelemetryPublisher::fromConfig($config),
        new DefaultArtifactRedactor($redaction),
        null,
    )->publish($execution);

    $spans = $kit->spans();
    $check(count($spans) === 1, "$mode exports exactly one span");
    if (count($spans) !== 1) {
        continue;
    }

    $span = $spans[0];
    $check($span->getName() === 'import', "$mode preserves span name");
    $check($span->getStatus()->getCode() === StatusCode::STATUS_ERROR, "$mode preserves error status");
    $check($span->getAttributes()->get(AttributeKeys::EXECUTION_ID) === 'otel-target', "$mode preserves execution id");
    $check(
        $span->getAttributes()->get(AttributeKeys::EXECUTION_STATUS) === 'failed',
        "$mode preserves execution status"
    );
    $events = $span->getEvents();
    $check(count($events) === 1 && $events[0]->getName() === 'exception', "$mode exports an exception event");
    if (count($events) !== 1) {
        continue;
    }

    $attributes = $events[0]->getAttributes();
    $check($attributes->get('exception.type') === 'RuntimeException', "$mode preserves exception type");
    $message = $attributes->get('exception.message');
    $trace = $attributes->get('exception.stacktrace');
    if ($mode === 'opt-out') {
        $check(is_string($message) && str_contains($message, $secrets[1]), 'opt-out exports the outer message');
        $check(is_string($trace) && str_contains($trace, $secrets[1]), 'opt-out exports the outer trace');
        continue;
    }

    $check($message === '[redacted]', 'default masks the exception message');
    $check($trace === null || $trace === '', 'default omits the exception trace');
    $exported = [$span->getName(), $span->getStatus()->getDescription(), $span->getAttributes()->toArray()];
    foreach ($events as $event) {
        $exported[] = [$event->getName(), $event->getAttributes()->toArray()];
    }

    $encoded = json_encode($exported, JSON_THROW_ON_ERROR);
    foreach ($secrets as $depth => $secret) {
        $check(!str_contains($encoded, $secret), "default export excludes synthetic marker $depth");
    }
}

$check($execution->normalize() === $before, 'source execution remains unchanged');
foreach ($failures as $failure) {
    fwrite(STDERR, "FAIL: $failure\n");
}

if ($failures !== []) {
    fwrite(STDERR, count($failures) . " OpenTelemetry target-policy checks failed.\n");
    exit(1);
}

fwrite(STDOUT, "OpenTelemetry target-policy checks passed.\n");
