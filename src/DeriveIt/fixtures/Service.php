<?php

declare(strict_types=1);

namespace _Fixture;

class Service
{
    public function name(): string
    {
        return 'svc';
    }

    public function collaborator(): Collaborator
    {
        return new Collaborator();
    }

    public function maybe(): ?Collaborator
    {
        return null;
    }

    public function reset(): void
    {
    }
}
