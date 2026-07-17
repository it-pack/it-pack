<?php

declare(strict_types=1);

namespace ItPack\HashIt;

interface HashUpdaterInterface
{
    public function update(string $data): void;
}