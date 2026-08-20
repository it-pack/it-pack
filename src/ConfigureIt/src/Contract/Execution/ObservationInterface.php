<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Contract\Execution;

interface ObservationInterface
{
    public function level(): string;

    public function message(): string;

    /**
     * @return array<string, mixed>
     */
    public function context(): array;
}