<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Psr\Log;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\Psr\Log\ObservationRecordingLogger;
use ItPack\RunIt\Application\Publication\PublicationGuard;
use ItPack\RunIt\Domain\Observation\ObservationSeverity;
use RuntimeException;
use stdClass;
use Stringable;
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
        self::assertSame('error', $recorded['severity']?->label());
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

        $names = array_map(static fn (array $r) => $r['severity']?->label(), $recorder->recorded());
        self::assertSame(
            [
                ObservationSeverity::Emergency->label(),
                ObservationSeverity::Alert->label(),
                ObservationSeverity::Critical->label(),
                ObservationSeverity::Warning->label(),
                ObservationSeverity::Notice->label(),
                ObservationSeverity::Info->label(),
                ObservationSeverity::Debug->label(),
            ],
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

    public function testConfiguredGuardIsEnteredAndLeftAroundNormalRecording(): void
    {
        $recorder = new RecordingObservationRecorder();
        $logger = new ObservationRecordingLogger($recorder, new PublicationGuard());

        $logger->log('info', 'guarded message', []);

        self::assertSame(1, $recorder->count());
    }

    public function testStringableObjectLevelIsUsedVerbatimAndUnknownLevelsFallBackToDebug(): void
    {
        $recorder = new RecordingObservationRecorder();
        $logger = new ObservationRecordingLogger($recorder);

        $logger->log(new class () implements Stringable {
            public function __toString(): string
            {
                return 'error';
            }
        }, 'stringable level');

        $logger->log([], 'unusable level falls back to debug');

        self::assertSame('error', $recorder->recorded()[0]['severity']?->label());
        self::assertSame('debug', $recorder->recorded()[1]['severity']?->label());
    }

    public function testScalarMapContainingAnObjectIsDroppedEntirely(): void
    {
        $recorder = new RecordingObservationRecorder();
        $logger = new ObservationRecordingLogger($recorder);

        $logger->log('info', 'with nested object', ['nested' => ['entity' => new stdClass()]]);

        self::assertNull($recorder->recorded()[0]['attributes']?->get('nested'));
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
