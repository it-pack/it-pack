<?php

declare(strict_types=1);

namespace _Fixture;

class OwnsPreExisting implements PreExistingInterface
{
    public function fromInterfaceOne(): void
    {
    }

    public function id(): int
    {
        return 1;
    }
}
