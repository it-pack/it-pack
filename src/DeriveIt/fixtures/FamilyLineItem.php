<?php

declare(strict_types=1);

namespace _Fixture;

class FamilyLineItem
{
    public function total(): int
    {
        return 100;
    }

    public function label(): string
    {
        return 'item';
    }
}
