<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Factory;

use ItPack\AsyncIt\Contract\TaskIdInterface;

interface TaskIdFactoryInterface
{
    public function create(): TaskIdInterface;

    public function createWithId(
        string $id,
    ): TaskIdInterface;
}
