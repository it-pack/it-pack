<?php

declare(strict_types=1);

namespace ItPack\DeriveItRunit\Tests\Fixture;

/**
 * Sample derivation target, autoloadable inside the bridge test namespace
 * (R4-7 / F-5 — extracted from DerivationExecutionFunctionalTest).
 */
class ReportWidget
{
    public function render(string $template): string
    {
        return $template;
    }

    public function name(): string
    {
        return 'report';
    }
}
