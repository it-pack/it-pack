<?php

declare(strict_types=1);

// Run separately: php tests/target-policy-psr3-regressions.php
// Default redaction must suppress trace values; explicit opt-out preserves them.
require __DIR__ . '/../vendor/autoload.php';

use ItPack\RunIt\Adapter\Psr\Log\ObservationLoggerPublisher;
use ItPack\RunIt\Application\Factory\PublisherStackFactory;
use ItPack\RunIt\Application\Publication\ArtifactRedactionConfig;
use ItPack\RunIt\Application\Publication\DefaultArtifactRedactor;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Observation\Observation;
use ItPack\RunIt\Domain\Observation\ObservationId;
use ItPack\RunIt\Domain\Shared\ThrowableDescription;
use Tests\Support\Fixture\Psr\InMemoryLogger;

$failures = [];
$check = static function (bool $condition, string $label) use (&$failures): void {
    if (!$condition) {
        $failures[] = $label;
    }
};

$chain = ThrowableDescription::create(
    'OuterException',
    'synthetic outer secret',
    2,
    ThrowableDescription::create(
        'MiddleException',
        'synthetic middle secret',
        1,
        ThrowableDescription::create('InnerException', 'synthetic inner secret', 0, null, 'synthetic inner trace'),
        'synthetic middle trace',
    ),
    'synthetic outer trace',
);
$observation = Observation::error(
    ObservationId::fromString('psr-target'),
    ExecutionId::fromString('psr-execution'),
    'operation failed',
    throwableDescription: $chain,
);
$before = $observation->normalize();
$logger = new InMemoryLogger();
$publisher = new PublisherStackFactory()->compose(
    new ObservationLoggerPublisher($logger),
    new DefaultArtifactRedactor(),
    null,
);
$publisher->publish($observation);
$entry = $logger->last();
$check($logger->count() === 1 && is_array($entry), 'observation reaches the PSR-3 logger');
$check(($entry['level'] ?? null) === 'error', 'severity reaches the logger');
$check(($entry['context']['execution_id'] ?? null) === 'psr-execution', 'execution correlation is preserved');
$throwable = $entry['context']['exception'] ?? null;
foreach ([['OuterException', 2], ['MiddleException', 1], ['InnerException', 0]] as $depth => [$class, $code]) {
    $check(is_array($throwable), "cause $depth is present");
    if (!is_array($throwable)) {
        break;
    }

    $check(
        ($throwable['class'] ?? null) === $class && ($throwable['code'] ?? null) === $code,
        "cause $depth retains metadata",
    );
    $check(($throwable['message'] ?? null) === '[redacted]', "cause $depth masks its message");
    $check(!isset($throwable['trace']), "cause $depth suppresses its trace value");
    $throwable = $throwable['previous'] ?? null;
}

$check($throwable === null, 'no additional cause is exported');
$check($observation->normalize() === $before, 'source observation remains unchanged');

$optOutLogger = new InMemoryLogger();
$optOutPublisher = new PublisherStackFactory()->compose(
    new ObservationLoggerPublisher($optOutLogger),
    new DefaultArtifactRedactor(new ArtifactRedactionConfig(redactThrowableMessages: false)),
    null,
);
$optOutPublisher->publish($observation);
$optOutEntry = $optOutLogger->last();
$check($optOutLogger->count() === 1 && is_array($optOutEntry), 'opt-out observation reaches the logger');
$throwable = $optOutEntry['context']['exception'] ?? null;
foreach (['outer', 'middle', 'inner'] as $depth => $name) {
    $check(is_array($throwable), "opt-out cause $depth is present");
    if (!is_array($throwable)) {
        break;
    }

    $check(($throwable['message'] ?? null) === "synthetic $name secret", "opt-out cause $depth preserves its message");
    $check(($throwable['trace'] ?? null) === "synthetic $name trace", "opt-out cause $depth preserves its trace");
    $throwable = $throwable['previous'] ?? null;
}

$check($throwable === null, 'opt-out has no additional cause');
$check($observation->normalize() === $before, 'opt-out leaves source observation unchanged');

foreach ($failures as $failure) {
    fwrite(STDERR, "FAIL: $failure\n");
}

if ($failures !== []) {
    fwrite(STDERR, count($failures) . " target-policy checks failed.\n");
    exit(1);
}

fwrite(STDOUT, "PSR-3 target-policy checks passed.\n");
