<?php

declare(strict_types=1);

namespace Tests\Support\Fixture;

/**
 * Consumes {@see CliSampleTrait} to exercise per-trait derivation
 * (R4-7 / F-5 — extracted from DeriveCommandTest).
 */
class CliSampleTraitUser
{
    use CliSampleTrait;

    public function selfDo(): void
    {
    }
}
