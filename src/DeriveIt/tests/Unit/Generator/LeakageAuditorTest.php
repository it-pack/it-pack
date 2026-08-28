<?php

declare(strict_types=1);

namespace Tests\Unit\Generator;

use ItPack\DeriveIt\DeriveIt;
use ItPack\DeriveIt\Exception\LeakageDetectedException;
use ItPack\DeriveIt\Generator\DeriveConfig;
use PHPUnit\Framework\TestCase;
use Tests\Support\Fixture\LeakBuiltinReturn;
use Tests\Support\Fixture\LeakForeignReturn;
use Tests\Support\Fixture\LeakOwnedReturn;

final class LeakageAuditorTest extends TestCase
{
    public function testForeignReturnTypeIsFlagged(): void
    {
        $this->expectException(LeakageDetectedException::class);
        DeriveIt::deriveWithResult(LeakForeignReturn::class, new DeriveConfig()->withAuditLeakage(true));
    }

    public function testBuiltinReturnTypeIsAllowed(): void
    {
        $result = DeriveIt::deriveWithResult(
            LeakBuiltinReturn::class,
            new DeriveConfig()->withAuditLeakage(true),
        );

        self::assertNotEmpty($result->source);
    }

    public function testOwnedNamespacedReturnTypeIsAllowed(): void
    {
        $result = DeriveIt::deriveWithResult(
            LeakOwnedReturn::class,
            new DeriveConfig()->withAuditLeakage(true),
        );

        self::assertNotEmpty($result->source);
    }
}
