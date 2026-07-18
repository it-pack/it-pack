<?php

declare(strict_types=1);

namespace ItPack\HashIt;

use Stringable;

interface HashInterface extends Stringable
{
    public function algorithm(): string;

    public function length(): int;

    public function binary(): bool;

    public function value(): string;

    public function equals(HashInterface $other): bool;
}
