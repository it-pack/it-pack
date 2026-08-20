<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Contract;

interface TempFileFactoryInterface
{
    public function build(): ?string;

    public function require(): string;
}