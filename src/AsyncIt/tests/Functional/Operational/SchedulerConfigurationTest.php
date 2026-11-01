<?php

declare(strict_types=1);

namespace Tests\Functional\Operational;

use DomainException;
use ItPack\AsyncIt\Contract\Port\BackpressurePolicy;
use ItPack\AsyncIt\Contract\Port\SchedulingPolicy;
use ItPack\AsyncIt\Domain\SchedulerConfiguration;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ItPack\AsyncIt\Domain\SchedulerConfiguration
 */
final class SchedulerConfigurationTest extends TestCase
{
    public function testSchedulerConfigurationDefaults(): void
    {
        $config = SchedulerConfiguration::defaults();

        self::assertSame(SchedulerConfiguration::MAX_CONCURRENCY_DEFAULT, $config->maxConcurrency);
        self::assertSame(SchedulingPolicy::Fifo, $config->schedulingPolicy);
        self::assertSame(BackpressurePolicy::Block, $config->backpressurePolicy);
        self::assertSame(1.0, $config->telemetrySamplingRate);
        self::assertFalse($config->debug);
        self::assertSame(30, $config->gracefulShutdownDeadline->seconds());
    }

    public function testSchedulerConfigurationWithChangesReturnsNewSnapshot(): void
    {
        $config = SchedulerConfiguration::defaults();
        $newConfig = $config->withChanges([
            'maxConcurrency' => 100,
            'debug' => true,
        ]);

        self::assertNotSame($config, $newConfig);
        self::assertSame(100, $newConfig->maxConcurrency);
        self::assertTrue($newConfig->debug);
        self::assertSame(SchedulerConfiguration::MAX_CONCURRENCY_DEFAULT, $config->maxConcurrency);
    }

    public function testSchedulerConfigurationForCanary(): void
    {
        $config = SchedulerConfiguration::forCanary(0.3);

        self::assertSame(0.3, $config->canaryWeight);
    }

    public function testSchedulerConfigurationWithChangesRejectsUnknownKey(): void
    {
        $config = SchedulerConfiguration::defaults();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Unknown configuration key');
        $config->withChanges(['unknownKey' => 'value']);
    }
}
