<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\OpenTelemetry\Contract;

interface RedactedMeasurementInstrumentMapperInterface
{
    /** @param array<string, mixed> $normalized the classified, bounded normalization */
    public function mapRedactedMeasurement(array $normalized): void;
}
