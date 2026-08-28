<?php

declare(strict_types=1);

namespace Foreign\Ns;

/**
 * Foreign (non-port) type used to exercise the namespaced branch of
 * {@see \ItPack\DeriveIt\Generator\LeakageAuditor::isAllowed}
 * (R4-7 / F-5 — extracted from LeakageAuditorTest).
 */
class Thing
{
    public function id(): int
    {
        return 0;
    }
}
