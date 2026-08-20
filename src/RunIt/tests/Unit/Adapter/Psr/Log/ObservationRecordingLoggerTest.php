<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Psr\Log;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\Psr\Log\ObservationRecordingLogger;
use ItPack\RunIt\Application\Publication\PublicationGuard;
use ItPack\RunIt\Domain\Observation\ObservationSeverity;
use RuntimeException;
use stdClass;
use Tests\Support\Fixture\Psr\RecordingObservationRecorder;

final class ObservationRecordingLoggerTest extends Unit
{
    public function testLogsAreRecordedAsObservationsWithMappedSeverity(): void
    {
        $recorder = new RecordingObservationRecorder();
        $logger = new ObservationRecordingLogger($recorder);

        $logger->error('boom', ['order' => 1]);

        self::assertSame(1, $recorder->count());
        $recorded = $recorder->recorded()[0];
        self::assertSame('boom', $recorded['message']);
        self::assertSame('error', $recorded['severity']?->name());
        self::assertSame(1, $recorded['attributes']?->get('order'));
    }

    public function testMapsEveryPsr3LevelToSeverity(): void
    {
        $recorder = new RecordingObservationRecorder();
        $logger = new ObservationRecordingLogger($recorder);

        $logger->emergency('e');
        $logger->alert('a');
        $logger->critical('c');
        $logger->warning('w');
        $logger->notice('n');
        $logger->info('i');
        $logger->debug('d');

        $names = array_map(static fn ($r) => $r['severity']?->name(), $recorder->recorded());
        self::assertSame(
            [ObservationSeverity::Emergency->name(), ObservationSeverity::Alert->name(), ObservationSeverity::Critical->name(), ObservationSeverity::Warning->name(), ObservationSeverity::Notice->name(), ObservationSeverity::Info->name(), ObservationSeverity::Debug->name()],
            $names,
        );
    }

    public function testThrowableInContextIsMappedThroughRecorder(): void
    {
        $recorder = new RecordingObservationRecorder();
        $logger = new ObservationRecordingLogger($recorder);

        $logger->error('failed', ['exception' => new RuntimeException('kaboom')]);

        self::assertSame('kaboom', $recorder->recorded()[0]['throwable']?->getMessage());
    }

    public function testNonScalarContextValuesAreDropped(): void
    {
        $recorder = new RecordingObservationRecorder();
        $logger = new ObservationRecordingLogger($recorder);

        $logger->info('with object', ['obj' => new stdClass(), 'scalar' => 'kept']);

        $attributes = $recorder->recorded()[0]['attributes'];
        self::assertNotNull($attributes);
        self::assertFalse($attributes->has('obj'));
        self::assertSame('kept', $attributes->get('scalar'));
        self::assertFalse($attributes->has('exception'));
    }

    public function testRecursionGuardPreventsNestedRecording(): void
    {
        $recorder = new RecordingObservationRecorder();
        $guard = new PublicationGuard();
        $token = $guard->enter();

        $logger = new ObservationRecordingLogger($recorder, $guard);
        $logger->info('nested should be skipped');

        self::assertSame(0, $recorder->count());

        $guard->leave($token);
    }
}
