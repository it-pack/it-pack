<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\OpenTelemetry\Contract;

use ItPack\RunIt\Contract\Domain\ObservationSeverityInterface;
use OpenTelemetry\API\Logs\Severity;

/**
 * Maps RunIt observation severities to OpenTelemetry log severities.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#ObservationSeverity
 */
interface SeverityMapInterface
{
    public function toSeverity(ObservationSeverityInterface $severity): Severity;

    public function toNumber(ObservationSeverityInterface $severity): int;

    public function toText(ObservationSeverityInterface $severity): string;
}
