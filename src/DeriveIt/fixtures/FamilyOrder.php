<?php

declare(strict_types=1);

namespace _Fixture;

class FamilyOrder
{
    public function id(): int
    {
        return 7;
    }

    public function lineItem(): FamilyLineItem
    {
        return new FamilyLineItem();
    }

    public function maybeLineItem(): ?FamilyLineItem
    {
        return null;
    }
}
