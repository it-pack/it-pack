<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Contract\ValueObject;

interface ValidationResultFactoryInterface
{
    public function build(
        bool $valid,
        array $errors = [],
    ): ValidationResultInterface;
}