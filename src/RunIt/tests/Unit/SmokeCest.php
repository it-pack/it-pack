<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\Support\UnitTester;

final class SmokeCest
{
    public function codeceptionIsConfigured(UnitTester $tester): void
    {
        $tester->assertTrue(true);
    }
}
