<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Common;

use Codeception\Test\Unit;
use InvalidArgumentException;
use ItPack\RunIt\Adapter\Common\ConfigurableHashCalculator;

/**
 * @group adapter
 * @group security
 */
final class ConfigurableHashCalculatorTest extends Unit
{
    public function testDefaultAlgorithmIsSha256(): void
    {
        $calculator = new ConfigurableHashCalculator();

        self::assertSame(hash('sha256', 'payload'), $calculator->calculate('payload'));
    }

    public function testConfiguredAlgorithmIsUsed(): void
    {
        $calculator = new ConfigurableHashCalculator('sha1');

        self::assertSame(hash('sha1', 'payload'), $calculator->calculate('payload'));
    }

    public function testUnsupportedAlgorithmIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ConfigurableHashCalculator('nope');
    }
}
