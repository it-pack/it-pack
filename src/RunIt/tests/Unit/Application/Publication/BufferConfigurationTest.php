<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Publication;

use Codeception\Test\Unit;
use InvalidArgumentException;
use ItPack\RunIt\Application\Publication\BufferConfiguration;
use ItPack\RunIt\Application\Publication\OverflowStrategy;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(BufferConfiguration::class)]
final class BufferConfigurationTest extends Unit
{
    public function testDefaultConfigurationReturnsBoundedValues(): void
    {
        $config = BufferConfiguration::default();

        self::assertSame(1024, $config->maxItems);
        self::assertSame(8 * 1024 * 1024, $config->maxEstimatedBytes);
        self::assertSame(60, $config->maxAgeSeconds);
        self::assertSame(256, $config->batchSize);
        self::assertSame(5, $config->flushIntervalSeconds);
        self::assertSame(4096, $config->shutdownFlushBudget);
        self::assertSame(OverflowStrategy::DropOldest, $config->overflow);
    }

    public function testConstructorAcceptsCustomValues(): void
    {
        $config = new BufferConfiguration(
            maxItems: 100,
            maxEstimatedBytes: 1000,
            maxAgeSeconds: 30,
            batchSize: 50,
            flushIntervalSeconds: 10,
            shutdownFlushBudget: 500,
            overflow: OverflowStrategy::DropNewest,
        );

        self::assertSame(100, $config->maxItems);
        self::assertSame(1000, $config->maxEstimatedBytes);
        self::assertSame(30, $config->maxAgeSeconds);
        self::assertSame(50, $config->batchSize);
        self::assertSame(10, $config->flushIntervalSeconds);
        self::assertSame(500, $config->shutdownFlushBudget);
        self::assertSame(OverflowStrategy::DropNewest, $config->overflow);
    }

    public function testNegativeMaxItemsThrows(): void
    {
        try {
            new BufferConfiguration(
                maxItems: -1,
                maxEstimatedBytes: 1000,
                maxAgeSeconds: 30,
                batchSize: 50,
                flushIntervalSeconds: 10,
                shutdownFlushBudget: 500,
                overflow: OverflowStrategy::DropOldest,
            );
            self::fail('Expected InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            self::assertSame('Buffer configuration values must not be negative.', $e->getMessage());
        }
    }

    public function testNegativeMaxEstimatedBytesThrows(): void
    {
        try {
            new BufferConfiguration(
                maxItems: 100,
                maxEstimatedBytes: -1,
                maxAgeSeconds: 30,
                batchSize: 50,
                flushIntervalSeconds: 10,
                shutdownFlushBudget: 500,
                overflow: OverflowStrategy::DropOldest,
            );
            self::fail('Expected InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            self::assertSame('Buffer configuration values must not be negative.', $e->getMessage());
        }
    }
}
