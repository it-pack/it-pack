<?php

declare(strict_types=1);

namespace Tests\Support\Fixture;

use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Observation\Observation;
use ItPack\RunIt\Domain\Observation\ObservationId;
use ItPack\RunIt\Domain\Observation\ObservationSeverity;

/**
 * Deterministic artifact builders for publication unit tests.
 *
 * Provides shared observation and priority-artifact fixtures so the
 * publication test classes do not each reimplement the same constructors.
 * Unknown severities resolve to info, matching the publication test helpers
 * rather than the OpenTelemetry factory's debug default.
 */
final class PublicationTestArtifactFactory
{
    public const string DEFAULT_MESSAGE = 'msg';

    public static function observation(
        string $severity = 'info',
        string $execution = 'exec-1',
        string $observationId = 'obs',
    ): ExecutionArtifactInterface {
        $id = ObservationId::fromString($observationId);
        $executionId = ExecutionId::fromString($execution);
        $level = ObservationSeverity::tryFrom($severity);

        return $level instanceof ObservationSeverity
            ? Observation::atSeverity($level, $id, $executionId, self::DEFAULT_MESSAGE)
            : Observation::info($id, $executionId, self::DEFAULT_MESSAGE);
    }

    public static function priorityArtifact(string $id, bool $high = false): Observation
    {
        $observationId = ObservationId::fromString($id);
        $executionId = ExecutionId::fromString('execution');

        return $high
            ? Observation::error($observationId, $executionId, 'message')
            : Observation::info($observationId, $executionId, 'message');
    }
}
