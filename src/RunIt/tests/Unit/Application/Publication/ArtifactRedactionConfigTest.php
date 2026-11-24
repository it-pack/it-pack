<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Publication;

use Codeception\Test\Unit;
use ItPack\RunIt\Application\Publication\ArtifactRedactionConfig;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ArtifactRedactionConfig::class)]
final class ArtifactRedactionConfigTest extends Unit
{
    public function testDefaultConfigMasksMostThings(): void
    {
        $config = new ArtifactRedactionConfig();

        self::assertSame([], $config->sensitiveAttributeKeys);
        self::assertSame([], $config->sensitiveDataKeys);
        self::assertSame('[redacted]', $config->marker);
        self::assertTrue($config->redactThrowableMessages);
        self::assertTrue($config->redactOccurrenceData);
        self::assertTrue($config->redactSnapshotPayloads);
        self::assertTrue($config->redactObservationMessages);
        self::assertFalse($config->redactMeasurementValues);
    }

    public function testDisabledConfigMasksNothing(): void
    {
        $config = ArtifactRedactionConfig::disabled();

        self::assertSame([], $config->sensitiveAttributeKeys);
        self::assertSame([], $config->sensitiveDataKeys);
        self::assertSame('[redacted]', $config->marker);
        self::assertFalse($config->redactThrowableMessages);
        self::assertFalse($config->redactOccurrenceData);
        self::assertFalse($config->redactSnapshotPayloads);
        self::assertFalse($config->redactObservationMessages);
        self::assertFalse($config->redactMeasurementValues);
    }

    public function testCustomConfig(): void
    {
        $config = new ArtifactRedactionConfig(
            sensitiveAttributeKeys: ['password', 'token'],
            sensitiveDataKeys: ['ssn'],
            marker: '***',
            redactThrowableMessages: false,
            redactOccurrenceData: false,
            redactSnapshotPayloads: false,
            redactObservationMessages: false,
            redactMeasurementValues: true,
        );

        self::assertSame(['password', 'token'], $config->sensitiveAttributeKeys);
        self::assertSame(['ssn'], $config->sensitiveDataKeys);
        self::assertSame('***', $config->marker);
        self::assertFalse($config->redactThrowableMessages);
        self::assertFalse($config->redactOccurrenceData);
        self::assertFalse($config->redactSnapshotPayloads);
        self::assertFalse($config->redactObservationMessages);
        self::assertTrue($config->redactMeasurementValues);
    }
}
