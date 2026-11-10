<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\OpenTelemetry\Contract;

use ItPack\RunIt\Contract\Domain\ActivityInterface;

/**
 * Maps a completed RunIt activity to an OpenTelemetry span.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Activity
 */
interface ActivitySpanMapperInterface
{
    public function map(ActivityInterface $activity): void;

    /**
     * Maps a redacted activity's normalized projection to an OpenTelemetry span.
     *
     * @param array<string, mixed> $normalized redacted activity normalization
     */
    public function mapRedactedActivity(array $normalized): void;
}
