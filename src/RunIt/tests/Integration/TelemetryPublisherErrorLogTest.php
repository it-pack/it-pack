<?php

declare(strict_types=1);

namespace Tests\Integration;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\OpenTelemetry\BridgeConfig;
use ItPack\RunIt\Adapter\OpenTelemetry\TelemetryPublisher;
use ItPack\RunIt\Application\Publication\RedactedArtifact;
use ItPack\RunIt\Contract\Application\Publication\InternalHealthReporterInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use RuntimeException;
use Throwable;

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

    public function testThrowingAndReentrantReportersUsePayloadFreeFallback(): void
    {
        $log = tempnam(sys_get_temp_dir(), 'runit-health-');
        self::assertNotFalse($log);
        $previous = ini_set('error_log', $log);
        try {
            $health = new class () implements InternalHealthReporterInterface {
                public ?TelemetryPublisher $publisher = null;

                public int $calls = 0;

                public function report(Throwable $error, ExecutionArtifactInterface $artifact): void
                {
                    ++$this->calls;
                    $this->publisher?->publish(new RedactedArtifact(['id' => 'nested-secret'], 'unknown'));
                    throw new RuntimeException('callback-secret');
                }
            };
            $publisher = TelemetryPublisher::fromConfig(BridgeConfig::default(), health: $health);
            $health->publisher = $publisher;
            $publisher->publish(new RedactedArtifact(['id' => 'outer-secret'], 'unknown'));
            $publisher->publish(new RedactedArtifact(['id' => 'outer-secret'], 'unknown'));

            self::assertSame(2, $health->calls);
            $contents = file_get_contents($log);
            self::assertIsString($contents);
            self::assertSame(4, substr_count($contents, 'unable to report a redacted artifact drop.'));
            foreach (['nested-secret', 'outer-secret', 'callback-secret'] as $secret) {
                self::assertStringNotContainsString($secret, $contents);
            }
        } finally {
            if ($previous !== false) {
                ini_set('error_log', $previous);
            }

            unlink($log);
        }
    }
}
