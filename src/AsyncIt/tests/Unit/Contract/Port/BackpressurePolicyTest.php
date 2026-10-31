<?php

declare(strict_types=1);

namespace Tests\Unit\Contract\Port;

use ItPack\AsyncIt\Contract\Port\BackpressurePolicy;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ItPack\AsyncIt\Contract\Port\BackpressurePolicy
 */
final class BackpressurePolicyTest extends TestCase
{
    public function testHasBlockPolicy(): void
    {
        self::assertSame('block', BackpressurePolicy::Block->value);
    }

    public function testHasDropPolicy(): void
    {
        self::assertSame('drop', BackpressurePolicy::Drop->value);
    }

    public function testHasErrorPolicy(): void
    {
        self::assertSame('error', BackpressurePolicy::Error->value);
    }

    public function testAllPoliciesAreDistinct(): void
    {
        self::assertCount(
            3,
            array_unique([
                BackpressurePolicy::Block->value,
                BackpressurePolicy::Drop->value,
                BackpressurePolicy::Error->value,
            ]),
        );
    }
}
