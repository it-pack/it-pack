<?php

declare(strict_types=1);

namespace Tests\Support\Fixture;

/**
 * Fixture returning a same-ownership namespaced type, which the leakage auditor
 * must allow (R4-7 / F-5 — extracted from LeakageAuditorTest).
 */
class LeakOwnedReturn
{
    public function go(): LeakBuiltinReturn
    {
        return new LeakBuiltinReturn();
    }
}
