<?php

declare(strict_types=1);

namespace Tests\Support\Fixture;

/**
 * Fixture whose derived port name collides with a concrete class, exercising the
 * collision branch of {@see \ItPack\DeriveIt\Generator\Naming::assertNoCollision}
 * (R4-7 / F-5 — extracted from NamingTest).
 */
class NamingCollide
{
    public function z(): void
    {
    }
}
