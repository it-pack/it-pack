<?php

declare(strict_types=1);

namespace Tests\Support\Fixture;

/**
 * Fixture returning a builtin type, which the leakage auditor must allow
 * (R4-7 / F-5 — extracted from LeakageAuditorTest).
 */
class LeakBuiltinReturn
{
    public function go(): int
    {
        return 0;
    }
}
