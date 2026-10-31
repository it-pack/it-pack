<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use DomainException;
use ItPack\AsyncIt\Contract\Port\BackpressurePolicy;
use ItPack\AsyncIt\Contract\Port\SchedulingPolicy;
use ItPack\AsyncIt\Domain\SchedulerConfiguration;
use ItPack\RunIt\Domain\Shared\Duration;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ItPack\AsyncIt\Domain\SchedulerConfiguration
 */
final class SchedulerConfigurationTest extends TestCase
{
    public function testDefaults(): void
    {
        $config = SchedulerConfiguration::defaults();

        self::assertSame(256, $config->maxConcurrency);
        self::assertNull($config->defaultTimeout);
        self::assertSame(SchedulingPolicy::Fifo, $config->schedulingPolicy);
        self::assertSame(BackpressurePolicy::Block, $config->backpressurePolicy);
        self::assertSame(1.0, $config->telemetrySamplingRate);
        self::assertTrue(Duration::ofSeconds(30)->equals($config->gracefulShutdownDeadline));
        self::assertFalse($config->debug);
        self::assertNull($config->canaryWeight);
        self::assertNull($config->canaryConfig);
    }

    public function testWithChangesReturnsNewInstance(): void
    {
        $original = SchedulerConfiguration::defaults();

        $new = $original->withChanges([
            'maxConcurrency' => 512,
            'schedulingPolicy' => SchedulingPolicy::Priority,
            'debug' => true,
        ]);

        self::assertNotSame($original, $new);
        self::assertSame(512, $new->maxConcurrency);
        self::assertSame(SchedulingPolicy::Priority, $new->schedulingPolicy);
        self::assertTrue($new->debug);

        self::assertSame(256, $original->maxConcurrency);
        self::assertSame(SchedulingPolicy::Fifo, $original->schedulingPolicy);
        self::assertFalse($original->debug);
    }

    public function testEnableDebug(): void
    {
        $original = SchedulerConfiguration::defaults();

        $new = $original->enableDebug();

        self::assertFalse($original->debug);
        self::assertTrue($new->debug);
    }

    public function testRejectsMaxConcurrencyBelowCeiling(): void
    {
        $this->expectException(DomainException::class);

        $original = SchedulerConfiguration::defaults();
        $original->withChanges(['maxConcurrency' => 0]);
    }

    public function testRejectsMaxConcurrencyAboveCeiling(): void
    {
        $this->expectException(DomainException::class);

        $original = SchedulerConfiguration::defaults();
        $original->withChanges(['maxConcurrency' => 10_001]);
    }

    public function testRejectsInvalidSamplingRate(): void
    {
        $this->expectException(DomainException::class);

        $original = SchedulerConfiguration::defaults();
        $original->withChanges(['telemetrySamplingRate' => 1.5]);
    }

    public function testRejectsNonPositiveDeadline(): void
    {
        $this->expectException(DomainException::class);

        $original = SchedulerConfiguration::defaults();
        $original->withChanges(['gracefulShutdownDeadline' => Duration::ofSeconds(0)]);
    }

    public function testForCanarySetsCanaryWeight(): void
    {
        $config = SchedulerConfiguration::forCanary(0.3);

        self::assertSame(0.3, $config->canaryWeight);
        self::assertNull($config->canaryConfig);
    }

    public function testForCanaryRejectsWeightOutOfRange(): void
    {
        $this->expectException(DomainException::class);

        SchedulerConfiguration::forCanary(-0.1);
    }

    public function testForCanaryRejectsWeightAboveOne(): void
    {
        $this->expectException(DomainException::class);

        SchedulerConfiguration::forCanary(1.1);
    }

    public function testWithChangesCanCarryCanaryWeight(): void
    {
        $original = SchedulerConfiguration::defaults();

        $new = $original->withChanges(['canaryWeight' => 0.7, 'debug' => true]);

        self::assertSame(0.7, $new->canaryWeight);
        self::assertTrue($new->debug);
        self::assertNull($original->canaryWeight);
    }

    public function testWithChangesCanCarryCanaryConfig(): void
    {
        $baseline = SchedulerConfiguration::defaults();
        $canary = SchedulerConfiguration::forCanary(0.5);

        $new = $baseline->withChanges(['canaryConfig' => $canary]);

        self::assertSame($canary, $new->canaryConfig);
        self::assertNull($baseline->canaryConfig);
    }

    public function testWithChangesRejectsUnknownKey(): void
    {
        $this->expectException(DomainException::class);

        $config = SchedulerConfiguration::defaults();
        $config->withChanges(['nonexistentKey' => 'value']);
    }
}
