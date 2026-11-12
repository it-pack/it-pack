<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\OpenTelemetry\Contract;

/** Optional capability for mapping sanitized observation projections. */
interface RedactedObservationLogMapperInterface
{
    /** @param array<string, mixed> $normalized */
    public function mapRedactedObservation(array $normalized): void;
}
