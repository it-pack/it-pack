<?php

declare(strict_types=1);

namespace ItPack\HashIt\src;

interface HashUpdaterInterface
{
    public function update(string $data): void;
}