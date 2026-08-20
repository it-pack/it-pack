<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Contract\Execution;

interface ExecutionContextInterface
{
    public function environment(): string;

    /**
     * @return array<string, string>
     */
    public function attributes(): array;

    public function attribute(string $key): ?string;
}
