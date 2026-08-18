<?php

declare(strict_types=1);

namespace _Fixture;

class Collaborator
{
    public function id(): int
    {
        return 42;
    }

    public function name(): string
    {
        return 'collaborator';
    }
}
