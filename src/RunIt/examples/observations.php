<?php

declare(strict_types=1);

/**
 * RunIt — observations.
 *
 * An observation is a qualitative note (a log message, warning, or error). Severities mirror the
 * PSR-3 levels. A throwable is normalized to a safe, bounded description — never a raw stack trace.
 * See docs/concepts/observation.md.
 *
 * Run from the package root: `php examples/observations.php`
 */

require __DIR__ . '/../vendor/autoload.php';

use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Adapter\Common\JsonSnapshotSerializer;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\Domain\Observation\ObservationSeverity;
use ItPack\RunIt\SDK\Builder\RunItBuilder;

$runtime = RunItBuilder::create()
    ->withPublisher(new InMemoryArtifactPublisher())
    ->withClock(new SystemMonotonicClock())
    ->withSequenceAllocator(new InMemorySequenceAllocator())
    ->withSnapshotSerializer(new JsonSnapshotSerializer())
    ->withIdentifierGenerator(new RandomIdentifierGenerator())
    ->build();

$runtime->run(
    ExecutionDefinition::create(ExecutionName::fromString('checkout.process'), ExecutionKind::fromString('operation')),
    static function (\ItPack\RunIt\Contract\Application\ExecutionScopeInterface $scope): void {
        $scope->observations()->record('payment accepted', ObservationSeverity::Info);
        $scope->observations()->record('deprecated coupon code', ObservationSeverity::Warning);

        try {
            throw new RuntimeException('gateway timeout');
        } catch (Throwable $e) {
            $scope->observations()->record('payment failed', ObservationSeverity::Error, throwable: $e);
        }
    },
);

echo 'recorded observations (info / warning / error)' . PHP_EOL;
