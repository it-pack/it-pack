<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Contract\Execution;

interface OccurrenceInterface
{
    public function type(): string;

    public function at(): float;

    /**
     * @return array<string, mixed>
     */
    public function payload(): array;
}