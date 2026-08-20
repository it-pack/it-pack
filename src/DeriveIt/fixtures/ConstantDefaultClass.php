<?php

declare(strict_types=1);

namespace _Fixture;

class ConstantDefaultClass
{
    public const DEFAULT = 'default';

    public function withDefault(string $value = self::DEFAULT): void
    {
    }
}
