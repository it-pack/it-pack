<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Contract\Execution;

interface OccurrenceFactoryInterface
{
    public function build(
        string $type,
        float $at,
        array $payload = [],
    ): OccurrenceInterface;
}