<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Contract\Execution;

use ItPack\ConfigureIt\ValueObject\OccurrenceType;

interface OccurrenceFactoryInterface
{
    /**
     * @param array<mixed> $payload
     */
    public function build(
        OccurrenceType $type,
        float $at,
        array $payload = [],
    ): OccurrenceInterface;
}
