<?php

declare(strict_types=1);

namespace Tests\Unit\Generator;

use ItPack\DeriveIt\Exception\NamingCollisionException;
use ItPack\DeriveIt\Generator\Naming;
use PHPUnit\Framework\TestCase;
use Tests\Support\Fixture\NamingCollide;
use Tests\Support\Fixture\NamingCollideInterface;
use Tests\Support\Fixture\NamingEndsWithInterface;

final class NamingTest extends TestCase
{
    public function testDerivedInterfaceShortNameKeepsExistingInterfaceSuffix(): void
    {
        $name = Naming::derivedInterfaceShortName(new \ReflectionClass(NamingEndsWithInterface::class));

        self::assertStringContainsString('NamingEndsWithInterface', $name);
        self::assertNotSame('NamingEndsWithInterface', $name);
    }

    public function testAssertNoCollisionThrowsOnConcreteClash(): void
    {
        // The colliding concrete port name is autoloaded so Naming::assertNoCollision
        // (which probes loaded classes) can detect it.
        class_exists(NamingCollideInterface::class);

        $this->expectException(NamingCollisionException::class);
        Naming::assertNoCollision(new \ReflectionClass(NamingCollide::class));
    }

    public function testAssertNoCollisionPassesWhenFree(): void
    {
        $reflection = new \ReflectionClass(NamingEndsWithInterface::class);

        self::assertSame('NamingEndsWithInterface', $reflection->getShortName());
        Naming::assertNoCollision($reflection);
    }
}
