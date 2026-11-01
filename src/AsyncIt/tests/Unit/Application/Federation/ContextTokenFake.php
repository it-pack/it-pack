<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Federation;

use ItPack\RunIt\Contract\Port\ContextTokenInterface;

final class ContextTokenFake implements ContextTokenInterface
{
    public function id(): int
    {
        return 0;
    }
}
