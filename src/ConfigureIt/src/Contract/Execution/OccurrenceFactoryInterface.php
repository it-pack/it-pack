<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Contract\Execution;

interface OccurrenceFactoryInterface
{
    /**
     * @param array<mixed> $payload
     */
    public function build(
        string $type,
        float $at,
        array $payload = [],
    ): OccurrenceInterface;
}
