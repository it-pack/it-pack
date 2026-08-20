<?php

declare(strict_types=1);

namespace _Fixture;

class ThrowsDocClass
{
    /**
     * @throws \RuntimeException when the wheel comes off
     * @throws \DomainException if the input is invalid
     */
    public function run(): void
    {
    }
}
