<?php

declare(strict_types=1);

namespace Tests\Integration;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\OpenTelemetry\BridgeConfig;
use ItPack\RunIt\Adapter\OpenTelemetry\TelemetryPublisher;
use ItPack\RunIt\Application\Publication\RedactedArtifact;

final class TelemetryPublisherErrorLogTest extends Unit
{
    /** Unsafe drops without a health reporter are visible without logging artifact data. */
    public function testUnsafeDropFallsBackToErrorLogWithoutPayload(): void
    {
        $log = tempnam(sys_get_temp_dir(), 'runit-health-');
        self::assertNotFalse($log);
        $previous = ini_set('error_log', $log);
        try {
            TelemetryPublisher::fromConfig(BridgeConfig::default())
                ->publish(new RedactedArtifact(['id' => 'secret-id', 'message' => 'secret-payload'], 'unknown'))
            ;
            $contents = file_get_contents($log);
            self::assertIsString($contents);
            self::assertStringContainsString(
                'Redacted measurement or unknown artifact kind is not delivered.',
                $contents,
            );
            self::assertStringNotContainsString('secret-id', $contents);
            self::assertStringNotContainsString('secret-payload', $contents);
        } finally {
            if ($previous !== false) {
                ini_set('error_log', $previous);
            }

            unlink($log);
        }
    }
}
