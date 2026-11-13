<?php

declare(strict_types=1);

namespace Tests\Integration;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Application\Publication\NeverSamplePolicy;
use ItPack\RunIt\Application\Publication\RedactedArtifact;
use ItPack\RunIt\Application\Publication\SamplingArtifactPublisher;
use ItPack\RunIt\Contract\Application\Publication\InternalHealthReporterInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use RuntimeException;
use Throwable;

final class SamplingPublisherErrorLogTest extends Unit
{
    public function testReporterFailureDoesNotLeakItsMessage(): void
    {
        $log = tempnam(sys_get_temp_dir(), 'runit-sampling-');
        self::assertNotFalse($log);
        $previous = ini_set('error_log', $log);
        try {
            $health = new class () implements InternalHealthReporterInterface {
                public function report(Throwable $error, ExecutionArtifactInterface $artifact): void
                {
                    throw new RuntimeException("secret payload\ninjected log line");
                }
            };
            new SamplingArtifactPublisher(new NeverSamplePolicy(), new InMemoryArtifactPublisher(), $health)
                ->publish(new RedactedArtifact(['id' => 'secret-id']))
            ;
            $contents = file_get_contents($log);
            self::assertIsString($contents);
            self::assertStringContainsString('Health reporter failed: RuntimeException', $contents);
            self::assertStringContainsString('Artifact dropped by sampling policy.', $contents);
            self::assertStringNotContainsString('secret', $contents);
            self::assertStringNotContainsString('injected log line', $contents);
        } finally {
            if ($previous !== false) {
                ini_set('error_log', $previous);
            }

            unlink($log);
        }
    }
}
