<?php

declare(strict_types=1);

namespace ItPack\HashIt;

interface HashContextInterface
{
    public function update(string $data): void;

    public function finalize(bool $binary): string;
}
