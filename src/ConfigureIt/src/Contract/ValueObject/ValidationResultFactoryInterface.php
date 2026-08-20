<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Contract\ValueObject;

interface ValidationResultFactoryInterface
{
    /**
     * @param list<array{path: string, message: string}> $errors
     */
    public function build(
        bool $valid,
        array $errors = [],
    ): ValidationResultInterface;
}