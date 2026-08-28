<?php

declare(strict_types=1);

namespace Tests\Support\Fixture;

use Foreign\Ns\Thing;

/**
 * Fixture returning a foreign (namespaced) type, which the leakage auditor must
 * flag (R4-7 / F-5 — extracted from LeakageAuditorTest).
 */
class LeakForeignReturn
{
    public function go(): Thing
    {
        return new Thing();
    }
}
