<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Contract\ValueObject;

interface ValidationResultInterface
{
    public function valid(): bool;

    /**
     * @return list<array{path: string, message: string}>
     */
    public function errors(): array;
}
