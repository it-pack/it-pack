<?php

declare(strict_types=1);

namespace Tests\Support\Fixture;

/**
 * Fixture whose short name already carries the "Interface" suffix, exercising the
 * marker branch of {@see \ItPack\DeriveIt\Generator\Naming::derivedInterfaceShortName}
 * (R4-7 / F-5 — extracted from NamingTest).
 */
class NamingEndsWithInterface
{
    public function x(): void
    {
    }
}
