<?php

declare(strict_types=1);

namespace _Fixture;

class OwnsCountable implements \Countable
{
    public function count(): int
    {
        return 3;
    }

    public function name(): string
    {
        return 'countable';
    }
}
