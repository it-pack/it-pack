<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\CloudEvents;

use ItPack\RunIt\Contract\Domain\OccurrenceInterface;

/**
 * Contract for mapping a RunIt occurrence to a CloudEvent.
 *
 * @see https://cloudevents.io/
 */
interface OccurrenceToCloudEventMapperInterface
{
    public function map(OccurrenceInterface $occurrence, ?object $policy = null): object;
}
